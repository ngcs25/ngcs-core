<?php

if (!defined('ABSPATH')) exit;

class NGCS_REST_Businesses {

    const NAMESPACE = 'ngcs/v1';

    public function register_routes() {

        register_rest_route(
            self::NAMESPACE,
            '/businesses',
            [
                'methods'  => 'GET',
                'callback' => [$this, 'get_businesses'],
                'permission_callback' => function () {
                    return is_user_logged_in();
                }
            ]
        );
    }

    public function get_businesses($request) {
        $user_id = get_current_user_id();

        // Dummy test data — replace later with DB query
        $demo = [
            [
                'id' => 1,
                'business_name' => 'Perfect Parts',
                'phone' => '0501234567',
                'email' => 'info@perfectparts.com',
                'logo' => 'https://via.placeholder.com/150'
            ],
            [
                'id' => 2,
                'business_name' => 'Panda Center',
                'phone' => '0507654321',
                'email' => 'hello@panda.com',
                'logo' => 'https://via.placeholder.com/150/00F/FFF'
            ]
        ];

        return $demo;
    }
}
