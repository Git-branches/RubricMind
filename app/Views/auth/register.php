<?php
/**
 * @var CodeIgniter\View\View  $this
 * @var array{name: string, email: string} $old
 * @var bool                   $open   Registration is turned on (admin Settings).
 * @var array<string, string>  $errors
 */
?>
<?php $this->extend('layouts/guest') ?>

<?php $this->section('title') ?>Create Student Account<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="guest__form-brand">
    <?= brand_logo('md') ?>
    <span class="guest__form-brand-name">Rubric<span>Mind</span></span>
</div>

<h1 class="auth-title">Create Student Account</h1>

<?php if (! $open): ?>
    <p class="auth-subtitle">Student sign-up is turned off at this school. Ask your teacher or administrator to create your account.</p>
<?php else: ?>
    <p class="auth-subtitle">For students. Join your class after you sign in.</p>

    <?= $this->include('partials/errors') ?>

    <form method="post" action="<?= site_url('register') ?>" class="form">
        <?= csrf_field() ?>

        <div class="field">
            <label for="name">Full name</label>
            <span class="input-icon">
                <?= icon('users') ?>
                <input type="text" id="name" name="name" value="<?= esc($old['name'], 'attr') ?>" maxlength="100"
                       autocomplete="name" placeholder="Juan Dela Cruz" required autofocus>
            </span>
        </div>

        <div class="field">
            <label for="email">Email address</label>
            <span class="input-icon">
                <?= icon('mail') ?>
                <input type="email" id="email" name="email" value="<?= esc($old['email'], 'attr') ?>" maxlength="191"
                       autocomplete="email" placeholder="you@school.edu" required>
            </span>
        </div>

        <div class="field-pair">
            <div class="field">
                <label for="password">Password</label>
                <?= password_input('password', 'password', ['autocomplete' => 'new-password', 'minlength' => 8, 'maxlength' => 72, 'placeholder' => '8+ characters', 'required' => true]) ?>
            </div>

            <div class="field">
                <label for="password_confirm">Confirm password</label>
                <?= password_input('password_confirm', 'password_confirm', ['autocomplete' => 'new-password', 'placeholder' => 'Type it again', 'required' => true]) ?>
            </div>
        </div>

        <button type="submit" class="button button--primary button--block button--lg">
            <?= icon('user-plus') ?> Create account
        </button>
    </form>
<?php endif ?>

<p class="auth-help">
    <span>Already have an account?</span>
    <a href="<?= site_url('login') ?>">Sign in <?= icon('arrow-right') ?></a>
</p>
<?php $this->endSection() ?>
