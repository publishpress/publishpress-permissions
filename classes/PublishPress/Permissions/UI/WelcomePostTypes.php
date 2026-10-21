<?php

namespace PublishPress\Permissions\UI;

/**
 * Post type choices for the welcome guide's content step, and the validated save behind them.
 *
 * Saving follows the Settings screen: a type is stored as '1' or '0', types the guide does not
 * offer keep their stored value, and the Settings screen remains the place for everything else.
 */
class WelcomePostTypes
{
    const OPTION = 'enabled_post_types';

    /**
     * Post types the guide offers, keyed by name: Posts, Pages and Media first, then the rest A to Z.
     *
     * @return \WP_Post_Type[]
     */
    public static function choices()
    {
        $types = get_post_types(['public' => true, 'show_ui' => true], 'objects');

        $omit = array_merge(
            (array) apply_filters('presspermit_unfiltered_post_types', ['wp_block']),
            array_keys((array) apply_filters('presspermit_hidden_post_types', []))
        );

        $types = array_diff_key($types, array_fill_keys($omit, true));
        $first = ['post' => 0, 'page' => 1, 'attachment' => 2];

        uasort($types, function ($a, $b) use ($first) {
            $rankA = isset($first[$a->name]) ? $first[$a->name] : 99;
            $rankB = isset($first[$b->name]) ? $first[$b->name] : 99;

            return $rankA === $rankB ? strcasecmp(self::label($a), self::label($b)) : $rankA - $rankB;
        });

        return $types;
    }

    /** @return string */
    public static function label($type)
    {
        if (isset($type->labels_pp->name)) {
            return (string) $type->labels_pp->name;
        }

        return isset($type->labels->name) ? (string) $type->labels->name : (string) $type->name;
    }

    /**
     * Types Permissions always keeps on, keyed by name.
     *
     * @return array
     */
    public static function locked()
    {
        return array_filter((array) apply_filters('presspermit_locked_post_types', ['nav_menu' => 'nav_menu']));
    }

    /**
     * Enabled state as the Settings screen reports it, keyed by post type name.
     *
     * @return array
     */
    public static function enabled()
    {
        return (array) apply_filters('presspermit_enabled_post_types', presspermit()->getOption(self::OPTION));
    }

    /**
     * Whether the current user may change the setting here. A network-wide value belongs to the network admin.
     *
     * @return bool
     */
    public static function canSave()
    {
        if (! current_user_can('pp_manage_settings')) {
            return false;
        }

        return ! (is_multisite() && in_array(self::OPTION, (array) presspermit()->netwide_options, true));
    }

    /**
     * @param array $posted Submitted checkbox values keyed by post type name.
     * @return bool True when the submission was accepted.
     */
    public static function save($posted)
    {
        if (! self::canSave()) {
            return false;
        }

        $selected = [];
        foreach ((array) $posted as $name => $value) {
            if (is_string($name) && $value) {
                $selected[] = sanitize_key($name);
            }
        }

        $pp = presspermit();
        $current = $pp->getOption(self::OPTION);
        $current = is_array($current) ? $current : [];

        $value = self::applyChoices($current, array_keys(self::choices()), array_keys(self::locked()), $selected);

        if ($value !== $current) {
            $pp->updateOption(self::OPTION, $value);
        }

        return true;
    }

    /**
     * Merge a submission into the stored setting.
     *
     * Only types the guide offered can change, so a forged field name never creates or alters an entry.
     * Types left unticked are stored as '0', matching the Settings screen, and locked types stay on.
     *
     * @param array    $current  Stored setting.
     * @param string[] $allowed  Post types offered by the guide.
     * @param string[] $locked   Post types that must stay enabled.
     * @param string[] $selected Post types ticked in the submission.
     * @return array
     */
    public static function applyChoices(array $current, array $allowed, array $locked, array $selected)
    {
        $selected = array_flip(array_intersect($selected, $allowed));

        foreach ($allowed as $name) {
            $current[$name] = (isset($selected[$name]) || in_array($name, $locked, true)) ? '1' : '0';
        }

        return $current;
    }
}
