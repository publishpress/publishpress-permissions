<?php
define('ABSPATH','/');define('HOUR_IN_SECONDS',3600);
class DS_Public_Post_Preview {private static function is_public_preview_available($id){}}
$admin=false;$main=true;$preview=true;$feed=false;$exists=true;$ajax=false;$enabled=[100];$life=172800;
function is_admin(){return $GLOBALS['admin'];}function wp_doing_ajax(){return $GLOBALS['ajax'];}
function get_post($id){return $GLOBALS['exists']?(object)['ID'=>$id]:null;}
function get_query_var($name){return '';}
function get_option($key,$default=false){return $key==='public_post_preview'?$GLOBALS['enabled']:($key==='public_post_preview_expiration_time'?48:$default);}
function apply_filters($key,$v){return $GLOBALS['life'];}function wp_hash($value,$scheme){return hash_hmac('sha256',$value,'test');}
function wp_unslash($s){return $s;}function sanitize_key($s){return $s;}
class Query {function is_main_query(){return $GLOBALS['main'];}function is_preview(){return $GLOBALS['preview'];}function is_feed(){return $GLOBALS['feed'];}function get($s){return $s==='p'?$GLOBALS['target']:0;}}
require dirname(__DIR__, 2).'/classes/PublishPress/Permissions/Compat/PublicPreview.php';
$count=0;$target=100;$q=new Query;$_SERVER['REQUEST_METHOD']='GET';$_POST=[];
function token($id){return substr(wp_hash(ceil(time()/86400).'public_post_preview_'.$id,'nonce'),-12,10);}
function check($expected){global $q,$count;$count++;$actual=PublishPress\Permissions\Compat\PublicPreview::postForQuery(0,$q);if($actual!==$expected)throw new Exception('Preview case '.$count.': '.$actual);}
$_GET=['_ppp'=>token(100)];check(100);
$target=101;check(0);$target=100;$_GET['_ppp']='wrong';check(0);$_GET['_ppp']=token(100);
$main=false;check(0);$main=true;$preview=false;check(0);$preview=true;$feed=true;check(0);$feed=false;
$admin=true;check(0);$admin=false;$ajax=true;check(0);$ajax=false;
$_POST=['x'=>1];check(0);$_POST=[];$_SERVER['REQUEST_METHOD']='POST';check(0);
$_SERVER['REQUEST_METHOD']='HEAD';check(100);$enabled=[];check(0);$enabled=[100];
$exists=false;check(0);$exists=true;$life=0;check(0);$life=172800;
$_GET['_ppp']=[];check(0);
echo "$count post-bound preview checks passed\n";
