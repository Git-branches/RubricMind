<?php
/**
 * Layout for pages shown before sign-in: a brand panel beside the form.
 *
 * @var CodeIgniter\View\View $this
 */
$highlights = [
    ['sparkles', 'Smart rubric mapping', 'Evidence from each essay is linked to the rubric criterion it supports.'],
    ['refresh', 'Consistent feedback', 'Balanced, encouraging feedback from the first paper to the hundredth.'],
    ['bar-chart', 'Classroom insights', 'See where the whole class is strong and where it needs support.'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->renderSection('title') ?> · RubricMind</title>
    <?= $this->include('partials/head_assets') ?>
</head>
<body class="guest">
<div class="guest__layout">
    <aside class="guest__panel">
        <div class="guest__hero">
            <div class="guest__pitch">
                <h2>Grade essays with <span>clarity</span> and <span>consistency</span>.</h2>
                <p>RubricMind helps teachers evaluate student writing with structured rubrics and AI-assisted suggestions, while keeping every final grade in the teacher's hands.</p>

                <ul class="guest__highlights">
                    <?php foreach ($highlights as [$iconName, $title, $text]): ?>
                        <li>
                            <span class="guest__highlight-icon"><?= icon($iconName) ?></span>
                            <span><strong><?= esc($title) ?></strong><?= esc($text) ?></span>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>

            <div class="guest__art" aria-hidden="true">
                <img src="<?= base_url('assets/img/logo-3d.jpg') ?>" alt="" width="280" height="280">
            </div>
        </div>

        <p class="guest__motto">
            Empowering teachers, one rubric at a time.
            <svg viewBox="0 0 200 12" aria-hidden="true"><path d="M2 9 C 50 2, 110 2, 198 6" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
        </p>
    </aside>

    <main id="main" class="guest__main">
        <?php // Not needed on the registration page itself. ?>
        <p class="guest__help-link" <?= trim(service('request')->getPath(), '/') === 'register' ? 'hidden' : '' ?>>
            New here?
            <?php if (service('systemSettings')->registrationEnabled()): ?>
                <a href="<?= site_url('register') ?>">Create a student account <?= icon('arrow-right') ?></a>
            <?php else: ?>
                <button type="button" class="link-button" data-alert-title="Need an account?"
                        data-alert-text="Accounts are created by your school's administrator or by your teacher. Ask them to add you, and they will give you a temporary password.">
                    Contact your administrator <?= icon('arrow-right') ?>
                </button>
            <?php endif ?>
        </p>

        <div class="guest__form">
            <?= $this->include('partials/flash') ?>
            <?= $this->renderSection('content') ?>
        </div>

        <p class="guest__trust">
            <span><?= icon('shield-check') ?> Your data is safe and secure</span>
            <span><?= icon('graduation') ?> Built for better education</span>
        </p>
    </main>
</div>
</body>
</html>
