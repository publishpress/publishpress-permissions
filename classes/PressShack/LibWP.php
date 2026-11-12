<?php
namespace PressShack;

\defined('ABSPATH') || exit;

class LibWP
{
    /**
     * Returns true if is a beta or stable version of WP 5.
     *
     * @return bool
     */
    public static function isWp5()
    {
        global $wp_version;
        return version_compare($wp_version, '5.0', '>=') || substr($wp_version, 0, 2) === '5.';
    }

    public static function getPluginPage() {
        global $plugin_page, $pagenow;

        if (!is_admin()) {
            return false;

        } elseif (!empty($plugin_page)) {
            return $plugin_page;

        } elseif (empty($pagenow) || ('admin.php' != $pagenow)) {
            return false;

        } else {
            return self::REQUEST_key('page');
        }
    }

    /**
     * Based on Edit Flow's \Block_Editor_Compatible::should_apply_compat method.
     *
     * @return bool
     */
    public static function isBlockEditorActive($post_type = '', $args = [])
    {
        global $current_user, $wp_version;

        $defaults = ['force' => false, 'suppress_filter' => false, 'force_refresh' => false];
        $args = array_merge($defaults, $args);
        $suppress_filter = $args['suppress_filter'];

        // Check if Revisionary lower than v1.3 is installed. It disables Gutenberg.
        if (defined('REVISIONARY_VERSION') && version_compare(REVISIONARY_VERSION, '1.3-beta', '<')) {
            return false;
        }

        if (isset($args['force'])) {
            if ('classic' === $args['force']) {
                return false;
            }

            if ('gutenberg' === $args['force']) {
                return true;
            }
        }

        // If the editor is being accessed in this request, we have an easy and reliable test
        if ((did_action('load-post.php') || did_action('load-post-new.php')) && did_action('admin_enqueue_scripts')) {
            if (did_action('enqueue_block_editor_assets')) {
                return true;
            }
        }

        // For other requests (or if the decision needs to be made prior to admin_enqueue_scripts action), proceed with other logic...

        static $buffer;
        if (!isset($buffer)) {
            $buffer = [];
        }

        if (!$post_type) {
            if (!$post_type = self::findPostType()) {
                $post_type = 'page';
            }
        }

        if ($post_type_obj = get_post_type_object($post_type)) {
            if (!$post_type_obj->show_in_rest) {
                return false;
            }
        }

        if (isset($buffer[$post_type]) && empty($args['force_refresh']) && !$suppress_filter) {
            return $buffer[$post_type];
        }

        if (class_exists('Classic_Editor')) {
			if (self::is_REQUEST('classic-editor__forget') && (self::is_REQUEST('classic') || self::is_REQUEST('classic-editor'))) {
				return false;

			} elseif (self::is_REQUEST('classic-editor__forget') && !self::is_REQUEST('classic') && !self::is_REQUEST('classic-editor')) {
				return true;

			} elseif (get_option('classic-editor-allow-users') === 'allow') {
				if ($post_id = self::getPostID()) {
					$which = get_post_meta( $post_id, 'classic-editor-remember', true );

					if ('block-editor' == $which) {
						return true;
					} elseif ('classic-editor' == $which) {
						return false;
					}
				} else {
                    $use_block = ('block' == get_user_meta($current_user->ID, 'wp_classic-editor-settings'));

                    if (version_compare($wp_version, '5.9-beta', '>=')) {
                    	if ($has_nav_action = has_action('use_block_editor_for_post_type', '_disable_block_editor_for_navigation_post_type')) {
                    		remove_action('use_block_editor_for_post_type', '_disable_block_editor_for_navigation_post_type');
                    	}
                    	
                    	if ($has_nav_filter = has_filter('use_block_editor_for_post_type', '_disable_block_editor_for_navigation_post_type')) {
                    		remove_filter('use_block_editor_for_post_type', '_disable_block_editor_for_navigation_post_type');
                    	}
                    }

                    $use_block = $use_block && apply_filters('use_block_editor_for_post_type', $use_block, $post_type);

                    if (version_compare($wp_version, '5.9-beta', '>=') && !empty($has_nav_filter)) {
                        add_filter('use_block_editor_for_post_type', '_disable_block_editor_for_navigation_post_type', 10, 2 );
                    }

                    return $use_block;
				}
			}
		}

        // Divi: Classic Editor option
		if (function_exists('et_get_option') && ( 'on' == et_get_option( 'et_enable_classic_editor', 'off' ))) {
			return false;
		}

		$pluginsState = array(
			'classic-editor' => class_exists( 'Classic_Editor' ),
			'gutenberg'      => function_exists( 'the_gutenberg_project' ),
			'gutenberg-ramp' => class_exists('Gutenberg_Ramp'),
            'disable-gutenberg' => class_exists('DisableGutenberg'),
		);
		
		$conditions = [];

        if ($suppress_filter) remove_filter('use_block_editor_for_post_type', $suppress_filter, 10, 2);

		/**
		 * 5.0:
		 *
		 * Classic editor either disabled or enabled (either via an option or with GET argument).
		 * It's a hairy conditional :(
		 */

        if (version_compare($wp_version, '5.9-beta', '>=')) {
            if ($has_nav_action = has_action('use_block_editor_for_post_type', '_disable_block_editor_for_navigation_post_type')) {
        		remove_action('use_block_editor_for_post_type', '_disable_block_editor_for_navigation_post_type');
        	}
        	
        	if ($has_nav_filter = has_filter('use_block_editor_for_post_type', '_disable_block_editor_for_navigation_post_type')) {
        		remove_filter('use_block_editor_for_post_type', '_disable_block_editor_for_navigation_post_type');
        	}
        }

        $_post = get_post(self::getPostID());

        $conditions[] = (self::isWp5() || $pluginsState['gutenberg'])
						&& ! $pluginsState['classic-editor']
						&& ! $pluginsState['gutenberg-ramp']
                        && ! $pluginsState['disable-gutenberg']
                        && apply_filters('use_block_editor_for_post_type', true, $post_type)
                        && (empty($_post) || !is_a($_post, 'WP_Post') || apply_filters('use_block_editor_for_post', true, $_post));

		$conditions[] = self::isWp5()
                        && $pluginsState['classic-editor']
                        && (get_option('classic-editor-replace') === 'block'
                            && ! self::is_GET('classic-editor__forget'));

        $conditions[] = self::isWp5()
                        && $pluginsState['classic-editor']
                        && (get_option('classic-editor-replace') === 'classic'
                            && self::is_GET('classic-editor__forget'));

        $conditions[] = $pluginsState['gutenberg-ramp'] 
                        && (empty($_post) || !is_a($_post, 'WP_Post') || apply_filters('use_block_editor_for_post', true, $_post));

        $conditions[] = $pluginsState['disable-gutenberg'] 
                        && !self::disableGutenberg(self::getPostID());

        if (version_compare($wp_version, '5.9-beta', '>=') && !empty($has_nav_filter)) {
            add_filter('use_block_editor_for_post_type', '_disable_block_editor_for_navigation_post_type', 10, 2 );
        }

		// Returns true if at least one condition is true.
		$result = count(
				   array_filter($conditions,
					   function ($c) {
						   return (bool)$c;
					   }
				   )
               ) > 0;
        
        if (!$suppress_filter) {
            $buffer[$post_type] = $result;
        }

        // Returns true if at least one condition is true.
        return $result;
    }

    // Port function from Disable Gutenberg plugin due to problematic early is_plugin_active() function call
    private static function disableGutenberg($post_id = false) {
	
        if (function_exists('disable_gutenberg_whitelist_id') && disable_gutenberg_whitelist_id($post_id)) return false;
        
        if (function_exists('disable_gutenberg_whitelist_slug') && disable_gutenberg_whitelist_slug($post_id)) return false;
        
        if (function_exists('disable_gutenberg_whitelist_title') && disable_gutenberg_whitelist_title($post_id)) return false;
        
        if (self::is_GET('block-editor')) return false;
        
        if (self::is_GET('classic-editor')) return true;
        
        if (self::is_POST('classic-editor')) return true;
        
        if (function_exists('disable_gutenberg_disable_all') && disable_gutenberg_disable_all()) return true;
        
        if (function_exists('disable_gutenberg_disable_user_role') && disable_gutenberg_disable_user_role()) return true;
        
        if (function_exists('disable_gutenberg_disable_post_type') && disable_gutenberg_disable_post_type()) return true;
        
        if (function_exists('disable_gutenberg_disable_templates') && disable_gutenberg_disable_templates()) return true;
        
        if (function_exists('disable_gutenberg_disable_ids') && disable_gutenberg_disable_ids($post_id)) return true;
        
        return false;
    }

    public static function sanitizeWord($key)
    {
        return preg_replace('/[^A-Za-z0-9_\-\.:]/', '', $key);
    }

    public static function sanitizeCSV($key)
    {
        return preg_replace('/[^A-Za-z0-9_\-\.,}{:\|\(\)\s\t\r\n]/', '', $key);
    }

    /**
     * Sanitizes a string entry
     *
     * Keys are used as internal identifiers. Uppercase or lowercase alphanumeric characters,
     * spaces, periods, commas, plusses, asterisks, colons, pipes, parentheses, dashes and underscores are allowed.
     *
     * @param string $entry String entry
     * @return string Sanitized entry
     */
    public static function sanitizeEntry( $entry ) {
        $entry = preg_replace( '/[^a-zA-Z0-9 \.\,\+\*\:\|\(\)_\-]/', '', $entry );
        return $entry;
    }

    /*
    * Same as sanitize_key(), but without applying filters
    */
    public static function sanitizeKey( $key ) {
        $raw_key = $key;
        $key     = strtolower( $key );
        $key     = preg_replace( '/[^a-z0-9_\-]/', '', $key );
        
        return $key;
    }


    // REQUEST / POST / GET / SERVER wrapper functions used to:
    //
    // * Qualify a request to deterimine filter loading
    // * Retrieve a variable when nonce verification is not necessary, or has already been applied

    public static function empty_REQUEST($var = false) {
        if (false === $var) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return empty($_REQUEST);
        } else {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return empty($_REQUEST[$var]);
        }
    }

    public static function is_REQUEST($var, $match = false) {
        if (false === $match) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return isset($_REQUEST[$var]);
            
        } elseif (is_array($match)) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return (isset($_REQUEST[$var]) && in_array($_REQUEST[$var], $match));
        } else {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return (isset($_REQUEST[$var]) && ($_REQUEST[$var] == $match));
        }
    }

    public static function REQUEST_key($var) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (empty($_REQUEST[$var])) {
            return '';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return (is_array($_REQUEST[$var])) ? array_map('sanitize_key', $_REQUEST[$var]) : sanitize_key($_REQUEST[$var]);
    }

    public static function REQUEST_key_match($var, $match, $args = []) {
        $args = (array) $args;
        
        $match_type = (!empty($args['match_type'])) ? $args['match_type'] : 'starts';

        $matched = false;
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $request_key = self::REQUEST_key($var);

        if (is_array($request_key)) {
            $matched = false;
        } else {
            switch ($match_type) {
                case 'contains':
                    $matched = (false !== strpos($request_key, $match));
                    break;
                    
                default: // 'starts'
                    $matched = (0 === strpos($request_key, $match));
            }
        }

        return $matched;
    }

    public static function REQUEST_int($var) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return (!empty($_REQUEST[$var])) ? intval($_REQUEST[$var]) : 0;
    }

    public static function REQUEST_url($var) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return (!empty($_REQUEST) && !empty($_REQUEST[$var])) ? sanitize_url(sanitize_text_field(wp_unslash($_REQUEST[$var]))) : '';
    }

    public static function empty_POST($var = false) {
        if (false === $var) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
            return empty($_POST);
        } else {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
            return empty($_POST[$var]);
        }
    }

    public static function is_POST($var, $match = false) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
        if (empty($_POST)) {
            return false;
        }
        
        if (false == $match) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
            return (isset($_POST[$var]));
        
        } elseif (is_array($match)) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
            return (isset($_POST[$var]) && in_array($_POST[$var], $match));
        } else {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
            return (isset($_POST[$var]) && ($_POST[$var] == $match));
        }
    }

    public static function POST_key($var) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
        if (empty($_POST) || empty($_POST[$var])) {
            return '';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
        return (is_array($_POST[$var])) ? array_map('sanitize_key', $_POST[$var]) : sanitize_key($_POST[$var]);
    }

    public static function POST_int($var) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
        return (!empty($_POST) && !empty($_POST[$var])) ? intval($_POST[$var]) : 0;
    }

    public static function POST_url($var) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
        return (!empty($_POST) && !empty($_POST[$var])) ? sanitize_url(sanitize_text_field(wp_unslash($_POST[$var]))) : '';
    }

    public static function empty_GET($var = false) {
        if (false === $var) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return empty($_GET);
        } else {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return empty($_GET[$var]);
        }
    }

    public static function is_GET($var, $match = false) {
        if (false === $match) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return isset($_GET[$var]);

        } elseif (is_array($match)) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return (isset($_GET[$var]) && in_array($_GET[$var], $match));
        } else {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return (!empty($_GET[$var]) && ($_GET[$var] == $match));
        }
    }

    public static function GET_key($var) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (empty($_GET[$var])) {
            return '';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return (is_array($_GET[$var])) ? array_map('sanitize_key', $_GET[$var]) : sanitize_key($_GET[$var]);
    }

    public static function GET_int($var) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return (!empty($_GET[$var])) ? intval($_GET[$var]) : 0;
    }

    public static function GET_url($var) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return (!empty($_GET[$var])) ? sanitize_url(sanitize_text_field(wp_unslash($_GET[$var]))) : '';
    }

    public static function SERVER_url($var) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return (!empty($_SERVER[$var])) ? sanitize_url(sanitize_text_field(wp_unslash($_SERVER[$var]))) : '';
    }

    public static function isAttachment()
    {
        global $wp_query;
        return !empty($wp_query->query_vars['attachment_id']) || !empty($wp_query->query_vars['attachment']);
    }

    public static function isFront()
    {
        $is_front = (!is_admin() && !defined('XMLRPC_REQUEST') && !defined('DOING_AJAX')
            && (!defined('REST_REQUEST') || !REST_REQUEST) && !self::doingCron());

        return apply_filters('presspermit_is_front', $is_front);
    }

    public static function doingCron()
    {
        // WP Cron Control plugin disables core cron by setting DOING_CRON on every site access.  Use plugin's own scheme to detect actual cron requests.
        $doing_cron = defined('DOING_CRON')
            && (!class_exists('WP_Cron_Control') || !defined('ABSPATH')) && apply_filters('pp_doing_cron', true);

        return $doing_cron;
    }

    // support array matching for post type
    public static function getPostStatuses($args, $return = 'names', $operator = 'and', $function_args = [])
    {
        if (isset($args['post_type'])) {
            $_post_type = $args['post_type'];

            $post_type = $args['post_type'];
            unset($args['post_type']);
            $stati = get_post_stati($args, 'object', $operator);

            foreach ($stati as $status => $obj) {
                if (!empty($obj->post_type) && !array_intersect((array)$post_type, (array)$obj->post_type))
                    unset($stati[$status]);
            }

            // restore original argument for filter
            $args['post_type'] = $_post_type;

            $statuses = ('names' == $return) ? array_keys($stati) : $stati;
        } else {
            $statuses = get_post_stati($args, $return, $operator);
        }

        return apply_filters('presspermit_get_post_statuses', $statuses, $args, $return, $operator, $function_args);
    }

    public static function findPostType($post_id = 0, $return_default = null)
    {
        global $typenow, $post;

        if (!$post_id && defined('REST_REQUEST') && REST_REQUEST) {
            if ($_post_type = apply_filters('presspermit_rest_post_type', '')) {
                return $_post_type;
            }
        }

        if (!$post_id && !empty($typenow)) {
            return $typenow;
        }

        if (is_object($post_id)) {
            $post_id = $post_id->ID;
        }

        if ($post_id && !empty($post) && ($post->ID == $post_id)) {
            return $post->post_type;
        }

        if (defined('DOING_AJAX') && DOING_AJAX) { // todo: separate static function to eliminate redundancy with PostFilters::fltPostsClauses()
            $ajax_post_types = apply_filters('pp_ajax_post_types', ['ai1ec_doing_ajax' => 'ai1ec_event']);

            foreach (array_keys($ajax_post_types) as $arg) {
                if (!self::empty_REQUEST($arg) || self::is_REQUEST('action', $arg)) {
                    return $ajax_post_types[$arg];
                }
            }
        }

        if ($post_id) { // note: calling static function already compared post_id to global $post
            if ($_post = get_post($post_id)) {
                $_type = $_post->post_type;
            }

            if (!empty($_type)) {
                return $_type;
            }
        }

        // no post id was passed in, or we couldn't retrieve it for some reason, so check $_REQUEST args
        global $pagenow, $wp_query;

        if (!empty($wp_query->queried_object)) {
            if (isset($wp_query->queried_object->post_type)) {
                $object_type = $wp_query->queried_object->post_type;

            } elseif (isset($wp_query->queried_object->name)) {
                if (post_type_exists($wp_query->queried_object->name)) {  // bbPress forums list
                    $object_type = $wp_query->queried_object->name;
                }
            }
        } elseif (in_array($pagenow, ['post-new.php', 'edit.php'])) {
            $object_type = self::is_GET('post_type') ? self::GET_key('post_type') : 'post';

        } elseif (in_array($pagenow, ['edit-tags.php'])) {
            $object_type = !self::empty_REQUEST('taxonomy') ? self::REQUEST_key('taxonomy') : 'category';

        } elseif (in_array($pagenow, ['admin-ajax.php']) && !self::empty_REQUEST('taxonomy')) {
            $object_type = self::REQUEST_key('taxonomy');

        } elseif ($_post_id = self::POST_int('post_ID')) {
            if ($_post = get_post($_post_id)) {
                $object_type = $_post->post_type;
            }
        } elseif ($id = self::GET_int('post')) {  // post.php
            if ($_post = get_post($id)) {
                $object_type = $_post->post_type;
            }
        }

        if (empty($object_type)) {
            if (is_null($return_default)) {
                $return_default = !defined('PRESSPERMIT_FIND_POST_TYPE_NO_DEFAULT_TYPE');
            }

            if ($return_default) { // default to post type
                return 'post';
            }
        } elseif ('any' != $object_type) {
            return $object_type;
        }
    }

    public static function getPostID()
    {
        global $post, $wp_query;

        if (defined('REST_REQUEST') && REST_REQUEST) {
            if ($_post_id = apply_filters('presspermit_rest_post_id', 0)) {
                return $_post_id;
            }
        }

        if (!empty($post) && is_object($post) && isset($post->ID)) {
            if (!empty($post->post_status) && ('auto-draft' == $post->post_status)) {
                return 0;
            } else {
                return $post->ID;
            }
        } elseif (!is_admin() && !empty($wp_query) && is_singular()) {
            if (!empty($wp_query)) {
                if (!empty($wp_query->query_vars) && !empty($wp_query->query_vars['p'])) {
                    return (int) $wp_query->query_vars['p'];

                } elseif (!empty($wp_query->query['post_type']) && !empty($wp_query->query['name'])) {
                    global $wpdb;

                    // This direct query is executed only under unusual conditions when the Post ID is not already available
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    return $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_name = %s LIMIT 1",
                            $wp_query->query['post_type'],
                            $wp_query->query['name']
                        )
                    );
                }
            }
        } elseif (self::is_REQUEST('post')) {
            return self::REQUEST_int('post');

        } elseif (self::is_REQUEST('post_ID')) {
            return self::REQUEST_int('post_ID');

        } elseif (self::is_REQUEST('post_id')) {
            return self::REQUEST_int('post_id');

        } elseif (defined('WOOCOMMERCE_VERSION') && !self::empty_REQUEST('product_id')) {
            return self::REQUEST_int('product_id');
        }
    }

    public static function getTaxonomyCap($taxonomy, $cap_property)
    {
        $tx_obj = get_taxonomy($taxonomy);
        return ($tx_obj && isset($tx_obj->cap->$cap_property)) ? $tx_obj->cap->$cap_property : '';
    }

    public static function getTypeCap($post_type, $cap_property)
    {
        $type_obj = get_post_type_object($post_type);
        return ($type_obj && isset($type_obj->cap->$cap_property)) ? $type_obj->cap->$cap_property : '';
    }

    public static function ttidToTermid($tt_ids, &$taxonomy, $all_terms = false)
    {
        if (!$taxonomy && is_array($tt_ids))  // if multiple terms are involved, avoid complication of multiple taxonomies
            return false;

        if (!$all_terms) {
            static $buffer_all_terms;

            if (!isset($buffer_all_terms)) {
                global $wpdb;

                $buffer_all_terms = [];

                // This result is cached to a static variable to ensure no more than one query per http request, for all taxonomies
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $results = $wpdb->get_results("SELECT taxonomy, term_id, term_taxonomy_id FROM $wpdb->term_taxonomy");
                foreach ($results as $row) {
                    $buffer_all_terms[$row->taxonomy][] = $row;
                }
            }

            $all_terms = $buffer_all_terms;
        }

        $term_ids = [];

        if (is_object($all_terms) || !$tt_ids) // error on invalid taxonomy
            return $tt_ids;

        foreach ((array)$tt_ids as $tt_id) {
            foreach (array_keys($all_terms) as $_taxonomy) {
                if ($taxonomy && ($_taxonomy != $taxonomy))  // if conversion is for a single term, taxonomy specification is not required
                    continue;

                foreach (array_keys($all_terms[$_taxonomy]) as $key) {
                    if ($all_terms[$_taxonomy][$key]->term_taxonomy_id == $tt_id) {
                        $term_ids[] = $all_terms[$_taxonomy][$key]->term_id;

                        if (!$taxonomy)
                            $taxonomy = $_taxonomy;  // set byref variable to determined taxonomy

                        break;
                    }
                }
            }
        }

        return (is_array($tt_ids)) ? $term_ids : current($term_ids);
    }

    public static function termidToTtid($term_ids, $taxonomy, $all_terms = false)
    {
        if (!$all_terms) {
            static $buffer_all_terms;

            if (!isset($buffer_all_terms)) {
                global $wpdb;

                $buffer_all_terms = [];

                // This result is cached to a static variable to ensure no more than one query per http request, for all taxonomies
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $results = $wpdb->get_results("SELECT taxonomy, term_id, term_taxonomy_id FROM $wpdb->term_taxonomy");
                foreach ($results as $row) {
                    $buffer_all_terms[$row->taxonomy][] = $row;
                }
            }

            $all_terms = $buffer_all_terms;
        }

        $tt_ids = [];

        if (is_object($all_terms) || !$term_ids || !isset($all_terms[$taxonomy])) // error on invalid taxonomy
            return $term_ids;

        foreach ((array)$term_ids as $term_id) {
            foreach (array_keys($all_terms[$taxonomy]) as $key)
                if (($all_terms[$taxonomy][$key]->term_id == $term_id)) {
                    $tt_ids[] = $all_terms[$taxonomy][$key]->term_taxonomy_id;
                    break;
                }
        }

        return (is_array($term_ids)) ? $tt_ids : current($tt_ids);
    }

    public static function getDescendantIds($item_source, $item_id, $args = [])
    {
        require_once(PRESSPERMIT_CLASSPATH_COMMON . '/Ancestry.php');
        
        switch ($item_source) {
            case 'post':
                // Back compat for existing getDescendantIds() calls
                if (isset($args['post_types'])) {
                    $args['post_type'] = (array) $args['post_types'];
                    unset($args['post_types']);
                }
                
                if (!isset($args['post_type']) && empty($args['any_type_or_taxonomy'])) {
                    $args['post_type'] = false;
                }

                return \PressShack\Ancestry::getPageDescendants($item_id, $args);
                break;

            case 'term':
                // Back compat for existing getDescendantIds() calls
                if (!isset($args['taxonomy']) && !empty($args['any_type_or_taxonomy'])) {
                    $args['taxonomy'] = false;
                }

                return \PressShack\Ancestry::getTermDescendants($item_id, $args);
                break;

            default:
                return [];
        }
    }

    // written because WP is_plugin_active() requires plugin folder in arg
    public static function isPluginActive($check_plugin_file)
    {
        $plugins = (array)get_option('active_plugins');
        foreach ($plugins as $plugin_file) {
            if (false !== strpos($plugin_file, $check_plugin_file)) {
                return $plugin_file;
            }
        }

        if (is_multisite()) {
            $plugins = (array)get_site_option('active_sitewide_plugins');

            // network activated plugin names are array keys
            foreach (array_keys($plugins) as $plugin_file) {
                if (false !== strpos($plugin_file, $check_plugin_file)) {
                    return $plugin_file;
                }
            }
        }
    }

    // Wrapper to prevent poEdit from adding core WordPress strings to the plugin .po
    // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.MethodDoubleUnderscore -- Preserve the legacy method name for backward compatibility.
    public static function __wp($string, $unused = '')
    {
        return __($string);
    }

    public static function wpVer($wp_ver_requirement)
    {
        global $wp_version;

        return version_compare($wp_version, $wp_ver_requirement, '>=');
    }

    public static function isMuPlugin($plugin_path = '')
    {
        if ( ! $plugin_path && defined('PRESSPERMIT_FILE') ) {
            $plugin_path = PRESSPERMIT_FILE;
        }
        return (defined('WPMU_PLUGIN_DIR') && (false !== strpos($plugin_path, WPMU_PLUGIN_DIR)));
    }

    public static function isNetworkActivated($plugin_file = '')
    {
        if (!$plugin_file) {
            if (defined('PRESSPERMIT_PRO_FILE')) {
                $plugin_file = plugin_basename(PRESSPERMIT_PRO_FILE);
            } elseif (defined('PRESSPERMIT_FILE')) {
            	$plugin_file = plugin_basename(PRESSPERMIT_FILE);
        	}
        }
        
        return (array_key_exists($plugin_file, (array)maybe_unserialize(get_site_option('active_sitewide_plugins'))));
    }

    public static function isAjax($action)
    {
        return defined('DOING_AJAX') && DOING_AJAX && $action && in_array(self::REQUEST_key('action'), (array)$action);
    }

    public static function doingAdminMenus()
    {
        return (
            (did_action('_admin_menu') && !did_action('admin_menu'))  // menu construction
            || (did_action('admin_head') && !did_action('adminmenu'))  // menu display
        );
    }

    public static function postAuthorClause($args = []) {
        global $wpdb, $current_user;

        $defaults = [
            'user_id' => $current_user->ID,
            'compare' => '=',
            'join' => '',
        ];

        if ($ppma_active = defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION') 
        && version_compare(PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION, '3.8.0', '>=') 
        && !empty($args['join']) 
        && strpos($args['join'], 'ppma_t')
        && (empty($args['context']) || ('tally_term_counts' != $args['context']))
        ) {
            $defaults['join_table'] = 'ppma_t';
        }

        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (empty($args['src_table'])) {
            $src_table = (!empty($args['source_alias'])) ? $args['source_alias'] : $wpdb->posts;
            $args['src_table'] = $src_table;
        } else {
            $src_table = $args['src_table'];
        }

        if ($ppma_active && $join_table && !defined('PRESSPERMIT_DISABLE_AUTHORS_JOIN')) {
            $join_where = ($compare == '=') ? "OR $join_table.term_id > 0" : "AND $join_table.term_id IS NULL";
            $clause = "( $src_table.post_author $compare '$user_id' $join_where )";
        } else {
            $clause = "$src_table.post_author $compare $user_id";
        }

        return $clause;
    }

    public static function admin_rel_url($admin_page = '') {
        $admin_url = admin_url($admin_page);
        $admin_arr = wp_parse_url($admin_url);
    
        $admin_rel_path = (!empty($admin_arr['path']))
        ? $admin_arr['path']
        : $admin_url;

        return $admin_rel_path;
    }
}
