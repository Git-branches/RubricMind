<?php

namespace App\Models;

use CodeIgniter\Model;

class EvaluationModel extends Model
{
    protected $table            = 'evaluations';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'submission_id',
        'rubric_id',
        'teacher_id',
        'total_score',
        'max_score',
        'student_feedback',
        'teacher_notes',
        'started_at',
        'reviewed_at',
        'finalized_at',
        'released_at',
    ];

    protected array $casts = [
        'id'            => 'int',
        'submission_id' => 'int',
        'rubric_id'     => 'int',
        'teacher_id'    => 'int',
        'total_score'   => 'int',
        'max_score'     => 'int',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function findBySubmission(int $submissionId): ?array
    {
        return $this->where('submission_id', $submissionId)->first();
    }

    /**
     * Criterion scores for an evaluation, keyed by criterion ID.
     *
     * @return array<int, array{id: int, criterion_id: int, level_id: int|null, points: int|null, comment: string|null}>
     */
    public function scores(int $evaluationId): array
    {
        $rows = $this->db->table('criterion_scores')
            ->where('evaluation_id', $evaluationId)
            ->get()
            ->getResultArray();

        $scores = [];

        foreach ($rows as $row) {
            $scores[(int) $row['criterion_id']] = [
                'id'           => (int) $row['id'],
                'criterion_id' => (int) $row['criterion_id'],
                'level_id'     => $row['level_id'] === null ? null : (int) $row['level_id'],
                'points'       => $row['points'] === null ? null : (int) $row['points'],
                'comment'      => $row['comment'],
            ];
        }

        return $scores;
    }
}
