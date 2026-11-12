<?php
namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

class Collab {
    private static $instance = null;

    public static function instance() {
        if ( is_null(self::$instance) ) {
            self::$instance = new Collab();
        }

        return self::$instance;
    }

    private function __construct()
    {
        
    }

    public static function populateRoles()
    {
        if (!get_option('ppce_added_role_caps_21beta')) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Updated.php');
            Collab\Updated::populateRoles();
        }
    }

    public static function getObjectTerms($object_ids, $taxonomy, $args = [])
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostTermsSave.php');
        return Collab\PostTermsSave::getObjectTerms($object_ids, $taxonomy, $args);
    }

    public static function getPostedObjectTerms($taxonomy)
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostTermsSave.php');
        return Collab\PostTermsSave::getPostedObjectTerms($taxonomy);
    }

    public static function isLimitedEditor()
    {
        if (presspermit()->isContentAdministrator())
            return false;

        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UserLimitation.php');
        return Collab\UserLimitation::isLimitedEditor();
    }

    public static function userCanAssociateMain($post_type)
    {
        global $current_user;

        if (presspermit()->isUserUnfiltered($current_user->ID, compact('post_type')))
            return true;

        if (!$post_type_obj = get_post_type_object($post_type))
            return true;

        if (!$post_type_obj->hierarchical)
            return true;

        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostEdit.php');
        return Collab\PostEdit::userCanAssociateMain($post_type);
    }

    public static function isEditREST()
    {
        if (!defined('REST_REQUEST') || ! REST_REQUEST || ! presspermit()->doingREST() ) {
            return false;
        }

        $rest = \PublishPress\Permissions\REST::instance();

        return ( $rest->is_posts_request || $rest->is_terms_request ) && ! $rest->is_view_method;
    }
}
