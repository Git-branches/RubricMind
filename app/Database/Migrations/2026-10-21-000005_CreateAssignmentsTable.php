<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAssignmentsTable extends Migration
{
    public function up()
    {
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
            'rubric_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'instructions' => [
                'type' => 'TEXT',
            ],
            'due_at' => [
                'type' => 'DATETIME',
            ],
            // draft: hidden from students. published: open for submissions.
            // closed: visible, but no new submissions or edits.
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['draft', 'published', 'closed'],
                'default'    => 'draft',
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
        $this->forge->addKey(['class_id', 'status']);
        $this->forge->addKey('rubric_id');
        $this->forge->addForeignKey('class_id', 'classes', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('rubric_id', 'rubrics', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('assignments');
    }

    public function down()
    {
        $this->forge->dropTable('assignments');
    }
}
