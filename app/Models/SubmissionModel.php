<?php

namespace App\Models;

use CodeIgniter\Model;

class SubmissionModel extends Model
{
    public const STATUSES = ['submitted', 'ai_evaluated', 'teacher_reviewed', 'finalized', 'released'];

    protected $table            = 'submissions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'assignment_id',
        'student_id',
        'content',
        'word_count',
        'file_name',
        'file_path',
        'file_size',
        'status',
        'ai_status',
        'is_late',
        'submitted_at',
        'locked_at',
    ];

    protected array $casts = [
        'id'            => 'int',
        'assignment_id' => 'int',
        'student_id'    => 'int',
        'word_count'    => 'int',
        'file_size'     => '?int',
        'is_late'       => 'bool',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public static function countWords(string $text): int
    {
        $text = trim($text);

        return $text === '' ? 0 : count(preg_split('/\s+/u', $text));
    }

    public function findForStudent(int $assignmentId, int $studentId): ?array
    {
        return $this->where('assignment_id', $assignmentId)->where('student_id', $studentId)->first();
    }

    /**
     * A submission only if its assignment's class belongs to the given teacher.
     */
    public function findForTeacher(int $id, int $teacherId): ?array
    {
        return $this->select('submissions.*')
            ->join('assignments', 'assignments.id = submissions.assignment_id')
            ->join('classes', 'classes.id = assignments.class_id')
            ->where('submissions.id', $id)
            ->where('classes.teacher_id', $teacherId)
            ->first();
    }

    /**
     * Every student enrolled in the class, with their submission for this
     * assignment if they have one (submission columns are null otherwise).
     * Includes students who submitted and were later removed from the class.
     */
    public function rosterForAssignment(int $assignmentId, int $classId): array
    {
        $columns = 'submissions.id, submissions.status, submissions.word_count, submissions.is_late, submissions.submitted_at, submissions.updated_at,'
            . ' evaluations.total_score, evaluations.max_score';

        $enrolled = $this->db->table('class_students')
            ->select('users.id AS student_id, users.name AS student_name, users.email AS student_email')
            ->select($columns)
            ->join('users', 'users.id = class_students.student_id')
            ->join('submissions', 'submissions.student_id = users.id AND submissions.assignment_id = ' . $this->db->escape($assignmentId), 'left')
            ->join('evaluations', 'evaluations.submission_id = submissions.id', 'left')
            ->where('class_students.class_id', $classId)
            ->get()
            ->getResultArray();

        $unenrolled = $this->db->table('submissions')
            ->select('users.id AS student_id, users.name AS student_name, users.email AS student_email')
            ->select($columns)
            ->join('users', 'users.id = submissions.student_id')
            ->join('evaluations', 'evaluations.submission_id = submissions.id', 'left')
            ->where('submissions.assignment_id', $assignmentId)
            ->whereNotIn('submissions.student_id', static fn ($builder) => $builder->select('student_id')->from('class_students')->where('class_id', $classId))
            ->get()
            ->getResultArray();

        $rows = array_merge($enrolled, array_map(static fn (array $row) => $row + ['unenrolled' => true], $unenrolled));
        usort($rows, static fn (array $a, array $b) => strcasecmp($a['student_name'], $b['student_name']));

        return $rows;
    }

    /**
     * Saves a student's essay and records it as a new revision.
     *
     * @param array{name: string, path: string, size: int}|null $file A newly uploaded original file
     *                                                                (see SubmissionFiles); null keeps the current one.
     *
     * @return int The submission ID.
     */
    public function saveEssay(?array $existing, int $assignmentId, int $studentId, string $content, bool $isLate, ?array $file = null): int
    {
        $now       = date('Y-m-d H:i:s');
        $wordCount = self::countWords($content);
        $fileRow   = $file === null ? [] : ['file_name' => $file['name'], 'file_path' => $file['path'], 'file_size' => $file['size']];

        $this->db->transException(true)->transStart();

        if ($existing === null) {
            $id = $this->insert([
                'assignment_id' => $assignmentId,
                'student_id'    => $studentId,
                'content'       => $content,
                'word_count'    => $wordCount,
                'status'        => 'submitted',
                'is_late'       => $isLate,
                'submitted_at'  => $now,
            ] + $fileRow);
        } else {
            $id = $existing['id'];
            $this->update($id, ['content' => $content, 'word_count' => $wordCount] + $fileRow);
        }

        $this->db->table('submission_revisions')->insert([
            'submission_id' => $id,
            'content'       => $content,
            'word_count'    => $wordCount,
            'created_at'    => $now,
        ]);

        $this->db->transComplete();

        return $id;
    }

    /**
     * The next submission in this assignment that is not yet finalized, after the
     * current one in student-name order (wrapping around). Null when none is left.
     */
    public function nextToGrade(int $assignmentId, int $currentId): ?int
    {
        $ids = array_map('intval', array_column($this->db->table('submissions')
            ->select('submissions.id')
            ->join('users', 'users.id = submissions.student_id')
            ->where('submissions.assignment_id', $assignmentId)
            ->whereIn('submissions.status', ['submitted', 'ai_evaluated', 'teacher_reviewed'])
            ->orderBy('users.name')
            ->get()
            ->getResultArray(), 'id'));

        $others = array_values(array_filter($ids, static fn (int $id) => $id !== $currentId));

        if ($others === []) {
            return null;
        }

        $position = array_search($currentId, $ids, true);

        if ($position === false) {
            return $others[0];
        }

        // The first one after the current submission, or wrap around to the start.
        return $ids[$position + 1] ?? $others[0];
    }

    /**
     * The student's earlier essays that have a finalized grade, oldest first,
     * for the Style Growth Tracker (FR-09). Excludes the given submission.
     *
     * @return list<array{id: int, content: string, word_count: int, submitted_at: string}>
     */
    public function styleHistory(array $submission): array
    {
        return array_map(static fn (array $row) => [
            'id'           => (int) $row['id'],
            'content'      => $row['content'],
            'word_count'   => (int) $row['word_count'],
            'submitted_at' => $row['submitted_at'],
        ], $this->db->table('submissions')
            ->select('id, content, word_count, submitted_at')
            ->where('student_id', $submission['student_id'])
            ->where('id !=', $submission['id'])
            ->where('submitted_at <', $submission['submitted_at'])
            ->whereIn('status', ['finalized', 'released'])
            ->orderBy('submitted_at')
            ->get()
            ->getResultArray());
    }

    /**
     * Finalized submissions for an assignment, ready to release.
     */
    public function finalizedForAssignment(int $assignmentId): array
    {
        return $this->where('assignment_id', $assignmentId)->where('status', 'finalized')->findAll();
    }

    public function revisions(int $submissionId): array
    {
        return $this->db->table('submission_revisions')
            ->select('id, word_count, created_at')
            ->where('submission_id', $submissionId)
            ->orderBy('id')
            ->get()
            ->getResultArray();
    }
}
