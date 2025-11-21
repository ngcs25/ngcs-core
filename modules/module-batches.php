<?php
/**
 * NGCS – Batch Management Engine
 * Handles: batch list, rows, deletion, sending, stats
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   REGISTER REST ENDPOINTS
   ============================================================ */

add_action('rest_api_init', function () {

    register_rest_route('ngcs/v1', '/batches', [
        'methods'  => 'GET',
        'callback' => 'ngcs_get_batches',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('ngcs/v1', '/get-rows', [
        'methods'  => 'GET',
        'callback' => 'ngcs_get_batch_rows',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('ngcs/v1', '/delete-row', [
        'methods'  => 'POST',
        'callback' => 'ngcs_delete_row',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('ngcs/v1', '/delete-batch', [
        'methods'  => 'POST',
        'callback' => 'ngcs_delete_batch',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('ngcs/v1', '/send-selected', [
        'methods'  => 'POST',
        'callback' => 'ngcs_send_selected',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('ngcs/v1', '/batch-stats', [
        'methods'  => 'GET',
        'callback' => 'ngcs_batch_stats',
        'permission_callback' => '__return_true'
    ]);

});


/* ============================================================
   1) GET BATCHES FOR USER
   ============================================================ */

function ngcs_get_batches(WP_REST_Request $request)
{
    global $wpdb;
    $user_id = get_current_user_id();

    $table = $wpdb->prefix . 'ngcs_excel_batches';

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, file_name, created_at 
         FROM $table 
         WHERE user_id = %d
         ORDER BY id DESC",
        $user_id
    ));

    return [
        'success' => true,
        'batches' => $rows
    ];
}


/* ============================================================
   2) GET ROWS OF A BATCH + PAGINATION
   ============================================================ */

function ngcs_get_batch_rows(WP_REST_Request $request)
{
    global $wpdb;

    $batch_id = intval($request->get_param('batch_id'));
    $page     = intval($request->get_param('page')) ?: 1;
    $limit    = intval($request->get_param('limit')) ?: 25;
    $offset   = ($page - 1) * $limit;

    $table = $wpdb->prefix . 'ngcs_excel_rows';

    $total = $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE batch_id = %d", $batch_id)
    );

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table 
         WHERE batch_id = %d 
         ORDER BY id ASC 
         LIMIT %d OFFSET %d",
        $batch_id, $limit, $offset
    ));

    return [
        'success' => true,
        'rows'    => $rows,
        'total'   => intval($total),
        'page'    => $page
    ];
}


/* ============================================================
   3) DELETE A SINGLE ROW
   ============================================================ */

function ngcs_delete_row(WP_REST_Request $request)
{
    global $wpdb;

    $row_id = intval($request->get_param('row_id'));

    $table = $wpdb->prefix . 'ngcs_excel_rows';

    $wpdb->delete($table, ['id' => $row_id]);

    return ['success' => true];
}


/* ============================================================
   4) DELETE ENTIRE BATCH
   ============================================================ */

function ngcs_delete_batch(WP_REST_Request $request)
{
    global $wpdb;

    $batch_id = intval($request->get_param('batch_id'));

    $tbl_rows   = $wpdb->prefix . 'ngcs_excel_rows';
    $tbl_batches = $wpdb->prefix . 'ngcs_excel_batches';

    $wpdb->delete($tbl_rows, ['batch_id' => $batch_id]);
    $wpdb->delete($tbl_batches, ['id' => $batch_id]);

    return ['success' => true];
}


/* ============================================================
   5) SEND SELECTED ROWS → FORWARD TO N8N
   ============================================================ */

function ngcs_send_selected(WP_REST_Request $request)
{
    $row_ids = $request->get_param('rows');
    $batch_id = intval($request->get_param('batch_id'));

    if (!is_array($row_ids) || empty($row_ids)) {
        return ['success' => false, 'msg' => 'No rows selected'];
    }

    // Forward to n8n
    $endpoint = "https://n8n.ngcs.co.il/webhook/send-selected";

    wp_remote_post($endpoint, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => json_encode([
            'batch_id' => $batch_id,
            'rows'     => $row_ids
        ])
    ]);

    return ['success' => true];
}


/* ============================================================
   6) GET BATCH STATS
   ============================================================ */

function ngcs_batch_stats(WP_REST_Request $request)
{
    global $wpdb;

    $batch_id = intval($request->get_param('batch_id'));

    $table = $wpdb->prefix . 'ngcs_excel_rows';

    $counts = [
        'total'     => 0,
        'sent'      => 0,
        'delivered' => 0,
        'read'      => 0,
        'failed'    => 0,
        'invalid'   => 0,
        'duplicate' => 0,
        'not_sent'  => 0,
    ];

    foreach ($counts as $key => &$v) {
        $v = intval($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE batch_id=%d AND status=%s",
                $batch_id, $key
            )
        ));
    }

    // Total rows
    $counts['total'] = intval($wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE batch_id=%d", $batch_id)
    ));

    return ['success' => true, 'stats' => $counts];
}
?>
