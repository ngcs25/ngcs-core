<?php
/**
 * NGCS — WhatsApp Onboarding Receiver
 * Receives onboarding data from n8n and saves WA credentials to database.
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   REST API ENDPOINT:
   POST /wp-json/ngcs/v1/register-wa-account
   ============================================================ */

add_action('rest_api_init', function () {
    register_rest_route('ngcs/v1', '/register-wa-account', [
        'methods'  => 'POST',
        'callback' => 'ngcs_register_wa_account',
        'permission_callback' => '__return_true'
    ]);
});


/* ============================================================
   MAIN HANDLER
   ============================================================ */

function ngcs_register_wa_account(WP_REST_Request $request) {
    global $wpdb;

    $table = $wpdb->prefix . "ngcs_wa_accounts";

    $business_id   = intval($request->get_param('business_id'));
    $user_id       = intval($request->get_param('user_id'));
    $waba_id       = sanitize_text_field($request->get_param('waba_id'));
    $phone_id      = sanitize_text_field($request->get_param('phone_id'));
    $access_token  = sanitize_text_field($request->get_param('access_token'));
    $business_name = sanitize_text_field($request->get_param('business_name'));
    $phone_number  = sanitize_text_field($request->get_param('phone_number'));

    if (!$business_id || !$user_id || !$waba_id || !$phone_id || !$access_token) {
        return new WP_Error(
            'missing_params',
            'Required parameters missing.',
            ['status' => 400]
        );
    }

    // CHECK IF BUSINESS ALREADY HAS ACCOUNT
    $existing = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table WHERE business_id = %d LIMIT 1", $business_id)
    );

    if ($existing) {
        // Update existing account
        $wpdb->update(
            $table,
            [
                'user_id'       => $user_id,
                'waba_id'       => $waba_id,
                'phone_id'      => $phone_id,
                'access_token'  => $access_token,
                'business_name' => $business_name,
                'logo_url'      => '',
                'phone_number'  => $phone_number
            ],
            ['business_id' => $business_id]
        );

        return [
            'success' => true,
            'action'  => 'updated',
            'message' => 'WhatsApp account updated for business.'
        ];
    }

    // INSERT NEW ACCOUNT
    $wpdb->insert(
        $table,
        [
            'business_id'   => $business_id,
            'user_id'       => $user_id,
            'waba_id'       => $waba_id,
            'phone_id'      => $phone_id,
            'access_token'  => $access_token,
            'business_name' => $business_name,
            'phone_number'  => $phone_number,
            'created_at'    => current_time('mysql'),
        ]
    );

    return [
        'success' => true,
        'action'  => 'inserted',
        'message' => 'WhatsApp account successfully registered!'
    ];
}
