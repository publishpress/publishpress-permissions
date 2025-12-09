<?php

namespace PublishPress\Permissions;

class Constants 
{
    public $constants = [];
    public $constant_types = [];
    public $constants_by_type = [];

    public function __construct() {
        do_action('presspermit_load_constants');

        $this->loadConstants();
        $this->loadConstantTypes();
    }

private function loadConstants() {
$type = 'filtering-switches';
$consts = [
    'PP_RESTRICTION_PRIORITY' => esc_html__("Specific Permissions: restrictions ('Blocked') take priority over additions ('Enabled')", 'press-permit-core'),
    'PP_NO_GROUP_RESTRICTIONS' => esc_html__("Specific Permissions: no restrictions ('Blocked') can be applied to custom-defined groups", 'press-permit-core'),
    'PP_ALL_ANON_ROLES' => esc_html__("Extra Roles assignment available for {All} and {Anonymous} metagroups", 'press-permit-core'),
    'PP_ALL_ANON_FULL_EXCEPTIONS' => esc_html__("Allow the {All} and {Anonymous} metagroups to be granted specific reading permissions for private content", 'press-permit-core'),
    'PP_EDIT_EXCEPTIONS_ALLOW_DELETION' => esc_html__("Users who have specific editing permissions for a post or attachment can also delete it", 'press-permit-core'),
    'PP_EDIT_EXCEPTIONS_ALLOW_ATTACHMENT_DELETION' => esc_html__("Users who have custom editing permissions for an attachment can also delete it", 'press-permit-core'),
    'PP_ALLOW_UNFILTERED_FRONT' => esc_html__("Disable front end filtering if logged user is a content administrator (normally filter to force inclusion of readable private posts in get_pages() listing, post counts, etc.", 'press-permit-core'),
    'PP_UNFILTERED_FRONT' => esc_html__("Disable front end filtering for all users (subject to limitation by PP_UNFILTERED_FRONT_TYPES)", 'press-permit-core'),
    'PP_UNFILTERED_FRONT_TYPES' => esc_html__("Comma-separated list of post types to limit the effect of PP_UNFILTERED_FRONT and apply_filters( 'presspermit_skip_cap_filtering' )", 'press-permit-core'),
    'PP_NO_ADDITIONAL_ACCESS' => esc_html__("Specific Permissions: additions ('Enabled') are not applied, cannot be assigned", 'press-permit-core'),
    'PP_POST_NO_EXCEPTIONS' => esc_html__("Don't assign or apply specific permissions for the 'post' type", 'press-permit-core'),
    'PP_PAGE_NO_EXCEPTIONS' => esc_html__("Don't assign or apply specific permissions for the 'page' type", 'press-permit-core'),
    'PP_ATTACHMENT_NO_EXCEPTIONS' => esc_html__("Don't assign or apply specific permissions for the 'media' type", 'press-permit-core'),
    'PP_MY_CUSTOM_TYPE_NO_EXCEPTIONS' => esc_html__("Don't assign or apply specific permissions for the specified custom post type", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'front-end';
$consts = [
    'PP_FUTURE_POSTS_BLOGROLL' => esc_html__("Include scheduled posts in the posts query if user can edit them", 'press-permit-core'),
    'PP_UNFILTERED_TERM_COUNTS' => esc_html__("Don't filter term post counts in get_terms() call", 'press-permit-core'),
    'PP_DISABLE_NAV_MENU_FILTER' => esc_html__("Leave unreadable posts on WP Navigation Menus", 'press-permit-core'),
    'PRESSPERMIT_NO_NAV_MENU_SCRIPTS' => esc_html__("Don't apply CSS to hide empty Nav Menus", 'press-permit-core'),
    'PRESSPERMIT_HIDE_EMPTY_NAV_MENU_DIV' => esc_html__("For legacy Nav Menus, hide empty nav menu div", 'press-permit-core'),
    'PP_NAV_MENU_SHOW_EMPTY_TERMS' => esc_html__("Leave terms with no readable posts on WP Navigation Menus", 'press-permit-core'),
];

if (defined('PRESSPERMIT_TEASER_VERSION')) {
    $consts = array_merge(
        $consts,
        [
        'PRESSPERMIT_TEASER_REDIRECT_ARG' => esc_html__("When Teaser is applied with Redirect enabled, append original url as redirect_to argument", 'press-permit-core'),
        'PRESSPERMIT_TEASER_REDIRECT_VAR' => esc_html__("For Teaser compatibility, specify a redirect argument to use instead of redirect_to", 'press-permit-core'),
        'PRESSPERMIT_TEASER_REDIRECT_ALTERNATE' => esc_html__("For Teaser compatibility, specify an additional redirect argument other than redirect_to", 'press-permit-core'),
        'PRESSPERMIT_TEASER_LOGIN_REDIRECT_NO_PP_ARG' => esc_html__("For Teaser compatibility, prevent the pp_permissions argument from being appended to redirects", 'press-permit-core'),
        ]
    );
}
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'get-pages';
$consts = [
    'PP_GET_PAGES_PRIORITY' => esc_html__("Filter priority for 'get_pages' filter (default: 1)", 'press-permit-core'),
    'PP_SUPPRESS_PRIVATE_PAGES' => esc_html__("Don't include readable private pages in the Pages widget or other wp_list_pages() / get_pages() results    ", 'press-permit-core'),
    'PPC_FORCE_PAGE_REMAP' => esc_html__("If some pages have been suppressed from get_pages() results, change child pages' corresponding post_parent values to a visible ancestor", 'press-permit-core'),
    'PPC_NO_PAGE_REMAP' => esc_html__("Never modify the post_parent value in the get_pages() result set, even if some pages have been suppressed", 'press-permit-core'),
    'PP_GET_PAGES_LEAN' => esc_html__("For performance, change the get_pages() database query to return only a subset of fields, excluding post_content", 'press-permit-core'),
];

if (defined('PRESSPERMIT_TEASER_VERSION')) {
    $consts = array_merge(
        $consts,
        [
        'PP_TEASER_HIDE_PAGE_LISTING' => esc_html__("Don't apply content teaser to get_pages() results (leave unreadable posts hidden)", 'press-permit-core'),
        ]
    );
}
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'get-terms';
$consts = [
    'PPC_FORCE_TERM_REMAP' => esc_html__("If some terms have been suppressed from get_terms() results, change child terms' corresponding parent values to a visible ancestor", 'press-permit-core'),
    'PPC_NO_TERM_REMAP' => esc_html__("Never modify the parent value in the get_terms() result set, even if some terms have been suppressed", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];


$type = 'post-author';
$consts = [
    'PRESSPERMIT_AUTOSET_AUTHOR' => esc_html__("Set Author to current user if autoset_post_author / autoset_page_author capability is assigned", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];


$type = 'media';
$consts = [
    'PP_MEDIA_LIB_UNFILTERED' => esc_html__("Leave Media Library with normal access criteria based on user's role capabilities ", 'press-permit-core'),
    'PRESSPERMIT_MEDIA_UPLOAD_GRANT_PAGE_EDIT_CAPS' => esc_html__("Accommodate front end uploading solutions that require page editing capabilities for the async upload request", 'press-permit-core'),
    'PRESSPERMIT_MEDIA_IGNORE_UNREGISTERED_PARENT_TYPES' => esc_html__('Treat media attached to unregistered post types as unattached, to avoid improper and confusing filtering', 'press-permit-core'),
];

if (defined('PRESSPERMIT_FILE_ACCESS_VERSION')) {
    $consts = array_merge(
        $consts, 
        [
        'PP_ATTACHED_FILE_AUTOPRIVACY' => esc_html__("Attached Files Private setting available", 'press-permit-core'),
        'PPFF_EXCLUDE_MIME_TYPES' => esc_html__("Comma-separated list of mime types to exclude from File Access filtering", 'press-permit-core'),
        'PPFF_INCLUDE_MIME_TYPES' => esc_html__("Comma-separated list of mime types to include in File Access filtering (bypassing others)", 'press-permit-core'),
        'PP_QUIET_FILE_404' => esc_html__("When file access is blocked, do not set the WP_Query 404 / 403 property", 'press-permit-core'),
        'PPFF_STATUS_CODE' => esc_html__("HTTP status code to send when file access is blocked", 'press-permit-core'),
        ]
    );
}
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'admin';
$consts = [
    'PP_USERS_UI_GROUP_FILTER_LINK' => esc_html__("On Users listing, Permission groups in custom column are list filter links instead of group edit links", 'press-permit-core'),
    'PP_ADMIN_READONLY_LISTABLE' => esc_html__("Unlock Permissions > Settings > Core > Admin Back End > 'Hide non-editable posts'", 'press-permit-core'),
    'PP_ADMIN_TERMS_READONLY_LISTABLE' => esc_html__("Unlock Permissions > Settings > Core > Admin Back End > 'Hide non-editable posts'", 'press-permit-core'),
    'PP_UPLOADS_FORCE_FILTERING' => esc_html__("Within the async-upload.php script, filter author's retrieval of the attachment they just uploaded", 'press-permit-core'),
    'PP_NO_COMMENT_FILTERING' => esc_html__("Don't filter comment display or moderation within wp-admin", 'press-permit-core'),
    'PP_ADMINS_IN_PERMISSION_GROUPS' => esc_html__("Allow Administrators to be added to Permission Groups, even though they are not restricted", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'permissions-admin';
$consts = [
    'PP_FORCE_EXCEPTION_OVERWRITE' => esc_html__("If propagating permissions are assigned to a page branch, overwrite any explicitly assigned permissions in sub-pages", 'press-permit-core'),
    'PP_EXCEPTIONS_MAX_INSERT_ROWS' => esc_html__("Max number of specific permissions to insert in a single database query (default 1000)", 'press-permit-core'),
    'PP_DISABLE_MENU_TWEAK' => esc_html__("Don't tweak the admin menu indexes to position Permissions menu under Users", 'press-permit-core'),
    'PP_FORCE_USERS_MENU' => esc_html__("Don't add a Permissions menu. Instead, add menu items to the Users and Settings menus.", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'permission-groups-ui';
$consts = [
    'PP_GROUPS_CAPTION' => esc_html__("Customize Permissions screen title for Groups", 'press-permit-core'),
    'GROUPS_CAPTION_RS' => esc_html__("Customize Permission Groups caption on user profile", 'press-permit-core'),
    'PRESSPERMIT_ADD_USER_SINGLE_GROUP_SELECT' => esc_html__("Only one group is selectable on Add User screen", 'press-permit-core'),
    'PRESSPERMIT_EDIT_USER_SINGLE_GROUP_SELECT' => esc_html__("Only one group is selectable on Edit User screen", 'press-permit-core'),
    'PP_GROUPS_HINT' => esc_html__("Display a custom hint on Permissions > User Groups", 'press-permit-core'),
    'PP_ITEM_MENU_PER_PAGE' => esc_html__("Max number of non-hierarchical posts / terms to display at one time (per page)", 'press-permit-core'),
    'PP_ITEM_MENU_HIERARCHICAL_PER_PAGE' => esc_html__("Max number of hierarchical posts / terms to display at one time (per page)", 'press-permit-core'),
    'PP_ITEM_MENU_FORCE_DISPLAY_DEPTH' => esc_html__("Disable auto-determination of how many levels of page tree to make visble by default. Instead, use specified value.", 'press-permit-core'),
    'PP_ITEM_MENU_DEFAULT_MAX_VISIBLE' => esc_html__("Target number of visible pages/terms, used for auto-determination of number of visible levels", 'press-permit-core'),
    'PP_ITEM_MENU_SEARCH_CONTENT' => esc_html__("Make search function on the post selection metabox look at post content", 'press-permit-core'),
    'PP_ITEM_MENU_SEARCH_EXCERPT' => esc_html__("Make search function on the post selection metabox look at post excerpt", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'user-selection';
$consts = [
    'PP_USER_LASTNAME_SEARCH' => esc_html__("Search by last name instead of display name", 'press-permit-core'),
    'PP_USER_SEARCH_FIELD' => esc_html__("User field to search by default", 'press-permit-core'),
    'PP_USER_SEARCH_META_FIELDS' => esc_html__("User meta fields selectable for search (comma-separated)", 'press-permit-core'),
    'PP_USER_SEARCH_NUMERIC_FIELDS' => esc_html__("User meta fields which should be treated as numeric (comma-separated)", 'press-permit-core'),
    'PP_USER_SEARCH_BOOLEAN_FIELDS' => esc_html__("User meta fields which should be treated as boolean (comma-separated)", 'press-permit-core'),
    'PP_USER_RESULTS_DISPLAY_NAME' => esc_html__("Use display name for search results instead of user_login", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];


$type = 'users';
$consts = [
    'PP_AUTODELETE_ROLE_METAGROUPS' => esc_html__("When synchronizing role metagroups to currently defined WP roles, don't delete groups for previously defined WP roles.", 'press-permit-core'),
    'PP_FORCE_DYNAMIC_ROLES' => esc_html__("Force detection of WP user roles which are appended dynamically but not stored to the WP database.", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];


$type = 'perf';
$consts = [
    'PP_NO_FRONTEND_ADMIN' => esc_html__("To save memory on front end access, don't register any filters related to content editing", 'press-permit-core'),
    'PP_NO_ATTACHMENT_COMMENTS' => esc_html__("Attached media do not have any comments, so don't append clauses to comment queries for them", 'press-permit-core'),
    'PP_LEAN_PAGE_LISTING' => esc_html__("Reduce overhead of pages query (in get_pages() and wp-admin) by defaulting fields to a set list that does not include post_content ", 'press-permit-core'),
    'PP_LEAN_POST_LISTING' => esc_html__("Reduce overhead of wp-admin posts query by defaulting fields to a set list that does not include post_content ", 'press-permit-core'),
    'PP_LEAN_MEDIA_LISTING' => esc_html__("Reduce overhead of wp-admin Media query by defaulting fields to a set list that does not include post_content ", 'press-permit-core'),
    'PP_LEAN_MY_CUSTOM_TYPE_LISTING' => esc_html__("Reduce overhead of wp-admin query for specified custom post type by defaulting fields to a set list that does not include post_content ", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'workarounds';
$consts = [
    'PRESSPERMIT_LEGACY_DB_SETUP' => esc_html__('Work around database setup warnings (at plugin activation) on some servers', 'press-permit-core')
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
/*
if (defined('PUBLISHPRESS_REVISIONS_VERSION') || defined("REVISIONARY_VERSION")) {
    $type = 'third-party';
    $consts = [
        'SCOPER_DEFAULT_MONITOR_GROUPS' => "",
        'PP_DEFAULT_MONITOR_GROUPS' => "",
    ];
    foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];
}
*/

$this->constants = apply_filters('presspermit_constants', $this->constants);

$arr = [];

$type = 'debug-dev-unsupported';
$consts = [
    'PRESSPERMIT_DEBUG' => "",
    'PRESSPERMIT_DEBUG_LOGFILE' => "",
    'PRESSPERMIT_MEMORY_LOG' => "",
    'AGP_NO_USAGE_MSG' => "",
    'PP_DISABLE_CAP_CACHE' => "",
    'PRESSPERMIT_DISABLE_TERM_PREASSIGN' => '',
    'PP_DISABLE_UNFILTERED_TYPES_CLAUSE' => esc_html__("Development use only (suppresses post_status = 'publish' clause for unfiltered post types with anonymous user)", 'press-permit-core'),
    'PP_RETAIN_PUBLISH_FILTER' => esc_html__("Development use only (on front end, do not replace 'post_status = 'publish'' clause with filtered equivalent)", 'press-permit-core'),
    'PP_DISABLE_BULK_ROLES' => "",
    'PUBLISHPRESS_ACTION_PRIORITY_INIT' => '',
    'PRESSPERMIT_NO_EARLY_CAPS_INIT' => '',
    'PRESSPERMIT_DISABLE_QUERYFILTERS' => '',
    'PP_ADMIN_POSTS_NO_FILTER' => '',
    'PRESSPERMIT_FORCE_POST_FILTERING' => '',
    'PRESSPERMIT_DISABLE_POST_COUNT_FILTER' => '',
    'PP_NO_PROPAGATING_EXCEPTION_DELETION' => '',
    'PP_EXCEPTIONS_MAX_INSERT_ROWS' => '',
    'PP_FORCE_EXCEPTION_OVERWRITE' => '',
    'PP_DISABLE_OPTIMIZED_EXCEPTIONS' => '',
    'PRESSPERMIT_SAVE_POST_ALLOW_BYPASS' => '',
    'PRESSPERMIT_AUTOSAVE_BYPASS_SAVE_FILTERS' => '',
    'PP_GET_PAGES_LIMIT_ADMIN_FILTERING' => '',
    'PRESSPERMIT_GET_PAGES_DISABLE_IN_CLAUSE' => '',
    'PRESSPERMIT_GET_PAGES_IGNORE_EXCLUDE_ARGS' => '',
    'PP_LEGACY_PAGE_URI_FILTER' => '',

    // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
    //'PRESSPERMIT_READ_PUBLIC_CAP' => '', 
    
    'PRESSPERMIT_STRICT_READ_CAP' => '',
    'PRESSPERMIT_SIMPLIFY_READ_PERMISSIONS' => '',
    'PRESSPERMIT_LEGACY_HOOKS' => '',
    'PRESSPERMIT_NO_LEGACY_API' => '',
    'PRESSPERMIT_FIND_POST_TYPE_NO_DEFAULT_TYPE' => '',
    'PRESSPERMIT_LIMIT_ASYNC_UPLOAD_FILTERING' => '',
    'PRESSPERMIT_FILTER_PRIVATE_TAXONOMIES' => '',
    'PUBLISHPRESS_PERMISSIONS_MENU_GROUPING' => '',
    'PP_DEFAULT_APPEARANCE_MENU' => '',
    'PRESSPERMIT_TILE' => '',
    'PRESSPERMIT_LEGACY_POST_TYPE_ENABLE_METABOX' => '',
    'PP_LEGACY_POST_TAG_CAPS' => '',
    'PRESSPERMIT_NO_PROCESS_BEFORE_REDIRECT' => '',
    'PRESSPERMIT_MENU_EDITOR_ADD_UNPUBLISHED' => '',
    'PP_NAV_MENU_ENABLE_POSTMETA_FILTER' => '',
    'PP_NAV_MENU_DISABLE_POSTMETA_FILTER' => '',
    'PRESSPERMIT_EDIT_NAV_MENUS_NO_PAGING' => '',
    'PP_PAGE_PARENT_NOPAGING' => '',
];
foreach ($consts as $k => $v) $arr[$k] = (object)['descript' => $v, 'type' => $type];


$type = 'deprecated';
$consts = [
    'PRESSPERMIT_TERM_FILTERS_LEGACY_LOAD' => '',
    'PP_LEGACY_POST_BLOCKAGE' => '',
    'PP_GET_PAGES_LEGACY_FILTER' => '',
    'PP_LEGACY_REST_FILTERING' => '',
    'PRESSPERMIT_LEGACY_ADMIN_TERM_COUNT_FILTER' => '',
    'PRESSPERMIT_LEGACY_SAVE_POST_TERM_ASSIGNMENT' => '',
    'PRESSPERMIT_LEGACY_PREASSIGN_TERMS' => '',
    'PRESSPERMIT_LEGACY_COMMENT_FILTERING' => '',
    'PRESSPERMIT_LEGACY_MAIN_SITE_CHECK' => '',
    'PRESSPERMIT_NAV_MENU_EDIT_DEBUG' => '',
    'PP_AGENTS_CAPTION_LIMIT' => '',
    'PP_AGENTS_EMSIZE_THRESHOLD' => '',
    'PP_UI_EMS_PER_CHARACTER' => '',
    'PP_FILTER_JSON_REST' => '',
    'PRESSPERMIT_NO_USER_LOCALE' => '',
    'PP_FORCE_PLUGIN_MENU' => '',
    'PP_FORCE_USERS_MENU' => '',
    'PRESSPERMIT_OWN_DESCENDENT_CHECK' => '',
    'PRESSPERMIT_FILTER_VALIDATE_PAGE_PARENT' => '',
    'PRESSPERMIT_NO_PROCESS_BEFORE_PARENT_REVERT' => '',
    'PRESSPERMIT_PAGE_LISTING_FLUSH_CACHE' => '',
];
foreach ($consts as $k => $v) $arr[$k] = (object)['descript' => $v, 'type' => $type];

$this->constants = $this->constants + $arr;

} // end function

function loadConstantTypes() {
    foreach ($this->constants as $name => $const) {
        if (empty($const->suppress_display)) {
            if (!isset($this->constant_types[$const->type])) {
                $this->constant_types[$const->type] = ucwords(str_replace('-', ' ', $const->type));

                foreach (['-' => ' ', 'Pp' => 'PP', 'Wp' => 'WP', 'Ui' => 'UI'] as $find => $repl)
                    $this->constant_types[$const->type] = ucwords(str_replace($find, $repl, $this->constant_types[$const->type]));
            }

            if (!isset($this->constants_by_type[$const->type])) $this->constants_by_type[$const->type] = [];

            $this->constants_by_type[$const->type][] = $name;
        }
    }

    $this->constant_types = apply_filters('presspermit_constant_types', $this->constant_types);
}

} // end class