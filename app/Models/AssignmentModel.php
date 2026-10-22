<?php

namespace App\Models;

use CodeIgniter\Model;

class AssignmentModel extends Model
{
    public const STATUSES = ['draft', 'published', 'closed'];

    protected $table            = 'assignments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'class_id',
        'rubric_id',
        'title',
        'instructions',
        'due_at',
        'status',
    ];

    protected array $casts = [
        'id'        => 'int',
        'class_id'  => 'int',
        'rubric_id' => 'int',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * An assignment only if its class belongs to the given teacher.
     */
    public function findForTeacher(int $id, int $teacherId): ?array
    {
        return $this->select('assignments.*')
            ->join('classes', 'classes.id = assignments.class_id')
            ->where('assignments.id', $id)
            ->where('classes.teacher_id', $teacherId)
            ->first();
    }

    /**
     * A class's assignments with how many students have submitted.
     */
    public function forClass(int $classId): array
    {
        $assignments = $this->db->prefixTable('assignments');
        $submissions = $this->db->prefixTable('submissions');

        return $this->select('assignments.*')
            ->select("(SELECT COUNT(*) FROM {$submissions} s WHERE s.assignment_id = {$assignments}.id) AS submission_count", false)
            ->where('class_id', $classId)
            ->orderBy('due_at', 'DESC')
            ->findAll();
    }

    /**
     * Published and closed assignments in the student's classes, with the
     * student's own submission status (null when not yet submitted).
     */
    public function forStudent(int $studentId, ?int $classId = null): array
    {
        if ($classId !== null) {
            $this->where('assignments.class_id', $classId);
        }

        $rows = $this->select('assignments.*, classes.name AS class_name, classes.status AS class_status')
            ->select('submissions.id AS submission_id, submissions.status AS submission_status, submissions.is_late, submissions.submitted_at')
            ->select('evaluations.total_score, evaluations.max_score')
            ->join('classes', 'classes.id = assignments.class_id')
            ->join('class_students', 'class_students.class_id = classes.id')
            ->join('submissions', 'submissions.assignment_id = assignments.id AND submissions.student_id = class_students.student_id', 'left')
            ->join('evaluations', 'evaluations.submission_id = submissions.id', 'left')
            ->where('class_students.student_id', $studentId)
            ->whereIn('assignments.status', ['published', 'closed'])
            ->orderBy('assignments.due_at')
            ->findAll();

        // Students may only see scores the teacher has released (FR-12).
        foreach ($rows as &$row) {
            if ($row['submission_status'] !== 'released') {
                $row['total_score'] = null;
                $row['max_score']   = null;
            }
        }

        return $rows;
    }

    /**
     * An assignment only if the student is enrolled in its class and it is visible to students.
     */
    public function findForStudent(int $id, int $studentId): ?array
    {
        return $this->select('assignments.*')
            ->join('class_students', 'class_students.class_id = assignments.class_id')
            ->where('assignments.id', $id)
            ->where('class_students.student_id', $studentId)
            ->whereIn('assignments.status', ['published', 'closed'])
            ->first();
    }

    public function hasSubmissions(int $assignmentId): bool
    {
        return $this->db->table('submissions')->where('assignment_id', $assignmentId)->countAllResults() > 0;
    }
}
