<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRubricsTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'teacher_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            // Set when the rubric is first used to grade a submission (FR-04).
            // A locked rubric cannot be edited; the teacher duplicates it instead.
            'locked_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'copied_from_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
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
        $this->forge->addKey('teacher_id');
        $this->forge->addForeignKey('teacher_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('copied_from_id', 'rubrics', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('rubrics');

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'rubric_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'max_points' => [
                'type'     => 'SMALLINT',
                'unsigned' => true,
            ],
            'sort_order' => [
                'type'     => 'SMALLINT',
                'unsigned' => true,
                'default'  => 0,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['rubric_id', 'sort_order']);
        $this->forge->addForeignKey('rubric_id', 'rubrics', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('rubric_criteria');

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'criterion_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'label' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'points' => [
                'type'     => 'SMALLINT',
                'unsigned' => true,
            ],
            'sort_order' => [
                'type'     => 'SMALLINT',
                'unsigned' => true,
                'default'  => 0,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['criterion_id', 'sort_order']);
        $this->forge->addForeignKey('criterion_id', 'rubric_criteria', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('rubric_levels');
    }

    public function down()
    {
        $this->forge->dropTable('rubric_levels');
        $this->forge->dropTable('rubric_criteria');
        $this->forge->dropTable('rubrics');
    }
}
