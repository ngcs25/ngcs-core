<?php
/**
 * NGCS Core – Excel Messaging Engine (Module 1)
 * File: module-excel.php
 * Version: 1.0.0
 * Author: NGCS
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
    1. DATABASE CREATION – ON ACTIVATION
   ============================================================ */
register_activation_hook(__FILE__, 'ngcs_excel_create_tables');

function ngcs_excel_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $table_batches = $wpdb->prefix . "ngcs_batches";
    $table_rows    = $wpdb->prefix . "ngcs_batch_rows";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Batches
    dbDelta("CREATE TABLE $table_batches (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        business_id BIGINT UNSIGNED NOT NULL,
        file_name TEXT NOT NULL,
        name VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(50) NOT NULL DEFAULT 'pending',
        total_rows INT NOT NULL DEFAULT 0,
        success_rows INT NOT NULL DEFAULT 0,
        failed_rows INT NOT NULL DEFAULT 0,
        delivered_rows INT NOT NULL DEFAULT 0,
        read_rows INT NOT NULL DEFAULT 0,
        duplicate_rows INT NOT NULL DEFAULT 0,
        invalid_rows INT NOT NULL DEFAULT 0,
        deleted TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) $charset;");

    // Rows
    dbDelta("CREATE TABLE $table_rows (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        batch_id BIGINT UNSIGNED NOT NULL,
        row_number INT NOT NULL,
        data LONGTEXT NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'not_sent',
        whatsapp_message_id VARCHAR(255) DEFAULT NULL,
        error_message TEXT DEFAULT NULL,
        deleted TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY batch_id (batch_id)
    ) $charset;");
}

/* ============================================================
    2. EXCEL PREVIEW ENDPOINT – CREATE BATCH + ROWS
   ============================================================ */
add_action('rest_api_init', function() {
    register_rest_route('ngcs/v1', '/excel-preview', [
        'methods'  => 'POST',
        'callback' => 'ngcs_excel_preview',
        'permission_callback' => '__return_true'
    ]);
});

function ngcs_excel_preview(WP_REST_Request $request) {
    global $wpdb;

    $business_id = intval($request->get_param('business_id'));
    $file        = $request->get_file_params()['file'];

    if (!$file || !$business_id) {
        return new WP_Error('missing', 'business_id or file missing', ['status'=>400]);
    }

    if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
        return new WP_Error('phpspreadsheet', 'PhpSpreadsheet missing', ['status'=>500]);
    }

    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();
    $rows  = $sheet->toArray(null, true, true, true);

    if (empty($rows)) return new WP_Error('empty', 'Excel file empty');

    // Create batch
    $table_batches = $wpdb->prefix . "ngcs_batches";
    $wpdb->insert($table_batches, [
        'business_id' => $business_id,
        'file_name'   => sanitize_text_field($file['name']),
        'total_rows'  => max(0, count($rows) - 1)
    ]);

    $batch_id = $wpdb->insert_id;

    $header = array_values($rows[1]); // First row is header
    $table_rows = $wpdb->prefix . "ngcs_batch_rows";

    $preview = [];

    foreach ($rows as $i => $row) {
        if ($i == 1) continue;

        $data = [];
        foreach ($header as $c => $colName) {
            $colLetter = chr(65 + $c);
            $data[$colName] = $row[$colLetter] ?? null;
        }

        $wpdb->insert($table_rows, [
            'batch_id'   => $batch_id,
            'row_number' => $i,
            'data'       => json_encode($data),
        ]);

        $data['row_id'] = $wpdb->insert_id;
        $preview[] = $data;
    }

    return [
        'success'  => true,
        'batch_id' => $batch_id,
        'columns'  => $header,
        'rows'     => $preview
    ];
}

/* ============================================================
    3. GET ROWS (for live updates)
   ============================================================ */
add_action('rest_api_init', function() {
    register_rest_route('ngcs/v1', '/get-rows', [
        'methods'=>'GET',
        'callback'=>'ngcs_get_rows',
        'permission_callback'=>'__return_true'
    ]);
});

function ngcs_get_rows(WP_REST_Request $req) {
    global $wpdb;
    $batch_id = intval($req->get_param('batch_id'));

    $table = $wpdb->prefix . "ngcs_batch_rows";
    $rows = $wpdb->get_results(
        "SELECT id as row_id, data, status 
         FROM $table 
         WHERE batch_id=$batch_id AND deleted=0",
        ARRAY_A
    );

    foreach ($rows as &$r) {
        $r['data'] = json_decode($r['data'], true);
    }

    return ['success'=>true, 'rows'=>$rows];
}

/* ============================================================
    4. BATCH STATS ENDPOINT
   ============================================================ */
add_action('rest_api_init', function() {
    register_rest_route('ngcs/v1', '/batch-stats', [
        'methods'=>'GET',
        'callback'=>'ngcs_batch_stats',
        'permission_callback'=>'__return_true'
    ]);
});

function ngcs_batch_stats(WP_REST_Request $req) {
    global $wpdb;

    $batch_id = intval($req->get_param('batch_id'));
    $table_batches = $wpdb->prefix . "ngcs_batches";
    $table_rows    = $wpdb->prefix . "ngcs_batch_rows";

    $batch = $wpdb->get_row("SELECT * FROM $table_batches WHERE id=$batch_id AND deleted=0", ARRAY_A);
    if (!$batch) return new WP_Error('notfound','Batch not found');

    $stats = [
        'total'      => (int)$wpdb->get_var("SELECT COUNT(*) FROM $table_rows WHERE batch_id=$batch_id AND deleted=0"),
        'not_sent'   => (int)$wpdb->get_var("SELECT COUNT(*) FROM $table_rows WHERE batch_id=$batch_id AND deleted=0 AND status='not_sent'"),
        'sending'    => (int)$wpdb->get_var("SELECT COUNT(*) AND deleted=0 AND status='sending'"),
        'sent'       => (int)$wpdb->get_var("SELECT COUNT(*) FROM $table_rows WHERE batch_id=$batch_id AND status='sent'"),
        'delivered'  => (int)$wpdb->get_var("SELECT COUNT(*) FROM $table_rows WHERE batch_id=$batch_id AND status='delivered'"),
        'read'       => (int)$wpdb->get_var("SELECT COUNT(*) FROM $table_rows WHERE batch_id=$batch_id AND status='read'"),
        'failed'     => (int)$wpdb->get_var("SELECT COUNT(*) FROM $table_rows WHERE batch_id=$batch_id AND status='failed'"),
        'invalid'    => (int)$wpdb->get_var("SELECT COUNT(*) FROM $table_rows WHERE batch_id=$batch_id AND status='invalid'"),
        'duplicate'  => (int)$wpdb->get_var("SELECT COUNT(*) FROM $table_rows WHERE batch_id=$batch_id AND status='duplicate'"),
    ];

    return ['success'=>true, 'batch'=>$batch, 'stats'=>$stats];
}

/* ============================================================
    5. DELETE ROW
   ============================================================ */
add_action('rest_api_init', function() {
    register_rest_route('ngcs/v1', '/delete-row', [
        'methods'=>'POST',
        'callback'=>'ngcs_delete_row',
        'permission_callback'=>'__return_true'
    ]);
});

function ngcs_delete_row(WP_REST_Request $req) {
    global $wpdb;
    $row_id = intval($req->get_param('row_id'));

    $wpdb->update(
        $wpdb->prefix . "ngcs_batch_rows",
        ['deleted'=>1],
        ['id'=>$row_id]
    );

    return ['success'=>true];
}

/* ============================================================
    6. DELETE BATCH
   ============================================================ */
add_action('rest_api_init', function() {
    register_rest_route('ngcs/v1', '/delete-batch', [
        'methods'=>'POST',
        'callback'=>'ngcs_delete_batch',
        'permission_callback'=>'__return_true'
    ]);
});

function ngcs_delete_batch(WP_REST_Request $req) {
    global $wpdb;
    $batch_id = intval($req->get_param('batch_id'));

    $wpdb->update($wpdb->prefix."ngcs_batches", ['deleted'=>1], ['id'=>$batch_id]);
    $wpdb->update($wpdb->prefix."ngcs_batch_rows", ['deleted'=>1], ['batch_id'=>$batch_id]);

    return ['success'=>true];
}

/* ============================================================
    7. SEND SELECTED ROWS → n8n
   ============================================================ */
add_action('rest_api_init', function() {
    register_rest_route('ngcs/v1', '/send-selected', [
        'methods'=>'POST',
        'callback'=>'ngcs_send_selected',
        'permission_callback'=>'__return_true'
    ]);
});

function ngcs_send_selected(WP_REST_Request $req) {
    global $wpdb;

    $batch_id = intval($req->get_param('batch_id'));
    $row_ids  = $req->get_param('rows');

    $table = $wpdb->prefix . "ngcs_batch_rows";

    $sendRows = [];
    foreach ($row_ids as $id) {
        $r = $wpdb->get_row("SELECT * FROM $table WHERE id=$id AND deleted=0", ARRAY_A);
        if (!$r) continue;

        $r['data'] = json_decode($r['data'], true);
        $sendRows[] = $r;

        $wpdb->update($table, ['status'=>'sending'], ['id'=>$id]);
    }

    // Send to n8n
    wp_remote_post("https://n8n.ngcs.co.il/webhook/excel-upload", [
        'timeout'=>20,
        'headers'=>['Content-Type'=>'application/json'],
        'body'=>json_encode([
            'batch_id'=>$batch_id,
            'rows'=>$sendRows
        ])
    ]);

    return ['success'=>true, 'count'=>count($sendRows)];
}

/* ============================================================
    8. RENAME BATCH
   ============================================================ */
add_action('rest_api_init', function() {
    register_rest_route('ngcs/v1', '/rename-batch', [
        'methods'=>'POST',
        'callback'=>'ngcs_rename_batch',
        'permission_callback'=>'__return_true'
    ]);
});

function ngcs_rename_batch(WP_REST_Request $req) {
    global $wpdb;

    $batch_id = intval($req->get_param('batch_id'));
    $name     = sanitize_text_field($req->get_param('name'));

    $wpdb->update(
        $wpdb->prefix."ngcs_batches",
        ['name'=>$name],
        ['id'=>$batch_id]
    );

    return ['success'=>true];
}

/* ============================================================
    9. BATCH LIST
   ============================================================ */
add_action('rest_api_init', function() {
    register_rest_route('ngcs/v1', '/batches', [
        'methods'=>'GET',
        'callback'=>'ngcs_get_batches',
        'permission_callback'=>'__return_true'
    ]);
});

function ngcs_get_batches(WP_REST_Request $req) {
    global $wpdb;

    $business_id = intval($req->get_param('business_id'));

    $rows = $wpdb->get_results("
        SELECT * FROM {$wpdb->prefix}ngcs_batches
        WHERE business_id=$business_id AND deleted=0
        ORDER BY created_at DESC
    ", ARRAY_A);

    return ['success'=>true, 'batches'=>$rows];
}

/* ============================================================
    10. ROW STATUS UPDATE FROM N8N
   ============================================================ */

function ngcs_update_row_status_from_n8n($payload) {
    global $wpdb;

    if (!isset($payload['row_id']) || !isset($payload['status'])) return;

    $row_id = intval($payload['row_id']);
    $status = sanitize_text_field($payload['status']);
    $msgid  = sanitize_text_field($payload['message_id'] ?? '');
    $error  = sanitize_text_field($payload['error'] ?? '');

    $allowed = [
        'sent','delivered','read',
        'failed','invalid','duplicate'
    ];

    if (!in_array($status, $allowed)) return;

    $wpdb->update(
        $wpdb->prefix."ngcs_batch_rows",
        [
            'status'=>$status,
            'whatsapp_message_id'=>$msgid,
            'error_message'=>$error
        ],
        ['id'=>$row_id]
    );
}

