<?php
/**
 * NGCS – WhatsApp Onboarding Callback Handler
 * Receives ?code= and ?state= from Meta and forwards to n8n.
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   Register Callback Endpoint
   URL: /wp-json/ngcs/v1/wa-onboard-callback
   ============================================================ */

add_action('rest_api_init', function () {
    register_rest_route('ngcs/v1', '/wa-onboard-callback', [
        'methods'  => 'GET',
        'callback' => 'ngcs_wa_onboard_callback_handler',
        'permission_callback' => '__return_true'
    ]);
});


/* ============================================================
   MAIN HANDLER
   ============================================================ */

function ngcs_wa_onboard_callback_handler(WP_REST_Request $request) {

    // Extract parameters from Meta
    $code  = sanitize_text_field($request->get_param('code'));
    $state = sanitize_text_field($request->get_param('state'));

    if (!$code || !$state) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'Missing code or state from Meta onboarding.'
        ], 400);
    }

    // Parse business_id:user_id format
    if (strpos($state, ':') === false) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'Invalid state parameter.'
        ], 400);
    }

    list($business_id, $user_id) = explode(':', $state);

    // Forward onboarding data to n8n (NEW dedicated webhook)
    $n8n_url = "https://n8n.ngcs.co.il/webhook/wp-onboard";

    $response = wp_remote_post($n8n_url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => json_encode([
            'code'        => $code,
            'business_id' => intval($business_id),
            'user_id'     => intval($user_id)
        ]),
        'timeout' => 20
    ]);

    // If n8n is unreachable
    if (is_wp_error($response)) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Failed to forward onboarding data to n8n.',
            'details' => $response->get_error_message()
        ], 500);
    }

    // Success HTML shown after redirect
    $html = "
        <div style='font-family: Arial; padding: 40px; text-align:center;'>
            <h2 style='color: #007bff;'>WhatsApp Connected Successfully 🎉</h2>
            <p>Your WhatsApp account is now being activated inside the NGCS system.</p>
            <p>You can safely close this window.</p>
        </div>
    ";

    return new WP_REST_Response($html, 200);
}
