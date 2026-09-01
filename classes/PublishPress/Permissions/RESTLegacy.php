<?php

namespace PublishPress\Permissions;

class RESTLegacy
{
    // Block unpermitted read requests (WP does not trigger a REST capability check for viewing single public posts)
    public static function fltPreDispatch($rest_response, $rest_server, $request)
    {
        if (!is_wp_error($rest_response) && !in_array($request->get_method(), [\WP_REST_Server::READABLE, 'GET'], true))
            return $rest_response;

        require_once(PRESSPERMIT_CLASSPATH . '/RESTHelper.php');

        $pp = presspermit();

        $path = $request->get_route();
        $method = $request->get_method();

        foreach ($rest_server->get_routes() as $route => $handlers) {
            if (preg_match('@^' . $route . '$@i', $path, $args)) {
                foreach ($handlers as $handler) {
                    if (!empty($handler['methods'][$method]) && is_array($handler['callback']) && is_object($handler['callback'][0])) {
                        if ('WP_REST_Posts_Controller' == get_class($handler['callback'][0])) {
                            // back post type and ID out of path because WP_REST_Posts_Controller does not expose them
                            $arr_path = explode('/', $request->get_route());

                            $post_id = array_pop($arr_path);

                            if ($post_id && is_numeric($post_id)) {
                                $rest_base = array_pop($arr_path);

                                if ($pp->getEnabledPostTypes(['rest_base' => $rest_base])) {
                                    if ($post_status_obj = get_post_status_object(get_post_field('post_status', $post_id))) {
                                        if ($post_status_obj->public && !current_user_can('read_post', $post_id)) {
                                            return new \WP_Error('rest_forbidden', esc_html__("Sorry, you are not allowed to do that."), ['status' => 403]);
                                        }
                                    }
                                }
                            }

                        } elseif ('WP_REST_Comments_Controller' == get_class($handler['callback'][0])) {
                            if ($comment_denied = RESTHelper::confirmCommentReadable($request)) {
                                return $comment_denied;
                            }
                        }
                    }
                }
            }
        }

        return $rest_response;
    }
}
