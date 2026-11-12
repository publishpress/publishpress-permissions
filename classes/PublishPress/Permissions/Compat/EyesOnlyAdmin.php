<?php

namespace PublishPress\Permissions\Compat;

\defined('ABSPATH') || exit;

class EyesOnlyAdmin
{
    public function __construct() {
        add_filter('sseo_pp_group_items', [$this, 'fltGroups']);
    }
    
    public function fltGroups($group_labels)
    {
        $group_labels = [];

        $groups = presspermit()->groups()->getGroups('pp_group', ['skip_meta_types' => ['wp_role']]);
        foreach ($groups as $group)
            $group_labels[$group->ID] = $group->name;

        return $group_labels;
    }
}