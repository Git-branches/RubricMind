<?php
/**
 * @var CodeIgniter\View\View       $this
 * @var list<array<string, mixed>>  $rubrics
 */
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?>Rubrics<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="page-header">
    <div>
        <h1>Rubrics</h1>
        <p>Reusable scoring guides with criteria and performance levels.</p>
    </div>
    <a href="<?= site_url('teacher/rubrics/new') ?>" class="button button--primary"><?= icon('plus') ?> New rubric</a>
</div>

<?php if ($rubrics === []): ?>
    <section class="card empty-state">
        <span class="empty-state__icon"><?= icon('rubrics') ?></span>
        <h2>Create your first rubric</h2>
        <p>A rubric lists the criteria you grade on and the points for each performance level. Every assignment needs one.</p>
        <a href="<?= site_url('teacher/rubrics/new') ?>" class="button button--primary"><?= icon('plus') ?> New rubric</a>
    </section>
<?php else: ?>
    <section class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Rubric</th>
                    <th scope="col">Criteria</th>
                    <th scope="col">Total points</th>
                    <th scope="col">Used by</th>
                    <th scope="col">Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rubrics as $rubric): ?>
                    <tr>
                        <td>
                            <span class="person">
                                <span class="class-card__icon" style="width: 36px; height: 36px; margin: 0"><?= icon('rubrics') ?></span>
                                <a class="person__name" href="<?= site_url("teacher/rubrics/{$rubric['id']}") ?>"><?= esc($rubric['title']) ?></a>
                            </span>
                        </td>
                        <td><?= esc($rubric['criterion_count']) ?></td>
                        <td><span class="points"><?= esc($rubric['total_points']) ?> pts</span></td>
                        <td class="muted"><?= esc($rubric['assignment_count']) ?> assignment(s)</td>
                        <td>
                            <?php if ($rubric['locked_at'] !== null): ?>
                                <span class="tag tag--locked">Locked</span>
                            <?php else: ?>
                                <span class="tag tag--published">Editable</span>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif ?>
<?php $this->endSection() ?>
