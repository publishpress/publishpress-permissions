<?php
// Run: php tests/unit/rest-edit-collection-gate.php
define('ABSPATH', __DIR__ . '/../../');
define('REST_REQUEST', true);
require ABSPATH . 'classes/PublishPress/Permissions/PostFilters.php';
class Fields {
    public static function empty_REQUEST($key) { return empty($_REQUEST[$key]); }
    public static function REQUEST_key($key) { return $_REQUEST[$key] ?? ''; }
}
class_alias('Fields', 'PublishPress\Permissions\PWP');
function is_admin() { return false; }
function apply_filters($hook,$value,...$args) { return $value; }
function presspermit() { return $GLOBALS['permission_service']; }
$permission_service = new class {
    public $checks = 0;
    public function isUserUnfiltered($id, $args) { $this->checks++; return true; }
};
$current_user = (object) ['ID' => 12]; $pagenow = '';
$filters = (new ReflectionClass('PublishPress\Permissions\PostFilters'))->newInstanceWithoutConstructor();
$_POST = []; $_SERVER['REQUEST_METHOD'] = 'GET';
foreach (['edit', 'view'] as $context) {
    $_REQUEST = ['context' => $context, 'include' => '34'];
    $filters->fltPostsClauses(['where' => 'ID = 34'], (object) ['query_vars' => ['post__in' => [34]]]);
}
if ($permission_service->checks !== 2) throw new RuntimeException('REST context bypassed the permission service');
echo "OK: edit and view collections both reach the permission service\n";
