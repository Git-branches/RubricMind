<?php

namespace Tests\Support;

use App\Libraries\Auth;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Demo;
use Config\Email;
use Config\Filters;
use Config\Services;

/**
 * Base class for HTTP feature tests against a freshly migrated database.
 */
abstract class FeatureTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected const PASSWORD = 'correct-horse';

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();

        // CSRF has its own test; other tests post without a token.
        $this->setCsrfEnabled(false);

        Services::resetSingle('auth');
        // Tests may inject a fake AI provider; don't let it leak into the next test.
        Services::resetSingle('essayAnalyzer');
        // Settings are cached per request; tests change them between requests.
        Services::resetSingle('systemSettings');
        // Each test gets a fresh mock cache, but the shared throttler would keep counting
        // sign-in and AI requests in the previous test's cache.
        Services::resetSingle('throttler');
        Services::resetSingle('mailer');
        // Tests never send email (.env may point at Mailpit or Gmail): emails only go to the outbox.
        config(Email::class)->fromEmail = '';
        // Defense Demo Mode is off unless a test turns it on (.env may turn it on).
        config(Demo::class)->passwordResetLinks = false;
    }

    protected function setCsrfEnabled(bool $enabled): void
    {
        $filters = config(Filters::class);
        $before  = array_values(array_diff($filters->globals['before'], ['csrf']));

        $filters->globals['before'] = $enabled ? ['csrf', ...$before] : $before;
    }

    protected function makeUser(string $role, array $overrides = []): array
    {
        $users = model(UserModel::class);

        $id = $users->insert($overrides + [
            'name'          => ucfirst($role) . ' User',
            'email'         => $role . '-' . uniqid() . '@example.com',
            'password_hash' => UserModel::hashPassword(self::PASSWORD),
            'role'          => $role,
            'status'        => 'active',
        ]);

        return $users->find($id);
    }

    protected function signedInAs(array $user): static
    {
        // The auth service caches the user for one request. In tests several
        // requests share a process, so start each one fresh.
        Services::resetSingle('auth');

        // Read the current password hash, which the session fingerprint is based on.
        $user = model(UserModel::class)->find($user['id']);

        return $this->withSession(['user_id' => $user['id'], 'auth_stamp' => Auth::stampFor($user)]);
    }
}
