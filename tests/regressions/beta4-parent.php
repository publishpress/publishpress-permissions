<?php
define('ABSPATH','/');
define('PRESSPERMIT_COLLAB_CLASSPATH',dirname(__DIR__, 2).'/modules/presspermit-collaboration/classes/Permissions/Collab');
$uid=7;$blog=1;$copy=true;$read=true;$current_user=(object)['ID'=>7];
class PPParent {function filteringEnabled(){return true;}function isUserUnfiltered(...$a){return false;}function getEnabledPostTypes(){return ['page'];}function getUser(){return $this;}function getExceptionPosts(...$a){return [];}function getOption($s){return $s==='lock_top_pages'?'1':false;}}
function presspermit(){static $p;return $p??=new PPParent;}
function is_post_type_hierarchical($s){return $s==='page';}function get_post_type_object($s){return (object)['hierarchical'=>true];}
function get_post($id){return $id===100?(object)['ID'=>100,'post_parent'=>0,'post_type'=>'page','post_status'=>'publish']:null;}
function get_current_user_id(){return $GLOBALS['uid'];}function get_current_blog_id(){return $GLOBALS['blog'];}
function current_user_can($cap,$id){return $cap==='copy_post'?$GLOBALS['copy']:$GLOBALS['read'];}
function esc_html__($s,$domain){return $s;}class WP_Error{function __construct(...$a){}}
function wp_die(...$a){throw new RuntimeException('Parent forbidden');}
require PRESSPERMIT_COLLAB_CLASSPATH.'/../Collab.php';class_alias('PublishPress\\Permissions\\Collab','PublishPress\\Permissions\\Collab\\Collab');
require PRESSPERMIT_COLLAB_CLASSPATH.'/AdminFilters.php';
$f=(new ReflectionClass('PublishPress\\Permissions\\Collab\\AdminFilters'))->newInstanceWithoutConstructor();
$src=['post_type'=>'page','post_parent'=>0,'post_status'=>'draft','post_mime_type'=>'draft-revision','comment_count'=>100,'post_author'=>7];
// wp_insert_post() does not pass comment_count to wp_insert_post_data.
$data=$src;unset($data['comment_count']);
$count=0;function check($allowed,$data,$arr=[],$update=false){global $f,$count;$count++;try{$f->fltAuthorizeParent($data,$arr,[],$update);$got=true;}catch(RuntimeException $e){$got=false;}if($got!==$allowed)throw new Exception('Parent case '.$count);}
check(false,$data);$bad_src=$src;$bad_src['comment_count']=101;$f->authorizeRevisionCopy(100,$bad_src);check(false,$data);
$f->authorizeRevisionCopy(100,$src);check(true,$data);check(false,$data);
$f->authorizeRevisionCopy(100,$src);$changed=$data;$changed['post_parent']=55;check(false,$changed);check(false,$data);
$copy=false;$f->authorizeRevisionCopy(100,$src);check(false,$data);$copy=true;
$read=false;$f->authorizeRevisionCopy(100,$src);check(false,$data);$read=true;
$f->authorizeRevisionCopy(100,$src);$blog=2;check(false,$data);$blog=1;
$f->authorizeRevisionCopy(100,$src);$uid=8;check(false,$data);$uid=7;
$changed=$src;$changed['post_mime_type']='image/jpeg';$f->authorizeRevisionCopy(100,$changed);unset($changed['comment_count']);check(false,$changed);
$changed=$src;$changed['post_author']=8;$f->authorizeRevisionCopy(100,$changed);unset($changed['comment_count']);check(false,$changed);
check(true,$data,['ID'=>100],true);
$ordinary=$data;$ordinary['post_mime_type']='';check(false,$ordinary);
$f->authorizeRevisionCopy(100,$src);check(false,$ordinary);check(false,$data);
echo "$count revision ancestry and spoofing checks passed\n";

