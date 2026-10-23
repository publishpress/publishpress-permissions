<?php
namespace PublishPress\Permissions\Collab\Revisions;

\defined('ABSPATH') || exit;

class CapabilityFilters
{
    function __construct()
    {
        add_filter('presspermit_has_post_cap_vars', [$this, 'has_post_cap_vars'], 10, 4);

        // todo: confirm no longer needed
        add_action('presspermit_user_init', [$this, 'actGrantListingCaps']);

        add_filter('revisionary_can_copy', [$this, 'fltCanCopy'], 10, 5);
        add_filter('revisionary_can_submit', [$this, 'fltCanSubmit'], 10, 5);

        add_filter('user_has_cap', [$this, 'fltUserHasReviseCap'], 200, 3);

        // Permissions compat: Ensure Gutenberg doesn't block Subscribers with revise permissions for specific pages
        add_action('init', [$this, 'actEnableLimitedRevisors'], 5);
    }

	function actEnableLimitedRevisors($user_id) {
		global $current_user;

		if (is_admin()) {
			if ($post_id = rvy_detect_post_id()) {
				if ('draft-revision' == rvy_in_revision_workflow($post_id)) {
					if ($post = get_post($post_id)) {
						if ($current_user->ID == $post->post_author) {
							if ($type_obj = get_post_type_object($post->post_type)) {
								$current_user->allcaps[$type_obj->cap->edit_posts] = true;
							}

                            // note: this capability does not affect access to published posts or other users' posts
							$current_user->allcaps['edit_posts'] = true;
						}
					}
				}
			}
		}
    }

    function fltUserHasReviseCap($wp_sitecaps, $orig_reqd_caps, $args) {
        global $current_user;
        
        $args = (array) $args;

        if (!array_diff($orig_reqd_caps, array_keys(array_filter($wp_sitecaps)))) {
            return $wp_sitecaps;
        }

        if ($args[1] != $current_user->ID) {
            return $wp_sitecaps;
        }

        $orig_cap = (isset($args[0])) ? sanitize_key($args[0]) : '';

        if (isset($args[2])) {
            if (is_object($args[2])) {
                $args[2] = (isset($args[2]->ID)) ? $args[2]->ID : 0;
            }
        } else {
            $item_id = 0;
        }

        $item_id = (isset($args[2])) ? (int) $args[2] : 0;

        if ($item_id && ('edit_post' == $orig_cap) && rvy_in_revision_workflow($item_id) && ($current_user->ID == get_post_field('post_author', $item_id))) {
            if (current_user_can('copy_post', rvy_post_id($item_id))) {
                return array_merge($wp_sitecaps, array_fill_keys($orig_reqd_caps, true));
            }
        }

        return $wp_sitecaps;
    }

    // todo: confirm this is no longer needed

    function actGrantListingCaps() {
        global $current_user, $revisionary;

        if (empty($revisionary->enabled_post_types)) {
            return;
        }

        if (rvy_get_option('copy_posts_capability')) {
            return;
        }

        $user = presspermit()->getUser();

        foreach(array_keys($revisionary->enabled_post_types) as $post_type) {
            $type_obj = get_post_type_object($post_type);

            // todo: custom privacy caps
            foreach(['edit_published_posts', 'edit_private_posts'] as $prop) {
                if (!empty($type_obj->cap->$prop) && empty($current_user->allcaps[$type_obj->cap->$prop])) {
                    if (!empty($current_user->allcaps[$type_obj->cap->edit_posts]) || !empty($current_user->allcaps['submit_changes'])) {
                        $list_cap = str_replace('edit_', 'list_', $type_obj->cap->$prop);
                        $current_user->allcaps[$list_cap] = true;
                        $user->allcaps[$list_cap] = true;
                    }
                }
            }
        }
    }

    // todo: move to a general module
    function fltPostAccessApplyExceptions($can_do, $operation, $post_type, $post_id, $args = []) {
        // todo: implement PP_RESTRICTION_PRIORITY ?
        
        // todo: implement for specific revision statuses

        $user = presspermit()->getUser();

        $op_key = "{$operation}_post";

        if (!isset($user->except[$op_key])) {
            $user->retrieveExceptions($operation, 'post');
        }

        $post_terms = [];

        // Only check for 'exclude' restrictive exceptions if not already lacking access
        if ($can_do) {
            $items = (!empty($user->except[$op_key]['post']['']['exclude'][$post_type][''])) 
            ? $user->except[$op_key]['post']['']['exclude'][$post_type]['']
            : [];

            // todo: implement status-specific exception storage for custom workflow?

            // check for term-assigned exceptions
            if ($can_do = !in_array($post_id, $items)) {
                foreach (presspermit()->getEnabledTaxonomies(['object_type' => $post_type]) as $taxonomy) {
                    if ($term_ids = $user->getExceptionTerms($operation, 'exclude', $post_type, $taxonomy, ['merge_universals' => true, 'return_term_ids' => true])) {
                        if (!isset($post_terms[$taxonomy])) {
                            $post_terms[$taxonomy] = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
                        }
                        
                        if (array_intersect($term_ids, $post_terms[$taxonomy])) {
                            $can_do = false;
                            break;
                        }
                    }
                }
            }
        }

        // Only check for 'include' restrictive exceptions if not already lacking access
        if ($can_do) {
            $items = (!empty($user->except[$op_key]['post']['']['include'][$post_type][''])) 
            ? $user->except[$op_key]['post']['']['include'][$post_type]['']
            : [];

            // check for term-assigned exceptions
            if ($can_do = empty($items) || in_array($post_id, $items)) {
                foreach (presspermit()->getEnabledTaxonomies(['object_type' => $post_type]) as $taxonomy) {
                    if ($term_ids = $user->getExceptionTerms($operation, 'include', $post_type, $taxonomy, ['merge_universals' => true, 'return_term_ids' => true])) {
                        if (!isset($post_terms[$taxonomy])) {
                            $post_terms[$taxonomy] = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
                        }

                        if (!empty($term_ids) && !array_intersect($term_ids, $post_terms[$taxonomy])) {
                            $can_do = false;
                            break;
                        }
                    }
                }
            }
        }

        // check Enable exceptions
        if (!$can_do) {
            $items = (!empty($user->except[$op_key]['post']['']['additional'][$post_type][''])) 
            ? $user->except[$op_key]['post']['']['additional'][$post_type]['']
            : [];

            // check for term-assigned exceptions
            if (!$can_do = in_array($post_id, $items)) {
                foreach (presspermit()->getEnabledTaxonomies(['object_type' => $post_type]) as $taxonomy) {
                    if ($term_ids = $user->getExceptionTerms($operation, 'additional', $post_type, $taxonomy, ['merge_universals' => true, 'return_term_ids' => true])) {
                        if (!isset($post_terms[$taxonomy])) {
                            $post_terms[$taxonomy] = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
                        }

                        if (array_intersect($term_ids, $post_terms[$taxonomy])) {
                            $can_do = true;
                            break;
                        }
                    }
                }
            }
        }

        return $can_do;
    }

    function fltCanCopy($can_copy, $post_id, $base_status, $revision_status, $args) {
        if (rvy_in_revision_workflow($post_id)) {
            return false;
        }
        
        if (presspermit()->isAdministrator()) {
            return $can_copy;
        }

        $post_type = (!empty($args['type_obj'])) ? $args['type_obj']->name : get_post_field('post_type', $post_id);

        if ('draft' == $base_status) {
            $operation = 'copy';

        //} elseif ('future' == $base_status) {
            // todo: review possible implementation for scheduled revisions
            //$operation = 'schedule'

        } else {
            return $can_copy;
        }

        if ($can_copy) {
            // Apply blocking exceptions for Create Revision operation
            return $this->fltPostAccessApplyExceptions($can_copy, $operation, $post_type, $post_id);

        } else {
        	// Possession of a submit_post permission also grants implicit copy_post permission.  This is partly for consistency with Revisions 2.x behavior
        	return $this->fltPostAccessApplyExceptions($can_copy, $operation, $post_type, $post_id) || $this->fltPostAccessApplyExceptions($can_copy, 'revise', $post_type, $post_id);
    	}
    }

    function fltCanSubmit($can_submit, $post_id, $new_base_status, $new_revision_status, $args) {
        if ('pending' != $new_base_status || !rvy_in_revision_workflow($post_id)) {
            return false;
        }

        if (presspermit()->isAdministrator()) {
            return $can_submit;
        }

        $main_post_id = (!empty($args['main_post_id'])) ? $args['main_post_id'] : rvy_post_id($post_id);
        $post_type = (!empty($args['type_obj'])) ? $args['type_obj']->name : get_post_field('post_type', $post_id);

        return $this->fltPostAccessApplyExceptions($can_submit, 'revise', $post_type, $main_post_id);
    }

    private function postTypeFromCaps($caps)
    {
        foreach (get_post_types(['public' => true, 'show_ui' => true], 'object', 'or') as $post_type => $type_obj) {
            $caps = array_diff($caps, ['edit_posts', 'edit_pages']); // ignore generic caps defined for extraneous properties (assign_term, etc.) 
            if (array_intersect((array)$type_obj->cap,  $caps)) {
                return $post_type;
            }
        }

        return false;
    }

    function has_post_cap_vars($force_vars, $wp_sitecaps, $pp_reqd_caps, $vars)
    {
        static $exclude_ids;
        static $additional_ids;

        $return = [];

        if (('read_post' == reset($pp_reqd_caps))) {
            if (!is_admin() && PWP::is_REQUEST('post_type', 'revision') 
            && (!PWP::empty_REQUEST('preview') || !PWP::empty_REQUEST('preview_id'))) {
                $return['pp_reqd_caps'] = ['edit_post'];
            }
        }

        if (('edit_post' == reset($pp_reqd_caps)) && !empty($vars['post_id'])) {
            if (rvy_in_revision_workflow($vars['post_id'])) { 

                // Normally, revisions capability checks are applied instead of a full permissions query. 
                // But if this post or its main page have permissions stored, allow the permissions query to execute.
                if ($rvy_is_compatible = defined('PUBLISHPRESS_REVISIONS_VERSION') && version_compare(PUBLISHPRESS_REVISIONS_VERSION, '3.7.9-beta', '>=')) {
                    $main_post_id = rvy_post_id($vars['post_id']);
    
                    if (!isset($exclude_ids)) {
                        $exclude_ids = [];
                    }
    
                    $exception_key = $vars['required_operation'] . $vars['post_type'];
    
                    if (!isset($exclude_ids[$exception_key])) {
                        $user = presspermit()->getUser();
    
                        if ($ids = $user->getExceptionPosts($vars['required_operation'], 'exclude', $vars['post_type'], ['status' => true])) {
                            if (isset($ids[''])) {
                                $exclude_ids[$exception_key] = $ids[''];
                            }
                        }
    
                        if ($ids = $user->getExceptionPosts($vars['required_operation'], 'additional', $vars['post_type'], ['status' => true])) {
                            if (isset($ids[''])) {
                                $additional_ids[$exception_key] = $ids[''];
                            }
                        }
                    }
                }

                if (
                    !$rvy_is_compatible ||
                    (
                        (
                            !isset($exclude_ids[$exception_key]) 
                            || (!in_array($vars['post_id'], $exclude_ids[$exception_key]) && (!get_option('rvy_apply_post_exceptions') || !in_array($main_post_id, $exclude_ids[$exception_key])))
                        )
            
                        && (
                            !isset($additional_ids[$exception_key]) 
                            || (!in_array($vars['post_id'], $additional_ids[$exception_key]) && (!get_option('rvy_apply_post_exceptions') || !in_array($main_post_id, $additional_ids[$exception_key])))
                        )
                    )
                ) {
                    $return['return_caps'] = $wp_sitecaps;
                }
            }
        }

        return ($return) ? array_merge((array)$force_vars, $return) : $force_vars;

        // note: CapabilityFilters::fltUserHasCap() filters return array to allowed variables before extracting
    }
}
