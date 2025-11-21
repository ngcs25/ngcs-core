<?php
if (!defined('ABSPATH')) exit;

/**
 * ============================================================
 * NGCS Onboarding Save API (Corrected for AGENCY MODE)
 * ============================================================
 */

add_action('rest_api_init', function () {
    register_rest_route('ngcs/v1', '/onboarding-save', [
        'methods'  => 'POST',
        'callback' => 'ngcs_onboarding_save_handler',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
});

function ngcs_onboarding_save_handler(WP_REST_Request $request)
{
    global $wpdb;

    $table = $wpdb->prefix . 'ngcs_wa_accounts'; // FIXED TABLE NAME

    // Required parameters
    $user_id      = intval($request->get_param('user_id'));
    $business_id  = intval($request->get_param('business_id')); // IMPORTANT for AGENCY MODE

    if (!$user_id || !$business_id) {
        return new WP_REST_Response(['error' => 'Missing user_id or business_id'], 400);
    }

    // Access token must not be sanitized heavily
    $access_token  = wp_unslash($request->get_param('access_token'));
    $waba_id       = sanitize_text_field($request->get_param('waba_id'));
    $phone_id      = sanitize_text_field($request->get_param('phone_id'));
    $phone         = sanitize_text_field($request->get_param('phone'));
    $display_name  = sanitize_text_field($request->get_param('display_name'));
    $verified_name = sanitize_text_field($request->get_param('verified_name'));
    $business_name = sanitize_text_field($request->get_param('business_name'));
    $timezone      = sanitize_text_field($request->get_param('timezone'));

    // Check existing by both user + business
    $existing = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table WHERE user_id=%d AND business_id=%d LIMIT 1",
            $user_id,
            $business_id
        )
    );

    $data = [
        'user_id'       => $user_id,
        'business_id'   => $business_id,
        'access_token'  => $access_token,
        'waba_id'       => $waba_id,
        'phone_id'      => $phone_id,
        'phone'         => $phone,
        'display_name'  => $display_name,
        'verified_name' => $verified_name,
        'business_name' => $business_name,
        'timezone'      => $timezone,
        'refresh_time'  => current_time('mysql'),
        'updated_at'    => current_time('mysql')
    ];

    if ($existing) {
        $wpdb->update(
            $table,
            $data,
            ['user_id' => $user_id, 'business_id' => $business_id]
        );
        return ['status' => 'updated'];
    } else {
        $wpdb->insert($table, $data);
        return ['status' => 'created'];
    }
}
