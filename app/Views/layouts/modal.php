<?php
/**
 * Layout for a form loaded into the shared modal. It renders only the content;
 * the page title becomes the modal heading.
 *
 * @var CodeIgniter\View\View $this
 */
?>
<div class="modal__header">
    <h2 class="modal__title" data-modal-title><?= $this->renderSection('title') ?></h2>
    <button type="button" class="icon-button" data-close-modal aria-label="Close"><?= icon('x') ?></button>
</div>
<div class="modal__content">
    <?= $this->renderSection('content') ?>
</div>
