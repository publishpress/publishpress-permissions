<?php
// Run: php tests/unit/bulk-exception-authorization.php
define('ABSPATH', __DIR__ . '/../../');
define('PRESSPERMIT_CLASSPATH', ABSPATH . 'classes/PublishPress/Permissions');
require ABSPATH . 'classes/PublishPress/Permissions/Admin.php';
require ABSPATH . 'classes/PublishPress/Permissions/UI/AgentPermissionsAjax.php';
class TestAdmin extends PublishPress\Permissions\Admin {
    public $permitted = [];
    public function bulkRolesEnabled() { return true; }
    public function canEditPermissionAgent($type, $id) { return $type === 'user' && (int) $id === 12; }
    public function canSetExceptions($op, $type, $args = []) { return in_array($op, $this->permitted, true); }
    public function getOperationObject($op, $type = '') {return in_array($op, ['read', 'edit'], true);}
}
class RequestFields {
    public static $values = ['agent_type' => 'user', 'agent_id' => 12, 'pp_ajax_agent_permissions' => 'exceptions_remove'];
    public static function GET_key($key) { return self::$values[$key]; }
    public static function GET_int($key) { return (int) self::$values[$key]; }
}
class_alias('RequestFields', 'PublishPress\Permissions\UI\PWP');
function absint($id) { return abs((int) $id); }
function post_type_exists($type) {return $type === 'post';}
function taxonomy_exists($type) {return false;}
function get_post($id) {return $id === 4 ? (object)['ID' => 4, 'post_type' => 'post'] : false;}
function current_user_can($cap, $id = 0) {return $cap === 'edit_post' && $id === 4;}
function presspermit() { return $GLOBALS['pp']; }
$admin = new TestAdmin();
$GLOBALS['pp'] = new class($admin) {
    private $admin;
    public function __construct($admin) { $this->admin = $admin; }
    public function admin() { return $this->admin; }
};
$args = ['operation' => 'read', 'mod_type' => 'additional', 'for_item_type' => 'post', 'for_item_source' => 'post', 'via_item_source' => 'post', 'item_id' => 4];
if ($admin->canEditAgentException('user', 12, $args)) throw new RuntimeException('Bulk role capability allowed an exception');
$admin->permitted = ['read'];
if (!$admin->canEditAgentException('user', 12, $args)) throw new RuntimeException('Authorized exception failed');
if ($admin->canEditAgentException('user', 13, $args)) throw new RuntimeException('Foreign target allowed');
$wpdb = new class($args) {
    public $ppc_exception_items = 'items', $ppc_exceptions = 'exceptions', $rows;
    public function __construct($args) {
        $this->rows = [(object) array_merge($args, ['agent_type' => 'user', 'agent_id' => 12, 'eitem_id' => 1]),
            (object) array_merge($args, ['agent_type' => 'user', 'agent_id' => 13, 'eitem_id' => 2])];
    }
    public function get_results($sql) { return $this->rows; }
};
$class = new ReflectionClass('PublishPress\Permissions\UI\AgentPermissionsAjax');
$ajax = $class->newInstanceWithoutConstructor();
$method = $class->getMethod('editableEitemIDs');
if ($method->invoke($ajax, [1, 2]) !== [1]) throw new RuntimeException('Foreign exception ID accepted');
RequestFields::$values['pp_ajax_agent_permissions'] = 'exceptions_mirror_edit';
if ($method->invoke($ajax, [1]) !== []) throw new RuntimeException('Unauthorized mirror operation accepted');
$admin->permitted[] = 'edit';
if ($method->invoke($ajax, [1]) !== [1]) throw new RuntimeException('Authorized mirror rejected');
echo "OK: bulk exception authorization\n";
