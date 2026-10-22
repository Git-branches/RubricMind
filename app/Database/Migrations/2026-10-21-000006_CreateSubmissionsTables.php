<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSubmissionsTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'assignment_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'student_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'content' => [
                'type' => 'MEDIUMTEXT',
            ],
            'word_count' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 0,
            ],
            // Grading workflow (FR-06).
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['submitted', 'ai_evaluated', 'teacher_reviewed', 'finalized', 'released'],
                'default'    => 'submitted',
            ],
            'is_late' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'submitted_at' => [
                'type' => 'DATETIME',
            ],
            // Set when the teacher clicks Start Grading or requests AI evaluation.
            // After this the student can no longer edit the submission.
            'locked_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        // One submission per student per assignment (FR-05).
        $this->forge->addUniqueKey(['assignment_id', 'student_id']);
        $this->forge->addKey('student_id');
        $this->forge->addKey(['assignment_id', 'status']);
        $this->forge->addForeignKey('assignment_id', 'assignments', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('student_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('submissions');

        // Every saved version of a submission, oldest first.
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'submission_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'content' => [
                'type' => 'MEDIUMTEXT',
            ],
            'word_count' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('submission_id');
        $this->forge->addForeignKey('submission_id', 'submissions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('submission_revisions');
    }

    public function down()
    {
        $this->forge->dropTable('submission_revisions');
        $this->forge->dropTable('submissions');
    }
}
