<?php
/**
 * @var CodeIgniter\View\View       $this
 * @var list<array<string, mixed>>  $classes The student's classes.
 * @var string                      $code    What the student typed, after an error.
 */
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?>My classes<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="page-header">
    <div>
        <h1>My classes</h1>
        <p>Join a class with the code from your teacher. One account works for all your classes.</p>
    </div>
</div>

<section class="card" id="join">
    <h2><?= icon('key') ?> Join a Class</h2>
    <form method="post" action="<?= site_url('student/classes/join') ?>" class="join-form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="code">Class code</label>
            <input type="text" id="code" name="code" value="<?= esc($code, 'attr') ?>" maxlength="20" required
                   placeholder="e.g. K7MP-Q2XR" autocomplete="off" autocapitalize="characters" spellcheck="false" aria-describedby="code-hint">
        </div>
        <button type="submit" class="button button--primary"><?= icon('user-plus') ?> Join class</button>
    </form>
    <p class="hint" id="code-hint" style="margin-bottom: 0">Your teacher shares the code in class. Capital letters and the dash do not matter.</p>
</section>

<section class="card">
    <div class="section-header">
        <h2>Enrolled classes <span class="muted">(<?= count($classes) ?>)</span></h2>
    </div>

    <?php if ($classes === []): ?>
        <p class="inline-empty"><?= icon('classes') ?> You have not joined a class yet. Enter a class code above.</p>
    <?php else: ?>
        <div class="class-tiles">
            <?php foreach ($classes as $class): ?>
                <a class="card card--link class-tile" href="<?= site_url("student/classes/{$class['id']}") ?>">
                    <h3>
                        <?= esc($class['name']) ?>
                        <?php if ($class['status'] !== 'active'): ?><span class="tag tag--closed">Archived</span><?php endif ?>
                    </h3>
                    <?php $subtitle = implode(' · ', array_filter([$class['section'], $class['school_year']])) ?>
                    <?php if ($subtitle !== ''): ?><p class="muted small" style="margin: 0"><?= esc($subtitle) ?></p><?php endif ?>
                    <div class="list__meta">
                        <span><?= icon('users') ?> <?= esc($class['teacher_name']) ?></span>
                        <span><?= icon('file-text') ?> <?= esc($class['assignment_count']) ?> assignment(s)</span>
                        <span><?= icon('calendar') ?> Joined <?= esc(date('M j, Y', strtotime($class['enrolled_at']))) ?></span>
                    </div>
                </a>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</section>
<?php $this->endSection() ?>
