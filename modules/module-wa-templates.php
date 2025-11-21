<?php
/**
 * NGCS – WhatsApp Template Sync Module
 * Fetches templates directly from Meta API using business_id-linked account.
 */

if (!defined('ABSPATH')) exit;

/* ================================================================
   Register REST endpoint:
   /wp-json/ngcs/v1/wa-templates?business_id=XX
   ================================================================ */

add_action('rest_api_init', function () {
    register_rest_route('ngcs/v1', '/wa-templates', [
        'methods'  => 'GET',
        'callback' => 'ngcs_fetch_wa_templates',
        'permission_callback' => '__return_true'
    ]);
});


/* ================================================================
   MAIN FUNCTION: Fetch Templates
   ================================================================ */

function ngcs_fetch_wa_templates(WP_REST_Request $req) {
    global $wpdb;

    // VALIDATE BUSINESS ID
    $business_id = intval($req->get_param('business_id'));
    if (!$business_id) {
        return new WP_Error(
            'missing_business_id',
            'business_id is required',
            ['status' => 400]
        );
    }

    // LOOKUP ACCOUNT IN DATABASE
    $table = $wpdb->prefix . "ngcs_wa_accounts";

    $account = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table WHERE business_id = %d LIMIT 1", $business_id),
        ARRAY_A
    );

    if (!$account) {
        return new WP_Error(
            'no_wa_account',
            'No WhatsApp account found for this business_id.',
            ['status' => 404]
        );
    }

    $access_token = $account['access_token'];
    $waba_id      = $account['waba_id'];

    if (!$access_token || !$waba_id) {
        return new WP_Error(
            'missing_credentials',
            'Missing access_token or WABA ID.',
            ['status' => 400]
        );
    }

    /* ================================================================
       CACHE CHECK
       ================================================================ */
    $cache_key = "ngcs_templates_" . $business_id;
    $cached_data = get_transient($cache_key);

    if ($cached_data) {
        return [
            'success' => true,
            'from_cache' => true,
            'templates' => $cached_data
        ];
    }


    /* ================================================================
       META API REQUEST
       ================================================================ */

    $url = "https://graph.facebook.com/v20.0/{$waba_id}/message_templates?limit=200";

    $response = wp_remote_get($url, [
        'headers' => [
            'Authorization' => "Bearer {$access_token}"
        ],
        'timeout' => 20
    ]);

    if (is_wp_error($response)) {
        return new WP_Error(
            'api_error',
            $response->get_error_message(),
            ['status' => 500]
        );
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    // INVALID API RESPONSE
    if (!isset($body['data'])) {
        return new WP_Error(
            'invalid_meta_response',
            'Meta API did not return "data".',
            ['status' => 500, 'meta_response' => $body]
        );
    }

    $templates = $body['data'];


    /* ================================================================
       SAVE TO CACHE (30 MINUTES)
       ================================================================ */
    set_transient($cache_key, $templates, 30 * MINUTE_IN_SECONDS);


    /* ================================================================
       RETURN TEMPLATES
       ================================================================ */

    return [
        'success'   => true,
        'from_cache'=> false,
        'templates' => $templates
    ];
}
