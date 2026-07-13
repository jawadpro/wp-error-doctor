<?php
defined('WP_UNINSTALL_PLUGIN') || exit;
delete_option('wpd_widget_settings');
delete_option('wpd_report_page_id');
delete_option('wpd_scanner_page_id');
delete_option('wpd_db_version');
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpd_leads");
