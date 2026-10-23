<?php
namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

class CollabHooksCompat
{
    private $wp_version;

    function __construct() {
        add_action('init', [$this, 'actStatusRegistrations'], 44);  // statuses need to be registered before establish_status_caps() execution

        add_action('presspermit_roles_defined', [$this, 'actAdjustDefaultPatternRoles']);

        add_filter('presspermit_operations', [$this, 'fltOperations']);
        add_action('presspermit_define_pattern_caps', [$this, 'actDefinePatternCaps']);
        add_filter('presspermit_apply_arbitrary_caps', [$this, 'fltApplyArbitraryCaps'], 10, 3);

        if (defined('POLYLANG_VERSION') && !defined('PRESSPERMIT_NO_POLYLANG_WORKAROUND')) {
            add_action('wp_loaded', function() {
                global $wp_version;

                // For now, we need Polylang to apply 'get_pages' filtering as in its previous versions
                if (version_compare($wp_version, '6.3-alpha', '>=')) {
                    $this->wp_version = $wp_version;

                    // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                    $wp_version = '6.2.2';
                }
            }, 4);

            add_action('wp_loaded', function() {
                global $wp_version;

                if (isset($this->wp_version)) {

                    // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                    $wp_version = $this->wp_version;
                }
            }, 6);
        }
    }

    function fltOperations($ops)
    {
        $ops[] = 'edit';

        if (presspermit()->getOption('publish_exceptions')) {
            $ops[] = 'publish';
        }

        if (defined('PUBLISHPRESS_REVISIONS_VERSION')) {
            $ops[] = 'copy';
        }

        if (defined('PUBLISHPRESS_REVISIONS_VERSION') || defined('REVISIONARY_VERSION')) {
            $ops[] = 'revise';
        }

        $ops = array_merge($ops, ['associate', 'assign', 'manage']);

        return $ops;
    }

    function actDefinePatternCaps($pattern_role_caps)
    {
        $type_obj = get_taxonomy('category');
        $type_caps['category'] = array_intersect_key(get_object_vars($type_obj->cap), array_fill_keys(['manage_terms'], true));

        $cap_caster = presspermit()->capCaster();

        foreach (array_keys($pattern_role_caps) as $role_name) {
            // log caps defined for the "category" taxonomy
            $cap_caster->pattern_role_taxonomy_caps[$role_name] = array_intersect_key($pattern_role_caps[$role_name], $type_caps['category']);
        }
    }

    function fltApplyArbitraryCaps($caps, $arr_name, $type_obj)
    {
        $base_role_name = $arr_name[0];

        $cap_caster = presspermit()->capCaster();

        // "Misc" caps are other caps in the pattern role which are not type-defined
        //
        // for now, don't apply arbitrary caps for typecast term management roles
        if (!empty($cap_caster->pattern_role_arbitrary_caps[$base_role_name]) && post_type_exists($type_obj->name)) {
            $arbitrary_caps = $cap_caster->pattern_role_arbitrary_caps[$base_role_name];

            // these caps will be added only for extra roles with no status specified
            if (!empty($arr_name[4])) {
                $arbitrary_caps = array_diff_key(
                    $arbitrary_caps, 
                    array_fill_keys(
                        apply_filters(
                            'presspermit_status_role_skip_caps', 
                            [
                                'list_users', 
                                'edit_users', 
                                'delete_users', 
                                'switch_themes', 
                                'edit_themes', 
                                'activate_plugins', 
                                'edit_plugins', 
                                'manage_options', 
                                'manage_links', 
                                'import'
                            ]
                        ), 
                        true
                    )
                );
            }

            $caps = array_merge($arbitrary_caps, $caps);
        }

        return $caps;
    }

    function actStatusRegistrations()
    {
        if (!defined('PRESSPERMIT_VERSION'))
            return;

        if (defined('PRESSPERMIT_STATUSES_VERSION') && !defined('PP_NO_MODERATION')) {
            // custom moderation stati
            register_post_status('approved', [
                'label' => _x('Approved', 'post'),
                'labels' => (object)['publish' => esc_html__('Approve', 'press-permit-core')],
                'moderation' => true,
                'protected' => true,
                'internal' => false,
                'label_count' => _n_noop('Approved <span class="count">(%s)</span>', 'Approved <span class="count">(%s)</span>'),
                'pp_builtin' => true,
            ]);
        }
    }

    function actAdjustDefaultPatternRoles()
    {
        if (defined('PUBLISHPRESS_REVISIONS_VERSION') || defined('REVISIONARY_VERSION')) {
            presspermit()->registerPatternRole(
                'revisor', 
                [
                    'labels' => (object)[
                        'name' => esc_html__('Revisors', 'press-permit-core'), 
                        'singular_name' => esc_html__('Revisor', 'press-permit-core')
                    ]
                ]
            );
        }
    }
}
