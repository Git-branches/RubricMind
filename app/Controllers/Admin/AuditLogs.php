<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditLogModel;

class AuditLogs extends BaseController
{
    private const PER_PAGE = 25;

    public function index(): string
    {
        $logs = model(AuditLogModel::class);

        return view('admin/audit_logs/index', [
            'logs'  => $logs->withUser()->orderBy('audit_logs.id', 'DESC')->paginate(self::PER_PAGE),
            'pager' => $logs->pager,
        ]);
    }
}
