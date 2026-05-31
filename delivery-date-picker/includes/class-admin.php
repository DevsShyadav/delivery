<?php
/**
 * Admin area: menus, screens, asset loading and onboarding redirect.
 *
 * @package DeliveryDatePicker
 */

namespace DDP;

defined( 'ABSPATH' ) || exit;

use function DDP\ddp_admin_capability;

/**
 * Builds the premium admin experience.
 */
class Admin {

	/**
	 * Page hook suffixes for our screens.
	 *
	 * @var array<string,string>
	 */
	private $hooks = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_onboarding' ) );
	}

	/**
	 * Register the admin menu and subpages.
	 *
	 * @return void
	 */
	public function register_menu() {
		$cap = ddp_admin_capability();

		$this->hooks['dashboard'] = add_menu_page(
			__( 'Delivery Date Picker', 'delivery-date-picker' ),
			__( 'Delivery', 'delivery-date-picker' ),
			$cap,
			'ddp-dashboard',
			array( $this, 'render_dashboard' ),
			$this->menu_icon(),
			56
		);

		$this->hooks['dashboard'] = add_submenu_page(
			'ddp-dashboard',
			__( 'Dashboard', 'delivery-date-picker' ),
			__( 'Dashboard', 'delivery-date-picker' ),
			$cap,
			'ddp-dashboard',
			array( $this, 'render_dashboard' )
		);

		$this->hooks['schedule'] = add_submenu_page(
			'ddp-dashboard',
			__( 'Schedule', 'delivery-date-picker' ),
			__( 'Schedule', 'delivery-date-picker' ),
			$cap,
			'ddp-schedule',
			array( $this, 'render_schedule' )
		);

		$this->hooks['settings'] = add_submenu_page(
			'ddp-dashboard',
			__( 'Settings', 'delivery-date-picker' ),
			__( 'Settings', 'delivery-date-picker' ),
			$cap,
			'ddp-settings',
			array( $this, 'render_settings' )
		);

		// Onboarding page: registered under the parent then hidden from the menu
		// (avoids deprecation notices from passing an empty parent slug).
		$this->hooks['onboarding'] = add_submenu_page(
			'ddp-dashboard',
			__( 'Welcome', 'delivery-date-picker' ),
			__( 'Welcome', 'delivery-date-picker' ),
			$cap,
			'ddp-onboarding',
			array( $this, 'render_onboarding' )
		);
		remove_submenu_page( 'ddp-dashboard', 'ddp-onboarding' );
	}

	/**
	 * Redirect to the onboarding wizard on first run (once).
	 *
	 * Uses the ddp_onboarded flag: 0 = fresh install (redirect),
	 * 2 = wizard shown, 1 = completed.
	 *
	 * @return void
	 */
	public function maybe_redirect_onboarding() {
		if ( (int) get_option( 'ddp_onboarded', 1 ) !== 0 ) {
			return;
		}
		if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}
		if ( ! current_user_can( ddp_admin_capability() ) ) {
			return;
		}
		if ( isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'ddp-onboarding' === $page ) {
			return;
		}

		// Mark as "shown" so we never loop, then send to the wizard.
		update_option( 'ddp_onboarded', 2, false );
		wp_safe_redirect( admin_url( 'admin.php?page=ddp-onboarding' ) );
		exit;
	}

	/**
	 * Is the current screen one of ours?
	 *
	 * @param string $hook_suffix Current hook suffix.
	 * @return bool
	 */
	private function is_plugin_screen( $hook_suffix ) {
		return in_array( $hook_suffix, $this->hooks, true );
	}

	/**
	 * Enqueue admin assets on our screens only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		// Order screens also get a tiny bit of column styling.
		$is_order_screen = in_array( $hook_suffix, array( 'woocommerce_page_wc-orders', 'edit.php' ), true );

		if ( ! $this->is_plugin_screen( $hook_suffix ) && ! $is_order_screen ) {
			return;
		}

		wp_enqueue_style(
			'ddp-admin',
			DDP_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			DDP_VERSION
		);

		if ( ! $this->is_plugin_screen( $hook_suffix ) ) {
			return; // Order screens only need the stylesheet.
		}

		wp_register_script(
			'ddp-calendar',
			DDP_PLUGIN_URL . 'assets/js/ddp-calendar.js',
			array(),
			DDP_VERSION,
			true
		);

		wp_enqueue_script(
			'ddp-admin',
			DDP_PLUGIN_URL . 'assets/js/admin.js',
			array( 'ddp-calendar', 'wp-i18n' ),
			DDP_VERSION,
			true
		);

		wp_localize_script(
			'ddp-admin',
			'DDPAdmin',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'restUrl'      => esc_url_raw( rest_url( DDP_REST_NAMESPACE ) ),
				'nonce'        => wp_create_nonce( Ajax::NONCE_ACTION ),
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'settings'     => Settings::all(),
				'firstDay'     => (int) Settings::get( 'first_day', 1 ),
				'months'       => $this->month_names(),
				'weekdays'     => $this->weekday_initials(),
				'weekdayNames' => $this->weekday_names(),
				'i18n'         => array(
					'saved'        => __( 'Saved', 'delivery-date-picker' ),
					'saving'       => __( 'Saving…', 'delivery-date-picker' ),
					'error'        => __( 'Something went wrong. Please try again.', 'delivery-date-picker' ),
					'confirmSlot'  => __( 'Delete this time slot?', 'delivery-date-picker' ),
					'confirmHol'   => __( 'Remove this holiday?', 'delivery-date-picker' ),
					'noOrders'     => __( 'No deliveries scheduled for this day.', 'delivery-date-picker' ),
					'noSlots'      => __( 'No time slots yet. Click “Add slot” to create one.', 'delivery-date-picker' ),
					'noHolidays'   => __( 'No closed days added yet.', 'delivery-date-picker' ),
					'loading'      => __( 'Loading…', 'delivery-date-picker' ),
					'block'        => __( 'Block this day', 'delivery-date-picker' ),
					'unblock'      => __( 'Unblock this day', 'delivery-date-picker' ),
					'deliveries'   => __( 'deliveries', 'delivery-date-picker' ),
					'delivery'     => __( 'delivery', 'delivery-date-picker' ),
					'viewOrder'    => __( 'View order', 'delivery-date-picker' ),
					'items'        => __( 'items', 'delivery-date-picker' ),
					'continueBtn'  => __( 'Continue', 'delivery-date-picker' ),
					'finishBtn'    => __( 'Finish setup', 'delivery-date-picker' ),
				),
			)
		);
	}

	/**
	 * Render the dashboard view.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		$this->render_view( 'dashboard' );
	}

	/**
	 * Render the schedule view.
	 *
	 * @return void
	 */
	public function render_schedule() {
		$this->render_view( 'schedule' );
	}

	/**
	 * Render the settings view.
	 *
	 * @return void
	 */
	public function render_settings() {
		$this->render_view( 'settings' );
	}

	/**
	 * Render the onboarding view.
	 *
	 * @return void
	 */
	public function render_onboarding() {
		$this->render_view( 'onboarding' );
	}

	/**
	 * Load a view file with a capability check.
	 *
	 * @param string $view View slug.
	 * @return void
	 */
	private function render_view( $view ) {
		if ( ! current_user_can( ddp_admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'delivery-date-picker' ) );
		}
		$file = DDP_PLUGIN_DIR . 'admin/views/' . $view . '.php';
		if ( is_readable( $file ) ) {
			$settings = Settings::all();
			require $file;
		}
	}

	/**
	 * Base64 SVG menu icon.
	 *
	 * @return string
	 */
	private function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="black"><path d="M7 2a1 1 0 0 1 1 1v1h8V3a1 1 0 1 1 2 0v1h1a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3H5a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h1V3a1 1 0 0 1 1-1Zm13 8H4v9a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-9Zm-9.3 2.3 1.3 1.29 2.79-2.79a1 1 0 1 1 1.42 1.42l-3.5 3.5a1 1 0 0 1-1.42 0l-2-2a1 1 0 0 1 1.42-1.42Z"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Month names from locale.
	 *
	 * @return array
	 */
	private function month_names() {
		global $wp_locale;
		$out = array();
		if ( $wp_locale && ! empty( $wp_locale->month ) ) {
			foreach ( $wp_locale->month as $m ) {
				$out[] = $m;
			}
			return $out;
		}
		return array( 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' );
	}

	/**
	 * Weekday initials from locale.
	 *
	 * @return array
	 */
	private function weekday_initials() {
		global $wp_locale;
		$out = array();
		if ( $wp_locale && ! empty( $wp_locale->weekday_initial ) ) {
			foreach ( $wp_locale->weekday as $full ) {
				$out[] = $wp_locale->weekday_initial[ $full ];
			}
			if ( count( $out ) === 7 ) {
				return $out;
			}
		}
		return array( 'S', 'M', 'T', 'W', 'T', 'F', 'S' );
	}

	/**
	 * Full weekday names from locale.
	 *
	 * @return array
	 */
	private function weekday_names() {
		global $wp_locale;
		$out = array();
		if ( $wp_locale && ! empty( $wp_locale->weekday ) ) {
			foreach ( $wp_locale->weekday as $full ) {
				$out[] = $full;
			}
			if ( count( $out ) === 7 ) {
				return $out;
			}
		}
		return array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
	}
}
