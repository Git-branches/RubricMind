<?php
/**
 * Create and edit form for user accounts.
 *
 * @var CodeIgniter\View\View       $this
 * @var array<string, mixed>|null   $user   Null when creating.
 * @var array<string, string>       $old    Submitted values after a failed save.
 * @var array<string, string>       $errors
 */
$isNew  = $user === null;
$old  ??= [];
$value  = static fn (string $field): string => (string) ($old[$field] ?? $user[$field] ?? '');
$action = $isNew ? site_url('admin/users') : site_url("admin/users/{$user['id']}");
?>
<?php $this->extend(page_layout()) ?>

<?php $this->section('title') ?><?= $isNew ? 'Add user' : 'Edit user' ?><?php $this->endSection() ?>

<?php $this->section('content') ?>
<p class="breadcrumb"><a href="<?= site_url('admin/users') ?>">Users</a> <?= icon('chevron-right') ?></p>

<div class="page-header">
    <div>
        <h1><?= $isNew ? 'Add user' : 'Edit ' . esc($user['name']) ?></h1>
        <p><?= $isNew ? 'Create an account and share the temporary password with the user privately.' : 'Update this account\'s name, email, or role.' ?></p>
    </div>
</div>

<div class="card card--narrow">
    <?= $this->include('partials/errors') ?>

    <form method="post" action="<?= $action ?>" class="form">
        <?= csrf_field() ?>

        <div class="field">
            <label for="name">Full name</label>
            <input type="text" id="name" name="name" value="<?= esc($value('name'), 'attr') ?>" maxlength="100" required>
        </div>

        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= esc($value('email'), 'attr') ?>" maxlength="191" required>
        </div>

        <div class="field">
            <label for="role">Role</label>
            <select id="role" name="role" required>
                <?php foreach (App\Models\UserModel::ROLES as $role): ?>
                    <option value="<?= $role ?>" <?= $value('role') === $role ? 'selected' : '' ?>><?= ucfirst($role) ?></option>
                <?php endforeach ?>
            </select>
        </div>

        <?php if ($isNew): ?>
            <div class="field">
                <label for="password">Temporary password</label>
                <?= password_input('password', 'password', ['autocomplete' => 'new-password', 'minlength' => 8, 'maxlength' => 72, 'aria-describedby' => 'password-hint', 'required' => true]) ?>
                <p class="hint" id="password-hint">At least 8 characters. The user must change it at first sign-in.</p>
            </div>

            <div class="field">
                <label for="password_confirm">Confirm temporary password</label>
                <?= password_input('password_confirm', 'password_confirm', ['autocomplete' => 'new-password', 'required' => true]) ?>
            </div>
        <?php endif ?>

        <div class="form-actions">
            <button type="submit" class="button button--primary"><?= $isNew ? 'Create account' : 'Save changes' ?></button>
            <?= cancel_button(site_url('admin/users')) ?>
        </div>
    </form>
</div>
<?php $this->endSection() ?>
