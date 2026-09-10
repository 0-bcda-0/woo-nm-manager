<?php
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }

if (!(bool)get_option('wnm_remove_data_on_uninstall', false)) {
    return;
}

delete_option('wnm_bundles');
delete_option('wnm_thresholds');
delete_option('wnm_alert_states');
delete_option('wnm_notification_email');
delete_option('wnm_remove_data_on_uninstall');
delete_option('wnm_reconcile_lock');
