<?php
/**
 * @var CodeIgniter\View\View  $this
 * @var string                 $email
 * @var array<string, string>  $errors
 */
?>
<?php $this->extend('layouts/guest') ?>

<?php $this->section('title') ?>Forgot password<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="guest__form-brand">
    <?= brand_logo('md') ?>
    <span class="guest__form-brand-name">Rubric<span>Mind</span></span>
</div>

<h1 class="auth-title">Forgot your password?</h1>
<p class="auth-subtitle">Enter the email address of your account. We will send you a link to choose a new password.</p>

<?= $this->include('partials/errors') ?>

<form method="post" action="<?= site_url('forgot-password') ?>" class="form">
    <?= csrf_field() ?>

    <div class="field">
        <label for="email">Email address</label>
        <span class="input-icon">
            <?= icon('mail') ?>
            <input type="email" id="email" name="email" value="<?= esc($email, 'attr') ?>"
                   autocomplete="username" placeholder="you@school.edu" maxlength="191" required autofocus>
        </span>
    </div>

    <button type="submit" class="button button--primary button--block button--lg">
        <?= icon('send') ?> Send reset link
    </button>
</form>

<p class="auth-help">
    <a href="<?= site_url('login') ?>"><?= icon('arrow-right') ?> Back to sign in</a>
</p>
<?php $this->endSection() ?>
