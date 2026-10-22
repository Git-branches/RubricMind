<?php

namespace Tests\Feature;

use App\Models\AuditLogModel;
use App\Models\EvaluationModel;
use App\Models\RubricModel;
use App\Models\SubmissionModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Tests\Support\ClassroomFixtures;
use Tests\Support\FeatureTestCase;

/**
 * Phase 3: manual grading, finalizing, releasing, and reopening grades.
 *
 * @internal
 */
final class GradingFlowTest extends FeatureTestCase
{
    use ClassroomFixtures;

    private const FEEDBACK = 'Your thesis is clear. Next time, add one more piece of evidence.';
    private const NOTES    = 'Private: check with the adviser about extra support.';

    /**
     * A submitted essay with grading already started.
     *
     * @return array{0: array, 1: array, 2: array, 3: list<array>} teacher, student, submission, criteria
     */
    private function startedGrading(): array
    {
        [$teacher, $student, , $rubric, $assignment] = $this->classroom();
        $submission = $this->submitEssay($assignment, $student);

        $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/start")->assertRedirect();

        return [$teacher, $student, model(SubmissionModel::class)->find($submission['id']), model(RubricModel::class)->criteriaWithLevels($rubric['id'])];
    }

    /**
     * Posts scores for both criteria of the fixture rubric (Thesis with levels, Evidence 0–15).
     */
    private function postScores(array $teacher, array $submission, array $criteria, ?int $thesis, ?int $evidence, string $intent = 'save')
    {
        return $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/grade", [
            'intent' => $intent,
            'scores' => [
                $criteria[0]['id'] => ['points' => $thesis === null ? '' : (string) $thesis, 'comment' => 'Strong claim.'],
                $criteria[1]['id'] => ['points' => $evidence === null ? '' : (string) $evidence],
            ],
            'student_feedback' => self::FEEDBACK,
            'teacher_notes'    => self::NOTES,
        ]);
    }

    private function statusOf(array $submission): string
    {
        return model(SubmissionModel::class)->find($submission['id'])['status'];
    }

    public function testViewingDoesNotStartGrading(): void
    {
        [$teacher, $student, , , $assignment] = $this->classroom();
        $submission = $this->submitEssay($assignment, $student);

        $result = $this->signedInAs($teacher)->get("teacher/submissions/{$submission['id']}");

        $result->assertOK();
        $result->assertSee('Start grading');
        $this->assertNull(model(EvaluationModel::class)->findBySubmission($submission['id']));
    }

    public function testStartGradingLocksEssayAndRubric(): void
    {
        [, $student, $submission, $criteria] = $this->startedGrading();

        $this->assertNotNull($submission['locked_at']);
        $this->assertNotNull(model(RubricModel::class)->find(model(EvaluationModel::class)->findBySubmission($submission['id'])['rubric_id'])['locked_at']);

        $evaluation = model(EvaluationModel::class)->findBySubmission($submission['id']);
        $this->assertSame(25, $evaluation['max_score']);
        $this->assertCount(count($criteria), model(EvaluationModel::class)->scores($evaluation['id']));
        $this->seeInDatabase('audit_logs', ['action' => 'grading.started', 'entity_id' => $submission['id']]);

        // The student can no longer edit.
        $result = $this->signedInAs($student)->post("student/assignments/{$submission['assignment_id']}/submission", ['content' => 'A new version of my essay.']);
        $result->assertSessionHas('error', 'Your submission can no longer be edited.');
    }

    public function testOtherTeacherCannotStartGrading(): void
    {
        [, $student, , , $assignment] = $this->classroom();
        $submission = $this->submitEssay($assignment, $student);

        $this->expectException(PageNotFoundException::class);

        $this->signedInAs($this->makeUser('teacher'))->post("teacher/submissions/{$submission['id']}/start");
    }

    public function testSavingScoresMarksReviewedAndTotals(): void
    {
        [$teacher, , $submission, $criteria] = $this->startedGrading();

        $this->postScores($teacher, $submission, $criteria, 10, 12)->assertRedirect();

        $evaluation = model(EvaluationModel::class)->findBySubmission($submission['id']);
        $scores     = model(EvaluationModel::class)->scores($evaluation['id']);
        $this->assertSame('teacher_reviewed', $this->statusOf($submission));
        $this->assertSame(22, $evaluation['total_score']);
        $this->assertSame(10, $scores[$criteria[0]['id']]['points']);
        $this->assertSame((int) $criteria[0]['levels'][0]['id'], $scores[$criteria[0]['id']]['level_id']);
        $this->assertSame('Strong claim.', $scores[$criteria[0]['id']]['comment']);
        $this->assertSame(self::NOTES, $evaluation['teacher_notes']);
    }

    public function testScoreMustMatchAPerformanceLevel(): void
    {
        [$teacher, , $submission, $criteria] = $this->startedGrading();

        $result = $this->postScores($teacher, $submission, $criteria, 7, 12);

        $result->assertOK();
        $result->assertSee('choose one of the performance levels');
        // The modal reopens by itself so the teacher sees the error.
        $this->assertStringContainsString('data-auto-open', (string) $result->getBody());
        $this->assertSame('submitted', $this->statusOf($submission));
    }

    public function testScoreCannotExceedCriterionMaximum(): void
    {
        [$teacher, , $submission, $criteria] = $this->startedGrading();

        $result = $this->postScores($teacher, $submission, $criteria, 10, 16);

        $result->assertSee('the score cannot be more than 15 points');
        $this->assertSame(0, model(EvaluationModel::class)->findBySubmission($submission['id'])['total_score']);
    }

    public function testFinalizeRequiresEveryCriterionScored(): void
    {
        [$teacher, , $submission, $criteria] = $this->startedGrading();

        $result = $this->postScores($teacher, $submission, $criteria, 10, null, 'finalize');

        $result->assertSessionHas('warning');
        $this->assertSame('teacher_reviewed', $this->statusOf($submission));
    }

    public function testFinalizedGradeIsHiddenFromStudentUntilReleased(): void
    {
        [$teacher, $student, $submission, $criteria] = $this->startedGrading();
        $this->postScores($teacher, $submission, $criteria, 5, 9, 'finalize')->assertRedirect();
        $this->assertSame('finalized', $this->statusOf($submission));

        $before = $this->signedInAs($student)->get("student/assignments/{$submission['assignment_id']}");
        $before->assertDontSee(self::FEEDBACK);
        $before->assertDontSee('Your score');
        $this->signedInAs($student)->get('student')->assertDontSee('14</strong>');

        $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/release")->assertRedirect();
        $this->assertSame('released', $this->statusOf($submission));

        $after = $this->signedInAs($student)->get("student/assignments/{$submission['assignment_id']}");
        $after->assertSee('Your score');
        $after->assertSee(self::FEEDBACK);
        $after->assertSee('Strong claim.');
        $after->assertDontSee(self::NOTES);
        $this->signedInAs($student)->get('student')->assertSee('14</strong>');
    }

    public function testFinalizedGradeCannotBeEdited(): void
    {
        [$teacher, , $submission, $criteria] = $this->startedGrading();
        $this->postScores($teacher, $submission, $criteria, 10, 15, 'finalize');

        $result = $this->postScores($teacher, $submission, $criteria, 5, 0);

        $result->assertSessionHas('error', 'This grade is finalized. Reopen it to make changes.');
        $this->assertSame(25, model(EvaluationModel::class)->findBySubmission($submission['id'])['total_score']);
    }

    public function testReopenNeedsReasonAndHidesReleasedGrade(): void
    {
        [$teacher, $student, $submission, $criteria] = $this->startedGrading();
        $this->postScores($teacher, $submission, $criteria, 10, 15, 'finalize');
        $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/release");

        $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/reopen", ['reason' => ''])
            ->assertSessionHas('error');
        $this->assertSame('released', $this->statusOf($submission));

        $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/reopen", ['reason' => 'Student asked for a re-check.'])
            ->assertRedirect();

        $this->assertSame('teacher_reviewed', $this->statusOf($submission));
        $this->signedInAs($student)->get("student/assignments/{$submission['assignment_id']}")->assertDontSee('Your score');

        $log = model(AuditLogModel::class)->where('action', 'grading.reopened')->first();
        $this->assertStringContainsString('Student asked for a re-check.', $log['details']);
        $this->assertStringContainsString('"previous_status":"released"', $log['details']);
    }

    public function testReleaseAllReleasesOnlyFinalizedGrades(): void
    {
        [$teacher, $student, $class, , $assignment] = $this->classroom();
        $second = $this->makeUser('student');
        $this->enroll($class, $second);

        $finalized = $this->submitEssay($assignment, $student);
        $pending   = $this->submitEssay($assignment, $second);
        $this->signedInAs($teacher)->post("teacher/submissions/{$finalized['id']}/start");
        $criteria = model(RubricModel::class)->criteriaWithLevels($assignment['rubric_id']);
        $this->postScores($teacher, $finalized, $criteria, 10, 10, 'finalize');

        $this->signedInAs($teacher)->post("teacher/assignments/{$assignment['id']}/release")->assertSessionHas('success', '1 grade(s) released to students.');

        $this->assertSame('released', $this->statusOf($finalized));
        $this->assertSame('submitted', $this->statusOf($pending));
    }

    public function testScoreChangesAreAudited(): void
    {
        [$teacher, , $submission, $criteria] = $this->startedGrading();
        $this->postScores($teacher, $submission, $criteria, 5, 10);
        $this->postScores($teacher, $submission, $criteria, 10, 10);

        $logs = model(AuditLogModel::class)->where('action', 'grading.saved')->orderBy('id')->findAll();
        $this->assertCount(2, $logs);
        $this->assertStringContainsString('"from":5,"to":10', $logs[1]['details']);
    }

    public function testGradingPageRendersInEveryState(): void
    {
        [$teacher, , $submission, $criteria] = $this->startedGrading();
        $url = "teacher/submissions/{$submission['id']}";

        $editable = $this->signedInAs($teacher)->get($url);
        $editable->assertSee('Open grading');
        $this->assertStringContainsString('<dialog class="modal" id="grading-modal"', (string) $editable->getBody());
        $editable->assertSee('Save and finalize');

        $this->postScores($teacher, $submission, $criteria, 10, 15, 'finalize');
        $finalized = $this->signedInAs($teacher)->get($url);
        $finalized->assertSee('Release to student');
        $finalized->assertSee('Change this grade');

        $this->signedInAs($teacher)->post("{$url}/release");
        $this->signedInAs($teacher)->get($url)->assertSee('Visible to student');
    }
}
