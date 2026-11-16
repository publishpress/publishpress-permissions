<?php
define('ABSPATH','/');define('PRESSPERMIT_CLASSPATH',dirname(__DIR__, 2).'/classes/PublishPress/Permissions');
$hooks=[];$did=[];$registered=false;$assignments=[];$redirect='';$current_user=(object)['ID'=>7];$deny=false;
function add_action($h,$cb,$priority=10,$accepted=1){$GLOBALS['hooks'][$h][]=$cb;}
function fire($h){$GLOBALS['did'][$h]=true;foreach($GLOBALS['hooks'][$h]??[] as $cb)$cb();}
function did_action($h){return !empty($GLOBALS['did'][$h]);}
function do_action(...$a){}
function apply_filters($h,$v,...$args){return $v;}
function presspermitPluginPage(){return 'presspermit-edit-permissions';}
function wp_unslash($s){return $s;}function sanitize_key($s){return $s;}function sanitize_text_field($s){return $s;}function esc_html__($s,$d){return $s;}function __($s,$d){return $s;}
function check_admin_referer(...$a){return true;}function taxonomy_exists($s){return $s==='late_tax'&&$GLOBALS['registered'];}function post_type_exists($s){return $s==='post';}
function update_user_option(...$a){}function wp_redirect($s){$GLOBALS['redirect']=$s;throw new RuntimeException('redirect');}
function add_query_arg($k,$v,$url){return $url.'&'.$k.'='.$v;}function esc_url_raw($s){return $s;}
function is_wp_error($v){return $v instanceof WP_Error;}class WP_Error{function __construct(...$a){}}
class PWPH {static function REQUEST_key($k){return $_REQUEST[$k]??'';}static function REQUEST_int($k){return (int)($_REQUEST[$k]??0);}static function REQUEST_url($k){return '';}static function empty_POST(){return false;}static function is_POST($k,$v=null){return isset($_POST[$k])&&(!$v||in_array($_POST[$k],(array)$v,true));}static function empty_REQUEST($k){return empty($_REQUEST[$k]);}static function is_REQUEST($k){return isset($_REQUEST[$k]);}static function sanitizeCSV($s){return $s;}static function termidToTtid($id,$tax){return 110;}}
class_alias('PWPH','PublishPress\\Permissions\\UI\\Handlers\\PWP');
class PPH {public $errors;function admin(){return $this;}function groups(){return $this;}function bulkRolesEnabled(){return true;}function groupTypeEditable($t){return false;}function canEditAgentException($type,$id,$args){return taxonomy_exists($args['via_item_type'])&&!$GLOBALS['deny'];}function assignExceptions($agents,$type,$args){if($agents)$GLOBALS['assignments'][]=$args;}}
function presspermit(){static $p;return $p??=new PPH;}
$_POST=['action'=>'pp_updateexceptions','pp_add_exception'=>[['mod_type'=>'include','item_id'=>10,'operation'=>'read','via_type'=>'late_tax','for_type'=>'post','for_item'=>1]]];$_REQUEST=$_POST+['agent_type'=>'user','agent_id'=>7];
require PRESSPERMIT_CLASSPATH.'/UI/Handlers/Admin.php';
PublishPress\Permissions\UI\Handlers\Admin::handleRequest();fire('presspermit_user_init');fire('presspermit_user_init');
if(count($hooks['wp_loaded']??[])!==1||$assignments)throw new Exception('Handler did not defer exactly once');
$registered=true;try{fire('wp_loaded');}catch(RuntimeException $e){if($e->getMessage()!=='redirect')throw $e;}
if($deny){if($assignments||$redirect||!is_wp_error(presspermit()->errors))throw new Exception('Rejected mutation reported success');}
elseif(count($assignments)!==1||!str_contains($redirect,'pp_exc=1'))throw new Exception('Late taxonomy mutation not saved');
echo ($deny?'Denied mutation feedback':'Late taxonomy save')." handler checks passed\n";
