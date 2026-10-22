<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateResetsOutboxAndFiles extends Migration
{
    public function up()
    {
        // One-time password reset links sent by email (FR-01). Only a hash of the token is stored.
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'token_hash' => [
                'type'       => 'CHAR',
                'constraint' => 64,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
            ],
            'used_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('password_resets');

        // Every email the system sends. Without email settings, this is also how
        // emails are delivered: an administrator reads them on the Email outbox page.
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'to_email' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
            ],
            'subject' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'body' => [
                'type' => 'TEXT',
            ],
            // sent: delivered to the mail server. saved: email is not set up, kept here only. failed: sending failed.
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
            ],
            'error' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('email_outbox');

        // The original PDF or DOCX file, when the student uploaded one (FR-05).
        $this->forge->addColumn('submissions', [
            'file_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'word_count',
            ],
            'file_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'file_name',
            ],
            'file_size' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'after'    => 'file_path',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('submissions', ['file_name', 'file_path', 'file_size']);
        $this->forge->dropTable('email_outbox');
        $this->forge->dropTable('password_resets');
    }
}
