(function () {
    'use strict';

    if (typeof window.wp === 'undefined') {
        window.wp = {
            media: {
                view: {settings: {post: {}}},
                editor: {
                    remove: function () {},
                    add: function () {}
                }
            }
        };
    }
}());
