<?php
/** Run with: php tests/unit/exception-post-ids.php */

define('ABSPATH', __DIR__ . '/../../');

class WP_User
{
}

require_once ABSPATH . 'classes/PressShack/LibArray.php';
class_alias('PressShack\LibArray', 'PublishPress\Arr');
require_once ABSPATH . 'classes/PublishPress/PermissionsUser.php';
require_once ABSPATH . 'classes/PublishPress/Permissions/DB/Permissions.php';

class ExceptionIdsUser extends \PublishPress\PermissionsUser
{
    public function retrieveExceptions($operations = [], $for_item_sources = [], $args = [])
    {
    }
}

function apply_filters($hook, $value, ...$args)
{
    global $filteredIds;
    return false === $value ? $filteredIds : $value;
}

// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Standalone regression must fail on the reported array-to-string warning.
set_error_handler(function ($severity, $message) {
    throw new RuntimeException($message);
});

$user = (new ReflectionClass(ExceptionIdsUser::class))->newInstanceWithoutConstructor();
$filteredIds = false;
$user->except = ['associate_post' => ['post' => ['' => [
    'include' => ['page' => ['' => [[12, ['13']], 14], 'post_status:private' => [99]]],
    'additional' => ['page' => ['' => [[15, [16]]]]],
    'exclude' => ['page' => ['' => []]],
]]]];

$assertions = 0;
function check($expected, $actual)
{
    global $assertions;
    $assertions++;
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . json_encode($expected) . ', got ' . json_encode($actual));
    }
}

check([12, '13', 14], $user->getExceptionPosts('associate', 'include', 'page'));
check([99], $user->getExceptionPosts('associate', 'include', 'page', ['status' => 'post_status:private']));
check(
    $user->except['associate_post']['post']['']['include']['page'],
    $user->getExceptionPosts('associate', 'include', 'page', ['status' => true])
);
check(
    " AND ID IN ('12','13','14','15','16')",
    \PublishPress\Permissions\DB\Permissions::getExceptionsClause('associate', 'page', ['user' => $user])
);

$filteredIds = [[12, ['13']], [[[0]]]];
check([12, '13', 0], $user->getExceptionPosts('associate', 'include', 'page'));
check($filteredIds, $user->getExceptionPosts('associate', 'include', 'page', ['status' => true]));
check([12, '13'], array_values(array_intersect([12, '13', 99], $user->getExceptionPosts('associate', 'include', 'page'))));
check([1, 2, 3, 4], \PressShack\LibArray::flatten([1, [2, [[3]]], 4], true));

restore_error_handler();
echo "OK ($assertions exception post ID assertions)\n";
