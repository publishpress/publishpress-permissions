<?php
namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

class Core {
    function __construct() {
        add_filter('presspermit_options', [$this, 'fltPressPermitOptions'], 15);

        global $presspermit_loaded_by_pro;

        if (! $presspermit_loaded_by_pro) {
            add_action('init', function() { // late execution avoids clash with autoloaders in other plugins
                if (presspermitPluginPage()
                    || (defined('DOING_AJAX') && DOING_AJAX && !PWP::empty_REQUEST('action') && (false !== strpos(PWP::REQUEST_key('action'), 'press-permit-core')))
                ) {
                    if (!class_exists('\PublishPress\WordPressReviews\ReviewsController')) {
                        include_once PRESSPERMIT_ABSPATH . '/lib/vendor/publishpress/wordpress-reviews/ReviewsController.php';
                    }

                    if (class_exists('\PublishPress\WordPressReviews\ReviewsController')) {
                        $reviews = new \PublishPress\WordPressReviews\ReviewsController(
                            'press-permit-core',
                            'PublishPress Permissions',
                            plugin_dir_url(PRESSPERMIT_FILE) . 'common/img/permissions-wp-logo.jpg'
                        );

                        add_filter('publishpress_wp_reviews_display_banner_press-permit-core', [$this, 'shouldDisplayBanner']);

                        $reviews->init();
                    }
                }
            });
        }
    }

    public function shouldDisplayBanner() {
        return presspermitPluginPage();
    }

    function fltPressPermitOptions($options) {
        $options['presspermit_display_extension_hints'] = true;
        return $options;
    }
}
