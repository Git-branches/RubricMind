<?php

namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Libraries\Ai\AiEvaluationService;
use App\Libraries\GradingWorkflow;
use App\Libraries\LessonPlanner;
use App\Libraries\StyleTracker;
use App\Libraries\SubmissionFiles;
use App\Models\AiRunModel;
use App\Models\AssignmentModel;
use App\Models\ClassModel;
use App\Models\EvaluationModel;
use App\Models\RubricModel;
use App\Models\StyleFlagModel;
use App\Models\SubmissionModel;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class Submissions extends BaseController
{
    private const MIN_REOPEN_REASON_LENGTH = 5;

    // At most this many AI requests per teacher in the time window.
    private const MAX_AI_REQUESTS   = 20;
    private const AI_WINDOW_SECONDS = 600;

    /**
     * The essay with its grading panel. Viewing does not lock it (FR-05);
     * only Start Grading does.
     */
    public function show(int $id): string
    {
        return $this->page($this->findOr404($id));
    }

    /**
     * Downloads the PDF or DOCX file the student uploaded.
     */
    public function file(int $id): ResponseInterface
    {
        $submission = $this->findOr404($id);

        if ($submission['file_path'] === null || ! is_file(SubmissionFiles::path($submission['file_path']))) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->download(SubmissionFiles::path($submission['file_path']), null, true)->setFileName($submission['file_name']);
    }

    public function start(int $id): RedirectResponse
    {
        $submission = $this->findOr404($id);
        $assignment = model(AssignmentModel::class)->find($submission['assignment_id']);

        (new GradingWorkflow())->start($submission, $assignment, $this->userId());

        // #grade opens the grading modal right away.
        return redirect()->to(site_url("teacher/submissions/{$id}#grade"))
            ->with('success', 'Grading started. The student can no longer edit this essay.');
    }

    /**
     * Runs AI suggestions. This also starts grading if it has not started yet.
     */
    public function requestAi(int $id): RedirectResponse
    {
        $submission = $this->findOr404($id);
        $back       = redirect()->to(site_url("teacher/submissions/{$id}#grading"));

        if (! service('systemSettings')->aiEnabled()) {
            return $back->with('error', 'AI suggestions are turned off.');
        }

        if (! GradingWorkflow::isEditable($submission)) {
            return $back->with('error', 'This grade is finalized. Reopen it before requesting new AI suggestions.');
        }

        // Protects the AI provider's quota (and cost) from repeated clicks.
        if (! service('throttler')->check('ai-teacher-' . $this->userId(), self::MAX_AI_REQUESTS, self::AI_WINDOW_SECONDS)) {
            return $back->with('warning', 'You have requested AI suggestions many times in a short period. Please wait a few minutes and try again.');
        }

        $assignment = model(AssignmentModel::class)->find($submission['assignment_id']);
        $run        = (new AiEvaluationService(service('essayAnalyzer')))->request($submission, $assignment, $this->userId());

        return $run['status'] === 'done'
            ? $back->with('success', 'AI suggestions are ready. Review them, then save your own scores.')
            : $back->with('error', 'AI suggestions failed: ' . $run['error'] . ' You can try again or grade manually.');
    }

    /**
     * Saves scores and feedback. With intent=finalize it also finalizes the grade.
     */
    public function grade(int $id): RedirectResponse|string
    {
        $submission = $this->findOr404($id);
        $evaluation = model(EvaluationModel::class)->findBySubmission($id);
        $back       = redirect()->to(site_url("teacher/submissions/{$id}#grading"));

        if ($evaluation === null) {
            return $back->with('error', 'Start grading before entering scores.');
        }

        if (! GradingWorkflow::isEditable($submission)) {
            return $back->with('error', 'This grade is finalized. Reopen it to make changes.');
        }

        $assignment = model(AssignmentModel::class)->find($submission['assignment_id']);
        $criteria   = model(RubricModel::class)->criteriaWithLevels($assignment['rubric_id']);
        $parsed     = GradingWorkflow::parseScores($criteria, $this->request->getPost('scores'));
        $feedback   = trim((string) $this->request->getPost('student_feedback'));
        $notes      = trim((string) $this->request->getPost('teacher_notes'));

        foreach (['student feedback' => $feedback, 'teacher notes' => $notes] as $label => $text) {
            if (mb_strlen($text) > GradingWorkflow::MAX_FEEDBACK_LENGTH) {
                $parsed['errors'][] = 'The ' . $label . ' must be ' . GradingWorkflow::MAX_FEEDBACK_LENGTH . ' characters or fewer.';
            }
        }

        if ($parsed['errors'] !== []) {
            return $this->page($submission, [
                'errors'   => $parsed['errors'],
                'posted'   => $parsed['scores'],
                'feedback' => $feedback,
                'notes'    => $notes,
            ]);
        }

        $workflow = new GradingWorkflow();
        $workflow->save($evaluation, $submission, $parsed['scores'], $feedback ?: null, $notes ?: null);

        if ($this->request->getPost('intent') !== 'finalize') {
            return $back->with('success', 'Scores and feedback saved.');
        }

        $error = $workflow->finalize($evaluation, $this->findOr404($id));

        if ($error !== null) {
            return $back->with('warning', 'Scores saved, but the grade was not finalized. ' . $error);
        }

        // The last essay of the assignment is graded: prepare a draft lesson plan from the class results (FR-11).
        $planId = (new LessonPlanner(db_connect()))->generateWhenGradingIsDone($assignment, $this->userId());

        return $back->with('success', $planId === null
            ? 'Grade finalized. Release it when the student should see it.'
            : 'Grade finalized. All essays for this assignment are graded, so a draft lesson plan was created from the class results (see Insights).');
    }

    public function release(int $id): RedirectResponse
    {
        $submission = $this->findOr404($id);
        $evaluation = model(EvaluationModel::class)->findBySubmission($id);
        $back       = redirect()->to(site_url("teacher/submissions/{$id}#grading"));

        if ($evaluation === null || $submission['status'] !== 'finalized') {
            return $back->with('error', 'Only a finalized grade can be released.');
        }

        (new GradingWorkflow())->release($evaluation, $submission);

        return $back->with('success', 'Grade and feedback released to the student.');
    }

    public function reopen(int $id): RedirectResponse
    {
        $submission = $this->findOr404($id);
        $evaluation = model(EvaluationModel::class)->findBySubmission($id);
        $back       = redirect()->to(site_url("teacher/submissions/{$id}#grading"));
        $reason     = trim((string) $this->request->getPost('reason'));

        if ($evaluation === null || ! in_array($submission['status'], ['finalized', 'released'], true)) {
            return $back->with('error', 'Only a finalized or released grade can be reopened.');
        }

        if (mb_strlen($reason) < self::MIN_REOPEN_REASON_LENGTH || mb_strlen($reason) > 1000) {
            return $back->with('error', 'Give a short reason (5 to 1000 characters) for changing a finalized grade.');
        }

        (new GradingWorkflow())->reopen($evaluation, $submission, $reason);

        $message = $submission['status'] === 'released'
            ? 'Grade reopened. It is hidden from the student until you release it again.'
            : 'Grade reopened for changes.';

        return $back->with('success', $message);
    }

    /**
     * Records that the teacher looked at the Style Growth Tracker result (FR-09).
     * This is only a note; it never changes the grade or the student's record.
     */
    public function reviewStyle(int $id): RedirectResponse
    {
        $submission = $this->findOr404($id);
        $note       = trim((string) $this->request->getPost('review_note'));
        $back       = redirect()->to(site_url("teacher/submissions/{$id}#style"));

        if (mb_strlen($note) > 1000) {
            return $back->with('error', 'The review note must be 1000 characters or fewer.');
        }

        $flags = model(StyleFlagModel::class);

        if ($flags->findBySubmission($id) !== null) {
            return $back->with('warning', 'You have already reviewed this style comparison.');
        }

        $comparison = StyleTracker::compare($submission['content'], model(SubmissionModel::class)->styleHistory($submission));

        $flags->insert([
            'submission_id' => $id,
            'status'        => $comparison['status'],
            'history_count' => $comparison['history_count'],
            'details'       => json_encode($comparison['metrics']),
            'reviewed_by'   => $this->userId(),
            'review_note'   => $note === '' ? null : $note,
            'reviewed_at'   => date('Y-m-d H:i:s'),
        ]);

        audit_log('style.reviewed', 'submission', $id, ['status' => $comparison['status']]);

        return $back->with('success', 'Style comparison marked as reviewed.');
    }

    /**
     * @param array{errors?: list<string>, posted?: array, feedback?: string, notes?: string} $failed Values from a failed save.
     */
    private function page(array $submission, array $failed = []): string
    {
        $assignment  = model(AssignmentModel::class)->find($submission['assignment_id']);
        $evaluations = model(EvaluationModel::class);
        $evaluation  = $evaluations->findBySubmission($submission['id']);
        $submissions = model(SubmissionModel::class);
        $runs        = model(AiRunModel::class);
        $aiRun       = $runs->latestForSubmission($submission['id']);

        return view('teacher/submissions/show', [
            'style'       => StyleTracker::compare($submission['content'], $submissions->styleHistory($submission)),
            'styleReview' => model(StyleFlagModel::class)->findBySubmission($submission['id']),
            'aiEnabled'   => service('systemSettings')->aiEnabled(),
            'aiSimulated' => service('essayAnalyzer')->isSimulated(),
            'aiRun'       => $aiRun,
            'ai'          => $aiRun !== null && $aiRun['status'] === 'done' ? $runs->results($aiRun) : null,
            'submission' => $submission,
            'assignment' => $assignment,
            'class'      => model(ClassModel::class)->find($assignment['class_id']),
            'student'    => model(UserModel::class)->find($submission['student_id']),
            'criteria'   => model(RubricModel::class)->criteriaWithLevels($assignment['rubric_id']),
            'revisions'  => $submissions->revisions($submission['id']),
            'evaluation' => $evaluation,
            'scores'     => $evaluation === null ? [] : $evaluations->scores($evaluation['id']),
            'editable'   => $evaluation !== null && GradingWorkflow::isEditable($submission),
            'nextId'     => $submissions->nextToGrade($assignment['id'], $submission['id']),
            'failed'     => $failed,
            'errors'     => $failed['errors'] ?? [],
        ]);
    }

    private function findOr404(int $id): array
    {
        return model(SubmissionModel::class)->findForTeacher($id, $this->userId()) ?? throw PageNotFoundException::forPageNotFound();
    }
}
