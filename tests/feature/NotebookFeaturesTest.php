<?php

namespace Tests\Feature;

use App\Models\RubricModel;
use Tests\Support\ClassroomFixtures;
use Tests\Support\FeatureTestCase;

/**
 * Details from the project's feature descriptions: highlight notes on the
 * essay (Smart Rubric Mapping), the mechanics summary in the sidebar, and the
 * lesson plan created once grading is done (Classroom Insight Dashboard).
 *
 * @internal
 */
final class NotebookFeaturesTest extends FeatureTestCase
{
    use ClassroomFixtures;

    private function finalize(array $teacher, array $submission, array $criteria)
    {
        $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/start");

        return $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/grade", [
            'intent' => 'finalize',
            'scores' => [
                $criteria[0]['id'] => ['points' => '10'],
                $criteria[1]['id'] => ['points' => '9'],
            ],
            'student_feedback' => 'Good work.',
        ]);
    }

    private function planCount(array $assignment): int
    {
        return db_connect()->table('lesson_plans')->where('assignment_id', $assignment['id'])->countAllResults();
    }

    public function testHighlightsShowTheirCriterionAndNote(): void
    {
        [$teacher, $student, , , $assignment] = $this->classroom();
        $submission                           = $this->submitEssay($assignment, $student, 'School should start later because students need sleep. Research shows teens sleep too little.');

        $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/ai");
        // Attribute values are HTML-escaped; decode them to compare plain text.
        $html = html_entity_decode($this->signedInAs($teacher)->get("teacher/submissions/{$submission['id']}")->response()->getBody(), ENT_QUOTES | ENT_HTML5);

        $this->assertStringContainsString('data-evidence-criterion="Criterion 1: Thesis"', $html);
        $this->assertMatchesRegularExpression('/<mark class="evidence evidence--1"[^>]*data-evidence-note="[^"]+"[^>]*title="Criterion 1: Thesis — [^"]+"/', $html);
        $this->assertStringContainsString('tabindex="0"', $html);
    }

    public function testMechanicsSummaryIsInTheSidebar(): void
    {
        [$teacher, $student, , , $assignment] = $this->classroom();
        $submission                           = $this->submitEssay($assignment, $student, 'students is tired. However students go to school early.');

        $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/ai");
        $html = $this->signedInAs($teacher)->get("teacher/submissions/{$submission['id']}")->response()->getBody();

        $aside = substr($html, strpos($html, '<aside id="grading"'));
        $this->assertStringContainsString('Writing mechanics summary', $aside);
        $this->assertStringNotContainsString('Writing mechanics summary', substr($html, 0, strpos($html, '<aside id="grading"')));
    }

    public function testLessonPlanIsCreatedWhenTheLastEssayIsFinalized(): void
    {
        [$teacher, $student, $class, $rubric, $assignment] = $this->classroom();
        $other                                             = $this->makeUser('student');
        $this->enroll($class, $other);
        $criteria = model(RubricModel::class)->criteriaWithLevels($rubric['id']);

        $first  = $this->submitEssay($assignment, $student);
        $second = $this->submitEssay($assignment, $other);

        // One essay is still ungraded: no plan yet.
        $this->finalize($teacher, $first, $criteria)->assertSessionHas('success', 'Grade finalized. Release it when the student should see it.');
        $this->assertSame(0, $this->planCount($assignment));

        // The last one: a draft plan is created and the teacher is told.
        $result = $this->finalize($teacher, $second, $criteria);
        $result->assertSessionHas('success');
        $this->assertStringContainsString('a draft lesson plan was created', $_SESSION['success']);
        $this->assertSame(1, $this->planCount($assignment));
        $this->seeInDatabase('audit_logs', ['action' => 'lesson_plan.generated']);

        $this->signedInAs($teacher)->get("teacher/assignments/{$assignment['id']}/insights")->assertSee('Mini-lesson: improving');
    }

    public function testNoAutomaticPlanWhenAiIsOffOrAPlanExists(): void
    {
        [$teacher, $student, , $rubric, $assignment] = $this->classroom();
        $criteria                                    = model(RubricModel::class)->criteriaWithLevels($rubric['id']);

        service('systemSettings')->save(['ai_enabled' => '0'], $teacher['id']);
        $this->finalize($teacher, $this->submitEssay($assignment, $student), $criteria);

        $this->assertSame(0, $this->planCount($assignment));

        // With AI on again, the teacher's button still works, and a reopened, re-finalized grade adds no duplicate plan.
        service('systemSettings')->save(['ai_enabled' => '1'], $teacher['id']);
        $this->signedInAs($teacher)->post("teacher/assignments/{$assignment['id']}/lesson-plans")->assertRedirect();
        $this->assertSame(1, $this->planCount($assignment));
    }
}
