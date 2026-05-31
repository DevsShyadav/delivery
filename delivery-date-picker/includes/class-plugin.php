<?php
/**
 * Core plugin bootstrap (singleton).
 *
 * @package DeliveryDatePicker
 */

namespace DDP;

defined( 'ABSPATH' ) || exit;

/**
 * Wires every component together and registers global hooks.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Component registry.
	 *
	 * @var array<string,object>
	 */
	private $components = array();

	/**
	 * Whether init() has run.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Private constructor (use instance()).
	 */
	private function __construct() {}

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot the plugin.
	 *
	 * @return void
	 */
	public function init() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( Installer::class, 'maybe_upgrade' ) );

		// Register components (each is single-responsibility).
		$this->components['settings'] = new Settings();
		$this->components['checkout'] = new Checkout();
		$this->components['order']    = new Order();
		$this->components['rest']     = new Rest_Controller();

		foreach ( $this->components as $component ) {
			if ( method_exists( $component, 'register' ) ) {
				$component->register();
			}
		}

		// Admin-only components.
		if ( is_admin() ) {
			$this->components['admin'] = new Admin();
			$this->components['ajax']  = new Ajax();
			$this->components['admin']->register();
			$this->components['ajax']->register();
		}

		// Plugin action links.
		add_filter( 'plugin_action_links_' . DDP_PLUGIN_BASENAME, array( $this, 'action_links' ) );

		do_action( 'ddp_loaded', $this );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'delivery-date-picker', false, dirname( DDP_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Add quick settings link on the plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url      = admin_url( 'admin.php?page=ddp-settings' );
		$settings = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'delivery-date-picker' ) . '</a>';
		array_unshift( $links, $settings );
		return $links;
	}

	/**
	 * Fetch a registered component.
	 *
	 * @param string $key Component key.
	 * @return object|null
	 */
	public function get( $key ) {
		return isset( $this->components[ $key ] ) ? $this->components[ $key ] : null;
	}
}
