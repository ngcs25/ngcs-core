<?php
/**
 * NGCS – Secure webhooks for status updates (n8n / WhatsApp callbacks)
 */

if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function() {
    register_rest_route('ngcs/v1', '/webhook/status', [
        'methods' => 'POST',
        'callback' => 'ngcs_webhook_status_handler',
        'permission_callback' => function() {
            $secret = get_option('ngcs_webhook_secret', '');
            $hdr = isset($_SERVER['HTTP_X_NGCS_SECRET']) ? $_SERVER['HTTP_X_NGCS_SECRET'] : '';
            if (empty($secret)) return false;
            return hash_equals($secret, $hdr);
        }
    ]);
});

function ngcs_webhook_status_handler(WP_REST_Request $request) {
    $payload = $request->get_json_params();

    // Basic validation
    if (!isset($payload['row_id']) || !isset($payload['status'])) {
        return new WP_Error('invalid_payload', 'row_id or status missing', ['status'=>400]);
    }

    // Delegate to existing updater if available
    if (function_exists('ngcs_update_row_status_from_n8n')) {
        ngcs_update_row_status_from_n8n($payload);
        return ['success'=>true];
    }

    return new WP_Error('handler_missing', 'Update handler not available', ['status'=>500]);
}
?>
