<?php
/** Run with: php tests/unit/teaser-global-options-migration.php */

namespace {
    define('ABSPATH', __DIR__ . '/../../');
    require_once ABSPATH . 'modules/presspermit-teaser/classes/Permissions/Teaser/GlobalOptionsMigration.php';

    $GLOBALS['pp_test_options'] = [];
    function get_option($name, $default = false) {
        return array_key_exists($name, $GLOBALS['pp_test_options']) ? $GLOBALS['pp_test_options'][$name] : $default;
    }
    function add_option($name, $value = '', $deprecated = '', $autoload = null) {
        if (array_key_exists($name, $GLOBALS['pp_test_options'])) {
            return false;
        }
        $GLOBALS['pp_test_options'][$name] = $value;
        return true;
    }
    function update_option($name, $value) { $GLOBALS['pp_test_options'][$name] = $value; return true; }

    use PublishPress\Permissions\Teaser\GlobalOptionsMigration as M;

    $assertions = 0;
    $check = function ($label, $expected, $actual) use (&$assertions) {
        $assertions++;
        if ((string) $expected !== (string) $actual) {
            throw new \RuntimeException("$label: expected " . json_encode($expected) . ', got ' . json_encode($actual));
        }
    };

    // Free-only site updated from 4.8.x (issue #2635 report): global option never created.
    $GLOBALS['pp_test_options'] = [
        'presspermit_tease_post_types' => ['post' => 1],
        'presspermit_tease_direct_access_only' => ['post' => '1'],
    ];
    M::maybeRun();
    $check('free site seeded', '1', get_option('presspermit_teaser_opt_direct_access_only'));
    $check('done flag set', 1, get_option(M::DONE_FLAG));

    // Runs only once: a later admin change is not overwritten.
    update_option('presspermit_teaser_opt_direct_access_only', 0);
    M::maybeRun();
    $check('runs once', '0', get_option('presspermit_teaser_opt_direct_access_only'));

    // Untease type listed first must not win (the reset() bug).
    $GLOBALS['pp_test_options'] = [
        'presspermit_tease_post_types' => ['page' => 0, 'post' => 1],
        'presspermit_tease_direct_access_only' => ['page' => '0', 'post' => '1'],
    ];
    M::maybeRun();
    $check('ignores types without teaser', '1', get_option('presspermit_teaser_opt_direct_access_only'));

    // Teased types disagree: most restrictive wins.
    $GLOBALS['pp_test_options'] = [
        'presspermit_tease_post_types' => ['page' => 1, 'post' => 1],
        'presspermit_tease_direct_access_only' => ['page' => '0', 'post' => '1'],
        'presspermit_tease_public_posts_only' => ['page' => 'custom', 'post' => '1'],
        'presspermit_tease_logged_only' => ['page' => '0', 'post' => 'anon'],
        'presspermit_teaser_disable_comments' => ['' => 1, 'post' => 0, 'page' => 0],
    ];
    M::maybeRun();
    $check('direct access strictest', '1', get_option('presspermit_teaser_opt_direct_access_only'));
    $check('public posts strictest', '1', get_option('presspermit_teaser_opt_public_posts_only'));
    $check('logged only restrictive', 'anon', get_option('presspermit_teaser_opt_logged_only'));
    $check('ignores default element', '0', get_option('presspermit_teaser_opt_disable_comments'));
    $check('missing old option untouched', 'unset', get_option('presspermit_teaser_opt_hide_thumbnail', 'unset'));

    // Pro site where the faulty migration already wrote reset() => repaired to stricter value.
    $GLOBALS['pp_test_options'] = [
        'presspermit_tease_post_types' => ['page' => 0, 'post' => 1],
        'presspermit_tease_direct_access_only' => ['page' => '0', 'post' => '1'],
        'presspermit_teaser_opt_direct_access_only' => '0',
    ];
    M::maybeRun();
    $check('repairs faulty pro migration', '1', get_option('presspermit_teaser_opt_direct_access_only'));

    // Existing value different from the faulty one is a deliberate choice: keep it.
    $GLOBALS['pp_test_options'] = [
        'presspermit_tease_post_types' => ['post' => 1],
        'presspermit_tease_direct_access_only' => ['page' => '1', 'post' => '1'],
        'presspermit_teaser_opt_direct_access_only' => '0',
    ];
    M::maybeRun();
    $check('keeps deliberate value', '0', get_option('presspermit_teaser_opt_direct_access_only'));

    // Never loosens an existing value.
    $GLOBALS['pp_test_options'] = [
        'presspermit_tease_post_types' => ['post' => 1],
        'presspermit_tease_direct_access_only' => ['page' => '1', 'post' => '0'],
        'presspermit_teaser_opt_direct_access_only' => '1',
    ];
    M::maybeRun();
    $check('never loosens', '1', get_option('presspermit_teaser_opt_direct_access_only'));

    // No teased types at all: fall back to stored per-type values.
    $check('fallback without teased types', '1', M::pickGlobalValue('teaser_hide_thumbnail', ['post' => 0, 'page' => 1], []));
    $check('default element only', '1', M::pickGlobalValue('teaser_disable_comments', ['' => 1], ['post']));


    // Archive all original arrays, including disabled types and default entries.
    $original = [
        'presspermit_tease_direct_access_only' => ['' => 0, 'page' => 0, 'post' => 1],
        'presspermit_tease_logged_only' => ['post' => 'anon'],
    ];
    $GLOBALS['pp_test_options'] = $original + [
        'presspermit_tease_post_types' => ['post' => 1, 'page' => 0],
        'presspermit_teaser_hide_thumbnail' => '1',
    ];
    M::maybeRun();
    $check('complete archive', true, $original === get_option(M::ARCHIVE_OPTION));
    update_option('presspermit_tease_direct_access_only', ['post' => 0]);
    M::maybeRun();
    M::run();
    $check('first archive preserved', true, $original === get_option(M::ARCHIVE_OPTION));
    $check('add_option refuses overwrite', false, add_option(M::ARCHIVE_OPTION, []));
    $check('archive still preserved', true, $original === get_option(M::ARCHIVE_OPTION));

    // Backfill the archive even when global migration was completed previously.
    $GLOBALS['pp_test_options'] = $original + [M::DONE_FLAG => 1];
    M::maybeRun();
    $check('completed migration archive backfilled', true, $original === get_option(M::ARCHIVE_OPTION));

    // A direct run also archives settings, and an empty first snapshot stays intact.
    $GLOBALS['pp_test_options'] = $original;
    M::run();
    $check('direct run archived', true, $original === get_option(M::ARCHIVE_OPTION));
    $GLOBALS['pp_test_options'] = [];
    M::maybeRun();
    $check('empty archive created', true, [] === get_option(M::ARCHIVE_OPTION));
    update_option('presspermit_tease_direct_access_only', ['post' => 1]);
    M::maybeRun();
    $check('empty first archive preserved', true, [] === get_option(M::ARCHIVE_OPTION));

    echo "OK ($assertions assertions)\n";
}

