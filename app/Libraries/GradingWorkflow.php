<?php

namespace App\Libraries;

use App\Models\EvaluationModel;
use App\Models\RubricModel;
use App\Models\SubmissionModel;
use CodeIgniter\Database\BaseConnection;

/**
 * The teacher's grading workflow (FR-06, FR-12, FR-13):
 *
 *   submitted / ai_evaluated --start--> (evaluation created, essay and rubric locked)
 *   --save--> teacher_reviewed --finalize--> finalized --release--> released
 *
 * A finalized or released grade can be reopened with a reason, which is audited.
 * Every method assumes the caller has already checked that the teacher owns the submission.
 */
final class GradingWorkflow
{
    /**
     * Statuses in which scores and feedback can still be edited.
     */
    public const EDITABLE_STATUSES = ['submitted', 'ai_evaluated', 'teacher_reviewed'];

    public const MAX_COMMENT_LENGTH  = 2000;
    public const MAX_FEEDBACK_LENGTH = 10000;

    private BaseConnection $db;
    private EvaluationModel $evaluations;
    private SubmissionModel $submissions;

    public function __construct()
    {
        $this->db          = db_connect();
        $this->evaluations = model(EvaluationModel::class);
        $this->submissions = model(SubmissionModel::class);
    }

    public static function isEditable(array $submission): bool
    {
        return in_array($submission['status'], self::EDITABLE_STATUSES, true);
    }

    /**
     * Creates the evaluation and locks the essay and rubric. Safe to call twice.
     */
    public function start(array $submission, array $assignment, int $teacherId): array
    {
        $existing = $this->evaluations->findBySubmission($submission['id']);

        if ($existing !== null) {
            return $existing;
        }

        $rubrics  = model(RubricModel::class);
        $criteria = $rubrics->criteriaWithLevels($assignment['rubric_id']);
        $now      = date('Y-m-d H:i:s');

        $this->db->transException(true)->transStart();

        $evaluationId = $this->evaluations->insert([
            'submission_id' => $submission['id'],
            'rubric_id'     => $assignment['rubric_id'],
            'teacher_id'    => $teacherId,
            'total_score'   => 0,
            'max_score'     => RubricModel::totalPoints($criteria),
            'started_at'    => $now,
        ]);

        foreach ($criteria as $criterion) {
            $this->db->table('criterion_scores')->insert([
                'evaluation_id' => $evaluationId,
                'criterion_id'  => $criterion['id'],
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        if ($submission['locked_at'] === null) {
            $this->submissions->update($submission['id'], ['locked_at' => $now]);
        }

        // Lock only if not already locked, so the first grading date is kept.
        $rubrics->where('id', $assignment['rubric_id'])->where('locked_at', null)->set('locked_at', $now)->update();

        $this->db->transComplete();

        audit_log('grading.started', 'submission', $submission['id'], ['evaluation_id' => $evaluationId]);

        return $this->evaluations->find($evaluationId);
    }

    /**
     * Reads posted scores: scores[<criterion id>][points|comment].
     * An empty points value means "not scored yet".
     *
     * @return array{scores: array<int, array{points: int|null, level_id: int|null, comment: string|null}>, errors: list<string>}
     */
    public static function parseScores(array $criteria, mixed $posted): array
    {
        $posted = is_array($posted) ? $posted : [];
        $scores = [];
        $errors = [];

        foreach ($criteria as $i => $criterion) {
            $raw     = is_array($posted[$criterion['id']] ?? null) ? $posted[$criterion['id']] : [];
            $name    = 'Criterion ' . ($i + 1) . " ({$criterion['title']})";
            $text    = trim((string) ($raw['points'] ?? ''));
            $comment = trim((string) ($raw['comment'] ?? ''));
            $score   = ['points' => null, 'level_id' => null, 'comment' => $comment === '' ? null : $comment];

            if (mb_strlen($comment) > self::MAX_COMMENT_LENGTH) {
                $errors[] = "{$name}: the comment must be " . self::MAX_COMMENT_LENGTH . ' characters or fewer.';
            }

            if ($text !== '') {
                if (preg_match('/^\d{1,4}$/', $text) !== 1) {
                    $errors[] = "{$name}: the score must be a whole number.";
                } elseif ($criterion['levels'] !== []) {
                    // With performance levels, the score must be one of the level values.
                    $level = array_values(array_filter($criterion['levels'], static fn (array $l) => $l['points'] === (int) $text))[0] ?? null;

                    if ($level === null) {
                        $errors[] = "{$name}: choose one of the performance levels.";
                    } else {
                        $score['points']   = $level['points'];
                        $score['level_id'] = (int) $level['id'];
                    }
                } elseif ((int) $text > $criterion['max_points']) {
                    $errors[] = "{$name}: the score cannot be more than {$criterion['max_points']} points.";
                } else {
                    $score['points'] = (int) $text;
                }
            }

            $scores[$criterion['id']] = $score;
        }

        return ['scores' => $scores, 'errors' => $errors];
    }

    /**
     * Saves scores and feedback and marks the submission as reviewed.
     *
     * @param array<int, array{points: int|null, level_id: int|null, comment: string|null}> $scores From parseScores().
     */
    public function save(array $evaluation, array $submission, array $scores, ?string $studentFeedback, ?string $teacherNotes): void
    {
        $previous = $this->evaluations->scores($evaluation['id']);
        $now      = date('Y-m-d H:i:s');
        $changes  = [];

        $this->db->transException(true)->transStart();

        foreach ($scores as $criterionId => $score) {
            $before = $previous[$criterionId] ?? null;

            if ($before === null) {
                continue;
            }

            if ($before['points'] !== $score['points']) {
                $changes[$criterionId] = ['from' => $before['points'], 'to' => $score['points']];
            }

            $this->db->table('criterion_scores')->where('id', $before['id'])->update([
                'points'     => $score['points'],
                'level_id'   => $score['level_id'],
                'comment'    => $score['comment'],
                'updated_at' => $now,
            ]);
        }

        $this->evaluations->update($evaluation['id'], [
            'total_score'      => array_sum(array_map(static fn (array $s) => $s['points'] ?? 0, $scores)),
            'student_feedback' => $studentFeedback,
            'teacher_notes'    => $teacherNotes,
            'reviewed_at'      => $now,
        ]);

        $this->submissions->update($submission['id'], ['status' => 'teacher_reviewed']);

        $this->db->transComplete();

        audit_log('grading.saved', 'submission', $submission['id'], $changes === [] ? [] : ['score_changes' => $changes]);
    }

    /**
     * @return string|null An error message, or null when finalized.
     */
    public function finalize(array $evaluation, array $submission): ?string
    {
        $unscored = array_filter($this->evaluations->scores($evaluation['id']), static fn (array $s) => $s['points'] === null);

        if ($unscored !== []) {
            return 'Score every criterion before finalizing. ' . count($unscored) . ' criterion(s) still need a score.';
        }

        $this->db->transException(true)->transStart();
        $this->evaluations->update($evaluation['id'], ['finalized_at' => date('Y-m-d H:i:s')]);
        $this->submissions->update($submission['id'], ['status' => 'finalized']);
        $this->db->transComplete();

        $evaluation = $this->evaluations->find($evaluation['id']);
        audit_log('grading.finalized', 'submission', $submission['id'], [
            'total_score' => $evaluation['total_score'],
            'max_score'   => $evaluation['max_score'],
        ]);

        return null;
    }

    public function release(array $evaluation, array $submission): void
    {
        $this->db->transException(true)->transStart();
        $this->evaluations->update($evaluation['id'], ['released_at' => date('Y-m-d H:i:s')]);
        $this->submissions->update($submission['id'], ['status' => 'released']);
        $this->db->transComplete();

        audit_log('grading.released', 'submission', $submission['id']);
    }

    /**
     * Reopens a finalized or released grade for changes. A released grade is
     * hidden from the student again until it is re-released.
     */
    public function reopen(array $evaluation, array $submission, string $reason): void
    {
        $this->db->transException(true)->transStart();
        $this->evaluations->update($evaluation['id'], ['finalized_at' => null, 'released_at' => null]);
        $this->submissions->update($submission['id'], ['status' => 'teacher_reviewed']);
        $this->db->transComplete();

        audit_log('grading.reopened', 'submission', $submission['id'], [
            'reason'          => $reason,
            'previous_status' => $submission['status'],
            'total_score'     => $evaluation['total_score'],
            'scores'          => array_map(static fn (array $s) => $s['points'], $this->evaluations->scores($evaluation['id'])),
        ]);
    }
}
