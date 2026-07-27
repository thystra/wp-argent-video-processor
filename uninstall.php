<?php
/**
 * /home/alan/src/wp-argent-video-processor/uninstall.php
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Preserve queue history, settings, original videos, and derivatives by default.
// Define ARGENT_VIDEO_REMOVE_DATA_ON_UNINSTALL as true before uninstalling to remove plugin data.
if (! defined('ARGENT_VIDEO_REMOVE_DATA_ON_UNINSTALL') || true !== ARGENT_VIDEO_REMOVE_DATA_ON_UNINSTALL) {
    return;
}

global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}argent_video_jobs");
delete_option('argent_video_processor_settings');
delete_option('argent_video_processor_db_version');
delete_option('argent_video_processor_worker_lock');
delete_option('argent_video_processor_last_worker_run');
delete_option('argent_video_processor_last_launch');

// EOF: /home/alan/src/wp-argent-video-processor/uninstall.php
