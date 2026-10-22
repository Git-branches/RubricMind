<?php
/**
 * @var CodeIgniter\View\View  $this
 * @var list<array{class: array, assignments: list<array>}> $classes
 */
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?>Insights<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="page-header">
    <div>
        <h1>Classroom insights</h1>
        <p>See where each class is strong and where it needs support. Only finalized grades are counted.</p>
    </div>
</div>

<?php if ($classes === []): ?>
    <section class="card empty-state">
        <span class="empty-state__icon"><?= icon('bar-chart') ?></span>
        <h2>No insights yet</h2>
        <p>Insights appear once you publish assignments and finalize grades.</p>
    </section>
<?php else: ?>
    <?php foreach ($classes as $group): ?>
        <section class="card">
            <div class="section-header">
                <h2><?= icon('classes') ?> <?= esc($group['class']['name']) ?></h2>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th scope="col">Assignment</th>
                        <th scope="col">Due</th>
                        <th scope="col">Finalized grades</th>
                        <th scope="col">Class average</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($group['assignments'] as $assignment): ?>
                        <tr>
                            <td><a href="<?= site_url("teacher/assignments/{$assignment['id']}/insights") ?>"><?= esc($assignment['title']) ?></a></td>
                            <td class="nowrap muted"><?= esc(format_datetime($assignment['due_at'])) ?></td>
                            <td><?= esc($assignment['graded']) ?></td>
                            <td style="min-width: 180px">
                                <?php if ($assignment['average'] === null): ?>
                                    <span class="muted">No finalized grades</span>
                                <?php else: ?>
                                    <span class="meter" title="<?= esc($assignment['average'], 'attr') ?>%">
                                        <span class="meter__track"><span class="meter__fill" style="width: <?= (int) $assignment['average'] ?>%"></span></span>
                                        <strong><?= esc($assignment['average']) ?>%</strong>
                                    </span>
                                <?php endif ?>
                            </td>
                            <td class="actions">
                                <a href="<?= site_url("teacher/assignments/{$assignment['id']}/insights") ?>" class="button button--small">View insights</a>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach ?>
<?php endif ?>
<?php $this->endSection() ?>
