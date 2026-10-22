<?php
/**
 * Read-only display of a rubric's criteria and performance levels.
 *
 * @var list<array<string, mixed>> $criteria As returned by RubricModel::criteriaWithLevels().
 */
?>
<?php if ($criteria === []): ?>
    <p class="muted">This rubric has no criteria.</p>
<?php else: ?>
    <div class="rubric">
        <?php foreach ($criteria as $i => $criterion): ?>
            <div class="rubric__criterion">
                <div class="rubric__header">
                    <h3><?= ($i + 1) . '. ' . esc($criterion['title']) ?></h3>
                    <span class="points"><?= esc($criterion['max_points']) ?> pts</span>
                </div>
                <?php if (! empty($criterion['description'])): ?>
                    <p class="muted"><?= esc($criterion['description']) ?></p>
                <?php endif ?>

                <?php if ($criterion['levels'] !== []): ?>
                    <ul class="rubric__levels">
                        <?php foreach ($criterion['levels'] as $level): ?>
                            <li>
                                <span class="rubric__level-head">
                                    <strong><?= esc($level['label']) ?></strong>
                                    <span class="muted small"><?= esc($level['points']) ?> pts</span>
                                </span>
                                <?php if (! empty($level['description'])): ?>
                                    <span class="muted"><?= esc($level['description']) ?></span>
                                <?php endif ?>
                            </li>
                        <?php endforeach ?>
                    </ul>
                <?php else: ?>
                    <p class="muted small">No performance levels. Scored from 0 to <?= esc($criterion['max_points']) ?> points.</p>
                <?php endif ?>
            </div>
        <?php endforeach ?>
        <p class="rubric__total"><span>Total</span> <span><?= esc(App\Models\RubricModel::totalPoints($criteria)) ?> points</span></p>
    </div>
<?php endif ?>
