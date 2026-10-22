<?php
/**
 * @var CodeIgniter\View\View        $this
 * @var list<array<string, mixed>>   $users
 * @var CodeIgniter\Pager\Pager      $pager
 * @var array{role: string, status: string, q: string} $filters
 */
$currentUserId = service('auth')->id();
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?>Users<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="page-header">
    <div>
        <h1>Users</h1>
        <p>Create accounts for teachers and students, and manage their access.</p>
    </div>
    <a href="<?= site_url('admin/users/new') ?>" data-modal class="button button--primary"><?= icon('user-plus') ?> Add user</a>
</div>

<form method="get" action="<?= site_url('admin/users') ?>" class="card filters" role="search">
    <div class="field">
        <label for="q">Search</label>
        <div class="search-input">
            <?= icon('search') ?>
            <input type="search" id="q" name="q" value="<?= esc($filters['q'], 'attr') ?>" placeholder="Search by name or email">
        </div>
    </div>
    <div class="field">
        <label for="role">Role</label>
        <select id="role" name="role">
            <option value="">All roles</option>
            <?php foreach (App\Models\UserModel::ROLES as $role): ?>
                <option value="<?= $role ?>" <?= $filters['role'] === $role ? 'selected' : '' ?>><?= ucfirst($role) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All statuses</option>
            <?php foreach (App\Models\UserModel::STATUSES as $status): ?>
                <option value="<?= $status ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="filters__actions">
        <button type="submit" class="button"><?= icon('filter') ?> Filter</button>
        <a href="<?= site_url('admin/users') ?>" class="button button--ghost">Clear</a>
    </div>
</form>

<section class="card">
    <?php if ($users === []): ?>
        <div class="empty-state">
            <span class="empty-state__icon"><?= icon('search') ?></span>
            <h2>No users found</h2>
            <p>No accounts match these filters. Try a different search or clear the filters.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">User</th>
                    <th scope="col">Role</th>
                    <th scope="col">Status</th>
                    <th scope="col">Last sign-in</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <span class="person">
                                <?= avatar($user['name']) ?>
                                <span class="person__text">
                                    <span class="person__name">
                                        <?= esc($user['name']) ?>
                                        <?php if ($user['id'] === $currentUserId): ?><span class="muted small">(you)</span><?php endif ?>
                                    </span>
                                    <span class="person__meta"><?= esc($user['email']) ?></span>
                                </span>
                            </span>
                        </td>
                        <td><span class="badge badge--<?= esc($user['role'], 'attr') ?>"><?= esc(ucfirst($user['role'])) ?></span></td>
                        <td>
                            <span class="status status--<?= esc($user['status'], 'attr') ?>"><?= esc(ucfirst($user['status'])) ?></span>
                            <?php if ($user['must_change_password']): ?>
                                <div class="muted small">Temporary password</div>
                            <?php endif ?>
                        </td>
                        <td class="nowrap"><?= $user['last_login_at'] !== null ? esc(format_datetime($user['last_login_at'])) : '<span class="muted">Never</span>' ?></td>
                        <td class="actions">
                            <a href="<?= site_url("admin/users/{$user['id']}/edit") ?>" data-modal class="link-button"><?= icon('pencil') ?> Edit</a>
                            <?php if ($user['id'] !== $currentUserId): ?>
                                <a href="<?= site_url("admin/users/{$user['id']}/password") ?>" data-modal class="link-button"><?= icon('key') ?> Reset password</a>
                                <form method="post" action="<?= site_url("admin/users/{$user['id']}/status") ?>" class="inline-form"
                                      <?= $user['status'] === 'active' ? 'data-confirm="Deactivate ' . esc($user['name'], 'attr') . '? They will be signed out and unable to sign in."' : '' ?>>
                                    <?= csrf_field() ?>
                                    <button type="submit" class="link-button <?= $user['status'] === 'active' ? 'link-button--danger' : '' ?>">
                                        <?= $user['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?= $pager->only(['q', 'role', 'status'])->links() ?>
    <?php endif ?>
</section>
<?php $this->endSection() ?>
