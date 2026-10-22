<?php
/**
 * @var CodeIgniter\View\View  $this
 * @var string                 $email
 * @var array<string, string>  $errors
 */
$schoolName = service('systemSettings')->schoolName();
?>
<?php $this->extend('layouts/guest') ?>

<?php $this->section('title') ?>Sign in<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="guest__form-brand">
    <?= brand_logo('md') ?>
    <span class="guest__form-brand-name">Rubric<span>Mind</span></span>
</div>

<h1 class="auth-title">Welcome back</h1>
<p class="auth-subtitle">Sign in to your RubricMind account<?= $schoolName !== '' ? ' at ' . esc($schoolName) : '' ?>.</p>

<?= $this->include('partials/errors') ?>

<form method="post" action="<?= site_url('login') ?>" class="form">
    <?= csrf_field() ?>

    <div class="field">
        <label for="email">Email address</label>
        <span class="input-icon">
            <?= icon('mail') ?>
            <input type="email" id="email" name="email" value="<?= esc($email, 'attr') ?>"
                   autocomplete="username" placeholder="you@school.edu" required autofocus>
        </span>
    </div>

    <div class="field">
        <label for="password">Password</label>
        <?= password_input('password', 'password', ['autocomplete' => 'current-password', 'placeholder' => 'Enter your password', 'required' => true]) ?>
    </div>

    <button type="submit" class="button button--primary button--block button--lg">
        Sign in <?= icon('arrow-right') ?>
    </button>
</form>

<div class="divider"><span>or</span></div>

<?php if (service('systemSettings')->registrationEnabled()): ?>
    <a href="<?= site_url('register') ?>" class="button button--block"><?= icon('user-plus') ?> Create Student Account</a>
<?php endif ?>

<p class="auth-help">
    <?= icon('key') ?>
    <a href="<?= site_url('forgot-password') ?>">Forgot your password?</a>
    <span>We will email you a reset link.</span>
</p>
<?php $this->endSection() ?>
