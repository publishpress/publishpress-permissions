<?php
// Run: php tests/unit/teaser-management-authorization.php
define('ABSPATH', __DIR__ . '/../../');
define('PRESSPERMIT_TEASER_CLASSPATH', ABSPATH . 'modules/presspermit-teaser/classes/Permissions/Teaser');
require ABSPATH . 'modules/presspermit-teaser/classes/Permissions/TeaserHooksAdmin.php';
class Response extends RuntimeException {
    public $body, $status;
    public function __construct($body, $status) { $this->body = $body; $this->status = $status; }
}
function current_user_can($cap, $id = 0) { return $cap === 'pp_manage_teaser' || $id === 1; }
function wp_verify_nonce(...$args) { return true; }
function sanitize_key($value) { return $value; }
function sanitize_text_field($value) { return $value; }
function wp_unslash($value) { return $value; }
function get_post_types(...$args) { return ['post']; }
function wp_send_json($body, $status = 200) { throw new Response($body, $status); }
function get_post($id) { return (object) ['ID' => $id, 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => 'SECRET']; }
function get_permalink($id) { return '/post/' . $id; }
class WP_Query {
    public $posts;
    public function __construct($args) {
        if ($args['suppress_filters'] || $args['required_operation'] !== 'read' || !$args['skip_teaser']) throw new LogicException('Search bypassed permission clauses');
        $this->posts = [(object) ['ID' => 1, 'post_title' => 'Allowed'], (object) ['ID' => 2, 'post_title' => 'SECRET']];
    }
}
$screen = (new ReflectionClass('PublishPress\Permissions\TeaserHooksAdmin'))->newInstanceWithoutConstructor();
$_GET = ['nonce' => 'valid', 'post_id' => 2, 'post_type' => 'post'];
try { $screen->getTeaserPreviewPost(); } catch (Response $r) {
    if ($r->status !== 404 || $r->body !== 'Error') throw new LogicException('Denied preview disclosed data');
}
foreach ([false, true] as $body_search) {
    $_GET = ['nonce' => 'valid', 'search' => 'secret', 'post_type' => 'post', 'search_content' => $body_search];
    try { $screen->searchPosts(); } catch (Response $r) {
        if ($r->status !== 200 || count($r->body) !== 1 || $r->body[0]->ID !== 1) throw new LogicException('Search disclosed restricted content');
    }
}
echo "OK: denied previews and title/body search disclosure checks\n";
