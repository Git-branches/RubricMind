<?php

namespace App\Models;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;

class ClassModel extends Model
{
    public const STATUSES = ['active', 'archived'];

    protected $table            = 'classes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'teacher_id',
        'name',
        'section',
        'school_year',
        'description',
        'status',
        'archived_at',
        'join_code',
        'join_code_enabled',
    ];

    protected array $casts = [
        'id'                => 'int',
        'teacher_id'        => 'int',
        'join_code_enabled' => 'bool',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Every new class gets a joining code.
    protected $beforeInsert = ['addJoinCode'];

    // Class codes use letters and digits that cannot be confused when read
    // aloud or copied (no 0/O, 1/I/L). 8 characters from 31: about 850 billion codes.
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    public const CODE_LENGTH    = 8;

    /**
     * A random, unused class code such as "K7MPQ2XR" (shown as "K7MP-Q2XR").
     */
    public static function newJoinCode(?BaseConnection $db = null): string
    {
        $db ??= db_connect();

        do {
            $code = '';

            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while ($db->table('classes')->where('join_code', $code)->countAllResults() > 0);

        return $code;
    }

    /**
     * What a student typed, as a stored code: "k7mp-q2xr " becomes "K7MPQ2XR".
     */
    public static function normalizeJoinCode(string $input): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input) ?? '');
    }

    /**
     * "K7MP-Q2XR", for reading and sharing.
     */
    public static function formatJoinCode(?string $code): string
    {
        return $code === null ? '' : implode('-', str_split($code, 4));
    }

    protected function addJoinCode(array $data): array
    {
        $data['data']['join_code'] ??= self::newJoinCode($this->db);

        return $data;
    }

    public function findByJoinCode(string $code): ?array
    {
        return $code === '' ? null : $this->where('join_code', $code)->first();
    }

    /**
     * One of the student's classes, with the teacher's name; null unless the student is enrolled.
     */
    public function findForStudent(int $id, int $studentId): ?array
    {
        return $this->select('classes.*, users.name AS teacher_name, class_students.created_at AS enrolled_at')
            ->join('class_students', 'class_students.class_id = classes.id')
            ->join('users', 'users.id = classes.teacher_id')
            ->where('classes.id', $id)
            ->where('class_students.student_id', $studentId)
            ->first();
    }

    /**
     * The classes a student is enrolled in, with the teacher and assignment count.
     */
    public function forStudent(int $studentId): array
    {
        $db = $this->db;

        return $this->select('classes.*, users.name AS teacher_name, class_students.created_at AS enrolled_at')
            ->select('(SELECT COUNT(*) FROM ' . $db->prefixTable('assignments') . ' a WHERE a.class_id = ' . $db->prefixTable('classes') . ".id AND a.status != 'draft') AS assignment_count", false)
            ->join('class_students', 'class_students.class_id = classes.id')
            ->join('users', 'users.id = classes.teacher_id')
            ->where('class_students.student_id', $studentId)
            ->orderBy('classes.status')
            ->orderBy('classes.name')
            ->findAll();
    }

    /**
     * A class only if it belongs to the given teacher.
     */
    public function findForTeacher(int $id, int $teacherId): ?array
    {
        return $this->where('id', $id)->where('teacher_id', $teacherId)->first();
    }

    /**
     * The teacher's classes with student and assignment counts.
     */
    public function forTeacher(int $teacherId, string $status = 'active'): array
    {
        $db = $this->db;

        return $this->select('classes.*')
            ->select('(SELECT COUNT(*) FROM ' . $db->prefixTable('class_students') . ' cs WHERE cs.class_id = ' . $db->prefixTable('classes') . '.id) AS student_count', false)
            ->select('(SELECT COUNT(*) FROM ' . $db->prefixTable('assignments') . ' a WHERE a.class_id = ' . $db->prefixTable('classes') . '.id) AS assignment_count', false)
            ->where('teacher_id', $teacherId)
            ->where('status', $status)
            ->orderBy('name')
            ->findAll();
    }

    public function isStudentEnrolled(int $classId, int $studentId): bool
    {
        return $this->db->table('class_students')
            ->where('class_id', $classId)
            ->where('student_id', $studentId)
            ->countAllResults() > 0;
    }

    /**
     * Students enrolled in a class, by name.
     */
    public function students(int $classId): array
    {
        return $this->db->table('class_students')
            ->select('users.id, users.name, users.email, users.status, class_students.created_at AS enrolled_at')
            ->join('users', 'users.id = class_students.student_id')
            ->where('class_students.class_id', $classId)
            ->orderBy('users.name')
            ->get()
            ->getResultArray();
    }

    /**
     * Enrolls students by email. Only existing student accounts can be enrolled.
     *
     * @param list<string> $emails
     *
     * @return array{enrolled: list<string>, already: list<string>, notFound: list<string>}
     */
    public function enrollByEmail(int $classId, array $emails): array
    {
        $result = ['enrolled' => [], 'already' => [], 'notFound' => []];
        $users  = model(UserModel::class);

        foreach ($emails as $email) {
            $student = $users->findByEmail($email);

            if ($student === null || $student['role'] !== 'student') {
                $result['notFound'][] = $email;

                continue;
            }

            $result[$this->enroll($classId, $student['id']) ? 'enrolled' : 'already'][] = $email;
        }

        return $result;
    }

    /**
     * @return bool False when the student was already enrolled.
     */
    public function enroll(int $classId, int $studentId): bool
    {
        if ($this->isStudentEnrolled($classId, $studentId)) {
            return false;
        }

        $this->db->table('class_students')->insert([
            'class_id'   => $classId,
            'student_id' => $studentId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * Distinct students enrolled in any of the teacher's classes, with the
     * names of those classes.
     *
     * @return list<array{id: int, name: string, email: string, status: string, must_change_password: bool, classes: list<array{id: int, name: string}>}>
     */
    public function studentsForTeacher(int $teacherId, string $search = ''): array
    {
        $builder = $this->db->table('class_students')
            ->select('users.id, users.name, users.email, users.status, users.must_change_password, classes.id AS class_id, classes.name AS class_name')
            ->join('users', 'users.id = class_students.student_id')
            ->join('classes', 'classes.id = class_students.class_id')
            ->where('classes.teacher_id', $teacherId)
            ->orderBy('users.name')
            ->orderBy('classes.name');

        if ($search !== '') {
            $builder->groupStart()->like('users.name', $search)->orLike('users.email', $search)->groupEnd();
        }

        $students = [];

        foreach ($builder->get()->getResultArray() as $row) {
            $id = (int) $row['id'];

            $students[$id] ??= [
                'id'                   => $id,
                'name'                 => $row['name'],
                'email'                => $row['email'],
                'status'               => $row['status'],
                'must_change_password' => (bool) $row['must_change_password'],
                'classes'              => [],
            ];
            $students[$id]['classes'][] = ['id' => (int) $row['class_id'], 'name' => $row['class_name']];
        }

        return array_values($students);
    }

    public function unenroll(int $classId, int $studentId): bool
    {
        $this->db->table('class_students')
            ->where('class_id', $classId)
            ->where('student_id', $studentId)
            ->delete();

        return $this->db->affectedRows() > 0;
    }
}
