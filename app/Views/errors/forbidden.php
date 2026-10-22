<?php
/**
 * Shown by RoleFilter when a signed-in user opens a page for another role.
 *
 * @var CodeIgniter\View\View $this
 */
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?>Access denied<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="card card--narrow empty-state" style="margin: 3rem auto">
    <span class="empty-state__icon" style="background: var(--error-bg); color: var(--error)"><?= icon('lock') ?></span>
    <h1 style="font-size: 1.35rem">Access denied</h1>
    <p>Your account does not have permission to open this page.</p>
    <a href="<?= site_url('/') ?>" class="button button--primary">Go to my dashboard</a>
</div>
<?php $this->endSection() ?>
