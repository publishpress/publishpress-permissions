<?php

namespace PublishPress\Permissions\UI\Dashboard;

class TermsListing
{
    private $exceptions = [];

    public function __construct()
    {
        if (presspermit()->filteringEnabled()) {
            add_action('admin_print_footer_scripts', [$this, 'actScriptHideMainOption']);
            add_action('admin_print_footer_scripts', [$this, 'actScriptResize']);
            add_action('admin_print_footer_scripts', [$this, 'actScriptUniversalExceptions']);
        }

        if (PWP::empty_REQUEST('tag_ID')) {
            if ($taxonomy = PWP::REQUEST_key('taxonomy')) {
                add_action('after-' . $taxonomy . '-table', [$this, 'actShowNotes']);
            }
        }
    }

    public function actShowNotes()
    {
        global $typenow;

        if (PWP::empty_REQUEST('pp_universal')) {
            $taxonomy = PWP::REQUEST_key('taxonomy');
            $tx_obj = get_taxonomy($taxonomy);
            $type_obj = get_post_type_object($typenow);
            $url = "edit-tags.php?taxonomy=$taxonomy&pp_universal=1";
?>
            <div class="form-wrap">
                <p>
                    <?php
                    printf(
                        esc_html__('Listed permissions are those assigned for the "%1$s" type. You can also %2$sdefine universal %3$s permissions which apply to all related post types%4$s.', 'press-permit-core'),
                        esc_html($type_obj->labels->singular_name),
                        "<a href='" . esc_url($url) . "'>",
                        esc_html($tx_obj->labels->singular_name),
                        '</a>'
                    );
                    ?>
                </p>
            </div>
        <?php
        }
    }

    public function fltDefineColumns($columns)
    {
        global $typenow;

        if (PWP::empty_REQUEST('pp_universal')) {
            $taxonomy = PWP::REQUEST_key('taxonomy');
            $type_obj = get_post_type_object($typenow);
            $title = esc_html__('Click to list/edit universal permissions', 'press-permit-core');
            $lbl = ($type_obj && $type_obj->labels) ? $type_obj->labels->singular_name : '';
            $caption = sprintf(esc_html__('%1$s Permissions %2$s*%3$s', 'press-permit-core'), $lbl, "<a href='edit-tags.php?taxonomy=$taxonomy&pp_universal=1' title='$title'>", '</a>');
        } else {
            $caption = esc_html__('Universal Permissions', 'press-permit-core');
        }

        return array_merge($columns, ['pp_exceptions' => $caption]);
    }

    public function fltCustomColumn($val, $column_name, $id)
    {
        global $taxonomy;

        if ('pp_ttid' == $column_name) {
            $ttid = PWP::termidToTtid($id, $taxonomy);
            echo esc_attr("$id ($ttid)");
        }

        if ('pp_exceptions' != $column_name) {
            return;
        }

        static $got_data;
        if (empty($got_data)) {
            $this->logTermData();
            $got_data = true;
        }

        $id = PWP::termidToTtid($id, $taxonomy);

        if (!empty($this->exceptions[$id])) {
            global $typenow;

            $pp_admin = presspermit()->admin();

            $op_names = [];

            foreach ($this->exceptions[$id] as $op) {
                if ($op_obj = $pp_admin->getOperationObject($op, $typenow))
                    $op_names[] = $op_obj->label;
            }

            uasort($op_names, 'strnatcasecmp');
            echo esc_html(implode(", ", $op_names));
        }
    }

    public function actScriptResize()
    {
        ?>
        <script type="text/javascript">
            /* <![CDATA[ */
            jQuery(document).ready(function($) {
                $('#col-left').css('width', '25%');
                $('#col-right').css('width', '75%');
                $('.column-slug').css('width', '15%');
                $('.column-posts').css('width', '10%');
            });
            /* ]]> */
        </script>
    <?php
    }

    public function actScriptUniversalExceptions()
    {
        global $post_type;

        if (PWP::empty_REQUEST('pp_universal')) {
            return;
        }
    ?>
        <script type="text/javascript">
            /* <![CDATA[ */
            function updateQueryStringParameterPP(uri, key, value) {
                <?php /* https://stackoverflow.com/a/6021027 */ ?>
                var re = new RegExp("([?|&])" + key + "=.*?(&|$)", "i");
                separator = uri.indexOf('?') !== -1 ? "&" : "?";
                if (uri.match(re)) {
                    return uri.replace(re, '$1' + key + "=" + value + '$2');
                } else {
                    return uri + separator + key + "=" + value;
                }
            }

            jQuery(document).ready(function($) {
                $('#the-list tr').each(function(i, e) {
                    $(e).find("a.row-title,span.edit a").each(function(ii, ee) {
                        var u = $(ee).attr('href').replace('&post_type=<?php echo esc_attr($post_type); ?>', '');
                        $(ee).attr('href', u + '&pp_universal=1');
                    });
                });
            });
            /* ]]> */
        </script>
    <?php
    }

    // In "Add New Term" form, hide the "Main" option from Parent dropdown if the logged user doesn't have manage_terms cap site-wide
    public function actScriptHideMainOption()
    {
        if (PWP::is_REQUEST('action', 'edit')) {
            return;
        }

        if ($taxonomy = PWP::REQUEST_key('taxonomy')) {  // using this with edit-link-categories
            if ($tx_obj = get_taxonomy($taxonomy)) {
                $cap_name = $tx_obj->cap->manage_terms;
            }
        }

        if (empty($cap_name)) {
            $cap_name = 'manage_categories';
        }

        if (!empty(presspermit()->getUser()->allcaps[$cap_name])) {
            $taxonomy = PWP::REQUEST_key('taxonomy');

            if (!presspermit()->getUser()->getExceptionTerms('manage', 'include', sanitize_key($taxonomy), sanitize_key($taxonomy), ['merge_universals' => true])) {
                return;
            }
        }
    ?>
        <script type="text/javascript">
            /* <![CDATA[ */
            jQuery(document).ready(function($) {
                $('#parent option[value="-1"]').remove();
            });
            /* ]]> */
        </script>
<?php
    }

    private function logTermData()
    {
        global $wp_object_cache, $wpdb, $typenow;

        $taxonomy = PWP::REQUEST_key('taxonomy');

        if (!empty($wp_object_cache) && (isset($wp_object_cache->cache[$taxonomy]) || isset($wp_object_cache->cache['terms']))) {
            $cache = (isset($wp_object_cache->cache[$taxonomy])) ? $wp_object_cache->cache[$taxonomy] : $wp_object_cache->cache['terms'];
        }

        if (!empty($cache)) {
            if (isset($cache)) { // Note: array is keyed "blog_id:term_id" on Multisite installs
                $listed_term_ids = [];
                foreach ($cache as $k => $term) {
                    if (!is_object($term)) {
                        continue;
                    }

                    if (!is_numeric($k)) {
                        $arr = explode(':', $k);
                        if (!$arr || (count($arr) != 2) || !is_numeric(array_pop($arr))) {
                            continue;
                        }
                    }

                    $listed_tt_ids[] = $term->term_taxonomy_id;
                }
            }

            if (PWP::empty_REQUEST('paged')) {
                $listed_tt_ids[] = 0;
            }
        } else {
            return;
        }

        $for_type = $typenow;

        if (!PWP::empty_REQUEST('pp_universal')) {
            $for_type = '';
        } elseif (empty($typenow)) {
            $for_type = PWP::REQUEST_key('post_type');
        }

        $this->exceptions = [];

        if (!empty($listed_tt_ids)) {
            $agent_type_csv = implode("','", array_map('sanitize_key', array_merge(['user'], presspermit()->groups()->getGroupTypes())));
            $id_csv = implode("','", array_map('intval', $listed_tt_ids));
            $post_type = (!PWP::empty_REQUEST('pp_universal')) ? '' : $for_type;

            $for_types = ($typenow) ? [$post_type] : ['', $taxonomy];
            $for_type_csv = implode("','", array_map('sanitize_key', $for_types));

            // One locally cached direct query of plugin tables to retrieve exceptions info for all listed Terms
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT i.item_id, e.operation FROM $wpdb->ppc_exceptions AS e"
                        . " INNER JOIN $wpdb->ppc_exception_items AS i ON e.exception_id = i.exception_id"
                        . " WHERE e.for_item_type IN ('$for_type_csv') AND e.via_item_source = 'term'"                      // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        . " AND e.via_item_type = %s AND e.agent_type IN ('$agent_type_csv') AND i.item_id IN ('$id_csv')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                    $taxonomy
                )
            );

            foreach ($results as $row) {
                $this->exceptions[$row->item_id][] = $row->operation;
            }
        }
    }
}
