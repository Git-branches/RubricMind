// Adds and removes criteria and performance levels in the rubric editor.
// The server reads criteria and levels in page order, so keys only need to be unique.
(function () {
    'use strict';

    var form = document.querySelector('[data-rubric-editor]');
    if (!form) {
        return;
    }

    var criteriaList = form.querySelector('[data-criteria]');
    var criterionTemplate = document.getElementById('criterion-template').innerHTML;
    var levelTemplate = document.getElementById('level-template').innerHTML;
    var totalOutput = form.querySelector('[data-total-points]');
    var counter = 0;

    function uniqueKey(prefix) {
        counter += 1;
        return prefix + 'n' + counter + Date.now().toString(36);
    }

    function fromHtml(html) {
        var wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        return wrapper.firstElementChild;
    }

    function renumber() {
        var criteria = criteriaList.querySelectorAll('[data-criterion]');
        criteria.forEach(function (criterion, index) {
            criterion.querySelector('[data-number]').textContent = String(index + 1);
            // Keep at least one criterion: hide its remove button when it is the only one.
            criterion.querySelector('[data-remove-criterion]').hidden = criteria.length === 1;
        });
    }

    function updateTotal() {
        var total = 0;
        criteriaList.querySelectorAll('[data-max-points]').forEach(function (input) {
            var value = parseInt(input.value, 10);
            if (!isNaN(value) && value > 0) {
                total += value;
            }
        });
        totalOutput.textContent = String(total);
    }

    function addCriterion() {
        var criterion = fromHtml(criterionTemplate.split('__C__').join(uniqueKey('c')));
        criteriaList.appendChild(criterion);
        renumber();
        updateTotal();
        criterion.querySelector('input').focus();
    }

    function addLevel(criterion) {
        var html = levelTemplate
            .split('__C__').join(criterion.getAttribute('data-key'))
            .split('__L__').join(uniqueKey('l'));
        var level = fromHtml(html);
        criterion.querySelector('[data-levels]').appendChild(level);
        level.querySelector('input').focus();
    }

    form.addEventListener('click', function (event) {
        var target = event.target;
        var criterion = target.closest('[data-criterion]');

        if (target.matches('[data-add-criterion]')) {
            addCriterion();
        } else if (target.matches('[data-add-level]') && criterion) {
            addLevel(criterion);
        } else if (target.matches('[data-remove-level]')) {
            target.closest('[data-level]').remove();
        } else if (target.matches('[data-remove-criterion]') && criterion) {
            criterion.remove();
            renumber();
            updateTotal();
        }
    });

    form.addEventListener('input', function (event) {
        if (event.target.matches('[data-max-points]')) {
            updateTotal();
        }
    });

    renumber();
    updateTotal();
})();
