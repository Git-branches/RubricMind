<?php

namespace Tests\Feature;

use App\Models\ClassModel;
use App\Models\RubricModel;
use App\Models\SubmissionModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Tests\Support\ClassroomFixtures;
use Tests\Support\FeatureTestCase;

/**
 * Phase 2: classes, enrollment, rubrics, assignments, and essay submissions.
 *
 * @internal
 */
final class ClassroomFlowTest extends FeatureTestCase
{
    use ClassroomFixtures;

    // Classes and enrollment

    public function testTeacherCreatesClass(): void
    {
        $teacher = $this->makeUser('teacher');

        $this->signedInAs($teacher)->post('teacher/classes', ['name' => 'English 10', 'section' => 'Rizal'])->assertRedirect();

        $this->seeInDatabase('classes', ['teacher_id' => $teacher['id'], 'name' => 'English 10', 'section' => 'Rizal']);
    }

    public function testTeacherCannotOpenAnotherTeachersClass(): void
    {
        $owner = $this->makeUser('teacher');
        $other = $this->makeUser('teacher');
        $class = $this->makeClass($owner);

        $this->expectException(PageNotFoundException::class);

        $this->signedInAs($other)->get("teacher/classes/{$class['id']}");
    }

    public function testClassWithAssignmentsCannotBeDeleted(): void
    {
        [$teacher, , $class] = $this->classroom();

        $this->signedInAs($teacher)->post("teacher/classes/{$class['id']}/delete")->assertRedirect();

        $this->seeInDatabase('classes', ['id' => $class['id']]);
    }

    // Rubrics

    public function testTeacherCreatesRubricWithCriteriaAndLevels(): void
    {
        $teacher = $this->makeUser('teacher');

        $this->signedInAs($teacher)->post('teacher/rubrics', [
            'title'    => 'Persuasive Essay',
            'criteria' => [
                'c0' => [
                    'title'      => 'Thesis',
                    'max_points' => '10',
                    'levels'     => [
                        'l0' => ['label' => 'Excellent', 'points' => '10'],
                        'l1' => ['label' => 'Beginning', 'points' => '2'],
                    ],
                ],
                'cn5' => ['title' => 'Organization', 'max_points' => '5'],
            ],
        ])->assertRedirect();

        $rubric = model(RubricModel::class)->where('title', 'Persuasive Essay')->first();
        $this->assertNotNull($rubric);

        $criteria = model(RubricModel::class)->criteriaWithLevels($rubric['id']);
        $this->assertSame(['Thesis', 'Organization'], array_column($criteria, 'title'));
        $this->assertSame([10, 2], array_column($criteria[0]['levels'], 'points'));
        $this->assertSame(15, RubricModel::totalPoints($criteria));
    }

    public function testRubricLevelCannotExceedCriterionMaximum(): void
    {
        $teacher = $this->makeUser('teacher');

        $result = $this->signedInAs($teacher)->post('teacher/rubrics', [
            'title'    => 'Bad Rubric',
            'criteria' => [
                ['title' => 'Thesis', 'max_points' => '10', 'levels' => [['label' => 'Too high', 'points' => '12']]],
            ],
        ]);

        $result->assertOK();
        $result->assertSee('12 points is more than the criterion maximum of 10');
        $this->dontSeeInDatabase('rubrics', ['title' => 'Bad Rubric']);
    }

    public function testRubricLevelsNeedDifferentPoints(): void
    {
        $teacher = $this->makeUser('teacher');

        $result = $this->signedInAs($teacher)->post('teacher/rubrics', [
            'title'    => 'Duplicate Points',
            'criteria' => [
                ['title' => 'Thesis', 'max_points' => '10', 'levels' => [
                    ['label' => 'Good', 'points' => '5'],
                    ['label' => 'Also good', 'points' => '5'],
                ]],
            ],
        ]);

        $result->assertSee('another level already gives 5 points');
        $this->dontSeeInDatabase('rubrics', ['title' => 'Duplicate Points']);
    }

    public function testLockedRubricCannotBeEdited(): void
    {
        $teacher = $this->makeUser('teacher');
        $rubric  = $this->makeRubric($teacher);
        model(RubricModel::class)->update($rubric['id'], ['locked_at' => date('Y-m-d H:i:s')]);

        $this->signedInAs($teacher)->post("teacher/rubrics/{$rubric['id']}", [
            'title'    => 'Changed',
            'criteria' => [['title' => 'Only one', 'max_points' => '5']],
        ])->assertRedirect();

        $this->seeInDatabase('rubrics', ['id' => $rubric['id'], 'title' => 'Essay Rubric']);
        $this->assertCount(2, model(RubricModel::class)->criteriaWithLevels($rubric['id']));
    }

    public function testDuplicateCopiesCriteriaAndLevels(): void
    {
        $teacher = $this->makeUser('teacher');
        $rubric  = $this->makeRubric($teacher);

        $this->signedInAs($teacher)->post("teacher/rubrics/{$rubric['id']}/duplicate")->assertRedirect();

        $copy = model(RubricModel::class)->where('copied_from_id', $rubric['id'])->first();
        $this->assertSame('Copy of Essay Rubric', $copy['title']);
        $this->assertNull($copy['locked_at']);
        $this->assertEquals(
            model(RubricModel::class)->criteriaWithLevels($rubric['id'])[0]['levels'][0]['label'],
            model(RubricModel::class)->criteriaWithLevels($copy['id'])[0]['levels'][0]['label'],
        );
    }

    public function testRubricUsedByAssignmentCannotBeDeleted(): void
    {
        [$teacher, , , $rubric] = $this->classroom();

        $this->signedInAs($teacher)->post("teacher/rubrics/{$rubric['id']}/delete")->assertRedirect();

        $this->seeInDatabase('rubrics', ['id' => $rubric['id']]);
    }

    // Assignments

    public function testNewAssignmentStartsAsDraft(): void
    {
        $teacher = $this->makeUser('teacher');
        $class   = $this->makeClass($teacher);
        $rubric  = $this->makeRubric($teacher);

        $this->signedInAs($teacher)->post("teacher/classes/{$class['id']}/assignments", [
            'title'        => 'Essay 1',
            'instructions' => 'Write about your summer.',
            'due_at'       => '2030-06-30T23:59',
            'rubric_id'    => $rubric['id'],
        ])->assertRedirect();

        $this->seeInDatabase('assignments', ['title' => 'Essay 1', 'status' => 'draft', 'due_at' => '2030-06-30 23:59:00']);
    }

    public function testAssignmentCannotUseAnotherTeachersRubric(): void
    {
        $teacher = $this->makeUser('teacher');
        $class   = $this->makeClass($teacher);
        $foreign = $this->makeRubric($this->makeUser('teacher'));

        $result = $this->signedInAs($teacher)->post("teacher/classes/{$class['id']}/assignments", [
            'title'        => 'Essay 1',
            'instructions' => 'Write.',
            'due_at'       => '2030-06-30T23:59',
            'rubric_id'    => $foreign['id'],
        ]);

        $result->assertSee('Choose one of your rubrics.');
        $this->dontSeeInDatabase('assignments', ['title' => 'Essay 1']);
    }

    public function testStudentCannotSeeDraftAssignment(): void
    {
        [, $student, , , $assignment] = $this->classroom(['status' => 'draft']);

        $this->expectException(PageNotFoundException::class);

        $this->signedInAs($student)->get("student/assignments/{$assignment['id']}");
    }

    public function testStudentNotInClassCannotSeeAssignment(): void
    {
        [, , , , $assignment] = $this->classroom();
        $outsider = $this->makeUser('student');

        $this->expectException(PageNotFoundException::class);

        $this->signedInAs($outsider)->get("student/assignments/{$assignment['id']}");
    }

    public function testPublishedAssignmentCannotGoBackToDraftAfterSubmissions(): void
    {
        [$teacher, $student, , , $assignment] = $this->classroom();
        model(SubmissionModel::class)->saveEssay(null, $assignment['id'], $student['id'], self::ESSAY, false);

        $this->signedInAs($teacher)->post("teacher/assignments/{$assignment['id']}/status", ['action' => 'unpublish'])->assertRedirect();

        $this->seeInDatabase('assignments', ['id' => $assignment['id'], 'status' => 'published']);
    }

    // Submissions

    public function testStudentSubmitsAndEditsBeforeDueDate(): void
    {
        [, $student, , , $assignment] = $this->classroom();
        $url = "student/assignments/{$assignment['id']}/submission";

        $this->signedInAs($student)->post($url, ['content' => self::ESSAY])->assertRedirect();
        $this->signedInAs($student)->post($url, ['content' => self::ESSAY . ' It also improves mood.'])->assertRedirect();

        $submission = model(SubmissionModel::class)->findForStudent($assignment['id'], $student['id']);
        $this->assertSame('submitted', $submission['status']);
        $this->assertFalse($submission['is_late']);
        $this->assertStringEndsWith('It also improves mood.', $submission['content']);
        $this->assertSame(16, $submission['word_count']);
        $this->assertCount(2, model(SubmissionModel::class)->revisions($submission['id']));
        $this->seeNumRecords(1, 'submissions', ['assignment_id' => $assignment['id']]);
    }

    public function testSubmissionAfterDueDateIsLateAndLocked(): void
    {
        [, $student, , , $assignment] = $this->classroom(['due_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))]);
        $url = "student/assignments/{$assignment['id']}/submission";

        $this->signedInAs($student)->post($url, ['content' => self::ESSAY])->assertRedirect();
        $result = $this->signedInAs($student)->post($url, ['content' => 'An edited essay that should be rejected.']);

        $result->assertSessionHas('error', 'Your submission can no longer be edited.');
        $submission = model(SubmissionModel::class)->findForStudent($assignment['id'], $student['id']);
        $this->assertTrue($submission['is_late']);
        $this->assertSame(self::ESSAY, $submission['content']);
    }

    public function testClosedAssignmentRejectsSubmissions(): void
    {
        [, $student, , , $assignment] = $this->classroom(['status' => 'closed']);

        $result = $this->signedInAs($student)->post("student/assignments/{$assignment['id']}/submission", ['content' => self::ESSAY]);

        $result->assertSessionHas('error', 'This assignment is no longer accepting submissions.');
        $this->dontSeeInDatabase('submissions', ['assignment_id' => $assignment['id']]);
    }

    public function testArchivedClassRejectsSubmissions(): void
    {
        [, $student, $class, , $assignment] = $this->classroom();
        model(ClassModel::class)->update($class['id'], ['status' => 'archived']);

        $result = $this->signedInAs($student)->post("student/assignments/{$assignment['id']}/submission", ['content' => self::ESSAY]);

        $result->assertSessionHas('error');
        $this->dontSeeInDatabase('submissions', ['assignment_id' => $assignment['id']]);
    }

    public function testSubmissionLockedForGradingCannotBeEdited(): void
    {
        [, $student, , , $assignment] = $this->classroom();
        $submissions = model(SubmissionModel::class);
        $id          = $submissions->saveEssay(null, $assignment['id'], $student['id'], self::ESSAY, false);
        $submissions->update($id, ['locked_at' => date('Y-m-d H:i:s')]);

        $result = $this->signedInAs($student)->post("student/assignments/{$assignment['id']}/submission", ['content' => 'Changed after grading started.']);

        $result->assertSessionHas('error', 'Your submission can no longer be edited.');
        $this->assertSame(self::ESSAY, $submissions->find($id)['content']);
    }

    public function testEmptyEssayIsRejected(): void
    {
        [, $student, , , $assignment] = $this->classroom();

        $result = $this->signedInAs($student)->post("student/assignments/{$assignment['id']}/submission", ['content' => '   ']);

        $result->assertSee('Please fix the following');
        $this->dontSeeInDatabase('submissions', ['assignment_id' => $assignment['id']]);
    }

    public function testTeacherViewingSubmissionDoesNotLockIt(): void
    {
        [$teacher, $student, , , $assignment] = $this->classroom();
        $id = model(SubmissionModel::class)->saveEssay(null, $assignment['id'], $student['id'], self::ESSAY, false);

        $result = $this->signedInAs($teacher)->get("teacher/submissions/{$id}");

        $result->assertOK();
        $result->assertSee('students need more sleep');
        $this->assertNull(model(SubmissionModel::class)->find($id)['locked_at']);
    }

    public function testTeacherCannotViewAnotherTeachersSubmission(): void
    {
        [, $student, , , $assignment] = $this->classroom();
        $id = model(SubmissionModel::class)->saveEssay(null, $assignment['id'], $student['id'], self::ESSAY, false);

        $this->expectException(PageNotFoundException::class);

        $this->signedInAs($this->makeUser('teacher'))->get("teacher/submissions/{$id}");
    }

    public function testEveryPageRenders(): void
    {
        [$teacher, $student, $class, $rubric, $assignment] = $this->classroom();
        $submissionId = model(SubmissionModel::class)->saveEssay(null, $assignment['id'], $student['id'], self::ESSAY, false);

        $teacherPages = [
            'teacher',
            'teacher/classes',
            'teacher/classes?status=archived',
            'teacher/classes/new',
            "teacher/classes/{$class['id']}",
            "teacher/classes/{$class['id']}/edit",
            "teacher/classes/{$class['id']}/assignments/new",
            "teacher/assignments/{$assignment['id']}",
            "teacher/assignments/{$assignment['id']}/edit",
            "teacher/submissions/{$submissionId}",
            'teacher/rubrics',
            'teacher/rubrics/new',
            "teacher/rubrics/{$rubric['id']}",
            "teacher/rubrics/{$rubric['id']}/edit",
        ];

        foreach ($teacherPages as $page) {
            $this->signedInAs($teacher)->get($page)->assertOK();
        }

        foreach (['student', "student/assignments/{$assignment['id']}"] as $page) {
            $this->signedInAs($student)->get($page)->assertOK();
        }
    }

    public function testAssignmentPageListsEveryEnrolledStudent(): void
    {
        [$teacher, $student, $class, , $assignment] = $this->classroom();
        $second = $this->makeUser('student', ['name' => 'Zed Second']);
        $this->enroll($class, $second);
        model(SubmissionModel::class)->saveEssay(null, $assignment['id'], $student['id'], self::ESSAY, false);

        $result = $this->signedInAs($teacher)->get("teacher/assignments/{$assignment['id']}");

        $result->assertOK();
        $result->assertSee('Submissions (1 of 2)');
        $result->assertSee('Zed Second');
    }
}
