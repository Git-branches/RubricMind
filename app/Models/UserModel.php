<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    public const ROLES    = ['admin', 'teacher', 'student'];
    public const STATUSES = ['active', 'inactive'];

    // Every password field, including temporary passwords (72 is bcrypt's limit).
    public const PASSWORD_RULES = 'required|min_length[8]|max_length[72]';

    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name',
        'email',
        'password_hash',
        'role',
        'status',
        'must_change_password',
        'last_login_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'id'                   => 'int',
        'must_change_password' => 'bool',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->where('email', strtolower(trim($email)))->first();
    }

    /**
     * @return array<string, int> Active and inactive users counted per role.
     */
    public function countByRole(): array
    {
        $counts = array_fill_keys(self::ROLES, 0);

        $rows = $this->builder()
            ->select('role, COUNT(*) AS total')
            ->groupBy('role')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $counts[$row['role']] = (int) $row['total'];
        }

        return $counts;
    }
}
