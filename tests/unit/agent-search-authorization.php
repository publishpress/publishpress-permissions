<?php
// Run: php tests/unit/agent-search-authorization.php
$root = sys_get_temp_dir() . '/pp-agent-test-' . uniqid();
mkdir($root . '/wp-admin/includes', 0777, true);
file_put_contents($root . '/wp-admin/includes/user.php', '<?php');
define('ABSPATH', $root . '/');
define('PP_USER_SEARCH_LIMIT', 999999);
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
function absint($value) { return abs((int) $value); }
function apply_filters($name, $value, ...$args) { return $value; }
function taxonomy_exists($value) { return false; }
function post_type_exists($value) { return $value === 'post'; }
function get_post_type_object($type) { return (object) ['cap' => (object) ['edit_others_posts' => 'edit_others_posts']]; }
function current_user_can($cap, ...$args) { return $GLOBALS['authorized'] && ($cap !== 'edit_user' || $args[0] === 12); }
function get_userdata($id) { return $id > 0; }
function is_multisite() { return false; }
function presspermit() { return $GLOBALS['pp']; }
class WP_User_Query {
    public function __construct($args) { $GLOBALS['query'] = $args; }
    public function get_results() { return []; }
}
$groups = new class {
    public function groupTypeExists($type) { return $type === 'pp_group'; }
    public function getGroups(...$args) { return []; }
    public function getGroupsForUser(...$args) { return []; }
};
$GLOBALS['pp'] = new class($groups) {
    private $groups;
    public function __construct($groups) { $this->groups = $groups; }
    public function groups() { return $this->groups; }
    public function admin() { return new stdClass(); }
};
require __DIR__ . '/../../classes/PublishPress/Permissions/UI/AgentsAjax.php';
try {
    foreach ([['pp_group', 'unknown', 0], ['unknown-type', 'unknown', 0], ['pp_group', 'pp_group', 13]] as [$type, $topic, $id]) {
        $_GET = ['pp_agent_search' => '', 'pp_agent_type' => $type, 'pp_topic' => $topic, 'pp_agent_id' => $id];
        $GLOBALS['authorized'] = true;
        try {
            new PublishPress\Permissions\UI\AgentsAjax();
            throw new LogicException('Unauthorized selector accepted');
        } catch (RuntimeException $error) {
            if ($error->getMessage() !== 'denied') throw $error;
        }
    }
    $_GET = ['pp_agent_search' => '', 'pp_agent_type' => 'user', 'pp_topic' => 'select-author', 'pp_context' => 'post', 'pp_search_page' => 2];
    $GLOBALS['authorized'] = true;
    ob_start();
    new PublishPress\Permissions\UI\AgentsAjax();
    ob_end_clean();
    if ($GLOBALS['query']['number'] !== 50 || $GLOBALS['query']['offset'] !== 50) throw new LogicException('Unbounded or unpaginated query');
    echo "OK: unknown topics/types, foreign target, and search bounds\n";
} finally {
    unlink($root . '/wp-admin/includes/user.php');
    rmdir($root . '/wp-admin/includes'); rmdir($root . '/wp-admin'); rmdir($root);
}
