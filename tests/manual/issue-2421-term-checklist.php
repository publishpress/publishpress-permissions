<?php

/**
 * Manual integration regression test for issue #2421.
 *
 * Run this file after loading WordPress in an admin context with the
 * Collaboration module active. The test creates and removes its own user,
 * terms and exceptions.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "WordPress must be loaded before running this test.\n");
    exit(1);
}

if (!is_admin() || !presspermit()->moduleActive('collaboration')) {
    fwrite(STDERR, "Run this test in an admin context with the Collaboration module active.\n");
    exit(1);
}

$assert = static function ($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

global $pagenow;

$original_user_id = get_current_user_id();
$original_pagenow = $pagenow ?? null;
$pagenow = 'post.php';

$test_suffix = strtolower(wp_generate_password(8, false, false));
$test_user_id = 0;
$test_term_ids = [];
$failure = null;

try {
    $test_user_id = wp_insert_user([
        'user_login' => "pp2421_{$test_suffix}",
        'user_pass' => wp_generate_password(24, true, true),
        'user_email' => "pp2421_{$test_suffix}@example.com",
        'role' => 'contributor',
    ]);
    $assert(!is_wp_error($test_user_id), 'Could not create the test user.');

    $term_names = [
        'top' => "PP2421 {$test_suffix} Top",
        'middle' => "PP2421 {$test_suffix} Middle",
        'child_a' => "PP2421 {$test_suffix} Child A",
        'child_b' => "PP2421 {$test_suffix} Child B",
        'grandchild_a' => "PP2421 {$test_suffix} Grandchild A",
        'grandchild_b' => "PP2421 {$test_suffix} Grandchild B",
    ];

    $top = wp_insert_term($term_names['top'], 'category');
    $assert(!is_wp_error($top), 'Could not create the top-level term.');
    $test_term_ids[] = $top['term_id'];

    $middle = wp_insert_term($term_names['middle'], 'category', ['parent' => $top['term_id']]);
    $assert(!is_wp_error($middle), 'Could not create the restricted term.');
    $test_term_ids[] = $middle['term_id'];

    $child_a = wp_insert_term($term_names['child_a'], 'category', ['parent' => $middle['term_id']]);
    $assert(!is_wp_error($child_a), 'Could not create Child A.');
    $test_term_ids[] = $child_a['term_id'];

    $child_b = wp_insert_term($term_names['child_b'], 'category', ['parent' => $middle['term_id']]);
    $assert(!is_wp_error($child_b), 'Could not create Child B.');
    $test_term_ids[] = $child_b['term_id'];

    $grandchild_a = wp_insert_term($term_names['grandchild_a'], 'category', ['parent' => $child_a['term_id']]);
    $assert(!is_wp_error($grandchild_a), 'Could not create Grandchild A.');
    $test_term_ids[] = $grandchild_a['term_id'];

    $grandchild_b = wp_insert_term($term_names['grandchild_b'], 'category', ['parent' => $child_b['term_id']]);
    $assert(!is_wp_error($grandchild_b), 'Could not create Grandchild B.');
    $test_term_ids[] = $grandchild_b['term_id'];

    $middle_term = get_term($middle['term_id'], 'category');
    $assert($middle_term && !is_wp_error($middle_term), 'Could not load the restricted term.');

    wp_set_current_user($test_user_id);
    presspermit()->setUser($test_user_id);

    $unrestricted_subset = get_terms([
        'taxonomy' => 'category',
        'get' => 'all',
        'include' => [$middle['term_id']],
        'required_operation' => 'assign',
        'object_type' => 'post',
    ]);
    $assert(!is_wp_error($unrestricted_subset), 'The unrestricted control query failed.');
    $assert(1 === count($unrestricted_subset), 'The unrestricted control query returned an unexpected number of terms.');
    $assert(
        (int) $top['term_id'] === (int) reset($unrestricted_subset)->parent,
        'An unrestricted partial query was unexpectedly remapped.'
    );

    wp_set_current_user($original_user_id);
    presspermit()->setUser($original_user_id);

    \PublishPress\Permissions\API::assignExceptions(
        [
            'item' => [$test_user_id => true],
            'children' => [$test_user_id => true],
        ],
        'user',
        [
            'operation' => 'assign',
            'mod_type' => 'include',
            'for_item_source' => 'post',
            'for_item_type' => 'post',
            'via_item_source' => 'term',
            'via_item_type' => 'category',
            'item_id' => $middle_term->term_taxonomy_id,
        ]
    );

    wp_set_current_user($test_user_id);
    presspermit()->setUser($test_user_id);

    $terms = get_terms([
        'taxonomy' => 'category',
        'get' => 'all',
        'required_operation' => 'assign',
        'object_type' => 'post',
    ]);
    $assert(!is_wp_error($terms), 'The restricted term query failed.');

    $terms_by_id = [];
    foreach ($terms as $term) {
        $terms_by_id[$term->term_id] = $term;
    }

    $assert(empty($terms_by_id[$top['term_id']]), 'The inaccessible ancestor was not filtered out.');
    $assert(isset($terms_by_id[$middle['term_id']]), 'The restricted subtree root is missing.');
    $assert(0 === (int) $terms_by_id[$middle['term_id']]->parent, 'The restricted subtree root was not remapped to level zero.');
    $assert((int) $middle['term_id'] === (int) $terms_by_id[$child_a['term_id']]->parent, 'Child A was orphaned.');
    $assert((int) $middle['term_id'] === (int) $terms_by_id[$child_b['term_id']]->parent, 'Child B was orphaned.');
    $assert((int) $child_a['term_id'] === (int) $terms_by_id[$grandchild_a['term_id']]->parent, 'Grandchild A was orphaned.');
    $assert((int) $child_b['term_id'] === (int) $terms_by_id[$grandchild_b['term_id']]->parent, 'Grandchild B was orphaned.');

    $stored_middle = get_term($middle['term_id'], 'category');
    $assert((int) $top['term_id'] === (int) $stored_middle->parent, 'The stored term parent was unexpectedly modified.');

    require_once(ABSPATH . 'wp-admin/includes/template.php');
    ob_start();
    wp_terms_checklist(0, ['taxonomy' => 'category']);
    $checklist = ob_get_clean();

    $middle_position = strpos($checklist, esc_html($term_names['middle']));
    $child_a_position = strpos($checklist, esc_html($term_names['child_a']));
    $grandchild_a_position = strpos($checklist, esc_html($term_names['grandchild_a']));

    $assert(false !== $middle_position, 'The restricted subtree root is missing from the checklist.');
    $assert(false !== $child_a_position, 'Child A is missing from the checklist.');
    $assert(false !== $grandchild_a_position, 'Grandchild A is missing from the checklist.');
    $assert($middle_position < $child_a_position, 'The restricted subtree root is rendered after Child A.');
    $assert($child_a_position < $grandchild_a_position, 'Grandchild A is not rendered beneath Child A.');
} catch (Throwable $throwable) {
    $failure = $throwable;
} finally {
    wp_set_current_user($original_user_id);
    presspermit()->setUser($original_user_id);

    if ($test_user_id && !is_wp_error($test_user_id)) {
        \PublishPress\Permissions\API::deleteExceptions([$test_user_id], 'user');
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        wp_delete_user($test_user_id);
    }

    foreach (array_reverse($test_term_ids) as $term_id) {
        wp_delete_term($term_id, 'category');
    }

    if (null === $original_pagenow) {
        unset($GLOBALS['pagenow']);
    } else {
        $pagenow = $original_pagenow;
    }
}

if ($failure) {
    fwrite(STDERR, 'FAIL: ' . $failure->getMessage() . "\n");
    exit(1);
}

echo "PASS: Issue #2421 term checklist hierarchy is preserved.\n";
