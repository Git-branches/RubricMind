<?php
/**
 * @var CodeIgniter\View\View       $this
 * @var array<string, mixed>        $rubric
 * @var list<array<string, mixed>>  $criteria
 * @var bool                        $isUsed
 */
$isLocked = $rubric['locked_at'] !== null;
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?><?= esc($rubric['title']) ?><?php $this->endSection() ?>

<?php $this->section('content') ?>
<p class="breadcrumb"><a href="<?= site_url('teacher/rubrics') ?>">Rubrics</a> <?= icon('chevron-right') ?></p>

<div class="page-header">
    <div>
        <h1>
            <?= esc($rubric['title']) ?>
            <?php if ($isLocked): ?><span class="tag tag--locked"><?= icon('lock') ?> Locked</span><?php endif ?>
        </h1>
        <div class="page-header__meta">
            <span><?= icon('rubrics') ?> <?= count($criteria) ?> criteria</span>
            <span><?= icon('check-circle') ?> <?= esc(App\Models\RubricModel::totalPoints($criteria)) ?> points total</span>
        </div>
    </div>
    <div class="button-row">
        <?php if (! $isLocked): ?>
            <a href="<?= site_url("teacher/rubrics/{$rubric['id']}/edit") ?>" class="button"><?= icon('pencil') ?> Edit</a>
        <?php endif ?>
        <form method="post" action="<?= site_url("teacher/rubrics/{$rubric['id']}/duplicate") ?>">
            <?= csrf_field() ?>
            <button type="submit" class="button"><?= icon('copy') ?> Duplicate</button>
        </form>
    </div>
</div>

<?php if ($isLocked): ?>
    <div class="alert alert--warning" role="status">
        <?= icon('lock', 'alert__icon') ?>
        <div>This rubric was used for grading on <?= esc(format_datetime($rubric['locked_at'])) ?>, so it is locked to protect existing grades.
            Duplicate it to make a changed version for future assignments.</div>
    </div>
<?php elseif ($isUsed): ?>
    <div class="alert alert--success" role="status">
        <?= icon('check-circle', 'alert__icon') ?>
        <div>This rubric is used by an assignment. You can still edit it until grading starts.</div>
    </div>
<?php endif ?>

<?php if (! empty($rubric['description'])): ?>
    <p class="prewrap muted"><?= esc($rubric['description']) ?></p>
<?php endif ?>

<section class="card card--medium">
    <?= $this->include('partials/rubric_table') ?>
</section>

<?php if (! $isLocked && ! $isUsed): ?>
    <section class="danger-zone card--medium">
        <p><strong>Delete this rubric</strong><br>Only possible while no assignment uses it.</p>
        <form method="post" action="<?= site_url("teacher/rubrics/{$rubric['id']}/delete") ?>" data-confirm="Delete this rubric permanently?">
            <?= csrf_field() ?>
            <button type="submit" class="button button--danger"><?= icon('trash') ?> Delete rubric</button>
        </form>
    </section>
<?php endif ?>
<?php $this->endSection() ?>
