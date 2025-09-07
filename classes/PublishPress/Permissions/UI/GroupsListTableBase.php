<?php

namespace PublishPress\Permissions\UI;

class GroupsListTableBase extends \WP_List_Table
{
    public $role_info;
    public $exception_info;

    public function __construct($args) {
        parent::__construct();
    }

    // Moved out of class GroupsListTable to support sharing with subclasses
    public function single_row_role_column($column_name, $group_id, $can_manage_group, $edit_link, $args = [])
    {
        switch ($column_name) {
            case 'roles':
                $role_str = '';

                if (isset($this->role_info[$group_id])) {
                    if (isset($this->role_info[$group_id]['roles'])) {
                        $display_limit = 3;
                        $any_role = true;

                        $role_titles = [];
                        $i = 0;
                        foreach (array_keys($this->role_info[$group_id]['roles']) as $role_title) {
                            $i++;
                            $role_titles[] = $role_title;
                            if ($i >= $display_limit) {
                                break;
                            }
                        }

                        if ($can_manage_group) {
                            echo "<a href='" . esc_url($edit_link) . "' title='" . esc_attr__('edit group permissions', 'press-permit-core') . "'>";
                        }

                        echo '<span class="pp-group-site-roles">';

                        if (count($this->role_info[$group_id]['roles']) > $display_limit) {
                            // phpcs Note: Role titles are escaped and html-wrapped upstream, cannot be escaped here

                            printf(esc_html__('%s, more...', 'press-permit-core'), implode(', ', $role_titles));  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        } else {
                            echo implode(', ', $role_titles);  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }

                        echo '</span>';

                        if ($can_manage_group) {
                            echo "</a><br />";
                        }
                    }
                }

                if (empty($any_role) && !empty($args['none_link']) && !empty($args['none_title'])) {
                    echo "<a href='" . esc_url($args['none_link']) . "'>" . esc_html($args['none_title']) . "</a>";
                }

                break;

            case 'exceptions':
                $exc_str = '';

                if (isset($this->exception_info[$group_id])) {
                    if (isset($this->exception_info[$group_id]['exceptions'])) {
                        $display_limit = 3;
                        $any_exception = true;

                        $exc_titles = [];
                        $i = 0;
                        foreach ($this->exception_info[$group_id]['exceptions'] as $exc_title => $exc_count) {
                            $i++;
                            $exc_titles[] = sprintf(esc_html__('%1$s (%2$s)', 'press-permit-core'), $exc_title, $exc_count);
                            if ($i >= $display_limit) {
                                break;
                            }
                        }

                        if ($can_manage_group) {
                            echo "<a href='" . esc_url($edit_link) . "' title='" . esc_attr__('edit group permissions', 'press-permit-core') . "'>";
                        }

                        echo '<span class="pp-group-site-roles">';

                        if (isset($this->role_info[$group_id]) && count($this->role_info[$group_id]['roles']) > $display_limit) {
                            printf(esc_html__('%s, more...', 'press-permit-core'), esc_html(implode(', ', $exc_titles)));
                        } else {
                            echo esc_html(implode(', ', $exc_titles));
                        }

                        echo '</span>';

                        if ($can_manage_group) {
                            echo "</a><br />";
                        }
                    }
                }

                if (empty($any_exception) && !empty($args['none_link']) && !empty($args['none_title'])) {
                    echo "<a href='" . esc_url($args['none_link']) . "'>" . esc_html($args['none_title']) . "</a>";
                }

                break;
        }
    }

    protected function row_actions( $actions, $always_visible = false ) {
		$action_count = count( $actions );

		if ( ! $action_count ) {
			return '';
		}

		$mode = get_user_setting( 'posts_list_mode', 'list' );

		if ( 'excerpt' === $mode ) {
			$always_visible = true;
		}

		echo '<div class="' . ( $always_visible ? 'row-actions visible' : 'row-actions' ) . '">';

		$i = 0;

		foreach ( $actions as $action => $link ) {
            // phpcs Note: row action link is escaped upstream

			++$i;
			$sep = ( $i < $action_count ) ? ' | ' : '';
			echo "<span class='" . esc_attr($action) . "'>" . $link . esc_html($sep) . "</span>";  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '</div>';

		echo '<button type="button" class="toggle-row"><span class="screen-reader-text">' . esc_html__( 'Show more details' ) . '</span></button>';
    }
}
