<?php

namespace Tests\Feature;

use App\Libraries\GradingWorkflow;
use App\Models\AssignmentModel;
use App\Models\EvaluationModel;
use App\Models\LessonPlanModel;
use App\Models\RubricModel;
use App\Models\SubmissionModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Tests\Support\ClassroomFixtures;
use Tests\Support\FeatureTestCase;
use Tests\Unit\StyleTrackerTest;

/**
 * Phase 5: Style Growth Tracker, Classroom Insight Dashboard, and lesson plans.
 *
 * @internal
 */
final class InsightsTest extends FeatureTestCase
{
    use ClassroomFixtures;

    /**
     * Grades a submission directly: Thesis (levels 10/5) and Evidence (0–15).
     */
    private function grade(array $teacher, array $submission, int $thesis, int $evidence, bool $finalize = true): void
    {
        $assignment = model(AssignmentModel::class)->find($submission['assignment_id']);
        $criteria   = model(RubricModel::class)->criteriaWithLevels($assignment['rubric_id']);
        $workflow   = new GradingWorkflow();
        $evaluation = $workflow->start($submission, $assignment, $teacher['id']);

        $parsed = GradingWorkflow::parseScores($criteria, [
            $criteria[0]['id'] => ['points' => (string) $thesis],
            $criteria[1]['id'] => ['points' => (string) $evidence],
        ]);
        $workflow->save($evaluation, model(SubmissionModel::class)->find($submission['id']), $parsed['scores'], null, null);

        if ($finalize) {
            $workflow->finalize($evaluation, model(SubmissionModel::class)->find($submission['id']));
        }
    }

    /**
     * A finalized earlier essay by the student, submitted $daysAgo days ago.
     */
    private function pastEssay(array $teacher, array $student, array $class, array $rubric, string $content, int $daysAgo): void
    {
        $assignment = $this->makeAssignment($class, $rubric, ['title' => "Past essay {$daysAgo}"]);
        $submission = $this->submitEssay($assignment, $student, $content);
        model(SubmissionModel::class)->update($submission['id'], ['submitted_at' => date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"))]);
        $this->grade($teacher, model(SubmissionModel::class)->find($submission['id']), 10, 10);
    }

    // Style Growth Tracker

    public function testNewStudentShowsInsufficientHistory(): void
    {
        [$teacher, $student, , , $assignment] = $this->classroom();
        $submission = $this->submitEssay($assignment, $student);

        $page = $this->signedInAs($teacher)->get("teacher/submissions/{$submission['id']}");

        $page->assertSee('Style Growth Tracker');
        $page->assertSee('Insufficient history');
    }

    public function testNotableStyleChangeIsFlaggedAsUncertainSignal(): void
    {
        [$teacher, $student, $class, $rubric, $assignment] = $this->classroom();
        $this->pastEssay($teacher, $student, $class, $rubric, StyleTrackerTest::simpleEssay(40), 20);
        $this->pastEssay($teacher, $student, $class, $rubric, StyleTrackerTest::simpleEssay(45), 10);
        $submission = $this->submitEssay($assignment, $student, StyleTrackerTest::formalEssay());

        $page = $this->signedInAs($teacher)->get("teacher/submissions/{$submission['id']}");

        $page->assertSee('Notable style change');
        $page->assertSee('uncertain signal');
        $page->assertSee('It never affects the grade');
    }

    public function testStyleReviewIsRecordedWithoutChangingTheGrade(): void
    {
        [$teacher, $student, $class, $rubric, $assignment] = $this->classroom();
        $this->pastEssay($teacher, $student, $class, $rubric, StyleTrackerTest::simpleEssay(40), 20);
        $this->pastEssay($teacher, $student, $class, $rubric, StyleTrackerTest::simpleEssay(45), 10);
        $submission = $this->submitEssay($assignment, $student, StyleTrackerTest::formalEssay());

        $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/style-review", ['review_note' => 'Talked with the student.'])
            ->assertSessionHas('success');

        $this->seeInDatabase('style_flags', ['submission_id' => $submission['id'], 'status' => 'notable_change', 'review_note' => 'Talked with the student.']);
        $this->seeInDatabase('audit_logs', ['action' => 'style.reviewed', 'entity_id' => $submission['id']]);
        $this->assertSame('submitted', model(SubmissionModel::class)->find($submission['id'])['status']);
        $this->assertNull(model(EvaluationModel::class)->findBySubmission($submission['id']));
    }

    public function testStudentNeverSeesStyleTracker(): void
    {
        [$teacher, $student, , , $assignment] = $this->classroom();
        $submission = $this->submitEssay($assignment, $student);
        $this->grade($teacher, $submission, 10, 12);
        $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/release");

        $page = $this->signedInAs($student)->get("student/assignments/{$assignment['id']}");

        $page->assertSee('Your score');
        $page->assertDontSee('Style Growth Tracker');
        $page->assertDontSee('Insufficient history');
    }

    // Classroom Insight Dashboard

    public function testInsightsCountOnlyFinalizedGrades(): void
    {
        [$teacher, $student, $class, , $assignment] = $this->classroom();
        $second = $this->makeUser('student', ['name' => 'Second Student']);
        $this->enroll($class, $second);

        $this->grade($teacher, $this->submitEssay($assignment, $student), 10, 15);          // 25/25 finalized
        $this->grade($teacher, $this->submitEssay($assignment, $second), 5, 0, false);      // 5/25 not finalized

        $page = $this->signedInAs($teacher)->get("teacher/assignments/{$assignment['id']}/insights");

        $page->assertOK();
        $page->assertSee('Based on <strong>1 finalized grade(s)</strong>');
        $page->assertSee('1 submitted essay(s) are not finalized yet');
        $page->assertSee('100%');
        $page->assertDontSee('Second Student');
    }

    public function testInsightsShowLearningGapsAndStudentsNeedingSupport(): void
    {
        [$teacher, $student, , , $assignment] = $this->classroom();
        $this->grade($teacher, $this->submitEssay($assignment, $student), 10, 3); // Thesis 100%, Evidence 20%

        $page = $this->signedInAs($teacher)->get("teacher/assignments/{$assignment['id']}/insights");

        $page->assertSee('Class strengths');
        $page->assertSee('Needs more support');
        $page->assertSee('Students who may need support');
        $page->assertSee(esc($student['name']));
    }

    public function testInsightsWithNoFinalizedGradesShowEmptyState(): void
    {
        [$teacher, , , , $assignment] = $this->classroom();

        $page = $this->signedInAs($teacher)->get("teacher/assignments/{$assignment['id']}/insights");

        $page->assertSee('No finalized grades yet');
        $page->assertDontSee('Generate lesson plan');
    }

    public function testOtherTeacherCannotSeeInsights(): void
    {
        [, , , , $assignment] = $this->classroom();

        $this->expectException(PageNotFoundException::class);

        $this->signedInAs($this->makeUser('teacher'))->get("teacher/assignments/{$assignment['id']}/insights");
    }

    public function testInsightsOverviewRenders(): void
    {
        [$teacher, $student, , , $assignment] = $this->classroom();
        $this->grade($teacher, $this->submitEssay($assignment, $student), 10, 15);

        $page = $this->signedInAs($teacher)->get('teacher/insights');

        $page->assertOK();
        $page->assertSee(esc($assignment['title']));
        $page->assertSee('100%');
    }

    // Lesson plans

    public function testLessonPlanTargetsWeakestCriterionWithoutStudentData(): void
    {
        [$teacher, $student, , , $assignment] = $this->classroom();
        $this->grade($teacher, $this->submitEssay($assignment, $student), 10, 3);

        $this->signedInAs($teacher)->post("teacher/assignments/{$assignment['id']}/lesson-plans")->assertRedirect();

        $plan = model(LessonPlanModel::class)->where('assignment_id', $assignment['id'])->first();
        $this->assertNotNull($plan);
        $this->assertStringContainsString('Evidence', $plan['title']);
        $this->assertStringNotContainsString($student['name'], $plan['content'] . $plan['focus']);
        $this->assertStringNotContainsString($student['email'], $plan['content'] . $plan['focus']);
        $this->seeInDatabase('audit_logs', ['action' => 'lesson_plan.generated', 'entity_id' => $plan['id']]);
    }

    public function testLessonPlanNeedsFinalizedGrades(): void
    {
        [$teacher, , , , $assignment] = $this->classroom();

        $this->signedInAs($teacher)->post("teacher/assignments/{$assignment['id']}/lesson-plans")
            ->assertSessionHas('error', 'Finalize at least one grade before generating a lesson plan.');

        $this->dontSeeInDatabase('lesson_plans', ['assignment_id' => $assignment['id']]);
    }

    public function testTeacherEditsAndDeletesLessonPlan(): void
    {
        [$teacher, $student, , , $assignment] = $this->classroom();
        $this->grade($teacher, $this->submitEssay($assignment, $student), 10, 3);
        $this->signedInAs($teacher)->post("teacher/assignments/{$assignment['id']}/lesson-plans");
        $plan = model(LessonPlanModel::class)->where('assignment_id', $assignment['id'])->first();

        $this->signedInAs($teacher)->get("teacher/lesson-plans/{$plan['id']}")->assertSee('Save changes');

        $this->signedInAs($teacher)->post("teacher/lesson-plans/{$plan['id']}", ['title' => 'My evidence lesson', 'content' => 'Edited plan.'])
            ->assertRedirect();
        $this->seeInDatabase('lesson_plans', ['id' => $plan['id'], 'title' => 'My evidence lesson', 'content' => 'Edited plan.']);

        $this->signedInAs($teacher)->post("teacher/lesson-plans/{$plan['id']}/delete")->assertRedirect();
        $this->dontSeeInDatabase('lesson_plans', ['id' => $plan['id']]);
    }

    public function testOtherTeacherCannotOpenLessonPlan(): void
    {
        [$teacher, $student, , , $assignment] = $this->classroom();
        $this->grade($teacher, $this->submitEssay($assignment, $student), 10, 3);
        $this->signedInAs($teacher)->post("teacher/assignments/{$assignment['id']}/lesson-plans");
        $plan = model(LessonPlanModel::class)->where('assignment_id', $assignment['id'])->first();

        $this->expectException(PageNotFoundException::class);

        $this->signedInAs($this->makeUser('teacher'))->get("teacher/lesson-plans/{$plan['id']}");
    }

    public function testStudentSeesOwnProgress(): void
    {
        [$teacher, $student, , , $assignment] = $this->classroom();
        $submission = $this->submitEssay($assignment, $student);
        $this->grade($teacher, $submission, 10, 10);

        $this->signedInAs($student)->get('student')->assertDontSee('My progress');

        $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/release");
        $this->signedInAs($student)->get('student')->assertSee('My progress');
    }
}
