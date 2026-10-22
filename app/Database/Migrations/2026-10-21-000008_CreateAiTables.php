<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAiTables extends Migration
{
    public function up()
    {
        // AI processing status, separate from the grading workflow (FR-06).
        // Null means AI has never been requested for this submission.
        $this->forge->addColumn('submissions', [
            'ai_status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'processing', 'done', 'failed'],
                'null'       => true,
                'after'      => 'status',
            ],
        ]);

        // One row per AI request, kept for auditing (FR-13).
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
            'requested_by' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'provider' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'model' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'prompt_version' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'processing', 'done', 'failed'],
                'default'    => 'pending',
            ],
            'error' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            // Suggested overall feedback (FR-07): JSON with summary, strengths, improvements.
            'feedback' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            // The provider's unprocessed output, for explaining suggestions later.
            'raw_response' => [
                'type' => 'MEDIUMTEXT',
                'null' => true,
            ],
            'started_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'finished_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['submission_id', 'id']);
        $this->forge->addForeignKey('submission_id', 'submissions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('requested_by', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('ai_runs');

        // Suggested score per criterion (FR-08).
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'ai_run_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'criterion_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'level_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'points' => [
                'type'     => 'SMALLINT',
                'unsigned' => true,
            ],
            'rationale' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['ai_run_id', 'criterion_id']);
        $this->forge->addForeignKey('ai_run_id', 'ai_runs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('criterion_id', 'rubric_criteria', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('level_id', 'rubric_levels', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('ai_criterion_suggestions');

        // Quoted evidence linked to a criterion (FR-08). Offsets are character
        // positions in the submission text; null when the quote was not found.
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'ai_run_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'criterion_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'quote' => [
                'type' => 'TEXT',
            ],
            'note' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'start_offset' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'length' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'verified' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('ai_run_id');
        $this->forge->addForeignKey('ai_run_id', 'ai_runs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('criterion_id', 'rubric_criteria', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('evidence_highlights');

        // Recurring writing-mechanics patterns (FR-10).
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'ai_run_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'pattern' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'occurrences' => [
                'type'     => 'SMALLINT',
                'unsigned' => true,
                'default'  => 1,
            ],
            // JSON list of short example snippets from the essay.
            'examples' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'suggestion' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('ai_run_id');
        $this->forge->addForeignKey('ai_run_id', 'ai_runs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('grammar_patterns');
    }

    public function down()
    {
        $this->forge->dropTable('grammar_patterns');
        $this->forge->dropTable('evidence_highlights');
        $this->forge->dropTable('ai_criterion_suggestions');
        $this->forge->dropTable('ai_runs');
        $this->forge->dropColumn('submissions', 'ai_status');
    }
}
