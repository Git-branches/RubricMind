// Grading page: the grading modal, live total, applying AI suggestions, and the highlight toggle.
(function () {
    'use strict';

    var modal = document.getElementById('grading-modal');
    var form = document.querySelector('[data-grading]');
    var output = form ? form.querySelector('[data-score-total]') : null;
    var summary = document.querySelector('[data-score-total-summary]');
    var dirtyNote = form ? form.querySelector('[data-dirty-note]') : null;
    var dirty = false;
    var submitting = false;

    // --- Modal -------------------------------------------------------------
    // Opening and closing are handled by app.js (data-open-dialog / data-close-modal).

    function focusFirstScore() {
        var first = modal.querySelector('.modal__pane--rubric input, .modal__pane--rubric textarea');
        if (first) {
            first.focus({ preventScroll: true });
        }
    }

    if (modal) {
        modal.addEventListener('modal:opened', focusFirstScore);

        // Open right away after a failed save or when grading has just started.
        if (modal.hasAttribute('data-auto-open') || window.location.hash === '#grade') {
            modal.showModal();
            focusFirstScore();
        }
    }

    // --- Scores ------------------------------------------------------------

    function updateTotal() {
        if (!form) {
            return;
        }
        var total = 0;
        form.querySelectorAll('[data-score]').forEach(function (input) {
            if ((input.type === 'radio' && !input.checked) || input.value === '') {
                return;
            }
            var value = parseInt(input.value, 10);
            if (!isNaN(value)) {
                total += value;
            }
        });
        output.textContent = String(total);
        if (summary) {
            summary.textContent = String(total);
        }
    }

    function markDirty() {
        dirty = true;
        if (dirtyNote) {
            dirtyNote.hidden = false;
        }
    }

    // Fills one criterion with a suggested score. Nothing is saved until the teacher saves.
    function applyScore(button) {
        var name = 'scores[' + button.getAttribute('data-criterion') + '][points]';
        var points = button.getAttribute('data-points');

        form.querySelectorAll('[name="' + name + '"]').forEach(function (input) {
            if (input.type === 'radio') {
                input.checked = input.value === points;
            } else {
                input.value = points;
            }
        });
    }

    if (form) {
        form.addEventListener('change', function () { updateTotal(); markDirty(); });
        form.addEventListener('input', function () { updateTotal(); markDirty(); });
        // app.js runs first and may stop the submit to ask for confirmation.
        document.addEventListener('submit', function (event) {
            if (event.target === form && !event.defaultPrevented) {
                submitting = true;
            }
        });

        form.addEventListener('click', function (event) {
            var button = event.target.closest('[data-apply-score]');
            if (button) {
                applyScore(button);
                updateTotal();
                markDirty();
            }
        });

        // Warn before leaving the page with unsaved scores.
        window.addEventListener('beforeunload', function (event) {
            if (dirty && !submitting) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
    }

    document.addEventListener('click', function (event) {
        if (!form) {
            return;
        }

        if (event.target.closest('[data-apply-all-scores]')) {
            form.querySelectorAll('[data-apply-score]').forEach(applyScore);
            updateTotal();
            markDirty();
        }

        var feedbackButton = event.target.closest('[data-apply-feedback]');
        if (feedbackButton) {
            var textarea = form.querySelector('#student_feedback');
            var apply = function () {
                textarea.value = feedbackButton.getAttribute('data-apply-feedback');
                markDirty();
                textarea.scrollIntoView({ block: 'center' });
            };

            if (textarea.value.trim() === '') {
                apply();
            } else {
                window.RM.confirm('Replace your feedback? The feedback you already wrote will be replaced by the AI suggestion.', {
                    confirmText: 'Replace'
                }).then(function (ok) {
                    if (ok) {
                        apply();
                    }
                });
            }
        }
    });

    // --- Highlights ----------------------------------------------------------

    var toggle = document.querySelector('[data-toggle-highlights]');
    var essay = document.querySelector('[data-highlights]');
    if (toggle && essay) {
        toggle.addEventListener('change', function () {
            essay.classList.toggle('highlights-off', !toggle.checked);
        });
    }

    updateTotal();
})();
