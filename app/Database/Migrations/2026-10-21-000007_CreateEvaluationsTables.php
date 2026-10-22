<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEvaluationsTables extends Migration
{
    public function up()
    {
        // One evaluation per submission, created when the teacher starts grading.
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
            // The rubric is locked when grading starts, so its criteria stay the same.
            'rubric_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'teacher_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            // Sum of the criterion scores entered so far.
            'total_score' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 0,
            ],
            'max_score' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            // Shown to the student once released (FR-12).
            'student_feedback' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            // Never shown to the student (FR-12).
            'teacher_notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'started_at' => [
                'type' => 'DATETIME',
            ],
            'reviewed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'finalized_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'released_at' => [
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
        $this->forge->addUniqueKey('submission_id');
        $this->forge->addKey('rubric_id');
        $this->forge->addForeignKey('submission_id', 'submissions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('rubric_id', 'rubrics', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('teacher_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('evaluations');

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'evaluation_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'criterion_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            // The chosen performance level, when the criterion has levels.
            'level_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            // Null until the teacher scores this criterion.
            'points' => [
                'type'     => 'SMALLINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'comment' => [
                'type' => 'TEXT',
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
        $this->forge->addUniqueKey(['evaluation_id', 'criterion_id']);
        $this->forge->addKey('criterion_id');
        $this->forge->addForeignKey('evaluation_id', 'evaluations', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('criterion_id', 'rubric_criteria', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('level_id', 'rubric_levels', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('criterion_scores');
    }

    public function down()
    {
        $this->forge->dropTable('criterion_scores');
        $this->forge->dropTable('evaluations');
    }
}
