<?php

namespace Tests\Feature;

use App\Models\UserModel;
use Tests\Support\ClassroomFixtures;
use Tests\Support\FeatureTestCase;

/**
 * Teachers create student accounts and enroll them (FR-02), plus the shared
 * modal and SweetAlert plumbing that the forms use.
 *
 * @internal
 */
final class TeacherStudentsTest extends FeatureTestCase
{
    use ClassroomFixtures;

    private const TEMP_PASSWORD = 'welcome-2026';

    private function addStudents(array $teacher, array $class, string $list, string $password = self::TEMP_PASSWORD)
    {
        return $this->signedInAs($teacher)->post('teacher/students', [
            'class_id'         => $class['id'],
            'students'         => $list,
            'password'         => $password,
            'password_confirm' => $password,
        ]);
    }

    public function testTeacherCreatesStudentAccountsFromAPastedList(): void
    {
        $teacher = $this->makeUser('teacher');
        $class   = $this->makeClass($teacher);

        $result = $this->addStudents($teacher, $class, "Juan Dela Cruz, Juan@Example.com\nMaria Santos\tmaria@example.com\nAna Reyes <ana@example.com>");

        $result->assertRedirect();
        $result->assertSessionHas('success');

        foreach (['juan@example.com' => 'Juan Dela Cruz', 'maria@example.com' => 'Maria Santos', 'ana@example.com' => 'Ana Reyes'] as $email => $name) {
            $student = model(UserModel::class)->findByEmail($email);
            $this->assertNotNull($student, "{$email} should have an account");
            $this->assertSame($name, $student['name']);
            $this->assertSame('student', $student['role']);
            $this->assertTrue($student['must_change_password']);
            $this->assertTrue(password_verify(self::TEMP_PASSWORD, $student['password_hash']));
            $this->seeInDatabase('class_students', ['class_id' => $class['id'], 'student_id' => $student['id']]);
        }

        $this->seeInDatabase('audit_logs', ['action' => 'class.students_enrolled', 'entity_id' => $class['id']]);
    }

    public function testExistingStudentsAreEnrolledWithoutAPassword(): void
    {
        $teacher = $this->makeUser('teacher');
        $class   = $this->makeClass($teacher);
        $student = $this->makeUser('student');

        $this->addStudents($teacher, $class, $student['email'], '')->assertSessionHas('success');

        $this->seeInDatabase('class_students', ['class_id' => $class['id'], 'student_id' => $student['id']]);
        $this->assertTrue(password_verify(self::PASSWORD, model(UserModel::class)->find($student['id'])['password_hash']));
    }

    public function testStaffEmailsAreSkippedAndNeverChanged(): void
    {
        $teacher = $this->makeUser('teacher');
        $class   = $this->makeClass($teacher);
        $other   = $this->makeUser('teacher');
        $admin   = $this->makeUser('admin');

        $result = $this->addStudents($teacher, $class, "Someone, {$other['email']}\n{$admin['email']}\nNew Kid, kid@example.com");

        $result->assertSessionHas('warning');
        $this->assertSame('teacher', model(UserModel::class)->find($other['id'])['role']);
        $this->assertSame('admin', model(UserModel::class)->find($admin['id'])['role']);
        $this->dontSeeInDatabase('class_students', ['student_id' => $other['id']]);
        $this->dontSeeInDatabase('class_students', ['student_id' => $admin['id']]);
        $this->assertNotNull(model(UserModel::class)->findByEmail('kid@example.com'));
    }

    public function testNewAccountsNeedATemporaryPassword(): void
    {
        $teacher = $this->makeUser('teacher');
        $class   = $this->makeClass($teacher);

        $result = $this->addStudents($teacher, $class, 'Juan Dela Cruz, juan@example.com', '');

        $result->assertOK();
        $result->assertSee('Please fix the following');
        $this->assertNull(model(UserModel::class)->findByEmail('juan@example.com'));
    }

    public function testNewEmailWithoutANameIsRejectedBeforeAnythingIsSaved(): void
    {
        $teacher = $this->makeUser('teacher');
        $class   = $this->makeClass($teacher);

        $result = $this->addStudents($teacher, $class, "Juan Dela Cruz, juan@example.com\nnoname@example.com");

        $result->assertSee('has no account yet, so a full name is needed');
        $this->assertNull(model(UserModel::class)->findByEmail('juan@example.com'));
    }

    public function testInvalidLinesAreReported(): void
    {
        $teacher = $this->makeUser('teacher');
        $class   = $this->makeClass($teacher);

        $result = $this->addStudents($teacher, $class, "Juan Dela Cruz juan-at-example.com");

        $result->assertSee('Line 1: no valid email address found');
    }

    public function testDuplicateLinesCreateOneAccount(): void
    {
        $teacher = $this->makeUser('teacher');
        $class   = $this->makeClass($teacher);

        $this->addStudents($teacher, $class, "Juan Dela Cruz, juan@example.com\nJuan Dela Cruz, JUAN@example.com");

        $this->assertSame(1, model(UserModel::class)->where('email', 'juan@example.com')->countAllResults());
    }

    public function testCannotAddStudentsToAnotherTeachersClass(): void
    {
        $teacher = $this->makeUser('teacher');
        $foreign = $this->makeClass($this->makeUser('teacher'));

        $result = $this->addStudents($teacher, $foreign, 'Juan Dela Cruz, juan@example.com');

        $result->assertSee('Choose one of your active classes.');
        $this->assertNull(model(UserModel::class)->findByEmail('juan@example.com'));
    }

    public function testStudentsPageListsOnlyTheTeachersOwnStudents(): void
    {
        $teacher = $this->makeUser('teacher');
        $mine    = $this->makeUser('student', ['name' => 'Mine Student']);
        $theirs  = $this->makeUser('student', ['name' => 'Their Student']);
        $this->enroll($this->makeClass($teacher), $mine);
        $this->enroll($this->makeClass($this->makeUser('teacher')), $theirs);

        $page = $this->signedInAs($teacher)->get('teacher/students');

        $page->assertOK();
        $page->assertSee('Mine Student');
        $page->assertDontSee('Their Student');
    }

    public function testStudentCannotOpenTeacherStudentsPage(): void
    {
        $this->signedInAs($this->makeUser('student'))->get('teacher/students')->assertStatus(403);
    }

    // Shared modal and messages

    public function testFormLoadsAsModalContentWithModalHeader(): void
    {
        $teacher = $this->makeUser('teacher');
        $this->makeClass($teacher);

        $result = $this->signedInAs($teacher)->withHeaders(['X-Modal' => '1'])->get('teacher/students/new');

        $result->assertOK();
        $body = $result->response()->getBody();
        $this->assertStringContainsString('data-modal-title', $body);
        $this->assertStringNotContainsString('class="sidebar"', $body);
        $this->assertStringNotContainsString('<html', $body);
    }

    public function testSuccessfulModalSaveReturnsRedirectAsJsonAndKeepsTheMessage(): void
    {
        $teacher = $this->makeUser('teacher');

        $result = $this->signedInAs($teacher)->withHeaders(['X-Modal' => '1'])
            ->post('teacher/classes', ['name' => 'Modal Class']);

        $result->assertStatus(200);
        $data = json_decode($result->response()->getBody(), true);
        $this->assertStringContainsString('/teacher/classes/', $data['redirect']);
        $result->assertSessionHas('success');
        $this->seeInDatabase('classes', ['name' => 'Modal Class']);
    }

    public function testFailedModalSaveReturnsTheFormWithErrors(): void
    {
        $teacher = $this->makeUser('teacher');

        $result = $this->signedInAs($teacher)->withHeaders(['X-Modal' => '1'])->post('teacher/classes', ['name' => '']);

        $result->assertOK();
        $body = $result->response()->getBody();
        $this->assertStringContainsString('data-form-errors', $body);
        $this->assertStringContainsString('data-modal-title', $body);
    }

    public function testSignInShowsAWelcomeMessageOnTheDashboard(): void
    {
        $teacher = $this->makeUser('teacher', ['name' => 'Maria Santos']);

        $result = $this->post('login', ['email' => $teacher['email'], 'password' => self::PASSWORD]);

        $this->assertStringEndsWith('/teacher', $result->getRedirectUrl());
        $result->assertSessionHas('success', 'Welcome back, Maria!');
    }

    public function testFlashMessagesAreHandedToSweetAlert(): void
    {
        session()->setFlashdata('success', 'Saved </script> it.');

        $html = view('partials/flash', ['error' => 'Bad thing.']);

        preg_match('#<script type="application/json" id="flash-messages">(.*?)</script>#s', $html, $match);

        $this->assertNotEmpty($match, 'Messages are passed to SweetAlert as JSON.');
        $this->assertSame(['success' => 'Saved </script> it.', 'error' => 'Bad thing.'], json_decode($match[1], true));
        $this->assertStringNotContainsString('Saved </script>', $match[1], 'A "</script>" in a message must be escaped.');
        $this->assertStringContainsString('<noscript>', $html);
    }
}
