<?php

namespace App\Database\Migrations;

use App\Models\ClassModel;
use CodeIgniter\Database\Migration;

class AddClassJoinCodes extends Migration
{
    public function up()
    {
        // Class-joining codes (FR-02, FR-14): students enter one to join a class.
        $this->forge->addColumn('classes', [
            'join_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 12,
                'null'       => true,
                'after'      => 'description',
            ],
            'join_code_enabled' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'after'      => 'join_code',
            ],
        ]);
        $this->forge->addKey('join_code', false, true, 'classes_join_code');
        $this->forge->processIndexes('classes');

        // Existing classes get a code too, so teachers can share it right away.
        foreach ($this->db->table('classes')->select('id')->get()->getResultArray() as $row) {
            $this->db->table('classes')->where('id', $row['id'])->update(['join_code' => ClassModel::newJoinCode($this->db)]);
        }
    }

    public function down()
    {
        $this->forge->dropKey('classes', 'classes_join_code');
        $this->forge->dropColumn('classes', ['join_code', 'join_code_enabled']);
    }
}
