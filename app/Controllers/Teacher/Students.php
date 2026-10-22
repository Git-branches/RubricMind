<?php

namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Libraries\StudentRoster;
use App\Models\ClassModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Teachers create student accounts and enroll students in their classes (FR-02).
 * Teachers can only create student accounts; admins manage all other accounts.
 */
class Students extends BaseController
{
    public function index(): string
    {
        $search = trim((string) $this->request->getGet('q'));

        return view('teacher/students/index', [
            'students' => model(ClassModel::class)->studentsForTeacher($this->userId(), $search),
            'search'   => $search,
            'classes'  => model(ClassModel::class)->forTeacher($this->userId()),
        ]);
    }

    public function new(): string
    {
        return $this->form(['class_id' => (string) $this->request->getGet('class_id'), 'students' => '']);
    }

    public function create(): RedirectResponse|string
    {
        $data = [
            'class_id'         => (string) $this->request->getPost('class_id'),
            'students'         => (string) $this->request->getPost('students'),
            'password'         => (string) $this->request->getPost('password'),
            'password_confirm' => (string) $this->request->getPost('password_confirm'),
        ];
        $old = ['class_id' => $data['class_id'], 'students' => $data['students']];

        $class = ctype_digit($data['class_id'])
            ? model(ClassModel::class)->findForTeacher((int) $data['class_id'], $this->userId())
            : null;

        if ($class === null || $class['status'] !== 'active') {
            return $this->form($old, ['class_id' => 'Choose one of your active classes.']);
        }

        $parsed = StudentRoster::parse($data['students']);
        $errors = $parsed['errors'] ?: StudentRoster::missingNames($parsed['students']);

        if ($errors !== []) {
            return $this->form($old, $errors);
        }

        $needsPassword = StudentRoster::needsNewAccounts($parsed['students']);

        if ($needsPassword) {
            $rules = [
                'password'         => ['label' => 'temporary password', 'rules' => UserModel::PASSWORD_RULES],
                'password_confirm' => ['label' => 'password confirmation', 'rules' => 'required|matches[password]'],
            ];

            if (! $this->validateData($data, $rules)) {
                return $this->form($old, $this->validator->getErrors());
            }
        }

        $result = StudentRoster::add($class['id'], $parsed['students'], $needsPassword ? $data['password'] : null);

        $added = array_filter([
            $result['created'] !== [] ? count($result['created']) . ' new student account(s) created' : null,
            $result['enrolled'] !== [] ? count($result['enrolled']) . ' existing student(s) enrolled' : null,
        ]);
        $message = $added === []
            ? "No new students were added to {$class['name']}."
            : ucfirst(implode(' and ', $added)) . " in {$class['name']}.";

        if ($result['already'] !== []) {
            $message .= ' ' . count($result['already']) . ' student(s) were already in the class.';
        }

        if ($result['created'] !== []) {
            $message .= ' New students sign in with the temporary password and then choose their own.';
        }

        $back = redirect()->to(site_url("teacher/classes/{$class['id']}#students"));

        return $result['skipped'] === []
            ? $back->with('success', $message)
            : $back->with('warning', $message . ' Skipped (these emails belong to teacher or admin accounts): '
                . implode(', ', $result['skipped']) . '.');
    }

    /**
     * @param array{class_id: string, students: string} $old
     * @param array<array-key, string>                 $errors
     */
    private function form(array $old, array $errors = []): string
    {
        return view('teacher/students/form', [
            'classes' => model(ClassModel::class)->forTeacher($this->userId()),
            'old'     => $old,
            'errors'  => $errors,
        ]);
    }
}
