<?php
/**
 * @var CodeIgniter\View\View       $this
 * @var array<string, mixed>        $assignment
 * @var array<string, mixed>        $class
 * @var array<string, mixed>        $rubric
 * @var list<array<string, mixed>>  $criteria
 * @var list<array<string, mixed>>  $roster
 */
$status         = $assignment['status'];
$submittedCount = count(array_filter($roster, static fn (array $row) => $row['id'] !== null));
$enrolledCount  = count(array_filter($roster, static fn (array $row) => empty($row['unenrolled'])));
$lateCount      = count(array_filter($roster, static fn (array $row) => ! empty($row['is_late'])));
$finalizedCount = count(array_filter($roster, static fn (array $row) => $row['status'] === 'finalized'));
$percent        = $enrolledCount > 0 ? (int) round(min($submittedCount, $enrolledCount) / $enrolledCount * 100) : 0;

// Status buttons available for each assignment status: action => [label, icon, confirm message].
$actions = [
    'draft'     => ['publish' => ['Publish', 'send', 'Publish this assignment? Students in the class will see it and can submit.']],
    'published' => [
        'unpublish' => ['Move to draft', 'pencil', 'Hide this assignment from students?'],
        'close'     => ['Close submissions', 'lock', 'Close this assignment? Students will no longer be able to submit or edit.'],
    ],
    'closed'    => ['reopen' => ['Reopen', 'send', null]],
];
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?><?= esc($assignment['title']) ?><?php $this->endSection() ?>

<?php $this->section('content') ?>
<p class="breadcrumb">
    <a href="<?= site_url('teacher/classes') ?>">Classes</a> <?= icon('chevron-right') ?>
    <a href="<?= site_url("teacher/classes/{$class['id']}") ?>"><?= esc($class['name']) ?></a> <?= icon('chevron-right') ?>
</p>

<div class="page-header">
    <div>
        <h1>
            <?= esc($assignment['title']) ?>
            <span class="tag tag--<?= esc($status, 'attr') ?>"><?= esc(assignment_status_label($status)) ?></span>
        </h1>
        <div class="page-header__meta">
            <span><?= icon('calendar') ?> Due <?= esc(format_datetime($assignment['due_at'])) ?></span>
            <span><?= icon('rubrics') ?> <?= esc($rubric['title']) ?></span>
        </div>
    </div>
    <div class="button-row">
        <a href="<?= site_url("teacher/assignments/{$assignment['id']}/insights") ?>" class="button"><?= icon('bar-chart') ?> Insights</a>
        <a href="<?= site_url("teacher/assignments/{$assignment['id']}/edit") ?>" data-modal class="button"><?= icon('pencil') ?> Edit</a>
        <?php foreach ($actions[$status] as $action => [$label, $iconName, $confirm]): ?>
            <form method="post" action="<?= site_url("teacher/assignments/{$assignment['id']}/status") ?>"
                  <?= $confirm !== null ? 'data-confirm="' . esc($confirm, 'attr') . '"' : '' ?>>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $action ?>">
                <button type="submit" class="button <?= in_array($action, ['publish', 'reopen'], true) ? 'button--primary' : '' ?>">
                    <?= icon($iconName) ?> <?= esc($label) ?>
                </button>
            </form>
        <?php endforeach ?>
    </div>
</div>

<?php if ($status === 'draft'): ?>
    <div class="alert alert--warning" role="status">
        <?= icon('alert-triangle', 'alert__icon') ?>
        <div>This assignment is a draft. Students cannot see it until you publish it.</div>
    </div>
<?php endif ?>

<div class="stats">
    <div class="stat stat--indigo card">
        <span class="stat__icon"><?= icon('inbox') ?></span>
        <span class="stat__body">
            <span class="stat__value"><?= $submittedCount ?><span class="muted" style="font-size: 1rem"> / <?= $enrolledCount ?></span></span>
            <span class="stat__label">Submitted (<?= $percent ?>%)</span>
        </span>
    </div>
    <div class="stat stat--amber card">
        <span class="stat__icon"><?= icon('users') ?></span>
        <span class="stat__body">
            <span class="stat__value"><?= max(0, $enrolledCount - $submittedCount) ?></span>
            <span class="stat__label">Not yet submitted</span>
        </span>
    </div>
    <div class="stat stat--rose card">
        <span class="stat__icon"><?= icon('clock') ?></span>
        <span class="stat__body">
            <span class="stat__value"><?= $lateCount ?></span>
            <span class="stat__label">Submitted late</span>
        </span>
    </div>
</div>

<section class="card" id="submissions">
    <div class="section-header">
        <h2>Submissions (<?= $submittedCount ?> of <?= $enrolledCount ?>)</h2>
        <?php if ($finalizedCount > 0): ?>
            <form method="post" action="<?= site_url("teacher/assignments/{$assignment['id']}/release") ?>"
                  data-confirm="Release <?= $finalizedCount ?> finalized grade(s)? Those students will see their scores and feedback.">
                <?= csrf_field() ?>
                <button type="submit" class="button button--primary button--small"><?= icon('send') ?> Release <?= $finalizedCount ?> finalized grade(s)</button>
            </form>
        <?php endif ?>
    </div>

    <?php if ($roster === []): ?>
        <div class="empty-state">
            <span class="empty-state__icon"><?= icon('users') ?></span>
            <h2>No students enrolled</h2>
            <p>Enroll students in this class so they can see and submit this assignment.</p>
            <a href="<?= site_url("teacher/classes/{$class['id']}#students") ?>" class="button button--primary"><?= icon('user-plus') ?> Enroll students</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Student</th>
                    <th scope="col">Status</th>
                    <th scope="col">Submitted</th>
                    <th scope="col">Words</th>
                    <th scope="col">Score</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($roster as $row): ?>
                    <tr>
                        <td>
                            <span class="person">
                                <?= avatar($row['student_name']) ?>
                                <span class="person__text">
                                    <span class="person__name"><?= esc($row['student_name']) ?></span>
                                    <span class="person__meta"><?= esc($row['student_email']) ?></span>
                                </span>
                            </span>
                            <?php if (! empty($row['unenrolled'])): ?><span class="tag tag--closed">No longer enrolled</span><?php endif ?>
                        </td>
                        <td>
                            <?php if ($row['id'] === null): ?>
                                <span class="tag tag--closed">Not submitted</span>
                            <?php else: ?>
                                <span class="tag tag--info"><?= esc(submission_status_label($row['status'])) ?></span>
                            <?php endif ?>
                            <?php if ($row['is_late']): ?><span class="tag tag--late">Late</span><?php endif ?>
                        </td>
                        <td class="nowrap"><?= $row['submitted_at'] !== null ? esc(format_datetime($row['submitted_at'])) : '<span class="muted">—</span>' ?></td>
                        <td><?= $row['word_count'] !== null ? esc($row['word_count']) : '<span class="muted">—</span>' ?></td>
                        <td class="nowrap">
                            <?php if ($row['total_score'] !== null && in_array($row['status'], ['teacher_reviewed', 'finalized', 'released'], true)): ?>
                                <strong><?= esc($row['total_score']) ?></strong><span class="muted"> / <?= esc($row['max_score']) ?></span>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif ?>
                        </td>
                        <td class="actions">
                            <?php if ($row['id'] !== null): ?>
                                <a href="<?= site_url("teacher/submissions/{$row['id']}") ?>" class="button button--small">
                                    <?= in_array($row['status'], ['submitted', 'ai_evaluated', 'teacher_reviewed'], true) ? 'Grade' : 'View' ?>
                                </a>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>

<section class="card split-card">
    <div class="split-card__part">
        <h2>Instructions</h2>
        <p class="prewrap"><?= esc($assignment['instructions']) ?></p>
    </div>

    <div class="split-card__part">
        <div class="section-header">
            <h2>Rubric</h2>
            <a href="<?= site_url("teacher/rubrics/{$rubric['id']}") ?>" class="button button--small">Open rubric <?= icon('arrow-right') ?></a>
        </div>
        <?= $this->include('partials/rubric_summary') ?>
    </div>
</section>

<?php if ($submittedCount === 0): ?>
    <section class="danger-zone">
        <p><strong>Delete this assignment</strong><br>Only possible while no student has submitted.</p>
        <form method="post" action="<?= site_url("teacher/assignments/{$assignment['id']}/delete") ?>"
              data-confirm="Delete this assignment permanently?">
            <?= csrf_field() ?>
            <button type="submit" class="button button--danger"><?= icon('trash') ?> Delete assignment</button>
        </form>
    </section>
<?php endif ?>
<?php $this->endSection() ?>
