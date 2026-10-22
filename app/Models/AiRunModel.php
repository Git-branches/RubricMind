<?php

namespace App\Models;

use CodeIgniter\Model;

class AiRunModel extends Model
{
    protected $table            = 'ai_runs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'submission_id',
        'requested_by',
        'provider',
        'model',
        'prompt_version',
        'status',
        'error',
        'feedback',
        'raw_response',
        'started_at',
        'finished_at',
    ];

    protected array $casts = [
        'id'            => 'int',
        'submission_id' => 'int',
        'requested_by'  => 'int',
    ];

    // Runs are never edited after they finish, so only the creation time is tracked.
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    public function latestForSubmission(int $submissionId): ?array
    {
        return $this->where('submission_id', $submissionId)->orderBy('id', 'DESC')->first();
    }

    /**
     * Everything a finished run produced, ready for display.
     *
     * @return array{
     *     feedback: array{summary: string, strengths: list<string>, improvements: list<string>}|null,
     *     suggestions: array<int, array>,
     *     highlights: list<array>,
     *     patterns: list<array>
     * }
     */
    public function results(array $run): array
    {
        $suggestions = [];

        foreach ($this->db->table('ai_criterion_suggestions')->where('ai_run_id', $run['id'])->get()->getResultArray() as $row) {
            $suggestions[(int) $row['criterion_id']] = [
                'points'    => (int) $row['points'],
                'level_id'  => $row['level_id'] === null ? null : (int) $row['level_id'],
                'rationale' => $row['rationale'],
            ];
        }

        $highlights = array_map(static fn (array $row) => [
            'id'           => (int) $row['id'],
            'criterion_id' => (int) $row['criterion_id'],
            'quote'        => $row['quote'],
            'note'         => $row['note'],
            'start'        => $row['start_offset'] === null ? null : (int) $row['start_offset'],
            'length'       => $row['length'] === null ? null : (int) $row['length'],
            'verified'     => (bool) $row['verified'],
        ], $this->db->table('evidence_highlights')->where('ai_run_id', $run['id'])->orderBy('id')->get()->getResultArray());

        $patterns = array_map(static fn (array $row) => [
            'pattern'     => $row['pattern'],
            'description' => $row['description'],
            'occurrences' => (int) $row['occurrences'],
            'examples'    => json_decode($row['examples'] ?? '[]', true) ?: [],
            'suggestion'  => $row['suggestion'],
        ], $this->db->table('grammar_patterns')->where('ai_run_id', $run['id'])->orderBy('occurrences', 'DESC')->get()->getResultArray());

        return [
            'feedback'    => $run['feedback'] === null ? null : json_decode($run['feedback'], true),
            'suggestions' => $suggestions,
            'highlights'  => $highlights,
            'patterns'    => $patterns,
        ];
    }
}
