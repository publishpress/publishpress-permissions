<?php

defined('ABSPATH') || exit;

if (presspermit()->isPro()) {
    require_once(PRESSPERMIT_PRO_ABSPATH . '/includes-pro/SettingsTabInstall.php');
} else {
    require_once(PRESSPERMIT_ABSPATH . '/includes/SettingsTabInstall.php');
}
