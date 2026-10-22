<?php
/**
 * Criterion-by-criterion scores of a graded submission.
 *
 * @var list<array<string, mixed>> $criteria
 * @var array<int, array>          $scores        Keyed by criterion ID.
 * @var bool                       $showComments
 */
?>
<ul class="score-breakdown">
    <?php foreach ($criteria as $criterion): ?>
        <?php
        $score = $scores[(int) $criterion['id']] ?? null;
        $level = $score === null || $score['level_id'] === null ? null
            : (array_values(array_filter($criterion['levels'], static fn (array $l) => (int) $l['id'] === $score['level_id']))[0] ?? null);
        ?>
        <li>
            <div class="score-breakdown__head">
                <span>
                    <strong><?= esc($criterion['title']) ?></strong>
                    <?php if ($level !== null): ?><span class="muted small">· <?= esc($level['label']) ?></span><?php endif ?>
                </span>
                <span class="score-breakdown__points"><?= $score['points'] ?? '—' ?> / <?= esc($criterion['max_points']) ?></span>
            </div>
            <?php if (! empty($showComments) && ! empty($score['comment'])): ?>
                <p class="score-breakdown__comment"><?= esc($score['comment']) ?></p>
            <?php endif ?>
        </li>
    <?php endforeach ?>
</ul>
