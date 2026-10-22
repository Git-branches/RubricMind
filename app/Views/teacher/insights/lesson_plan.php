<?php
/**
 * @var CodeIgniter\View\View  $this
 * @var array<string, mixed>   $plan
 * @var array<string, mixed>   $assignment
 * @var array<string, string>  $errors
 */
$focus = json_decode($plan['focus'] ?? 'null', true);
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?><?= esc($plan['title']) ?><?php $this->endSection() ?>

<?php $this->section('content') ?>
<p class="breadcrumb">
    <a href="<?= site_url('teacher/insights') ?>">Insights</a> <?= icon('chevron-right') ?>
    <a href="<?= site_url("teacher/assignments/{$assignment['id']}/insights") ?>"><?= esc($assignment['title']) ?></a> <?= icon('chevron-right') ?>
</p>

<div class="page-header">
    <div>
        <h1>Lesson plan</h1>
        <p>A draft to adapt for your class. Edit anything before you use it.</p>
    </div>
    <div class="button-row">
        <?php if ($plan['provider'] === 'demo'): ?>
            <span class="tag tag--draft">Demo AI (simulated)</span>
        <?php else: ?>
            <span class="tag tag--info">Drafted by <?= esc(App\Libraries\SystemSettings::AI_PROVIDERS[$plan['provider']][0] ?? $plan['provider']) ?></span>
        <?php endif ?>
        <button type="button" class="button" onclick="window.print()"><?= icon('file-text') ?> Print</button>
    </div>
</div>

<div class="two-column">
    <form method="post" action="<?= site_url("teacher/lesson-plans/{$plan['id']}") ?>" class="card form">
        <?= csrf_field() ?>
        <?= $this->include('partials/errors') ?>

        <div class="field">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= esc($plan['title'], 'attr') ?>" maxlength="200" required>
        </div>

        <div class="field">
            <label for="content">Lesson plan</label>
            <textarea id="content" name="content" rows="26" maxlength="20000" required class="plan-text"><?= esc($plan['content']) ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="button button--primary"><?= icon('check-circle') ?> Save changes</button>
            <a href="<?= site_url("teacher/assignments/{$assignment['id']}/insights#lesson-plans") ?>" class="button">Back to insights</a>
        </div>
    </form>

    <aside>
        <?php if (is_array($focus)): ?>
            <section class="card">
                <h2>Built from</h2>
                <p class="muted small">Class results at the time this draft was generated (<?= esc($focus['graded_count']) ?> finalized grades, class average <?= esc($focus['class_average']) ?>%).</p>
                <ul class="insight-list">
                    <?php foreach ($focus['criteria'] as $c): ?>
                        <li><span><?= esc($c['title']) ?></span><strong><?= esc($c['average_percent']) ?>%</strong></li>
                    <?php endforeach ?>
                </ul>
                <?php if ($focus['mechanics'] !== []): ?>
                    <h3 style="margin-top: 1rem">Common writing patterns</h3>
                    <ul class="insight-list">
                        <?php foreach ($focus['mechanics'] as $m): ?>
                            <li><span><?= esc($m['pattern']) ?></span><strong><?= esc($m['students']) ?> students</strong></li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
            </section>
        <?php endif ?>

        <section class="danger-zone">
            <p><strong>Delete this draft</strong><br>This cannot be undone.</p>
            <form method="post" action="<?= site_url("teacher/lesson-plans/{$plan['id']}/delete") ?>" data-confirm="Delete this lesson plan?">
                <?= csrf_field() ?>
                <button type="submit" class="button button--danger"><?= icon('trash') ?> Delete</button>
            </form>
        </section>
    </aside>
</div>
<?php $this->endSection() ?>
