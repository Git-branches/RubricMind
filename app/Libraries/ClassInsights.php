<?php

namespace App\Libraries;

use App\Models\RubricModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Classroom Insight Dashboard data (FR-11).
 *
 * Only finalized or released grades are counted. Drafts, unreviewed AI
 * suggestions, and grades still being edited are excluded and reported as
 * "not yet finalized" so the teacher knows what the numbers cover.
 */
final class ClassInsights
{
    public const FINAL_STATUSES = ['finalized', 'released'];

    // Criterion averages at or above STRENGTH are strengths; below NEEDS_WORK need attention.
    public const STRENGTH   = 80;
    public const NEEDS_WORK = 65;

    // Score distribution bins: [label, lowest percent included].
    private const BINS = [
        ['Below 60%', 0],
        ['60–69%', 60],
        ['70–79%', 70],
        ['80–89%', 80],
        ['90–100%', 90],
    ];

    private BaseConnection $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    public function forAssignment(array $assignment): array
    {
        $criteria = model(RubricModel::class)->criteriaWithLevels($assignment['rubric_id']);

        $graded = $this->db->table('submissions')
            ->select('submissions.id AS submission_id, users.id AS student_id, users.name AS student_name')
            ->select('evaluations.id AS evaluation_id, evaluations.total_score, evaluations.max_score')
            ->join('evaluations', 'evaluations.submission_id = submissions.id')
            ->join('users', 'users.id = submissions.student_id')
            ->where('submissions.assignment_id', $assignment['id'])
            ->whereIn('submissions.status', self::FINAL_STATUSES)
            ->orderBy('users.name')
            ->get()
            ->getResultArray();

        $submittedCount = $this->db->table('submissions')->where('assignment_id', $assignment['id'])->countAllResults();
        $enrolledCount  = $this->db->table('class_students')->where('class_id', $assignment['class_id'])->countAllResults();

        $percents = array_map(static fn (array $g) => self::percent((int) $g['total_score'], (int) $g['max_score']), $graded);

        return [
            'criteria'       => $this->criteriaStats($criteria, array_column($graded, 'evaluation_id')),
            'gradedCount'    => count($graded),
            'pendingCount'   => $submittedCount - count($graded),
            'submittedCount' => $submittedCount,
            'enrolledCount'  => $enrolledCount,
            'average'        => $percents === [] ? null : (int) round(array_sum($percents) / count($percents)),
            'highest'        => $percents === [] ? null : max($percents),
            'lowest'         => $percents === [] ? null : min($percents),
            'distribution'   => self::distribution($percents),
            'mechanics'      => $this->mechanics(array_column($graded, 'submission_id')),
            'needSupport'    => array_values(array_filter(array_map(static fn (array $g) => [
                'submission_id' => (int) $g['submission_id'],
                'name'          => $g['student_name'],
                'percent'       => self::percent((int) $g['total_score'], (int) $g['max_score']),
            ], $graded), static fn (array $s) => $s['percent'] < self::NEEDS_WORK)),
        ];
    }

    /**
     * Average percent and graded count for each of the teacher's assignments, by class.
     *
     * @return list<array{class: array, assignments: list<array>}>
     */
    public function overview(int $teacherId): array
    {
        $assignments = $this->db->table('assignments')
            ->select('assignments.id, assignments.title, assignments.status, assignments.due_at, classes.id AS class_id, classes.name AS class_name')
            ->join('classes', 'classes.id = assignments.class_id')
            ->where('classes.teacher_id', $teacherId)
            ->where('classes.status', 'active')
            ->where('assignments.status !=', 'draft')
            ->orderBy('classes.name')
            ->orderBy('assignments.due_at', 'DESC')
            ->get()
            ->getResultArray();

        $scores = $assignments === [] ? [] : $this->db->table('submissions')
            ->select('submissions.assignment_id, evaluations.total_score, evaluations.max_score')
            ->join('evaluations', 'evaluations.submission_id = submissions.id')
            ->whereIn('submissions.assignment_id', array_column($assignments, 'id'))
            ->whereIn('submissions.status', self::FINAL_STATUSES)
            ->get()
            ->getResultArray();

        $byAssignment = [];

        foreach ($scores as $row) {
            $byAssignment[$row['assignment_id']][] = self::percent((int) $row['total_score'], (int) $row['max_score']);
        }

        $classes = [];

        foreach ($assignments as $assignment) {
            $percents = $byAssignment[$assignment['id']] ?? [];

            $classes[$assignment['class_id']]['class'] = ['id' => (int) $assignment['class_id'], 'name' => $assignment['class_name']];
            $classes[$assignment['class_id']]['assignments'][] = $assignment + [
                'graded'  => count($percents),
                'average' => $percents === [] ? null : (int) round(array_sum($percents) / count($percents)),
            ];
        }

        return array_values($classes);
    }

    /**
     * Learning gaps to build a lesson plan from. Contains no student information.
     */
    public static function lessonFocus(array $insights): array
    {
        $scored = array_filter($insights['criteria'], static fn (array $c) => $c['average'] !== null);
        usort($scored, static fn (array $a, array $b) => $a['average'] <=> $b['average']);

        return [
            'graded_count' => $insights['gradedCount'],
            'class_average'=> $insights['average'],
            'criteria'     => array_map(static fn (array $c) => [
                'title'           => $c['title'],
                'description'     => $c['description'],
                'average_percent' => $c['average'],
            ], array_values($scored)),
            'mechanics'    => array_map(static fn (array $m) => [
                'pattern'  => $m['pattern'],
                'students' => $m['students'],
            ], array_slice($insights['mechanics'], 0, 3)),
        ];
    }

    private function criteriaStats(array $criteria, array $evaluationIds): array
    {
        $scores = $evaluationIds === [] ? [] : $this->db->table('criterion_scores')
            ->select('criterion_id, level_id, points')
            ->whereIn('evaluation_id', $evaluationIds)
            ->where('points IS NOT NULL')
            ->get()
            ->getResultArray();

        $byCriterion = [];

        foreach ($scores as $row) {
            $byCriterion[(int) $row['criterion_id']][] = $row;
        }

        return array_map(static function (array $criterion) use ($byCriterion) {
            $rows   = $byCriterion[(int) $criterion['id']] ?? [];
            $points = array_map(static fn (array $r) => (int) $r['points'], $rows);
            $avg    = $points === [] ? null : array_sum($points) / count($points);

            // Levels from highest to lowest, with how many students reached each.
            $levels = $criterion['levels'];
            usort($levels, static fn (array $a, array $b) => $b['points'] <=> $a['points']);
            $levels = array_map(static fn (array $level) => [
                'label'  => $level['label'],
                'points' => $level['points'],
                'count'  => count(array_filter($rows, static fn (array $r) => (int) $r['level_id'] === (int) $level['id'])),
            ], $levels);

            return [
                'id'           => (int) $criterion['id'],
                'title'        => $criterion['title'],
                'description'  => $criterion['description'],
                'max_points'   => $criterion['max_points'],
                'scored'       => count($points),
                'avg_points'   => $avg === null ? null : round($avg, 1),
                'average'      => $avg === null ? null : self::percent($avg, $criterion['max_points']),
                'levels'       => $levels,
            ];
        }, $criteria);
    }

    /**
     * Mechanics patterns from the latest successful AI run of each graded essay.
     * These come from AI suggestions, so the view labels them as such.
     */
    private function mechanics(array $submissionIds): array
    {
        if ($submissionIds === []) {
            return [];
        }

        $runs = $this->db->table('ai_runs')
            ->select('submission_id')
            ->selectMax('id', 'run_id')
            ->whereIn('submission_id', $submissionIds)
            ->where('status', 'done')
            ->groupBy('submission_id')
            ->get()
            ->getResultArray();

        if ($runs === []) {
            return [];
        }

        $patterns = $this->db->table('grammar_patterns')
            ->select('pattern, occurrences')
            ->whereIn('ai_run_id', array_column($runs, 'run_id'))
            ->get()
            ->getResultArray();

        $grouped = [];

        foreach ($patterns as $row) {
            $grouped[$row['pattern']]['pattern']     = $row['pattern'];
            $grouped[$row['pattern']]['students']    = ($grouped[$row['pattern']]['students'] ?? 0) + 1;
            $grouped[$row['pattern']]['occurrences'] = ($grouped[$row['pattern']]['occurrences'] ?? 0) + (int) $row['occurrences'];
        }

        usort($grouped, static fn (array $a, array $b) => [$b['students'], $b['occurrences']] <=> [$a['students'], $a['occurrences']]);

        return array_map(static fn (array $g) => $g + ['analyzed' => count($runs)], $grouped);
    }

    private static function distribution(array $percents): array
    {
        $bins = [];

        foreach (self::BINS as $i => [$label, $from]) {
            $to     = self::BINS[$i + 1][1] ?? 101;
            $bins[] = [
                'label' => $label,
                'count' => count(array_filter($percents, static fn (int $p) => $p >= $from && $p < $to)),
            ];
        }

        return $bins;
    }

    private static function percent(float|int $score, float|int $max): int
    {
        return $max > 0 ? (int) round($score / $max * 100) : 0;
    }
}
