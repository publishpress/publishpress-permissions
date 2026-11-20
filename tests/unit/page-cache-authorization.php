<?php
// Run: php tests/unit/page-cache-authorization.php
define('ABSPATH', __DIR__ . '/../../');
class TestArray {
    public static function getPropertyArray(...$args) { return []; }
    public static function restorePropertyArray(...$args) {}
}
class_alias('TestArray', 'PublishPress\Permissions\Arr');
class Front { public static function isFront() { return false; } }
class_alias('Front', 'PublishPress\Permissions\PWP');
function presspermit() { return new class { public function getEnabledPostTypes() { return ['page']; } }; }
function is_admin() { return false; }
function is_post_type_hierarchical($type) { return true; }
function wp_parse_args($args, $defaults) { return array_merge($defaults, $args); }
function absint($value) { return abs((int) $value); }
function wp_parse_id_list($value) { return $value === '' ? [] : (array) $value; }
function get_post_stati(...$args) { return ['publish']; }
function sanitize_key($value) { return $value; }
function apply_filters($name, $value, ...$args) {
    if ($name === 'presspermit_posts_clauses') $value['where'] .= ' AND readable=' . (int) $GLOBALS['readable'];
    return $value;
}
function sanitize_post($post, $context) { return $post; }
function update_post_cache($posts) {}
function get_post($post) { return $post; }
function wp_cache_get(...$args) { throw new RuntimeException('Shared permission-result cache read'); }
function wp_cache_set(...$args) { throw new RuntimeException('Shared permission-result cache write'); }
function wp_cache_get_last_changed(...$args) { return 'unchanged'; }
$wpdb = new class {
    public $posts = 'posts', $queries = 0;
    public function prepare($sql, ...$args) { return vsprintf(str_replace('%s', "'%s'", $sql), $args); }
    public function get_results($sql) {
        $this->queries++;
        return strpos($sql, 'readable=1') !== false ? [(object) ['ID' => 7, 'post_title' => 'Restricted', 'post_parent' => 0]] : [];
    }
};
$current_user = (object) ['ID' => 1]; $pagenow = '';
require ABSPATH . 'classes/PublishPress/Permissions/PageFilters.php';
$args = ['sort_column' => 'post_title', 'depth' => 1, 'required_operation' => 'read'];
foreach ([true, false, true, false] as $readable) {
    $GLOBALS['readable'] = $readable;
    $pages = PublishPress\Permissions\PageFilters::fltGetPages([], $args);
    if (count($pages) !== (int) $readable) throw new RuntimeException('Stale authorization results');
}
if ($wpdb->queries !== 4) throw new RuntimeException('Permission changes did not recompute results');
echo "OK: privileged, anonymous, and changed-permission page lists recomputed\n";
