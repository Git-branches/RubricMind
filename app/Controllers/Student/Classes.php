<?php

namespace App\Controllers\Student;

use App\Controllers\BaseController;
use App\Models\AssignmentModel;
use App\Models\ClassModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * My classes and Join a Class (FR-02, FR-14).
 *
 * A class code only lets a student enroll in that one class. It gives no
 * access to anything else; every class, assignment, and grade page still
 * checks enrollment on the server.
 */
class Classes extends BaseController
{
    // Wrong codes allowed per student in the window, against guessing.
    private const MAX_FAILED_JOINS  = 10;
    private const JOIN_WINDOW       = 15 * MINUTE;

    public function index(): string
    {
        return view('student/classes/index', [
            'classes' => model(ClassModel::class)->forStudent($this->userId()),
            'code'    => '',
        ]);
    }

    /**
     * One class the student joined: its details and its assignments.
     */
    public function show(int $id): string
    {
        $class = model(ClassModel::class)->findForStudent($id, $this->userId()) ?? throw PageNotFoundException::forPageNotFound();

        return view('student/classes/show', [
            'class'       => $class,
            'assignments' => model(AssignmentModel::class)->forStudent($this->userId(), $id),
        ]);
    }

    public function join(): RedirectResponse|string
    {
        $back    = redirect()->to(site_url('student/classes'));
        $failKey = 'join-failures-' . $this->userId();
        $failed  = (int) (cache($failKey) ?? 0);

        if ($failed >= self::MAX_FAILED_JOINS) {
            return $back->with('error', 'Too many incorrect class codes. Please wait 15 minutes and try again, or check the code with your teacher.');
        }

        $typed   = (string) $this->request->getPost('code');
        $code    = ClassModel::normalizeJoinCode($typed);
        $classes = model(ClassModel::class);
        $class   = strlen($code) === ClassModel::CODE_LENGTH ? $classes->findByJoinCode($code) : null;

        $error = match (true) {
            $code === ''                    => 'Enter the class code from your teacher.',
            $class === null                 => 'That class code is not valid. Check it with your teacher and try again.',
            ! $class['join_code_enabled']   => 'This class code is turned off. Ask your teacher for a new code.',
            $class['status'] !== 'active'   => 'This class is no longer accepting students.',
            default                         => null,
        };

        if ($error !== null) {
            // Only wrong or unusable codes count toward the limit.
            if ($code !== '') {
                cache()->save($failKey, $failed + 1, self::JOIN_WINDOW);
            }

            return view('student/classes/index', [
                'classes' => $classes->forStudent($this->userId()),
                'code'    => $typed,
                'error'   => $error,
            ]);
        }

        if (! $classes->enroll($class['id'], $this->userId())) {
            return $back->with('warning', "You are already enrolled in {$class['name']}.");
        }

        audit_log('class.joined', 'class', $class['id'], ['student_id' => $this->userId()]);

        return $back->with('success', "You joined {$class['name']}. Its assignments now appear in My assignments.");
    }
}
