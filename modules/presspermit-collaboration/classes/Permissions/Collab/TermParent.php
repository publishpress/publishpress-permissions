<?php
namespace PublishPress\Permissions\Collab;

\defined('ABSPATH') || exit;

class TermParent
{
    public static function canSelect($taxonomy, $parent, $term_id = 0)
    {
        $pp = presspermit();
        $tx = get_taxonomy($taxonomy);
        if (!$tx || !$tx->hierarchical || !$pp->isTaxonomyEnabled($taxonomy) || $pp->isUserUnfiltered()) {
            return true;
        }

        $parent = max(0, (int) $parent);
        if ($term_id) {
            $stored = get_term($term_id, $taxonomy);
            if ($stored && !is_wp_error($stored) && (int) $stored->parent === $parent) {
                return true;
            }
        }

        $user = $pp->getUser();
        $additional = $user->getExceptionTerms('associate', 'additional', $taxonomy, $taxonomy);
        $included = apply_filters('presspermit_get_terms_exceptions',
            $user->getExceptionTerms('associate', 'include', $taxonomy, $taxonomy, ['merge_universals' => true]),
            'associate', 'include', $taxonomy, $taxonomy, ['additional_tt_ids' => $additional]);
        $excluded = apply_filters('presspermit_get_terms_exceptions',
            $user->getExceptionTerms('associate', 'exclude', $taxonomy, $taxonomy, ['merge_universals' => true]),
            'associate', 'exclude', $taxonomy, $taxonomy, ['additional_tt_ids' => $additional]);

        if ($parent) {
            $term = get_term($parent, $taxonomy);
            if (!$term || is_wp_error($term)) {
                return false;
            }
            $ttid = (int) $term->term_taxonomy_id;
        } else {
            $ttid = 0;
        }

        $included = array_map('intval', (array) $included);
        $excluded = array_map('intval', (array) $excluded);
        $additional = array_map('intval', (array) $additional);
        if ($included) {
            if (!in_array($ttid, array_merge($included, $additional), true)) {
                return false;
            }
        } elseif (in_array($ttid, array_diff($excluded, $additional), true)) {
            return false;
        }

        // Scoped term-management grants must not become permission to create at root
        // or below an unrelated term through a broad taxonomy primitive.
        if (empty($user->allcaps[$tx->cap->manage_terms])) {
            if (!$parent) {
                return false;
            }
            $manageable = get_terms(['taxonomy' => $taxonomy, 'required_operation' => 'manage', 'hide_empty' => false, 'fields' => 'ids']);
            if (is_wp_error($manageable) || !in_array($parent, array_map('intval', $manageable), true)) {
                return false;
            }
        }
        return true;
    }

    public static function denied()
    {
        return new \WP_Error('presspermit_parent_forbidden', esc_html__('You do not have permission to select that Parent', 'press-permit-core'), ['status' => 403]);
    }
}
