<?php
namespace PublishPress\Permissions\Collab;

class PostSaveHierarchical
{
    public static function fltPageParent($parent_id, $post_type = '')
    {
        if (function_exists('bbp_get_version') && PWP::is_REQUEST('action', ['bbp-new-topic', 'bbp-new-reply'])) {
            return $parent_id;
        }

        if (presspermit()->doing_rest) {
            $rest = \PublishPress\Permissions\REST::instance();
            
            if (!empty($rest) && 'WP_REST_Attachments_Controller' == $rest->endpoint_class) {
                return $parent_id;
            }
        }

        $selected_parent_id = $parent_id;

        // this filter is not intended to regulate attachment parent
        if (isset($_SERVER['REQUEST_URI']) && strpos(esc_url_raw($_SERVER['REQUEST_URI']), 'async-upload.php') && PWP::is_REQUEST('action', 'upload-attachment')) {
            return $parent_id;
        }

        if (!$post_id = presspermit()->getCurrentSanitizePostID()) {
            $post_id = PWP::getPostID();
        }

        if (!$post_type && presspermit()->doingREST() && \PublishPress\Permissions\REST::getPostType()) {
            if ($post_id) {
                if ($_post = get_post($post_id)) {
                    $post_type = $_post->post_type;
                }
            } else {
            	$post_type = \PublishPress\Permissions\REST::getPostType();
        	}
        }

        if (!$post_id && !$post_type)  // allow post type to be passed in for pre-filtering of new page creation
            return $parent_id;

        if (($parent_id == $post_id) && $post_id) {  // normal revision save
            $_post = get_post($post_id);

            if ($_post && ('revision' == $_post->post_type)) {
                return $parent_id;
            }
        }

        if ($parent_id) {
            if ($parent_post = get_post($parent_id)) {
                if (!in_array($parent_post->post_type, presspermit()->getEnabledPostTypes(), true))
                    return $parent_id;
            }
        }

        if ($post_id && !$post_type) {
            if ($post = get_post($post_id))
                $post_type = $post->post_type;
        }

        // If a newly selected parent is invalid due to exceptions or because it's a descendant, revert to last stored setting

        if ($post_type) {
            if (!in_array($post_type, presspermit()->getEnabledPostTypes(), true))
                return $parent_id;

            static $return;
            if (!isset($return)) $return = [];
            if (isset($return[$post_id])) return $return[$post_id];

            $revert = false;

            $user = presspermit()->getUser();

            $required_operation = (presspermit()->getOption('page_parent_editable_only')) ? 'edit' : 'associate';

            $additional_ids = $user->getExceptionPosts($required_operation, 'additional', $post_type);

            if ($include_ids = $user->getExceptionPosts($required_operation, 'include', $post_type)) {
                $exclude_ids = false;

                if (!in_array(0, $include_ids, true) && !in_array('0', $include_ids, true)) {
                    $include_ids = array_merge($include_ids, $additional_ids);
                    if (!in_array($parent_id, $include_ids))
                        $revert = true;
                }

            } elseif ($exclude_ids = array_diff($user->getExceptionPosts($required_operation, 'exclude', $post_type), $additional_ids)) {
                $exclude_ids []= $post_id;

                if (in_array($parent_id, $exclude_ids))
                    $revert = true;
            }

            if ($parent_id) {
                if (defined('PRESSPERMIT_OWN_DESCENDENT_CHECK')) {
                    $descendants = self::getPageDescendantIds($post_id);

                    if (in_array($parent_id, $descendants)) {
                        $revert = true;
                    }
                }

                if ($post_id == $parent_id) {
                    $revert = true;
                }

                if ($revert) {
                    $parent_id = self::revertPageParent($post_id, $post_type, compact('include_ids', 'exclude_ids'));
                }
            }

            if (defined('PRESSPERMIT_FILTER_VALIDATE_PAGE_PARENT')) {
                if (!isset($descendants)) {
                    $descendants = self::getPageDescendantIds($post_id);
                }

                $parent_id = apply_filters(
                    'presspermit_validate_page_parent', 
                    $parent_id, 
                    $post_type, 
                    compact('descendants', 'include_ids', 'exclude_ids')
                );
            }

            // subsequent filtering is currently just a safeguard against invalid "no parent" posting in violation of lock_top_pages
            // if ( $parent_id || ( ! $selected_parent_id && Collab::userCanAssociateMain( $post_type ) ) )
            if ($parent_id || Collab::userCanAssociateMain($post_type))
                return $parent_id;

            return self::revertPageParent($post_id, $post_type, compact('include_ids', 'exclude_ids'));
        }

        $return[$post_id] = $parent_id;

        return $parent_id;
    }

    static function getPageDescendantIds($page_id, $pages = '')
    {
        global $wpdb;

        if (empty($pages)) {
            // Single direct query for each get_pages() call or page parent update

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $pages = $wpdb->get_results(
                "SELECT ID, post_parent FROM $wpdb->posts WHERE post_parent > 0 AND post_type NOT IN ( 'revision', 'attachment' )"
            );
        }

        $descendant_ids = [];
        foreach ((array)$pages as $page) {
            if ($page->post_parent == $page_id) {
                $descendant_ids[] = $page->ID;
                if ($children = get_page_children($page->ID, $pages)) { // Okay to use unfiltered WP function here since it's only used for excluding
                    foreach ($children as $_page)
                        $descendant_ids [] = $_page->ID;
                }
            }
        }

        return $descendant_ids;
    }

    private static function revertPageParent($post_id, $post_type, $args = [])
    {
        $defaults = ['include_ids' => [], 'exclude_ids' => []];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (!$post_id) {
            return 0;
        }

        $post = get_post($post_id);

        $valid_statuses = get_post_stati(['public' => true, 'private' => true], 'names', 'OR');
        $workflow_statuses = get_post_stati(['protected' => true, 'internal' => false]);
        $valid_statuses = array_merge($valid_statuses, $workflow_statuses, ['draft', 'pending']);
        $statuses_csv = implode("','", array_map('sanitize_key', $valid_statuses));

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $valid_parents = $wpdb->get_col(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_status IN ('$statuses_csv') AND ID > 0 ORDER BY post_parent, ID ASC",
                $post_type
            )
        );

        $descendants = self::getPageDescendantIds($post_id);

        $valid_parents = array_diff($valid_parents, $descendants, (array)$post_id);
        $allowed_parents = $valid_parents;

        $universal_include = $include_ids && (in_array(0, (array) $include_ids, true) || in_array('0', (array) $include_ids, true));

        if (!count($valid_parents)) {
            // No existing posts qualify as parent, regardless of permissions
            $parent_id = 0;
        } else {
            if ($include_ids && !$universal_include) {
                $allowed_parents = array_intersect($allowed_parents, (array) $include_ids);
            } elseif ($exclude_ids) {
                $allowed_parents = array_diff($allowed_parents, $exclude_ids);
            }
        }

        if ($post 
        && ((!$post->post_parent && ($post->post_status != 'auto-draft' || Collab::userCanAssociateMain($post_type)))
        || in_array($post->post_parent, $allowed_parents)
		|| in_array($post->post_parent, $valid_parents) && ($post->post_status != 'auto-draft'))
        ) {
            $parent_id = $post->post_parent;
        } else {
            if ($allowed_parents) {
                sort($allowed_parents);
                $parent_id = reset($allowed_parents);
            } elseif ($valid_parents) {
                sort($valid_parents);
                $parent_id = reset($valid_parents);
            } else {
                $parent_id = 0;
            }
			
			if (!defined('PRESSPERMIT_NO_PROCESS_BEFORE_PARENT_REVERT')) {
	            require_once(PRESSPERMIT_CLASSPATH . '/PostSave.php');
	
	            if ($parent_id) {
	                $is_new = true;
	                require_once(PRESSPERMIT_CLASSPATH . '/ItemSave.php');
	                $via_item_source = 'post';
	                $set_parent = $parent_id;
	                $_args = compact('via_item_source', 'set_parent', 'is_new');
	                \PublishPress\Permissions\ItemSave::inheritParentExceptions($post_id, $_args);
	            }
	    	}
        }

        $_POST['parent_id'] = $parent_id; // for subsequent post_status filter

        return $parent_id;
    }

    // Filtering of Page Parent submission (applied to post_status filter because fallback on invalid submission 
    // for a previously unpublished post is to force it to draft status).
    //
    // There is currently no way to explictly restrict or grant Page Association rights to Main Page (root). Instead:
    //  * Require site-wide edit_others_pages cap for association of a page with Main
    //  * If an unqualified user tries to associate or un-associate a page with Main Page,
    //    revert page to previously stored parent if possible. Otherwise set status to "unpublished".
    public static function enforceTopPagesLock($status)
    {
        global $post;

        // user can't associate / un-associate a page with Main page unless they have edit_pages site-wide
        if ($post_id = PWP::POST_int('post_ID')) {
            $selected_parent_id = PWP::POST_int('parent_id');
        } elseif (!empty($post)) {
            $post_id = $post->ID;
            $selected_parent_id = $post->post_parent;
        } else
            return $status;

        if (!$_post = get_post($post_id)) {
            return $status;
        }

        if (empty($_post->post_status)) {
            return $status;
        }

        if ($saved_status_object = get_post_status_object($_post->post_status))
            $already_published = ($saved_status_object->public || $saved_status_object->private);
        else
            $already_published = false;

        // if neither the stored nor selected parent is Main, we have no beef with it
        if (!empty($selected_parent_id) && (!empty($_post->post_parent) || !$already_published))
            return $status;

        // if the page is and was associated with Main Page, don't mess
        if (empty($selected_parent_id) && empty($_post->post_parent) 
        && ($already_published || defined('PPCE_LIMITED_EDITORS_TOP_LEVEL_PUBLISH'))
        ) {
            return $status;
        }

        if (PWP::empty_POST('parent_id')) {
            if (!$already_published) {  // This should only ever happen if the POST data is manually fudged
                if ($post_status_object = get_post_status_object($status)) {
                    if ($post_status_object->public || $post_status_object->private) {
                        $status = 'draft';
                    }
                }
            }
        }

        return $status;
    }
}
