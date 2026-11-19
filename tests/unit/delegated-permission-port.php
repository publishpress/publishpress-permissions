<?php
define('ABSPATH', dirname(__DIR__, 2) . '/');
define('PRESSPERMIT_CLASSPATH', ABSPATH . 'classes/PublishPress/Permissions');
define('PRESSPERMIT_READ_PUBLIC_CAP', 'read');
define('PRESSPERMIT_MIN_DATE_STRING', '0000-00-00 00:00:00');
define('PRESSPERMIT_MAX_DATE_STRING', '9999-12-31 23:59:59');
define('PRESSPERMIT_LEGACY_HOOKS', false);
function check($ok, $message) {if (!$ok) throw new RuntimeException($message);}
$caps=['edit_posts'=>true,'pp_manage_permissions'=>true];$networkAdmin=false;
function current_user_can($cap,...$args) {return !empty($GLOBALS['caps'][$cap]);}
function is_super_admin() {return $GLOBALS['networkAdmin'];}
function is_multisite() {return true;}
function is_user_member_of_blog($id) {return $id!==99;}
function get_current_user_id() {return 5;}
function get_userdata($id) {return $id===5 || $id===99 ? (object)['ID'=>$id]:false;}
function post_type_exists($type) {return $type==='page';}
function taxonomy_exists($type) {return $type==='category';}
function get_post_type_object($type) {return $type==='page' ? (object)['cap'=>(object)['edit_published_posts'=>'edit_posts']]:false;}
function get_post_status_object($status) {return $status==='private' ? (object)['name'=>$status]:false;}
function get_post($id) {return $id===12 ? (object)['ID'=>$id,'post_type'=>'page']:false;}
function get_term_by(...$args) {return false;}
function apply_filters($name,$value,...$args) {return $value;}
function do_action(...$args) {}
function current_time(...$args) {return '2026-11-07 00:00:00';}
function presspermit() {return $GLOBALS['pp'];}
class Subset {static function subset($args,$keys){return array_intersect_key($args,array_fill_keys($keys,true));}}
class_alias('Subset','PublishPress\Permissions\DB\Arr');
require PRESSPERMIT_CLASSPATH.'/Admin.php';
require PRESSPERMIT_CLASSPATH.'/PermissionsAdmin.php';
class AdminFixture extends PublishPress\Permissions\Admin {
    function canSetAnyPostPermissions($type=''){return true;}
    function getOperationObject($op,$type=''){return in_array($op,['read','edit'],true);}
}
class GroupsFixture {
    function groupTypeExists($type){return in_array($type,['pp_group','pp_net_group'],true);}
    function getGroup($id,$type){return (object)['ID'=>$id];}
    function userCan(...$args){return true;}
}
class PPFixture {
    public $role_defs,$administrator=false;
    function __construct() {$this->role_defs=(object)['disabled_pattern_role_types'=>[], 'pattern_roles'=>['editor'=>(object)['labels'=>(object)['singular_name'=>'Editor']]],'direct_roles'=>[]];}
    function admin(){return $GLOBALS['admin'];}
    function groups(){return new GroupsFixture;}
    function isUserAdministrator(){return $this->administrator;}
    function getOption($key){return $key==='non_admins_set_read_exceptions';}
    function getRoleCaps($role){return ['edit_posts'=>true];}
}
$admin=new AdminFixture;$pp=new PPFixture;
use PublishPress\Permissions\PermissionsAdmin as Policy;
check(!Policy::canAdminAgent('user',5),'uneditable recipient accepted');
$caps['edit_user']=true;
check(Policy::canAdminAgent('user',5),'editable recipient denied');
check(!Policy::canAdminAgent('user',99),'cross-site recipient accepted');
check(!Policy::canAdminAgent('user',0),'missing recipient accepted');
check(!Policy::canAdminAgent('pp_net_group',5),'local authority reached a network group');
check(!Policy::canAssignRole('administrator','page'),'crafted site role accepted');
check(Policy::canAssignRole('editor:post:page','page'),'permitted role rejected');
$caps['edit_posts']=false;
check(!Policy::canAssignRole('editor:post:page','page'),'role exceeded caller capability ceiling');
$caps['pp_set_view_permissions']=true;
$args=['operation'=>'read','mod_type'=>'additional','for_item_source'=>'post','for_item_type'=>'page','via_item_source'=>'post','item_id'=>12];
check(!$admin->canEditAgentException('user',5,$args),'uneditable exception object accepted');
$caps['edit_post']=true;
check($admin->canEditAgentException('user',5,$args),'permitted exception rejected');
$args['mod_type']='arbitrary';check(!$admin->canEditAgentException('user',5,$args),'invalid mode accepted');
$args['mod_type']='additional';$args['item_id']=0;
check(!$admin->canEditAgentException('user',5,$args),'type-wide exception exceeded delegated scope');
require PRESSPERMIT_CLASSPATH.'/DB/GroupUpdate.php';
class MembersDB {
    public $pp_group_members='members',$members_table,$inserted=[],$updated=[];
    function prepare($query,...$args){return vsprintf($query,$args);}
    function get_col($query){return [];}
    function get_results($query){return [];}
    function insert($table,$data){$this->inserted[]=$data['user_id'];}
    function update($table,$data,$where){$this->updated[]=$where['user_id'];}
}
$wpdb=new MembersDB;
PublishPress\Permissions\DB\GroupUpdate::addGroupUser(2,[5,7]);
check($wpdb->inserted===[7],'delegate enrolled themselves');
PublishPress\Permissions\DB\GroupUpdate::updateGroupUser(2,[5,7],['status'=>'active']);
check($wpdb->updated===[7],'delegate reactivated their own membership');
$caps['pp_manage_members']=true;
PublishPress\Permissions\DB\GroupUpdate::addGroupUser(2,[5]);
check($wpdb->inserted===[7,5],'sitewide membership administrator denied');
echo "OK: role ceilings, exact recipients, exception objects, network groups and self-enrollment\n";
