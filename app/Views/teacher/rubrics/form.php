<?php
/**
 * Rubric editor: title, criteria, and performance levels.
 * Criteria and levels are added and removed in the browser by rubric-editor.js.
 *
 * @var CodeIgniter\View\View       $this
 * @var array<string, mixed>|null   $rubric    Null when creating.
 * @var array<string, mixed>        $old       Submitted title/description after a failed save.
 * @var list<array<string, mixed>>  $criteria  Criteria with 'levels', from the database or the failed form.
 * @var list<string>                $errors
 */
$isNew  = $rubric === null;
$old  ??= [];
$title       = (string) ($old['title'] ?? $rubric['title'] ?? '');
$description = (string) ($old['description'] ?? $rubric['description'] ?? '');
$action      = $isNew ? site_url('teacher/rubrics') : site_url("teacher/rubrics/{$rubric['id']}");
$cancel      = $isNew ? site_url('teacher/rubrics') : site_url("teacher/rubrics/{$rubric['id']}");

$defaultLevels = [
    ['label' => 'Excellent', 'description' => null, 'points' => ''],
    ['label' => 'Proficient', 'description' => null, 'points' => ''],
    ['label' => 'Developing', 'description' => null, 'points' => ''],
    ['label' => 'Beginning', 'description' => null, 'points' => ''],
];

$renderLevel = static function (string $cKey, string $lKey, array $level): void {
    $name = "criteria[{$cKey}][levels][{$lKey}]";
    $id   = "level-{$cKey}-{$lKey}";
    ?>
    <div class="level" data-level>
        <div class="field level__label">
            <label for="<?= $id ?>-label">Level</label>
            <input type="text" id="<?= $id ?>-label" name="<?= $name ?>[label]" value="<?= esc((string) $level['label'], 'attr') ?>"
                   maxlength="100" placeholder="e.g. Excellent" required>
        </div>
        <div class="field level__points">
            <label for="<?= $id ?>-points">Points</label>
            <input type="number" id="<?= $id ?>-points" name="<?= $name ?>[points]" value="<?= esc((string) $level['points'], 'attr') ?>"
                   min="0" max="1000" step="1" required>
        </div>
        <div class="field level__description">
            <label for="<?= $id ?>-description">What this level looks like <span class="optional">(optional)</span></label>
            <input type="text" id="<?= $id ?>-description" name="<?= $name ?>[description]"
                   value="<?= esc((string) ($level['description'] ?? ''), 'attr') ?>" maxlength="1000">
        </div>
        <button type="button" class="link-button link-button--danger level__remove" data-remove-level>Remove level</button>
    </div>
    <?php
};

$renderCriterion = static function (string $cKey, array $criterion, string $number) use ($renderLevel): void {
    $name = "criteria[{$cKey}]";
    $id   = "criterion-{$cKey}";
    ?>
    <fieldset class="criterion" data-criterion data-key="<?= $cKey ?>">
        <legend>Criterion <span data-number><?= $number ?></span></legend>

        <div class="field-row field-row--criterion">
            <div class="field">
                <label for="<?= $id ?>-title">Title</label>
                <input type="text" id="<?= $id ?>-title" name="<?= $name ?>[title]" value="<?= esc((string) $criterion['title'], 'attr') ?>"
                       maxlength="150" placeholder="e.g. Thesis statement" required>
            </div>
            <div class="field field--points">
                <label for="<?= $id ?>-max">Max points</label>
                <input type="number" id="<?= $id ?>-max" name="<?= $name ?>[max_points]" value="<?= esc((string) $criterion['max_points'], 'attr') ?>"
                       min="1" max="1000" step="1" required data-max-points>
            </div>
        </div>

        <div class="field">
            <label for="<?= $id ?>-description">Description <span class="optional">(optional)</span></label>
            <textarea id="<?= $id ?>-description" name="<?= $name ?>[description]" rows="2" maxlength="1000"><?= esc((string) ($criterion['description'] ?? '')) ?></textarea>
        </div>

        <div class="levels" data-levels>
            <?php foreach (array_values($criterion['levels']) as $j => $level): ?>
                <?php $renderLevel($cKey, 'l' . $j, $level) ?>
            <?php endforeach ?>
        </div>

        <div class="criterion__actions">
            <button type="button" class="button button--small" data-add-level><?= icon('plus') ?> Add level</button>
            <button type="button" class="link-button link-button--danger" data-remove-criterion>Remove criterion</button>
        </div>
    </fieldset>
    <?php
};
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?><?= $isNew ? 'New rubric' : 'Edit rubric' ?><?php $this->endSection() ?>

<?php $this->section('content') ?>
<p class="breadcrumb"><a href="<?= site_url('teacher/rubrics') ?>">Rubrics</a> <?= icon('chevron-right') ?></p>

<div class="page-header">
    <div>
        <h1><?= $isNew ? 'New rubric' : 'Edit ' . esc($rubric['title']) ?></h1>
        <p>Define what you grade on and how many points each performance level earns.</p>
    </div>
</div>

<?= $this->include('partials/errors') ?>

<form method="post" action="<?= $action ?>" class="form rubric-form" data-rubric-editor>
    <?= csrf_field() ?>

    <section class="card">
        <div class="field">
            <label for="title">Rubric title</label>
            <input type="text" id="title" name="title" value="<?= esc($title, 'attr') ?>" maxlength="150"
                   placeholder="e.g. Argumentative Essay Rubric" required>
        </div>
        <div class="field">
            <label for="description">Description <span class="optional">(optional)</span></label>
            <textarea id="description" name="description" rows="2" maxlength="2000"><?= esc($description) ?></textarea>
        </div>
    </section>

    <section class="card">
        <div class="section-header">
            <div>
                <h2>Criteria</h2>
                <p class="hint">Each criterion has a maximum score. Performance levels are optional; each level needs a different point value that is not more than the maximum.</p>
            </div>
            <span class="total-pill" aria-live="polite">Total: <strong data-total-points>0</strong> points</span>
        </div>

        <div class="criteria" data-criteria>
            <?php foreach (array_values($criteria) as $i => $criterion): ?>
                <?php $renderCriterion('c' . $i, $criterion, (string) ($i + 1)) ?>
            <?php endforeach ?>
        </div>

        <button type="button" class="button" data-add-criterion><?= icon('plus') ?> Add criterion</button>
    </section>

    <div class="form-actions">
        <button type="submit" class="button button--primary"><?= $isNew ? 'Create rubric' : 'Save rubric' ?></button>
        <a href="<?= $cancel ?>" class="button">Cancel</a>
    </div>
</form>

<template id="criterion-template">
    <?php $renderCriterion('__C__', ['title' => '', 'description' => null, 'max_points' => '', 'levels' => $defaultLevels], '') ?>
</template>
<template id="level-template">
    <?php $renderLevel('__C__', '__L__', ['label' => '', 'description' => null, 'points' => '']) ?>
</template>

<script src="<?= base_url('assets/js/rubric-editor.js') ?>" defer></script>
<?php $this->endSection() ?>
