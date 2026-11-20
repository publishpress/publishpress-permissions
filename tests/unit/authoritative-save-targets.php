<?php
// Run: php tests/unit/authoritative-save-targets.php
namespace PublishPress\Permissions\Collab {
    class Collab {
        public static function userCanAssociateMain($type) { return true; }
        public static function getObjectTerms($id, $tx, $args) {
            if ($id !== 34) throw new \RuntimeException('Wrong term-retention target');
            return [98];
        }
    }
}
namespace {
    define('ABSPATH', __DIR__ . '/../../');
    define('PRESSPERMIT_COLLAB_CLASSPATH', ABSPATH . 'modules/presspermit-collaboration/classes/Permissions/Collab');
    require ABSPATH . 'classes/PublishPress/Permissions.php';
    require PRESSPERMIT_COLLAB_CLASSPATH . '/AdminFilters.php';
    require PRESSPERMIT_COLLAB_CLASSPATH . '/PostSaveHierarchical.php';
    require PRESSPERMIT_COLLAB_CLASSPATH . '/PostTermsSave.php';
    require PRESSPERMIT_COLLAB_CLASSPATH . '/XmlRpc.php';
    class Fields {
        public static function getPostID() { return 0; }
        public static function findPostType() { return 'page'; }
    }
    class_alias('Fields', 'PublishPress\Permissions\Collab\PWP');
    function get_post($id) { return (object) ['ID' => $id, 'post_type' => 'page', 'post_parent' => 0, 'post_status' => 'draft']; }
    function is_post_type_hierarchical($type) { return true; }
    function is_taxonomy_hierarchical($type) { return true; }
    function get_terms($args) { return [1, 2]; }
    function wp_die(...$args) { throw new RuntimeException('denied'); }
    function esc_html__($text, ...$args) { return $text; }
    function current_user_can(...$args) { return true; }
    function do_action(...$args) {}
    class WP_Error { public function __construct(...$args) {} }
    function wp_set_post_categories($id, $terms) { $GLOBALS['saved'] = [$id, $terms]; }
    function apply_filters($name, $value, ...$args) {
        if ($name === 'presspermit_pre_object_terms') {
            if ($args[1]['object_id'] !== 34) throw new RuntimeException('XML-RPC omitted authoritative target');
            return PublishPress\Permissions\Collab\PostTermsSave::fltPreObjectTerms($value, ...$args);
        }
        return $value;
    }
    function presspermit() { return $GLOBALS['pp']; }
    $user = new class {
        public function getExceptionPosts($op, $mode, $type) { return $mode === 'exclude' ? [9] : []; }
        public function getExceptionTerms(...$args) { return []; }
    };
    $GLOBALS['pp'] = new class($user) {
        private $user;
        public function __construct($user) { $this->user = $user; }
        public function filteringEnabled() { return true; }
        public function isTaxonomyEnabled($tx) { return true; }
        public function isUserUnfiltered() { return false; }
        public function getUser() { return $this->user; }
        public function getOption($name) { return false; }
        public function getEnabledPostTypes() { return ['page']; }
    };
    $class = new ReflectionClass('PublishPress\Permissions');
    $context = $class->newInstanceWithoutConstructor();
    $property = $class->getProperty('sanitizing_post_id');
    $property->setAccessible(true); $property->setValue($context, 34);
    if ($context->getCurrentSanitizePostID() !== 34) throw new RuntimeException('Sanitization identity lost');
    $class = new ReflectionClass('PublishPress\Permissions\Collab\AdminFilters');
    $filters = $class->newInstanceWithoutConstructor();
    foreach ([false, true, false, true] as $update) {
        $filters->fltLogInsertPost(false, $update ? ['ID' => 34] : []);
        try {
            $filters->fltAuthorizeParent(['post_type' => 'page', 'post_parent' => 9, 'post_status' => 'publish'], ['ID' => 34], [], $update);
            throw new LogicException('Excluded parent allowed in mixed batch');
        } catch (RuntimeException $error) { if ($error->getMessage() !== 'denied') throw $error; }
        $allowed = ['post_type' => 'page', 'post_parent' => 8, 'post_status' => 'publish'];
        if ($filters->fltAuthorizeParent($allowed, ['ID' => 34], [], $update) !== $allowed) throw new LogicException('Allowed parent rejected');
    }
    $wp_xmlrpc_server = new class {
        public function escape(&$args) {}
        public function login(...$args) { return true; }
    };
    $xml = (new ReflectionClass('PublishPress\Permissions\Collab\XmlRpc'))->newInstanceWithoutConstructor();
    $xml->mt_set_categories([34, 'user', 'password', [['categoryId' => 1]]]);
    if ($GLOBALS['saved'][0] !== 34 || $GLOBALS['saved'][1] !== [1, 98]) throw new RuntimeException('Protected XML-RPC term was removed');
    echo "OK: XML-RPC protected terms, sanitization identity, and mixed-batch parent checks\n";
}
