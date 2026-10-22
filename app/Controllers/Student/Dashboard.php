<?php

namespace App\Controllers\Student;

use App\Controllers\BaseController;
use App\Models\AssignmentModel;

class Dashboard extends BaseController
{
    public function index(): string
    {
        return view('student/dashboard', [
            'assignments' => model(AssignmentModel::class)->forStudent($this->userId()),
        ]);
    }
}
