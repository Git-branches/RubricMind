<?php
/**
 * @var CodeIgniter\View\View       $this
 * @var array<string, mixed>        $class
 * @var list<array<string, mixed>>  $students
 * @var list<array<string, mixed>>  $assignments
 */
use App\Models\ClassModel;

$isArchived = $class['status'] === 'archived';
$subtitle   = implode(' · ', array_filter([$class['section'], $class['school_year']]));
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?><?= esc($class['name']) ?><?php $this->endSection() ?>

<?php $this->section('content') ?>
<p class="breadcrumb"><a href="<?= site_url('teacher/classes') ?>">Classes</a> <?= icon('chevron-right') ?></p>

<div class="page-header">
    <div>
        <h1>
            <?= esc($class['name']) ?>
            <?php if ($isArchived): ?><span class="tag tag--closed">Archived</span><?php endif ?>
        </h1>
        <div class="page-header__meta">
            <?php if ($subtitle !== ''): ?><span><?= icon('classes') ?> <?= esc($subtitle) ?></span><?php endif ?>
            <span><?= icon('users') ?> <?= count($students) ?> students</span>
            <span><?= icon('file-text') ?> <?= count($assignments) ?> assignments</span>
        </div>
    </div>
    <div class="button-row">
        <a href="<?= site_url("teacher/classes/{$class['id']}/edit") ?>" data-modal class="button"><?= icon('pencil') ?> Edit</a>
        <form method="post" action="<?= site_url("teacher/classes/{$class['id']}/archive") ?>"
              <?= $isArchived ? '' : 'data-confirm="Archive this class? Students can still view it, but no new submissions will be accepted."' ?>>
            <?= csrf_field() ?>
            <button type="submit" class="button"><?= icon('archive') ?> <?= $isArchived ? 'Restore' : 'Archive' ?></button>
        </form>
        <?php if (! $isArchived): ?>
            <a href="<?= site_url("teacher/classes/{$class['id']}/assignments/new") ?>" data-modal class="button button--primary"><?= icon('plus') ?> New assignment</a>
        <?php endif ?>
    </div>
</div>

<?php if (! empty($class['description'])): ?>
    <p class="prewrap muted"><?= esc($class['description']) ?></p>
<?php endif ?>

<?php $code = ClassModel::formatJoinCode($class['join_code']) ?>
<section class="card class-code" id="class-code">
    <div class="class-code__main">
        <span class="class-code__label"><?= icon('key') ?> Class code</span>
        <span class="class-code__value <?= $class['join_code_enabled'] && ! $isArchived ? '' : 'class-code__value--off' ?>"><?= esc($code) ?></span>
        <span class="muted small">
            <?php if ($isArchived): ?>
                Archived classes do not accept new students.
            <?php elseif ($class['join_code_enabled']): ?>
                Students sign in, open <strong>My classes</strong>, and enter this code to join.
            <?php else: ?>
                <span class="tag tag--closed">Off</span> No one can join with this code until you turn it on.
            <?php endif ?>
        </span>
    </div>
    <div class="button-row">
        <button type="button" class="button button--small" data-copy="<?= esc($code, 'attr') ?>"><?= icon('copy') ?> Copy</button>
        <form method="post" action="<?= site_url("teacher/classes/{$class['id']}/code/regenerate") ?>"
              data-confirm="Create a new class code? The current code stops working right away. Students already in the class stay enrolled."
              data-confirm-button="Create new code">
            <?= csrf_field() ?>
            <button type="submit" class="button button--small"><?= icon('refresh') ?> New code</button>
        </form>
        <form method="post" action="<?= site_url("teacher/classes/{$class['id']}/code/toggle") ?>">
            <?= csrf_field() ?>
            <button type="submit" class="button button--small"><?= icon($class['join_code_enabled'] ? 'lock' : 'check-circle') ?> <?= $class['join_code_enabled'] ? 'Turn off' : 'Turn on' ?></button>
        </form>
    </div>
</section>

<section class="card" id="assignments">
    <div class="section-header">
        <h2>Assignments</h2>
    </div>

    <?php if ($assignments === []): ?>
        <div class="empty-state">
            <span class="empty-state__icon"><?= icon('file-text') ?></span>
            <h2>No assignments yet</h2>
            <p>Create an essay assignment and attach a rubric. It stays a draft until you publish it.</p>
            <?php if (! $isArchived): ?>
                <a href="<?= site_url("teacher/classes/{$class['id']}/assignments/new") ?>" data-modal class="button button--primary"><?= icon('plus') ?> New assignment</a>
            <?php endif ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Title</th>
                    <th scope="col">Due</th>
                    <th scope="col">Status</th>
                    <th scope="col">Submissions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($assignments as $assignment): ?>
                    <tr>
                        <td><a href="<?= site_url("teacher/assignments/{$assignment['id']}") ?>"><?= esc($assignment['title']) ?></a></td>
                        <td class="nowrap"><?= esc(format_datetime($assignment['due_at'])) ?></td>
                        <td><span class="tag tag--<?= esc($assignment['status'], 'attr') ?>"><?= esc(assignment_status_label($assignment['status'])) ?></span></td>
                        <td><strong><?= esc($assignment['submission_count']) ?></strong> <span class="muted">of <?= count($students) ?></span></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>

<section class="card" id="students">
    <div class="section-header">
        <h2>Students <span class="muted">(<?= count($students) ?>)</span></h2>
        <?php if (! $isArchived): ?>
            <a href="<?= site_url("teacher/students/new?class_id={$class['id']}") ?>" class="button button--primary button--small" data-modal>
                <?= icon('user-plus') ?> Add students
            </a>
        <?php endif ?>
    </div>

    <?php if ($students === []): ?>
        <div class="empty-state">
            <span class="empty-state__icon"><?= icon('users') ?></span>
            <h2>No students yet</h2>
            <p>Share the class code above so students can join, or paste a list of names and emails to create accounts and enroll them in one step.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Student</th>
                    <th scope="col">Enrolled</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td>
                            <span class="person">
                                <?= avatar($student['name']) ?>
                                <span class="person__text">
                                    <span class="person__name">
                                        <?= esc($student['name']) ?>
                                        <?php if ($student['status'] !== 'active'): ?><span class="tag tag--closed">Inactive account</span><?php endif ?>
                                    </span>
                                    <span class="person__meta"><?= esc($student['email']) ?></span>
                                </span>
                            </span>
                        </td>
                        <td class="nowrap muted"><?= esc(format_datetime($student['enrolled_at'])) ?></td>
                        <td class="actions">
                            <form method="post" action="<?= site_url("teacher/classes/{$class['id']}/students/{$student['id']}/remove") ?>"
                                  class="inline-form" data-confirm="Remove <?= esc($student['name'], 'attr') ?> from this class? Their existing submissions are kept.">
                                <?= csrf_field() ?>
                                <button type="submit" class="link-button link-button--danger">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>

<?php if ($assignments === []): ?>
    <section class="danger-zone">
        <p><strong>Delete this class</strong><br>Only possible while the class has no assignments. Enrollments will be removed.</p>
        <form method="post" action="<?= site_url("teacher/classes/{$class['id']}/delete") ?>"
              data-confirm="Delete this class permanently? Enrollments will be removed.">
            <?= csrf_field() ?>
            <button type="submit" class="button button--danger"><?= icon('trash') ?> Delete class</button>
        </form>
    </section>
<?php endif ?>
<?php $this->endSection() ?>
