<?php
/**
 * @var CodeIgniter\View\View  $this
 * @var string                 $token
 * @var array<string, string>  $errors
 */
?>
<?php $this->extend('layouts/guest') ?>

<?php $this->section('title') ?>Choose a new password<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="guest__form-brand">
    <?= brand_logo('md') ?>
    <span class="guest__form-brand-name">Rubric<span>Mind</span></span>
</div>

<h1 class="auth-title">Choose a new password</h1>
<p class="auth-subtitle">Use at least 8 characters. A longer phrase is easier to remember and harder to guess.</p>

<?= $this->include('partials/errors') ?>

<form method="post" action="<?= site_url('reset-password/' . esc($token, 'url')) ?>" class="form">
    <?= csrf_field() ?>

    <div class="field">
        <label for="password">New password</label>
        <?= password_input('password', 'password', ['autocomplete' => 'new-password', 'minlength' => 8, 'maxlength' => 72, 'required' => true, 'autofocus' => true]) ?>
    </div>

    <div class="field">
        <label for="password_confirm">Confirm new password</label>
        <?= password_input('password_confirm', 'password_confirm', ['autocomplete' => 'new-password', 'required' => true]) ?>
    </div>

    <button type="submit" class="button button--primary button--block button--lg">
        <?= icon('check-circle') ?> Save new password
    </button>
</form>

<p class="auth-help">
    <a href="<?= site_url('login') ?>"><?= icon('arrow-right') ?> Back to sign in</a>
</p>
<?php $this->endSection() ?>
