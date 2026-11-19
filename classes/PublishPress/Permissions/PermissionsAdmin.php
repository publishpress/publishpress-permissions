<?php

namespace PublishPress\Permissions;

\defined('ABSPATH') || exit;

class PermissionsAdmin
{
    public static function canAdminAgent($type, $id)
    {
        return presspermit()->admin()->canEditPermissionAgent($type, $id);
    }

    public static function canAssignRole($role, $type = '')
    {
        $parts = explode(':', $role);
        $source = $parts[1] ?? 'post';
        $object_type = $parts[2] ?? $type;
        if (!in_array($source, ['post', 'term'], true)) {
            return false;
        }
        require_once(PRESSPERMIT_CLASSPATH . '/RoleAdmin.php');
        $base_role = implode(':', array_slice($parts, 0, min(3, count($parts))));
        $allowed = RoleAdmin::getTypeRoles($source, $object_type ?: '-1');
        if (!isset($allowed[$base_role])) {
            return false;
        }
        if (count($parts) > 3 && (count($parts) !== 5 || 'post_status' !== $parts[3]
            || !get_post_status_object($parts[4]))) {
            return false;
        }
        if (false === strpos($role, ':') && !current_user_can('promote_users')) {
            return false;
        }
        if (!self::userCanAdminRole($parts[0], $object_type ?: '-1')) {
            return false;
        }
        // Check the actual typecast capabilities, including direct site-role caps.
        $caps = array_filter(presspermit()->getRoleCaps($role));
        foreach (array_keys($caps) as $cap) {
            if (!current_user_can($cap)) {
                return false;
            }
        }
        return true;
    }

    public static function canAssignException($args)
    {
        $args = (array) $args;
        $operation = $args['operation'] ?? '';
        $source = $args['via_item_source'] ?? '';
        $for_source = $args['for_item_source'] ?? '';
        $type = $args['for_item_type'] ?? '';
        $item = (int) ($args['item_id'] ?? 0);
        if (!in_array($source, ['post', 'term', 'pp_group', 'pp_net_group'], true)
            || !in_array($for_source, ['post', 'term', 'pp_group', 'pp_net_group'], true)
            || !in_array($args['mod_type'] ?? '', ['include', 'exclude', 'additional'], true)
            || !presspermit()->admin()->getOperationObject($operation, $type)
            || !presspermit()->admin()->canSetExceptions($operation, $type, $args)
        ) {
            return false;
        }
        if ($type && !(('post' === $for_source && post_type_exists($type))
            || ('term' === $for_source && taxonomy_exists($type))
            || (in_array($for_source, ['pp_group', 'pp_net_group'], true) && $type === $for_source))) {
            return false;
        }
        if (!empty($args['assign_for']) && !in_array($args['assign_for'], ['item', 'children'], true)) {
            return false;
        }
        $status = $args['for_item_status'] ?? '';
        if ($status) {
            foreach (explode(',', $status) as $condition) {
                if (0 !== strpos($condition, 'post_status:') || !get_post_status_object(substr($condition, 12))) {
                    return false;
                }
            }
        }
        if (in_array($source, ['pp_group', 'pp_net_group'], true)) {
            if ($for_source !== $source || $type !== $source) {
                return false;
            }
            return $item && self::canAdminAgent($source, $item);
        }
        if ('post' === $source) {
            if ($item) {
                $post = get_post($item);
                return $post && (!$type || 'post' !== $for_source || $post->post_type === $type)
                    && current_user_can('edit_post', $item);
            }
            return presspermit()->isUserAdministrator();
        }
        $taxonomy = $args['via_item_type'] ?? '';
        if (!taxonomy_exists($taxonomy)) {
            return false;
        }
        if (!$item) {
            return presspermit()->isUserAdministrator();
        }
        $term = get_term_by('term_taxonomy_id', $item, $taxonomy);
        return $term && !is_wp_error($term) && current_user_can('edit_term', $term->term_id);
    }


    // Regulates the ability to set exceptions for a specific post or term.  Note: exceptions bulk editor (Edit Permissions screen) requires edit_users capability.
    // * PP options determine whether non-Admins are checked for pp_set_*_exceptions cap or simply blocked
    // * pp_set_*_exceptions cap must be present in user's WP role.  It is *not* granted through Pattern Role assignment.
    // * If a non-Admin user has cap, they are still required to have edit_published and edit_others (if applicable) for the post type
    public static function canSetExceptions($operation, $for_item_type, $args)
    {
        $defaults = ['item_id' => 0, 'via_item_source' => 'post', 'via_item_type' => '', 'for_item_source' => 'post'];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $pp = presspermit();

        if (!$is_administrator = $pp->isUserAdministrator()) {
            $enabled = ('read' == $operation)
                ? $pp->getOption('non_admins_set_read_exceptions')
                : $pp->getOption('non_admins_set_edit_exceptions');

            if (!$enabled) {
                return false;
            }
        }

        if (in_array($via_item_source, ['post', 'term'], true) && ('read' == $operation)) {
            $can = $is_administrator || current_user_can('pp_set_view_permissions');
        } else {
            $can = false;
        }

        // also filter for Administrators to account for non-applicable operations
        return apply_filters('presspermit_can_set_exceptions', $can, $operation, $for_item_type, array_merge($args, compact('is_administrator')));
    }

    public static function userCanAdminRole($role_name, $item_type, $item_id = 0)
    {
        if (presspermit()->isUserAdministrator()) {
            return true;
        }

        $can_do = false;

        if ($type_obj = get_post_type_object($item_type)) {
            if (!presspermit()->admin()->canSetAnyPostPermissions($item_type)) {
            return false;
        }

            if (!empty($type_obj->cap->edit_published_posts)) {
                $can_do = current_user_can($type_obj->cap->edit_published_posts);
            }
        } elseif ($tx_obj = get_taxonomy($item_type)) {
            if (!presspermit()->admin()->canSetAnyTermPermissions('', $item_type)) {
                return false;
            }

            if (!empty($tx_obj->cap->manage_categories)) {
                $can_do = current_user_can($tx_obj->cap->manage_categories);
            }
        }

        return apply_filters('presspermit_user_can_admin_role', $can_do, $role_name, $item_type, $item_id);
    }

    public static function getRoleTitle($role_name, $args = [])
    {
        global $wp_roles;

        $defaults = ['plural' => false, 'show_disabled' => true, 'slug_fallback' => true, 'include_warnings' => false, 'echo' => false, 'status_suffix' => true];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $pp = presspermit();
        $cap_caster = $pp->capCaster();

        if (strpos($role_name, ':')) {
            $arr_name = explode(':', $role_name);
            if (!empty($arr_name[2])) {
                $caption_prop = ($plural) ? 'name' : 'singular_name';
                $warning = '';

                if (isset($pp->role_defs->pattern_roles[$arr_name[0]])) {
                    $role_caption = (isset($pp->role_defs->pattern_roles[$arr_name[0]]->labels->$caption_prop)) 
                    ? $pp->role_defs->pattern_roles[$arr_name[0]]->labels->$caption_prop 
                    : '';

                    if (
                        $include_warnings && isset($wp_roles->role_names[$arr_name[0]])
                        && !$cap_caster->isValidPatternRole($arr_name[0])
                    ) {
                        $warning = '<span class="pp-red"> ' . sprintf(
                                esc_html__('(using default capabilities due to invalid %s definition)', 'press-permit-core'),
                                esc_html($wp_roles->role_names[$arr_name[0]])
                            ) . '</span>';
                    }
                } elseif ($slug_fallback) {
                    $role_caption = $arr_name[0];
                } else {
                    return '';
                }

                $type_caption = '';
                if ($type_obj = $pp->getTypeObject($arr_name[1], $arr_name[2])) {
                    $type_caption = $type_obj->labels->singular_name;
                } else {
                    $role_name = ($slug_fallback) ? $role_name : '';
                    echo ($echo) ? esc_html($role_name) : '';
                    return $role_name;
                }

                $cond_caption = '';

                if (!empty($args['status_suffix'])) {
                    if (isset($arr_name[4])) {
                        if (!$show_disabled && ('post_status' == $arr_name[3])) {
                            $status_obj = get_post_status_object($arr_name[4]);

                            // Post status is undefined or disabled
                            if (!$status_obj) {
                                return false;
                            }

                            // Post status is not enabled for this post type
                            if (!empty($type_obj) && !empty($status_obj->post_type) && !in_array($type_obj->name, (array) $status_obj->post_type)) {
                                return false;
                            }
                        }

                        $cond_caption = apply_filters(
                            'presspermit_condition_caption',
                            ucwords(str_replace('_', ' ', $arr_name[4])),
                            $arr_name[3],
                            $arr_name[4]
                        );
                    }
                }

                if ($cond_caption) {
                    if (!empty($args['echo'])) {
                        printf(
                            esc_html__('%1$s&nbsp;%2$s&nbsp;%3$s-&nbsp;%4$s%5$s%6$s', 'press-permit-core'),
                            esc_html($type_caption),
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            str_replace(' ', '&nbsp;', esc_html($role_caption)),
                            '<span class="pp_nolink">',
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            str_replace(' ', '&nbsp;', esc_html($cond_caption)),
                            '</span>',
                            ''
                        );

                        if (!empty($warning)) {
                            echo '<span class="pp-red"> ';
                            printf(
                                esc_html__('(using default capabilities due to invalid %s definition)', 'press-permit-core'),
                                esc_html($wp_roles->role_names[$arr_name[0]])
                            );
                            echo '</span>';
                        }

                        $echo_done = true;
                    } else {
                        $role_name = trim(
                            sprintf(
                                esc_html__('%1$s&nbsp;%2$s&nbsp;%3$s-&nbsp;%4$s%5$s%6$s', 'press-permit-core'),
                                esc_html($type_caption),
                                str_replace(' ', '&nbsp;', esc_html($role_caption)),
                                '<span class="pp_nolink">',
                                str_replace(' ', '&nbsp;', esc_html($cond_caption)),
                                '</span>',
                                $warning // previously escaped in this function
                            )
                        );
                    }
                } else {
                    if (!empty($args['echo'])) {
                        printf(
                            esc_html__('%1$s&nbsp;%2$s%3$s', 'press-permit-core'),
                            esc_html($type_caption),
                            esc_html($role_caption),
                            ''
                        );

                        if (!empty($warning)) {
                            echo '<span class="pp-red"> ';
                            printf(
                                esc_html__('(using default capabilities due to invalid %s definition)', 'press-permit-core'),
                                esc_html($wp_roles->role_names[$arr_name[0]])
                            ); 
                            echo '</span>';
                        }

                        $echo_done = true;
                    } else {
                        $role_name = trim(
                            sprintf(
                                esc_html__('%1$s&nbsp;%2$s%3$s', 'press-permit-core'),
                                esc_html($type_caption),
                                esc_html($role_caption),
                                " $warning" // previously escaped in this function
                            )
                        );
                    }
                }
            }
        } elseif (isset($wp_roles->role_names[$role_name])) {
            $role_name = $wp_roles->role_names[$role_name];
        } else {
            $role_name = apply_filters('presspermit_role_title', $role_name, $args);
        }

        echo ($echo && empty($echo_done)) ? esc_html($role_name) : '';
        return $role_name;
    }
}
