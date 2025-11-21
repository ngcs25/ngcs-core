<?php

if (!defined('ABSPATH')) exit;

/**
 * ============================================================
 * NGCS REST API ROUTES (BUSINESSES + TEMPLATES + ONBOARDING)
 * ============================================================
 */

add_action('rest_api_init', function () {

    // 1) Get All Businesses
    register_rest_route('ngcs/v1', '/businesses', [
        'methods'  => 'GET',
        'callback' => 'ngcs_get_businesses',
        'permission_callback' => function() { return true; }
    ]);

    // 2) Get Single Business Info
    register_rest_route('ngcs/v1', '/business-info', [
        'methods'  => 'GET',
        'callback' => 'ngcs_get_business_info',
        'permission_callback' => function() { return true; }
    ]);

    // 3) Get Templates (per business)
    register_rest_route('ngcs/v1', '/templates', [
        'methods'  => 'GET',
        'callback' => 'ngcs_get_templates',
        'permission_callback' => function() { return true; }
    ]);

    // 4) WhatsApp Onboarding Status
    register_rest_route('ngcs/v1', '/onboarding-status', [
        'methods'  => 'GET',
        'callback' => 'ngcs_get_onboarding_status',
        'permission_callback' => function() { return true; }
    ]);
});


/**
 * ============================================================
 * 1) GET ALL BUSINESSES
 * ============================================================
 */
function ngcs_get_businesses(WP_REST_Request $request) {
    global $wpdb;

    $user_id = intval($request->get_param('user_id'));

    $table = $wpdb->prefix . "ngcs_businesses";

    $results = $wpdb->get_results(
        $wpdb->prepare("
            SELECT 
                id,
                owner_user_id AS user_id,
                business_name,
                contact_phone AS phone,
                logo_url AS logo
            FROM $table
            WHERE owner_user_id = %d
        ", $user_id),
        ARRAY_A
    );

    return $results ?: [];
}


/**
 * ============================================================
 * 2) GET SINGLE BUSINESS INFO
 * ============================================================
 */
function ngcs_get_business_info(WP_REST_Request $request) {
    global $wpdb;

    $business_id = intval($request->get_param('business_id'));

    $table = $wpdb->prefix . "ngcs_businesses";

    $row = $wpdb->get_row(
        $wpdb->prepare("
            SELECT
                id,
                owner_user_id AS user_id,
                business_name,
                contact_phone AS phone,
                contact_email AS email,
                logo_url AS logo,
                created_at
            FROM $table
            WHERE id = %d
            LIMIT 1
        ", $business_id),
        ARRAY_A
    );

    return $row ?: [];
}


/**
 * ============================================================
 * 3) GET TEMPLATES
 * ============================================================
 */
function ngcs_get_templates(WP_REST_Request $request) {
    global $wpdb;

    $business_id = intval($request->get_param('business_id'));
    $table = $wpdb->prefix . "ngcs_templates";

    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        return [];
    }

    $results = $wpdb->get_results(
        $wpdb->prepare("
            SELECT 
                id,
                template_name,
                status,
                approval_message
            FROM $table
            WHERE business_id = %d
        ", $business_id),
        ARRAY_A
    );

    return $results ?: [];
}


/**
 * ============================================================
 * 4) GET WHATSAPP ONBOARDING STATUS
 * ============================================================
 */
function ngcs_get_onboarding_status(WP_REST_Request $request) {
    global $wpdb;

    $business_id = intval($request->get_param('business_id'));
    $table = $wpdb->prefix . 'ngcs_wa_accounts';

    if (!$business_id) {
        return new WP_REST_Response(['error' => 'Missing business_id'], 400);
    }

    $row = $wpdb->get_row(
        $wpdb->prepare("
            SELECT 
                waba_id,
                phone_id,
                phone,
                display_name,
                verified_name,
                business_name,
                timezone,
                updated_at,
                refresh_time
            FROM $table
            WHERE business_id = %d
            LIMIT 1
        ", $business_id),
        ARRAY_A
    );

    if (!$row) {
        return ['connected' => false];
    }

    $row['connected'] = true;
    return $row;
}
