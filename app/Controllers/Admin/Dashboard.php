<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditLogModel;
use App\Models\UserModel;

class Dashboard extends BaseController
{
    public function index(): string
    {
        return view('admin/dashboard', [
            'counts'     => model(UserModel::class)->countByRole(),
            'recentLogs' => model(AuditLogModel::class)->withUser()->orderBy('audit_logs.id', 'DESC')->findAll(8),
        ]);
    }
}
