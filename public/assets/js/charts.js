// Hover and keyboard-focus tooltips for chart marks with data-tip.
// Tooltips add detail only; every value is also in the chart labels or table view.
(function () {
    'use strict';

    var tooltip = document.querySelector('.chart-tooltip');
    if (!tooltip) {
        return;
    }

    function show(target) {
        tooltip.textContent = target.getAttribute('data-tip');
        tooltip.hidden = false;

        var box = target.getBoundingClientRect();
        var tip = tooltip.getBoundingClientRect();
        var left = Math.min(Math.max(8, box.left + box.width / 2 - tip.width / 2), window.innerWidth - tip.width - 8);
        var top = box.top - tip.height - 8;

        tooltip.style.left = left + 'px';
        tooltip.style.top = (top < 8 ? box.bottom + 8 : top) + 'px';
    }

    function hide() {
        tooltip.hidden = true;
    }

    document.querySelectorAll('[data-tip]').forEach(function (mark) {
        mark.addEventListener('mouseenter', function () { show(mark); });
        mark.addEventListener('focus', function () { show(mark); });
        mark.addEventListener('mouseleave', hide);
        mark.addEventListener('blur', hide);
    });

    window.addEventListener('scroll', hide, { passive: true });
})();
