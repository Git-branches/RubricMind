<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateClassesTables extends Migration
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
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'section' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'school_year' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            // Archived classes stay readable but no longer accept submissions.
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['active', 'archived'],
                'default'    => 'active',
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
        $this->forge->addKey(['teacher_id', 'status']);
        $this->forge->addForeignKey('teacher_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('classes');

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'class_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'student_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['class_id', 'student_id']);
        $this->forge->addKey('student_id');
        $this->forge->addForeignKey('class_id', 'classes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('student_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('class_students');
    }

    public function down()
    {
        $this->forge->dropTable('class_students');
        $this->forge->dropTable('classes');
    }
}
