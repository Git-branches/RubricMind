<?php
/**
 * @var CodeIgniter\View\View  $this
 * @var array<string, string>  $errors
 */
$mustChange = service('auth')->user()['must_change_password'];
?>
<?php $this->extend(page_layout()) ?>

<?php $this->section('title') ?>Change password<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="page-header">
    <div>
        <h1>Change password</h1>
        <p>Use at least 8 characters. A longer phrase is easier to remember and harder to guess.</p>
    </div>
</div>

<div class="card card--narrow">
    <?php if ($mustChange): ?>
        <div class="alert alert--warning" role="status">
            <?= icon('key', 'alert__icon') ?>
            <div>You are using a temporary password set by an administrator. Choose your own password to continue.</div>
        </div>
    <?php endif ?>

    <?= $this->include('partials/errors') ?>

    <form method="post" action="<?= site_url('account/password') ?>" class="form">
        <?= csrf_field() ?>

        <div class="field">
            <label for="current_password"><?= $mustChange ? 'Temporary password' : 'Current password' ?></label>
            <?= password_input('current_password', 'current_password', ['autocomplete' => 'current-password', 'required' => true]) ?>
        </div>

        <div class="field">
            <label for="password">New password</label>
            <?= password_input('password', 'password', ['autocomplete' => 'new-password', 'minlength' => 8, 'maxlength' => 72, 'aria-describedby' => 'password-hint', 'required' => true]) ?>
            <p class="hint" id="password-hint">At least 8 characters.</p>
        </div>

        <div class="field">
            <label for="password_confirm">Confirm new password</label>
            <?= password_input('password_confirm', 'password_confirm', ['autocomplete' => 'new-password', 'required' => true]) ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="button button--primary">Save password</button>
            <?php if (! $mustChange): ?>
                <?= cancel_button(site_url('/')) ?>
            <?php endif ?>
        </div>
    </form>
</div>
<?php $this->endSection() ?>
