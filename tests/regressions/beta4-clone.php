<?php
define('ABSPATH','/');define('PRESSPERMIT_CLASSPATH',dirname(__DIR__, 2).'/classes/PublishPress/Permissions');
define('OBJECT_K','OBJECT_K');$current_user=(object)['ID'=>7];$deny=($argv[1]??'')==='denied';$redirect='';
class PWPClone {static function REQUEST_key($k){return $_REQUEST[$k]??'';}static function REQUEST_int($k){return (int)($_REQUEST[$k]??0);}static function is_REQUEST($k){return isset($_REQUEST[$k]);}static function REQUEST_url($k){return '';}}
class_alias('PWPClone','PublishPress\\Permissions\\UI\\Handlers\\PWP');
function apply_filters($h,$v,...$args){return $v;}function check_admin_referer(...$a){}function do_action(...$a){}
function esc_html__($s,$d){return $s;}function __($s,$d){return $s;}function is_wp_error($v){return $v instanceof WP_Error;}class WP_Error{function __construct(...$a){}}
function wp_redirect($s){$GLOBALS['redirect']=$s;throw new RuntimeException('redirect');}function esc_url_raw($s){return $s;}function add_query_arg($k,$v,$url){return $url.'&'.$k.'='.$v;}
class PPClone {public $errors;function admin(){return $this;}function groups(){return $this;}function canEditPermissionAgent($type,$id){return $id!==15||!$GLOBALS['deny'];}function isUserAdministrator(){return true;}function bulkRolesEnabled(){return true;}function getGroup($id){return (object)['ID'=>$id,'metagroup_type'=>'','metagroup_id'=>''];}}
function presspermit(){static $p;return $p??=new PPClone;}
class DBClone {public $pp_groups='groups';public $ppc_roles='roles';public $ppc_exceptions='exceptions';public $ppc_exception_items='items';function prepare($sql,...$args){return $sql;}function get_row($sql){return (object)['ID'=>15,'metagroup_type'=>'wp_role'];}function get_col($sql){return [];}function get_results(...$args){return [];}}
$wpdb=new DBClone;
$_REQUEST=['action'=>'pp_updateclone','agent_type'=>'pp_group','agent_id'=>7,'pp_select_role'=>'editor'];
require PRESSPERMIT_CLASSPATH.'/UI/Handlers/AgentEdit.php';
try{new PublishPress\Permissions\UI\Handlers\AgentEdit;}catch(RuntimeException $e){if($e->getMessage()!=='redirect')throw $e;}
if($deny){if($redirect||!is_wp_error(presspermit()->errors))throw new Exception('False clone success');}
elseif(!str_contains($redirect,'pp_cloned=1'))throw new Exception('Successful clone not reported');
echo ($deny?'Rejected':'Authorized')." clone handler verified\n";
