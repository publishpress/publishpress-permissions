<?php
/*
Copyright © 2025 PublishPress

This file is part of PublishPress Permissions.

PublishPress Permissions is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

PublishPress Permissions is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this plugin.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace PublishPress;

\defined('ABSPATH') || exit;

if (!defined('PRESSPERMIT_COLLAB_FILE')) {
    define('PRESSPERMIT_COLLAB_FILE', __FILE__);
    define('PRESSPERMIT_COLLAB_ABSPATH', __DIR__);
    define('PRESSPERMIT_COLLAB_CLASSPATH', __DIR__ . '/classes/Permissions/Collab');

    add_action(
        'plugins_loaded', 
        function() 
        {
            if (!defined('PRESSPERMIT_VERSION')) {
                return;
            }

            $ext_version = PRESSPERMIT_VERSION;

            $title = 'Editing Permissions'; // @todo: review removing this, as it is separately set with translation downstream

            if (presspermit()->registerModule(
                'collaboration', $title, dirname(plugin_basename(__FILE__)), $ext_version, ['min_pp_version' => '2.7-beta']
            )) {
                define('PRESSPERMIT_COLLAB_VERSION', $ext_version);

                class_alias('\PressShack\LibArray', '\PublishPress\Permissions\Collab\Arr');
                class_alias('\PressShack\LibArray', '\PublishPress\Permissions\Collab\Revisions\Arr');
                class_alias('\PressShack\LibArray', '\PublishPress\Permissions\Collab\Revisionary\Arr');
                class_alias('\PressShack\LibArray', '\PublishPress\Permissions\Collab\UI\Dashboard\Arr');

                class_alias('\PressShack\LibWP', '\PublishPress\Permissions\Collab\PWP');
                class_alias('\PressShack\LibWP', '\PublishPress\Permissions\Collab\Compat\PWP');
                class_alias('\PressShack\LibWP', '\PublishPress\Permissions\Collab\Revisions\PWP');
                class_alias('\PressShack\LibWP', '\PublishPress\Permissions\Collab\Revisionary\PWP');
                class_alias('\PressShack\LibWP', '\PublishPress\Permissions\Collab\UI\PWP');
                class_alias('\PressShack\LibWP', '\PublishPress\Permissions\Collab\UI\Dashboard\PWP');
                class_alias('\PressShack\LibWP', '\PublishPress\Permissions\Collab\UI\Gutenberg\PWP');
                class_alias('\PressShack\LibWP', '\PublishPress\Permissions\Collab\UI\Handlers\PWP');

                require_once(__DIR__ . '/classes/Permissions/Collab.php');
                class_alias('\PublishPress\Permissions\Collab', '\PublishPress\Permissions\Collab\Collab');

                if ( is_admin() ) {
                    class_alias('\PublishPress\Permissions\Collab', '\PublishPress\Permissions\Collab\UI\Collab');
                    class_alias('\PublishPress\Permissions\Collab', '\PublishPress\Permissions\Collab\UI\Handlers\Collab');
                    class_alias('\PublishPress\Permissions\Collab', '\PublishPress\Permissions\Collab\UI\Dashboard\Collab');
                    class_alias('\PublishPress\Permissions\Collab', '\PublishPress\Permissions\Collab\UI\Gutenberg\Collab');
                    class_alias('\PublishPress\Permissions\Collab', '\PublishPress\Permissions\Collab\Revisions\Collab');
                    class_alias('\PublishPress\Permissions\Collab', '\PublishPress\Permissions\Collab\Revisionary\Collab');
                }

                require_once(__DIR__ . '/classes/Permissions/CollabHooksCompat.php');
                new \PublishPress\Permissions\CollabHooksCompat();

                // Divi Page Builder
                if (PWP::is_REQUEST('action', 'editpost') && !PWP::empty_REQUEST('et_pb_use_builder') && !PWP::empty_REQUEST('auto_draft')) {
                    return;
                }

                if (PWP::is_REQUEST('action', 'edit')) {
                    if ($post_id = PWP::REQUEST_int('post')) {
                        if ($_post = get_post($post_id)) {
                            if ('auto-draft' == $post_id) {
                            	return;
                            }
                        }
                    }
                }

                require_once(__DIR__ . '/classes/Permissions/CollabHooks.php');
                new \PublishPress\Permissions\CollabHooks();

                if (is_admin()) {
                    require_once(__DIR__ . '/classes/Permissions/CollabHooksAdmin.php');
                    new \PublishPress\Permissions\CollabHooksAdmin();
                }
            }
        }
    );

    add_action(
        'plugins_loaded', 
        function() 
        {
            if (!defined('REVISIONARY_VERSION') && defined('RVY_VERSION')) {
                define('REVISIONARY_VERSION', RVY_VERSION);
            }
        }, 
        20
    );
} else {
    add_action(
        'init', 
        function()
        {
            do_action('presspermit_duplicate_module', 'collaboration', dirname(plugin_basename(__FILE__)));
        }
    );
    return;
}
