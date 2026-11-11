<?php
namespace PublishPress\Permissions\Mcp;

\defined('ABSPATH') || exit;

/** Teaser modes, rich messages, redirects, RSS and notice presentation. */
class Teaser extends NativeOptions
{
    protected function definitions($scope)
    {
        $captions = [];
        $caption_file = PRESSPERMIT_ABSPATH . '/modules/presspermit-teaser/classes/Permissions/Teaser/UI/PostsTeaser.php';
        if (is_file($caption_file)) {
            require_once $caption_file;
            $captions = (new \PublishPress\Permissions\Teaser\UI\PostsTeaser(false))->optionCaptions([]);
        }
        $captions += [
            'tease_post_types' => __('Teaser Type', 'press-permit-core'),
            'x_chars_num_chars' => __('Number of characters to show', 'press-permit-core'),
            'excerpt_num_chars' => __('Maximum excerpt characters', 'press-permit-core'),
            'tease_replace_content' => __('Restricted content message', 'press-permit-core'),
            'teaser_hide_links_taxonomy' => __('Restricted link taxonomy', 'press-permit-core'),
            'teaser_hide_links_term' => __('Restricted link term', 'press-permit-core'),
        ];
        $defs = [];
        $add = static function ($name, $schema, $default, $extra = []) use (&$defs, $captions) {
            $defs[$name] = ['schema' => $schema, 'default' => $default, 'label' => $captions[$name] ?? ucwords(str_replace('_', ' ', $name))] + $extra;
        };
        $text = ['type' => 'string', 'maxLength' => 20000];
        if (!$scope) {
            foreach (['teaser_opt_direct_access_only', 'teaser_opt_hide_menu_links', 'teaser_opt_hide_thumbnail', 'teaser_opt_disable_comments'] as $name) {
                $add($name, ['type' => 'boolean'], 'teaser_opt_disable_comments' === $name, ['empty_default' => 'teaser_opt_disable_comments' === $name]);
            }
            $add('teaser_opt_logged_only', ['type' => 'string', 'enum' => ['0', 'anon', '1']], '0');
            $privacy = ['0', '1'];
            if (defined('PUBLISHPRESS_STATUSES_VERSION') || class_exists('PublishPress\Statuses\Factory')) {
                $privacy[] = 'custom';
            }
            $add('teaser_opt_public_posts_only', ['type' => 'string', 'enum' => $privacy], '0');
            foreach (['rss_private_feed_mode', 'rss_nonprivate_feed_mode'] as $name) {
                $add($name, ['type' => 'string', 'enum' => ['full_content', 'excerpt_only', 'title_only']],
                    'rss_private_feed_mode' === $name ? 'title_only' : 'full_content');
            }
            $add('feed_teaser', $text, presspermit()->default_options['feed_teaser'] ?? '', ['rich' => true]);
            $add('teaser_hide_links_taxonomy', ['type' => 'string', 'maxLength' => 100], '');
            $add('teaser_hide_links_term', ['type' => 'integer', 'minimum' => 0], 0);
            return $defs;
        }
        $add('tease_post_types', ['type' => 'string', 'enum' => ['0', '1', 'read_more', 'excerpt', 'more', 'x_chars', 'redirect']], '0');
        foreach (['x_chars_num_chars', 'excerpt_num_chars'] as $name) {
            $add($name, ['type' => 'integer', 'minimum' => 10, 'maximum' => 1000], defined('PP_TEASER_NUM_CHARS') ? PP_TEASER_NUM_CHARS : 50);
        }
        foreach (['tease_replace_content', 'tease_prepend_content', 'tease_append_content', 'tease_prepend_name',
            'tease_append_name', 'tease_replace_excerpt', 'tease_prepend_excerpt', 'tease_append_excerpt'] as $name) {
            $add($name, $text, '', ['rich' => true]);
            if ('tease_replace_content' !== $name) {
                $add($name . '_anon', $text, '', ['rich' => true]);
            }
        }
        foreach (['teaser_redirect', 'teaser_redirect_anon'] as $name) {
            $modes = ['0', '(select)', '(url)'];
            if ('teaser_redirect_anon' === $name) {
                $modes[] = '(login)';
            }
            $add($name, ['type' => 'string', 'enum' => $modes], '0');
            $add($name . '_page', ['type' => 'integer', 'minimum' => 0], 0);
            $add($name . '_post_type', ['type' => 'string', 'enum' => array_values(array_diff(get_post_types(['public' => true]), ['attachment']))], 'page');
            $add($name . '_url', ['type' => 'string', 'maxLength' => 2000], '');
        }
        foreach (['bg_color' => '#f0f6fc', 'text_color' => '#1d2327', 'border_color' => '#0073aa'] as $suffix => $default) {
            $add('teaser_notice_' . $suffix, ['type' => 'string', 'pattern' => '^#(?:[a-fA-F0-9]{3}|[a-fA-F0-9]{6})$'], $default, ['empty_default' => true]);
        }
        $add('teaser_notice_border_position', ['type' => 'string', 'enum' => ['left', 'right', 'top', 'bottom', 'all']], 'left');
        foreach (['border_width' => [0, 20, 4], 'border_radius' => [0, 50, 0], 'font_size' => [10, 30, 14], 'padding' => [0, 50, 15]] as $suffix => $bounds) {
            $add('teaser_notice_' . $suffix, ['type' => 'integer', 'minimum' => $bounds[0], 'maximum' => $bounds[1]], $bounds[2]);
        }
        return $defs;
    }

    protected function authorize($scope)
    {
        if (!presspermit()->moduleActive('teaser') || is_wp_error(Access::requireCaps(['pp_manage_teaser', 'pp_manage_settings']))) {
            return Access::denied();
        }
        if ($scope) {
            $type = get_post_type_object($scope);
            if (!$type || !in_array($scope, presspermit()->getEnabledPostTypes(), true)
                || !current_user_can($type->cap->edit_others_posts) || !current_user_can($type->cap->read_private_posts)) {
                return Access::denied();
            }
        }
        return true;
    }

    public function read($input)
    {
        $state = parent::read($input);
        if (is_wp_error($state) || !$state['post_type']) {
            return $state;
        }
        $scope = $state['post_type'];
        $legacy = presspermit()->getTypeOption('teaser_num_chars', $scope);
        foreach (['x_chars_num_chars', 'excerpt_num_chars'] as $name) {
            $stored = presspermit()->getTypeOption($name, $scope);
            if (in_array($stored, [false, ''], true) && !empty($legacy)) {
                $value = Input::from_native($legacy, $state['settings']->{$name}['schema']);
                if (is_wp_error($value)) {
                    return $value;
                }
                $state['settings']->{$name}['value'] = $value;
            }
        }
        $state['settings']->tease_replace_content['effective_value'] = \PublishPress\Permissions\TeaserHooks::getTeaserOptionOrDefault('tease_replace_content', $scope);
        $state['revision'] = Access::revision([$state['revision'], (new Settings())->snapshot('teaser_num_chars')]);
        return $state;
    }
    protected function normalize($name, $value, $definition)
    {
        if (str_ends_with($name, '_url') && $value) {
            $url = esc_url_raw($value, ['http', 'https']);
            $parts = wp_parse_url($url);
            if (!$url || !is_array($parts) || empty($parts['host']) || !empty($parts['user']) || !empty($parts['pass'])) {
                return Input::error();
            }
            return $url;
        }
        return parent::normalize($name, $value, $definition);
    }

    protected function checkPatch($scope, $values, $changes)
    {
        if (!$scope) {
            $taxonomy = $values['teaser_hide_links_taxonomy'];
            $id = $values['teaser_hide_links_term'];
            if ($taxonomy) {
                $tx = get_taxonomy($taxonomy);
                if (!$tx || !current_user_can($tx->cap->manage_terms)
                    || ($id && (!get_term($id, $taxonomy) || is_wp_error(get_term($id, $taxonomy)) || !current_user_can('edit_term', $id)))) {
                    return Access::denied();
                }
            } elseif ($id) {
                return Input::error();
            }
            return true;
        }
        foreach (['teaser_redirect', 'teaser_redirect_anon'] as $name) {
            if ('(select)' === $values[$name]) {
                $id = $values[$name . '_page'];
                $post = get_post($id);
                $type = get_post_type_object($values[$name . '_post_type']);
                if (!$post || !$type || !$type->public || 'attachment' === $type->name || $post->post_type !== $type->name
                    || !current_user_can('read_post', $id) || !current_user_can('edit_post', $id)) {
                    return Access::denied();
                }
            }
            if ('(url)' === $values[$name] && !$values[$name . '_url']) {
                return Input::error();
            }
        }
        return true;
    }
}
