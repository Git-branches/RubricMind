<?php

namespace Tests\Feature;

use App\Libraries\Auth;
use App\Models\UserModel;
use Config\Services;
use Tests\Support\ClassroomFixtures;
use Tests\Support\FeatureTestCase;

/**
 * Fixes from the security review: session invalidation, sign-in throttling
 * per account, and AI request limits.
 *
 * @internal
 */
final class SecurityTest extends FeatureTestCase
{
    use ClassroomFixtures;

    /**
     * A session as it was before the user's password changed.
     */
    private function oldSession(array $user): static
    {
        Services::resetSingle('auth');

        return $this->withSession(['user_id' => $user['id'], 'auth_stamp' => Auth::stampFor($user)]);
    }

    public function testAdminPasswordResetSignsOutTheUsersOtherSessions(): void
    {
        $admin   = $this->makeUser('admin');
        $teacher = $this->makeUser('teacher');

        $this->signedInAs($admin)->post("admin/users/{$teacher['id']}/password", [
            'password'         => 'new-temporary-1',
            'password_confirm' => 'new-temporary-1',
        ])->assertRedirect();

        $result = $this->oldSession($teacher)->get('teacher');

        $result->assertRedirect();
        $this->assertStringEndsWith('/login', $result->getRedirectUrl());
    }

    public function testChangingOwnPasswordKeepsThisSessionButEndsOthers(): void
    {
        $teacher = $this->makeUser('teacher');

        $this->signedInAs($teacher)->post('account/password', [
            'current_password' => self::PASSWORD,
            'password'         => 'my-new-password',
            'password_confirm' => 'my-new-password',
        ])->assertRedirect();

        // The session that changed the password carries the new fingerprint.
        $this->assertSame(Auth::stampFor(model(UserModel::class)->find($teacher['id'])), $_SESSION['auth_stamp'] ?? null);

        // A session opened before the change is signed out.
        $result = $this->oldSession($teacher)->get('teacher');
        $this->assertStringEndsWith('/login', $result->getRedirectUrl());
    }

    public function testSessionWithoutFingerprintIsRejected(): void
    {
        $teacher = $this->makeUser('teacher');
        Services::resetSingle('auth');

        $result = $this->withSession(['user_id' => $teacher['id']])->get('teacher');

        $this->assertStringEndsWith('/login', $result->getRedirectUrl());
    }

    public function testSignInIsLimitedPerAccountAcrossAddresses(): void
    {
        $teacher = $this->makeUser('teacher');

        for ($i = 1; $i <= 10; $i++) {
            service('superglobals')->setServer('REMOTE_ADDR', "10.0.0.{$i}");
            $this->post('login', ['email' => $teacher['email'], 'password' => 'wrong-password']);
        }

        service('superglobals')->setServer('REMOTE_ADDR', '10.0.0.99');
        $result = $this->post('login', ['email' => $teacher['email'], 'password' => self::PASSWORD]);

        $result->assertSee('Too many sign-in attempts.');
        $result->assertSessionMissing('user_id');
    }

    public function testAiRequestsAreLimitedPerTeacher(): void
    {
        [$teacher, $student, , , $assignment] = $this->classroom();
        $submission = $this->submitEssay($assignment, $student);
        $url        = "teacher/submissions/{$submission['id']}/ai";

        for ($i = 0; $i < 20; $i++) {
            $this->signedInAs($teacher)->post($url)->assertSessionHas('success');
        }

        $this->signedInAs($teacher)->post($url)->assertSessionHas('warning');
    }
}
