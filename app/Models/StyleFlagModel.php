<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * A teacher's review of a Style Growth Tracker result (FR-09).
 */
class StyleFlagModel extends Model
{
    protected $table            = 'style_flags';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'submission_id',
        'status',
        'history_count',
        'details',
        'reviewed_by',
        'review_note',
        'reviewed_at',
    ];

    protected array $casts = [
        'id'            => 'int',
        'submission_id' => 'int',
        'history_count' => 'int',
        'reviewed_by'   => 'int',
    ];

    protected $useTimestamps = false;

    public function findBySubmission(int $submissionId): ?array
    {
        return $this->where('submission_id', $submissionId)->first();
    }
}
