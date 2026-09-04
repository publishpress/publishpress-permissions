(function () {
    'use strict';

    var config = document.querySelector('.pp-version-notice-redirect');
    if (config) {
        window.setTimeout(function () {
            window.location.replace(config.dataset.redirectTo);
        }, 600);
    }
}());
