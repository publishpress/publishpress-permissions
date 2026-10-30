<?php

namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

class Capabilities
{
    public $all_type_caps = [];    // $all_type_caps = array of cap names
    private $processed_types = [];
    
    public function __construct()
    {
        $this->forceDistinctPostCaps();
    }

    public function refresh()
    {
        $this->forceDistinctPostCaps();

        do_action('presspermit_refresh_capabilities');
    }

    public function forceDistinctPostCaps()
    {  // but only if the post type has PP filtering enabled
        global $wp_post_types, $wp_roles;

        $pp = presspermit();

        $core_meta_caps = array_fill_keys(['read_post', 'edit_post', 'delete_post'], true);

        $append_caps = [
            'edit_published_posts' => 'edit_posts',
            'edit_private_posts' => 'edit_posts',
            'delete_posts' => 'edit_posts',
            'delete_others_posts' => 'delete_posts',
            'delete_published_posts' => 'delete_posts',
            'delete_private_posts' => 'delete_posts',
            'read' => PRESSPERMIT_READ_PUBLIC_CAP,
        ];

        if (get_option('presspermit_define_create_posts_cap')) {
            foreach (['post', 'page'] as $post_type) {
                if ($wp_post_types[$post_type]->cap->create_posts == $wp_post_types[$post_type]->cap->edit_posts) {
                    $wp_post_types[$post_type]->cap->create_posts = "create_{$post_type}s";
                }
            }

            foreach ($pp->getEnabledPostTypes() as $post_type) {
                if (!in_array($post_type, ['post', 'page'])) {
                    if ($wp_post_types[$post_type]->cap->create_posts == $wp_post_types[$post_type]->cap->edit_posts) {
                        $wp_post_types[$post_type]->cap->create_posts = str_replace('edit_', 'create_', $wp_post_types[$post_type]->cap->edit_posts);
                    }
                }
            }

            $append_caps['create_posts'] = 'create_posts';
        }

        // count the number of post types that use each capability
		foreach( $wp_post_types as $post_type => $type_obj ) {
			foreach( array_unique( (array) $type_obj->cap ) as $cap_name ) {
				if ( ! isset( $this->all_type_caps[$cap_name] ) ) {
					$this->all_type_caps[$cap_name] = 1;
				} else {
					$this->all_type_caps[$cap_name]++;
				}
			}
		}
		
		$post_caps = (array) $wp_post_types['post']->cap;
		$page_caps = ( isset( $wp_post_types['page'] ) ) ? (array) $wp_post_types['page']->cap : [];
		
		$enabled_types = array_diff( $pp->getEnabledPostTypes(), $this->processed_types );

        // post types which are enabled for PP filtering must have distinct type-related cap definitions
        foreach ($enabled_types as $post_type) {
			if (empty($wp_post_types[$post_type]) || empty($wp_post_types[$post_type]->cap)) {
				continue;
			}
			
			if (('attachment' == $post_type) && !$pp->getOption('define_media_post_caps')) {
				if (isset($wp_post_types['attachment']) && is_object($wp_post_types['attachment']->cap)) {
					foreach(['edit_posts', 'edit_others_posts', 'delete_posts', 'delete_others_posts'] as $_post_cap) {
						$wp_post_types['attachment']->cap->$_post_cap = $_post_cap;
					}
				}

				continue;
			}

            // append missing capability definitions
            foreach ($append_caps as $prop => $default) {
                if (!isset($wp_post_types[$post_type]->cap->$prop)) {
                    $wp_post_types[$post_type]->cap->$prop = ('read' == $prop) ? PRESSPERMIT_READ_PUBLIC_CAP : $wp_post_types[$post_type]->cap->$default;
                }
            }

            $wp_post_types[$post_type]->map_meta_cap = true;

			if (!isset($wp_post_types[$post_type]->cap->list_published_posts)) {
				$cap_name = ('page' == $post_type) ? 'list_published_pages' : 'list_published_posts';
				$wp_post_types[$post_type]->cap->list_published_posts = $cap_name;
				$this->all_type_caps[$cap_name] = (isset($this->all_type_caps[$cap_name])) ? $this->all_type_caps[$cap_name]++ : 1;
			}

			if (!isset($wp_post_types[$post_type]->cap->list_private_posts)) {
				$cap_name = ('page' == $post_type) ? 'list_private_pages' : 'list_private_posts';
				$wp_post_types[$post_type]->cap->list_private_posts = $cap_name;
				$this->all_type_caps[$cap_name] = (isset($this->all_type_caps[$cap_name])) ? $this->all_type_caps[$cap_name]++ : 1;
			}

            $type_caps = array_diff_key((array)$wp_post_types[$post_type]->cap, $core_meta_caps);

            $cap_base = ('attachment' == $post_type) ? 'file' : $post_type;

            $cap_properties = array_keys($type_caps);

            if ('attachment' == $post_type) {  
				$cap_properties = array_diff(
                    $cap_properties, 
                    [
                        'publish_posts', 
                        'edit_published_posts', 
                        'delete_published_posts', 
                        'edit_private_posts', 
                        'delete_private_posts', 
                        'read_private_posts'
                    ]
                );
			}
			
			// 'read' is not converted to a type-specific equivalent, so disregard it for perf. 
			$cap_properties = array_diff($cap_properties, ['read', PRESSPERMIT_READ_PUBLIC_CAP]);

            foreach($cap_properties as $k => $cap_property) {
				// If a cap property is set to one of the generic post type's caps, we will replace it
				if (( 'post' != $post_type) && in_array($type_caps[$cap_property], $post_caps, true)) {
					continue;
				}
				
				if (('page' != $post_type) && in_array($type_caps[$cap_property], $page_caps, true)) {
					continue;
				}
				
				// If a cap property is non-generic and not used by any other post types, keep it as is
				if ($this->all_type_caps[$type_caps[$cap_property]] <= 1) {
					unset($cap_properties[$k]);
			
				// If a cap property is used by any other post types, still keep it if it is the standard type-specific capability form for this post type
				} elseif (($type_caps[$cap_property] == str_replace("_posts", "_{$post_type}s", $cap_property))
						|| ($type_caps[$cap_property] == str_replace("_pages", "_{$post_type}s", $cap_property))) {
					
					unset($cap_properties[$k]);
				
				// If a cap property is used by any other post types, still keep it if it is the custom pluralized type-specific capability form for this post type
				} else {
					$plural_type = self::getPlural($post_type, $wp_post_types[$post_type]);
					if (($type_caps[$cap_property] == str_replace("_posts", "_{$plural_type}", $cap_property))
						|| ($type_caps[$cap_property] == str_replace("_pages", "_{$plural_type}", $cap_property))) {

						unset($cap_properties[$k]);
					}
				}
			}

			if (!empty($wp_post_types[$post_type]->cap->edit_published_posts)) {
				$wp_post_types[$post_type]->cap->list_published_posts = str_replace('edit_', 'list_', $wp_post_types[$post_type]->cap->edit_published_posts);
			}

			if (!empty($wp_post_types[$post_type]->cap->edit_private_posts)) {
				$wp_post_types[$post_type]->cap->list_private_posts = str_replace('edit_', 'list_', $wp_post_types[$post_type]->cap->edit_private_posts);
			}

			if (!$cap_properties) { 
				// This post type has no defaulted cap properties that need to be made type-specific.
				continue;
			}

			$plural_type = self::getPlural($post_type, $wp_post_types[$post_type]);
			
			if ("{$cap_base}s" != $plural_type) {
				// If any role already has capabilities based on simple plural form, keep using that instead
				foreach ($wp_roles as $role) {
					foreach(array_keys($type_caps) as $cap_property) {
						$generic_type = (strpos($cap_property, '_pages')) ? 'page' : 'post';
						
						$simple_plural = str_replace("_{$generic_type}s", "_{$cap_base}s", $cap_property);
						
						if (isset($role->capabilities[$simple_plural])) {
							// A simple plural capability was manually stored to a role, so stick with that form
							$plural_type = "{$cap_base}s";
							break 2;
						}
					}
				}
			}
			
			// Replace "edit_posts" and other post type caps with an equivalent for this post type, using pluralization determined above.
			// If a this is a problem, register the post type with an array capability_type arg including the desired plural form.
			// It is also possible to modify existing $wp_post_types[$post_type]->cap values by hooking to the init action at priority 40.
			foreach($cap_properties as $cap_property) {
				// create_posts capability may be defaulted to "edit_posts" / "edit_pages"
				$generic_type = (strpos($cap_property, '_pages')) ? 'page' : 'post';

				$target_cap_property = ('create_posts' == $cap_property) ? $wp_post_types[$generic_type]->cap->$cap_property : $cap_property;
			
				if ($plural_type != "{$cap_base}s") {
					// Since plural form is not simple, first replace plurals ('edit_posts' > 'edit_doohickies')
					$wp_post_types[$post_type]->cap->$cap_property = str_replace("_{$generic_type}s", "_{$plural_type}", $target_cap_property);
				} else {
					// Replace based on simple plural ('edit_posts' > 'edit_doohickys')
					$wp_post_types[$post_type]->cap->$cap_property = str_replace("_{$generic_type}", "_{$cap_base}", $target_cap_property);
				}
			}

			// Force distinct capability_type. This may be an array with plural form in second element (but not useful here if set as default 'post' / 'posts' ).
			// Some caution here against changing the variable data type. Although array is supported, other plugin code may assume string.
			if (is_array($wp_post_types[$post_type]->capability_type)) {
				$wp_post_types[$post_type]->capability_type = [$post_type, $plural_type];

			} elseif (in_array($wp_post_types[$post_type]->capability_type, ['post','page'])) {
				$wp_post_types[$post_type]->capability_type = $post_type;
			}
			
			$type_caps = array_diff_key((array)$wp_post_types[$post_type]->cap, $core_meta_caps);

			$wp_post_types[$post_type]->cap = (object) array_merge((array) $wp_post_types[$post_type]->cap, $type_caps);
			
			foreach(array_unique((array)$wp_post_types[$post_type]->cap) as $cap_name) {
				if (!isset($this->all_type_caps[$cap_name])) {
					$this->all_type_caps[$cap_name] = 1;
				} else {
					$this->all_type_caps[$cap_name]++;
				}
			}

		} // end foreach post type
		
		$this->processed_types = array_merge($this->processed_types, $enabled_types);
		
		// need this for casting to other types even if "post" type is not enabled for PP filtering
		$wp_post_types['post']->cap->set_posts_status = 'set_posts_status';
    }

    public static function getPlural( $slug, $type_obj = false ) {
        if ($type_obj && ! empty($type_obj->rest_base) && ($type_obj->rest_base != $slug) && ($type_obj->rest_base != "{$slug}s")) {
            // Use plural form from rest_base
            if ($pos = strpos($type_obj->rest_base, '/')) {
                return sanitize_key(substr($type_obj->rest_base, 0, $pos + 1));
            } else {
                return sanitize_key($type_obj->rest_base);
            }
        } else {
            require_once (PRESSPERMIT_CLASSPATH_COMMON . '/Inflect.php');
            return sanitize_key(\PressShack\Inflect::pluralize($slug));	
        }
    }
}
