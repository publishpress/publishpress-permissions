<?php
namespace PublishPress\Permissions\Collab\UI\Dashboard;

\defined('ABSPATH') || exit;

class Media
{
    public static function count_attachments_query($query)
    { // return false if no modification
        if (strpos($query, 'WHERE ')) {
            static $att_sanity_count = 0;

            if ($att_sanity_count > 5) {  // todo: why does this apply filtering to 300+ queries on at least one MS installation?
                return false;
            }

            $att_sanity_count++;

            if (!empty(presspermit()->getUser()->allcaps['pp_list_all_files'])) {
                return false;
            } else {
                return apply_filters('presspermit_posts_request', $query, ['pp_context' => 'count_attachments']);
            }
        }

        return false;
    }
}
