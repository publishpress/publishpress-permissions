<?php
/** Run with: php tests/unit/post-status-counts.php */

namespace PublishPress\Permissions {
    class PostFilters
    {
        public static $args;

        public static function instance()
        {
            return new self();
        }

        public function fltPostsJoin($join, $args)
        {
            return $join;
        }

        public function getPostsWhere($args)
        {
            self::$args = $args;
            return empty($args['include_trash']) ? " AND post_status <> 'trash'" : '';
        }
    }
}

namespace {
    define('ABSPATH', __DIR__ . '/../../');
    require_once ABSPATH . 'classes/PublishPress/Permissions/UI/Dashboard/PostsListing.php';

    function get_post_stati()
    {
        return ['publish', 'trash'];
    }

    function sanitize_key($value)
    {
        return $value;
    }

    function presspermit()
    {
        global $unfiltered;
        return new class($unfiltered) {
            private $unfiltered;
            public function __construct($unfiltered) { $this->unfiltered = $unfiltered; }
            public function isUserUnfiltered() { return $this->unfiltered; }
        };
    }

    $wpdb = new class {
        public $posts = 'wp_posts';
        public $trashCount = 1;
        public $queries = 0;
        public function prepare($query, $type) { return str_replace('%s', "'$type'", $query); }
        public function get_results($query)
        {
            $this->queries++;
            $rows = [(object) ['post_status' => 'publish', 'num_posts' => 2]];
            if (false === strpos($query, "post_status <> 'trash'")) {
                $rows[] = (object) ['post_status' => 'trash', 'num_posts' => $this->trashCount];
            }
            return $rows;
        }
    };

    $listing = (new \ReflectionClass(\PublishPress\Permissions\UI\Dashboard\PostsListing::class))->newInstanceWithoutConstructor();
    $raw = (object) ['publish' => 20, 'trash' => 9, 'unregistered_status' => 3];
    $unfiltered = false;
    $assertions = 0;
    function check($expected, $actual)
    {
        global $assertions;
        $assertions++;
        if ($expected !== $actual) {
            throw new \RuntimeException('Expected ' . json_encode($expected) . ', got ' . json_encode($actual));
        }
    }

    check(['publish' => 2, 'trash' => 1], (array) $listing->fltCountPosts($raw, 'post'));
    check(true, \PublishPress\Permissions\PostFilters::$args['include_trash']);
    check('edit', \PublishPress\Permissions\PostFilters::$args['required_operation']);
    check(['post'], \PublishPress\Permissions\PostFilters::$args['post_types']);
    $wpdb->trashCount = 4;
    check(['publish' => 2, 'trash' => 4], (array) $listing->fltCountPosts($raw, 'post'));
    check(9, $raw->trash);
    $unfiltered = true;
    check(['publish' => 20, 'trash' => 9], (array) $listing->fltCountPosts($raw, 'post'));
    check(2, $wpdb->queries);
    echo "OK ($assertions post status count assertions)\n";
}
