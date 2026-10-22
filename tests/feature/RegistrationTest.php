<?php

namespace Tests\Feature;

use App\Models\UserModel;
use Tests\Support\FeatureTestCase;

/**
 * Public student self-registration (FR-14).
 *
 * @internal
 */
final class RegistrationTest extends FeatureTestCase
{
    private const NEW_PASSWORD = 'my-own-passphrase';

    private function register(array $overrides = [])
    {
        return $this->post('register', $overrides + [
            'name'             => '  Ana   Lopez ',
            'email'            => 'Ana.Lopez@Example.com',
            'password'         => self::NEW_PASSWORD,
            'password_confirm' => self::NEW_PASSWORD,
        ]);
    }

    public function testSignInPageOffersStudentRegistration(): void
    {
        $this->get('login')->assertSee('Create Student Account');
        $this->get('register')->assertSee('Create account');
    }

    public function testCreatesAnActiveStudentAccountThatCanSignIn(): void
    {
        $result = $this->register();

        $result->assertRedirectTo(site_url('login'));
        $result->assertSessionHas('registered_email', 'ana.lopez@example.com');

        $user = model(UserModel::class)->findByEmail('ana.lopez@example.com');
        $this->assertSame('Ana Lopez', $user['name']);
        $this->assertSame('student', $user['role']);
        $this->assertSame('active', $user['status']);
        $this->assertFalse($user['must_change_password']);
        $this->assertTrue(password_verify(self::NEW_PASSWORD, $user['password_hash']));
        $this->assertNotSame(self::NEW_PASSWORD, $user['password_hash']);
        $this->seeInDatabase('audit_logs', ['action' => 'auth.registered', 'entity_id' => $user['id']]);

        // Not enrolled anywhere until the student joins a class.
        $this->assertSame(0, db_connect()->table('class_students')->where('student_id', $user['id'])->countAllResults());

        $this->post('login', ['email' => 'ana.lopez@example.com', 'password' => self::NEW_PASSWORD])->assertSessionHas('user_id', $user['id']);
    }

    public function testCannotRegisterAsTeacherOrAdmin(): void
    {
        $this->register(['role' => 'admin', 'status' => 'active', 'must_change_password' => '0']);

        $this->assertSame('student', model(UserModel::class)->findByEmail('ana.lopez@example.com')['role']);
    }

    public function testValidatesTheForm(): void
    {
        $this->register(['name' => '', 'email' => 'not-an-email', 'password' => 'short', 'password_confirm' => 'other'])
            ->assertSee('The full name field is required.');

        $this->register(['password_confirm' => 'something-else'])->assertSee('The two passwords do not match.');

        $this->assertSame(0, model(UserModel::class)->where('role', 'student')->countAllResults());
    }

    public function testEmailMustBeUniqueWithoutRevealingTheAccount(): void
    {
        $teacher = $this->makeUser('teacher', ['name' => 'Maria Santos']);

        $result = $this->register(['email' => strtoupper($teacher['email'])]);

        $result->assertSee('An account already uses this email.');
        $result->assertDontSee('Maria Santos');
        $result->assertDontSee('teacher account');
        $this->assertSame('teacher', model(UserModel::class)->find($teacher['id'])['role']);
    }

    public function testRegistrationCanBeTurnedOff(): void
    {
        $admin = $this->makeUser('admin');
        service('systemSettings')->save(['registration_enabled' => '0'], $admin['id']);

        $this->get('login')->assertDontSee('Create Student Account');
        $this->get('register')->assertSee('Student sign-up is turned off');
        $this->register();

        $this->assertNull(model(UserModel::class)->findByEmail('ana.lopez@example.com'));
    }

    public function testSignedInUsersAreSentHome(): void
    {
        $student = $this->makeUser('student');

        $this->signedInAs($student)->get('register')->assertRedirectTo(site_url('/'));
    }
}
