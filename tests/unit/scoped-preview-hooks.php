<?php
define('ABSPATH', dirname(__DIR__,2).'/');
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function apply_filters($hook,$value,...$args){
    if($hook==='presspermit_public_preview_post')return !empty($args[0]->validatedMain) ? 12 : 0;
    if($hook==='presspermit_posts_teaser')return ['filtered'];
    return $value;
}
class DB {public $posts='posts';function prepare($query,$id){return sprintf($query,$id);}}
$wpdb=new DB;
require ABSPATH.'classes/PublishPress/Permissions/PostFilters.php';
$filter=(new ReflectionClass('PublishPress\Permissions\PostFilters'))->newInstanceWithoutConstructor();
$query=(object)['validatedMain'=>true,'request'=>'SELECT'];
$clauses=['where'=>' AND status = 1'];
$result=$filter->fltPostsClauses($clauses,$query);
check(strpos($result['where'],'posts.ID = 12')!==false,'preview grant did not constrain the row');
$post=(object)['ID'=>12];
check($filter->fltThePosts([$post],$query)===[$post],'bound post was teased');
$query->validatedMain=false;
check($filter->fltThePosts([$post],$query)===['filtered'],'secondary query bypassed teaser');
$query->validatedMain=true;
check($filter->fltThePosts([(object)['ID'=>99]],$query)===['filtered'],'unbound post bypassed teaser');
require ABSPATH.'modules/presspermit-teaser/classes/Permissions/Teaser/PostFilters.php';
$teaser=(new ReflectionClass('PublishPress\Permissions\Teaser\PostFilters'))->newInstanceWithoutConstructor();
check($teaser->fltPostsFrontEndResults([$post],$query)===[$post],'bound result was altered');
$wp_query=$query;
check($teaser->fltTheContent('bound preview')==='bound preview','bound content was altered');
echo "OK: scoped SQL and teaser integration hooks\n";
