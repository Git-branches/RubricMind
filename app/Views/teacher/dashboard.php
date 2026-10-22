<?php
/**
 * @var CodeIgniter\View\View       $this
 * @var list<array<string, mixed>>  $classes
 * @var list<array<string, mixed>>  $recent
 * @var int                         $awaitingReview
 * @var int                         $openAssignments
 * @var int                         $studentCount  Distinct students across active classes.
 */
$user      = service('auth')->user();
$firstName = explode(' ', trim($user['name']))[0];
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?>Teacher dashboard<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="page-header">
    <div>
        <h1>Good day, <?= esc($firstName) ?></h1>
        <p>Here's what is happening in your classes.</p>
    </div>
    <div class="button-row">
        <a href="<?= site_url('teacher/rubrics/new') ?>" class="button"><?= icon('rubrics') ?> New rubric</a>
        <a href="<?= site_url('teacher/classes/new') ?>" data-modal class="button button--primary"><?= icon('plus') ?> New class</a>
    </div>
</div>

<div class="stats">
    <a class="stat stat--indigo card" href="<?= site_url('teacher/classes') ?>">
        <span class="stat__icon"><?= icon('classes') ?></span>
        <span class="stat__body">
            <span class="stat__value"><?= count($classes) ?></span>
            <span class="stat__label">Active classes</span>
        </span>
    </a>
    <div class="stat stat--sky card">
        <span class="stat__icon"><?= icon('users') ?></span>
        <span class="stat__body">
            <span class="stat__value"><?= esc($studentCount) ?></span>
            <span class="stat__label">Enrolled students</span>
        </span>
    </div>
    <div class="stat stat--emerald card">
        <span class="stat__icon"><?= icon('file-text') ?></span>
        <span class="stat__body">
            <span class="stat__value"><?= esc($openAssignments) ?></span>
            <span class="stat__label">Open assignments</span>
        </span>
    </div>
    <div class="stat stat--amber card">
        <span class="stat__icon"><?= icon('inbox') ?></span>
        <span class="stat__body">
            <span class="stat__value"><?= esc($awaitingReview) ?></span>
            <span class="stat__label">Waiting to be graded</span>
        </span>
    </div>
</div>

<section class="card">
    <div class="section-header">
        <div>
            <h2>Recent submissions</h2>
            <p class="muted small">The newest essays across all your classes.</p>
        </div>
    </div>
    <?php if ($recent === []): ?>
        <div class="empty-state">
            <span class="empty-state__icon"><?= icon('inbox') ?></span>
            <h2>No submissions yet</h2>
            <p>Essays will appear here as soon as students submit them.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Student</th>
                    <th scope="col">Assignment</th>
                    <th scope="col">Submitted</th>
                    <th scope="col">Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $row): ?>
                    <tr>
                        <td>
                            <span class="person">
                                <?= avatar($row['student_name'], 'sm') ?>
                                <a class="person__name" href="<?= site_url("teacher/submissions/{$row['id']}") ?>"><?= esc($row['student_name']) ?></a>
                            </span>
                        </td>
                        <td>
                            <a href="<?= site_url("teacher/assignments/{$row['assignment_id']}") ?>"><?= esc($row['assignment_title']) ?></a>
                            <div class="muted small"><?= esc($row['class_name']) ?></div>
                        </td>
                        <td class="nowrap">
                            <?= esc(format_datetime($row['submitted_at'])) ?>
                            <?php if ($row['is_late']): ?><span class="tag tag--late">Late</span><?php endif ?>
                        </td>
                        <td><span class="tag tag--info"><?= esc(submission_status_label($row['status'])) ?></span></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
<?php $this->endSection() ?>
