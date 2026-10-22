<?php
/**
 * @var CodeIgniter\View\View  $this
 * @var array<string, mixed>   $user
 * @var array<string, string>  $errors
 */
?>
<?php $this->extend(page_layout()) ?>

<?php $this->section('title') ?>Reset password<?php $this->endSection() ?>

<?php $this->section('content') ?>
<p class="breadcrumb"><a href="<?= site_url('admin/users') ?>">Users</a> <?= icon('chevron-right') ?></p>

<div class="page-header">
    <div>
        <h1>Reset password</h1>
        <p>Set a temporary password for <?= esc($user['name']) ?>. They will choose a new one at their next sign-in.</p>
    </div>
</div>

<div class="card card--narrow">
    <div class="person" style="margin-bottom: 1.25rem">
        <?= avatar($user['name']) ?>
        <span class="person__text">
            <span class="person__name"><?= esc($user['name']) ?></span>
            <span class="person__meta"><?= esc($user['email']) ?></span>
        </span>
    </div>

    <?= $this->include('partials/errors') ?>

    <form method="post" action="<?= site_url("admin/users/{$user['id']}/password") ?>" class="form">
        <?= csrf_field() ?>

        <div class="field">
            <label for="password">Temporary password</label>
            <?= password_input('password', 'password', ['autocomplete' => 'new-password', 'minlength' => 8, 'maxlength' => 72, 'aria-describedby' => 'password-hint', 'required' => true]) ?>
            <p class="hint" id="password-hint">At least 8 characters.</p>
        </div>

        <div class="field">
            <label for="password_confirm">Confirm temporary password</label>
            <?= password_input('password_confirm', 'password_confirm', ['autocomplete' => 'new-password', 'required' => true]) ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="button button--primary">Set temporary password</button>
            <?= cancel_button(site_url('admin/users')) ?>
        </div>
    </form>
</div>
<?php $this->endSection() ?>
