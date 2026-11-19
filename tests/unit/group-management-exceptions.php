<?php
define('ABSPATH',dirname(__DIR__,2).'/');
define('PRESSPERMIT_CLASSPATH',ABSPATH.'classes/PublishPress/Permissions');
require PRESSPERMIT_CLASSPATH.'/Admin.php';
require PRESSPERMIT_CLASSPATH.'/PermissionsAdmin.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function current_user_can($cap,...$args){return $cap==='edit_user'&&$args[0]===12;}
function get_userdata($id){return $id===12?(object)['ID'=>$id]:false;}
function is_multisite(){return true;}
function is_user_member_of_blog($id){return true;}
function is_super_admin(){return $GLOBALS['network'];}
function post_type_exists($type){return false;}
function taxonomy_exists($type){return false;}
function presspermit(){return $GLOBALS['pp'];}
class GroupExceptionAdmin extends PublishPress\Permissions\Admin {
    function bulkRolesEnabled(){return true;}
    function canSetExceptions($op,$type,$args=[]){return $GLOBALS['can_set']&&$op==='manage';}
    function getOperationObject($op,$type=''){return $op==='manage';}
}
class ManagedGroups {
    function groupTypeExists($type){return in_array($type,['pp_group','pp_net_group'],true);}
    function getGroup($id,$type){return $id===7?(object)['ID'=>$id]:false;}
    function userCan($cap,$id,$type){return !empty($GLOBALS['can_edit_group']);}
}
$admin=new GroupExceptionAdmin;$network=false;$can_set=true;$can_edit_group=true;
$pp=new class($admin){private $admin;function __construct($admin){$this->admin=$admin;}function admin(){return $this->admin;}function groups(){return new ManagedGroups;}};
$args=['operation'=>'manage','mod_type'=>'additional','for_item_type'=>'pp_group','for_item_source'=>'pp_group','via_item_source'=>'pp_group','item_id'=>7];
check($admin->canEditAgentException('user',12,$args),'authorized local group management exception rejected');
$can_set=false;check(!$admin->canEditAgentException('user',12,$args),'missing exception authority accepted');$can_set=true;
$can_edit_group=false;check(!$admin->canEditAgentException('user',12,$args),'uneditable group accepted');$can_edit_group=true;
$args['item_id']=99;check(!$admin->canEditAgentException('user',12,$args),'nonexistent group accepted');$args['item_id']=7;
$args['for_item_source']='post';check(!$admin->canEditAgentException('user',12,$args),'mixed source domain accepted');$args['for_item_source']='pp_group';
$args['via_item_source']=$args['for_item_source']=$args['for_item_type']='pp_net_group';
check(!$admin->canEditAgentException('user',12,$args),'local delegate reached network group');
$network=true;check($admin->canEditAgentException('user',12,$args),'authorized network administrator rejected');
$args['via_item_source']='custom_unregistered';check(!$admin->canEditAgentException('user',12,$args),'unknown source accepted');
echo "PASS group-management exception source domains and authorization\n";
