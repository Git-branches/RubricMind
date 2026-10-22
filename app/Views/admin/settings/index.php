<?php
/**
 * System settings (§3) and data retention (§5).
 *
 * @var CodeIgniter\View\View       $this
 * @var array<string, string>       $values
 * @var int                         $retentionYears
 * @var bool                        $geminiReady
 * @var string                      $geminiModel
 * @var App\Libraries\Mailer        $mailer
 * @var list<array<string, mixed>>  $archived
 * @var array<array-key, string>    $errors
 */
use App\Libraries\SystemSettings;

$eligible = array_values(array_filter($archived, static fn (array $c) => $c['eligible']));
?>
<?php $this->extend('layouts/app') ?>

<?php $this->section('title') ?>Settings<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="page-header">
    <div>
        <h1>Settings</h1>
        <p>System-wide options for every teacher and student.</p>
    </div>
</div>

<?= $this->include('partials/errors') ?>

<form method="post" action="<?= site_url('admin/settings') ?>" class="form">
    <?= csrf_field() ?>

    <!-- One card of setting rows: name and explanation on the left, the control on the right. -->
    <section class="card settings">
        <h3 class="settings__group"><?= icon('school') ?> General</h3>
        <div class="setting-row">
            <div class="setting-row__text">
                <label for="school_name">School name <span class="optional">(optional)</span></label>
                <p class="hint" id="school-hint">Shown on the sign-in page and under the RubricMind name in the menu.</p>
            </div>
            <div class="setting-row__control">
                <input type="text" id="school_name" name="school_name" value="<?= esc($values['school_name'], 'attr') ?>" maxlength="150"
                       placeholder="e.g. Rizal National High School" aria-describedby="school-hint">
            </div>
        </div>

        <div class="setting-row">
            <div class="setting-row__text">
                <label for="registration_enabled">Allow students to create their own accounts</label>
                <p class="hint" id="registration-hint">Shows "Create Student Account" on the sign-in page. New accounts are always students, and they only see a class after joining it with its class code. Teachers can still add students themselves.</p>
            </div>
            <div class="setting-row__control">
                <label class="switch-field">
                    <input type="checkbox" id="registration_enabled" name="registration_enabled" value="1" aria-describedby="registration-hint" <?= $values['registration_enabled'] === '1' ? 'checked' : '' ?>>
                    <span>On</span>
                </label>
            </div>
        </div>

        <div class="setting-row">
            <div class="setting-row__text">
                <span class="setting-row__label">Email delivery</span>
                <p class="hint">Used for "Forgot your password?" links. Set it up in the <code>.env</code> file (<code>email.</code> settings), for example with Gmail and an App Password.</p>
            </div>
            <div class="setting-row__control">
                <p class="provider-status small">
                    <?php if ($mailer->smtpConfigured() && $mailer->testInboxUrl() !== ''): ?>
                        <span class="tag tag--published">Test inbox</span>
                        <span class="muted">Emails go to Mailpit on this computer: <a href="<?= esc($mailer->testInboxUrl(), 'attr') ?>" target="_blank" rel="noopener"><?= esc($mailer->testInboxUrl()) ?></a></span>
                    <?php elseif ($mailer->smtpConfigured()): ?>
                        <span class="tag tag--published">On</span> <span class="muted">Sending from <?= esc($mailer->senderAddress()) ?> through <?= esc($mailer->serverName()) ?></span>
                    <?php else: ?>
                        <span class="tag tag--closed">Not set up</span>
                        <span class="muted">Emails are saved to the <a href="<?= site_url('admin/emails') ?>">Email outbox</a> instead of being sent.</span>
                    <?php endif ?>
                </p>
            </div>
        </div>

        <h3 class="settings__group"><?= icon('file-text') ?> Essay submissions</h3>
        <div class="setting-row">
            <div class="setting-row__text">
                <label for="uploads_enabled">Allow PDF and Word (DOCX) uploads</label>
                <p class="hint" id="uploads-hint">Students can upload a file instead of typing. The text is read from the file so they can check it before submitting, and the original file is kept for the teacher. Scanned or handwritten pages cannot be read.</p>
            </div>
            <div class="setting-row__control">
                <label class="switch-field">
                    <input type="checkbox" id="uploads_enabled" name="uploads_enabled" value="1" aria-describedby="uploads-hint" <?= $values['uploads_enabled'] === '1' ? 'checked' : '' ?>>
                    <span>On</span>
                </label>
            </div>
        </div>

        <h3 class="settings__group"><?= icon('sparkles') ?> AI assistance</h3>
        <div class="setting-row">
            <div class="setting-row__text">
                <label for="ai_enabled">Turn on AI suggestions</label>
                <p class="hint" id="ai-enabled-hint">Suggested scores, evidence highlights, mechanics summaries, and lesson-plan drafts. When off, teachers grade manually and AI buttons are hidden.</p>
            </div>
            <div class="setting-row__control">
                <label class="switch-field">
                    <input type="checkbox" id="ai_enabled" name="ai_enabled" value="1" aria-describedby="ai-enabled-hint" <?= $values['ai_enabled'] === '1' ? 'checked' : '' ?>>
                    <span>On</span>
                </label>
            </div>
        </div>

        <div class="setting-row">
            <div class="setting-row__text">
                <label for="ai_provider">AI provider</label>
                <p class="hint">The demo AI uses simple, explainable rules on this server and sends nothing outside the school.</p>
            </div>
            <div class="setting-row__control">
                <select id="ai_provider" name="ai_provider">
                    <?php foreach (SystemSettings::AI_PROVIDERS as $key => [, $label]): ?>
                        <option value="<?= esc($key, 'attr') ?>" <?= $values['ai_provider'] === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
                <p class="provider-status small">
                    <strong>Google Gemini:</strong>
                    <?php if ($geminiReady): ?>
                        <span class="tag tag--published">API key set</span> <span class="muted">Model: <?= esc($geminiModel) ?></span>
                    <?php else: ?>
                        <span class="tag tag--closed">No API key</span>
                        <span class="muted">Add <code>ai.geminiApiKey</code> to the <code>.env</code> file to use it.</span>
                    <?php endif ?>
                    <span class="provider-status__warning"><?= icon('alert-triangle') ?> On Google AI Studio's <strong>free tier</strong>, Google may use what is sent to improve its products.
                        Use demo essays, or a paid API key, before analyzing real students' work. Only essay text and the rubric are sent, never names or emails.</span>
                </p>
            </div>
        </div>

        <div class="setting-row">
            <div class="setting-row__text">
                <label for="ai_notice_enabled">Show the AI notice to students</label>
                <p class="hint" id="ai-notice-hint">Tells students on their assignment page that AI may help review essays and that the teacher decides the grade.</p>
            </div>
            <div class="setting-row__control">
                <label class="switch-field">
                    <input type="checkbox" id="ai_notice_enabled" name="ai_notice_enabled" value="1" aria-describedby="ai-notice-hint" <?= $values['ai_notice_enabled'] === '1' ? 'checked' : '' ?>>
                    <span>On</span>
                </label>
            </div>
        </div>

        <h3 class="settings__group" id="retention-settings"><?= icon('archive') ?> Data retention</h3>
        <div class="setting-row">
            <div class="setting-row__text">
                <label for="retention_years">Keep archived class records for</label>
                <p class="hint">After this period, an administrator may permanently delete an archived class's essays and grades (see the list below). Nothing is deleted automatically.</p>
            </div>
            <div class="setting-row__control field--inline">
                <input type="number" id="retention_years" name="retention_years" value="<?= esc($values['retention_years'], 'attr') ?>"
                       min="<?= SystemSettings::MIN_RETENTION_YEARS ?>" max="<?= SystemSettings::MAX_RETENTION_YEARS ?>" step="1" required>
                <span>year(s)</span>
            </div>
        </div>

        <div class="settings__footer">
            <button type="submit" class="button button--primary"><?= icon('check-circle') ?> Save settings</button>
        </div>
    </section>
</form>

<section class="card" id="retention">
    <div class="section-header">
        <div>
            <h2>Archived classes</h2>
            <p class="muted small">
                <?= count($eligible) ?> of <?= count($archived) ?> archived class(es) are older than <?= $retentionYears ?> year(s) and can be deleted.
                Deleting removes essays, revisions, grades, AI results, lesson plans, assignments, and enrollments.
                Student accounts, rubrics, and the audit log are kept.
            </p>
        </div>
    </div>

    <?php if ($archived === []): ?>
        <p class="inline-empty"><?= icon('archive') ?> No archived classes yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Class</th>
                    <th scope="col">Teacher</th>
                    <th scope="col">Archived</th>
                    <th scope="col">Records</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($archived as $class): ?>
                    <tr>
                        <td>
                            <strong><?= esc($class['name']) ?></strong>
                            <div class="muted small"><?= esc(implode(' · ', array_filter([$class['section'], $class['school_year']]))) ?></div>
                        </td>
                        <td><?= esc($class['teacher_name']) ?></td>
                        <td class="nowrap"><?= $class['archived_at'] !== null ? esc(format_datetime($class['archived_at'])) : '<span class="muted">Unknown</span>' ?></td>
                        <td class="small"><?= esc($class['students']) ?> students · <?= esc($class['assignments']) ?> assignments · <?= esc($class['submissions']) ?> essays</td>
                        <td class="actions">
                            <?php if ($class['eligible']): ?>
                                <form method="post" action="<?= site_url("admin/retention/classes/{$class['id']}/delete") ?>" class="inline-form"
                                      data-confirm="Permanently delete &quot;<?= esc($class['name'], 'attr') ?>&quot;? <?= esc($class['submissions'], 'attr') ?> essay(s) and all grades for this class will be deleted. This cannot be undone."
                                      data-confirm-button="Delete permanently">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="button button--danger button--small"><?= icon('trash') ?> Delete records</button>
                                </form>
                            <?php else: ?>
                                <span class="tag tag--closed">Kept until <?= esc($class['eligible_on'] ?? '—') ?></span>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
<?php $this->endSection() ?>
