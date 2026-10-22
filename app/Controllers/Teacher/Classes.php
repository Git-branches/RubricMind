<?php

namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Models\AssignmentModel;
use App\Models\ClassModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

class Classes extends BaseController
{
    private ClassModel $classes;

    public function __construct()
    {
        $this->classes = model(ClassModel::class);
    }

    public function index(): string
    {
        $status = $this->request->getGet('status') === 'archived' ? 'archived' : 'active';

        return view('teacher/classes/index', [
            'classes' => $this->classes->forTeacher($this->userId(), $status),
            'status'  => $status,
        ]);
    }

    public function new(): string
    {
        return view('teacher/classes/form', ['class' => null]);
    }

    public function create(): RedirectResponse|string
    {
        $data = $this->postedClassData();

        if (! $this->validateData($data, $this->classRules())) {
            return view('teacher/classes/form', ['class' => null, 'old' => $data, 'errors' => $this->validator->getErrors()]);
        }

        $id = $this->classes->insert($data + ['teacher_id' => $this->userId(), 'status' => 'active']);
        audit_log('class.created', 'class', $id, ['name' => $data['name']]);

        return redirect()->to(site_url("teacher/classes/{$id}"))->with('success', 'Class created. You can now enroll students.');
    }

    public function show(int $id): string
    {
        $class = $this->findOr404($id);

        return view('teacher/classes/show', [
            'class'       => $class,
            'students'    => $this->classes->students($id),
            'assignments' => model(AssignmentModel::class)->forClass($id),
        ]);
    }

    public function edit(int $id): string
    {
        return view('teacher/classes/form', ['class' => $this->findOr404($id)]);
    }

    public function update(int $id): RedirectResponse|string
    {
        $class = $this->findOr404($id);
        $data  = $this->postedClassData();

        if (! $this->validateData($data, $this->classRules())) {
            return view('teacher/classes/form', ['class' => $class, 'old' => $data, 'errors' => $this->validator->getErrors()]);
        }

        $this->classes->update($id, $data);
        audit_log('class.updated', 'class', $id);

        return redirect()->to(site_url("teacher/classes/{$id}"))->with('success', 'Class updated.');
    }

    public function toggleArchive(int $id): RedirectResponse
    {
        $class  = $this->findOr404($id);
        $status = $class['status'] === 'active' ? 'archived' : 'active';

        // The archive date starts the data-retention period (see RetentionService).
        $this->classes->update($id, ['status' => $status, 'archived_at' => $status === 'archived' ? date('Y-m-d H:i:s') : null]);
        audit_log($status === 'archived' ? 'class.archived' : 'class.restored', 'class', $id);

        $message = $status === 'archived'
            ? 'Class archived. Students can still view it, but no new submissions are accepted.'
            : 'Class restored.';

        return redirect()->to(site_url("teacher/classes/{$id}"))->with('success', $message);
    }

    public function delete(int $id): RedirectResponse
    {
        $class = $this->findOr404($id);

        if (model(AssignmentModel::class)->where('class_id', $id)->countAllResults() > 0) {
            return redirect()->to(site_url("teacher/classes/{$id}"))
                ->with('error', 'This class has assignments, so it cannot be deleted. Archive it instead.');
        }

        $this->classes->delete($id);
        audit_log('class.deleted', 'class', $id, ['name' => $class['name']]);

        return redirect()->to(site_url('teacher/classes'))->with('success', "Class \"{$class['name']}\" deleted.");
    }

    public function unenroll(int $id, int $studentId): RedirectResponse
    {
        $this->findOr404($id);

        if ($this->classes->unenroll($id, $studentId)) {
            audit_log('class.student_removed', 'class', $id, ['student_id' => $studentId]);
        }

        return redirect()->to(site_url("teacher/classes/{$id}#students"))
            ->with('success', 'Student removed from the class. Their existing submissions are kept.');
    }

    /**
     * A new class code; the old one stops working at once (for a code that was shared too widely).
     */
    public function regenerateCode(int $id): RedirectResponse
    {
        $this->findOr404($id);

        $code = ClassModel::newJoinCode();
        $this->classes->update($id, ['join_code' => $code, 'join_code_enabled' => true]);
        audit_log('class.code_regenerated', 'class', $id);

        return redirect()->to(site_url("teacher/classes/{$id}#class-code"))
            ->with('success', 'New class code: ' . ClassModel::formatJoinCode($code) . '. The old code no longer works.');
    }

    public function toggleCode(int $id): RedirectResponse
    {
        $class   = $this->findOr404($id);
        $enabled = ! $class['join_code_enabled'];

        $this->classes->update($id, ['join_code_enabled' => $enabled]);
        audit_log($enabled ? 'class.code_enabled' : 'class.code_disabled', 'class', $id);

        return redirect()->to(site_url("teacher/classes/{$id}#class-code"))->with('success', $enabled
            ? 'Class code turned on. Students can join with it again.'
            : 'Class code turned off. No one can join with it until you turn it on.');
    }

    private function findOr404(int $id): array
    {
        return $this->classes->findForTeacher($id, $this->userId()) ?? throw PageNotFoundException::forPageNotFound();
    }

    private function postedClassData(): array
    {
        $optional = fn (string $field): ?string => trim((string) $this->request->getPost($field)) ?: null;

        return [
            'name'        => trim((string) $this->request->getPost('name')),
            'section'     => $optional('section'),
            'school_year' => $optional('school_year'),
            'description' => $optional('description'),
        ];
    }

    private function classRules(): array
    {
        return [
            'name'        => ['label' => 'class name', 'rules' => 'required|max_length[100]'],
            'section'     => ['label' => 'section', 'rules' => 'permit_empty|max_length[50]'],
            'school_year' => ['label' => 'school year', 'rules' => 'permit_empty|max_length[20]'],
            'description' => ['label' => 'description', 'rules' => 'permit_empty|max_length[2000]'],
        ];
    }
}
