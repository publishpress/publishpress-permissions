<?php
// Run: php tests/unit/user-group-target-authorization.php
define('ABSPATH', __DIR__ . '/../../');
require ABSPATH . 'classes/PublishPress/Permissions/UserGroupsUpdate.php';
function get_userdata($id) { return in_array($id, [12, 13, 14], true); }
function current_user_can($cap, $id) { return $cap === 'edit_user' && $id !== 13; }
function is_multisite() { return true; }
function is_super_admin() { return false; }
function is_user_member_of_blog($id) { return $id !== 14; }
function esc_html__($text, ...$args) { return $text; }
function wp_die(...$args) { throw new RuntimeException('denied'); }
function check_admin_referer($action, $field) {
    if ($action !== $GLOBALS['nonce_action']) throw new RuntimeException('nonce denied');
}
class Fields { public static function empty_POST($key) { return empty($_POST[$key]); } public static function is_POST($key) { return isset($_POST[$key]); } }
class_alias('Fields', 'PublishPress\Permissions\PWP');
function is_main_site() { return true; }
function presspermit() { return new class {
    public function groups() { return new class { public function getGroupTypes($args) { return ['pp_group']; } }; }
}; }
foreach ([0, 13, 14, 999] as $id) {
    if (PublishPress\Permissions\UserGroupsUpdate::canEditUser($id)) throw new LogicException('Unauthorized target accepted');
    foreach (['addUserGroups', 'removeUserGroups'] as $method) {
        try { PublishPress\Permissions\UserGroupsUpdate::$method($id); throw new LogicException('Unauthorized mutation'); }
        catch (RuntimeException $error) { if ($error->getMessage() !== 'denied') throw $error; }
    }
}
if (!PublishPress\Permissions\UserGroupsUpdate::canEditUser(12)) throw new LogicException('Authorized target denied');
$_POST = ['pp_group' => [3]];
$GLOBALS['nonce_action'] = 'pp-user-profile-groups_13';
foreach (['addUserGroups', 'removeUserGroups'] as $method) {
    try { PublishPress\Permissions\UserGroupsUpdate::$method(12); throw new LogicException('Cross-target nonce accepted'); }
    catch (RuntimeException $error) { if ($error->getMessage() !== 'nonce denied') throw $error; }
}
echo "OK: target capability, multisite scope and cross-target nonce checks\n";
