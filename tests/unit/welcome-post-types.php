<?php
/**
 * Regression checks for the welcome guide's post type save logic. Runs without WordPress:
 *
 *   php tests/unit/welcome-post-types.php
 */

define('ABSPATH', __DIR__ . '/../../');

require_once __DIR__ . '/../../classes/PublishPress/Permissions/UI/WelcomePostTypes.php';

use PublishPress\Permissions\UI\WelcomePostTypes;

$failures = [];
$assertions = 0;

function check($label, $expected, $actual)
{
    global $failures, $assertions;

    $assertions++;

    // Key order in an options map carries no meaning.
    if (is_array($expected) && is_array($actual)) {
        ksort($expected);
        ksort($actual);
    }

    if ($expected !== $actual) {
        $failures[] = $label . "\n  expected: " . json_encode($expected) . "\n  actual:   " . json_encode($actual);
    }
}

$offered = ['post', 'page', 'attachment'];

// Ticked types are stored as '1', unticked offered types as '0'.
check(
    'ticked and unticked types',
    ['post' => '1', 'page' => '0', 'attachment' => '1'],
    WelcomePostTypes::applyChoices(['post' => true, 'page' => true, 'attachment' => true], $offered, [], ['post', 'attachment'])
);

// A fresh install has no stored value, so every offered type is written explicitly.
check(
    'fresh install',
    ['post' => '1', 'page' => '1', 'attachment' => '0'],
    WelcomePostTypes::applyChoices([], $offered, [], ['post', 'page'])
);

// Types the guide did not offer keep whatever was stored, including custom types that are no longer registered.
check(
    'unoffered types are untouched',
    ['post' => '1', 'page' => '0', 'attachment' => '0', 'product' => '1', 'legacy_type' => true],
    WelcomePostTypes::applyChoices(['product' => '1', 'legacy_type' => true], $offered, [], ['post'])
);

// A forged field name can neither create an entry nor switch on a type that was never offered.
check(
    'unknown names are ignored',
    ['post' => '1', 'page' => '0', 'attachment' => '0'],
    WelcomePostTypes::applyChoices([], $offered, [], ['post', 'wp_not_offered', '../../etc'])
);

check(
    'a type the guide did not offer stays off',
    ['shop_order' => '0', 'post' => '0', 'page' => '0', 'attachment' => '0'],
    WelcomePostTypes::applyChoices(['shop_order' => '0'], $offered, [], ['shop_order'])
);

// Locked types stay on even when the checkbox was disabled and therefore not submitted.
check(
    'locked types stay on',
    ['post' => '0', 'page' => '1', 'attachment' => '0'],
    WelcomePostTypes::applyChoices([], $offered, ['page'], [])
);

// Clearing every box turns every offered type off, matching the Settings screen.
check(
    'nothing selected',
    ['post' => '0', 'page' => '0', 'attachment' => '0'],
    WelcomePostTypes::applyChoices(['post' => '1', 'page' => '1', 'attachment' => '1'], $offered, [], [])
);

// Applying the same submission twice gives the same result.
$once = WelcomePostTypes::applyChoices(['post' => true], $offered, [], ['page']);
check('idempotent', $once, WelcomePostTypes::applyChoices($once, $offered, [], ['page']));

if ($failures) {
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Standalone CLI test output, not a filesystem write.
    fwrite(STDERR, "FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}

echo "OK ($assertions welcome post type assertions)\n";
