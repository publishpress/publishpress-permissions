<?php

namespace PublishPress\Permissions;

class RESTHelper
{
    public static function restForbidden()
    {
        return new \WP_Error('rest_forbidden', esc_html__("Sorry, you are not allowed to do that."), ['status' => 403]);
    }

    public static function getRequestItemId($request)
    {
        $item_id = $request->get_param('id');

        if (!$item_id) {
            $arr_path = explode('/', trim($request->get_route(), '/'));
            $item_id = array_pop($arr_path);
        }

        return (is_numeric($item_id)) ? (int) $item_id : 0;
    }

    public static function confirmCommentReadable($request)
    {
        if (!$comment_id = self::getRequestItemId($request)) {
            return null;
        }

        if (!$comment = get_comment($comment_id)) {
            return null;
        }

        if ($comment->comment_post_ID && !current_user_can('read_post', $comment->comment_post_ID)) {
            return self::restForbidden();
        }

        return null;
    }

    // As of 4.8, WP does not trigger a REST capability check for viewing single public posts
    public static function fltConfirmRestReadable($rest_response, $handler, $request)
    {
        $pp = presspermit();
        $is_readable_request = !is_wp_error($rest_response) && in_array($request->get_method(), [\WP_REST_Server::READABLE, 'GET'], true);

        // we are only concerned about read access here
        if ($is_readable_request) {
            $controller_class = get_class($handler['callback'][0]);

            if ('WP_REST_Posts_Controller' == $controller_class) {
                $is_posts_controller = true;
            } else {
                foreach ($pp->getEnabledPostTypes(['show_in_rest' => true], 'object') as $type_obj) {
                    if (isset($type_obj->rest_controller_class) && ($controller_class == $type_obj->rest_controller_class)) {
                        $is_posts_controller = true;
                        break;
                    }
                }
            }
        }

        if (!empty($is_posts_controller)) {
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
        }

        if ($is_readable_request && !empty($handler['callback'][0]) && is_object($handler['callback'][0]) && ('WP_REST_Comments_Controller' == get_class($handler['callback'][0]))) {
            if ($comment_denied = self::confirmCommentReadable($request)) {
                return $comment_denied;
            }
        }

        return $rest_response;
    }
}
