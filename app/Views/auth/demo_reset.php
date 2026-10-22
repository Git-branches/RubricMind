<?php
/**
 * Shown after "Forgot your password?" in Defense Demo Mode only (see PasswordReset::demo()).
 * It looks like a normal "check your email" page; the button opens the email in
 * the local test inbox (Mailpit), or the reset link itself when there is no test inbox.
 *
 * @var CodeIgniter\View\View  $this
 * @var string                 $email       The account the link was created for.
 * @var string                 $link        The real reset link, as sent in the email.
 * @var string                 $expires_at
 * @var string                 $mail_status 'sent', 'saved', or 'failed'
 * @var string                 $mailpit     The test inbox URL, or ''.
 */
$target = $mailpit !== '' && $mail_status === 'sent'
    ? rtrim($mailpit, '/') . '/view/latest' // Mailpit opens its newest email: this one.
    : $link;
?>
<?php $this->extend('layouts/guest') ?>

<?php $this->section('title') ?>Check your email<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="guest__form-brand">
    <?= brand_logo('md') ?>
    <span class="guest__form-brand-name">Rubric<span>Mind</span></span>
</div>

<h1 class="auth-title">Check your email</h1>
<p class="auth-subtitle">
    We sent a password reset link to <strong><?= esc($email) ?></strong>.
    It works once and expires at <?= esc(date('g:i A', strtotime($expires_at))) ?>.
</p>

<a href="<?= esc($target, 'attr') ?>" class="button button--primary button--block button--lg">
    <?= icon('mail') ?> Password Reset Link
</a>

<p class="auth-help">
    <a href="<?= site_url('login') ?>"><?= icon('arrow-right') ?> Back to sign in</a>
</p>
<?php $this->endSection() ?>
