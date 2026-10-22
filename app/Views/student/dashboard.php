<?php
/**
 * @var CodeIgniter\View\View       $this
 * @var list<array<string, mixed>>  $assignments
 */
use App\Libraries\SubmissionRules;

$user      = service('auth')->user();
$firstName = explode(' ', trim($user['name']))[0];
$now       = date('Y-m-d H:i:s');

// To do: open and not yet submitted. Everything else is listed below it.
$todo  = [];
$other = [];

foreach ($assignments as $a) {
    $isOpen = $a['status'] === 'published' && $a['class_status'] === 'active';

    if ($a['submission_id'] === null && $isOpen) {
        $todo[] = $a;
    } else {
        $other[] = $a;
    }
}

$submittedCount = count(array_filter($assignments, static fn (array $a) => $a['submission_id'] !== null));
$gradedCount    = count(array_filter($assignments, static fn (array $a) => $a['submission_status'] === 'released'));
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?>My assignments<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="page-header">
    <div>
        <h1>Hello, <?= esc($firstName) ?></h1>
        <p>Your essay assignments and feedback in one place.</p>
    </div>
</div>

<div class="stats">
    <div class="stat stat--amber card">
        <span class="stat__icon"><?= icon('clock') ?></span>
        <span class="stat__body">
            <span class="stat__value"><?= count($todo) ?></span>
            <span class="stat__label">To do</span>
        </span>
    </div>
    <div class="stat stat--indigo card">
        <span class="stat__icon"><?= icon('send') ?></span>
        <span class="stat__body">
            <span class="stat__value"><?= $submittedCount ?></span>
            <span class="stat__label">Submitted</span>
        </span>
    </div>
    <div class="stat stat--emerald card">
        <span class="stat__icon"><?= icon('check-circle') ?></span>
        <span class="stat__body">
            <span class="stat__value"><?= $gradedCount ?></span>
            <span class="stat__label">Graded</span>
        </span>
    </div>
</div>

<?php if ($assignments === []): ?>
    <section class="card empty-state">
        <span class="empty-state__icon"><?= icon('book-open') ?></span>
        <h2>No assignments yet</h2>
        <p>When your teacher publishes an assignment for one of your classes, it will appear here.
            Not in a class yet? Join one with the code from your teacher.</p>
        <a href="<?= site_url('student/classes#join') ?>" class="button button--primary"><?= icon('key') ?> Join a Class</a>
    </section>
<?php else: ?>
    <section class="card">
        <div class="section-header">
            <div>
                <h2>To do</h2>
                <p class="muted small">Open assignments you haven't submitted yet.</p>
            </div>
        </div>
        <?php if ($todo === []): ?>
            <div class="empty-state" style="padding: 1.5rem">
                <span class="empty-state__icon" style="background: var(--success-bg); color: var(--success)"><?= icon('check-circle') ?></span>
                <p>You're all caught up.</p>
            </div>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($todo as $a): ?>
                    <?php $pastDue = $now > $a['due_at'] ?>
                    <li>
                        <div class="list__main">
                            <a class="list__title" href="<?= site_url("student/assignments/{$a['id']}") ?>"><?= esc($a['title']) ?></a>
                            <div class="list__meta">
                                <span><?= icon('classes') ?> <?= esc($a['class_name']) ?></span>
                                <span><?= icon('calendar') ?> Due <?= esc(format_datetime($a['due_at'])) ?></span>
                                <?php if ($pastDue): ?><span class="tag tag--late">Past due</span><?php endif ?>
                            </div>
                        </div>
                        <a href="<?= site_url("student/assignments/{$a['id']}") ?>" class="button button--primary button--small">Start essay <?= icon('arrow-right') ?></a>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </section>

    <?php
    // Released results, oldest first, so the student can see their progress over time.
    $results = array_values(array_filter($assignments, static fn (array $a) => $a['total_score'] !== null));
    usort($results, static fn (array $a, array $b) => strcmp($a['due_at'], $b['due_at']));
    ?>
    <?php if ($results !== []): ?>
        <section class="card">
            <div class="section-header">
                <div>
                    <h2><?= icon('bar-chart') ?> My progress</h2>
                    <p class="muted small">Your released scores, oldest to newest.</p>
                </div>
            </div>
            <ul class="insight-list">
                <?php foreach ($results as $r): ?>
                    <?php $percent = $r['max_score'] > 0 ? (int) round($r['total_score'] / $r['max_score'] * 100) : 0 ?>
                    <li>
                        <a href="<?= site_url("student/assignments/{$r['id']}") ?>" style="min-width: 40%"><?= esc($r['title']) ?></a>
                        <span class="meter" style="max-width: 50%">
                            <span class="meter__track"><span class="meter__fill" style="width: <?= $percent ?>%"></span></span>
                            <strong><?= $percent ?>%</strong>
                        </span>
                    </li>
                <?php endforeach ?>
            </ul>
        </section>
    <?php endif ?>

    <?php if ($other !== []): ?>
        <section class="card">
            <div class="section-header">
                <h2>Submitted and closed</h2>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th scope="col">Assignment</th>
                        <th scope="col">Due</th>
                        <th scope="col">Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($other as $a): ?>
                        <tr>
                            <td>
                                <a href="<?= site_url("student/assignments/{$a['id']}") ?>"><?= esc($a['title']) ?></a>
                                <div class="muted small"><?= esc($a['class_name']) ?></div>
                            </td>
                            <td class="nowrap"><?= esc(format_datetime($a['due_at'])) ?></td>
                            <td>
                                <?php $label = SubmissionRules::studentStatusLabel($a['submission_status']) ?>
                                <span class="tag <?= $a['submission_status'] === 'released' ? 'tag--published' : ($a['submission_id'] !== null ? 'tag--info' : 'tag--closed') ?>"><?= esc($label) ?></span>
                                <?php if ($a['total_score'] !== null): ?>
                                    <strong style="margin-left: 0.4rem"><?= esc($a['total_score']) ?></strong><span class="muted"> / <?= esc($a['max_score']) ?></span>
                                <?php endif ?>
                                <?php if ($a['is_late']): ?><span class="tag tag--late">Late</span><?php endif ?>
                                <?php if ($a['submission_id'] === null): ?><span class="tag tag--closed">Closed</span><?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif ?>
<?php endif ?>
<?php $this->endSection() ?>
