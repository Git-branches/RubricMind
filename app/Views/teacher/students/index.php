<?php
/**
 * @var CodeIgniter\View\View       $this
 * @var list<array<string, mixed>>  $students
 * @var string                      $search
 * @var list<array<string, mixed>>  $classes
 */
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?>Students<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="page-header">
    <div>
        <h1>Students</h1>
        <p>Everyone enrolled in your classes. Add students to create their accounts and enroll them in one step.</p>
    </div>
    <?php if ($classes !== []): ?>
        <a href="<?= site_url('teacher/students/new') ?>" class="button button--primary" data-modal><?= icon('user-plus') ?> Add students</a>
    <?php endif ?>
</div>

<form method="get" action="<?= site_url('teacher/students') ?>" class="card filters filters--simple" role="search">
    <div class="field">
        <label for="q">Search</label>
        <div class="search-input">
            <?= icon('search') ?>
            <input type="search" id="q" name="q" value="<?= esc($search, 'attr') ?>" placeholder="Search by name or email">
        </div>
    </div>
    <div class="filters__actions">
        <button type="submit" class="button"><?= icon('filter') ?> Search</button>
        <?php if ($search !== ''): ?><a href="<?= site_url('teacher/students') ?>" class="button button--ghost">Clear</a><?php endif ?>
    </div>
</form>

<section class="card">
    <?php if ($students === []): ?>
        <div class="empty-state">
            <span class="empty-state__icon"><?= icon('users') ?></span>
            <?php if ($search !== ''): ?>
                <h2>No students found</h2>
                <p>No student in your classes matches "<?= esc($search) ?>".</p>
            <?php elseif ($classes === []): ?>
                <h2>Create a class first</h2>
                <p>Students are added to a class. Create your first class, then add students to it.</p>
                <a href="<?= site_url('teacher/classes/new') ?>" class="button button--primary" data-modal><?= icon('plus') ?> New class</a>
            <?php else: ?>
                <h2>No students yet</h2>
                <p>Paste a list of names and emails to create accounts and enroll students in one step.</p>
                <a href="<?= site_url('teacher/students/new') ?>" class="button button--primary" data-modal><?= icon('user-plus') ?> Add students</a>
            <?php endif ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Student</th>
                    <th scope="col">Classes</th>
                    <th scope="col">Account</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td>
                            <span class="person">
                                <?= avatar($student['name']) ?>
                                <span class="person__text">
                                    <span class="person__name"><?= esc($student['name']) ?></span>
                                    <span class="person__meta"><?= esc($student['email']) ?></span>
                                </span>
                            </span>
                        </td>
                        <td>
                            <div class="button-row">
                                <?php foreach ($student['classes'] as $class): ?>
                                    <a href="<?= site_url("teacher/classes/{$class['id']}#students") ?>" class="tag tag--info"><?= esc($class['name']) ?></a>
                                <?php endforeach ?>
                            </div>
                        </td>
                        <td>
                            <span class="status status--<?= esc($student['status'], 'attr') ?>"><?= esc(ucfirst($student['status'])) ?></span>
                            <?php if ($student['must_change_password']): ?><div class="muted small">Has not signed in yet</div><?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
<?php $this->endSection() ?>
