<?php
/** Run with: php tests/unit/teaser-content-boundaries.php */

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
    require_once PRESSPERMIT_TEASER_CLASSPATH . '/PostsTeaser.php';

    function presspermit() { return new class {
        public function getOption($name) { return false; }
        public function getTypeOption($name, $type) { return ''; }
    }; }
    function do_shortcode($text) { return $text; }
    function get_permalink($id) { return 'https://example.test/post/' . $id; }
    function has_blocks($text) { return false !== strpos($text, '<!-- wp:'); }
    function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES); }
    function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES); }
    // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- WordPress substitute for this standalone test.
    function wp_strip_all_tags($text) { return strip_tags($text); }
    function wpautop($text) { return false !== strpos($text, '<') ? $text : '<p>' . $text . '</p>'; }
    function force_balance_tags($text) {
        $document = new \DOMDocument();
        $document->loadHTML('<html><body>' . $text . '</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);
        $html = '';
        foreach ($document->getElementsByTagName('body')->item(0)->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }
        return $html;
    }

    $assertions = 0;
    function check($condition, $label) {
        global $assertions;
        $assertions++;
        if (!$condition) throw new \RuntimeException($label);
    }
    foreach ([false, true] as $blocks) {
        foreach ([false, true] as $excerpt) {
            foreach (['<div id="before">BEFORE</div>', '<div id="before">BEFORE'] as $before) {
                $post = (object) ['ID' => 10, 'post_content' => $blocks ? '<!-- wp:paragraph --><p>SECRET</p><!-- /wp:paragraph -->' : 'SECRET', 'post_excerpt' => 'PUBLIC INTRO', 'post_password' => ''];
                $args = [
                    'force_refresh' => true,
                    'teaser_replace' => ['post' => ['post_content' => '<div class="pp-teaser-notice">BLOCKED MESSAGE</div>']],
                    'teaser_prepend' => ['post' => ['post_content' => $before]],
                    'teaser_append' => ['post' => ['post_content' => '<div id="after">AFTER</div>']],
                    'excerpt_teaser' => ['post' => $excerpt],
                ];
                \PublishPress\Permissions\Teaser\PostsTeaser::applyTeaser($post, 'post', 'post', $args);
                $document = new \DOMDocument();
                $document->loadHTML($post->post_content, LIBXML_NOERROR | LIBXML_NOWARNING);
                $xpath = new \DOMXPath($document);
                check(1 === $xpath->query('//div[contains(@class,"pp-teaser-notice")]')->length, 'One styled notice');
                check(0 === $xpath->query('//div[contains(@class,"pp-teaser-notice")]//*[@id="before" or @id="after"]')->length, 'Fragments stay outside notice');
                check(0 === $xpath->query('//*[@id="before"]//div[contains(@class,"pp-teaser-notice")]')->length, 'Unclosed prefix cannot swallow notice');
                check(strpos($post->post_content, 'BEFORE') < strpos($post->post_content, 'BLOCKED MESSAGE'), 'Before precedes notice');
                check(strpos($post->post_content, 'BLOCKED MESSAGE') < strpos($post->post_content, 'AFTER'), 'After follows notice');
                check(false === strpos($post->post_content, 'SECRET'), 'Restricted body is not leaked');
            }
        }
    }
    echo "OK ($assertions teaser boundary assertions)\n";
}
