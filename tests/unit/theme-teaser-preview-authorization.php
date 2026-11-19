<?php
define('ABSPATH', __DIR__);
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$root = dirname(__DIR__, 2);

$allowed=false;$logged=false;$nonce=false;$queried=12;
function is_user_logged_in(){global $logged;return $logged;}
function current_user_can($cap,...$args){global $allowed;return $cap==='pp_manage_teaser' || $allowed;}
function wp_verify_nonce(...$args){global $nonce;return $nonce;}
function sanitize_key($s){return $s;}
function wp_unslash($s){return $s;}
function get_queried_object_id(){global $queried;return $queried;}
function nocache_headers(){}
function wp_send_json($value,$status=200){throw new RuntimeException((string)$status);}
function get_post($id){return (object)['ID'=>$id,'post_status'=>'publish','post_type'=>'post','post_content'=>'secret'];}
function get_post_types(...$args){return ['post'];}
require $root.'/modules/presspermit-teaser/classes/Permissions/TeaserHooks.php';
$class=new ReflectionClass('PublishPress\Permissions\TeaserHooks');$object=$class->newInstanceWithoutConstructor();$method=$class->getMethod('getThemePreviewState');
$_GET=['pp_permissions_teaser_preview'=>'teaser','_pp_teaser_nonce'=>'test'];
check($method->invoke($object)==='','anonymous theme preview allowed');
$logged=true;check($method->invoke($object)==='','missing valid nonce allowed');
$nonce=true;check($method->invoke($object)==='','unreadable theme post allowed');
$allowed=true;check($method->invoke($object)==='teaser','authorized theme preview denied');
require $root.'/modules/presspermit-teaser/classes/Permissions/TeaserHooksAdmin.php';
$admin=(new ReflectionClass('PublishPress\Permissions\TeaserHooksAdmin'))->newInstanceWithoutConstructor();
$allowed=false;$_GET=['nonce'=>'test','post_id'=>12,'post_type'=>'post'];
try{$admin->getTeaserPreviewPost();throw new Exception('unreadable body returned');}catch(RuntimeException $e){check($e->getMessage()==='404','wrong unreadable-post response');}
echo "Teaser authentication, nonce and object-read tests passed\n";
