<?php
namespace PublishPress\Permissions\Collab\Revisionary;


\defined('ABSPATH') || exit;

class AdminLegacy
{
    function __construct() {
        add_filter('map_meta_cap', [$this, 'flt_mapMetaCap'], 1, 4);
        add_filter('pre_post_parent', [$this, 'fltPageParent']);
        add_filter('presspermit_get_exception_items', [$this, 'flt_get_exception_items'], 10, 5);

        add_filter('presspermit_additions_clause', [$this, 'flt_additions_clause'], 10, 4);

        add_filter('presspermit_administrator_caps', [$this, 'flt_pp_administrator_caps'], 5);
        add_filter('presspermit_term_include_clause', [$this, 'flt_term_include_clause'], 10, 2);
        add_filter('presspermit_exception_clause', [$this, 'flt_pp_exception_clause'], 10, 4);
    }

    public function flt_pp_administrator_caps($caps)
    {
        // todo: why is edit_others_revisions cap required for Administrators in Edit Posts listing (but not Edit Pages) ?

        $caps['edit_revisions'] = true;
        $caps['edit_others_revisions'] = true;
        $caps['list_others_revisions'] = true;
        $caps['delete_revisions'] = true;
        $caps['delete_others_revisions'] = true;

        return $caps;
    }

    public function flt_pp_exception_clause($clause, $required_operation, $post_type, $args = [])
    {
        $defaults = ['logic' => 'NOT IN', 'ids' => [], 'src_table' => ''];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }
        return "( $clause OR ( $src_table.post_type = 'revision' AND $src_table.post_parent $logic ('" . implode("','", $ids) . "') ) )";
    }

    public function flt_term_include_clause($clause, $args = [])
    {
        global $wpdb;

        $defaults = ['tt_ids' => [], 'src_table' => ''];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $clause .= " OR ( $src_table.post_type='revision' AND $src_table.post_parent IN" 
        . " ( SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ('" . implode("','", $tt_ids) . "') ) )";
        
        return $clause;
    }

    public function fltPageParent($parent_id)
    {
        global $revisionary;
        if (!empty($revisionary->admin->revision_save_in_progress)) {
            do_action('presspermit_disable_page_parent_filter');
        }

        return $parent_id;
    }

    public function flt_additions_clause($clause, $operation, $post_type, $args)
    {
        // args elements: status, in_clause, src_table 

        if (in_array($operation, ['edit', 'delete'], true) && empty($args['status']) 
        && !in_array($post_type, apply_filters('presspermit_unrevisable_types', []), true)) {
            $user = presspermit()->getUser();
            
			if ( isset($args['ids']) ) { // PressPermit Core >= 2.6.2
				
				// If we are hiding other revisions from revisors, need to distinguish 
				// between 'edit' exceptions and 'revise' exceptions (which are merged upstream for other reasons).
				if (rvy_get_option('revisor_lock_others_revisions')) {
                    $revise_ids = [];
                    
                    switch ($args['via_item_source']) {
						case 'post':
							$via_item_type = (isset($args['via_item_type'])) ? $args['via_item_type'] : $post_type;
							$revise_ids = $user->getExceptionPosts( 
                                'revise', 
                                'additional', 
                                $via_item_type, 
                                ['status' => $args['status']]
                            );
                            
							break;
						
						case 'term':
							foreach(presspermit()->getEnabledTaxonomies(['object_type' => $post_type]) as $taxonomy) {
								$tt_ids = $user->getExceptionTerms( 
                                    'revise', 
                                    'additional', 
                                    $post_type, 
                                    $taxonomy, 
                                    ['status' => $args['status'], 'merge_universals' => true]
                                );
                                
                                $revise_ids = array_merge($revise_ids, $tt_ids);
							}
							
							break;
					}
                    
					$edit_ids = ($revise_ids) ? array_diff($args['ids'], $revise_ids) : $args['ids'];
					
					if ( $edit_ids || $revise_ids ) {
						$parent_clause = array();
						
						if ( $edit_ids ) {
							$parent_clause []= "( {$args['src_table']}.post_parent IN ('" . implode("','", $edit_ids) . "') )";
						}
						
						if ( $revise_ids ) {
                            $parent_clause []= "( {$args['src_table']}.post_author = $user->ID" 
                            . " AND {$args['src_table']}.post_parent IN ('" . implode("','", $revise_ids) . "') )";
						}
						
						$parent_clause = 'AND (' . Arr::implode(' OR ', $parent_clause) . ' )';
						
                        $clause .= " OR ( {$args['src_table']}.post_type = 'revision'"
                        . " AND {$args['src_table']}.post_status IN ('pending', 'future') $parent_clause )";
					}
				} else {
					// Not hiding other users' revisions from Revisors, so list all posts with 'edit' or 'revise' exceptions regardless of author.
                    $clause .= " OR ( {$args['src_table']}.post_type = 'revision'" 
                    . " AND {$args['src_table']}.post_status IN ('pending', 'future')"
                    . " AND {$args['src_table']}.post_parent {$args['in_clause']} )";
				}
			} else {
				// Older PP Core version doesn't pass ids, so can't distinguish between 'edit' and 'revise' exceptions; retain previous behavior.
                $clause .= " OR ( {$args['src_table']}.post_type = 'revision'"
                . " AND {$args['src_table']}.post_status IN ('pending', 'future')"
                . " AND {$args['src_table']}.post_author = " . presspermit()->getUser()->ID 
                . " AND {$args['src_table']}.post_parent {$args['in_clause']} )";
			}
		}

		return $clause;
    }

    public function flt_mapMetaCap($caps, $meta_cap, $user_id, $wp_args)
    {
        global $current_user;

        if (in_array($meta_cap, ['edit_post', 'edit_page'], true)) {
            if ($user_id != $current_user->ID)
                return $caps;

            if (isset($_SERVER['SCRIPT_NAME']) && false !== strpos(sanitize_text_field(wp_unslash($_SERVER['SCRIPT_NAME'])), 'update.php')) { // Revisionary does not load on update.php
                return $caps;
            }

            if (rvy_get_option('pending_revisions')) {
                $do_revision_clause = false;

                if (!empty($wp_args[0])) {
                    $_post = (is_object($wp_args[0])) ? $wp_args[0] : get_post($wp_args[0]);

                    if ($_post) {
                        $caps = $this->convert_post_edit_caps($caps, $_post->post_type);
                    }
                }
            }

            if (!rvy_get_option('require_edit_others_drafts'))
                return $caps;

            if (isset($wp_args[0]) && is_object($wp_args[0])) {
                if ($current_user->ID == $wp_args[0]->post_author) {
                    return $caps;
                }

                $status_obj = get_post_status_object($wp_args[0]->post_status);
                if ($status_obj && ($status_obj->public || $status_obj->private)) {
                    return $caps;
                }

                if ('revision' == $wp_args[0]->post_type) {
                    return $caps;
                }

                $post_type_obj = get_post_type_object($wp_args[0]->post_type);

                // don't require any additional caps for sitewide Editors
                if (!empty($current_user->allcaps[$post_type_obj->cap->edit_published_posts])) {
                    return $caps;
                }

                $caps[] = 'edit_others_drafts';
            }
        }
        return $caps;
    }

    // merge revise exceptions into edit exceptions
    public function flt_get_exception_items($exception_items, $operation, $mod_type, $for_item_type, $args = [])
    {
        if ('edit' != $operation)
            return $exception_items;

        global $revisionary;

        if (empty($revisionary->skip_revision_allowance)) {
            $defaults = ['via_item_source' => 'post', 'via_item_type' => '', 'status' => ''];
            $args = array_merge($defaults, $args);
            foreach (array_keys($defaults) as $var) {
                $$var = $args[$var];
            }

            $user = presspermit()->getUser();

            if (!isset($user->except['revise_post'])) {
                $user->retrieveExceptions('revise', 'post');
            }

            if (!isset($user->except['revise_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type])) {
                return $exception_items;
            }

            $exception_items = (isset($user->except['edit_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type])) 
            ? $user->except['edit_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type] 
            : [];

            foreach (array_keys($user->except['revise_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type]) as $_status) {
                Arr::setElem($exception_items, [$_status]);

                $exception_items[$_status] = array_merge(
                    $exception_items[$_status], 
                    $user->except['revise_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type][$_status]
                );
            }

            if (true === $status) {
                return $exception_items;
            } else {
                return Arr::flatten(array_intersect_key($exception_items, [$status => true]));
            }
        }

        return $exception_items;
    }

    public static function adjust_revision_reqd_caps($reqd_caps, $object_type)
    {
        global $revisionary;

        if (empty($revisionary->skip_revision_allowance)) {
            global $pagenow;

            $revision_uris = apply_filters('presspermit_revision_uris', ['edit.php', 'upload.php', 'widgets.php', 'admin-ajax.php', 'rvy-revisions', 'rvy-moderation']);

            $revision_uris [] = 'index.php';

            if (presspermit_is_preview() || in_array($pagenow, $revision_uris, true) || in_array(presspermitPluginPage(), $revision_uris, true)) {
                $strip_capreqs = [];

                foreach ((array)$object_type as $_object_type) {
                    if ($type_obj = get_post_type_object($_object_type)) {
                        $strip_capreqs = array_merge(
                            $strip_capreqs, 
                            apply_filters(
                                'rvy_replace_post_edit_caps', 
                                [$type_obj->cap->edit_published_posts, $type_obj->cap->edit_private_posts], 
                                $_object_type, 
                                0
                            )
                        );

                        if (array_intersect($reqd_caps, $strip_capreqs))
                            $reqd_caps [] = $type_obj->cap->edit_posts;
                    }
                }

                $reqd_caps = array_unique(array_diff($reqd_caps, $strip_capreqs));
            }
        }

        return $reqd_caps;
    }

    // Allow contributors and revisors to edit published post/page, with change stored as a revision pending review
    private function convert_post_edit_caps($rs_reqd_caps, $post_type)
    {
        global $revisionary;

        if (!empty($revisionary->skip_revision_allowance) || !rvy_get_option('pending_revisions')) {
            return $rs_reqd_caps;
        }

        $post_id = PWP::getPostID();

        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            // don't need to fudge the capreq for post.php unless existing post has public/private status
            $status = get_post_field('post_status', $post_id, 'post');
            $status_obj = get_post_status_object($status);

            if (empty($status_obj->public) && empty($status_obj->private) && ('future' != $status)) {
                return $rs_reqd_caps;
            }
        }

        if ($type_obj = get_post_type_object($post_type)) {
            $replace_caps = apply_filters(
                'rvy_replace_post_edit_caps', 
                [   'edit_published_posts', 
                    'edit_private_posts', 
                    'publish_posts', 
                    $type_obj->cap->edit_published_posts, 
                    $type_obj->cap->edit_private_posts, 
                    $type_obj->cap->publish_posts
                ], 
                $post_type, 
                $post_id
            );
            
            $use_cap_req = $type_obj->cap->edit_posts;
        } else {
            $replace_caps = [];
        }

        if (array_intersect($rs_reqd_caps, $replace_caps)) {
            foreach ($rs_reqd_caps as $key => $cap_name) {
                if (in_array($cap_name, $replace_caps, true)) {
                    $rs_reqd_caps[$key] = $use_cap_req;
                }
            }
        }

        return $rs_reqd_caps;
    }

    // ensure proper cap requirements when a non-Administrator Quick-Edits or Bulk-Edits Posts/Pages 
    // (which may be included in the edit listing only for revision submission)
    public static function fix_table_edit_reqd_caps($pp_reqd_caps, $orig_meta_cap, $_post, $object_type_obj)
    {
        if (empty($_post)) {
            return $pp_reqd_caps;
        }

        foreach (['edit', 'delete'] as $op) {
            if (in_array($orig_meta_cap, ["{$op}_post", "{$op}_page"], true)) {
                $status_obj = get_post_status_object($_post->post_status);
                foreach (['public' => 'published', 'private' => 'private'] as $status_prop => $cap_suffix) {
                    if (!empty($status_obj->$status_prop)) {
                        global $revisionary;
                        $cap_prop = "{$op}_{$cap_suffix}_posts";
                        $pp_reqd_caps[] = $object_type_obj->cap->$cap_prop;
                        $revisionary->skip_revision_allowance = true;
                    }
                }
            }
        }
        return $pp_reqd_caps;
    }
}
