<?php
/**
 * Main layout for signed-in pages: sidebar navigation, top bar, and content.
 *
 * @var CodeIgniter\View\View $this
 */
$user = service('auth')->user();

// Sidebar items per role: [path, label, icon, page patterns that belong to this section].
// Patterns use * as a wildcard. When several items match, the most specific pattern wins.
$navByRole = [
    'admin' => [
        ['admin', 'Dashboard', 'dashboard', ['admin']],
        ['admin/users', 'Users', 'users', ['admin/users*']],
        ['admin/audit-logs', 'Audit log', 'history', ['admin/audit-logs*']],
        ['admin/emails', 'Email outbox', 'mail', ['admin/emails*']],
        ['admin/settings', 'Settings', 'settings', ['admin/settings*']],
    ],
    'teacher' => [
        ['teacher', 'Dashboard', 'dashboard', ['teacher']],
        ['teacher/classes', 'Classes', 'classes', ['teacher/classes*', 'teacher/assignments/*', 'teacher/submissions/*']],
        ['teacher/students', 'Students', 'users', ['teacher/students*']],
        ['teacher/rubrics', 'Rubrics', 'rubrics', ['teacher/rubrics*']],
        ['teacher/insights', 'Insights', 'bar-chart', ['teacher/insights*', 'teacher/assignments/*/insights', 'teacher/lesson-plans/*']],
    ],
    'student' => [
        ['student', 'My assignments', 'book-open', ['student', 'student/assignments/*']],
        ['student/classes', 'My classes', 'classes', ['student/classes*']],
    ],
];

$roleLabels  = ['admin' => 'Administrator', 'teacher' => 'Teacher', 'student' => 'Student'];
$currentPath = trim(service('request')->getPath(), '/');

$activePath = null;

if ($user !== null) {
    $bestLength = -1;

    foreach ($navByRole[$user['role']] as [$path, , , $patterns]) {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $currentPath) && strlen($pattern) > $bestLength) {
                [$activePath, $bestLength] = [$path, strlen($pattern)];
            }
        }
    }
}

$isActive = static fn (string $path): bool => $path === $activePath;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->renderSection('title') ?> · RubricMind</title>
    <?= $this->include('partials/head_assets') ?>
</head>
<body class="app">
<a class="skip-link" href="#main">Skip to content</a>

<div class="shell">
    <aside class="sidebar" id="sidebar" aria-label="Sidebar">
        <div class="sidebar__brand">
            <a class="brand" href="<?= site_url('/') ?>">
                <?= brand_logo() ?>
                <span class="brand__name">Rubric<span>Mind</span></span>
            </a>
            <button type="button" class="icon-button sidebar__close" data-sidebar-close aria-label="Close menu">
                <?= icon('x') ?>
            </button>
        </div>
        <?php if (($schoolName = service('systemSettings')->schoolName()) !== ''): ?>
            <span class="sidebar__school"><?= esc($schoolName) ?></span>
        <?php endif ?>

        <?php if ($user !== null): ?>
            <nav class="sidebar__nav" aria-label="Main">
                <p class="sidebar__heading">Menu</p>
                <?php foreach ($navByRole[$user['role']] as [$path, $label, $iconName]): ?>
                    <a href="<?= site_url($path) ?>" class="sidebar__link" <?= $isActive($path) ? 'aria-current="page"' : '' ?>>
                        <?= icon($iconName) ?>
                        <span><?= esc($label) ?></span>
                    </a>
                <?php endforeach ?>

                <p class="sidebar__heading">Account</p>
                <a href="<?= site_url('account/password') ?>" data-modal class="sidebar__link" <?= $currentPath === 'account/password' ? 'aria-current="page"' : '' ?>>
                    <?= icon('key') ?>
                    <span>Change password</span>
                </a>
            </nav>
        <?php endif ?>
    </aside>
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <div class="shell__main">
        <header class="topbar">
            <button type="button" class="icon-button topbar__menu" data-sidebar-open aria-controls="sidebar" aria-expanded="false" aria-label="Open menu">
                <?= icon('menu') ?>
            </button>
            <p class="topbar__date"><?= icon('calendar') ?> <?= esc(date('l, F j, Y')) ?></p>

            <?php if ($user !== null): ?>
                <details class="profile-menu">
                    <summary>
                        <?= avatar($user['name'], 'sm') ?>
                        <span class="profile-menu__name"><?= esc($user['name']) ?></span>
                        <?= icon('chevron-down', 'profile-menu__chevron') ?>
                    </summary>
                    <div class="profile-menu__panel">
                        <div class="profile-menu__header">
                            <strong><?= esc($user['name']) ?></strong>
                            <span><?= esc($user['email']) ?></span>
                            <span class="badge badge--<?= esc($user['role'], 'attr') ?>"><?= esc($roleLabels[$user['role']]) ?></span>
                        </div>
                        <a href="<?= site_url('account/password') ?>" data-modal><?= icon('key') ?> Change password</a>
                        <form method="post" action="<?= site_url('logout') ?>">
                            <?= csrf_field() ?>
                            <button type="submit"><?= icon('log-out') ?> Sign out</button>
                        </form>
                    </div>
                </details>
            <?php endif ?>
        </header>

        <main id="main" class="content">
            <?= $this->include('partials/flash') ?>
            <?= $this->renderSection('content') ?>
        </main>
    </div>
</div>

<!-- Shared modal: forms opened from links marked data-modal are loaded here. -->
<dialog class="modal modal--form" id="app-modal">
    <div class="modal__frame" data-modal-body></div>
</dialog>

</body>
</html>
