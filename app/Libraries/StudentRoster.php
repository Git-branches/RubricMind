<?php

namespace App\Libraries;

use App\Models\ClassModel;
use App\Models\UserModel;

/**
 * Adds students to a class from a pasted list (FR-02).
 *
 * Each line is "Full name, email" (also "Full name <email>" or a tab between,
 * as when copying from a spreadsheet). An email alone works for students who
 * already have an account.
 *
 * - New email: a student account is created with the batch's temporary password.
 * - Existing student: enrolled (or reported as already enrolled).
 * - Email of a teacher or admin: skipped. Teachers can only create student accounts.
 */
final class StudentRoster
{
    public const MAX_LINES = 100;

    /**
     * @return array{students: list<array{line: int, name: string, email: string}>, errors: list<string>}
     */
    public static function parse(string $text): array
    {
        $students = [];
        $errors   = [];
        $seen     = [];

        foreach (preg_split('/\R/u', $text) as $i => $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $number = $i + 1;

            if (preg_match('/[^\s<>,;]+@[^\s<>,;]+/u', $line, $match) !== 1 || filter_var($match[0], FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = "Line {$number}: no valid email address found in \"{$line}\".";

                continue;
            }

            $email = strtolower($match[0]);
            $name  = trim(preg_replace('/\s+/u', ' ', str_replace($match[0], '', $line)), " \t,;<>");

            if (mb_strlen($name) > 100) {
                $errors[] = "Line {$number}: the name must be 100 characters or fewer.";

                continue;
            }

            if (isset($seen[$email])) {
                continue;
            }

            $seen[$email] = true;
            $students[]   = ['line' => $number, 'name' => $name, 'email' => $email];
        }

        if ($students === [] && $errors === []) {
            $errors[] = 'Enter at least one student, one per line.';
        }

        if (count($students) > self::MAX_LINES) {
            $errors[] = 'You can add at most ' . self::MAX_LINES . ' students at a time.';
        }

        return ['students' => $students, 'errors' => $errors];
    }

    /**
     * Lines that would create a new account but have no name. Checked before
     * anything is saved, so the teacher can fix the list in one go.
     *
     * @return list<string>
     */
    public static function missingNames(array $students): array
    {
        $users  = model(UserModel::class);
        $errors = [];

        foreach ($students as $student) {
            if ($student['name'] === '' && $users->findByEmail($student['email']) === null) {
                $errors[] = "Line {$student['line']}: {$student['email']} has no account yet, so a full name is needed.";
            }
        }

        return $errors;
    }

    public static function needsNewAccounts(array $students): bool
    {
        $users = model(UserModel::class);

        foreach ($students as $student) {
            if ($users->findByEmail($student['email']) === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{created: list<string>, enrolled: list<string>, already: list<string>, skipped: list<string>}
     */
    public static function add(int $classId, array $students, ?string $temporaryPassword): array
    {
        $users   = model(UserModel::class);
        $classes = model(ClassModel::class);
        $result  = ['created' => [], 'enrolled' => [], 'already' => [], 'skipped' => []];

        $db = db_connect();
        $db->transException(true)->transStart();

        foreach ($students as $student) {
            $existing = $users->findByEmail($student['email']);

            if ($existing !== null && $existing['role'] !== 'student') {
                $result['skipped'][] = $student['email'];

                continue;
            }

            if ($existing === null) {
                $id = $users->insert([
                    'name'                 => $student['name'],
                    'email'                => $student['email'],
                    'password_hash'        => UserModel::hashPassword($temporaryPassword),
                    'role'                 => 'student',
                    'status'               => 'active',
                    'must_change_password' => true,
                ]);
                $classes->enroll($classId, $id);
                $result['created'][] = $student['email'];

                audit_log('user.created', 'user', $id, ['role' => 'student', 'created_for_class' => $classId]);

                continue;
            }

            $result[$classes->enroll($classId, $existing['id']) ? 'enrolled' : 'already'][] = $student['email'];
        }

        $db->transComplete();

        if ($result['created'] !== [] || $result['enrolled'] !== []) {
            audit_log('class.students_enrolled', 'class', $classId, [
                'emails' => array_merge($result['created'], $result['enrolled']),
            ]);
        }

        return $result;
    }
}
