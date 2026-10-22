<?php
/**
 * Create and edit form for classes.
 *
 * @var CodeIgniter\View\View       $this
 * @var array<string, mixed>|null   $class  Null when creating.
 * @var array<string, mixed>        $old
 * @var array<string, string>       $errors
 */
$isNew  = $class === null;
$old  ??= [];
$value  = static fn (string $field): string => (string) (array_key_exists($field, $old) ? $old[$field] : ($class[$field] ?? ''));
$action = $isNew ? site_url('teacher/classes') : site_url("teacher/classes/{$class['id']}");
$cancel = $isNew ? site_url('teacher/classes') : site_url("teacher/classes/{$class['id']}");
?>
<?php $this->extend(page_layout()) ?>

<?php $this->section('title') ?><?= $isNew ? 'New class' : 'Edit class' ?><?php $this->endSection() ?>

<?php $this->section('content') ?>
<p class="breadcrumb"><a href="<?= site_url('teacher/classes') ?>">Classes</a> <?= icon('chevron-right') ?></p>

<div class="page-header">
    <div>
        <h1><?= $isNew ? 'New class' : 'Edit ' . esc($class['name']) ?></h1>
        <p><?= $isNew ? 'You can enroll students and add assignments after creating the class.' : 'Update the class details.' ?></p>
    </div>
</div>

<div class="card card--narrow">
    <?= $this->include('partials/errors') ?>

    <form method="post" action="<?= $action ?>" class="form">
        <?= csrf_field() ?>

        <div class="field">
            <label for="name">Class name</label>
            <input type="text" id="name" name="name" value="<?= esc($value('name'), 'attr') ?>" maxlength="100"
                   placeholder="e.g. English 10" required>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="section">Section <span class="optional">(optional)</span></label>
                <input type="text" id="section" name="section" value="<?= esc($value('section'), 'attr') ?>" maxlength="50"
                       placeholder="e.g. Grade 10 – Rizal">
            </div>
            <div class="field">
                <label for="school_year">School year <span class="optional">(optional)</span></label>
                <input type="text" id="school_year" name="school_year" value="<?= esc($value('school_year'), 'attr') ?>" maxlength="20"
                       placeholder="e.g. 2026–2027">
            </div>
        </div>

        <div class="field">
            <label for="description">Description <span class="optional">(optional)</span></label>
            <textarea id="description" name="description" rows="3" maxlength="2000"><?= esc($value('description')) ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="button button--primary"><?= $isNew ? 'Create class' : 'Save changes' ?></button>
            <?= cancel_button($cancel) ?>
        </div>
    </form>
</div>
<?php $this->endSection() ?>
