<?php
/** Run with: php tests/unit/teaser-preview-title.php */

define('ABSPATH', __DIR__ . '/../../');
define('PRESSPERMIT_TEASER_CLASSPATH', ABSPATH . 'modules/presspermit-teaser/classes/Permissions/Teaser');
require_once ABSPATH . 'modules/presspermit-teaser/classes/Permissions/TeaserHooksAdmin.php';

class PreviewJsonResponse extends RuntimeException
{
    public $data;
    public function __construct($data) { $this->data = $data; }
}
function wp_send_json($data, $status = null) { throw new PreviewJsonResponse($data); }
function current_user_can($capability) { return true; }
function wp_verify_nonce($nonce, $action) { return 'valid' === $nonce; }
function sanitize_key($value) { return $value; }
function sanitize_text_field($value) { return $value; }
function wp_unslash($value) { return $value; }
function get_bloginfo($field) { return 'UTF-8'; }
function get_post_types($args, $output) { return ['post']; }
function get_post($id) { return $GLOBALS['preview_post']; }
function get_the_title($post) { return $GLOBALS['filtered_title']; }
function get_permalink($id) { return 'https://example.test/post/' . (is_object($id) ? $id->ID : $id); }
function strip_shortcodes($text) { return $text; }
// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Standalone WordPress substitute.
function wp_strip_all_tags($text) { return strip_tags($text); }
function wpautop($text) { return '<p>' . $text . '</p>'; }

$wpdb = new class {
    public $prefix = 'wp_';
    public function prepare($query, ...$args) { return $query; }
    public function esc_like($text) { return $text; }
    public function get_results($query) {
        return [(object) ['ID' => 10, 'post_title' => $GLOBALS['stored_title']]];
    }
};
$handler = (new ReflectionClass(PublishPress\Permissions\TeaserHooksAdmin::class))->newInstanceWithoutConstructor();
$assertions = 0;
function check($condition, $label) {
    global $assertions;
    $assertions++;
    if (!$condition) throw new RuntimeException($label);
}
function response($handler, $method) {
    try {
        $handler->$method();
    } catch (PreviewJsonResponse $response) {
        return $response->data;
    }
    throw new RuntimeException('Missing JSON response');
}

foreach ([
    ['Canada&#8217;s ghost island', 'Canada’s ghost island'],
    ['A &amp; B &quot;quoted&quot; &#039;title&#039;', 'A & B "quoted" \'title\''],
    ['Caf&eacute; &mdash; 日本語', 'Café — 日本語'],
    ['&lt;img src=x onerror=alert(1)&gt;', '<img src=x onerror=alert(1)>'],
    ['Literal &amp;#8217; entity', 'Literal &#8217; entity'],
] as [$encoded, $decoded]) {
    $stored_title = $filtered_title = $encoded;
    $preview_post = (object) ['ID' => 10, 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => 'Intro<!--more-->Private', 'post_excerpt' => 'Excerpt'];
    $_GET = ['nonce' => 'valid', 'post_id' => 10, 'post_type' => 'post'];
    $preview = response($handler, 'getTeaserPreviewPost');
    check($preview['post_title'] === $decoded, 'Preview title must be decoded exactly once: ' . $encoded);
    check($preview['pre_more'] === '<p>Intro</p>', 'Pre-More content remains intact');
    foreach ([0, 1] as $search_content) {
        $_GET['search_content'] = $search_content;
        $results = response($handler, 'searchPosts');
        check($results[0]->post_title === $decoded, 'Search title must be plain text for Select2');
        check($results[0]->preview_url === 'https://example.test/post/10', 'Search retains preview URL');
    }
}
echo "Passed $assertions teaser preview title assertions.\n";
