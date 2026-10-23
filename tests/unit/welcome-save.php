<?php
/**
 * Exercise the real welcome save handler with in-memory WordPress substitutes:
 *
 *   php tests/unit/welcome-save.php
 *
 * Run separately from WordPress and the other standalone test scripts.
 */

define('ABSPATH', __DIR__ . '/../../');

require_once __DIR__ . '/../../classes/PublishPress/Permissions/UI/WelcomePostTypes.php';
require_once __DIR__ . '/../../classes/PublishPress/Permissions/UI/WelcomeExperience.php';

use PublishPress\Permissions\UI\WelcomeExperience;

class WelcomeSaveRequestStopped extends Exception
{
}

class WelcomeSavePermissions
{
    public $netwide_options = [];
    public $options = ['post' => '1', 'page' => '1', 'attachment' => '1', 'legacy_type' => '1'];
    public $writes = 0;

    public function getOption($name)
    {
        if ('enabled_post_types' !== $name) {
            throw new RuntimeException('Unexpected option read: ' . $name);
        }

        return $this->options;
    }

    public function updateOption($name, $value)
    {
        if ('enabled_post_types' !== $name) {
            throw new RuntimeException('Unexpected option write: ' . $name);
        }

        $this->options = $value;
        $this->writes++;
    }
}

function presspermit()
{
    global $permissions;
    return $permissions;
}

function current_user_can($capability)
{
    global $canManageSettings;
    return 'pp_manage_settings' === $capability && $canManageSettings;
}

function check_admin_referer($action)
{
    global $validNonce;

    if (WelcomeExperience::POST_TYPES_NONCE !== $action || ! $validNonce) {
        throw new WelcomeSaveRequestStopped('nonce', 403);
    }
}

function wp_die($message, $title, $args)
{
    throw new WelcomeSaveRequestStopped($message, $args['response']);
}

function esc_html__($message, $domain)
{
    return $message;
}

function wp_unslash($value)
{
    return $value;
}

function is_multisite()
{
    global $networkOwned;
    return $networkOwned;
}

function apply_filters($hook, $value)
{
    global $lockedTypes;
    return 'presspermit_locked_post_types' === $hook ? $lockedTypes : $value;
}

function get_post_types($args, $output)
{
    return [
        'post' => (object) ['name' => 'post'],
        'page' => (object) ['name' => 'page'],
        'attachment' => (object) ['name' => 'attachment'],
    ];
}

function sanitize_key($value)
{
    return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', $value));
}

function wp_safe_redirect($url)
{
    if ('/welcome/teaser' !== $url) {
        throw new RuntimeException('Unexpected redirect: ' . $url);
    }

    // Stop before the production handler's exit, so each case can inspect the saved state.
    throw new WelcomeSaveRequestStopped('redirect', 302);
}

$reflection = new ReflectionClass(WelcomeExperience::class);
$experience = $reflection->newInstanceWithoutConstructor();
$controller = $reflection->getProperty('controller');
$controller->setAccessible(true);
$controller->setValue($experience, new class {
    public function stepUrlForId($id)
    {
        return '/welcome/' . $id;
    }
});

$failures = [];
$assertions = 0;

function check($label, $expected, $actual)
{
    global $failures, $assertions;
    $assertions++;

    if ($expected !== $actual) {
        $failures[] = $label . ': expected ' . json_encode($expected) . ', got ' . json_encode($actual);
    }
}

$unchanged = ['post' => '1', 'page' => '1', 'attachment' => '1', 'legacy_type' => '1'];
$cleared = ['post' => '0', 'page' => '0', 'attachment' => '0', 'legacy_type' => '1'];
$cases = [
    'GET with valid nonce' => ['method' => 'GET', 'status' => 405],
    'missing request method' => ['method' => null, 'status' => 405],
    'invalid nonce' => ['nonce' => false, 'status' => 403],
    'insufficient capability' => ['allowed' => false, 'status' => 403],
    'network-owned setting' => ['network' => true, 'status' => 302],
    'intentional empty POST' => ['writes' => 1, 'saved' => $cleared],
    'selected and forged types' => [
        'posted' => ['page' => '1', 'unknown_type' => '1'],
        'writes' => 1,
        'saved' => array_merge($cleared, ['page' => '1']),
    ],
    'disabled locked checkbox omitted from POST' => [
        'locked' => ['page' => 'page'],
        'writes' => 1,
        'saved' => array_merge($cleared, ['page' => '1']),
    ],
    'unchanged submission avoids a write' => [
        'posted' => ['post' => '1', 'page' => '1', 'attachment' => '1'],
    ],
];

foreach ($cases as $label => $case) {
    $case = array_merge([
        'method' => 'POST',
        'nonce' => true,
        'allowed' => true,
        'network' => false,
        'locked' => [],
        'posted' => [],
        'status' => 302,
        'writes' => 0,
        'saved' => $unchanged,
    ], $case);

    $permissions = new WelcomeSavePermissions();
    $validNonce = $case['nonce'];
    $canManageSettings = $case['allowed'];
    $networkOwned = $case['network'];
    $lockedTypes = $case['locked'];
    if ($networkOwned) {
        $permissions->netwide_options = ['enabled_post_types'];
    }

    unset($_SERVER['REQUEST_METHOD']);
    if (null !== $case['method']) {
        $_SERVER['REQUEST_METHOD'] = $case['method'];
    }
    $_POST = $case['posted'] ? ['ppw_post_types' => $case['posted']] : [];

    $status = 0;
    try {
        $experience->savePostTypes();
    } catch (WelcomeSaveRequestStopped $stop) {
        $status = $stop->getCode();
    }

    check($label . ' response', $case['status'], $status);
    check($label . ' writes', $case['writes'], $permissions->writes);
    check($label . ' saved options', $case['saved'], $permissions->options);
}

if ($failures) {
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Standalone CLI test output, not a filesystem write.
    fwrite(STDERR, "FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}

echo "OK ($assertions welcome save handler assertions)\n";
