<?php
if (!defined('ABSPATH')) exit;

class NGCS_Businesses {

    /**
     * Register all REST endpoints for businesses
     */
    public static function register() {

        register_rest_route('ngcs/v1', '/businesses', [
            'methods'  => 'GET',
            'callback' => [self::class, 'get_businesses'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('ngcs/v1', '/business/create', [
            'methods'  => 'POST',
            'callback' => [self::class, 'create_business'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('ngcs/v1', '/business/update', [
            'methods'  => 'POST',
            'callback' => [self::class, 'update_business'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('ngcs/v1', '/business/delete', [
            'methods'  => 'POST',
            'callback' => [self::class, 'delete_business'],
            'permission_callback' => '__return_true'
        ]);
    }

    /**
     * GET — All businesses for this user
     */
    public static function get_businesses($request) {
        global $wpdb;

        $user_id = intval($request['user_id']);

        if (!$user_id) {
            return new WP_Error('missing_user', 'Missing user_id', ['status' => 400]);
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM ngcs_businesses WHERE user_id = %d ORDER BY id DESC", $user_id),
            ARRAY_A
        );

        return $rows ? $rows : [];
    }

    /**
     * POST — Create New Business
     */
    public static function create_business($request) {
        global $wpdb;

        $data = [
            'user_id'       => intval($request['user_id']),
            'business_name' => sanitize_text_field($request['business_name']),
            'phone'         => sanitize_text_field($request['phone']),
            'email'         => sanitize_email($request['email']),
            'logo'          => esc_url_raw($request['logo']),
            'created_at'    => current_time('mysql')
        ];

        $wpdb->insert('ngcs_businesses', $data);

        return [
            'success' => true,
            'business_id' => $wpdb->insert_id
        ];
    }

    /**
     * POST — Update Existing Business
     */
    public static function update_business($request) {
        global $wpdb;

        $id = intval($request['id']);
        if (!$id) return new WP_Error('missing_id', 'Missing business id', 400);

        $data = [];

        if (isset($request['business_name'])) $data['business_name'] = sanitize_text_field($request['business_name']);
        if (isset($request['phone']))         $data['phone']         = sanitize_text_field($request['phone']);
        if (isset($request['email']))         $data['email']         = sanitize_email($request['email']);
        if (isset($request['logo']))          $data['logo']          = esc_url_raw($request['logo']);

        $wpdb->update('ngcs_businesses', $data, ['id' => $id]);

        return ['success' => true];
    }

    /**
     * POST — Delete Business
     */
    public static function delete_business($request) {
        global $wpdb;

        $id = intval($request['id']);
        if (!$id) return new WP_Error('missing_id', 'Missing business id', 400);

        $wpdb->delete('ngcs_businesses', ['id' => $id]);

        return ['success' => true];
    }
}
