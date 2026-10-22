<?php

namespace App\Database\Seeds;

use App\Models\UserModel;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * Creates the first administrator account.
 *
 * Run: php spark db:seed AdminSeeder
 */
class AdminSeeder extends Seeder
{
    private const EMAIL = 'admin@rubricmind.local';

    public function run()
    {
        $users = model(UserModel::class);

        if ($users->findByEmail(self::EMAIL) !== null) {
            CLI::write('Admin account ' . self::EMAIL . ' already exists. Nothing changed.', 'yellow');

            return;
        }

        $password  = (string) env('RUBRICMIND_ADMIN_PASSWORD', '');
        $generated = $password === '';

        if ($generated) {
            $password = bin2hex(random_bytes(6));
        }

        $users->insert([
            'name'                 => 'System Administrator',
            'email'                => self::EMAIL,
            'password_hash'        => UserModel::hashPassword($password),
            'role'                 => 'admin',
            'status'               => 'active',
            'must_change_password' => true,
        ]);

        CLI::write('Admin account created.', 'green');
        CLI::write('  Email:    ' . self::EMAIL);

        if ($generated) {
            CLI::write('  Password: ' . $password . '  (shown only once; you will change it at first sign-in)');
        }
    }
}
