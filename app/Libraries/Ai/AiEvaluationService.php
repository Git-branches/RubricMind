<?php

namespace App\Libraries\Ai;

use App\Libraries\GradingWorkflow;
use App\Models\AiRunModel;
use App\Models\RubricModel;
use App\Models\SubmissionModel;
use CodeIgniter\Database\BaseConnection;
use Throwable;

/**
 * Requests AI suggestions for a submission and stores the results (FR-07, FR-08, FR-10).
 *
 * - Requesting AI starts grading, which locks the essay (FR-05).
 * - AI status is tracked on its own; a failure never changes the grading workflow,
 *   so the teacher can always grade manually or try again (FR-06).
 * - Suggestions are only stored. Nothing is applied to the grade until the teacher saves.
 */
final class AiEvaluationService
{
    private BaseConnection $db;
    private AiRunModel $runs;
    private SubmissionModel $submissions;

    public function __construct(private readonly EssayAnalyzer $analyzer)
    {
        $this->db          = db_connect();
        $this->runs        = model(AiRunModel::class);
        $this->submissions = model(SubmissionModel::class);
    }

    /**
     * @return array The finished run (status "done" or "failed").
     */
    public function request(array $submission, array $assignment, int $teacherId): array
    {
        (new GradingWorkflow())->start($submission, $assignment, $teacherId);

        $runId = $this->runs->insert([
            'submission_id'  => $submission['id'],
            'requested_by'   => $teacherId,
            'provider'       => $this->analyzer->provider(),
            'model'          => $this->analyzer->model(),
            'prompt_version' => $this->analyzer->promptVersion(),
            'status'         => 'pending',
        ]);
        $this->submissions->update($submission['id'], ['ai_status' => 'pending']);

        audit_log('ai.requested', 'submission', $submission['id'], [
            'ai_run_id' => $runId,
            'provider'  => $this->analyzer->provider(),
        ]);

        return $this->process($runId, $assignment);
    }

    private function process(int $runId, array $assignment): array
    {
        $run        = $this->runs->find($runId);
        $submission = $this->submissions->find($run['submission_id']);
        $criteria   = model(RubricModel::class)->criteriaWithLevels($assignment['rubric_id']);

        $this->runs->update($runId, ['status' => 'processing', 'started_at' => date('Y-m-d H:i:s')]);
        $this->submissions->update($submission['id'], ['ai_status' => 'processing']);

        try {
            // Only the essay text and the rubric leave this method; no student details.
            $raw = $this->analyzer->analyze([
                'essay'    => $submission['content'],
                'criteria' => array_map(static fn (array $c) => [
                    'id'          => (int) $c['id'],
                    'title'       => $c['title'],
                    'description' => $c['description'],
                    'max_points'  => $c['max_points'],
                    'levels'      => array_map(static fn (array $l) => [
                        'id'          => (int) $l['id'],
                        'label'       => $l['label'],
                        'description' => $l['description'],
                        'points'      => $l['points'],
                    ], $c['levels']),
                ], $criteria),
            ]);

            $result = AiResultNormalizer::normalize($raw, $criteria, $submission['content']);
            $this->store($runId, $raw, $result);
        } catch (Throwable $e) {
            $message = $e instanceof AiException ? $e->getMessage() : 'The AI service could not be reached or returned an unexpected result.';
            log_message('error', 'AI run {id} failed: {error}', ['id' => $runId, 'error' => $e->getMessage()]);

            $this->runs->update($runId, ['status' => 'failed', 'error' => $message, 'finished_at' => date('Y-m-d H:i:s')]);
            $this->submissions->update($submission['id'], ['ai_status' => 'failed']);
            audit_log('ai.failed', 'submission', $submission['id'], ['ai_run_id' => $runId, 'error' => $message]);

            return $this->runs->find($runId);
        }

        $update = ['ai_status' => 'done'];

        // Only an untouched submission moves to "AI suggestions ready"; a reviewed one stays reviewed.
        if ($submission['status'] === 'submitted') {
            $update['status'] = 'ai_evaluated';
        }

        $this->submissions->update($submission['id'], $update);

        $unverified = 0;

        foreach ($result['criteria'] as $suggestion) {
            foreach ($suggestion['evidence'] as $evidence) {
                $unverified += $evidence['verified'] ? 0 : 1;
            }
        }

        audit_log('ai.completed', 'submission', $submission['id'], [
            'ai_run_id'          => $runId,
            'suggested_criteria' => count($result['criteria']),
            'unverified_quotes'  => $unverified,
        ]);

        return $this->runs->find($runId);
    }

    private function store(int $runId, array $raw, array $result): void
    {
        $this->db->transException(true)->transStart();

        foreach ($result['criteria'] as $criterionId => $suggestion) {
            $this->db->table('ai_criterion_suggestions')->insert([
                'ai_run_id'    => $runId,
                'criterion_id' => $criterionId,
                'level_id'     => $suggestion['level_id'],
                'points'       => $suggestion['points'],
                'rationale'    => $suggestion['rationale'],
            ]);

            foreach ($suggestion['evidence'] as $evidence) {
                $this->db->table('evidence_highlights')->insert([
                    'ai_run_id'    => $runId,
                    'criterion_id' => $criterionId,
                    'quote'        => $evidence['quote'],
                    'note'         => $evidence['note'],
                    'start_offset' => $evidence['start'],
                    'length'       => $evidence['length'],
                    'verified'     => $evidence['verified'] ? 1 : 0,
                ]);
            }
        }

        foreach ($result['mechanics'] as $pattern) {
            $this->db->table('grammar_patterns')->insert([
                'ai_run_id'   => $runId,
                'pattern'     => $pattern['pattern'],
                'description' => $pattern['description'],
                'occurrences' => $pattern['occurrences'],
                'examples'    => json_encode($pattern['examples'], JSON_UNESCAPED_UNICODE),
                'suggestion'  => $pattern['suggestion'],
            ]);
        }

        $this->runs->update($runId, [
            'status'       => 'done',
            'feedback'     => json_encode($result['feedback'], JSON_UNESCAPED_UNICODE),
            'raw_response' => json_encode($raw, JSON_UNESCAPED_UNICODE),
            'finished_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->db->transComplete();
    }
}
