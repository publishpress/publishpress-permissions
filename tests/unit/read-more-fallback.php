<?php
/** Run with: php tests/unit/read-more-fallback.php */

namespace PublishPress\Permissions {
    class Teaser
    {
        public static function instance() { return new self(); }
        public function setTeasedPost($id) {}
    }
    class TeaserHooks
    {
        public $teased_excerpts = [];
        public static function instance() { static $instance; return $instance ?: ($instance = new self()); }
        public static function getTeaserOptionOrDefault($name, $type) { return 'BLOCKED MESSAGE'; }
    }
}

namespace {
    define('ABSPATH', __DIR__ . '/../../');
    define('PRESSPERMIT_TEASER_CLASSPATH', ABSPATH . 'modules/presspermit-teaser/classes/Permissions/Teaser');
    class_alias('PublishPress\Permissions\Teaser', 'PublishPress\Permissions\Teaser\Teaser');
    require_once ABSPATH . 'modules/presspermit-teaser/classes/Permissions/Teaser/PostsTeaser.php';
    require_once ABSPATH . 'modules/presspermit-teaser/classes/Permissions/Teaser/ReadMoreHandler.php';

    function presspermit() { return new class {
        public function getOption($name) { return false; }
        public function getTypeOption($name, $type) { return ''; }
    }; }
    function do_shortcode($text) { return $text; }
    function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES); }
    function esc_html__($text, $domain) { return $text; }
    // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Minimal WordPress substitute in this standalone test.
    function wp_strip_all_tags($text) { return strip_tags($text); }
    function get_permalink($id) { return 'https://example.test/post/' . $id; }
    function get_the_title($id) { return 'Title'; }
    function is_user_logged_in() { return false; }
    function is_single() { return true; }
    function is_page() { return false; }
    function force_balance_tags($text) { return $text; }
    function apply_filters($hook, $value, ...$args) { return $value; }

    $assertions = 0;
    foreach (['SECRET CONTENT', '<!-- wp:paragraph --><p>SECRET CONTENT</p><!-- /wp:paragraph -->', '<!--more-->SECRET CONTENT', '<!-- wp:more /-->SECRET CONTENT'] as $content) {
        foreach (['', 'SECRET EXCERPT'] as $excerpt) {
            $post = (object) ['ID' => 10, 'post_type' => 'post', 'post_content' => $content, 'post_excerpt' => $excerpt, 'post_password' => ''];
            \PublishPress\Permissions\Teaser\PostsTeaser::applyTeaser($post, 'post', 'post', ['read_more_teaser' => ['post' => true], 'force_refresh' => true]);
            $assertions += 3;
            if (false !== strpos($post->post_content, 'SECRET') || '' !== $post->post_excerpt || false === strpos($post->post_content, 'BLOCKED MESSAGE')) {
                throw new \RuntimeException('Missing/empty delimiter must show only the blocked message');
            }
        }
    }
    foreach (['<!--more-->', '<!-- wp:more /-->', '<!-- wp:read-more /-->'] as $marker) {
        $post = (object) ['ID' => 10, 'post_type' => 'post', 'post_content' => '<p>PUBLIC INTRO</p>' . $marker . 'SECRET CONTENT', 'post_excerpt' => 'SECRET EXCERPT', 'post_password' => ''];
        \PublishPress\Permissions\Teaser\PostsTeaser::applyTeaser($post, 'post', 'post', ['read_more_teaser' => ['post' => true], 'force_refresh' => true]);
        $assertions += 2;
        if (false === strpos($post->post_content, 'PUBLIC INTRO') || false !== strpos($post->post_content, 'SECRET')) {
            throw new \RuntimeException('A real delimiter must preserve only the public intro');
        }
    }
    echo "OK ($assertions Read More fallback assertions)\n";
}
