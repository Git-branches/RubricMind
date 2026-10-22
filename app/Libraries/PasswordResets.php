<?php

namespace App\Libraries;

use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;

/**
 * One-time password reset links (FR-01, self-service by email).
 *
 * The link carries a random token; only its SHA-256 hash is stored, so a copy
 * of the database cannot be used to reset passwords. A link works once, for
 * LIFETIME_MINUTES, and only for an active account. Requesting a new link
 * cancels the older ones.
 */
final class PasswordResets
{
    public const LIFETIME_MINUTES = 60;

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * Creates a reset link token for the user and cancels their earlier unused ones.
     *
     * @return string The token to put in the link (shown only once).
     */
    public function create(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $now   = time();

        $this->db->table('password_resets')->where('user_id', $userId)->where('used_at', null)->delete();
        $this->db->table('password_resets')->insert([
            'user_id'    => $userId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', $now + self::LIFETIME_MINUTES * 60),
            'created_at' => date('Y-m-d H:i:s', $now),
        ]);

        return $token;
    }

    /**
     * The user a token belongs to, if the link is unused, not expired, and the account is active.
     */
    public function userFor(string $token): ?array
    {
        if (preg_match('/^[0-9a-f]{64}$/', $token) !== 1) {
            return null;
        }

        $reset = $this->db->table('password_resets')
            ->where('token_hash', hash('sha256', $token))
            ->where('used_at', null)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->get()
            ->getRowArray();

        if ($reset === null) {
            return null;
        }

        $user = model(UserModel::class)->find($reset['user_id']);

        return $user !== null && $user['status'] === 'active' ? $user : null;
    }

    /**
     * Sets the new password and uses up every reset link of the user.
     * Changing the password hash also signs the user out everywhere (see Auth::stampFor()).
     */
    public function complete(array $user, string $password): void
    {
        $this->db->transException(true)->transStart();

        model(UserModel::class)->update($user['id'], [
            'password_hash'        => UserModel::hashPassword($password),
            'must_change_password' => false,
        ]);

        $this->db->table('password_resets')
            ->where('user_id', $user['id'])
            ->where('used_at', null)
            ->update(['used_at' => date('Y-m-d H:i:s')]);

        $this->db->transComplete();
    }
}
