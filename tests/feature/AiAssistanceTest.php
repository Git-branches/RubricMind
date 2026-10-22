<?php

namespace Tests\Feature;

use App\Libraries\Ai\AiException;
use App\Libraries\Ai\EssayAnalyzer;
use App\Models\AiRunModel;
use App\Models\EvaluationModel;
use App\Models\RubricModel;
use App\Models\SubmissionModel;
use Config\Services;
use Tests\Support\ClassroomFixtures;
use Tests\Support\FeatureTestCase;

/**
 * Phase 4: AI suggestions, evidence highlights, mechanics summary, and AI failures.
 *
 * @internal
 */
final class AiAssistanceTest extends FeatureTestCase
{
    use ClassroomFixtures;

    private const AI_ESSAY = "School should start later because students is always tired.\n\nResearch shows that teenagers need more sleep to learn well.";

    /**
     * An analyzer that records what it was sent and returns a fixed result or fails.
     */
    private function fakeAnalyzer(?array $result, ?string $error = null): EssayAnalyzer
    {
        return new class ($result, $error) implements EssayAnalyzer {
            public ?array $received = null;

            public function __construct(private ?array $result, private ?string $error)
            {
            }

            public function provider(): string
            {
                return 'fake';
            }

            public function model(): string
            {
                return 'fake-1';
            }

            public function promptVersion(): string
            {
                return 'test';
            }

            public function isSimulated(): bool
            {
                return true;
            }

            public function analyze(array $request): array
            {
                $this->received = $request;

                if ($this->error !== null) {
                    throw new AiException($this->error);
                }

                return $this->result;
            }

            public function lessonPlan(array $focus): array
            {
                throw new AiException('Not used in these tests.');
            }
        };
    }

    /**
     * @return array{0: array, 1: array, 2: array, 3: list<array>} teacher, student, submission, criteria
     */
    private function submitted(): array
    {
        [$teacher, $student, , $rubric, $assignment] = $this->classroom();

        return [$teacher, $student, $this->submitEssay($assignment, $student, self::AI_ESSAY), model(RubricModel::class)->criteriaWithLevels($rubric['id'])];
    }

    public function testDemoAiSuggestionsAreStoredAndShown(): void
    {
        [$teacher, , $submission, $criteria] = $this->submitted();

        $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/ai")
            ->assertSessionHas('success', 'AI suggestions are ready. Review them, then save your own scores.');

        $fresh = model(SubmissionModel::class)->find($submission['id']);
        $this->assertSame('ai_evaluated', $fresh['status']);
        $this->assertSame('done', $fresh['ai_status']);
        $this->assertNotNull($fresh['locked_at'], 'Requesting AI starts grading and locks the essay.');
        $this->assertNotNull(model(EvaluationModel::class)->findBySubmission($submission['id']));

        $run     = model(AiRunModel::class)->latestForSubmission($submission['id']);
        $results = model(AiRunModel::class)->results($run);
        $this->assertSame('demo', $run['provider']);
        $this->assertCount(count($criteria), $results['suggestions']);
        $this->assertContains('Subject-verb agreement', array_column($results['patterns'], 'pattern'));

        $page = $this->signedInAs($teacher)->get("teacher/submissions/{$submission['id']}");
        $page->assertSee('Demo AI (simulated)');
        $page->assertSee('AI suggests');
        $page->assertSee('Writing mechanics summary');
        $page->assertSee('class="evidence');
        $this->seeInDatabase('audit_logs', ['action' => 'ai.completed', 'entity_id' => $submission['id']]);
    }

    public function testAiSuggestionsAreNotAppliedUntilTheTeacherSaves(): void
    {
        [$teacher, , $submission] = $this->submitted();

        $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/ai");

        $evaluation = model(EvaluationModel::class)->findBySubmission($submission['id']);
        $this->assertSame(0, $evaluation['total_score']);

        foreach (model(EvaluationModel::class)->scores($evaluation['id']) as $score) {
            $this->assertNull($score['points']);
        }
    }

    public function testOnlyEssayTextAndRubricAreSentToTheAi(): void
    {
        [$teacher, $student, $submission, $criteria] = $this->submitted();
        $analyzer = $this->fakeAnalyzer(['criteria' => [], 'feedback' => [], 'mechanics' => []]);
        Services::injectMock('essayAnalyzer', $analyzer);

        $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/ai");

        $this->assertSame(['essay', 'criteria'], array_keys($analyzer->received));
        $this->assertSame(self::AI_ESSAY, $analyzer->received['essay']);
        $this->assertSame(array_map('intval', array_column($criteria, 'id')), array_column($analyzer->received['criteria'], 'id'));

        $sent = json_encode($analyzer->received);
        $this->assertStringNotContainsString($student['name'], $sent);
        $this->assertStringNotContainsString($student['email'], $sent);
    }

    public function testInventedQuotesAreUnverifiedAndNotHighlighted(): void
    {
        [$teacher, , $submission, $criteria] = $this->submitted();
        Services::injectMock('essayAnalyzer', $this->fakeAnalyzer([
            'criteria' => [[
                'criterion_id'     => $criteria[1]['id'],
                'suggested_points' => 12,
                'rationale'        => 'Uses research.',
                'evidence'         => [
                    ['quote' => 'Research shows that teenagers need more sleep to learn well.', 'note' => 'Real quote.'],
                    ['quote' => 'A Harvard study of 10,000 students proves this.', 'note' => 'Invented quote.'],
                ],
            ]],
        ]));

        $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/ai");
        $page = $this->signedInAs($teacher)->get("teacher/submissions/{$submission['id']}");

        $page->assertSee('Unverified: not found in the essay');

        // The essay appears on the page and in the grading modal; only the page copy has anchors.
        $body = (string) $page->getBody();
        $this->assertSame(1, substr_count($body, 'id="evidence-'));
        $this->assertSame(0, preg_match('/<mark[^>]*>[^<]*(?:<span[^>]*>[^<]*<\/span>)?[^<]*Harvard/', $body));
        $this->seeInDatabase('evidence_highlights', ['quote' => 'A Harvard study of 10,000 students proves this.', 'verified' => 0, 'start_offset' => null]);
    }

    public function testAiFailureKeepsSubmissionAndManualGradingWorks(): void
    {
        [$teacher, , $submission, $criteria] = $this->submitted();
        Services::injectMock('essayAnalyzer', $this->fakeAnalyzer(null, 'The AI service timed out.'));

        $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/ai")->assertSessionHas('error');

        $fresh = model(SubmissionModel::class)->find($submission['id']);
        $this->assertSame('failed', $fresh['ai_status']);
        $this->assertSame('submitted', $fresh['status'], 'An AI failure must not change the grading workflow.');
        $this->assertSame(self::AI_ESSAY, $fresh['content']);
        $this->assertSame('failed', model(AiRunModel::class)->latestForSubmission($submission['id'])['status']);

        $this->signedInAs($teacher)->get("teacher/submissions/{$submission['id']}")->assertSee('Try again');

        $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/grade", [
            'intent' => 'save',
            'scores' => [$criteria[0]['id'] => ['points' => '10'], $criteria[1]['id'] => ['points' => '8']],
        ])->assertSessionHas('success');
        $this->assertSame('teacher_reviewed', model(SubmissionModel::class)->find($submission['id'])['status']);
    }

    public function testUnexpectedErrorsShowAGenericMessage(): void
    {
        [$teacher, , $submission] = $this->submitted();
        Services::injectMock('essayAnalyzer', $this->fakeAnalyzer(['criteria' => null, 'feedback' => null, 'mechanics' => null]));

        // Malformed but non-throwing output is cleaned, not treated as a failure.
        $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/ai")->assertSessionHas('success');
        $this->assertSame('done', model(SubmissionModel::class)->find($submission['id'])['ai_status']);
    }

    public function testCannotRequestAiOnAFinalizedGrade(): void
    {
        [$teacher, , $submission, $criteria] = $this->submitted();
        $url = "teacher/submissions/{$submission['id']}";
        $this->signedInAs($teacher)->post("{$url}/start");
        $this->signedInAs($teacher)->post("{$url}/grade", [
            'intent' => 'finalize',
            'scores' => [$criteria[0]['id'] => ['points' => '10'], $criteria[1]['id'] => ['points' => '8']],
        ]);

        $this->signedInAs($teacher)->post("{$url}/ai")->assertSessionHas('error');
        $this->assertNull(model(AiRunModel::class)->latestForSubmission($submission['id']));
    }

    public function testStudentNeverSeesAiSuggestions(): void
    {
        [$teacher, $student, $submission, $criteria] = $this->submitted();
        $url = "teacher/submissions/{$submission['id']}";
        $this->signedInAs($teacher)->post("{$url}/ai");
        $this->signedInAs($teacher)->post("{$url}/grade", [
            'intent'           => 'finalize',
            'scores'           => [$criteria[0]['id'] => ['points' => '10'], $criteria[1]['id'] => ['points' => '8']],
            'student_feedback' => 'Teacher-written feedback.',
        ]);
        $this->signedInAs($teacher)->post("{$url}/release");

        $page = $this->signedInAs($student)->get("student/assignments/{$submission['assignment_id']}");

        $page->assertSee('Teacher-written feedback.');
        $page->assertDontSee('AI suggests');
        $page->assertDontSee('Writing mechanics summary');
        $page->assertDontSee('class="evidence');
    }
}
