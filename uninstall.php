<?php
// WordPress defines this only during the plugin deletion workflow.
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }
require_once __DIR__ . '/classes/poll-uninstall.php';
OnyxPollsUninstall::run();
