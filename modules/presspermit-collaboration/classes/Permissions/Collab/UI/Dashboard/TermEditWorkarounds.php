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

        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/TermParent.php');
        if (!\PublishPress\Permissions\Collab\TermParent::canSelect($taxonomy, $selected_parent, $tag_id)) {
            // The WP_Error contains an escaped translated message; wp_die handles the error object.
            wp_die(\PublishPress\Permissions\Collab\TermParent::denied(), '', ['response' => 403]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_Error message is already escaped.
        }
    }
}
