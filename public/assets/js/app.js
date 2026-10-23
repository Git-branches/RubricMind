// Shared page behaviour: SweetAlert messages and confirmations, the shared form
// modal, prompts, the mobile sidebar, and live word counts.
(function () {
    'use strict';

    var PRIMARY = '#4f46e5';
    var DANGER = '#dc2626';
    var MUTED = '#64748b';

    // --- SweetAlert helpers (also used by grading.js as window.RM) -----------

    function hasSwal() {
        return typeof window.Swal !== 'undefined';
    }

    // An open <dialog> sits in the browser's top layer, above everything else, so
    // SweetAlert must render inside it to be visible.
    function swalTarget() {
        var dialogs = document.querySelectorAll('dialog[open]');
        return dialogs.length ? dialogs[dialogs.length - 1] : document.body;
    }

    var Toast = hasSwal() ? window.Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true,
        didOpen: function (toast) {
            toast.addEventListener('mouseenter', window.Swal.stopTimer);
            toast.addEventListener('mouseleave', window.Swal.resumeTimer);
        }
    }) : null;

    function toast(icon, message) {
        if (Toast) {
            // titleText (not title): messages may contain names users typed; never render them as HTML.
            Toast.fire({ icon: icon, titleText: message, target: swalTarget() });
        }
    }

    function alertBox(icon, title, text) {
        if (!hasSwal()) {
            window.alert(text ? title + '\n\n' + text : title);
            return Promise.resolve();
        }
        return window.Swal.fire({ icon: icon, titleText: title, text: text || '', confirmButtonColor: PRIMARY, target: swalTarget() });
    }

    // SweetAlert renders button labels as HTML; escape anything that is not a fixed string.
    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    }

    // Resolves true when confirmed. "Question? Extra detail." becomes title + text.
    function confirmBox(message, options) {
        options = options || {};
        var split = message.indexOf('?');
        var title = split > -1 ? message.slice(0, split + 1) : message;
        var text = split > -1 ? message.slice(split + 1).trim() : '';

        if (!hasSwal()) {
            return Promise.resolve(window.confirm(message));
        }

        return window.Swal.fire({
            icon: options.danger ? 'warning' : 'question',
            titleText: title,
            text: text,
            showCancelButton: true,
            confirmButtonText: escapeHtml(options.confirmText || 'Yes, continue'),
            cancelButtonText: 'Cancel',
            confirmButtonColor: options.danger ? DANGER : PRIMARY,
            cancelButtonColor: MUTED,
            reverseButtons: true,
            focusCancel: !!options.danger,
            target: swalTarget()
        }).then(function (result) {
            return result.isConfirmed;
        });
    }

    window.RM = { toast: toast, alert: alertBox, confirm: confirmBox };

    // Esc should close only the SweetAlert, not the modal behind it.
    document.addEventListener('cancel', function (event) {
        if (hasSwal() && window.Swal.isVisible()) {
            event.preventDefault();
        }
    }, true);

    // --- Flash messages and form errors -------------------------------------

    function showMessages(root) {
        var flash = root.querySelector('#flash-messages');
        if (flash) {
            var messages = JSON.parse(flash.textContent || '{}');
            flash.remove();

            if (messages.error) {
                alertBox('error', 'Something needs attention', messages.error);
            } else if (messages.warning) {
                alertBox('warning', 'Please note', messages.warning);
            } else if (messages.info) {
                alertBox('info', 'Check your email', messages.info);
            }
            if (messages.success) {
                toast('success', messages.success);
            }
        }

        var errors = root.querySelector('[data-form-errors]');
        if (errors) {
            var count = parseInt(errors.getAttribute('data-form-errors'), 10) || 1;
            toast('error', count === 1 ? 'Please fix 1 problem in the form.' : 'Please fix ' + count + ' problems in the form.');
            errors.scrollIntoView({ block: 'nearest' });
        }
    }

    // --- Shared form modal ----------------------------------------------------

    var modal = document.getElementById('app-modal');
    var modalBody = modal ? modal.querySelector('[data-modal-body]') : null;

    function closeAppModal() {
        if (modal && modal.open) {
            modal.close();
        }
    }

    function fillModal(html) {
        modalBody.innerHTML = html;

        var title = modalBody.querySelector('[data-modal-title]');
        if (title) {
            title.id = 'app-modal-title';
            modal.setAttribute('aria-labelledby', 'app-modal-title');
        }

        initWordCounts(modalBody);

        if (!modal.open) {
            modal.showModal();
        }

        var first = modalBody.querySelector('.form input:not([type=hidden]), .form select, .form textarea');
        if (first) {
            first.focus();
        }
        showMessages(modalBody);
    }

    // Goes to a URL and always loads it, even when it is the current page with a
    // different #anchor (which the browser would otherwise only scroll to).
    function navigate(url) {
        var target = new URL(url, window.location.href);
        var here = new URL(window.location.href);

        if (target.origin + target.pathname + target.search === here.origin + here.pathname + here.search) {
            window.location.hash = target.hash;
            window.location.reload();
        } else {
            window.location.href = target.href;
        }
    }

    // Handles a response to a modal request: JSON {redirect} after a save,
    // HTML for the form (first load or with validation errors).
    function handleModalResponse(response) {
        var type = response.headers.get('Content-Type') || '';

        if (type.indexOf('application/json') > -1) {
            return response.json().then(function (data) {
                if (data.redirect) {
                    navigate(data.redirect);
                } else {
                    throw new Error('Unexpected response');
                }
            });
        }

        if (response.redirected) {
            // For example, the session expired and the server sent the sign-in page.
            navigate(response.url);
            return null;
        }

        if (response.status === 403) {
            closeAppModal();
            return alertBox('error', 'Access denied', 'Your account does not have permission to do that.');
        }

        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        return response.text().then(fillModal);
    }

    function modalRequest(url, options) {
        options = options || {};
        options.credentials = 'same-origin';
        options.headers = { 'X-Modal': '1' };

        return fetch(url, options).then(handleModalResponse).catch(function () {
            alertBox('error', 'Something went wrong', 'The request could not be completed. Please check your connection and try again.');
        });
    }

    if (modal) {
        document.addEventListener('click', function (event) {
            var link = event.target.closest('a[data-modal]');
            if (link && !event.ctrlKey && !event.metaKey && !event.shiftKey) {
                event.preventDefault();
                setSidebar(false);
                document.querySelectorAll('details.profile-menu[open]').forEach(function (menu) {
                    menu.removeAttribute('open');
                });
                modalRequest(link.href);
            }
        });

        modal.addEventListener('close', function () {
            modalBody.innerHTML = '';
        });
    }

    // --- Evidence highlights: tap or press Enter on one to see its criterion and note ----

    var evidenceBubble = null;

    function closeEvidenceNote() {
        if (evidenceBubble) {
            evidenceBubble.remove();
            evidenceBubble = null;
        }
    }

    function openEvidenceNote(mark) {
        closeEvidenceNote();

        if (mark.closest('.highlights-off')) {
            return;
        }

        // Inside the essay box, so it also works inside the grading dialog.
        var box = mark.closest('.essay__text') || mark.parentElement;
        box.classList.add('has-evidence-note');

        evidenceBubble = document.createElement('div');
        evidenceBubble.className = 'evidence-note';
        evidenceBubble.setAttribute('role', 'status');

        var title = document.createElement('strong');
        title.textContent = mark.getAttribute('data-evidence-criterion');
        evidenceBubble.appendChild(title);

        var note = mark.getAttribute('data-evidence-note');
        if (note) {
            var text = document.createElement('span');
            text.textContent = note;
            evidenceBubble.appendChild(text);
        }

        box.appendChild(evidenceBubble);

        var left = Math.max(0, Math.min(mark.offsetLeft, box.clientWidth - evidenceBubble.offsetWidth - 8));
        evidenceBubble.style.top = (mark.offsetTop + mark.offsetHeight + 6) + 'px';
        evidenceBubble.style.left = left + 'px';
    }

    document.addEventListener('click', function (event) {
        var mark = event.target.closest('mark[data-evidence-criterion]');

        if (mark) {
            openEvidenceNote(mark);
        } else if (!event.target.closest('.evidence-note')) {
            closeEvidenceNote();
        }
    });

    document.addEventListener('keydown', function (event) {
        var mark = event.target.closest && event.target.closest('mark[data-evidence-criterion]');

        if (mark && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            openEvidenceNote(mark);
        } else if (event.key === 'Escape') {
            closeEvidenceNote();
        }
    });

    // Reading a large PDF can take a few seconds: show that something is happening.
    document.addEventListener('submit', function (event) {
        var form = event.target.closest('[data-upload-form]');
        if (form) {
            var button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            button.textContent = 'Reading file…';
        }
    });

    // --- Small helpers: info pop-ups and show/hide password ----------------------

    // "Copy" buttons: copy their data-copy text to the clipboard.
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-copy]');
        if (!button) {
            return;
        }

        var text = button.getAttribute('data-copy');
        var copied = function () { toast('success', 'Link copied.'); };
        var fallback = function () { window.prompt('Copy this link:', text); };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(copied, fallback);
        } else {
            fallback();
        }
    });

    document.addEventListener('click', function (event) {
        var info = event.target.closest('[data-alert-title]');
        if (info) {
            alertBox('info', info.getAttribute('data-alert-title'), info.getAttribute('data-alert-text'));
        }

        var toggle = event.target.closest('[data-toggle-password]');
        if (toggle) {
            var input = document.getElementById(toggle.getAttribute('data-toggle-password'));
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            toggle.querySelector('[data-when="hidden"]').hidden = show;
            toggle.querySelector('[data-when="shown"]').hidden = !show;
            input.focus();
        }
    });

    // --- Any <dialog class="modal"> on the page ------------------------------
    // Open with data-open-dialog="<id>"; close with a data-close-modal button
    // inside it, or by clicking the backdrop around the box.

    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-open-dialog]');
        if (opener) {
            var dialog = document.getElementById(opener.getAttribute('data-open-dialog'));
            if (dialog && !dialog.open) {
                dialog.showModal();
                dialog.dispatchEvent(new CustomEvent('modal:opened'));
            }
        }

        var closer = event.target.closest('[data-close-modal]');
        if (closer && closer.closest('dialog')) {
            closer.closest('dialog').close();
        }

        // A click on the dialog element itself (not its content) is a backdrop click.
        if (event.target instanceof HTMLDialogElement && event.target.classList.contains('modal')) {
            event.target.close();
        }
    });

    // --- Form submission: confirm, prompt, modal submit, double-submit guard --

    function isDanger(form, button) {
        return !!((button && button.matches('.button--danger, .link-button--danger')) ||
            form.querySelector('.button--danger, .link-button--danger'));
    }

    function buttonLabel(button) {
        return button ? button.textContent.replace(/\s+/g, ' ').trim() : '';
    }

    function resubmit(form, button) {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(button || undefined);
        } else {
            form.submit();
        }
    }

    function promptFor(form) {
        var required = form.hasAttribute('data-prompt-required');
        var min = parseInt(form.getAttribute('data-prompt-min'), 10) || 0;

        if (!hasSwal()) {
            var value = window.prompt(form.getAttribute('data-prompt'));
            return Promise.resolve(value === null ? null : value);
        }

        return window.Swal.fire({
            titleText: form.getAttribute('data-prompt'),
            text: form.getAttribute('data-prompt-text') || '',
            input: 'textarea',
            inputPlaceholder: form.getAttribute('data-prompt-placeholder') || '',
            inputAttributes: { maxlength: '1000', 'aria-label': form.getAttribute('data-prompt') },
            showCancelButton: true,
            confirmButtonText: escapeHtml(form.getAttribute('data-prompt-button') || 'Save'),
            confirmButtonColor: PRIMARY,
            cancelButtonColor: MUTED,
            reverseButtons: true,
            target: swalTarget(),
            inputValidator: function (value) {
                value = (value || '').trim();
                if (required && value === '') {
                    return 'Please write something before continuing.';
                }
                if (value !== '' && value.length < min) {
                    return 'Please write at least ' + min + ' characters.';
                }
                return null;
            }
        }).then(function (result) {
            return result.isConfirmed ? (result.value || '').trim() : null;
        });
    }

    function lockButton(button) {
        if (button) {
            // After the browser has read the form data, so the button's value is still sent.
            window.setTimeout(function () {
                button.disabled = true;
                button.classList.add('is-busy');
            }, 0);
        }
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        var button = event.submitter || null;

        // 1. Confirmation (a button's own data-confirm wins over the form's).
        var message = (button && button.getAttribute('data-confirm')) || form.getAttribute('data-confirm');
        if (message && form.dataset.confirmed !== '1') {
            event.preventDefault();
            confirmBox(message, {
                danger: isDanger(form, button),
                confirmText: form.getAttribute('data-confirm-button') || buttonLabel(button) || 'Yes, continue'
            }).then(function (ok) {
                if (ok) {
                    form.dataset.confirmed = '1';
                    resubmit(form, button);
                }
            });
            return;
        }

        // 2. Prompt for a value (e.g. the reason for reopening a grade).
        if (form.hasAttribute('data-prompt') && form.dataset.prompted !== '1') {
            event.preventDefault();
            promptFor(form).then(function (value) {
                if (value === null) {
                    return;
                }
                form.querySelector('[name="' + form.getAttribute('data-prompt-input') + '"]').value = value;
                form.dataset.prompted = '1';
                resubmit(form, button);
            });
            return;
        }

        delete form.dataset.confirmed;
        delete form.dataset.prompted;

        // Block a second submit while the first one is still being sent.
        if (form.dataset.sending === '1') {
            event.preventDefault();
            return;
        }

        // 3. Forms inside the shared modal are sent in the background.
        if (modal && modal.contains(form)) {
            event.preventDefault();
            form.dataset.sending = '1';
            lockButton(button);

            var data = new FormData(form);
            if (button && button.name) {
                data.append(button.name, button.value);
            }

            modalRequest(form.action, { method: 'POST', body: data }).then(function () {
                delete form.dataset.sending;
                if (button) {
                    button.disabled = false;
                    button.classList.remove('is-busy');
                }
            });
            return;
        }

        if (form.method.toLowerCase() === 'post') {
            form.dataset.sending = '1';
            lockButton(button);
        }
    });

    // Pages restored from the back/forward cache keep their disabled buttons; re-enable them.
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[data-sending]').forEach(function (form) {
            delete form.dataset.sending;
            form.querySelectorAll('.is-busy').forEach(function (button) {
                button.disabled = false;
                button.classList.remove('is-busy');
            });
        });
    });

    // --- Word counts ------------------------------------------------------------

    function initWordCounts(root) {
        root.querySelectorAll('textarea[data-word-count]').forEach(function (textarea) {
            var output = document.getElementById(textarea.getAttribute('aria-describedby'));
            if (!output) {
                return;
            }

            function update() {
                var text = textarea.value.trim();
                var words = text === '' ? 0 : text.split(/\s+/).length;
                output.textContent = words + (words === 1 ? ' word' : ' words');
            }

            textarea.addEventListener('input', update);
            update();
        });
    }

    // --- Mobile sidebar and profile menu -------------------------------------------

    var openButton = document.querySelector('[data-sidebar-open]');

    function setSidebar(open) {
        document.body.classList.toggle('sidebar-open', open);
        if (openButton) {
            openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    if (openButton) {
        openButton.addEventListener('click', function () {
            setSidebar(true);
        });
    }

    document.querySelectorAll('[data-sidebar-close]').forEach(function (element) {
        element.addEventListener('click', function () {
            setSidebar(false);
        });
    });

    document.addEventListener('click', function (event) {
        document.querySelectorAll('details.profile-menu[open]').forEach(function (menu) {
            if (!menu.contains(event.target)) {
                menu.removeAttribute('open');
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            setSidebar(false);
            document.querySelectorAll('details.profile-menu[open]').forEach(function (menu) {
                menu.removeAttribute('open');
            });
        }
    });

    initWordCounts(document);
    showMessages(document);
})();
