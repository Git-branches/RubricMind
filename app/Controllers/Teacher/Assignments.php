<?php

namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Libraries\GradingWorkflow;
use App\Models\AssignmentModel;
use App\Models\ClassModel;
use App\Models\EvaluationModel;
use App\Models\RubricModel;
use App\Models\SubmissionModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

class Assignments extends BaseController
{
    /**
     * Allowed status changes: action => [from status, to status, audit action].
     */
    private const TRANSITIONS = [
        'publish'   => ['draft', 'published', 'assignment.published'],
        'unpublish' => ['published', 'draft', 'assignment.unpublished'],
        'close'     => ['published', 'closed', 'assignment.closed'],
        'reopen'    => ['closed', 'published', 'assignment.reopened'],
    ];

    private AssignmentModel $assignments;

    public function __construct()
    {
        $this->assignments = model(AssignmentModel::class);
    }

    public function new(int $classId): RedirectResponse|string
    {
        $class = $this->findClassOr404($classId);

        if ($class['status'] !== 'active') {
            return redirect()->to(site_url("teacher/classes/{$classId}"))->with('error', 'Restore this class before adding assignments.');
        }

        return $this->form($class, null);
    }

    public function create(int $classId): RedirectResponse|string
    {
        $class = $this->findClassOr404($classId);

        if ($class['status'] !== 'active') {
            return redirect()->to(site_url("teacher/classes/{$classId}"))->with('error', 'Restore this class before adding assignments.');
        }

        $data   = $this->postedAssignmentData();
        $errors = $this->validateAssignment($data);

        if ($errors !== []) {
            return $this->form($class, null, $data, $errors);
        }

        $id = $this->assignments->insert($this->toRecord($data) + ['class_id' => $classId, 'status' => 'draft']);
        audit_log('assignment.created', 'assignment', $id, ['title' => $data['title']]);

        return redirect()->to(site_url("teacher/assignments/{$id}"))
            ->with('success', 'Assignment saved as a draft. Publish it when students should see it.');
    }

    public function show(int $id): string
    {
        $assignment = $this->findOr404($id);
        $rubrics    = model(RubricModel::class);

        return view('teacher/assignments/show', [
            'assignment' => $assignment,
            'class'      => model(ClassModel::class)->find($assignment['class_id']),
            'rubric'     => $rubrics->find($assignment['rubric_id']),
            'criteria'   => $rubrics->criteriaWithLevels($assignment['rubric_id']),
            'roster'     => model(SubmissionModel::class)->rosterForAssignment($id, $assignment['class_id']),
        ]);
    }

    public function edit(int $id): string
    {
        $assignment = $this->findOr404($id);

        return $this->form(model(ClassModel::class)->find($assignment['class_id']), $assignment);
    }

    public function update(int $id): RedirectResponse|string
    {
        $assignment = $this->findOr404($id);
        $class      = model(ClassModel::class)->find($assignment['class_id']);
        $data       = $this->postedAssignmentData();
        $errors     = $this->validateAssignment($data);

        // Changing the rubric after grading started would detach existing scores.
        if ($errors === [] && (int) $data['rubric_id'] !== $assignment['rubric_id']) {
            $currentRubric = model(RubricModel::class)->find($assignment['rubric_id']);

            if ($currentRubric['locked_at'] !== null) {
                $errors['rubric_id'] = 'Grading has started with the current rubric, so it cannot be changed.';
            }
        }

        if ($errors !== []) {
            return $this->form($class, $assignment, $data, $errors);
        }

        $this->assignments->update($id, $this->toRecord($data));
        audit_log('assignment.updated', 'assignment', $id);

        return redirect()->to(site_url("teacher/assignments/{$id}"))->with('success', 'Assignment updated.');
    }

    public function updateStatus(int $id): RedirectResponse
    {
        $assignment = $this->findOr404($id);
        $action     = (string) $this->request->getPost('action');
        $back       = redirect()->to(site_url("teacher/assignments/{$id}"));

        if (! isset(self::TRANSITIONS[$action]) || self::TRANSITIONS[$action][0] !== $assignment['status']) {
            return $back->with('error', 'That status change is not available right now.');
        }

        if ($action === 'unpublish' && $this->assignments->hasSubmissions($id)) {
            return $back->with('error', 'Students have already submitted, so this assignment cannot go back to draft. Close it instead.');
        }

        if ($action === 'publish' || $action === 'reopen') {
            $class = model(ClassModel::class)->find($assignment['class_id']);

            if ($class['status'] !== 'active') {
                return $back->with('error', 'Restore the class before opening this assignment.');
            }
        }

        [$from, $status, $auditAction] = self::TRANSITIONS[$action];
        $this->assignments->update($id, ['status' => $status]);
        audit_log($auditAction, 'assignment', $id, ['from' => $from, 'to' => $status]);

        $messages = [
            'publish'   => 'Assignment published. Students in the class can now see it and submit.',
            'unpublish' => 'Assignment moved back to draft and hidden from students.',
            'close'     => 'Assignment closed. Students can no longer submit or edit.',
            'reopen'    => 'Assignment reopened for submissions.',
        ];

        return $back->with('success', $messages[$action]);
    }

    /**
     * Releases every finalized grade in this assignment at once (FR-12).
     */
    public function releaseAll(int $id): RedirectResponse
    {
        $this->findOr404($id);

        $finalized   = model(SubmissionModel::class)->finalizedForAssignment($id);
        $evaluations = model(EvaluationModel::class);
        $workflow    = new GradingWorkflow();

        foreach ($finalized as $submission) {
            $workflow->release($evaluations->findBySubmission($submission['id']), $submission);
        }

        $back = redirect()->to(site_url("teacher/assignments/{$id}#submissions"));

        return $finalized === []
            ? $back->with('warning', 'There are no finalized grades to release.')
            : $back->with('success', count($finalized) . ' grade(s) released to students.');
    }

    public function delete(int $id): RedirectResponse
    {
        $assignment = $this->findOr404($id);

        if ($this->assignments->hasSubmissions($id)) {
            return redirect()->to(site_url("teacher/assignments/{$id}"))
                ->with('error', 'This assignment has submissions, so it cannot be deleted. Close it instead.');
        }

        $this->assignments->delete($id);
        audit_log('assignment.deleted', 'assignment', $id, ['title' => $assignment['title']]);

        return redirect()->to(site_url("teacher/classes/{$assignment['class_id']}"))
            ->with('success', "Assignment \"{$assignment['title']}\" deleted.");
    }

    private function form(array $class, ?array $assignment, array $old = [], array $errors = []): string
    {
        return view('teacher/assignments/form', [
            'class'      => $class,
            'assignment' => $assignment,
            'rubrics'    => model(RubricModel::class)->forTeacher($this->userId()),
            'old'        => $old,
            'errors'     => $errors,
        ]);
    }

    private function findOr404(int $id): array
    {
        return $this->assignments->findForTeacher($id, $this->userId()) ?? throw PageNotFoundException::forPageNotFound();
    }

    private function findClassOr404(int $classId): array
    {
        return model(ClassModel::class)->findForTeacher($classId, $this->userId()) ?? throw PageNotFoundException::forPageNotFound();
    }

    private function postedAssignmentData(): array
    {
        return [
            'title'        => trim((string) $this->request->getPost('title')),
            'instructions' => trim((string) $this->request->getPost('instructions')),
            'due_at'       => trim((string) $this->request->getPost('due_at')),
            'rubric_id'    => (string) $this->request->getPost('rubric_id'),
        ];
    }

    /**
     * @return array<string, string> Errors by field.
     */
    private function validateAssignment(array $data): array
    {
        $rules = [
            'title'        => ['label' => 'title', 'rules' => 'required|max_length[150]'],
            'instructions' => ['label' => 'instructions', 'rules' => 'required|max_length[10000]'],
            'due_at'       => ['label' => 'due date', 'rules' => 'required|valid_date[Y-m-d\TH:i]'],
            'rubric_id'    => ['label' => 'rubric', 'rules' => 'required|is_natural_no_zero'],
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validator->getErrors();
        }

        if (model(RubricModel::class)->findForTeacher((int) $data['rubric_id'], $this->userId()) === null) {
            return ['rubric_id' => 'Choose one of your rubrics.'];
        }

        return [];
    }

    /**
     * Converts validated form data into database values.
     */
    private function toRecord(array $data): array
    {
        return [
            'title'        => $data['title'],
            'instructions' => $data['instructions'],
            'due_at'       => str_replace('T', ' ', $data['due_at']) . ':00',
            'rubric_id'    => (int) $data['rubric_id'],
        ];
    }
}
