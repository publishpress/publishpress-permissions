(function ($) {
    'use strict';

    function selectField(field) {
        field.focus();
        field.select();
        field.setSelectionRange(0, field.value.length);
    }

    function copyWithFallback(field) {
        selectField(field);

        return document.execCommand('copy');
    }

    function copyShortcode(field) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(field.value).catch(function () {
                if (!copyWithFallback(field)) {
                    return Promise.reject();
                }
            });
        }

        return copyWithFallback(field)
            ? Promise.resolve()
            : Promise.reject();
    }

    $(document).on('click', '.pp-content-visibility-shortcode-field', function () {
        selectField(this);
    });

    $(document).on('click', '.pp-content-visibility-copy-shortcode', function () {
        var button = this;
        var control = button.closest('.pp-content-visibility-shortcode-control');
        var field = control.querySelector('.pp-content-visibility-shortcode-field');
        var tooltip = button.querySelector('.pp-content-visibility-copy-tooltip');
        var status = control.querySelector('.pp-content-visibility-copy-status');
        var copiedLabel = button.getAttribute('data-copied-label');
        var copyLabel = button.getAttribute('data-copy-label');

        copyShortcode(field).then(function () {
            button.classList.add('is-copied');
            button.setAttribute('aria-label', copiedLabel);
            tooltip.textContent = copiedLabel;
            status.textContent = copiedLabel;

            window.setTimeout(function () {
                button.classList.remove('is-copied');
                button.setAttribute('aria-label', copyLabel);
                tooltip.textContent = copyLabel;
                status.textContent = '';
            }, 2000);
        });
    });
}(jQuery));
