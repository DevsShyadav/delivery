<?php
/**
 * Plugin Name:       Delivery Date Picker for WooCommerce
 * Plugin URI:        https://example.com/delivery-date-picker
 * Description:        Let customers choose exactly when their order is delivered. Premium calendar at checkout, time slots, capacity limits, holidays, and a beautiful delivery schedule dashboard.
 * Version:           1.0.0
 * Author:            Delivery Date Picker
 * Author URI:        https://example.com
 * Text Domain:       delivery-date-picker
 * Domain Path:       /languages
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * WC tested up to:   9.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package DeliveryDatePicker
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------- */
define( 'DDP_VERSION', '1.0.0' );
define( 'DDP_DB_VERSION', '1.0.0' );
define( 'DDP_PLUGIN_FILE', __FILE__ );
define( 'DDP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DDP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DDP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'DDP_SETTINGS_KEY', 'ddp_settings' );
define( 'DDP_REST_NAMESPACE', 'ddp/v1' );

/* -------------------------------------------------------------------------
 * Autoloader (PSR-ish: DDP\Some_Class -> includes/class-some-class.php)
 * ---------------------------------------------------------------------- */
spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'DDP\\' ) ) {
			return;
		}

		$relative = substr( $class, strlen( 'DDP\\' ) );
		$relative = strtolower( str_replace( array( '_', '\\' ), array( '-', '/' ), $relative ) );
		$file     = DDP_PLUGIN_DIR . 'includes/class-' . $relative . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

// Procedural helpers (not class-based).
require_once DDP_PLUGIN_DIR . 'includes/helpers.php';

/* -------------------------------------------------------------------------
 * Activation / Deactivation
 * ---------------------------------------------------------------------- */
register_activation_hook(
	__FILE__,
	static function () {
		\DDP\Installer::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		\DDP\Installer::deactivate();
	}
);

/* -------------------------------------------------------------------------
 * Declare WooCommerce feature compatibility (HPOS + Cart/Checkout Blocks)
 * ---------------------------------------------------------------------- */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DDP_PLUGIN_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', DDP_PLUGIN_FILE, true );
	}
);

/* -------------------------------------------------------------------------
 * Boot
 * ---------------------------------------------------------------------- */
add_action(
	'plugins_loaded',
	static function () {
		// WooCommerce is required.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'Delivery Date Picker requires WooCommerce to be installed and active.', 'delivery-date-picker' );
					echo '</p></div>';
				}
			);
			return;
		}

		\DDP\Plugin::instance()->init();
	}
);
