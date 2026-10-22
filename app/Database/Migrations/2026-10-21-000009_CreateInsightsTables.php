<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInsightsTables extends Migration
{
    public function up()
    {
        // A teacher's review of a Style Growth Tracker result (FR-09). The comparison
        // itself is computed from the essays on demand; this row keeps a snapshot of
        // what the teacher saw and their note. It never affects the grade.
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
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['insufficient_history', 'consistent', 'notable_change'],
            ],
            'history_count' => [
                'type'     => 'SMALLINT',
                'unsigned' => true,
                'default'  => 0,
            ],
            // JSON snapshot of the metric comparison at review time.
            'details' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'reviewed_by' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'review_note' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'reviewed_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('submission_id');
        $this->forge->addForeignKey('submission_id', 'submissions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('reviewed_by', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('style_flags');

        // Draft lesson plans generated from class insights (FR-11). Teachers edit them freely.
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
            'teacher_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'content' => [
                'type' => 'MEDIUMTEXT',
            ],
            // JSON: the learning gaps the draft was built from.
            'focus' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'provider' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
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
        $this->forge->addKey('assignment_id');
        $this->forge->addForeignKey('assignment_id', 'assignments', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('teacher_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('lesson_plans');
    }

    public function down()
    {
        $this->forge->dropTable('lesson_plans');
        $this->forge->dropTable('style_flags');
    }
}
