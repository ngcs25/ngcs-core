<?php
/**
 * NGCS – Migration runner (WP-CLI)
 * Usage: wp ngcs migrate
 */

if (!defined('WP_CLI')) return;

WP_CLI::add_command('ngcs migrate', function($args, $assoc_args) {
    global $wpdb;

    $old_batches = $wpdb->prefix . 'ngcs_excel_batches';
    $old_rows = $wpdb->prefix . 'ngcs_excel_rows';
    $new_batches = $wpdb->prefix . 'ngcs_batches';
    $new_rows = $wpdb->prefix . 'ngcs_batch_rows';

    WP_CLI::log('Starting NGCS migration...');

    if ($wpdb->get_var("SHOW TABLES LIKE '{$old_batches}'")) {
        WP_CLI::log("Renaming {$old_batches} -> {$new_batches}");
        $wpdb->query("RENAME TABLE `{$old_batches}` TO `{$new_batches}`");
    } else {
        WP_CLI::log("{$old_batches} not found, skipping");
    }

    if ($wpdb->get_var("SHOW TABLES LIKE '{$old_rows}'")) {
        WP_CLI::log("Renaming {$old_rows} -> {$new_rows}");
        $wpdb->query("RENAME TABLE `{$old_rows}` TO `{$new_rows}`");
    } else {
        WP_CLI::log("{$old_rows} not found, skipping");
    }

    WP_CLI::success('NGCS migration complete.');
});
?>
