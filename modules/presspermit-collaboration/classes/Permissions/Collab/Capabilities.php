<?php
namespace PublishPress\Permissions\Collab;

\defined('ABSPATH') || exit;

class Capabilities
{
    var $all_taxonomy_caps = [];  // $all_taxonomy_caps = array of cap names
    private $processed_taxonomies = [];
    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new Capabilities();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->forceDistinctTaxonomyCaps();

        add_filter('presspermit_administrator_caps', [$this, 'fltAdministratorCaps']);

        add_filter('presspermit_exclude_arbitrary_caps', [$this, 'fltExcludeArbitraryCaps']);

        add_action('presspermit_refresh_capabilities', [$this, 'forceDistinctTaxonomyCaps']);
    }

    function fltAdministratorCaps($caps)
    {
        return array_merge($caps, array_fill_keys(array_keys($this->all_taxonomy_caps), true));
    }

    function fltExcludeArbitraryCaps($exclude_caps)
    {
        return array_merge($exclude_caps, $this->all_taxonomy_caps);
    }

    function forceDistinctTaxonomyCaps()
    {
        global $wp_taxonomies, $wp_roles;

        // Work around bug in More Taxonomies (and possibly other plugins) where category taxonomy is overriden without setting it public
        foreach (['category', 'post_tag'] as $taxonomy) {
            if (isset($wp_taxonomies[$taxonomy]))
                $wp_taxonomies[$taxonomy]->public = true;
        }

        $use_taxonomies = array_diff($this->getAssistedTaxonomies(), $this->processed_taxonomies);

        $tx_specific_caps = [
            'manage_terms' => 'manage_terms',
            'edit_terms' => 'manage_terms',
            'delete_terms' => 'manage_terms'
        ];

        if ($detailed_taxonomies = $this->getDetailedTaxonomies()) {
            $tx_detail_caps = [
                'edit_terms' => 'edit_terms',
                'delete_terms' => 'delete_terms',
                'assign_terms' => 'assign_terms'
            ];
        }

        $core_tx_caps = [];
        $this->all_taxonomy_caps = [];

        // currently, disallow category and post_tag cap use by selected custom taxonomies, but don't require category and post_tag to have different caps
        $core_taxonomies = ['category'];
        foreach ($core_taxonomies as $taxonomy) {
            foreach (array_keys($tx_specific_caps) as $cap_property) {
                $core_tx_caps[$wp_taxonomies[$taxonomy]->cap->$cap_property] = true;
            }
        }

        // count the number of taxonomies that use each capability
        foreach ($wp_taxonomies as $taxonomy => $tx_obj) {
            $this_tx_caps = array_unique((array)$tx_obj->cap);

            foreach ($this_tx_caps as $cap_name) {
                if (!isset($this->all_taxonomy_caps[$cap_name])) {
                    $this->all_taxonomy_caps[$cap_name] = 1;
                } else {
                    $this->all_taxonomy_caps[$cap_name]++;
                }
            }
        }

        foreach (array_keys($wp_taxonomies) as $taxonomy) {
            // clean up a GD Taxonomies quirk (otherwise wp_get_taxonomy_object will fail when filtering for public => true)
            if ('yes' == $wp_taxonomies[$taxonomy]->public) {
                $wp_taxonomies[$taxonomy]->public = true;

            // clean up a More Taxonomies quirk (otherwise wp_get_taxonomy_object will fail when filtering for public => true)
            } elseif (('' === $wp_taxonomies[$taxonomy]->public) && (!empty($wp_taxonomies[$taxonomy]->query_var_bool))) {
                $wp_taxonomies[$taxonomy]->public = true;
            }

            $tx_caps = (array)$wp_taxonomies[$taxonomy]->cap;

            if ((!in_array($taxonomy, $use_taxonomies, true) || empty($wp_taxonomies[$taxonomy]->public))
                && ('nav_menu' != $taxonomy)) {
                continue;
            }

            if (!in_array($taxonomy, $core_taxonomies, true)) {
                if (!class_exists('PublishPress\Permissions\Capabilities')) {
                    presspermit()->capDefs();
                }

                $plural_type = \PublishPress\Permissions\Capabilities::getPlural($taxonomy, $wp_taxonomies[$taxonomy]);

                if ("{$taxonomy}s" != $plural_type) {
                    // ... unless any role already has capabilities based on simple plural form
                    foreach ($wp_roles as $role) {
                        foreach (array_keys($tx_caps) as $cap_property) {
                            $simple_plural = str_replace("_terms", "_{$taxonomy}s", $cap_property);

                            if (isset($role->capabilities[$simple_plural])) {
                                // A simple plural capability was manually stored to a role, so stick with that form
                                $plural_type = "{$taxonomy}s";
                                break 2;
                            }
                        }
                    }
                }

                // First, force taxonomy-specific capabilities.
                // (Don't allow any capability defined for this taxonomy to match any capability defined for category or post tag 
                // (unless this IS category or post tag)
                foreach ($tx_specific_caps as $cap_property => $replacement_cap_format) {
                    // If this capability is also defined as another taxonomy cap, replace it
                    // note: greater than check is on array value, not count
                    if (!empty($tx_caps[$cap_property]) && ($this->all_taxonomy_caps[$tx_caps[$cap_property]] > 1)) {

                        // ... but leave it alone if it is a standard taxonomy-specific cap for this taxonomy
                        if (($tx_caps[$cap_property] != str_replace('_terms', "_{$plural_type}", $cap_property))
                            && ($tx_caps[$cap_property] != str_replace('_terms', "_{$taxonomy}s", $cap_property))) {

                            $wp_taxonomies[$taxonomy]->cap->$cap_property = str_replace('_terms', "_{$plural_type}", $replacement_cap_format);
                        }
                    }
                }
                $tx_caps = (array)$wp_taxonomies[$taxonomy]->cap;

                // Optionally, also force edit_terms and delete_terms to be distinct from manage_terms, and force a distinct assign_terms capability
                if (in_array($taxonomy, $detailed_taxonomies, true)) {
                    foreach ($tx_detail_caps as $cap_property => $replacement_cap_format) {
                        if (!empty($this->all_taxonomy_caps[$tx_caps[$cap_property]])) {
                            // assign_terms is otherwise not forced taxonomy-distinct
                            $wp_taxonomies[$taxonomy]->cap->$cap_property = str_replace('_terms', "_{$plural_type}", $replacement_cap_format);
                            break;
                        }

                        foreach ($tx_caps as $other_cap_property => $other_cap) {
                            if ($other_cap_property == $cap_property) {
                                continue;
                            }

                            if ($other_cap == $tx_caps[$cap_property]) {
                                $wp_taxonomies[$taxonomy]->cap->$cap_property = str_replace('_terms', "_{$plural_type}", $replacement_cap_format);
                                break;
                            }
                        }
                    }
                }

                $tx_caps = (array)$wp_taxonomies[$taxonomy]->cap;
            }

            foreach (array_unique($tx_caps) as $cap_name) {
                if (!isset($this->all_taxonomy_caps[$cap_name])) {
                    $this->all_taxonomy_caps[$cap_name] = 1;
                } else {
                    $this->all_taxonomy_caps[$cap_name]++;
                }
            }
        }

        $this->all_taxonomy_caps = array_merge($this->all_taxonomy_caps, ['assign_term' => true]);

        if (current_user_can('administrator') || current_user_can('pp_administer_content')) {  // @ todo: support restricted administrator
            global $current_user;
            $current_user->allcaps = array_merge(
                $current_user->allcaps, 
                array_fill_keys(array_keys($this->all_taxonomy_caps), true)
            );

            $user = presspermit()->getUser();
            if (!empty($user)) {
                $user->allcaps = array_merge(
                    $user->allcaps, 
                    array_fill_keys(array_keys($this->all_taxonomy_caps), true)
                );
            }
        }

        // make sure Nav Menu Managers can also add menu items
        $wp_taxonomies['nav_menu']->cap->assign_terms = (!empty($wp_taxonomies['nav_menu']->cap->manage_terms)) ? $wp_taxonomies['nav_menu']->cap->manage_terms : 'manage_nav_menus';

        $this->processed_taxonomies = array_merge($this->processed_taxonomies, $use_taxonomies);
    }

    function getAssistedTaxonomies()
    {     // apply CME filter only if CME is active
        $tx_args = ['public' => true];

        return (defined('CAPSMAN_VERSION'))
            ? apply_filters('cme_assisted_taxonomies', presspermit()->getEnabledTaxonomies($tx_args), $tx_args)
            : presspermit()->getEnabledTaxonomies($tx_args);
    }

    function getDetailedTaxonomies()
    {
        if (!defined('CAPSMAN_VERSION')) { // currently relying on CME settings UI
            return [];
        }

        $taxonomies = array_intersect(
            array_keys(array_filter((array)get_option('cme_detailed_taxonomies', []))),
            $this->getAssistedTaxonomies()
        );

        return apply_filters('cme_detailed_taxonomies', $taxonomies, ['public' => true]);
    }
}
