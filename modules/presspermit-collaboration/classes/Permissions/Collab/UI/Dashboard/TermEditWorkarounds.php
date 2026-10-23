<?php
namespace PublishPress\Permissions\Collab\UI\Dashboard;

\defined('ABSPATH') || exit;

class TermEditWorkarounds
{
    public static function term_edit_attempt()
    {
        // Nonce verification unnecessary because this needs to be checked regardless of how the term edit was triggered, and can only block the operation.

        // filter category parent selection for Category editing
        if (!$tag_id = PWP::POST_int('tag_ID')) {
            return;
        }

        if (!$taxonomy = PWP::POST_key('taxonomy')) {
            return;
        }

        if (!$tx = get_taxonomy($taxonomy)) {
            return;
        }

        if (!$tx->hierarchical) {
            return;
        }

        $stored_term = get_term_by('id', $tag_id, $taxonomy);

        $selected_parent = PWP::POST_int('parent');

        if (-1 == $selected_parent) {
            $selected_parent = 0;
        }

        if ($stored_term->parent != $selected_parent) {
            if ($tx_obj = get_taxonomy($taxonomy)) {
                $user = presspermit()->getUser();

                if (!$included_ttids = apply_filters(
                    'presspermit_get_terms_exceptions', 
                    $user->getExceptionTerms('associate', 'include', $taxonomy, $taxonomy, ['merge_universals' => true]), 
                    'associate', 
                    'include', 
                    $taxonomy, 
                    $additional_tt_ids
                )) {
                    $excluded_ttids = apply_filters(
                        'presspermit_get_terms_exceptions', 
                        $user->getExceptionTerms('associate', 'include', $taxonomy, $taxonomy, ['merge_universals' => true]), 
                        'associate', 
                        'exclude', 
                        $taxonomy, 
                        $additional_tt_ids
                    );
                }

                if ($selected_parent) {
                    $additional_tt_ids = $user->getExceptionTerms('associate', 'additional', $taxonomy, $taxonomy);
                    $parent_ttid = PWP::termidToTtid($selected_parent, $taxonomy);

                    $permit = true;
                    if ($included_ttids) {
                        if ($additional_tt_ids)
                            $included_ttids = array_merge($included_ttids, $additional_tt_ids);

                        if (!in_array($parent_ttid, $included_ttids))
                            $permit = false;
                    } else {
                        if ($additional_tt_ids)
                            $excluded_ttids = array_diff($excluded_ttids, $additional_tt_ids);

                        if (in_array($parent_ttid, $excluded_ttids))
                            $permit = false;
                    }
                } else {
                    if ($included_ttids && !in_array(0, $included_ttids))
                        $permit = false;
                    elseif ($excluded_ttids && in_array(0, $excluded_ttids))
                        $permit = false;
                    else
                        $permit = !empty($user->allcaps[$tx_obj->cap->manage_terms]);
                }
            }

            if (empty($permit)) {
                wp_die(esc_html__('You do not have permission to select that Parent', 'press-permit-core'));
            }
        }
    }
}
