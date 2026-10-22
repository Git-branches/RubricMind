<?php
/**
 * @var CodeIgniter\View\View       $this
 * @var list<array<string, mixed>>  $classes
 * @var string                      $status  'active' or 'archived'
 */
use App\Models\ClassModel;
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?>Classes<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="page-header">
    <div>
        <h1>Classes</h1>
        <p>Your classes, their students, and their assignments.</p>
    </div>
    <a href="<?= site_url('teacher/classes/new') ?>" data-modal class="button button--primary"><?= icon('plus') ?> New class</a>
</div>

<nav class="tabs" aria-label="Class status">
    <a href="<?= site_url('teacher/classes') ?>" <?= $status === 'active' ? 'aria-current="page"' : '' ?>>Active</a>
    <a href="<?= site_url('teacher/classes?status=archived') ?>" <?= $status === 'archived' ? 'aria-current="page"' : '' ?>>Archived</a>
</nav>

<?php if ($classes === []): ?>
    <section class="card empty-state">
        <span class="empty-state__icon"><?= icon($status === 'archived' ? 'archive' : 'classes') ?></span>
        <?php if ($status === 'archived'): ?>
            <h2>No archived classes</h2>
            <p>Classes you archive will appear here.</p>
        <?php else: ?>
            <h2>Create your first class</h2>
            <p>Add a class, enroll your students, then create essay assignments for them.</p>
            <a href="<?= site_url('teacher/classes/new') ?>" data-modal class="button button--primary"><?= icon('plus') ?> New class</a>
        <?php endif ?>
    </section>
<?php else: ?>
    <div class="card-grid">
        <?php foreach ($classes as $class): ?>
            <?php $subtitle = implode(' · ', array_filter([$class['section'], $class['school_year']])) ?>
            <?php $codeOn = $class['join_code_enabled'] && $class['status'] === 'active' ?>
            <a class="card card--link class-card" href="<?= site_url("teacher/classes/{$class['id']}") ?>">
                <span class="class-card__top">
                    <span class="class-card__icon"><?= icon('classes') ?></span>
                    <span class="class-card__code <?= $codeOn ? '' : 'class-card__code--off' ?>"
                          title="<?= $codeOn ? 'Class code: students enter it in My classes to join' : 'Class code is turned off' ?>">
                        <?= icon('key') ?> <?= esc(ClassModel::formatJoinCode($class['join_code'])) ?>
                        <?php if (! $codeOn): ?><span class="visually-hidden">(turned off)</span><?php endif ?>
                    </span>
                </span>
                <h2><?= esc($class['name']) ?></h2>
                <p class="muted small"><?= $subtitle !== '' ? esc($subtitle) : 'No section set' ?></p>
                <div class="class-card__stats">
                    <span><?= icon('users') ?> <strong><?= esc($class['student_count']) ?></strong> students</span>
                    <span><?= icon('file-text') ?> <strong><?= esc($class['assignment_count']) ?></strong> assignments</span>
                </div>
            </a>
        <?php endforeach ?>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
