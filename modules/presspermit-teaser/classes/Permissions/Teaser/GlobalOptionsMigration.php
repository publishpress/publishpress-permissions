<?php
namespace PublishPress\Permissions\Teaser;

\defined('ABSPATH') || exit;

/**
 * One-time migration of the per-post-type Teaser options that became single global
 * options in 4.9.0 (issue #2518).
 *
 * The original 4.9.0 migration had two problems (issue #2635):
 *  - It was only hooked to the Pro update action, so Free-only sites never ran it and
 *    fell back to the defaults (e.g. "List and Single view"), exposing restricted posts
 *    in search results, archives and Query Loop blocks.
 *  - It seeded each global option from the first element of the old per-type array,
 *    which was often a post type with the teaser disabled, or a less restrictive value.
 *
 * This migration runs once (tracked by an option flag) on any Free or Pro update. It
 * derives each global value from the post types that actually had the teaser enabled,
 * and when they disagree it keeps the most restrictive value.
 */
class GlobalOptionsMigration
{
    const DONE_FLAG = 'presspermit_teaser_global_options_migrated';

    /**
     * Old per-type option name => new global option name.
     */
    public static function optionMap()
    {
        return [
            'tease_direct_access_only' => 'teaser_opt_direct_access_only',
            'tease_logged_only' => 'teaser_opt_logged_only',
            'teaser_hide_menu_links_type' => 'teaser_opt_hide_menu_links',
            'tease_public_posts_only' => 'teaser_opt_public_posts_only',
            'teaser_hide_thumbnail' => 'teaser_opt_hide_thumbnail',
            'teaser_disable_comments' => 'teaser_opt_disable_comments',
        ];
    }

    public static function maybeRun()
    {
        if (get_option(self::DONE_FLAG)) {
            return;
        }

        self::run();

        update_option(self::DONE_FLAG, 1);
    }

    public static function run()
    {
        $teased_types = array_keys(array_filter((array) get_option('presspermit_tease_post_types')));

        foreach (self::optionMap() as $old_name => $new_name) {
            $old_value = get_option("presspermit_$old_name");

            if (!is_array($old_value) || !$old_value) {
                continue;
            }

            $migrated = self::pickGlobalValue($old_name, $old_value, $teased_types);

            if (null === $migrated) {
                continue;
            }

            $current = get_option("presspermit_$new_name");

            if (false === $current) {
                // Never migrated (Free-only sites before this fix).
                update_option("presspermit_$new_name", $migrated);
                continue;
            }

            // The option exists. If it still holds exactly what the faulty 4.9.0 migration
            // would have written, and the correct value is more restrictive, repair it.
            // A less restrictive correction is never applied over an existing value.
            $faulty = reset($old_value);

            if ((string) $current === (string) $faulty
                && self::rank($old_name, $migrated) > self::rank($old_name, $current)
            ) {
                update_option("presspermit_$new_name", $migrated);
            }
        }
    }

    /**
     * Derive a single global value from an old per-type array.
     *
     * @param string $old_name     Old per-type option name (without presspermit_ prefix).
     * @param array  $old_value    post_type => value (may include a '' default element).
     * @param array  $teased_types Post types that had the teaser enabled.
     * @return mixed|null Null if nothing usable is stored.
     */
    public static function pickGlobalValue($old_name, $old_value, $teased_types)
    {
        $old_value = (array) $old_value;

        $default = array_key_exists('', $old_value) ? $old_value[''] : null;
        unset($old_value['']);

        // Prefer post types that actually had the teaser enabled. Settings stored for
        // types without a teaser had no effect, so they should not drive the result.
        $candidates = array_intersect_key($old_value, array_fill_keys((array) $teased_types, true));

        if (!$candidates) {
            $candidates = $old_value;
        }

        if (!$candidates) {
            return $default;
        }

        $picked = null;
        $picked_rank = -1;

        foreach ($candidates as $val) {
            $rank = self::rank($old_name, $val);

            if ($rank > $picked_rank) {
                $picked = $val;
                $picked_rank = $rank;
            }
        }

        return $picked;
    }

    /**
     * How restrictive a value is (higher = shows less restricted content).
     */
    public static function rank($old_name, $val)
    {
        $val = (string) $val;

        if ('tease_public_posts_only' === $old_name) {
            // 1 = hide all private posts, custom = hide custom private statuses only.
            $ranks = ['1' => 2, 'custom' => 1];
            return isset($ranks[$val]) ? $ranks[$val] : 0;
        }

        // tease_logged_only: '1' (logged in only) or 'anon' (anonymous only) each turn the
        // teaser off for one group, so either is more restrictive than '0' (everyone).
        // The remaining options are on/off flags where on is more restrictive.
        return ('' === $val || '0' === $val) ? 0 : 1;
    }
}
