<?php

namespace PublishPress\Permissions\UI;

class ItemsMetaboxAjax
{
    public function __construct() 
    {
        check_ajax_referer('pp-ajax');

        require_once( PRESSPERMIT_CLASSPATH . '/UI/ItemsMetabox.php' );

        if (PWP::is_POST('item-type', 'post_type')) {
            $type = 'posttype';
            $callback = ['\PublishPress\Permissions\UI\ItemsMetabox', 'post_type_meta_box'];
            $items = (array) presspermit()->getEnabledPostTypes([], 'object');

        } elseif (PWP::is_POST('item-type', 'taxonomy')) {
            $type = 'taxonomy';
            $callback = ['\PublishPress\Permissions\UI\ItemsMetabox', 'taxonomy_meta_box'];
            $items = (array) get_taxonomies( [ 'show_ui' => true ], 'object' );
        }

        // Nonce verificaiton is not needed here, as this is just UI output
        if (!PWP::empty_POST('item-object')) {
            $item_type = PWP::POST_key('item-object');

            if ('posttype' == $type) {            
                if (!presspermit()->admin()->canSetAnyPostPermissions($item_type)) {
                    wp_die( -1 );
                }
            } else {
                if (!presspermit()->admin()->canSetAnyTermPermissions('', $item_type)) {
                    wp_die( -1 );
                }
            }

            if (!empty($items[$item_type])) {
                $item = $items[$item_type];

                ob_start();

                call_user_func_array($callback, [
                    null,
                    [
                        'id' => 'add-' . $item->name,
                        'title' => $item->labels->name,
                        'callback' => $callback,
                        'args' => $item,
                    ]
                ]);

                $markup = ob_get_clean();
                
                echo wp_json_encode([
                    'replace-id' => $type . '-' . $item->name,
                    'markup' => $markup,
                ]);
            }
        }

        exit;
    }
}
