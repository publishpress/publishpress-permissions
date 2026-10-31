<?php
/** Separate native setup and maintenance requests on an isolated Pro MCP database. */
use WP\MCP\Domain\Tools\McpTool;
if (!defined('ABSPATH') || !str_starts_with(DB_NAME, 'permissions_mcp_') || !defined('PRESSPERMIT_PRO_VERSION')) {
    throw new RuntimeException('Use an isolated Pro MCP database.');
}
require_once getenv('MCP_ADAPTER_TEST_BOOTSTRAP');
wp_set_current_user((int) get_user_by('login', 'mcpadmin')->ID);
$invoke_maintenance = static function ($name, $input) {
    $tool = McpTool::fromAbility(wp_get_ability('publishpress-permissions/' . $name));
    return is_wp_error($tool) ? $tool : $tool->execute($input);
};
$fixture_case = getenv('MCP_FILE_CASE') ?: 'rotate';
if ('failure' === $fixture_case) {
    define('PP_NGINX_CFG_PATH', '/proc/version');
    define('PP_NO_HTACCESS', true);
}
if ('setup' === $fixture_case) {
    $fixture_original = get_option('presspermit_attached_files_private', null);
    presspermit()->updateOption('attached_files_private', '1');
    $fixture_parent = wp_insert_post(['post_type' => 'page', 'post_title' => 'Protected file fixture', 'post_status' => 'private']);
    $fixture_uploads = wp_upload_dir();
    wp_mkdir_p($fixture_uploads['basedir']);
    $fixture_path = trailingslashit($fixture_uploads['basedir']) . 'mcp-protected.pdf';
    file_put_contents($fixture_path, '%PDF-1.0 isolated protection fixture');
    $fixture_attachment = wp_insert_attachment(['post_parent' => $fixture_parent, 'post_mime_type' => 'application/pdf', 'post_title' => 'Protected fixture', 'post_status' => 'inherit',
        'guid' => trailingslashit($fixture_uploads['baseurl']) . 'mcp-protected.pdf'], $fixture_path, $fixture_parent);
    update_post_meta($fixture_attachment, '_rs_file_key', 'old-fixture-key');
    update_option('permissions_mcp_file_fixture', compact('fixture_parent', 'fixture_attachment', 'fixture_path', 'fixture_original'));
    echo "OK (native file-rule fixture setup)\n";
    return;
}
$fixture_record = get_option('permissions_mcp_file_fixture');
if (!is_array($fixture_record)) {
    throw new RuntimeException('Missing isolated native file fixture.');
}
$fixture_parent = $fixture_record['fixture_parent'];
$fixture_attachment = $fixture_record['fixture_attachment'];
$fixture_path = $fixture_record['fixture_path'];
try {
    $fixture_plan = $invoke_maintenance('get-file-maintenance', ['operation' => 'rotate_keys']);
    if (is_wp_error($fixture_plan)) {
        throw new RuntimeException('File rule plan failed: ' . $fixture_plan->get_error_message());
    }
    $fixture_result = $invoke_maintenance('run-file-maintenance', ['operation' => 'rotate_keys', 'revision' => $fixture_plan['revision']]);
    if ('failure' === $fixture_case) {
        if (!is_wp_error($fixture_result)) {
            throw new RuntimeException('Failed file-rule write reported success.');
        }
    } else {
        if (is_wp_error($fixture_result) || empty($fixture_result['completed'])) {
            throw new RuntimeException('Native key rotation failed.');
        }
        $fixture_key = get_post_meta($fixture_attachment, '_rs_file_key', true);
        if (!$fixture_key || 'old-fixture-key' === $fixture_key) {
            throw new RuntimeException('Native rotation did not change the attachment key.');
        }
        $fixture_rules = file_get_contents(trailingslashit(wp_upload_dir()['basedir']) . '.htaccess');
        if (!str_contains($fixture_rules, $fixture_key) || str_contains(wp_json_encode($fixture_result), $fixture_key)) {
            throw new RuntimeException('Native key was not installed privately.');
        }
    }
    echo "OK (native file-rule $fixture_case through MCP Adapter)\n";
} finally {
    wp_delete_attachment($fixture_attachment, true);
    wp_delete_post($fixture_parent, true);
    if (file_exists($fixture_path)) {
        unlink($fixture_path);
    }
    null === $fixture_record['fixture_original'] ? delete_option('presspermit_attached_files_private') : update_option('presspermit_attached_files_private', $fixture_record['fixture_original']);
    presspermit()->refreshOptions();
    delete_option('permissions_mcp_file_fixture');
}
