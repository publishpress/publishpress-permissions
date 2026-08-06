<?php

namespace PublishPress\Permissions;

class FrontFilters
{
    public function __construct() {
        if ( $this->isActiveWidgetPrefix('calendar-')) {
            add_filter('query', [$this, 'fltCalendar']);
        }

        if (presspermit()->getOption('strip_private_caption')) {
            add_filter('the_title', [$this, 'fltTitle'], 10, 3);

            if (defined('WPLANG') && WPLANG) {
                add_filter('gettext', [$this, 'fltGetText'], 10, 3);
            }
        }

        add_action('wp_print_footer_scripts', [$this, 'fltHideEmptyMenus']);
    }

    private function isActiveWidgetPrefix($id_prefix)
    {
        global $wp_registered_widgets;

        foreach ((array)wp_get_sidebars_widgets() as $sidebar => $widgets) {
            if ('wp_inactive_widgets' != $sidebar && is_array($widgets)) {
                foreach ($widgets as $widget) {
                    if (
                        isset($wp_registered_widgets[$widget]['id'])
                        && (0 === strpos($wp_registered_widgets[$widget]['id'], $id_prefix))
                    ) {
                        return $sidebar;
                    }
                }
            }
        }

        return false;
    }

    public function fltHideEmptyMenus()
    {
        // For hierarchical custom nav menus, hide sub-lists for which all items are unreadable
        // Note: this does not apply to Nav Menus that follow standard structure and class naming convention
        if (defined('PRESSPERMIT_NO_NAV_MENU_SCRIPTS')) {
            return;
        }

        $legacy_div_toggle = defined('PRESSPERMIT_HIDE_EMPTY_NAV_MENU_DIV') /* legacy nav menu support */
            ? 'if (ulist.parentElement.nodeName == "DIV") { ulist.parentElement.style.display = "none"; }'
            : '';

        $js = '
            document.querySelectorAll("ul.nav-menu").forEach(
                ulist => {
                    if (ulist.querySelectorAll("li").length == 0) {
                        ulist.style.display = "none";
                        ' . $legacy_div_toggle . '
                    }
                }
            );
        ';

        // wp_print_inline_script_tag() (WP 5.7+) lets WP tag the script with a CSP nonce when one is active.
        // Fall back to a raw <script> tag on the older WP versions this plugin still supports.
        if (function_exists('wp_print_inline_script_tag')) {
            wp_print_inline_script_tag($js);
        } else {
            echo '<script type="text/javascript">' . $js . '</script>';
        }
    }

    public function fltTitle($title)
    {
        if (0 === strpos($title, 'Private: ') || 0 === strpos($title, 'Protected: ')) {
            $title = substr($title, strpos($title, ':') + 2);
        }

        return $title;
    }

    public function fltGetText($translated_text, $orig_text)
    {
        if (('Private: %s' == $orig_text) || ('Protected: %s' == $orig_text)) {
            $translated_text = '%s';
        }

        return $translated_text;
    }

    public function fltCalendar($query)
    {
        if (
            strpos($query, "DISTINCT DAYOFMONTH") || strpos($query, "post_title, DAYOFMONTH(post_date)")
            || strpos($query, "MONTH(post_date) AS month")
        ) {
            $query = apply_filters('presspermit_posts_request', $query);
        }

        return $query;
    }
}
