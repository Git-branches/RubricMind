<?php
/**
 * @var CodeIgniter\View\View  $this
 * @var array<string, mixed>   $email
 */
use App\Models\EmailOutboxModel;

// Links in the email body become clickable; everything else stays plain, escaped text.
$body = preg_replace_callback(
    '~https?://[^\s<]+~',
    static fn (array $m) => '<a href="' . $m[0] . '" target="_blank" rel="noopener noreferrer">' . $m[0] . '</a>',
    esc($email['body']),
);

// A reset link to give the user by hand when the email did not reach them.
$resetLink = preg_match('~https?://\S+/reset-password/[0-9a-f]{64}~', $email['body'], $m) === 1 ? $m[0] : null;
?>
<?php $this->extend(page_layout()) ?>

<?php $this->section('title') ?><?= esc($email['subject']) ?><?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="page-header">
    <div>
        <h1><?= esc($email['subject']) ?></h1>
        <div class="page-header__meta">
            <span><?= icon('mail') ?> To <?= esc($email['to_email']) ?></span>
            <span><?= icon('clock') ?> <?= esc(format_datetime($email['created_at'])) ?></span>
            <span><?= esc(EmailOutboxModel::STATUS_LABELS[$email['status']] ?? $email['status']) ?></span>
        </div>
    </div>
</div>

<div class="card">
    <div class="email-body"><?= $body ?></div>

    <?php if ($resetLink !== null): ?>
        <p class="hint" style="margin-top: 0.75rem">
            <?= icon('key') ?> If this email did not reach the user, copy the link and give it to them in person.
            Only give it to the account owner: anyone with the link can set a new password until it expires.
        </p>
    <?php endif ?>

    <div class="form-actions">
        <?php if ($resetLink !== null): ?>
            <button type="button" class="button button--primary" data-copy="<?= esc($resetLink, 'attr') ?>"><?= icon('copy') ?> Copy reset link</button>
        <?php endif ?>
        <?= cancel_button(site_url('admin/emails'), 'Close') ?>
    </div>
</div>
<?php $this->endSection() ?>
