<?php
namespace PublishPress\Permissions;

class REST
{
    var $route = '';
    var $is_view_method = false;
    var $endpoint_class = '';
    var $taxonomy = '';
    var $post_type = '';
    var $post_id = 0;
    var $post_status = '';
    var $is_posts_request = false;
    var $is_terms_request = false;
    var $operation = '';
    var $params = [];
    var $referer = '';

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new REST();
            presspermit()->doing_rest = true;
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_filter('presspermit_rest_post_cap_requirement', [$this, 'fltRestPostCapRequirement'], 10, 2);
    }

    public static function getPostType()
    {
        return self::instance()->post_type;
    }

    public static function getPostID()
    {
        return self::instance()->post_id;
    }

    function fltRestPostCapRequirement($orig_cap, $item_id)
    {
        if ('edit' == $this->operation) {
            $post_type = get_post_field('post_type', $item_id);

            if ($type_obj = get_post_type_object($post_type)) {
                if ($orig_cap == $type_obj->cap->read_post) {
                    if ('auto-draft' != get_post_field('post_status', $item_id)) {
                        $orig_cap = $type_obj->cap->edit_post;
                    }
                }
            }
        }

        return $orig_cap;
    }

    function pre_dispatch($rest_response, $rest_server, $request)
    {
        $method = $request->get_method();
        $path   = $request->get_route();
        $routes = $rest_server->get_routes();
        
        $post_endpoints = apply_filters('presspermit_rest_post_endpoints', []);
        $term_endpoints = apply_filters('presspermit_rest_term_endpoints', []);
        
        $extra_route_endpoints = array_replace($post_endpoints, $term_endpoints);
        $endpoint_post_types = [];
		
        foreach($extra_route_endpoints as $route => $endpoint) {
            if ($route && !is_numeric($route)) {
                $set_routes []= $route;
                $set_routes []= $route . '/(?P<id>[\d]+)';

                foreach($set_routes as $route) {
					if (is_array($endpoint)) {
                        $_endpoint = $endpoint;
                        $endpoint = reset($_endpoint);
                        $post_type = key($_endpoint);

                        if ($post_type) {
                            $endpoint_post_types[$endpoint] = $post_type;
                        }
						
                        if (isset($post_endpoints[$route]) && is_array($post_endpoints[$route])) {
                            $post_endpoints[$route] = $endpoint;
                        }
						
                        if (isset($term_endpoints[$route]) && is_array($term_endpoints[$route])) {
                            $term_endpoints[$route] = $endpoint;
                        }
                    }
					
                    if (!empty($routes[$route])) {
                        $routes[$route] []= ['callback' => [0 => $endpoint]];
                    } else {
                        $routes[$route] = ['callback' => [0 => $endpoint]];
                    }
                }
            }
        }

        $post_endpoints[]= 'WP_REST_Posts_Controller';
        $post_endpoints[]= 'WP_REST_Autosaves_Controller';
        $term_endpoints[]= 'WP_REST_Terms_Controller';
		
		foreach ( $routes as $route => $handlers ) {
			$match = preg_match( '@^' . $route . '$@i', $path, $matches );

			if ( ! $match ) {
				continue;
			}

			$args = [];
			foreach ( $matches as $param => $value ) {
				if ( ! is_int( $param ) ) {
					$args[ $param ] = $value;
				}
			}

			foreach ( $handlers as $handler ) {
                if (!is_array($handler['callback']) || !isset($handler['callback'][0])) {
                    continue;
                }

                if (is_object($handler['callback'][0])) {
					$this->endpoint_class = get_class($handler['callback'][0]);

                } elseif (is_string($handler['callback'][0])) {
                    $this->endpoint_class = $handler['callback'][0];
                } else {
                    continue;
                }
				
                if (!in_array($this->endpoint_class, $post_endpoints, true) && !in_array($this->endpoint_class, $term_endpoints, true)
                ) {
                    continue;
                }
				
                $this->route = $route;

                $this->is_view_method = in_array($method, [\WP_REST_Server::READABLE, 'GET']);
                $this->params = $request->get_params();
                
                $headers = $request->get_headers();
                $this->referer = (isset($headers['referer'])) ? $headers['referer'] : '';
                if (is_array($this->referer)) {
                    $this->referer = reset($this->referer);
                }

                $this->operation = (isset($this->params['context'])) ? sanitize_key($this->params['context']) : '';
                if ('view' == $this->operation) {
                    $this->operation = 'read';
                }

			  // voluntary filtering of get_items (for WYSIWY can edit, etc.)
                if ($this->is_view_method && ('read' == $this->operation) && !PWP::empty_REQUEST('operation')) {
                    $this->operation = PWP::REQUEST_key('operation');
                }
			
                // NOTE: setting or default may be adapted downstream
                if (!in_array($this->operation, ['edit', 'assign', 'manage', 'delete'], true)) {
                    if ($this->is_view_method) {
                        $this->operation = ('WP_REST_Terms_Controller' == $this->endpoint_class) ? apply_filters('presspermit_rest_view_terms_operation', 'assign', $this) : 'read';
                    } else {
                        $this->operation = 'edit';
                    }
                }

                if (in_array($this->endpoint_class, $post_endpoints)) {
                    $this->post_type = (!empty($args['post_type'])) ? $args['post_type'] : '';
                    
                    if (!$this->post_type && !empty($endpoint_post_types[$this->endpoint_class])) {
                        $this->post_type = $endpoint_post_types[$this->endpoint_class];
                        $this->params['post_type'] = $this->post_type;
                    }
                
                    if ( ! $this->post_id = (!empty($args['id'])) ? $args['id'] : 0 ) {
                        $this->post_id = (!empty($this->params['id'])) ? $this->params['id'] : 0;
                    }

                    // workaround for superfluous post retrieval by Gutenberg on Parent Page query
                    if ($this->is_view_method && !$this->post_id) {
                        $params = $request->get_params();

                        if (!empty($params['exclude']) || !empty($params['parent_exclude'])) {
                            // Prevent Gutenberg from triggering needless post_name retrieval (for permalink generation) for each item in Page Parent dropdown
                            if (!empty($_SERVER) && !empty($_SERVER['HTTP_REFERER']) && false !== strpos(esc_url_raw($_SERVER['HTTP_REFERER']), admin_url())) {
                                global $wp_post_types;

                                if (!$this->post_type) {
                                    $id = (!empty($params['exclude'])) ? $params['exclude'] : $params['parent_exclude'];
                                    $this->post_type = get_post_field('post_type', $id);
                                }

                                if (!empty($wp_post_types[$this->post_type])) {
                                    $wp_post_types[$this->post_type]->publicly_queryable = false;
                                    $wp_post_types[$this->post_type]->_builtin = false;
                                }

                                // Prevent Gutenberg from triggering revisions retrieval for each item in Page Parent dropdown
                                add_filter('wp_revisions_to_keep', function($num, $post) {return 0;}, 10, 2);
                            }
                        }
                    }

                    if (!$this->post_type) {
                        if (!$this->post_type = get_post_field('post_type', $this->post_id)) {
                            return $rest_response;
                        }
                    } elseif (!empty($args['post_type'])) {
                        $this->post_type = $args['post_type'];
                    }

                    $this->is_posts_request = true;

                    if (!presspermit()->isContentAdministrator()) {
                        // do this here because WP does not trigger a capability check if the post type is public
                        if ($this->post_id && in_array($this->post_type, presspermit()->getEnabledPostTypes(), true)) {
                            if ('read' == $this->operation) {
                                $post_status_obj = get_post_status_object(get_post_field('post_status', $this->post_id));
                                $check_cap = ($post_status_obj->public) ? 'read_post' : '';

                            } elseif(in_array($this->operation, ['edit','delete'], true)) {
                                $check_cap = "{$this->operation}_post";
                            } else {
                                $check_cap = false;
                            }

                            if ($check_cap && ! current_user_can($check_cap, $this->post_id) 
                            && (('edit' != $this->operation) || ('trash' != get_post_field('post_status', $this->post_id)))
                            ) { // Avoid conflicts with WP trashing. WP will still prevent editing of trashed posts
                                return self::rest_denied();
                            }
                        }
                    }

                } elseif (in_array($this->endpoint_class, $term_endpoints)) { 
                    if (!empty($this->referer) && strpos($this->referer, 'post-new.php') && !empty($this->endpoint_class) && ('WP_REST_Terms_Controller' == $this->endpoint_class)) {
                        $this->operation = 'assign';
                        $this->is_view_method = false;

                        $required_operation = 'assign';
                    } else {
                        $required_operation = ('read' == $this->operation) ? 'read' : 'manage';
                    }
                    
                    $this->is_terms_request = true;

                    if (empty($args['taxonomy'])) break;

                    $this->taxonomy = $args['taxonomy'];

                    if (!presspermit()->isContentAdministrator()) {
                        if (!empty($args['post'])) {
                            $post_id = $this->params['post'];

                            $check_cap = ('read' == $required_operation) ? 'read_post' : 'edit_post';

                            if (!current_user_can($check_cap, $post_id)) {
                                return self::rest_denied();
                            }
                        }

                        if (!empty($params['id'])) {
                            $user_terms = get_terms(
                                $this->taxonomy, 
                                ['required_operation' => $required_operation, 'hide_empty' => 0, 'fields' => 'ids']
                            );

                            if (!in_array($params['id'], $user_terms)) {
                                return self::rest_denied();
                            }
                        }
                    }
                }
            }
        }

        if ($this->is_posts_request) {
            add_filter('presspermit_rest_post_type', [$this, 'fltRestPostType']);
            add_filter('presspermit_rest_post_id', [$this, 'fltRestPostID']);
        }

        return $rest_response;
    }  // end function pre_dispatch

    private function rest_denied()
    {
        // leave a diagnostic clue that the 403 was triggered by PublishPress Permissions
        $msg = (in_array(get_locale(), ['en_EN', 'en_US'])) ? "Sorry, you are not permitted to do that." : esc_html__("Sorry, you are not allowed to do that.");
        return new \WP_Error('rest_forbidden', $msg, ['status' => 403]);
    }

    function fltRestPostType($post_type)
    {
        return ($this->post_type) ? $this->post_type : $post_type;
    }

    function fltRestPostID($post_id)
    {
        return ($this->post_id) ? $this->post_id : $post_id;
    }
}
