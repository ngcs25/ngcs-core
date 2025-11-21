<?php
/**
 * Plugin Name: NGCS Core
 * Description: Main core engine for NGCS platform (Excel Messaging Engine, WA automation, n8n integration)
 * Version: 1.0.0
 * Author: NGCS
 */
 
 

/*
Plugin Name: NGCS Core
Plugin URI: https://github.com/ngcs25/ngcs-core
GitHub Plugin URI: https://github.com/ngcs25/ngcs-core
Description: NGCS Core engine
Version: 1.0.2
Author: Nabeel Gadoun
*/

if (!defined('ABSPATH')) exit;

/* ============================================================
   LOAD MODULES
   ============================================================ */

$modules = [
    '/modules/module-excel.php',
    '/modules/module-batches.php',
    '/modules/module-wa-templates.php',
    '/modules/module-wa-onboard-button.php',
  //  '/modules/module-wa-onboard-callback.php',
];

foreach ($modules as $m) {
    $path = __DIR__ . $m;
    if (file_exists($path)) require_once $path;
}

/* ============================================================
   ENQUEUE ASSETS
   ============================================================ */

add_action('wp_enqueue_scripts', 'ngcs_enqueue_dashboard_assets');

function ngcs_enqueue_dashboard_assets() {

    if (!is_singular()) return;

    global $post;
    if (!$post) return;

    if (strpos($post->post_content, '[ngcs_app]') !== false ||
        strpos($post->post_content, '[ngcs_connect_whatsapp]') !== false) {

        wp_localize_script('ngcs-dashboard-js', 'NGCS_AJAX', [
            'rest_url' => site_url('/wp-json/')
        ]);

        wp_enqueue_script(
            'ngcs-dashboard-js',
            plugins_url('assets/js/ngcs-dashboard.js', __FILE__),
            ['jquery'],
            time(),
            true
        );

        wp_enqueue_style(
            'ngcs-dashboard-css',
            plugins_url('assets/css/ngcs-dashboard.css', __FILE__),
            [],
            time()
        );
    }
}

/* ============================================================
   SHORTCODE: [ngcs_app]
   ============================================================ */

add_shortcode('ngcs_app', function () {
    ob_start(); ?>

    <div id="ngcs-app" data-user-id="<?php echo get_current_user_id(); ?>">
        <div id="ngcs-batch-list" style="margin-bottom:20px;"></div>

        <div id="ngcs-stats-bar" class="ngcs-stats-bar">
            <div class="ngcs-stat-item"><span class="ngcs-stat-icon">🔢</span><span>Total</span><strong id="ngcs-stat-total">0</strong></div>
            <div class="ngcs-stat-item"><span class="ngcs-stat-icon">📤</span><span>Sent</span><strong id="ngcs-stat-sent">0</strong></div>
            <div class="ngcs-stat-item"><span class="ngcs-stat-icon">📥</span><span>Delivered</span><strong id="ngcs-stat-delivered">0</strong></div>
            <div class="ngcs-stat-item"><span class="ngcs-stat-icon">👁</span><span>Read</span><strong id="ngcs-stat-read">0</strong></div>
            <div class="ngcs-stat-item"><span class="ngcs-stat-icon">❌</span><span>Failed</span><strong id="ngcs-stat-failed">0</strong></div>
            <div class="ngcs-stat-item"><span class="ngcs-stat-icon">⚠</span><span>Invalid</span><strong id="ngcs-stat-invalid">0</strong></div>
            <div class="ngcs-stat-item"><span class="ngcs-stat-icon">🔁</span><span>Duplicate</span><strong id="ngcs-stat-duplicate">0</strong></div>
            <div class="ngcs-stat-item"><span class="ngcs-stat-icon">🟧</span><span>Not Sent</span><strong id="ngcs-stat-not-sent">0</strong></div>
            <div class="ngcs-stat-item"><span class="ngcs-stat-icon">✔</span><span>Selected</span><strong id="ngcs-stat-selected">0</strong></div>
        </div>

        <div id="ngcs-table-controls" class="ngcs-table-controls">
            <button id="ngcs-select-all" class="ngcs-btn">Select All</button>
            <button id="ngcs-remove-all" class="ngcs-btn">Remove All</button>
            <button id="ngcs-delete-selected" class="ngcs-btn-danger">Delete Selected</button>
            <button id="ngcs-delete-batch" class="ngcs-btn-danger">Delete Batch</button>
            <button id="ngcs-send-selected" class="ngcs-btn-primary">Send Selected</button>

            <div class="ngcs-paging-control">
                <label>Rows per page:</label>
                <select id="ngcs-rows-per-page">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>

        <div id="ngcs-excel-table-wrapper">
            <table class="ngcs-table">
                <thead id="ngcs-table-head"></thead>
                <tbody id="ngcs-table-body"></tbody>
            </table>
        </div>

        <div id="ngcs-pagination"></div>
    </div>

    <?php return ob_get_clean();
});
?>

