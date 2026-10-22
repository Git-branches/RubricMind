<?php

namespace App\Models;

use CodeIgniter\Model;

class LessonPlanModel extends Model
{
    protected $table            = 'lesson_plans';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'assignment_id',
        'teacher_id',
        'title',
        'content',
        'focus',
        'provider',
    ];

    protected array $casts = [
        'id'            => 'int',
        'assignment_id' => 'int',
        'teacher_id'    => 'int',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function findForTeacher(int $id, int $teacherId): ?array
    {
        return $this->where('id', $id)->where('teacher_id', $teacherId)->first();
    }

    public function forAssignment(int $assignmentId): array
    {
        return $this->where('assignment_id', $assignmentId)->orderBy('id', 'DESC')->findAll();
    }

    /**
     * Turns a structured plan from EssayAnalyzer::lessonPlan() into editable text.
     */
    public static function toText(array $plan): string
    {
        $lines = [
            'Objective: ' . $plan['objective'],
            'Duration: ' . $plan['duration_minutes'] . ' minutes',
            '',
        ];

        foreach ($plan['sections'] as $i => $section) {
            $lines[] = ($i + 1) . ". {$section['heading']} ({$section['minutes']} min)";

            foreach ($section['steps'] as $step) {
                $lines[] = "   - {$step}";
            }

            $lines[] = '';
        }

        $lines[] = 'Materials: ' . implode(', ', $plan['materials']);
        $lines[] = 'Assessment: ' . $plan['assessment'];

        return implode("\n", $lines);
    }
}
