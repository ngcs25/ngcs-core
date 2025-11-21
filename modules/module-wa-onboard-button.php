<?php
/**
 * NGCS – WhatsApp Onboarding Button
 * Generates the button that opens Meta's Embedded Signup for Technical Providers.
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   SHORTCODE: [ngcs_connect_whatsapp]
   ============================================================ */

add_shortcode('ngcs_connect_whatsapp', 'ngcs_connect_whatsapp_shortcode');

function ngcs_connect_whatsapp_shortcode() {

    $business_id = 1;
    $user_id = get_current_user_id();

    $app_id = '735212935829881';
    $configuration_id = '1360676899183842';

    $redirect_uri = urlencode('https://ngcs.co.il/wp-json/ngcs/v1/wa-onboard-callback');
    $state = $business_id . ':' . $user_id;

    // FINAL Meta onboarding URL (2025-compliant)
    $url =
        "https://www.facebook.com/v20.0/dialog/whatsapp_business_phone_number_setup?" .
        "app_id={$app_id}" .
        "&redirect_uri={$redirect_uri}" .
        "&configuration_id={$configuration_id}" .   // REQUIRED
        "&setup_type=embedded-signup" .             // REQUIRED
        "&state={$state}";

    $html = "
        <button class='ngcs-btn-primary' id='ngcs-wa-btn'>
            Connect WhatsApp
        </button>

        <script>
            document.getElementById('ngcs-wa-btn').addEventListener('click', function (e) {
                e.preventDefault();
                window.open(
                    '{$url}',
                    'waOnboard',
                    'width=720,height=740,toolbar=0,menubar=0,scrollbars=1,resizable=1'
                );
            });
        </script>
    ";

    return $html;
}


    <?php
    return ob_get_clean();
}
