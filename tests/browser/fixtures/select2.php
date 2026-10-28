<?php
/** Render the real controls with in-memory WordPress substitutes for browser regressions. */

namespace PublishPress\Permissions\UI {
    class PWP
    {
        public static function empty_GET($key) { return true; }
        public static function empty_REQUEST($key) { return true; }
        public static function REQUEST_key_match($key, $value) { return false; }
    }
}

namespace PublishPress\Permissions {
    class TeaserHooks
    {
        public static function getDefaultTeaserText() { return 'Blocked message'; }
    }
}

namespace {
    define('ABSPATH', dirname(__DIR__, 3) . '/');
    define('PRESSPERMIT_CLASSPATH', ABSPATH . 'classes/PublishPress/Permissions');
    define('PRESSPERMIT_URLPATH', 'https://permissions.test/plugin');
    define('PRESSPERMIT_TEASER_FILE', ABSPATH . 'modules/presspermit-teaser/presspermit-teaser.php');
    define('PRESSPERMIT_VERSION', 'test');
    define('PRESSPERMIT_TEASER_VERSION', 'test');

    $mode = $argv[1] ?? 'members';
    if (!in_array($mode, ['members', 'author', 'permissions', 'teaser'], true)) {
        throw new RuntimeException('Unknown fixture');
    }
    $pagenow = 'author' === $mode ? 'post.php' : 'admin.php';
    $wp_scripts = (object) ['in_footer' => []];
    $scripts = $styles = $localized = $actions = [];

    function __($text, $domain = '') { return $text; }
    function _x($text, $context, $domain) { return $text; }
    function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES); }
    function esc_attr($text) { return esc_html($text); }
    function esc_url($text) { return esc_html($text); }
    function esc_html__($text, $domain = '') { return esc_html($text); }
    function esc_html_e($text, $domain = '') { echo esc_html($text); }
    function esc_attr_e($text, $domain = '') { echo esc_attr($text); }
    function selected($left, $right) { if ((string) $left === (string) $right) echo ' selected'; }
    function apply_filters($hook, $value, ...$args) { return $value; }
    function do_action($hook, ...$args) {}
    function admin_url($path = '') { return 'https://permissions.test/wp-admin/' . $path; }
    function plugins_url($path, $file) { return PRESSPERMIT_URLPATH . '/modules/presspermit-teaser' . $path; }
    function wp_nonce_url($url, $action) { return $url . '?_wpnonce=test'; }
    function wp_create_nonce($action) { return 'test'; }
    function get_user_meta($id, $key, $single) { return 'first_name' === $key ? 'Saved' : 'User'; }
    function get_the_title($id) { return 'Saved page'; }
    function add_action($hook, $callback, $priority = 10, $args = 1) { $GLOBALS['actions'][$hook][] = $callback; }
    function add_filter($hook, $callback, $priority = 10, $args = 1) {}
    function presspermit_enqueue_admin_script() {}
    function wp_style_is($handle, $state) { return isset($GLOBALS['styles'][$handle]); }
    function wp_script_is($handle, $state) { return 'done' !== $state && isset($GLOBALS['scripts'][$handle]); }
    function wp_register_style($handle, $src, $deps = [], $version = '', $media = '') { $GLOBALS['styles'][$handle] = compact('src', 'deps', 'version'); }
    function wp_register_script($handle, $src, $deps = [], $version = '', $footer = false) { $GLOBALS['scripts'][$handle] = compact('src', 'deps', 'version', 'footer'); }
    function wp_enqueue_style($handle, $src = '', $deps = [], $version = '') { if ($src) wp_register_style($handle, $src, $deps, $version); }
    function wp_enqueue_script($handle, $src = '', $deps = [], $version = '', $footer = false) { if ($src) wp_register_script($handle, $src, $deps, $version, $footer); }
    function wp_localize_script($handle, $name, $values) { $GLOBALS['localized'][$name] = $values; }
    function presspermitPluginPage() { return 'teaser' === $GLOBALS['mode'] ? 'presspermit-posts-teaser' : 'presspermit-edit-permissions'; }
    function get_post_types($args, $output) {
        return ['page' => (object) ['labels' => (object) ['singular_name' => 'Page']], 'post' => (object) ['labels' => (object) ['singular_name' => 'Post']]];
    }
    function presspermit() {
        return new class {
            public function isUserAdministrator() { return false; }
            public function getOption($name) { return false; }
            public function groups() { return new class { public function getGroupByName($name) { return false; } }; }
            public function getTypeOption($name, $type) {
                $values = ['teaser_redirect_anon' => '(select)', 'teaser_redirect' => '(select)', 'teaser_redirect_anon_page' => 9, 'teaser_redirect_page' => 9];
                return $values[$name] ?? '';
            }
        };
    }

    ob_start();
    if (in_array($mode, ['members', 'author'], true)) {
        require_once PRESSPERMIT_CLASSPATH . '/UI/AgentsDynamicUI.php';
        $ui = new \PublishPress\Permissions\UI\AgentsDynamicUI();
        $saved = (object) ['ID' => 9, 'user_login' => 'saved', 'display_name' => 'Saved User'];
        $ui->display_select2('user', 'members' === $mode ? 'member' : 'select-author', 'members' === $mode ? [9 => $saved] : [], [
            'agent_id' => 55, 'multi_select' => 'members' === $mode, 'display_stored_selections' => 'members' === $mode,
        ]);
        $ui->actAjaxSelectionScripts();
        if ('author' === $mode) echo '<select id="post_author_override" name="post_author_override"><option value="9">Saved User</option></select>';
    } elseif ('permissions' === $mode) {
        require_once PRESSPERMIT_CLASSPATH . '/UI/Dashboard/ItemExceptionsUI.php';
        require_once PRESSPERMIT_CLASSPATH . '/UI/Dashboard/ItemExceptionsRenderUI.php';
        $render = new \PublishPress\Permissions\UI\Dashboard\ItemExceptionsRenderUI();
        $render->setOptions('user');
        $ui = (new ReflectionClass(\PublishPress\Permissions\UI\Dashboard\ItemExceptionsUI::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($ui, 'render'))->setValue($ui, $render);
        $method = new ReflectionMethod($ui, 'renderUserItemTemplate');
        echo '<div class="pp-agent-type-content active"><div class="pp-search-box pp-search-box-select2"><select class="pp-user-search-select2" multiple data-op="read" data-for-item-type="page" data-agent-type="user" data-placeholder="Search users"></select></div><div class="pp-permission-card"><div class="pp-permission-list">';
        $method->invoke($ui, 'read', 'page', true, (object) ['labels' => (object) ['singular_name' => 'Page', 'name' => 'Pages']]);
        echo '</div></div></div>';
        $localized = ['PPAgentSelect' => ['ajaxurl' => admin_url()]];
    } else {
        require_once ABSPATH . 'modules/presspermit-teaser/classes/Permissions/TeaserHooksAdmin.php';
        new \PublishPress\Permissions\TeaserHooksAdmin();
        foreach ($actions['admin_enqueue_scripts'] as $callback) $callback();
        require_once ABSPATH . 'modules/presspermit-teaser/classes/Permissions/Teaser/UI/TeaserUIBaseTrait.php';
        require_once ABSPATH . 'modules/presspermit-teaser/classes/Permissions/Teaser/UI/TeaserProgressiveUI.php';
        $ui = new \PublishPress\Permissions\Teaser\UI\TeaserProgressiveUI(presspermit(), (object) ['display_hints' => false, 'all_otype_options' => []], ['post' => 'redirect'], ['arr_num_chars' => []]);
        (new ReflectionMethod($ui, 'renderRedirectSection'))->invoke($ui, 'post');
    }
    $markup = ob_get_clean();
    echo json_encode(compact('markup', 'scripts', 'styles', 'localized'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
