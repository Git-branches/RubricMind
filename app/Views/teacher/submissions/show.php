<?php
/**
 * Essay view with AI suggestions and the grading panel.
 *
 * @var CodeIgniter\View\View       $this
 * @var array<string, mixed>        $submission
 * @var array<string, mixed>        $assignment
 * @var array<string, mixed>        $class
 * @var array<string, mixed>        $student
 * @var list<array<string, mixed>>  $criteria
 * @var list<array<string, mixed>>  $revisions
 * @var array<string, mixed>|null   $evaluation  Null until grading starts.
 * @var array<int, array>           $scores      Saved criterion scores keyed by criterion ID.
 * @var bool                        $editable    Scores can be edited now.
 * @var int|null                    $nextId      Next submission still to grade.
 * @var array<string, mixed>        $failed      Posted values after a failed save.
 * @var list<string>                $errors
 * @var bool                        $aiEnabled
 * @var bool                        $aiSimulated
 * @var array<string, mixed>|null   $aiRun       The latest AI run, if any.
 * @var array<string, mixed>|null   $ai          Results of the latest run when it succeeded.
 * @var array<string, mixed>        $style       StyleTracker::compare() result.
 * @var array<string, mixed>|null   $styleReview The teacher's review of the style comparison.
 */
$status   = $submission['status'];
$isFinal  = in_array($status, ['finalized', 'released'], true);
$maxScore = App\Models\RubricModel::totalPoints($criteria);
$canAskAi = $aiEnabled && ($evaluation === null || $editable);

// Criterion ID => its number in the rubric, used to label highlights.
$numbers = [];

foreach ($criteria as $i => $criterion) {
    $numbers[(int) $criterion['id']] = $i + 1;
}

// Label results by the AI that produced them, not the one selected now.
$resultsSimulated = $aiRun !== null ? $aiRun['provider'] === 'demo' : $aiSimulated;

$suggestions = $ai['suggestions'] ?? [];
$highlights  = $ai['highlights'] ?? [];
$hasMarks    = array_filter($highlights, static fn (array $h) => $h['verified']) !== [];

// Value shown for a criterion: the failed post if any, otherwise the saved score.
$pointsFor = static function (int $criterionId) use ($failed, $scores): ?int {
    if (isset($failed['posted'])) {
        return $failed['posted'][$criterionId]['points'] ?? null;
    }

    return $scores[$criterionId]['points'] ?? null;
};
$commentFor = static fn (int $criterionId): string => (string) (isset($failed['posted'])
    ? ($failed['posted'][$criterionId]['comment'] ?? '')
    : ($scores[$criterionId]['comment'] ?? ''));

$levelLabel = static function (array $criterion, ?int $levelId): ?string {
    foreach ($criterion['levels'] as $level) {
        if ((int) $level['id'] === $levelId) {
            return $level['label'];
        }
    }

    return null;
};

// Suggested feedback as plain text the teacher can copy into the feedback box.
$suggestedFeedback = '';

if (! empty($ai['feedback'])) {
    $parts = [$ai['feedback']['summary']];

    if ($ai['feedback']['strengths'] !== []) {
        $parts[] = "What you did well:\n- " . implode("\n- ", $ai['feedback']['strengths']);
    }

    if ($ai['feedback']['improvements'] !== []) {
        $parts[] = "Next steps:\n- " . implode("\n- ", $ai['feedback']['improvements']);
    }

    $suggestedFeedback = trim(implode("\n\n", array_filter($parts)));
}
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?><?= esc($student['name']) ?> · <?= esc($assignment['title']) ?><?php $this->endSection() ?>

<?php $this->section('content') ?>
<p class="breadcrumb">
    <a href="<?= site_url("teacher/classes/{$class['id']}") ?>"><?= esc($class['name']) ?></a> <?= icon('chevron-right') ?>
    <a href="<?= site_url("teacher/assignments/{$assignment['id']}") ?>"><?= esc($assignment['title']) ?></a> <?= icon('chevron-right') ?>
</p>

<div class="page-header">
    <div class="person">
        <?= avatar($student['name']) ?>
        <span class="person__text">
            <h1 style="font-size: 1.4rem"><?= esc($student['name']) ?></h1>
            <span class="person__meta"><?= esc($student['email']) ?></span>
        </span>
    </div>
    <div class="button-row">
        <span class="tag <?= $status === 'released' ? 'tag--published' : ($isFinal ? 'tag--locked' : 'tag--info') ?>"><?= esc(submission_status_label($status)) ?></span>
        <?php if ($submission['is_late']): ?><span class="tag tag--late">Late</span><?php endif ?>
        <?php if ($nextId !== null): ?>
            <a href="<?= site_url("teacher/submissions/{$nextId}") ?>" class="button button--small">Next to grade <?= icon('arrow-right') ?></a>
        <?php endif ?>
        <?php if ($editable): ?>
            <button type="button" class="button button--primary button--small" data-open-dialog="grading-modal"><?= icon('pencil') ?> Open grading</button>
        <?php endif ?>
    </div>
</div>

<div class="two-column">
    <div>
        <section class="card">
            <div class="section-header">
                <h2>Essay</h2>
                <div class="button-row">
                    <?php if ($hasMarks): ?>
                        <label class="toggle small">
                            <input type="checkbox" checked data-toggle-highlights> Show evidence highlights
                        </label>
                    <?php endif ?>
                    <span class="word-count"><?= icon('file-text') ?> <?= esc($submission['word_count']) ?> words</span>
                </div>
            </div>
            <?php if ($hasMarks): ?>
                <p class="hint" style="margin-bottom: 0.75rem">
                    Highlighted sentences are AI-suggested evidence. The small number shows which rubric criterion each one supports.
                </p>
                <div class="essay__text prewrap" data-highlights><?= highlight_essay($submission['content'], $highlights, $numbers) ?></div>
            <?php else: ?>
                <div class="essay__text prewrap"><?= esc($submission['content']) ?></div>
            <?php endif ?>
        </section>

        <?php if ($ai !== null): ?>
            <section class="card">
                <div class="section-header">
                    <div>
                        <h2>Writing mechanics summary</h2>
                        <p class="muted small">Recurring patterns, not every individual error. These are suggestions and may need review.</p>
                    </div>
                    <?php if ($resultsSimulated): ?><span class="tag tag--draft">Demo AI (simulated)</span><?php endif ?>
                </div>
                <?php if ($ai['patterns'] === []): ?>
                    <p class="muted">No recurring mechanics patterns were found.</p>
                <?php else: ?>
                    <ul class="mechanics">
                        <?php foreach ($ai['patterns'] as $pattern): ?>
                            <li>
                                <div class="mechanics__head">
                                    <strong><?= esc($pattern['pattern']) ?></strong>
                                    <span class="tag"><?= esc($pattern['occurrences']) ?>×</span>
                                </div>
                                <?php if (! empty($pattern['description'])): ?><p class="muted small"><?= esc($pattern['description']) ?></p><?php endif ?>
                                <?php if ($pattern['examples'] !== []): ?>
                                    <ul class="mechanics__examples">
                                        <?php foreach ($pattern['examples'] as $example): ?>
                                            <li>“<?= esc($example) ?>”</li>
                                        <?php endforeach ?>
                                    </ul>
                                <?php endif ?>
                                <?php if (! empty($pattern['suggestion'])): ?><p class="small"><?= icon('sparkles') ?> <?= esc($pattern['suggestion']) ?></p><?php endif ?>
                            </li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
            </section>

            <?php if ($highlights !== []): ?>
                <section class="card">
                    <h2>Evidence by criterion</h2>
                    <ul class="evidence-list">
                        <?php foreach ($highlights as $highlight): ?>
                            <?php $number = $numbers[$highlight['criterion_id']] ?? 0 ?>
                            <li>
                                <span class="evidence-list__number evidence--<?= $number % 6 ?>"><?= $number ?></span>
                                <div>
                                    <?php if ($highlight['verified']): ?>
                                        <a href="#evidence-<?= $highlight['id'] ?>" class="evidence-list__quote">“<?= esc($highlight['quote']) ?>”</a>
                                    <?php else: ?>
                                        <span class="evidence-list__quote">“<?= esc($highlight['quote']) ?>”</span>
                                        <span class="tag tag--late">Unverified: not found in the essay</span>
                                    <?php endif ?>
                                    <?php if (! empty($highlight['note'])): ?><p class="muted small"><?= esc($highlight['note']) ?></p><?php endif ?>
                                </div>
                            </li>
                        <?php endforeach ?>
                    </ul>
                </section>
            <?php endif ?>
        <?php endif ?>

        <section class="card style-card" id="style">
            <div class="section-header">
                <div>
                    <h2><?= icon('bar-chart') ?> Style Growth Tracker</h2>
                    <p class="muted small">Compares this essay with <?= esc($student['name']) ?>'s earlier graded essays.</p>
                </div>
                <?php if ($style['status'] === 'notable_change'): ?>
                    <span class="tag tag--draft"><?= icon('alert-triangle') ?> Notable style change</span>
                <?php elseif ($style['status'] === 'consistent'): ?>
                    <span class="tag tag--published">Consistent with earlier work</span>
                <?php else: ?>
                    <span class="tag tag--closed">Insufficient history</span>
                <?php endif ?>
            </div>

            <?php if ($style['status'] === 'insufficient_history'): ?>
                <p class="muted">Not enough earlier writing to compare yet. The tracker needs at least
                    <?= App\Libraries\StyleTracker::MIN_HISTORY ?> earlier graded essays of
                    <?= App\Libraries\StyleTracker::MIN_HISTORY_WORDS ?>+ words; this student has <?= esc($style['history_count']) ?>.</p>
            <?php else: ?>
                <div class="alert alert--warning" role="note">
                    <?= icon('alert-triangle', 'alert__icon') ?>
                    <div>A style difference is an <strong>uncertain signal</strong>, not proof of AI use or plagiarism.
                        Students' writing changes as they grow. Use this only as a reason to look more closely or talk with the student.
                        It never affects the grade.</div>
                </div>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th scope="col">Measure</th>
                            <th scope="col">Usual (<?= esc($style['history_count']) ?> essays)</th>
                            <th scope="col">This essay</th>
                            <th scope="col">Change</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($style['metrics'] as $metric): ?>
                            <tr>
                                <td>
                                    <strong><?= esc($metric['label']) ?></strong>
                                    <div class="muted small"><?= esc($metric['explanation']) ?></div>
                                </td>
                                <td class="nowrap"><?= esc($metric['usual']) ?> <span class="muted small"><?= esc($metric['unit']) ?></span></td>
                                <td class="nowrap"><?= esc($metric['current']) ?></td>
                                <td class="nowrap">
                                    <?php if ($metric['notable']): ?>
                                        <span class="tag tag--draft"><?= $metric['change'] > 0 ? '+' : '' ?><?= esc($metric['change']) ?>% <?= esc($metric['direction']) ?></span>
                                    <?php else: ?>
                                        <span class="muted"><?= $metric['change'] > 0 ? '+' : '' ?><?= esc($metric['change']) ?>%</span>
                                    <?php endif ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($styleReview !== null): ?>
                    <p class="hint" style="margin-top: 1rem"><?= icon('check-circle') ?> You reviewed this on <?= esc(format_datetime($styleReview['reviewed_at'])) ?>.
                        <?= $styleReview['review_note'] !== null ? 'Note: ' . esc($styleReview['review_note']) : '' ?></p>
                <?php else: ?>
                    <form method="post" action="<?= site_url("teacher/submissions/{$submission['id']}/style-review") ?>" class="action-row"
                          data-prompt="Mark style comparison as reviewed" data-prompt-input="review_note" data-prompt-button="Save review"
                          data-prompt-text="Add an optional private note. It is never shown to the student."
                          data-prompt-placeholder="e.g. Discussed with the student; they explained their drafting process.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="review_note" value="">
                        <button type="submit" class="button button--small"><?= icon('check-circle') ?> Mark as reviewed</button>
                    </form>
                <?php endif ?>
            <?php endif ?>
        </section>
    </div>

    <aside id="grading">
        <?php if ($aiEnabled && ($aiRun !== null || $canAskAi)): ?>
            <section class="card ai-card">
                <div class="section-header">
                    <h2><?= icon('sparkles') ?> AI suggestions</h2>
                    <?php if ($resultsSimulated): ?><span class="tag tag--draft" title="Results are simulated by local rules, not a real AI model.">Demo AI (simulated)</span><?php endif ?>
                </div>

                <?php if ($aiRun === null): ?>
                    <p class="muted small">Get suggested scores, evidence from the essay, draft feedback, and a mechanics summary.
                        You review everything before it counts. Only the essay text and rubric are analyzed, never the student's name.</p>
                <?php elseif ($aiRun['status'] === 'failed'): ?>
                    <div class="alert alert--error" role="alert">
                        <?= icon('alert-circle', 'alert__icon') ?>
                        <div><strong>AI suggestions failed.</strong> <?= esc($aiRun['error']) ?> You can try again or grade manually.</div>
                    </div>
                <?php elseif ($ai !== null): ?>
                    <p class="muted small">Generated <?= esc(format_datetime($aiRun['finished_at'])) ?><?= $aiRun['provider'] !== 'demo' ? ' by ' . esc($aiRun['model']) : '' ?>.
                        Suggestions are advisory: nothing changes until you save.</p>
                    <?php if (! empty($ai['feedback']['summary'])): ?>
                        <div class="ai-feedback">
                            <p><?= esc($ai['feedback']['summary']) ?></p>
                            <?php if ($ai['feedback']['strengths'] !== []): ?>
                                <p class="ai-feedback__label">Strengths</p>
                                <ul><?php foreach ($ai['feedback']['strengths'] as $item): ?><li><?= esc($item) ?></li><?php endforeach ?></ul>
                            <?php endif ?>
                            <?php if ($ai['feedback']['improvements'] !== []): ?>
                                <p class="ai-feedback__label">Next steps</p>
                                <ul><?php foreach ($ai['feedback']['improvements'] as $item): ?><li><?= esc($item) ?></li><?php endforeach ?></ul>
                            <?php endif ?>
                        </div>
                    <?php endif ?>
                <?php endif ?>

                <div class="button-row" style="margin-top: 1rem">
                    <?php if ($ai !== null && $editable): ?>
                        <button type="button" class="button button--small" data-apply-all-scores data-open-dialog="grading-modal"><?= icon('check-circle') ?> Use all suggested scores</button>
                        <?php if ($suggestedFeedback !== ''): ?>
                            <button type="button" class="button button--small" data-apply-feedback="<?= esc($suggestedFeedback, 'attr') ?>" data-open-dialog="grading-modal"><?= icon('pencil') ?> Use as feedback</button>
                        <?php endif ?>
                    <?php endif ?>
                    <?php if ($canAskAi): ?>
                        <form method="post" action="<?= site_url("teacher/submissions/{$submission['id']}/ai") ?>"
                              <?= $evaluation === null ? 'data-confirm="Get AI suggestions? This starts grading, so ' . esc($student['name'], 'attr') . ' will no longer be able to edit this essay."' : '' ?>>
                            <?= csrf_field() ?>
                            <button type="submit" class="button button--small <?= $aiRun === null ? 'button--primary' : '' ?>">
                                <?= icon('sparkles') ?> <?= $aiRun === null ? 'Get AI suggestions' : ($aiRun['status'] === 'failed' ? 'Try again' : 'Run again') ?>
                            </button>
                        </form>
                    <?php endif ?>
                </div>
            </section>
        <?php endif ?>

        <?php if ($evaluation === null): ?>
            <section class="card">
                <div class="empty-state" style="padding: 1rem 0 1.5rem">
                    <span class="empty-state__icon"><?= icon('rubrics') ?></span>
                    <h2>Ready to grade?</h2>
                    <p>Starting grading locks the essay so the student can no longer edit it, and locks the rubric to protect the scores.</p>
                    <form method="post" action="<?= site_url("teacher/submissions/{$submission['id']}/start") ?>"
                          data-confirm="Start grading? <?= esc($student['name'], 'attr') ?> will no longer be able to edit this essay.">
                        <?= csrf_field() ?>
                        <button type="submit" class="button button--primary"><?= icon('pencil') ?> Start grading</button>
                    </form>
                </div>
                <h3>Rubric</h3>
                <?= $this->include('partials/rubric_summary') ?>
            </section>

        <?php elseif ($editable): ?>
            <?php $scoredCount = count(array_filter($scores, static fn (array $s) => $s['points'] !== null)) ?>
            <section class="card grading-summary">
                <div class="section-header">
                    <h2>Grading</h2>
                    <span class="total-pill"><span data-score-total-summary><?= esc($evaluation['total_score']) ?></span> / <?= $maxScore ?> pts</span>
                </div>
                <dl class="info-list">
                    <div><dt>Criteria scored</dt><dd><?= $scoredCount ?> of <?= count($criteria) ?></dd></div>
                    <div><dt>Feedback written</dt><dd><?= empty($evaluation['student_feedback']) ? 'Not yet' : 'Yes' ?></dd></div>
                    <?php if ($evaluation['reviewed_at'] !== null): ?>
                        <div><dt>Last saved</dt><dd><?= esc(format_datetime($evaluation['reviewed_at'])) ?></dd></div>
                    <?php endif ?>
                </dl>
                <button type="button" class="button button--primary button--block" style="margin-top: 1.25rem" data-open-dialog="grading-modal">
                    <?= icon('pencil') ?> <?= $scoredCount === 0 ? 'Open grading' : 'Continue grading' ?>
                </button>
                <p class="hint" style="margin-top: 0.6rem; text-align: center">Scores the essay side by side with the rubric.</p>
            </section>

            <dialog class="modal" id="grading-modal" aria-labelledby="grading-modal-title" <?= $errors !== [] ? 'data-auto-open' : '' ?>>
            <form method="post" action="<?= site_url("teacher/submissions/{$submission['id']}/grade") ?>" class="modal__form grading" data-grading>
                <?= csrf_field() ?>

                <header class="modal__header">
                    <div class="person">
                        <?= avatar($student['name'], 'sm') ?>
                        <span class="person__text">
                            <h2 id="grading-modal-title" style="margin: 0">Grading: <?= esc($student['name']) ?></h2>
                            <span class="person__meta"><?= esc($assignment['title']) ?></span>
                        </span>
                    </div>
                    <div class="button-row">
                        <span class="total-pill" aria-live="polite">
                            <span data-score-total><?= esc($evaluation['total_score']) ?></span> / <?= $maxScore ?> pts
                        </span>
                        <?php if ($ai !== null): ?>
                            <button type="button" class="button button--small" data-apply-all-scores><?= icon('sparkles') ?> Use all AI scores</button>
                        <?php endif ?>
                        <button type="button" class="icon-button" data-close-modal aria-label="Close grading"><?= icon('x') ?></button>
                    </div>
                </header>

                <div class="modal__body">
                    <div class="modal__pane modal__pane--essay">
                        <div class="section-header">
                            <h3 style="margin: 0">Essay</h3>
                            <span class="word-count"><?= icon('file-text') ?> <?= esc($submission['word_count']) ?> words</span>
                        </div>
                        <div class="essay__text prewrap"><?= $hasMarks ? highlight_essay($submission['content'], $highlights, $numbers, false) : esc($submission['content']) ?></div>
                    </div>

                    <div class="modal__pane modal__pane--rubric">
                <?= $this->include('partials/errors') ?>

                <?php foreach ($criteria as $i => $criterion): ?>
                    <?php $cid = (int) $criterion['id']; $current = $pointsFor($cid); $name = "scores[{$cid}]"; $suggestion = $suggestions[$cid] ?? null ?>
                    <fieldset class="score-criterion" data-criterion="<?= $cid ?>">
                        <legend>
                            <span><?= ($i + 1) . '. ' . esc($criterion['title']) ?></span>
                            <span class="points"><?= esc($criterion['max_points']) ?> pts</span>
                        </legend>
                        <?php if (! empty($criterion['description'])): ?>
                            <p class="muted small"><?= esc($criterion['description']) ?></p>
                        <?php endif ?>

                        <?php if ($suggestion !== null): ?>
                            <div class="ai-suggestion">
                                <div class="ai-suggestion__head">
                                    <span><?= icon('sparkles') ?> AI suggests <strong><?= esc($suggestion['points']) ?> pts</strong>
                                        <?php $label = $levelLabel($criterion, $suggestion['level_id']) ?>
                                        <?= $label !== null ? '(' . esc($label) . ')' : '' ?></span>
                                    <button type="button" class="link-button" data-apply-score data-criterion="<?= $cid ?>" data-points="<?= esc($suggestion['points'], 'attr') ?>">Use</button>
                                </div>
                                <?php if (! empty($suggestion['rationale'])): ?><p class="muted small"><?= esc($suggestion['rationale']) ?></p><?php endif ?>
                            </div>
                        <?php endif ?>

                        <?php if ($criterion['levels'] !== []): ?>
                            <div class="level-options">
                                <?php foreach ($criterion['levels'] as $level): ?>
                                    <label class="level-option">
                                        <input type="radio" name="<?= $name ?>[points]" value="<?= esc($level['points'], 'attr') ?>"
                                               data-score <?= $current === $level['points'] ? 'checked' : '' ?>>
                                        <span class="level-option__body">
                                            <span class="level-option__head">
                                                <strong><?= esc($level['label']) ?></strong>
                                                <span><?= esc($level['points']) ?> pts</span>
                                            </span>
                                            <?php if (! empty($level['description'])): ?>
                                                <span class="muted small"><?= esc($level['description']) ?></span>
                                            <?php endif ?>
                                        </span>
                                    </label>
                                <?php endforeach ?>
                                <label class="level-option level-option--none">
                                    <input type="radio" name="<?= $name ?>[points]" value="" data-score <?= $current === null ? 'checked' : '' ?>>
                                    <span class="level-option__body"><span class="muted">Not scored yet</span></span>
                                </label>
                            </div>
                        <?php else: ?>
                            <div class="field field--inline">
                                <label for="score-<?= $cid ?>">Score</label>
                                <input type="number" id="score-<?= $cid ?>" name="<?= $name ?>[points]" min="0" max="<?= esc($criterion['max_points'], 'attr') ?>"
                                       step="1" value="<?= $current === null ? '' : esc($current, 'attr') ?>" data-score>
                                <span class="muted small">out of <?= esc($criterion['max_points']) ?></span>
                            </div>
                        <?php endif ?>

                        <div class="field">
                            <label for="comment-<?= $cid ?>" class="small">Comment for the student <span class="optional">(optional)</span></label>
                            <textarea id="comment-<?= $cid ?>" name="<?= $name ?>[comment]" rows="2" maxlength="2000"><?= esc($commentFor($cid)) ?></textarea>
                        </div>
                    </fieldset>
                <?php endforeach ?>

                <div class="field">
                    <label for="student_feedback">Overall feedback for the student</label>
                    <textarea id="student_feedback" name="student_feedback" rows="6" maxlength="10000"
                              placeholder="Start with what the student did well, then give one or two clear next steps."><?= esc($failed['feedback'] ?? ($evaluation['student_feedback'] ?? '')) ?></textarea>
                </div>

                <div class="field">
                    <label for="teacher_notes"><?= icon('lock') ?> Private notes <span class="optional">(never shown to the student)</span></label>
                    <textarea id="teacher_notes" name="teacher_notes" rows="3" maxlength="10000"><?= esc($failed['notes'] ?? ($evaluation['teacher_notes'] ?? '')) ?></textarea>
                </div>

                    </div>
                </div>

                <footer class="modal__footer">
                    <span class="muted small" data-dirty-note hidden><?= icon('alert-circle') ?> You have unsaved changes.</span>
                    <div class="button-row" style="margin-left: auto">
                        <button type="button" class="button button--ghost" data-close-modal>Close</button>
                        <button type="submit" name="intent" value="save" class="button"><?= icon('check-circle') ?> Save</button>
                        <button type="submit" name="intent" value="finalize" class="button button--primary"
                                data-confirm="Finalize this grade? You can still reopen it later, but the change will be recorded.">
                            <?= icon('lock') ?> Save and finalize
                        </button>
                    </div>
                </footer>
            </form>
            </dialog>

        <?php else: ?>
            <section class="card">
                <div class="section-header">
                    <h2>Final grade</h2>
                    <span class="tag <?= $status === 'released' ? 'tag--published' : 'tag--locked' ?>">
                        <?= $status === 'released' ? 'Visible to student' : 'Not yet released' ?>
                    </span>
                </div>

                <div class="score-summary">
                    <span class="score-summary__value"><?= esc($evaluation['total_score']) ?></span>
                    <span class="score-summary__max">/ <?= esc($evaluation['max_score']) ?> points</span>
                    <span class="score-summary__percent">
                        <?= $evaluation['max_score'] > 0 ? round($evaluation['total_score'] / $evaluation['max_score'] * 100) : 0 ?>%
                    </span>
                </div>

                <?php $this->setVar('showComments', true) ?>
                <?= $this->include('partials/score_breakdown') ?>

                <?php if (! empty($evaluation['student_feedback'])): ?>
                    <h3>Feedback for the student</h3>
                    <p class="prewrap"><?= esc($evaluation['student_feedback']) ?></p>
                <?php endif ?>

                <?php if (! empty($evaluation['teacher_notes'])): ?>
                    <h3><?= icon('lock') ?> Private notes</h3>
                    <p class="prewrap muted"><?= esc($evaluation['teacher_notes']) ?></p>
                <?php endif ?>

                <dl class="info-list" style="margin-top: 1rem">
                    <div><dt>Finalized</dt><dd><?= esc(format_datetime($evaluation['finalized_at'])) ?></dd></div>
                    <?php if ($evaluation['released_at'] !== null): ?>
                        <div><dt>Released</dt><dd><?= esc(format_datetime($evaluation['released_at'])) ?></dd></div>
                    <?php endif ?>
                </dl>

                <div class="form-actions">
                    <?php if ($status === 'finalized'): ?>
                        <form method="post" action="<?= site_url("teacher/submissions/{$submission['id']}/release") ?>"
                              data-confirm="Release this grade? <?= esc($student['name'], 'attr') ?> will see the score and feedback.">
                            <?= csrf_field() ?>
                            <button type="submit" class="button button--primary"><?= icon('send') ?> Release to student</button>
                        </form>
                    <?php endif ?>
                    <form method="post" action="<?= site_url("teacher/submissions/{$submission['id']}/reopen") ?>"
                          data-prompt="Why are you changing this grade?" data-prompt-input="reason" data-prompt-required data-prompt-min="5"
                          data-prompt-button="Reopen grade"
                          data-prompt-text="The reason and the previous scores are recorded in the audit log.<?= $status === 'released' ? ' The grade will be hidden from the student until you release it again.' : '' ?>"
                          data-prompt-placeholder="e.g. Re-read the evidence section after the student's question.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="reason" value="">
                        <button type="submit" class="button"><?= icon('pencil') ?> Change this grade</button>
                    </form>
                </div>
            </section>
        <?php endif ?>

        <section class="card">
            <h2>Submission details</h2>
            <dl class="info-list">
                <div><dt>Submitted</dt><dd><?= esc(format_datetime($submission['submitted_at'])) ?></dd></div>
                <div><dt>Last edited by student</dt><dd><?= esc(format_datetime($revisions === [] ? $submission['submitted_at'] : end($revisions)['created_at'])) ?></dd></div>
                <div><dt>Due date</dt><dd><?= esc(format_datetime($assignment['due_at'])) ?></dd></div>
                <div><dt>Versions saved</dt><dd><?= count($revisions) ?></dd></div>
                <div><dt>Submitted as</dt><dd><?= empty($submission['file_name']) ? 'Typed or pasted text' : 'Uploaded file' ?></dd></div>
                <?php if (! empty($submission['file_name'])): ?>
                    <div class="info-list__file">
                        <dt>Original file</dt>
                        <dd><a href="<?= site_url("teacher/submissions/{$submission['id']}/file") ?>"><?= icon('download') ?> <?= esc($submission['file_name']) ?></a>
                            <span class="muted small"><?= esc(format_bytes($submission['file_size'])) ?></span></dd>
                    </div>
                <?php endif ?>
                <?php if ($evaluation !== null): ?>
                    <div><dt>Grading started</dt><dd><?= esc(format_datetime($evaluation['started_at'])) ?></dd></div>
                <?php endif ?>
            </dl>
        </section>
    </aside>
</div>

<script src="<?= base_url('assets/js/grading.js') ?>" defer></script>
<?php $this->endSection() ?>
