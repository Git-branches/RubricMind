<?php
/**
 * A class the student joined: details and its assignments (drafts are never shown).
 *
 * @var CodeIgniter\View\View       $this
 * @var array<string, mixed>        $class
 * @var list<array<string, mixed>>  $assignments
 */
use App\Libraries\SubmissionRules;

$subtitle = implode(' · ', array_filter([$class['section'], $class['school_year']]));
$now      = date('Y-m-d H:i:s');
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?><?= esc($class['name']) ?><?php $this->endSection() ?>

<?php $this->section('content') ?>
<p class="breadcrumb"><a href="<?= site_url('student/classes') ?>">My classes</a> <?= icon('chevron-right') ?></p>

<div class="page-header">
    <div>
        <h1>
            <?= esc($class['name']) ?>
            <?php if ($class['status'] !== 'active'): ?><span class="tag tag--closed">Archived</span><?php endif ?>
        </h1>
        <div class="page-header__meta">
            <?php if ($subtitle !== ''): ?><span><?= icon('classes') ?> <?= esc($subtitle) ?></span><?php endif ?>
            <span><?= icon('users') ?> Teacher: <?= esc($class['teacher_name']) ?></span>
            <span><?= icon('calendar') ?> Joined <?= esc(date('M j, Y', strtotime($class['enrolled_at']))) ?></span>
        </div>
    </div>
</div>

<?php if (! empty($class['description'])): ?>
    <section class="card">
        <h2>About this class</h2>
        <p class="prewrap" style="margin-bottom: 0"><?= esc($class['description']) ?></p>
    </section>
<?php endif ?>

<?php if ($class['status'] !== 'active'): ?>
    <div class="alert alert--warning" role="status">
        <?= icon('archive', 'alert__icon') ?>
        <div>This class is archived. You can still read your essays and released grades, but new submissions are not accepted.</div>
    </div>
<?php endif ?>

<section class="card">
    <div class="section-header">
        <h2>Assignments <span class="muted">(<?= count($assignments) ?>)</span></h2>
    </div>

    <?php if ($assignments === []): ?>
        <p class="inline-empty"><?= icon('file-text') ?> No assignments yet. When your teacher publishes one, it will appear here.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Assignment</th>
                    <th scope="col">Due</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($assignments as $a): ?>
                    <?php
                    $isOpen  = $a['status'] === 'published' && $class['status'] === 'active';
                    $todo    = $a['submission_id'] === null && $isOpen;
                    $label   = SubmissionRules::studentStatusLabel($a['submission_status']);
                    $tag     = match (true) {
                        $a['submission_status'] === 'released' => 'tag--published',
                        $a['submission_id'] !== null           => 'tag--info',
                        default                                => 'tag--closed',
                    };
                    ?>
                    <tr>
                        <td><a href="<?= site_url("student/assignments/{$a['id']}") ?>"><?= esc($a['title']) ?></a></td>
                        <td class="nowrap">
                            <?= esc(format_datetime($a['due_at'])) ?>
                            <?php if ($todo && $now > $a['due_at']): ?><span class="tag tag--late">Past due</span><?php endif ?>
                        </td>
                        <td>
                            <span class="tag <?= $tag ?>"><?= esc($label) ?></span>
                            <?php if ($a['total_score'] !== null): ?>
                                <strong style="margin-left: 0.4rem"><?= esc($a['total_score']) ?></strong><span class="muted"> / <?= esc($a['max_score']) ?></span>
                            <?php endif ?>
                            <?php if ($a['is_late']): ?><span class="tag tag--late">Late</span><?php endif ?>
                            <?php if (! $isOpen && $a['submission_id'] === null): ?><span class="tag tag--closed">Closed</span><?php endif ?>
                        </td>
                        <td class="actions">
                            <a href="<?= site_url("student/assignments/{$a['id']}") ?>" class="button button--small <?= $todo ? 'button--primary' : '' ?>">
                                <?= $todo ? 'Start essay' : 'View' ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
<?php $this->endSection() ?>
