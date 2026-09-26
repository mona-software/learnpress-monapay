<?php
/**
 * Plugin Name:       MONA Pay for LearnPress
 * Plugin URI:        https://monapay.vn/
 * Description:       Automatic bank transfer confirmation for LearnPress with dynamic VietQR, virtual accounts, and signed webhooks.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  learnpress
 * Author:            The MONA Group
 * Author URI:        https://mona.software/
 * License:           MIT
 * License URI:       https://opensource.org/license/mit
 * Text Domain:       learnpress-monapay
 * Domain Path:       /languages
 *
 * @package LearnPress_MONAPay
 */

defined( 'ABSPATH' ) || exit;

define( 'LEARNPRESS_MONAPAY_VERSION', '1.0.0' );
define( 'LEARNPRESS_MONAPAY_FILE', __FILE__ );
define( 'LEARNPRESS_MONAPAY_PATH', plugin_dir_path( __FILE__ ) );
define( 'LEARNPRESS_MONAPAY_URL', plugin_dir_url( __FILE__ ) );

/** Show a dependency notice when LearnPress is unavailable. */
function learnpress_monapay_missing_learnpress_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	?>
	<div class="notice notice-error"><p><?php esc_html_e( 'MONA Pay for LearnPress requires LearnPress to be installed and active.', 'learnpress-monapay' ); ?></p></div>
	<?php
}

/** Load the integration only after all plugins are available. */
function learnpress_monapay_init() {
	load_plugin_textdomain( 'learnpress-monapay', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	if ( ! class_exists( 'LP_Gateway_Abstract' ) ) {
		add_action( 'admin_notices', 'learnpress_monapay_missing_learnpress_notice' );
		return;
	}

	require_once LEARNPRESS_MONAPAY_PATH . 'vendor/autoload.php';
	require_once LEARNPRESS_MONAPAY_PATH . 'includes/functions.php';
	require_once LEARNPRESS_MONAPAY_PATH . 'includes/class-learnpress-monapay-client.php';
	require_once LEARNPRESS_MONAPAY_PATH . 'includes/class-learnpress-monapay-gateway.php';
	require_once LEARNPRESS_MONAPAY_PATH . 'includes/class-learnpress-monapay-webhook.php';

	$GLOBALS['learnpress_monapay_webhook'] = new LearnPress_MONAPay_Webhook();
}
add_action( 'plugins_loaded', 'learnpress_monapay_init', 20 );

/**
 * Register the MONA Pay gateway with LearnPress.
 *
 * @param array $gateways Existing gateway map.
 * @return array
 */
function learnpress_monapay_register_gateway( $gateways ) {
	$gateways['monapay'] = 'LearnPress_MONAPay_Gateway';
	return $gateways;
}
add_filter( 'learn-press/payment-methods', 'learnpress_monapay_register_gateway' );

