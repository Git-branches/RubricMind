<?php
/**
 * @var CodeIgniter\View\View        $this
 * @var list<array<string, mixed>>   $emails
 * @var CodeIgniter\Pager\Pager      $pager
 * @var App\Libraries\Mailer         $mailer
 */
use App\Models\EmailOutboxModel;

$statusTags = ['sent' => 'tag--published', 'saved' => 'tag--info', 'failed' => 'tag--late'];
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?>Email outbox<?php $this->endSection() ?>

<?php $this->section('content') ?>
<?php
$testInbox  = $mailer->smtpConfigured() ? $mailer->testInboxUrl() : '';
$hasFailure = in_array('failed', array_column($emails, 'status'), true);
?>
<div class="page-header">
    <div>
        <h1>Email outbox</h1>
        <p>Every email RubricMind sends, such as password reset links.</p>
    </div>
    <?php if ($testInbox !== ''): ?>
        <div class="button-row">
            <a href="<?= esc($testInbox, 'attr') ?>" target="_blank" rel="noopener" class="button button--primary"><?= icon('inbox') ?> Open test inbox</a>
        </div>
    <?php endif ?>
</div>

<?php if ($testInbox !== ''): ?>
    <div class="alert alert--info" role="status">
        <?= icon('mail', 'alert__icon') ?>
        <div>
            <strong>Emails go to the test inbox (Mailpit) on this computer.</strong>
            Every user's emails arrive there, whatever their address, and nothing is sent over the internet.
            Open <a href="<?= esc($testInbox, 'attr') ?>" target="_blank" rel="noopener"><?= esc($testInbox) ?></a> to read them like a mailbox.
        </div>
    </div>
    <?php if ($hasFailure): ?>
        <div class="alert alert--warning" role="alert">
            <?= icon('alert-triangle', 'alert__icon') ?>
            <div>Some emails failed. Mailpit is probably not running: start <code>tools\mailpit\start-mailpit.bat</code> and ask the user to request a new link. Meanwhile, you can open a failed email below and copy its link.</div>
        </div>
    <?php endif ?>
<?php elseif ($mailer->smtpConfigured()): ?>
    <div class="alert alert--success" role="status">
        <?= icon('check-circle', 'alert__icon') ?>
        <div>Email delivery is on. Emails are sent from <strong><?= esc($mailer->senderAddress()) ?></strong> through <?= esc($mailer->serverName()) ?>, and a copy is kept here.</div>
    </div>
<?php else: ?>
    <div class="alert alert--info" role="status">
        <?= icon('mail', 'alert__icon') ?>
        <div>
            <strong>Email delivery is not set up, so emails are only saved here.</strong>
            When a user asks for a password reset, open their email below and give them the link.
            To send real emails, add your Gmail address and a Google App Password to the <code>.env</code> file (see the <code>email.</code> settings there).
        </div>
    </div>
<?php endif ?>

<section class="card">
    <?php if ($emails === []): ?>
        <p class="inline-empty"><?= icon('inbox') ?> No emails yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">When</th>
                    <th scope="col">To</th>
                    <th scope="col">Subject</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($emails as $email): ?>
                    <tr>
                        <td class="nowrap"><?= esc(format_datetime($email['created_at'])) ?></td>
                        <td><?= esc($email['to_email']) ?></td>
                        <td><?= esc($email['subject']) ?></td>
                        <td>
                            <span class="tag <?= $statusTags[$email['status']] ?? 'tag--closed' ?>"><?= esc(EmailOutboxModel::STATUS_LABELS[$email['status']] ?? $email['status']) ?></span>
                            <?php if ($email['error'] !== null): ?>
                                <div class="muted small"><?= esc($email['error']) ?></div>
                            <?php endif ?>
                        </td>
                        <td class="actions">
                            <a href="<?= site_url("admin/emails/{$email['id']}") ?>" data-modal class="button button--small"><?= icon('eye') ?> Open</a>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?= $pager->links() ?>
    <?php endif ?>
</section>
<?php $this->endSection() ?>
