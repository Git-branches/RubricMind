<?php

namespace App\Libraries;

use App\Models\UserModel;
use CodeIgniter\Session\Session;

/**
 * Session-based authentication for RubricMind.
 *
 * Access it through service('auth').
 */
class Auth
{
    private const SESSION_KEY = 'user_id';

    // Fingerprint of the password the session signed in with. When the password
    // changes (or an admin resets it), older sessions no longer match and end.
    private const STAMP_KEY = 'auth_stamp';

    // A real bcrypt hash, verified when the email is unknown so that both
    // failure cases take the same time and don't reveal which emails exist.
    private const DUMMY_HASH = '$2y$10$FJRSywKI9ajX1me70pF4euK5vMxK8MTlYM1yHV97T2yBlUCQ4pgd2';

    private ?array $user = null;
    private bool $loaded = false;

    public function __construct(
        private readonly Session $session,
        private readonly UserModel $users,
    ) {
    }

    /**
     * Checks credentials without signing the user in.
     *
     * @return array{user: array|null, error: string|null}
     */
    public function attempt(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            password_verify($password, self::DUMMY_HASH);

            return ['user' => null, 'error' => 'invalid'];
        }

        if (! password_verify($password, $user['password_hash'])) {
            return ['user' => $user, 'error' => 'invalid'];
        }

        if ($user['status'] !== 'active') {
            return ['user' => $user, 'error' => 'inactive'];
        }

        return ['user' => $user, 'error' => null];
    }

    public function login(array $user): void
    {
        // A new session ID on sign-in prevents session fixation.
        $this->session->regenerate(true);
        $this->session->set([self::SESSION_KEY => $user['id'], self::STAMP_KEY => self::stampFor($user)]);

        $this->users->update($user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);

        $this->user   = $user;
        $this->loaded = true;
    }

    public function logout(): void
    {
        // Clear everything and issue a new session ID. The session itself is kept
        // (not destroyed) so the "signed out" flash message can still be shown.
        $this->session->remove(array_keys($this->session->get() ?? []));
        $this->session->regenerate(true);

        $this->user   = null;
        $this->loaded = true;
    }

    /**
     * The signed-in user, reloaded from the database once per request so that
     * deactivating an account or changing a role takes effect immediately.
     */
    public function user(): ?array
    {
        if ($this->loaded) {
            return $this->user;
        }

        $this->loaded = true;
        $id           = $this->session->get(self::SESSION_KEY);

        if ($id === null) {
            return null;
        }

        $user  = $this->users->find($id);
        $stamp = $this->session->get(self::STAMP_KEY);

        if ($user === null || $user['status'] !== 'active' || ! is_string($stamp) || ! hash_equals(self::stampFor($user), $stamp)) {
            $this->session->remove([self::SESSION_KEY, self::STAMP_KEY]);

            return null;
        }

        return $this->user = $user;
    }

    /**
     * Keeps the current session signed in after the user changed their own
     * password (other sessions of the same user are signed out). Starts a new
     * session ID as well.
     */
    public function passwordChanged(array $user): void
    {
        $this->session->regenerate(true);
        $this->session->set(self::STAMP_KEY, self::stampFor($user));
        $this->refresh();
    }

    /**
     * A short fingerprint of the stored password hash (never the hash itself).
     */
    public static function stampFor(array $user): string
    {
        return substr(hash('sha256', 'rubricmind-session|' . $user['id'] . '|' . $user['password_hash']), 0, 32);
    }

    public function id(): ?int
    {
        return $this->user()['id'] ?? null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function hasRole(string ...$roles): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user['role'], $roles, true);
    }

    /**
     * Clears the per-request cache after the current user's record changes.
     */
    public function refresh(): void
    {
        $this->loaded = false;
        $this->user   = null;
    }
}
