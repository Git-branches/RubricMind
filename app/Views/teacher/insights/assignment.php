<?php
/**
 * Classroom Insight Dashboard for one assignment (FR-11).
 *
 * @var CodeIgniter\View\View       $this
 * @var array<string, mixed>        $assignment
 * @var array<string, mixed>        $class
 * @var array<string, mixed>        $rubric
 * @var array<string, mixed>        $insights   ClassInsights::forAssignment()
 * @var list<array<string, mixed>>  $lessonPlans
 * @var bool                        $aiEnabled
 */
use App\Libraries\ClassInsights;

$criteria   = $insights['criteria'];
$scored     = array_values(array_filter($criteria, static fn (array $c) => $c['average'] !== null));
$strengths  = array_values(array_filter($scored, static fn (array $c) => $c['average'] >= ClassInsights::STRENGTH));
$needsWork  = array_values(array_filter($scored, static fn (array $c) => $c['average'] < ClassInsights::NEEDS_WORK));
usort($needsWork, static fn (array $a, array $b) => $a['average'] <=> $b['average']);
$maxBin     = max(1, ...array_column($insights['distribution'], 'count'));
$hasGrades  = $insights['gradedCount'] > 0;

// Ordinal blue ramp for performance levels, darkest = highest level (validated: monotone, single hue).
$ramp = ['#104281', '#1c5cab', '#2a78d6', '#5598e7', '#86b6ef'];
$levelColor = static function (int $index, int $count) use ($ramp): string {
    return $count <= 1 ? $ramp[2] : $ramp[(int) round($index * (count($ramp) - 1) / ($count - 1))];
};
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?>Insights · <?= esc($assignment['title']) ?><?php $this->endSection() ?>

<?php $this->section('content') ?>
<p class="breadcrumb">
    <a href="<?= site_url('teacher/insights') ?>">Insights</a> <?= icon('chevron-right') ?>
    <a href="<?= site_url("teacher/assignments/{$assignment['id']}") ?>"><?= esc($assignment['title']) ?></a> <?= icon('chevron-right') ?>
</p>

<div class="page-header">
    <div>
        <h1>Classroom insights</h1>
        <div class="page-header__meta">
            <span><?= icon('file-text') ?> <?= esc($assignment['title']) ?></span>
            <span><?= icon('classes') ?> <?= esc($class['name']) ?></span>
            <span><?= icon('rubrics') ?> <?= esc($rubric['title']) ?></span>
        </div>
    </div>
    <a href="<?= site_url("teacher/assignments/{$assignment['id']}") ?>" class="button"><?= icon('inbox') ?> Submissions</a>
</div>

<div class="alert <?= $insights['pendingCount'] > 0 ? 'alert--warning' : 'alert--success' ?>" role="note">
    <?= icon($insights['pendingCount'] > 0 ? 'alert-triangle' : 'check-circle', 'alert__icon') ?>
    <div>
        Based on <strong><?= esc($insights['gradedCount']) ?> finalized grade(s)</strong>.
        <?php if ($insights['pendingCount'] > 0): ?>
            <?= esc($insights['pendingCount']) ?> submitted essay(s) are not finalized yet and are not included.
        <?php endif ?>
        Draft scores and AI suggestions are never counted.
    </div>
</div>

<?php if (! $hasGrades): ?>
    <section class="card empty-state">
        <span class="empty-state__icon"><?= icon('bar-chart') ?></span>
        <h2>No finalized grades yet</h2>
        <p>Finalize grades for this assignment to see class strengths, learning gaps, and lesson-plan ideas.</p>
        <a href="<?= site_url("teacher/assignments/{$assignment['id']}") ?>" class="button button--primary">Go to submissions</a>
    </section>
<?php else: ?>
    <div class="stats">
        <div class="card stat-hero">
            <span class="stat-hero__label">Class average</span>
            <span class="stat-hero__value"><?= esc($insights['average']) ?>%</span>
            <span class="muted small"><?= esc($insights['gradedCount']) ?> of <?= esc($insights['enrolledCount']) ?> students graded</span>
        </div>
        <div class="stat stat--emerald card">
            <span class="stat__icon"><?= icon('check-circle') ?></span>
            <span class="stat__body"><span class="stat__value"><?= esc($insights['highest']) ?>%</span><span class="stat__label">Highest score</span></span>
        </div>
        <div class="stat stat--rose card">
            <span class="stat__icon"><?= icon('alert-circle') ?></span>
            <span class="stat__body"><span class="stat__value"><?= esc($insights['lowest']) ?>%</span><span class="stat__label">Lowest score</span></span>
        </div>
        <div class="stat stat--amber card">
            <span class="stat__icon"><?= icon('users') ?></span>
            <span class="stat__body"><span class="stat__value"><?= count($insights['needSupport']) ?></span><span class="stat__label">Below <?= ClassInsights::NEEDS_WORK ?>%</span></span>
        </div>
    </div>

    <div class="two-column two-column--balanced">
        <section class="card">
            <div class="section-header">
                <div>
                    <h2>Average score by criterion</h2>
                    <p class="muted small">Percent of the maximum points, across finalized grades.</p>
                </div>
            </div>
            <div class="hbar-chart" role="list">
                <?php foreach ($criteria as $c): ?>
                    <div class="hbar" role="listitem" tabindex="0"
                         data-tip="<?= esc($c['title'], 'attr') ?>: <?= $c['average'] === null ? 'no scores' : esc($c['avg_points'], 'attr') . ' of ' . esc($c['max_points'], 'attr') . ' points on average (' . $c['average'] . '%), ' . $c['scored'] . ' graded' ?>">
                        <span class="hbar__label"><?= esc($c['title']) ?></span>
                        <span class="hbar__track">
                            <?php if ($c['average'] !== null): ?><span class="hbar__fill" style="width: <?= (int) $c['average'] ?>%"></span><?php endif ?>
                        </span>
                        <span class="hbar__value"><?= $c['average'] === null ? '—' : $c['average'] . '%' ?></span>
                    </div>
                <?php endforeach ?>
                <div class="hbar hbar--axis" aria-hidden="true">
                    <span class="hbar__label"></span>
                    <span class="hbar__ticks"><span>0</span><span>25</span><span>50</span><span>75</span><span>100%</span></span>
                    <span class="hbar__value"></span>
                </div>
            </div>
            <details class="table-toggle">
                <summary>View as table</summary>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th scope="col">Criterion</th><th scope="col">Average points</th><th scope="col">Average %</th><th scope="col">Graded</th></tr></thead>
                        <tbody>
                        <?php foreach ($criteria as $c): ?>
                            <tr>
                                <td><?= esc($c['title']) ?></td>
                                <td><?= $c['avg_points'] === null ? '—' : esc($c['avg_points']) . ' / ' . esc($c['max_points']) ?></td>
                                <td><?= $c['average'] === null ? '—' : esc($c['average']) . '%' ?></td>
                                <td><?= esc($c['scored']) ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </details>
        </section>

        <section class="card">
            <h2>Strengths and learning gaps</h2>
            <h3 class="insight-heading insight-heading--good"><?= icon('check-circle') ?> Class strengths <span class="muted small">(<?= ClassInsights::STRENGTH ?>% or higher)</span></h3>
            <?php if ($strengths === []): ?>
                <p class="muted small">No criterion reached <?= ClassInsights::STRENGTH ?>% yet.</p>
            <?php else: ?>
                <ul class="insight-list">
                    <?php foreach ($strengths as $c): ?><li><span><?= esc($c['title']) ?></span><strong><?= esc($c['average']) ?>%</strong></li><?php endforeach ?>
                </ul>
            <?php endif ?>

            <h3 class="insight-heading insight-heading--warn"><?= icon('alert-triangle') ?> Needs more support <span class="muted small">(below <?= ClassInsights::NEEDS_WORK ?>%)</span></h3>
            <?php if ($needsWork === []): ?>
                <p class="muted small">No criterion is below <?= ClassInsights::NEEDS_WORK ?>%.</p>
            <?php else: ?>
                <ul class="insight-list">
                    <?php foreach ($needsWork as $c): ?><li><span><?= esc($c['title']) ?></span><strong><?= esc($c['average']) ?>%</strong></li><?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>
    </div>

    <section class="card">
        <div class="section-header">
            <div>
                <h2>Performance levels reached</h2>
                <p class="muted small">How many students reached each level of each criterion.</p>
            </div>
            <div class="legend" aria-hidden="true">
                <span>Highest level</span>
                <?php foreach ($ramp as $color): ?><span class="legend__swatch" style="background: <?= $color ?>"></span><?php endforeach ?>
                <span>Lowest level</span>
            </div>
        </div>
        <div class="stack-chart">
            <?php foreach ($criteria as $c): ?>
                <?php $total = array_sum(array_column($c['levels'], 'count')) ?>
                <div class="stack-row">
                    <span class="stack-row__label"><?= esc($c['title']) ?></span>
                    <?php if ($c['levels'] === []): ?>
                        <span class="muted small">No performance levels (scored by points).</span>
                    <?php elseif ($total === 0): ?>
                        <span class="muted small">No scores yet.</span>
                    <?php elseif (count($c['levels']) > count($ramp)): ?>
                        <span class="muted small">Too many levels to chart. See the table below.</span>
                    <?php else: ?>
                        <span class="stack-bar">
                            <?php foreach ($c['levels'] as $i => $level): ?>
                                <?php if ($level['count'] > 0): ?>
                                    <span class="stack-bar__segment" tabindex="0" style="flex-grow: <?= $level['count'] ?>; background: <?= $levelColor($i, count($c['levels'])) ?>"
                                          data-tip="<?= esc($c['title'] . ' · ' . $level['label'] . ' (' . $level['points'] . ' pts): ' . $level['count'] . ' student(s)', 'attr') ?>"
                                          aria-label="<?= esc($level['label'] . ': ' . $level['count'] . ' student(s)', 'attr') ?>"></span>
                                <?php endif ?>
                            <?php endforeach ?>
                        </span>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
        <details class="table-toggle">
            <summary>View as table</summary>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th scope="col">Criterion</th><th scope="col">Level</th><th scope="col">Points</th><th scope="col">Students</th></tr></thead>
                    <tbody>
                    <?php foreach ($criteria as $c): ?>
                        <?php foreach ($c['levels'] as $level): ?>
                            <tr><td><?= esc($c['title']) ?></td><td><?= esc($level['label']) ?></td><td><?= esc($level['points']) ?></td><td><?= esc($level['count']) ?></td></tr>
                        <?php endforeach ?>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </details>
    </section>

    <div class="two-column two-column--balanced">
        <section class="card">
            <h2>Score distribution</h2>
            <p class="muted small">Number of students in each score range.</p>
            <div class="column-chart" role="list">
                <?php foreach ($insights['distribution'] as $bin): ?>
                    <div class="column" role="listitem" tabindex="0" data-tip="<?= esc($bin['label'], 'attr') ?>: <?= $bin['count'] ?> student(s)">
                        <span class="column__value"><?= $bin['count'] ?></span>
                        <span class="column__bar-area">
                            <?php if ($bin['count'] > 0): ?><span class="column__bar" style="height: <?= round($bin['count'] / $maxBin * 100) ?>%"></span><?php endif ?>
                        </span>
                        <span class="column__label"><?= esc($bin['label']) ?></span>
                    </div>
                <?php endforeach ?>
            </div>
        </section>

        <section class="card">
            <div class="section-header">
                <div>
                    <h2>Common writing patterns</h2>
                    <p class="muted small">From AI mechanics summaries of graded essays. Suggestions only.</p>
                </div>
            </div>
            <?php if ($insights['mechanics'] === []): ?>
                <p class="muted small">No AI mechanics summaries yet. Run AI suggestions while grading to see class-wide patterns.</p>
            <?php else: ?>
                <ul class="insight-list">
                    <?php foreach (array_slice($insights['mechanics'], 0, 6) as $m): ?>
                        <li>
                            <span><?= esc($m['pattern']) ?><br><span class="muted small"><?= esc($m['occurrences']) ?> time(s) in total</span></span>
                            <strong><?= esc($m['students']) ?> of <?= esc($m['analyzed']) ?> students</strong>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>
    </div>

    <section class="card">
        <div class="section-header">
            <div>
                <h2>Students who may need support</h2>
                <p class="muted small">Finalized scores below <?= ClassInsights::NEEDS_WORK ?>%. Visible only to you.</p>
            </div>
        </div>
        <?php if ($insights['needSupport'] === []): ?>
            <p class="muted">Every graded student scored <?= ClassInsights::NEEDS_WORK ?>% or higher.</p>
        <?php else: ?>
            <ul class="insight-list">
                <?php foreach ($insights['needSupport'] as $s): ?>
                    <li>
                        <span class="person"><?= avatar($s['name'], 'sm') ?><a class="person__name" href="<?= site_url("teacher/submissions/{$s['submission_id']}") ?>"><?= esc($s['name']) ?></a></span>
                        <strong><?= esc($s['percent']) ?>%</strong>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </section>
<?php endif ?>

<section class="card" id="lesson-plans">
    <div class="section-header">
        <div>
            <h2><?= icon('book-open') ?> Lesson-plan drafts</h2>
            <p class="muted small">A draft targeted at the class's weakest criterion, created automatically once every submitted essay is finalized. Built only from class averages; no student names are used.</p>
        </div>
        <?php if ($hasGrades && $aiEnabled): ?>
            <form method="post" action="<?= site_url("teacher/assignments/{$assignment['id']}/lesson-plans") ?>">
                <?= csrf_field() ?>
                <button type="submit" class="button <?= $lessonPlans === [] ? 'button--primary' : '' ?>"><?= icon('sparkles') ?> <?= $lessonPlans === [] ? 'Generate lesson plan' : 'Generate a new version' ?></button>
            </form>
        <?php endif ?>
    </div>
    <?php if ($lessonPlans === []): ?>
        <p class="muted">No lesson plans yet. One is created automatically when the last submitted essay is finalized<?= $hasGrades && $aiEnabled ? ', or create one now' : '' ?>.</p>
    <?php else: ?>
        <ul class="list">
            <?php foreach ($lessonPlans as $plan): ?>
                <li>
                    <div class="list__main">
                        <a class="list__title" href="<?= site_url("teacher/lesson-plans/{$plan['id']}") ?>"><?= esc($plan['title']) ?></a>
                        <div class="list__meta"><span><?= icon('clock') ?> Updated <?= esc(format_datetime($plan['updated_at'])) ?></span></div>
                    </div>
                    <a href="<?= site_url("teacher/lesson-plans/{$plan['id']}") ?>" class="button button--small">Open</a>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</section>

<div class="chart-tooltip" role="tooltip" hidden></div>
<script src="<?= base_url('assets/js/charts.js') ?>" defer></script>
<?php $this->endSection() ?>
