<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSettingsAndRetention extends Migration
{
    public function up()
    {
        // System-level settings managed by administrators (§3). One row per key.
        $this->forge->addField([
            'key' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'value' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'updated_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('key');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('settings');

        // When a class was archived, for the data-retention rule (§5).
        $this->forge->addColumn('classes', [
            'archived_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'status',
            ],
        ]);

        // Classes archived before this column existed: use their last update time.
        $this->db->table('classes')->set('archived_at', 'updated_at', false)->where('status', 'archived')->update();
    }

    public function down()
    {
        $this->forge->dropColumn('classes', 'archived_at');
        $this->forge->dropTable('settings');
    }
}
