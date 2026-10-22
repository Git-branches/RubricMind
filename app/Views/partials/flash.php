<?php
/**
 * Flash messages. assets/js/app.js shows them with SweetAlert (success as a
 * toast; information, warnings, and errors as dialogs). The <noscript> copy is the fallback.
 */
$messages = array_filter([
    'success' => session('success'),
    'info'    => session('info'),
    'warning' => session('warning'),
    'error'   => session('error') ?? ($error ?? null),
]);
$icons = ['success' => 'check-circle', 'info' => 'mail', 'warning' => 'alert-triangle', 'error' => 'alert-circle'];
?>
<?php if ($messages !== []): ?>
    <script type="application/json" id="flash-messages"><?= json_encode($messages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
    <noscript>
        <?php foreach ($messages as $type => $message): ?>
            <div class="alert alert--<?= $type ?>" role="<?= $type === 'success' ? 'status' : 'alert' ?>">
                <?= icon($icons[$type], 'alert__icon') ?>
                <div><?= esc($message) ?></div>
            </div>
        <?php endforeach ?>
    </noscript>
<?php endif ?>
