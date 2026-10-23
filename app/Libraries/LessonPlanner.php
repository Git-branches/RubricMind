<?php

namespace App\Libraries;

use App\Libraries\Ai\AiException;
use App\Models\LessonPlanModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/**
 * Creates draft lesson plans from an assignment's finalized results (FR-11).
 *
 * A plan is created when the teacher asks for one, and automatically once the
 * last submitted essay of an assignment is finalized. Plans are always drafts:
 * the teacher reviews and edits them before using them in class.
 */
final class LessonPlanner
{
    // Statuses of submissions that still need grading.
    private const UNGRADED = ['submitted', 'ai_evaluated', 'teacher_reviewed'];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * @return int The new lesson plan's ID.
     *
     * @throws RuntimeException When no grade is finalized yet.
     * @throws AiException      When the AI provider fails.
     */
    public function generate(array $assignment, int $teacherId, bool $automatic = false): int
    {
        $insights = (new ClassInsights())->forAssignment($assignment);

        if ($insights['gradedCount'] === 0) {
            throw new RuntimeException('Finalize at least one grade before generating a lesson plan.');
        }

        $focus    = ClassInsights::lessonFocus($insights);
        $analyzer = service('essayAnalyzer');
        $plan     = $analyzer->lessonPlan($focus);

        $planId = model(LessonPlanModel::class)->insert([
            'assignment_id' => $assignment['id'],
            'teacher_id'    => $teacherId,
            'title'         => mb_substr($plan['title'], 0, 200),
            'content'       => LessonPlanModel::toText($plan),
            'focus'         => json_encode($focus, JSON_UNESCAPED_UNICODE),
            'provider'      => $analyzer->provider(),
        ]);

        audit_log('lesson_plan.generated', 'lesson_plan', $planId, ['assignment_id' => $assignment['id'], 'automatic' => $automatic]);

        return $planId;
    }

    /**
     * After a grade is finalized: when no submitted essay of the assignment is
     * left to grade, creates a draft lesson plan, unless AI is off or the
     * assignment already has one. Never throws: finalizing must not fail
     * because a lesson plan could not be made.
     *
     * @return int|null The new plan's ID, or null when none was created.
     */
    public function generateWhenGradingIsDone(array $assignment, int $teacherId): ?int
    {
        if (! service('systemSettings')->aiEnabled()) {
            return null;
        }

        $submissions = $this->db->table('submissions')->where('assignment_id', $assignment['id']);
        $ungraded    = (clone $submissions)->whereIn('status', self::UNGRADED)->countAllResults();
        $hasPlan     = $this->db->table('lesson_plans')->where('assignment_id', $assignment['id'])->countAllResults() > 0;

        if ($ungraded > 0 || $hasPlan || $submissions->countAllResults() === 0) {
            return null;
        }

        try {
            return $this->generate($assignment, $teacherId, true);
        } catch (Throwable $e) {
            log_message('warning', "Automatic lesson plan for assignment {$assignment['id']} failed: {$e->getMessage()}");

            return null;
        }
    }
}
