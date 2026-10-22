<?php

namespace Tests\Feature;

use App\Models\ClassModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Tests\Support\ClassroomFixtures;
use Tests\Support\FeatureTestCase;

/**
 * Class codes and Join a Class (FR-02, FR-14).
 *
 * @internal
 */
final class ClassJoinTest extends FeatureTestCase
{
    use ClassroomFixtures;

    private function join(array $student, string $code)
    {
        return $this->signedInAs($student)->post('student/classes/join', ['code' => $code]);
    }

    private function enrolled(array $class, array $student): bool
    {
        return model(ClassModel::class)->isStudentEnrolled($class['id'], $student['id']);
    }

    public function testEveryClassGetsAnUnpredictableCode(): void
    {
        $teacher = $this->makeUser('teacher');
        $first   = $this->makeClass($teacher);
        $second  = $this->makeClass($teacher);

        $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{8}$/', $first['join_code']);
        $this->assertNotSame($first['join_code'], $second['join_code']);
        $this->assertTrue($first['join_code_enabled']);

        $this->signedInAs($teacher)->get("teacher/classes/{$first['id']}")->assertSee(ClassModel::formatJoinCode($first['join_code']));
    }

    public function testTeacherClassListShowsEachCode(): void
    {
        $teacher = $this->makeUser('teacher');
        $first   = $this->makeClass($teacher, ['name' => 'English 10']);
        $second  = $this->makeClass($teacher, ['name' => 'Science 10']);

        $page = $this->signedInAs($teacher)->get('teacher/classes');

        $page->assertSee(ClassModel::formatJoinCode($first['join_code']));
        $page->assertSee(ClassModel::formatJoinCode($second['join_code']));
    }

    public function testStudentOpensAJoinedClass(): void
    {
        [$teacher, $student, $class, , $assignment] = $this->classroom();
        $draft = $this->makeAssignment($class, $this->makeRubric($teacher), ['title' => 'Secret Draft', 'status' => 'draft']);

        $this->signedInAs($student)->get('student/classes')->assertSee(site_url("student/classes/{$class['id']}"));

        $page = $this->signedInAs($student)->get("student/classes/{$class['id']}");
        $page->assertSee('English 10');
        $page->assertSee($teacher['name']);
        $page->assertSee($assignment['title']);
        $page->assertDontSee($draft['title']);
    }

    public function testStudentCannotOpenAClassTheyAreNotIn(): void
    {
        [, , $class] = $this->classroom();
        $outsider    = $this->makeUser('student');

        $this->expectException(PageNotFoundException::class);
        $this->signedInAs($outsider)->get("student/classes/{$class['id']}");
    }

    public function testStudentJoinsWithAValidCode(): void
    {
        [, , $class, , $assignment] = $this->classroom();
        $student                    = $this->makeUser('student');

        // Lowercase, spaces, and the dash do not matter.
        $code   = strtolower(' ' . ClassModel::formatJoinCode($class['join_code']) . ' ');
        $result = $this->join($student, $code);

        $result->assertRedirectTo(site_url('student/classes'));
        $result->assertSessionHas('success', 'You joined English 10. Its assignments now appear in My assignments.');
        $this->assertTrue($this->enrolled($class, $student));
        $this->seeInDatabase('audit_logs', ['action' => 'class.joined', 'entity_id' => $class['id']]);

        $this->signedInAs($student)->get('student/classes')->assertSee('English 10');
        $this->signedInAs($student)->get("student/assignments/{$assignment['id']}")->assertSee($assignment['title']);
    }

    public function testOneAccountJoinsSeveralClasses(): void
    {
        $teacher = $this->makeUser('teacher');
        $english = $this->makeClass($teacher, ['name' => 'English 10']);
        $science = $this->makeClass($this->makeUser('teacher'), ['name' => 'Science 10']);
        $student = $this->makeUser('student');
        $users   = db_connect()->table('users')->countAllResults();

        $this->join($student, $english['join_code'])->assertSessionHas('success');
        $this->join($student, $science['join_code'])->assertSessionHas('success');

        $this->assertTrue($this->enrolled($english, $student));
        $this->assertTrue($this->enrolled($science, $student));
        $this->assertSame($users, db_connect()->table('users')->countAllResults(), 'Joining must not create accounts.');
    }

    public function testJoiningTwiceSaysAlreadyEnrolled(): void
    {
        [, $student, $class] = $this->classroom();

        $this->join($student, $class['join_code'])->assertSessionHas('warning', 'You are already enrolled in English 10.');
        $this->assertSame(1, db_connect()->table('class_students')->where('class_id', $class['id'])->where('student_id', $student['id'])->countAllResults());
    }

    public function testInvalidDisabledAndArchivedCodesAreRefused(): void
    {
        $teacher = $this->makeUser('teacher');
        $class   = $this->makeClass($teacher);
        $student = $this->makeUser('student');

        $this->join($student, 'ZZZZ-ZZZZ')->assertSee('That class code is not valid.');
        $this->join($student, '')->assertSee('Enter the class code from your teacher.');

        model(ClassModel::class)->update($class['id'], ['join_code_enabled' => false]);
        $this->join($student, $class['join_code'])->assertSee('This class code is turned off.');

        model(ClassModel::class)->update($class['id'], ['join_code_enabled' => true, 'status' => 'archived']);
        $this->join($student, $class['join_code'])->assertSee('This class is no longer accepting students.');

        $this->assertFalse($this->enrolled($class, $student));
    }

    public function testRepeatedWrongCodesAreLimited(): void
    {
        $class   = $this->makeClass($this->makeUser('teacher'));
        $student = $this->makeUser('student');

        for ($i = 0; $i < 10; $i++) {
            $this->join($student, 'WRNG-' . sprintf('%04d', $i));
        }

        // Even the right code is refused until the wait is over.
        $this->join($student, $class['join_code'])->assertSessionHas('error');
        $this->assertFalse($this->enrolled($class, $student));
    }

    public function testTeacherRegeneratesAndTurnsOffTheCode(): void
    {
        $teacher = $this->makeUser('teacher');
        $class   = $this->makeClass($teacher);
        $student = $this->makeUser('student');

        $this->signedInAs($teacher)->post("teacher/classes/{$class['id']}/code/regenerate")->assertSessionHas('success');
        $new = model(ClassModel::class)->find($class['id']);
        $this->assertNotSame($class['join_code'], $new['join_code']);
        $this->join($student, $class['join_code'])->assertSee('That class code is not valid.');

        $this->signedInAs($teacher)->post("teacher/classes/{$class['id']}/code/toggle");
        $this->assertFalse(model(ClassModel::class)->find($class['id'])['join_code_enabled']);
        $this->join($student, $new['join_code'])->assertSee('This class code is turned off.');

        $this->signedInAs($teacher)->post("teacher/classes/{$class['id']}/code/toggle");
        $this->join($student, $new['join_code'])->assertSessionHas('success');
    }

    public function testOtherTeachersCannotChangeTheCode(): void
    {
        $class = $this->makeClass($this->makeUser('teacher'));
        $other = $this->makeUser('teacher');

        $this->expectException(PageNotFoundException::class);
        $this->signedInAs($other)->post("teacher/classes/{$class['id']}/code/regenerate");
    }

    public function testCodeGivesNoAccessToOtherClasses(): void
    {
        [, , , , $assignment] = $this->classroom();
        $outsider             = $this->makeUser('student');
        $otherClass           = $this->makeClass($this->makeUser('teacher'));

        $this->join($outsider, $otherClass['join_code']);

        // Joined a different class: the first class's assignment is still off limits.
        $this->expectException(PageNotFoundException::class);
        $this->signedInAs($outsider)->get("student/assignments/{$assignment['id']}");
    }

    public function testOnlyStudentsCanJoin(): void
    {
        $class   = $this->makeClass($this->makeUser('teacher'));
        $teacher = $this->makeUser('teacher');

        $this->signedInAs($teacher)->post('student/classes/join', ['code' => $class['join_code']])->assertStatus(403);
    }

    public function testRemovedStudentKeepsAccountAndWork(): void
    {
        [$teacher, $student, $class, , $assignment] = $this->classroom();
        $submission                                 = $this->submitEssay($assignment, $student);

        $this->signedInAs($teacher)->post("teacher/classes/{$class['id']}/students/{$student['id']}/remove");

        $this->assertFalse($this->enrolled($class, $student));
        $this->seeInDatabase('users', ['id' => $student['id']]);
        $this->seeInDatabase('submissions', ['id' => $submission['id']]);

        // The student can rejoin with the code; nothing is duplicated.
        $this->join($student, $class['join_code'])->assertSessionHas('success');
    }
}
