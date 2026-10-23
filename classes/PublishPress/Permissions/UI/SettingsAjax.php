<?php

namespace PublishPress\Permissions\UI;

\defined('ABSPATH') || exit;

class SettingsAjax
{
    public function __construct() {
        if (presspermit()->isPro()) {
			include_once(PRESSPERMIT_PRO_ABSPATH . '/includes-pro/pro-activation-ajax.php');
		}
    }
}
