(function () {
    'use strict';

    var config = document.querySelector('.pp-settings-redirect');

    if (config) {
        window.location.href = config.dataset.redirectTo;
    }
}());
