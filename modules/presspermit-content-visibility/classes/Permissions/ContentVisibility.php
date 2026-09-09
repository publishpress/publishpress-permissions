<?php

namespace PublishPress\Permissions;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Conditionally renders shortcode content for the current visitor.
 */
class ContentVisibility
{
    const SHORTCODE = 'pp_restrict';

    public function __construct()
    {
        add_action('init', [$this, 'registerShortcodes'], 20);
        add_action('wp', [$this, 'markRestrictedQueriesAsDynamic'], 0);
    }

    /**
     * Registers the native shortcode.
     */
    public function registerShortcodes()
    {
        add_shortcode(self::SHORTCODE, [$this, 'renderShortcode']);
    }

    /**
     * Renders [pp_restrict] content when all configured rules match by default.
     *
     * A comma- or space-separated list within one attribute is an OR list.
     * Separate populated attributes are combined using relation="all" unless
     * relation="any" is specified.
     *
     * @param array|string $atts   Shortcode attributes.
     * @param string|null  $content Enclosed content.
     * @return string
     */
    public function renderShortcode($atts, $content = null)
    {
        if (null === $content) {
            return '';
        }

        $this->markResponseAsDynamic();

        $atts = shortcode_atts(
            [
                'logged' => '',
                'roles' => '',
                'capabilities' => '',
                'usernames' => '',
                'groups' => '',
                'pp_group' => '',
                'relation' => 'all',
                'hide' => '',
            ],
            (array) $atts,
            self::SHORTCODE
        );

        $conditions = $this->getConditions($atts);

        // A restriction without any valid rules must never expose its content.
        if (!$conditions) {
            return '';
        }

        $relation = ('any' === strtolower((string) $atts['relation'])) ? 'any' : 'all';
        $allowed = ('any' === $relation)
            ? in_array(true, $conditions, true)
            : !in_array(false, $conditions, true);

        /**
         * Filters whether the current visitor matches a Content Visibility rule.
         *
         * @param bool   $allowed    Whether the visitor matches.
         * @param bool[] $conditions Results for each populated condition type.
         * @param array  $atts       Normalized shortcode attributes.
         * @param string $content    Raw enclosed content.
         */
        $allowed = (bool) apply_filters(
            'presspermit_content_visibility_is_allowed',
            $allowed,
            $conditions,
            $atts,
            $content
        );

        if ($this->isTruthy($atts['hide'])) {
            $allowed = !$allowed;
        }

        return $allowed ? $this->renderAllowedContent($content) : '';
    }

    /**
     * Prevents full-page caches from sharing one visitor's restricted output
     * with another visitor.
     */
    public function markRestrictedQueriesAsDynamic()
    {
        global $wp_query;

        if (empty($wp_query->posts) || !is_array($wp_query->posts)) {
            return;
        }

        foreach ($wp_query->posts as $post) {
            if (
                is_object($post)
                && !empty($post->post_content)
                && $this->containsVisibilityShortcode($post->post_content)
            ) {
                $this->markResponseAsDynamic();
                return;
            }
        }
    }

    /**
     * Builds the results for each populated native shortcode condition.
     *
     * @param array $atts Normalized shortcode attributes.
     * @return bool[]
     */
    private function getConditions($atts)
    {
        $conditions = [];
        $logged = strtolower(trim((string) $atts['logged']));

        if ($logged) {
            $conditions['logged'] = ('in' === $logged)
                ? is_user_logged_in()
                : (('out' === $logged) ? !is_user_logged_in() : false);
        }

        $roles = array_values(
            array_filter(array_map('sanitize_key', $this->splitList($atts['roles'])))
        );

        if ($roles) {
            $user = wp_get_current_user();
            $conditions['roles'] = (bool) array_intersect($roles, (array) $user->roles);
        }

        $capabilities = array_values(
            array_filter(array_map('sanitize_key', $this->splitList($atts['capabilities'])))
        );

        if ($capabilities) {
            $conditions['capabilities'] = $this->currentUserCanAny($capabilities);
        }

        $usernames = array_map(
            function ($username) {
                return strtolower(sanitize_user($username, true));
            },
            $this->splitList($atts['usernames'])
        );
        $usernames = array_values(array_filter($usernames));

        if ($usernames) {
            $conditions['usernames'] = in_array(
                strtolower(wp_get_current_user()->user_login),
                $usernames,
                true
            );
        }

        $groups = array_merge(
            $this->splitList($atts['groups']),
            $this->splitList($atts['pp_group'])
        );
        $group_ids = array_values(array_filter(array_map('absint', $groups)));

        if ($group_ids) {
            $conditions['groups'] = $this->currentUserInAnyPermissionGroup($group_ids);
        }

        return $conditions;
    }

    /**
     * @param string[] $capabilities Capability names.
     * @return bool
     */
    private function currentUserCanAny($capabilities)
    {
        foreach ($capabilities as $capability) {
            if ($capability && current_user_can($capability)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int[] $group_ids Permission Group IDs.
     * @return bool
     */
    private function currentUserInAnyPermissionGroup($group_ids)
    {
        $user = presspermit()->getUser();

        if (empty($user->groups['pp_group']) || !is_array($user->groups['pp_group'])) {
            return false;
        }

        $current_group_ids = array_map('absint', array_keys($user->groups['pp_group']));
        return (bool) array_intersect($group_ids, $current_group_ids);
    }

    /**
     * @param string $content Post content.
     * @return bool
     */
    private function containsVisibilityShortcode($content)
    {
        return has_shortcode($content, self::SHORTCODE);
    }

    /**
     * @param string $content Enclosed shortcode content.
     * @return string
     */
    private function renderAllowedContent($content)
    {
        $content = trim((string) $content);

        if ('' === $content) {
            return '';
        }

        if (function_exists('has_blocks') && has_blocks($content)) {
            return do_shortcode(do_blocks($content));
        }

        return do_shortcode(shortcode_unautop(wpautop($content)));
    }

    /**
     * @param mixed $value List value.
     * @return string[]
     */
    private function splitList($value)
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value), 'strlen'));
        }

        $items = preg_split(
            '/[\s,]+/',
            trim((string) $value),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        return is_array($items) ? $items : [];
    }

    /**
     * @param mixed $value Boolean-like value.
     * @return bool
     */
    private function isTruthy($value)
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'true', 'on'], true);
    }

    private function markResponseAsDynamic()
    {
        if (!defined('DONOTCACHEPAGE')) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standard page-cache interoperability constant.
            define('DONOTCACHEPAGE', true);
        }

        if (!headers_sent()) {
            nocache_headers();
        }
    }
}
