<?php
namespace PublishPress\Permissions\Compat;

\defined('ABSPATH') || exit;

/** Validate Public Post Preview without granting request-wide unfiltered access. */
class PublicPreview
{
    public static function postForQuery($post_id, $query)
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? ''); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Compare the method only.
        if (is_admin() || !empty($_POST) || !in_array($method, ['GET', 'HEAD'], true)  // phpcs:ignore WordPress.Security.NonceVerification.Missing
            || (defined('REST_REQUEST') && REST_REQUEST)
            || (function_exists('wp_doing_ajax') && wp_doing_ajax())
            || defined('PRESSPERMIT_DISABLE_PPP_PASSTHROUGH')
            || !class_exists('DS_Public_Post_Preview') || !is_object($query)
            || !$query->is_main_query() || !$query->is_preview() || $query->is_feed()
        ) {
            return $post_id;
        }
        $target = (int) ($query->get('p') ?: $query->get('page_id'));
        return $target && get_post($target) && self::available($target) ? $target : $post_id;
    }

    public static function available($post_id)
    {
        if (!$post_id || !class_exists('DS_Public_Post_Preview')) {
            return false;
        }
        // Use the plugin API when public; older versions expose only a private verifier.
        if (is_callable(['DS_Public_Post_Preview', 'is_public_preview_available'])) {
            return (bool) \DS_Public_Post_Preview::is_public_preview_available($post_id);
        }
        $nonce = isset($_GET['_ppp']) && is_string($_GET['_ppp']) ? sanitize_key(wp_unslash($_GET['_ppp'])) : get_query_var('_ppp'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is the post-bound public preview token, verified below.
        if (!is_string($nonce) || '' === $nonce
            || !in_array((int) $post_id, array_map('intval', (array) get_option('public_post_preview', [])), true)) {
            return false;
        }
        $hours = get_option('public_post_preview_expiration_time') ?: 48;
        $life = (float) apply_filters('ppp_nonce_life', $hours * HOUR_IN_SECONDS);
        if ($life <= 0) {
            return false;
        }
        $tick = ceil(time() / ($life / 2));
        foreach ([$tick, $tick - 1] as $value) {
            if (hash_equals(substr(wp_hash($value . 'public_post_preview_' . (int) $post_id, 'nonce'), -12, 10), $nonce)) {
                return true;
            }
        }
        return false;
    }
}
