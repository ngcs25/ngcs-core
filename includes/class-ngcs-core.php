<?php
if (!defined('ABSPATH')) exit;

class NGCS_Core {

    public function __construct() {
        add_action('init', [$this, 'register_shortcodes']);

        // Load REST API routes (THIS WAS MISSING)
        add_action('rest_api_init', [$this, 'load_rest_api']);

        // Load JS/CSS
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /*
    |-------------------------------------------------------------------
    | SHORTCODE
    |-------------------------------------------------------------------
    */
    public function register_shortcodes() {
        add_shortcode('ngcs_dashboard', [$this, 'render_dashboard']);
    }

    public function render_dashboard() {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to view the NGCS Dashboard.</p>';
        }

        ob_start(); ?>
        
        <div id="ngcs-app" data-user-id="<?php echo get_current_user_id(); ?>">
            <div class="ngcs-loading">Loading NGCS Dashboard…</div>
        </div>

        <?php
        return ob_get_clean();
    }

    /*
    |-------------------------------------------------------------------
    | LOAD REST API (REQUIRED!)
    |-------------------------------------------------------------------
    */
    public function load_rest_api() {
        require_once NGCS_CORE_PATH . 'includes/rest-api.php';
    }

    /*
    |-------------------------------------------------------------------
    | ASSETS
    |-------------------------------------------------------------------
    */
    public function enqueue_assets() {
        global $post;

        // Only load on pages that contain the shortcode
        if (!is_singular() || empty($post)) return;
        if (strpos($post->post_content, '[ngcs_dashboard]') === false) return;

        // CSS
        wp_enqueue_style(
            'ngcs-dashboard-css',
            NGCS_CORE_URL . 'assets/css/ngcs-dashboard.css',
            [],
            NGCS_CORE_VERSION
        );

        // JS
        wp_enqueue_script(
            'ngcs-dashboard',
            NGCS_CORE_URL . 'assets/js/ngcs-dashboard.js',
            ['jquery'],
            NGCS_CORE_VERSION . '_' . time(),
            true
        );

        // Attach REST data to JS
        wp_localize_script('ngcs-dashboard', 'ngcsDashboard', [
            'restUrl' => rest_url('ngcs/v1/'),
            'nonce'   => wp_create_nonce('wp_rest')
        ]);
    }
}
