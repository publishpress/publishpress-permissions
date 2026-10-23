<?php
namespace PublishPress\Permissions\Collab\UI\Dashboard;

\defined('ABSPATH') || exit;

class PostEditCustomize
{
    public static function hide_admin_divs($hide_ids, $object_type)
    {
        $hide_ids = str_replace(' ', '', $hide_ids);
        $hide_ids = str_replace(',', ';', $hide_ids);
        $hide_ids = explode(';', $hide_ids);  // option storage is as semicolon-delimited string

        if (empty($hide_ids)) {
            return;
        }

        $object_id = PWP::getPostID();

        if (!$type_obj = get_post_type_object($object_type)) {
            return;
        }

        $pp = presspermit();

        $reqd_caps = [];

        $sitewide_requirement = $pp->getOption('editor_ids_sitewide_requirement');
        $sitewide_requirement_met = false;

        if ('admin_option' == $sitewide_requirement)
            $sitewide_requirement_met = current_user_can('pp_manage_settings');

        elseif ('admin_user' == $sitewide_requirement)
            $sitewide_requirement_met = $pp->isUserAdministrator();

        elseif ('admin_content' == $sitewide_requirement)
            $sitewide_requirement_met = $pp->isContentAdministrator();

        elseif ('editor' == $sitewide_requirement)
            $reqd_caps = (isset($type_obj->cap->edit_published_posts)) 
            ? [$type_obj->cap->edit_published_posts, $type_obj->cap->edit_others_posts]
            : [$type_obj->cap->edit_posts, $type_obj->cap->edit_others_posts];

        elseif ('author' == $sitewide_requirement)
            $reqd_caps = (isset($type_obj->cap->edit_published_posts)) 
            ? [$type_obj->cap->edit_published_posts]
            : [$type_obj->cap->edit_posts];

        elseif ($sitewide_requirement)
            $reqd_caps = [$type_obj->cap->edit_posts];
        else
            $sitewide_requirement_met = true;

        if ($reqd_caps) {
            $sitewide_requirement_met = !array_diff($reqd_caps, array_keys(array_filter(presspermit()->getUser()->allcaps)));
        }

        if ($sitewide_requirement_met) {
            // don't hide anything if a user with sufficient site-wide role is creating a new object
            if (!$object_id)
                return;

            if (!$object = get_post($object_id))
                return;

            if (empty($object->post_date)) // don't prevent the full editing of new posts/pages
                return;
        }


        if ($sitewide_requirement && !$sitewide_requirement_met) {
            do_action('presspermit_hide_admin_divs', $object_id, $hide_ids, $sitewide_requirement);

            echo "\n<style type='text/css'>\n<!--\n";

            $removeable_metaboxes = apply_filters(
                'presspermit_removeable_metaboxes', 
                [   'categorydiv', 
                    'tagsdiv-post_tag', 
                    'postcustom', 
                    'pagecustomdiv', 
                    'authordiv', 
                    'pageauthordiv', 
                    'trackbacksdiv', 
                    'revisionsdiv', 
                    'pending_revisions_div', 
                    'future_revisionsdiv'
                ]
            );

            // This code block took some cues from Clutter Free plugin by Mark Jaquith
            foreach ($hide_ids as $id) {
                if (in_array($id, $removeable_metaboxes, true)) {
                    // thanks to piemanek for tip on using remove_meta_box for any core admin div
                    remove_meta_box($id, $object_type, 'normal');
                    remove_meta_box($id, $object_type, 'advanced');
                } else {
                    // hide via CSS if the element is not a removeable metabox
                    echo "#" . esc_attr($id) . " { display: none !important; }\n";
                }
            }

            echo "-->\n</style>\n";
        }
    }
} // end class
