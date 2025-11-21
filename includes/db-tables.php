<?php
if (!defined('ABSPATH')) exit;

/**
 * ============================================================
 * NGCS Database Installer / Upgrader (AGENCY MODE)
 * ============================================================
 * This file:
 *  ✔ Creates ngcs_businesses table
 *  ✔ Updates WhatsApp accounts table
 *  ✔ Updates customers table
 *  ✔ Updates message logs table
 *  ✔ Uses dbDelta() for safe upgrades
 * ============================================================
 */

function ngcs_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $prefix = $wpdb->prefix;

    $table_businesses = $prefix . "ngcs_businesses";
    $table_accounts   = $prefix . "ngcs_wa_accounts";
    $table_customers  = $prefix . "ngcs_customers";
    $table_logs       = $prefix . "ngcs_message_logs";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');


    /**
     * ============================================================
     * TABLE 1 — Businesses (NEW)
     * ============================================================
     */
    $sql_businesses = "CREATE TABLE $table_businesses (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        owner_user_id bigint(20) unsigned NOT NULL,
        business_name varchar(255) NOT NULL,
        contact_email varchar(255) DEFAULT '',
        contact_phone varchar(50) DEFAULT '',
        logo_url varchar(255) DEFAULT '',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY owner_user_id (owner_user_id)
    ) $charset;";


    /**
     * ============================================================
     * TABLE 2 — WhatsApp Accounts (UPDATED)
     * ============================================================
     */
    $sql_accounts = "CREATE TABLE $table_accounts (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        business_id bigint(20) unsigned NOT NULL,
        user_id bigint(20) unsigned NOT NULL,
        waba_id varchar(50) DEFAULT '',
        phone_id varchar(50) DEFAULT '',
        access_token longtext,
        business_name varchar(255) DEFAULT '',
        logo_url varchar(255) DEFAULT '',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY business_id (business_id),
        KEY user_id (user_id)
    ) $charset;";


    /**
     * ============================================================
     * TABLE 3 — Customers (UPDATED)
     * ============================================================
     */
    $sql_customers = "CREATE TABLE $table_customers (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        business_id bigint(20) unsigned NOT NULL,
        user_id bigint(20) unsigned NOT NULL,
        full_name varchar(255) NOT NULL,
        phone varchar(50) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY business_id (business_id),
        KEY user_id (user_id),
        KEY phone (phone)
    ) $charset;";


    /**
     * ============================================================
     * TABLE 4 — Message Logs (UPDATED)
     * ============================================================
     */
    $sql_logs = "CREATE TABLE $table_logs (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        business_id bigint(20) unsigned NOT NULL,
        user_id bigint(20) unsigned NOT NULL,
        full_name varchar(255) DEFAULT '',
        phone varchar(50) NOT NULL,
        template varchar(255) DEFAULT '',
        response longtext,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY business_id (business_id),
        KEY user_id (user_id),
        KEY phone (phone)
    ) $charset;";


    /**
     * ============================================================
     * EXECUTE SAFE UPGRADES
     * ============================================================
     */
    dbDelta($sql_businesses);
    dbDelta($sql_accounts);
    dbDelta($sql_customers);
    dbDelta($sql_logs);
}


/**
 * ============================================================
 * PLUGIN ACTIVATION HOOK
 * ============================================================
 * IMPORTANT:
 * We must point to the main plugin file:
 *    ngcs-core/ngcs-core.php
 * ============================================================
 */

// __DIR__ = /wp-content/plugins/ngcs-core/includes
// dirname(__DIR__) = /wp-content/plugins/ngcs-core

register_activation_hook(dirname(__DIR__) . '/ngcs-core.php', 'ngcs_create_tables');
