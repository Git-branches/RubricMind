<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Copies of the emails the system sent or saved (see App\Libraries\Mailer).
 * Rows are written by the Mailer; this model only reads them.
 */
class EmailOutboxModel extends Model
{
    public const STATUS_LABELS = [
        'sent'   => 'Sent',
        'saved'  => 'Saved, not sent',
        'failed' => 'Failed',
    ];

    protected $table         = 'email_outbox';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [];

    protected array $casts = [
        'id' => 'int',
    ];
}
