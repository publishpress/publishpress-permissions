<?php
// Run: php tests/unit/term-group-search-authorization.php
// Group search on the term edit screen (pp_exception_term): the term must exist, the user must be
// able to edit it and to set exceptions for the operation, with the same arguments ItemSave uses.
$root = sys_get_temp_dir() . '/pp-term-group-test-' . uniqid();
mkdir($root . '/wp-admin/includes', 0777, true);
file_put_contents($root . '/wp-admin/includes/user.php', '<?php');
define('ABSPATH', $root . '/');
class Fields {
    public static function is_GET($key) { return isset($_GET[$key]); }
    public static function GET_key($key) { return $_GET[$key] ?? ''; }
    public static function GET_int($key) { return (int) ($_GET[$key] ?? 0); }
    public static function empty_GET($key) { return empty($_GET[$key]); }
    public static function sanitizeEntry($value) { return $value; }
}
class_alias('Fields', 'PublishPress\Permissions\UI\PWP');
class_alias('Fields', 'PressShack\LibWP');
function check_ajax_referer(...$args) {}
function wp_die(...$args) { throw new RuntimeException('denied'); }
function sanitize_text_field($value) { return $value; }
function wp_unslash($value) { return $value; }
function apply_filters($name, $value, ...$args) { return $value; }
function esc_attr($value) { return $value; }
function esc_html($value) { return $value; }
function is_wp_error($value) { return false; }
function taxonomy_exists($value) { return $value === 'category'; }
function post_type_exists($value) { return $value === 'post'; }
function get_term_by($field, $value) {
    return ('term_taxonomy_id' === $field && 7 === $value) ? (object) ['term_id' => 5, 'taxonomy' => 'category'] : false;
}
function current_user_can($cap, ...$args) { return $GLOBALS['can_edit_term'] && 'edit_term' === $cap && 5 === $args[0]; }
function presspermit() { return $GLOBALS['pp']; }
$groups = new class {
    public $args;
    public function groupTypeExists($type) { return $type === 'pp_group'; }
    public function getGroups($type, $args) {
        $this->args = $args;
        return [11 => (object) ['ID' => 11, 'name' => 'Editors']];
    }
};
$admin = new class {
    public $calls = [];
    public function canSetExceptions($operation, $for_item_type, $args) {
        $this->calls[] = [$operation, $for_item_type, $args];
        return $GLOBALS['can_set'];
    }
};
$GLOBALS['pp'] = new class($groups, $admin) {
    private $groups, $admin;
    public function __construct($groups, $admin) { $this->groups = $groups; $this->admin = $admin; }
    public function groups() { return $this->groups; }
    public function admin() { return $this->admin; }
};
require __DIR__ . '/../../classes/PublishPress/Permissions/UI/AgentsAjax.php';

function run_search($term, $op, $for_type, $can_edit_term = true, $can_set = true) {
    $_GET = [
        'pp_agent_search' => 'edi', 'pp_agent_type' => 'pp_group', 'pp_operation' => $op,
        'pp_topic' => "$op:$for_type:pp_group", 'pp_exception_term' => $term, 'pp_search_page' => 1,
    ];
    $GLOBALS['can_edit_term'] = $can_edit_term;
    $GLOBALS['can_set'] = $can_set;
    $GLOBALS['pp']->admin()->calls = [];
    ob_start();
    try {
        new PublishPress\Permissions\UI\AgentsAjax();
        return ob_get_clean();
    } catch (RuntimeException $error) {
        ob_end_clean();
        if ($error->getMessage() !== 'denied') throw $error;
        return 'denied';
    }
}

try {
    if (run_search(99, 'edit', 'post') !== 'denied') throw new LogicException('Unknown term accepted');
    if (run_search(7, 'edit', 'post', false) !== 'denied') throw new LogicException('Term the user cannot edit accepted');
    if (run_search(7, 'edit', 'post', true, false) !== 'denied') throw new LogicException('Operation the user cannot set accepted');
    if (run_search(7, 'edit', 'unknown-type') !== 'denied') throw new LogicException('Unknown post type accepted');

    $out = run_search(7, 'edit', 'post');
    if (strpos($out, '<option value="11">Editors</option>') === false) throw new LogicException('Group not listed');
    [$op, $for_type, $args] = $admin->calls[0];
    if ($op !== 'edit' || $for_type !== 'post' || $args['via_item_source'] !== 'term' || $args['via_item_type'] !== 'category'
        || $args['for_item_source'] !== 'post' || $args['item_id'] !== 7) {
        throw new LogicException('Post operation checked with the wrong arguments');
    }
    if ($groups->args['skip_meta_types'] !== 'wp_role' || $groups->args['limit'] !== 21) throw new LogicException('Unbounded group query');

    run_search(7, 'edit', ''); // universal permissions (all post types)
    if ($admin->calls[0][1] !== '') throw new LogicException('Universal permissions checked for a post type');

    run_search(7, 'manage', 'category');
    [$op, $for_type, $args] = $admin->calls[0];
    if ($for_type !== 'category' || $args['for_item_source'] !== 'term') throw new LogicException('Term operation checked as a post operation');

    echo "OK: term group search requires an editable term and permission to set the operation\n";
} finally {
    unlink($root . '/wp-admin/includes/user.php');
    rmdir($root . '/wp-admin/includes'); rmdir($root . '/wp-admin'); rmdir($root);
}
