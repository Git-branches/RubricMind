<?php
/**
 * @var CodeIgniter\View\View        $this
 * @var list<array<string, mixed>>   $logs
 * @var CodeIgniter\Pager\Pager      $pager
 */
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?>Audit log<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="page-header">
    <div>
        <h1>Audit log</h1>
        <p>A permanent record of sign-ins, account changes, and grading actions.</p>
    </div>
</div>

<section class="card">
    <?= $this->include('partials/audit_table') ?>
    <?= $pager->links() ?>
</section>
<?php $this->endSection() ?>
