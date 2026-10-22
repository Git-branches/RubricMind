<?php
/**
 * Add students to a class: creates student accounts as needed (FR-02).
 *
 * @var CodeIgniter\View\View       $this
 * @var list<array<string, mixed>>  $classes  The teacher's active classes.
 * @var array{class_id: string, students: string} $old
 * @var array<array-key, string>    $errors
 */
?>
<?php $this->extend(page_layout()) ?>

<?php $this->section('title') ?>Add students<?php $this->endSection() ?>

<?php $this->section('content') ?>
<p class="breadcrumb"><a href="<?= site_url('teacher/students') ?>">Students</a> <?= icon('chevron-right') ?></p>

<div class="page-header">
    <div>
        <h1>Add students</h1>
        <p>Students without an account get one with the temporary password below. Existing students are simply enrolled.</p>
    </div>
</div>

<div class="card card--medium">
    <?= $this->include('partials/errors') ?>

    <?php if ($classes === []): ?>
        <div class="alert alert--warning" role="status">
            <?= icon('alert-triangle', 'alert__icon') ?>
            <div>Create a class first, then add students to it.</div>
        </div>
    <?php else: ?>
        <form method="post" action="<?= site_url('teacher/students') ?>" class="form">
            <?= csrf_field() ?>

            <div class="field">
                <label for="class_id">Class</label>
                <select id="class_id" name="class_id" required>
                    <option value="">Choose a class</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= $class['id'] ?>" <?= $old['class_id'] === (string) $class['id'] ? 'selected' : '' ?>>
                            <?= esc($class['name']) ?><?= $class['section'] ? ' · ' . esc($class['section']) : '' ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>

            <div class="field">
                <label for="students">Students</label>
                <textarea id="students" name="students" rows="7" required aria-describedby="students-hint"
                          placeholder="Juan Dela Cruz, juan.delacruz@school.edu&#10;Maria Santos, maria.santos@school.edu"><?= esc($old['students']) ?></textarea>
                <p class="hint" id="students-hint">One student per line: <strong>full name, email</strong>. You can paste two columns from a spreadsheet.
                    For students who already have an account, the email alone is enough. Up to <?= App\Libraries\StudentRoster::MAX_LINES ?> at a time.</p>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="password">Temporary password <span class="optional">(for new accounts)</span></label>
                    <?= password_input('password', 'password', ['autocomplete' => 'new-password', 'minlength' => 8, 'maxlength' => 72, 'aria-describedby' => 'password-hint']) ?>
                </div>
                <div class="field">
                    <label for="password_confirm">Confirm temporary password</label>
                    <?= password_input('password_confirm', 'password_confirm', ['autocomplete' => 'new-password']) ?>
                </div>
            </div>
            <p class="hint" id="password-hint" style="margin-top: -0.5rem">At least 8 characters. Share it with the new students privately; each one chooses a new password at first sign-in.</p>

            <div class="form-actions">
                <button type="submit" class="button button--primary"><?= icon('user-plus') ?> Add students</button>
                <?= cancel_button(site_url('teacher/students')) ?>
            </div>
        </form>
    <?php endif ?>
</div>
<?php $this->endSection() ?>
