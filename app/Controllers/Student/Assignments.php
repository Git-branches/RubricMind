<?php

namespace App\Controllers\Student;

use App\Controllers\BaseController;
use App\Libraries\DocumentText;
use App\Libraries\DocumentTextException;
use App\Libraries\SubmissionFiles;
use App\Libraries\SubmissionRules;
use App\Models\AssignmentModel;
use App\Models\ClassModel;
use App\Models\EvaluationModel;
use App\Models\RubricModel;
use App\Models\SubmissionModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class Assignments extends BaseController
{
    // Essay length limits, in characters.
    private const MIN_LENGTH = 20;
    private const MAX_LENGTH = 50000;

    public function show(int $id): string
    {
        return $this->page($this->findOr404($id));
    }

    public function submit(int $id): RedirectResponse|string
    {
        $assignment  = $this->findOr404($id);
        $class       = model(ClassModel::class)->find($assignment['class_id']);
        $submissions = model(SubmissionModel::class);
        $existing    = $submissions->findForStudent($id, $this->userId());
        $back        = redirect()->to(site_url("student/assignments/{$id}"));

        if (! SubmissionRules::acceptsSubmissions($assignment, $class)) {
            return $back->with('error', 'This assignment is no longer accepting submissions.');
        }

        if ($existing !== null && ! SubmissionRules::canEdit($existing, $assignment, $class)) {
            return $back->with('error', 'Your submission can no longer be edited.');
        }

        $content = trim(str_replace("\r\n", "\n", (string) $this->request->getPost('content')));
        $rules   = ['content' => [
            'label'  => 'essay',
            'rules'  => 'required|min_length[' . self::MIN_LENGTH . ']|max_length[' . self::MAX_LENGTH . ']',
        ]];

        if (! $this->validateData(['content' => $content], $rules)) {
            return $this->page($assignment, $content, $this->validator->getErrors());
        }

        // A file read with "Upload file" is attached unless the student unticked it.
        $file = $this->request->getPost('attach_file') === '1' ? SubmissionFiles::takePending($id) : null;
        SubmissionFiles::delete(SubmissionFiles::pending($id)['path'] ?? null);
        SubmissionFiles::forgetPending($id);

        $isLate       = $existing === null && SubmissionRules::isPastDue($assignment);
        $submissionId = $submissions->saveEssay($existing, $id, $this->userId(), $content, $isLate, $file);

        // The new file replaces the old one.
        if ($file !== null && $existing !== null) {
            SubmissionFiles::delete($existing['file_path']);
        }

        audit_log($existing === null ? 'submission.created' : 'submission.updated', 'submission', $submissionId, array_filter([
            'assignment_id' => $id,
            'word_count'    => SubmissionModel::countWords($content),
            'late'          => $isLate,
            'file'          => $file['name'] ?? null,
        ], static fn ($v) => $v !== null));

        $message = match (true) {
            $existing !== null => 'Your submission has been updated.',
            $isLate            => 'Your essay has been submitted. It was submitted after the due date, so it is marked late.',
            default            => 'Your essay has been submitted.',
        };

        return $back->with('success', $message);
    }

    /**
     * "Upload file": reads the text from a PDF or DOCX into the essay box so the
     * student can check it. Nothing is submitted until they press Submit.
     */
    public function upload(int $id): RedirectResponse|string
    {
        $assignment = $this->findOr404($id);
        $class      = model(ClassModel::class)->find($assignment['class_id']);
        $existing   = model(SubmissionModel::class)->findForStudent($id, $this->userId());
        $back       = redirect()->to(site_url("student/assignments/{$id}"));

        if (! service('systemSettings')->uploadsEnabled()) {
            return $back->with('error', 'File uploads are turned off. Type or paste your essay instead.');
        }

        if (! SubmissionRules::acceptsSubmissions($assignment, $class)
            || ($existing !== null && ! SubmissionRules::canEdit($existing, $assignment, $class))) {
            return $back->with('error', 'Your submission can no longer be changed.');
        }

        $file      = $this->request->getFile('essay_file');
        $extension = $file === null ? '' : strtolower($file->getClientExtension());

        $error = match (true) {
            $file === null || $file->getError() === UPLOAD_ERR_NO_FILE           => 'Choose a PDF or Word (.docx) file to upload.',
            in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true),
            $file->isValid() && $file->getSize() > SubmissionFiles::MAX_BYTES  => 'The file is too large. The limit is ' . (SubmissionFiles::MAX_BYTES / 1024 / 1024) . ' MB.',
            ! $file->isValid()                                                 => 'The file could not be uploaded. Please try again.',
            ! in_array($extension, DocumentText::EXTENSIONS, true)             => 'Upload a PDF or Word (.docx) file. Older .doc files are not supported: save it as .docx or PDF first.',
            default                                                            => null,
        };

        if ($error !== null) {
            return $this->page($assignment, null, ['essay_file' => $error]);
        }

        try {
            $text = DocumentText::extract($file->getTempName(), $extension);
        } catch (DocumentTextException $e) {
            return $this->page($assignment, null, ['essay_file' => $e->getMessage()]);
        }

        $pending = SubmissionFiles::storePending($file, $extension, $id);

        return $this->page($assignment, $text, [], $pending);
    }

    /**
     * Downloads the student's own uploaded file.
     */
    public function file(int $id): ResponseInterface
    {
        $this->findOr404($id);
        $submission = model(SubmissionModel::class)->findForStudent($id, $this->userId());

        if ($submission === null || $submission['file_path'] === null || ! is_file(SubmissionFiles::path($submission['file_path']))) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->download(SubmissionFiles::path($submission['file_path']), null, true)->setFileName($submission['file_name']);
    }

    private function page(array $assignment, ?string $draft = null, array $errors = [], ?array $pendingFile = null): string
    {
        $class      = model(ClassModel::class)->find($assignment['class_id']);
        $submission = model(SubmissionModel::class)->findForStudent($assignment['id'], $this->userId());

        // Grades and feedback are visible only after the teacher releases them (FR-12).
        $evaluations = model(EvaluationModel::class);
        $evaluation  = $submission !== null && $submission['status'] === 'released'
            ? $evaluations->findBySubmission($submission['id'])
            : null;

        $settings = service('systemSettings');

        return view('student/assignments/show', [
            // Tell students when AI may help review essays (§5).
            'aiNotice'    => $settings->aiEnabled() && $settings->aiNoticeEnabled(),
            'aiSimulated' => service('essayAnalyzer')->isSimulated(),
            'aiProvider'  => $settings->aiProviderName(),
            'evaluation'  => $evaluation,
            'scores'     => $evaluation === null ? [] : $evaluations->scores($evaluation['id']),
            'assignment' => $assignment,
            'class'      => $class,
            'criteria'   => model(RubricModel::class)->criteriaWithLevels($assignment['rubric_id']),
            'submission' => $submission,
            'canSubmit'  => $submission === null && SubmissionRules::acceptsSubmissions($assignment, $class),
            'canEdit'    => $submission !== null && SubmissionRules::canEdit($submission, $assignment, $class),
            'isPastDue'  => SubmissionRules::isPastDue($assignment),
            'draft'      => $draft,
            'errors'     => $errors,
            // Text just read from this file is in the essay box, waiting to be submitted.
            'pendingFile'    => $pendingFile ?? ($draft !== null ? SubmissionFiles::pending($assignment['id']) : null),
            'uploadsEnabled' => $settings->uploadsEnabled(),
        ]);
    }

    private function findOr404(int $id): array
    {
        return model(AssignmentModel::class)->findForStudent($id, $this->userId()) ?? throw PageNotFoundException::forPageNotFound();
    }
}
