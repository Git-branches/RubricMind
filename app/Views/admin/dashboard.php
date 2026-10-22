<?php
/**
 * @var CodeIgniter\View\View        $this
 * @var array<string, int>           $counts
 * @var list<array<string, mixed>>   $recentLogs
 */
$stats = [
    ['admin', 'Administrators', 'shield', 'indigo'],
    ['teacher', 'Teachers', 'graduation', 'sky'],
    ['student', 'Students', 'users', 'emerald'],
];
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?>Admin dashboard<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="page-header">
    <div>
        <h1>Admin dashboard</h1>
        <p>Manage accounts and review system activity.</p>
    </div>
    <a href="<?= site_url('admin/users/new') ?>" data-modal class="button button--primary"><?= icon('user-plus') ?> Add user</a>
</div>

<div class="stats">
    <?php foreach ($stats as [$role, $label, $iconName, $color]): ?>
        <a class="stat stat--<?= $color ?> card" href="<?= site_url('admin/users?role=' . $role) ?>">
            <span class="stat__icon"><?= icon($iconName) ?></span>
            <span class="stat__body">
                <span class="stat__value"><?= esc($counts[$role]) ?></span>
                <span class="stat__label"><?= esc($label) ?></span>
            </span>
        </a>
    <?php endforeach ?>
</div>

<section class="card">
    <div class="section-header">
        <div>
            <h2>Recent activity</h2>
            <p class="muted small">The latest actions recorded in the audit log.</p>
        </div>
        <a href="<?= site_url('admin/audit-logs') ?>" class="button button--small">View all <?= icon('arrow-right') ?></a>
    </div>
    <?php $this->setVar('logs', $recentLogs) ?>
    <?= $this->include('partials/audit_table') ?>
</section>
<?php $this->endSection() ?>
