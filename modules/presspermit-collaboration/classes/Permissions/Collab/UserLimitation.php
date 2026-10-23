<?php
namespace PublishPress\Permissions\Collab;

\defined('ABSPATH') || exit;

class UserLimitation
{
    public static function isLimitedEditor()
    {
        if (presspermit()->isContentAdministrator())
            return false;

        if ($type_obj = get_post_type_object(PWP::findPostType())) {
            if (!current_user_can($type_obj->cap->edit_posts))
                return true;

            $user = presspermit()->getUser();

            foreach (['edit_post', 'associate_post'] as $op) {
                foreach (['include', 'exclude'] as $mod) {
                    if (!empty($user->except[$op]['post'][''][$mod][$type_obj->name])) {
                        return true;
                    }
                }
            }

            if ($taxonomies = presspermit()->getEnabledTaxonomies(['object_type' => $type_obj->name])) {
                foreach ($taxonomies as $taxonomy) {
                    foreach (['include', 'exclude'] as $mod) {
                        if (!empty($user->except['assign_post']['term'][$taxonomy][$mod]['post'])) {
                            return true;
                        }
                    }
                }
            }

            return apply_filters('presspermit_hide_quickedit', false, $type_obj);
        }
    }
}
