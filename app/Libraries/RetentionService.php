<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Data retention for student records (§5).
 *
 * After a class has been archived for the retention period set on the admin
 * Settings page, an administrator may permanently delete its student work:
 * essays and their revisions and uploaded files, grades, AI results, style
 * reviews, lesson plans, assignments, and enrollments. Nothing is deleted automatically.
 *
 * Student accounts, the teacher's reusable rubrics, and the audit log are kept.
 */
final class RetentionService
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly int $retentionYears,
    ) {
    }

    /**
     * Archived classes with their size and whether the retention period has passed.
     *
     * @return list<array<string, mixed>>
     */
    public function archivedClasses(): array
    {
        $rows = $this->db->table('classes')
            ->select('classes.id, classes.name, classes.section, classes.school_year, classes.archived_at, users.name AS teacher_name')
            ->join('users', 'users.id = classes.teacher_id')
            ->where('classes.status', 'archived')
            ->orderBy('classes.archived_at')
            ->get()
            ->getResultArray();

        $cutoff = $this->cutoff();

        return array_map(function (array $row) use ($cutoff) {
            $counts = $this->counts((int) $row['id']);

            return $row + $counts + [
                'eligible'     => $row['archived_at'] !== null && $row['archived_at'] <= $cutoff,
                'eligible_on'  => $row['archived_at'] === null ? null
                    : date('Y-m-d', strtotime($row['archived_at'] . " +{$this->retentionYears} years")),
            ];
        }, $rows);
    }

    /**
     * Permanently deletes an eligible class and its student work.
     *
     * @return array{name: string, assignments: int, submissions: int, students: int}
     *
     * @throws RuntimeException When the class is not archived or the retention period has not passed.
     */
    public function purge(int $classId): array
    {
        $class = $this->db->table('classes')->where('id', $classId)->get()->getRowArray();

        if ($class === null || $class['status'] !== 'archived' || $class['archived_at'] === null || $class['archived_at'] > $this->cutoff()) {
            throw new RuntimeException('This class is not archived long enough to be deleted.');
        }

        $counts        = $this->counts($classId);
        $assignmentIds = array_column($this->db->table('assignments')->select('id')->where('class_id', $classId)->get()->getResultArray(), 'id');
        $files         = $assignmentIds === [] ? [] : array_column($this->db->table('submissions')
            ->select('file_path')
            ->whereIn('assignment_id', $assignmentIds)
            ->where('file_path IS NOT NULL')
            ->get()
            ->getResultArray(), 'file_path');

        $this->db->transException(true)->transStart();

        if ($assignmentIds !== []) {
            // Deleting submissions also removes, through foreign keys: revisions,
            // evaluations, criterion scores, AI runs and their results, and style reviews.
            $this->db->table('submissions')->whereIn('assignment_id', $assignmentIds)->delete();
            // Deleting assignments also removes their lesson plans.
            $this->db->table('assignments')->whereIn('id', $assignmentIds)->delete();
        }

        // Deleting the class also removes its enrollments.
        $this->db->table('classes')->where('id', $classId)->delete();

        $this->db->transComplete();

        // Uploaded essay files are removed only after the records are gone.
        foreach ($files as $file) {
            SubmissionFiles::delete($file);
        }

        return ['name' => $class['name']] + $counts;
    }

    /**
     * Archived on or before this time means the retention period has passed.
     */
    private function cutoff(): string
    {
        return date('Y-m-d H:i:s', strtotime("-{$this->retentionYears} years"));
    }

    /**
     * @return array{assignments: int, submissions: int, students: int}
     */
    private function counts(int $classId): array
    {
        return [
            'assignments' => $this->db->table('assignments')->where('class_id', $classId)->countAllResults(),
            'submissions' => $this->db->table('submissions')
                ->join('assignments', 'assignments.id = submissions.assignment_id')
                ->where('assignments.class_id', $classId)
                ->countAllResults(),
            'students'    => $this->db->table('class_students')->where('class_id', $classId)->countAllResults(),
        ];
    }
}
