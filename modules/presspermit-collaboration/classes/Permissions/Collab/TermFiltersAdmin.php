<?php
namespace PublishPress\Permissions\Collab;

\defined('ABSPATH') || exit;

class TermFiltersAdmin
{
    public static function fltGetTermsUniversalExceptions($universal, $required_operation, $taxonomy, $args)
    {
        // if a term is excluded (or outside an include set) for editing, don't allow assignment either
        $user = presspermit()->getUser();

         // this is for the purpose of exempting items from omission due to include/exclude exceptions
        $universal['additional'] = array_merge(
            $universal['additional'], 
            $user->getExceptionTerms('edit', 'additional', '', $taxonomy)
        );

        // if a term is excluded from editing, don't allow assignment either
        foreach (['include', 'exclude'] as $mod_type) {
            if ($edit_tt_ids = $user->getExceptionTerms('edit', $mod_type, '', $taxonomy)) {
                if ('include' == $mod_type) {
                    if ($universal[$mod_type]) {
                        if ($edit_assign_intersect = array_intersect($universal[$mod_type], $edit_tt_ids))
                            $universal[$mod_type] = $edit_assign_intersect;
                        else
                            $universal[$mod_type] = [-1];  // include exceptions are set for both edit and assign, and there is no overlap
                    } else
                        $universal[$mod_type] = $edit_tt_ids;
                } else {
                    $universal[$mod_type] = array_merge($universal[$mod_type], $edit_tt_ids);
                }
            }
        }

        return $universal;
    }

    public static function fltGetTermsExceptions($tt_ids, $required_operation, $mod_type, $post_type, $taxonomy, $args)
    {
        $user = presspermit()->getUser();

        if ('assign' == $required_operation) { // already checked
            // if a term is excluded from editing, don't allow assignment either
            if ($edit_tt_ids = $user->getExceptionTerms('edit', $mod_type, $post_type, $taxonomy)) {
                if ('include' == $mod_type) {
                    if ($tt_ids) {
                        if ($edit_assign_intersect = array_intersect($tt_ids, $edit_tt_ids))
                            $tt_ids = $edit_assign_intersect;
                        else
                            $tt_ids = [-1];  // include exceptions are set for both edit and assign, and there is no overlap.  Note: universal+type_specific include exceptions are additive, but edit*assign include exceptions are an intersection
                    } else
                        $tt_ids = $edit_tt_ids;
                } else {
                    $tt_ids = array_merge($tt_ids, $edit_tt_ids);
                }
            }
        }

        if (('manage' == $required_operation) && ('include' == $mod_type)) {
            if (!empty($args['additional_tt_ids'])) {
                if ($tx_obj = get_taxonomy($taxonomy)) {
                    // if a user lacking sitewide manage cap has additions, only those terms are manageable
                    if (empty($user->allcaps[$tx_obj->cap->manage_terms]))
                        $tt_ids = array_merge($tt_ids, $args['additional_tt_ids']);
                }
            }
        }

        return $tt_ids;
    }
}
