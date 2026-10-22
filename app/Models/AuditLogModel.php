<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table            = 'audit_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'details',
        'ip_address',
    ];

    protected array $casts = [
        'id'        => 'int',
        'user_id'   => '?int',
        'entity_id' => '?int',
    ];

    // Audit records are never edited, so only the creation time is stored.
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    /**
     * Joins the acting user's name and email for display.
     */
    public function withUser(): static
    {
        $this->select('audit_logs.*, users.name AS user_name, users.email AS user_email')
            ->join('users', 'users.id = audit_logs.user_id', 'left');

        return $this;
    }
}
