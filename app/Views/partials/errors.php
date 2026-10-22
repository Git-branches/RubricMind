<?php
/**
 * Summary of validation errors at the top of a form. assets/js/app.js also
 * shows a short SweetAlert notice pointing to it.
 *
 * @var array<string, string>|list<string>|null $errors
 */
?>
<?php if (! empty($errors)): ?>
    <div class="alert alert--error" role="alert" data-form-errors="<?= count($errors) ?>">
        <?= icon('alert-circle', 'alert__icon') ?>
        <div>
            <p><strong>Please fix the following:</strong></p>
            <ul>
                <?php foreach ($errors as $message): ?>
                    <li><?= esc($message) ?></li>
                <?php endforeach ?>
            </ul>
        </div>
    </div>
<?php endif ?>
