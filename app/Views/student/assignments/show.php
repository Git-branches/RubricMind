<?php
/**
 * @var CodeIgniter\View\View       $this
 * @var array<string, mixed>        $assignment
 * @var array<string, mixed>        $class
 * @var list<array<string, mixed>>  $criteria
 * @var array<string, mixed>|null   $submission
 * @var bool                        $canSubmit  No submission yet and submissions are open.
 * @var bool                        $canEdit    Has a submission that can still be edited.
 * @var bool                        $isPastDue
 * @var string|null                 $draft      Text from a failed save.
 * @var array<string, string>       $errors
 * @var array<string, mixed>|null   $evaluation Only set once the grade is released.
 * @var array<int, array>           $scores
 * @var bool                        $aiNotice   Show the AI-use notice.
 * @var bool                        $aiSimulated
 * @var string                      $aiProvider Short name, e.g. "Google Gemini".
 * @var array<string, mixed>|null   $pendingFile Uploaded file whose text is in the essay box, not submitted yet.
 * @var bool                        $uploadsEnabled
 */
use App\Libraries\SubmissionFiles;
use App\Libraries\SubmissionRules;

$showForm    = $canSubmit || $canEdit;
$text        = $draft ?? ($submission['content'] ?? '');
$statusLabel = SubmissionRules::studentStatusLabel($submission['status'] ?? null);
$statusClass = match (true) {
    $submission === null                 => 'tag--closed',
    $submission['status'] === 'released' => 'tag--published',
    default                              => 'tag--info',
};
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?><?= esc($assignment['title']) ?><?php $this->endSection() ?>

<?php $this->section('content') ?>
<p class="breadcrumb"><a href="<?= site_url('student') ?>">My assignments</a> <?= icon('chevron-right') ?></p>

<div class="page-header">
    <div>
        <h1>
            <?= esc($assignment['title']) ?>
            <span class="tag <?= $statusClass ?>"><?= esc($statusLabel) ?></span>
            <?php if (! empty($submission['is_late'])): ?><span class="tag tag--late">Late</span><?php endif ?>
        </h1>
        <div class="page-header__meta">
            <span><?= icon('classes') ?> <?= esc($class['name']) ?></span>
            <span><?= icon('calendar') ?> Due <?= esc(format_datetime($assignment['due_at'])) ?></span>
            <?php if ($submission !== null): ?>
                <span><?= icon('send') ?> Submitted <?= esc(format_datetime($submission['submitted_at'])) ?></span>
            <?php endif ?>
        </div>
    </div>
</div>

<?php if ($evaluation !== null): ?>
    <section class="card result-card">
        <div class="result-card__score">
            <span class="result-card__label">Your score</span>
            <div class="score-summary">
                <span class="score-summary__value"><?= esc($evaluation['total_score']) ?></span>
                <span class="score-summary__max">/ <?= esc($evaluation['max_score']) ?> points</span>
                <span class="score-summary__percent">
                    <?= $evaluation['max_score'] > 0 ? round($evaluation['total_score'] / $evaluation['max_score'] * 100) : 0 ?>%
                </span>
            </div>
            <span class="muted small">Released <?= esc(format_datetime($evaluation['released_at'])) ?></span>
        </div>
        <div class="result-card__details">
            <?php if (! empty($evaluation['student_feedback'])): ?>
                <h2>Feedback from your teacher</h2>
                <p class="prewrap"><?= esc($evaluation['student_feedback']) ?></p>
            <?php endif ?>
            <h3>Score by criterion</h3>
            <?php $this->setVar('showComments', true) ?>
            <?= $this->include('partials/score_breakdown') ?>
        </div>
    </section>
<?php endif ?>

<div class="two-column">
    <div>
        <section class="card">
            <h2>Instructions</h2>
            <p class="prewrap"><?= esc($assignment['instructions']) ?></p>
        </section>

        <section class="card" id="essay">
            <div class="section-header">
                <h2>Your essay</h2>
                <?php if ($submission !== null && $submission['updated_at'] !== $submission['created_at']): ?>
                    <span class="muted small">Last edited <?= esc(format_datetime($submission['updated_at'])) ?></span>
                <?php endif ?>
            </div>

            <?php if ($showForm): ?>
                <?php if ($canSubmit && $isPastDue): ?>
                    <div class="alert alert--warning" role="status">
                        <?= icon('clock', 'alert__icon') ?>
                        <div>The due date has passed. You can still submit, but your essay will be marked late.</div>
                    </div>
                <?php elseif ($canEdit): ?>
                    <div class="alert alert--success" role="status">
                        <?= icon('check-circle', 'alert__icon') ?>
                        <div>Submitted. You can still edit your essay until <?= esc(format_datetime($assignment['due_at'])) ?> or until your teacher starts grading it.</div>
                    </div>
                <?php endif ?>

                <?= $this->include('partials/errors') ?>

                <?php if ($uploadsEnabled): ?>
                    <form method="post" action="<?= site_url("student/assignments/{$assignment['id']}/upload") ?>" enctype="multipart/form-data"
                          class="upload-box" data-upload-form>
                        <?= csrf_field() ?>
                        <span class="upload-box__icon"><?= icon('upload') ?></span>
                        <div class="upload-box__text">
                            <label for="essay_file"><strong>Have your essay in a file?</strong></label>
                            <span class="hint" id="upload-hint">Upload a PDF or Word (.docx) file, up to <?= SubmissionFiles::MAX_BYTES / 1024 / 1024 ?> MB. Its text appears below for you to check before you submit.</span>
                            <input type="file" id="essay_file" name="essay_file" required aria-describedby="upload-hint"
                                   accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                        </div>
                        <button type="submit" class="button"><?= icon('file-text') ?> Read file</button>
                    </form>
                <?php endif ?>

                <form method="post" action="<?= site_url("student/assignments/{$assignment['id']}/submission") ?>" class="form"
                      <?= $canSubmit ? 'data-confirm="Submit your essay now?"' : '' ?>>
                    <?= csrf_field() ?>

                    <?php if ($pendingFile !== null): ?>
                        <div class="alert alert--info" role="status">
                            <?= icon('paperclip', 'alert__icon') ?>
                            <div>
                                <strong>The text from <?= esc($pendingFile['name']) ?> is in the box below.</strong>
                                Read it through and fix anything that came out wrong (for example, page headers or broken lines), then press
                                <?= $canEdit ? 'Update submission' : 'Submit essay' ?>. Nothing has been submitted yet.
                                <label class="switch-field" style="margin-top: 0.5rem">
                                    <input type="checkbox" name="attach_file" value="1" checked>
                                    <span>Attach the original file (<?= esc(format_bytes($pendingFile['size'])) ?>) so my teacher can see it</span>
                                </label>
                            </div>
                        </div>
                    <?php elseif (! empty($submission['file_name'])): ?>
                        <p class="file-chip">
                            <?= icon('paperclip') ?> Attached file:
                            <a href="<?= site_url("student/assignments/{$assignment['id']}/file") ?>"><?= esc($submission['file_name']) ?></a>
                            <span class="muted">(<?= esc(format_bytes($submission['file_size'])) ?>)</span>
                        </p>
                    <?php endif ?>

                    <div class="field">
                        <label for="content" class="visually-hidden">Essay text</label>
                        <textarea id="content" name="content" rows="18" maxlength="50000" required
                                  placeholder="Type or paste your essay here…"
                                  aria-describedby="word-count" data-word-count><?= esc($text) ?></textarea>
                    </div>
                    <div class="form-actions" style="justify-content: space-between; align-items: center">
                        <span class="word-count"><?= icon('file-text') ?> <span id="word-count" aria-live="polite"></span></span>
                        <button type="submit" class="button button--primary"><?= icon('send') ?> <?= $canEdit ? 'Update submission' : 'Submit essay' ?></button>
                    </div>
                </form>
            <?php elseif ($submission !== null): ?>
                <?php if (! empty($submission['file_name'])): ?>
                    <p class="file-chip">
                        <?= icon('paperclip') ?> Attached file:
                        <a href="<?= site_url("student/assignments/{$assignment['id']}/file") ?>"><?= esc($submission['file_name']) ?></a>
                        <span class="muted">(<?= esc(format_bytes($submission['file_size'])) ?>)</span>
                    </p>
                <?php endif ?>
                <div class="essay__text prewrap"><?= esc($submission['content']) ?></div>
                <?php if ($submission['status'] !== 'released'): ?>
                    <p class="hint" style="margin-top: 1rem"><?= icon('lock') ?> Your essay can no longer be edited. Your teacher will release your grade and feedback when grading is done.</p>
                <?php endif ?>
            <?php else: ?>
                <div class="empty-state" style="padding: 1.5rem">
                    <span class="empty-state__icon"><?= icon('lock') ?></span>
                    <p>This assignment is closed. Submissions are no longer accepted.</p>
                </div>
            <?php endif ?>
        </section>
    </div>

    <aside>
        <?php if ($aiNotice): ?>
            <section class="card ai-notice" role="note">
                <h2><?= icon('sparkles') ?> About AI in grading</h2>
                <p class="small">Your teacher may use AI suggestions to help review your essay. Only the essay text is analyzed, never your name or email.
                    <?= $aiSimulated
                        ? 'The analysis runs on your school\'s own server; your essay is not sent to any outside service.'
                        : 'Your essay text is sent to ' . esc($aiProvider) . ', an outside AI service, for analysis.' ?></p>
                <p class="small"><strong>Your teacher always reviews the work and decides your final grade.</strong></p>
            </section>
        <?php endif ?>

        <section class="card">
            <div class="section-header">
                <div>
                    <h2>How you'll be graded</h2>
                    <p class="muted small">Your teacher uses this rubric to score your essay.</p>
                </div>
            </div>
            <?= $this->include('partials/rubric_summary') ?>
        </section>
    </aside>
</div>
<?php $this->endSection() ?>
