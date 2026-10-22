<?php

namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Models\ClassModel;

class Dashboard extends BaseController
{
    // How many recent submissions to list.
    private const RECENT_LIMIT = 10;

    public function index(): string
    {
        $teacherId = $this->userId();
        $db        = db_connect();

        $recent = $db->table('submissions')
            ->select('submissions.id, submissions.status, submissions.is_late, submissions.submitted_at')
            ->select('users.name AS student_name, assignments.id AS assignment_id, assignments.title AS assignment_title, classes.name AS class_name')
            ->join('assignments', 'assignments.id = submissions.assignment_id')
            ->join('classes', 'classes.id = assignments.class_id')
            ->join('users', 'users.id = submissions.student_id')
            ->where('classes.teacher_id', $teacherId)
            ->orderBy('submissions.submitted_at', 'DESC')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->getResultArray();

        $awaitingReview = $db->table('submissions')
            ->join('assignments', 'assignments.id = submissions.assignment_id')
            ->join('classes', 'classes.id = assignments.class_id')
            ->where('classes.teacher_id', $teacherId)
            ->whereIn('submissions.status', ['submitted', 'ai_evaluated', 'teacher_reviewed'])
            ->countAllResults();

        $openAssignments = $db->table('assignments')
            ->join('classes', 'classes.id = assignments.class_id')
            ->where('classes.teacher_id', $teacherId)
            ->where('assignments.status', 'published')
            ->countAllResults();

        // A student in two of the teacher's classes is counted once.
        $studentCount = $db->table('class_students')
            ->distinct()
            ->select('class_students.student_id')
            ->join('classes', 'classes.id = class_students.class_id')
            ->where('classes.teacher_id', $teacherId)
            ->where('classes.status', 'active')
            ->countAllResults();

        return view('teacher/dashboard', [
            'classes'         => model(ClassModel::class)->forTeacher($teacherId),
            'studentCount'    => $studentCount,
            'recent'          => $recent,
            'awaitingReview'  => $awaitingReview,
            'openAssignments' => $openAssignments,
        ]);
    }
}
