<?php
/**
 * Compact rubric (criteria and points) with the full rubric in a modal.
 *
 * @var list<array<string, mixed>> $criteria As returned by RubricModel::criteriaWithLevels().
 */
?>
<?php if ($criteria === []): ?>
    <p class="muted">This rubric has no criteria.</p>
<?php else: ?>
    <ul class="rubric-summary">
        <?php foreach ($criteria as $i => $criterion): ?>
            <li>
                <span><?= ($i + 1) . '. ' . esc($criterion['title']) ?></span>
                <span class="points"><?= esc($criterion['max_points']) ?> pts</span>
            </li>
        <?php endforeach ?>
    </ul>
    <p class="rubric__total"><span>Total</span> <span><?= esc(App\Models\RubricModel::totalPoints($criteria)) ?> points</span></p>

    <button type="button" class="button button--block" style="margin-top: 1rem" data-open-dialog="rubric-modal">
        <?= icon('rubrics') ?> View full rubric
    </button>

    <dialog class="modal modal--form" id="rubric-modal" aria-labelledby="rubric-modal-title">
        <div class="modal__frame">
            <div class="modal__header">
                <h2 class="modal__title" id="rubric-modal-title">Rubric and performance levels</h2>
                <button type="button" class="icon-button" data-close-modal aria-label="Close"><?= icon('x') ?></button>
            </div>
            <div class="modal__content">
                <?= $this->include('partials/rubric_table') ?>
            </div>
        </div>
    </dialog>
<?php endif ?>
