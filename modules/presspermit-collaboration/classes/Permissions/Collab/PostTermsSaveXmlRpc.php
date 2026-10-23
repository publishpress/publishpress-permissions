<?php
namespace PublishPress\Permissions\Collab;

\defined('ABSPATH') || exit;

class PostTermsSaveXmlRpc
{
    public static function getPostedXmlrpcTerms($taxonomy)
    {
        global $wp_xmlrpc_server;

        if (empty($wp_xmlrpc_server->message))
            return [];

        $xmlrpc_method = $wp_xmlrpc_server->message->methodName;

        if (empty($wp_xmlrpc_server->message->params))
            return [];

        if (in_array($xmlrpc_method, ['metaWeblog.newPost', 'metaWeblog.editPost'], true)) {
            if (!empty($wp_xmlrpc_server->message->params[3])) {
                $data = $wp_xmlrpc_server->message->params[3];

                if ('category' == $taxonomy) {
                    if (is_array($data['categories'])) {
                        $post_category = [];
                        foreach ($data['categories'] as $cat) {
                            $post_category[] = get_cat_ID($cat);
                        }

                        return $post_category;
                    }
                } elseif ('post_tag' == $taxonomy) {
                    if (!empty($data['mt_keywords'])) {
                        $tags = $data['mt_keywords'];
                        $comma = _x(',', 'tag delimiter');
                        if (',' !== $comma)
                            $tags = str_replace($comma, ',', $tags);
                        $tags = explode(',', trim($tags, " \n\t\r\0\x0B,"));
                        return $tags;
                    }
                }
            }
        } elseif (in_array($xmlrpc_method, ['blogger.newPost', 'blogger.editPost'], true)) {
            if (!empty($wp_xmlrpc_server->message->params[4])) {
                $data = $wp_xmlrpc_server->message->params[4];

                if ('category' == $taxonomy) {
                    if (function_exists('xmlrpc_getpostcategory')) {
                        $post_category = xmlrpc_getpostcategory($data);
                        return $post_category;
                    }
                }
            }
        } elseif (in_array($xmlrpc_method, ['wp.newPost', 'wp.editPost'], true)) {
            if (!empty($wp_xmlrpc_server->message->params[3])) {
                $post_data = $wp_xmlrpc_server->message->params[3];

                // accumulate term IDs from terms and terms_names
                $terms = [];

                if (isset($post_data['terms']) && is_array($post_data['terms'])) {
                    foreach ($post_data['terms'][$taxonomy] as $term_id) {
                        if ($term = get_term_by('id', $term_id, $taxonomy)) {
                            $terms[] = (int)$term_id;
                        }
                    }
                }

                if (isset($post_data['terms_names']) && is_array($post_data['terms_names'])) {
                    foreach ($post_data['terms_names'][$taxonomy] as $term_name) {
                        // term creation is outside the scope of this usage
                        if ($term = get_term_by('name', $term_name, $taxonomy)) { 
                            $terms[] = (int)$term->term_id;
                        }
                    }
                }

                return $terms;
            }
        }

        return [];
    }
} // end class
