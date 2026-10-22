<?php
/**
 * Create and edit form for assignments.
 *
 * @var CodeIgniter\View\View       $this
 * @var array<string, mixed>        $class
 * @var array<string, mixed>|null   $assignment  Null when creating.
 * @var list<array<string, mixed>>  $rubrics
 * @var array<string, string>       $old
 * @var array<string, string>       $errors
 */
$isNew = $assignment === null;

$values = $old !== [] ? $old : [
    'title'        => $assignment['title'] ?? '',
    'instructions' => $assignment['instructions'] ?? '',
    // datetime-local inputs use "2026-10-21T23:59".
    'due_at'       => isset($assignment['due_at']) ? date('Y-m-d\TH:i', strtotime($assignment['due_at'])) : '',
    'rubric_id'    => (string) ($assignment['rubric_id'] ?? ''),
];

$action = $isNew ? site_url("teacher/classes/{$class['id']}/assignments") : site_url("teacher/assignments/{$assignment['id']}");
$cancel = $isNew ? site_url("teacher/classes/{$class['id']}") : site_url("teacher/assignments/{$assignment['id']}");
?>
<?php $this->extend(page_layout()) ?>

<?php $this->section('title') ?><?= $isNew ? 'New assignment' : 'Edit assignment' ?><?php $this->endSection() ?>

<?php $this->section('content') ?>
<p class="breadcrumb">
    <a href="<?= site_url('teacher/classes') ?>">Classes</a> <?= icon('chevron-right') ?>
    <a href="<?= site_url("teacher/classes/{$class['id']}") ?>"><?= esc($class['name']) ?></a> <?= icon('chevron-right') ?>
</p>

<div class="page-header">
    <div>
        <h1><?= $isNew ? 'New assignment' : 'Edit assignment' ?></h1>
        <p><?= $isNew ? 'New assignments are saved as drafts. Students see them only after you publish.' : 'Changes are visible to students right away if the assignment is published.' ?></p>
    </div>
</div>

<div class="card card--medium">
    <?php if ($rubrics === []): ?>
        <div class="alert alert--warning" role="status">
            <?= icon('alert-triangle', 'alert__icon') ?>
            <div>You need a rubric before you can create an assignment.
                <a href="<?= site_url('teacher/rubrics/new') ?>">Create a rubric</a> first.</div>
        </div>
    <?php endif ?>

    <?= $this->include('partials/errors') ?>

    <form method="post" action="<?= $action ?>" class="form">
        <?= csrf_field() ?>

        <div class="field">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= esc($values['title'], 'attr') ?>" maxlength="150"
                   placeholder="e.g. Argumentative Essay: Should school start later?" required>
        </div>

        <div class="field">
            <label for="instructions">Instructions for students</label>
            <textarea id="instructions" name="instructions" rows="8" maxlength="10000" required><?= esc($values['instructions']) ?></textarea>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="due_at">Due date and time</label>
                <input type="datetime-local" id="due_at" name="due_at" value="<?= esc($values['due_at'], 'attr') ?>"
                       aria-describedby="due-hint" required>
                <p class="hint" id="due-hint">Students can edit until this time. Later submissions are marked late.</p>
            </div>

            <div class="field">
                <label for="rubric_id">Rubric</label>
                <select id="rubric_id" name="rubric_id" required>
                    <option value="">Choose a rubric</option>
                    <?php foreach ($rubrics as $rubric): ?>
                        <option value="<?= $rubric['id'] ?>" <?= $values['rubric_id'] === (string) $rubric['id'] ? 'selected' : '' ?>>
                            <?= esc($rubric['title']) ?> (<?= esc($rubric['total_points']) ?> pts)
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="button button--primary"><?= $isNew ? 'Save as draft' : 'Save changes' ?></button>
            <?= cancel_button($cancel) ?>
        </div>
    </form>
</div>
<?php $this->endSection() ?>
