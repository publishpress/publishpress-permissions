<?php

namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

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

    public static function fltConfirmRestReadable($rest_response, $handler, $request)
    {
        if (is_wp_error($rest_response) || !in_array($request->get_method(), ['GET', 'HEAD'], true)
            || !($rest_response instanceof \WP_REST_Response) || $rest_response->get_status() >= 400
        ) {
            return $rest_response;
        }

        $callback = $handler['callback'] ?? null;
        if (!is_array($callback) || !is_object($callback[0] ?? null) || 'get_item' !== ($callback[1] ?? null)) {
            return $rest_response;
        }

        $controller = $callback[0];

        $pp = presspermit();
        if ($pp->isContentAdministrator()) {
            return $rest_response;
        }

        $data = $rest_response->get_data();
        $post_id = is_array($data) ? ($data['id'] ?? null) : null;
        if (!is_numeric($post_id) || (int) $post_id <= 0) {
            return $rest_response;
        }

        $post = get_post((int) $post_id);
        if (!$post) {
            return $rest_response;
        }

        $types = $pp->getEnabledPostTypes(['show_in_rest' => true], 'object');
        $type = $types[$post->post_type] ?? null;
        if (!$type || (!($controller instanceof \WP_REST_Posts_Controller)
            && get_class($controller) !== ($type->rest_controller_class ?? ''))
        ) {
            return $rest_response;
        }

        $status = get_post_status_object($post->post_status);
        if ($status && $status->public && !current_user_can('read_post', $post->ID)) {
            return self::restForbidden();
        }

        return $rest_response;
    }
}
