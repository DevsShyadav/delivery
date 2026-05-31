<?php
/**
 * Frontend checkout integration (classic checkout).
 *
 * @package DeliveryDatePicker
 */

namespace DDP;

defined( 'ABSPATH' ) || exit;

use function DDP\ddp_validate_date;

/**
 * Renders the picker, validates and persists the customer's choice.
 */
class Checkout {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! (int) Settings::get( 'enabled', 1 ) ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		$placement = (string) Settings::get( 'placement', 'after_order_notes' );
		$hook      = $this->placement_hook( $placement );
		add_action( $hook, array( $this, 'render_field' ) );

		add_action( 'woocommerce_checkout_process', array( $this, 'validate' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_to_order' ), 10, 2 );
	}

	/**
	 * Map a placement setting to a WooCommerce hook.
	 *
	 * @param string $placement Placement key.
	 * @return string
	 */
	private function placement_hook( $placement ) {
		switch ( $placement ) {
			case 'before_payment':
				return 'woocommerce_review_order_before_payment';
			case 'review_order':
				return 'woocommerce_checkout_before_order_review';
			case 'after_order_notes':
			default:
				return 'woocommerce_after_order_notes';
		}
	}

	/**
	 * Enqueue checkout assets only on the checkout page.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}

		wp_enqueue_style(
			'ddp-checkout',
			DDP_PLUGIN_URL . 'assets/css/checkout.css',
			array(),
			DDP_VERSION
		);

		wp_register_script(
			'ddp-calendar',
			DDP_PLUGIN_URL . 'assets/js/ddp-calendar.js',
			array(),
			DDP_VERSION,
			true
		);

		wp_enqueue_script(
			'ddp-checkout',
			DDP_PLUGIN_URL . 'assets/js/checkout.js',
			array( 'ddp-calendar' ),
			DDP_VERSION,
			true
		);

		$weekdays = array();
		foreach ( (array) Settings::get( 'disabled_weekdays', array() ) as $d ) {
			$weekdays[] = (int) $d;
		}

		wp_localize_script(
			'ddp-checkout',
			'DDPCheckout',
			array(
				'restUrl'      => esc_url_raw( rest_url( DDP_REST_NAMESPACE ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'minDate'      => Availability::min_date(),
				'maxDate'      => Availability::max_date(),
				'firstDay'     => (int) Settings::get( 'first_day', 1 ),
				'disabledDays' => $weekdays,
				'enableSlots'  => (int) Settings::get( 'enable_time_slots', 1 ),
				'slotRequired' => (int) Settings::get( 'slot_required', 1 ),
				'required'     => (int) Settings::get( 'required', 1 ),
				'theme'        => (string) Settings::get( 'theme', 'auto' ),
				'accent'       => (string) Settings::get( 'accent_color', '#6366f1' ),
				'i18n'         => array(
					'choose'      => __( 'Select a delivery date', 'delivery-date-picker' ),
					'selected'    => __( 'Delivery date', 'delivery-date-picker' ),
					'slotsTitle'  => __( 'Choose a time slot', 'delivery-date-picker' ),
					'noSlots'     => __( 'No time slots available for this date.', 'delivery-date-picker' ),
					'loading'     => __( 'Loading…', 'delivery-date-picker' ),
					'full'        => __( 'Full', 'delivery-date-picker' ),
					'left'        => __( 'left', 'delivery-date-picker' ),
					'unlimited'   => __( 'Available', 'delivery-date-picker' ),
					'months'      => $this->month_names(),
					'weekdays'    => $this->weekday_initials(),
					'prev'        => __( 'Previous month', 'delivery-date-picker' ),
					'next'        => __( 'Next month', 'delivery-date-picker' ),
				),
			)
		);
	}

	/**
	 * Render the checkout field block.
	 *
	 * @return void
	 */
	public function render_field() {
		$label       = (string) Settings::get( 'field_label', __( 'Delivery Date', 'delivery-date-picker' ) );
		$description = (string) Settings::get( 'field_description', '' );
		$required    = (int) Settings::get( 'required', 1 );
		$note_on     = (int) Settings::get( 'enable_note', 1 );
		$note_label  = (string) Settings::get( 'note_label', __( 'Delivery Instructions', 'delivery-date-picker' ) );

		// Preserve previously entered values (e.g. on a validation failure reload).
		$saved_date = isset( $_POST['ddp_delivery_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ddp_delivery_date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$saved_slot = isset( $_POST['ddp_slot_id'] ) ? absint( wp_unslash( $_POST['ddp_slot_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$saved_note = isset( $_POST['ddp_delivery_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ddp_delivery_note'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$theme = (string) Settings::get( 'theme', 'auto' );
		?>
		<div id="ddp-checkout-field" class="ddp-checkout-field ddp-theme-<?php echo esc_attr( $theme ); ?>" data-required="<?php echo esc_attr( (string) $required ); ?>">
			<h3 class="ddp-field-title">
				<span class="ddp-field-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
				</span>
				<span><?php echo esc_html( $label ); ?><?php echo $required ? ' <abbr class="required" title="' . esc_attr__( 'required', 'delivery-date-picker' ) . '">*</abbr>' : ''; ?></span>
			</h3>
			<?php if ( '' !== $description ) : ?>
				<p class="ddp-field-description"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>

			<div class="ddp-card">
				<div class="ddp-summary" id="ddp-summary" hidden>
					<span class="ddp-summary-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
					</span>
					<span class="ddp-summary-text" id="ddp-summary-text"></span>
					<button type="button" class="ddp-change" id="ddp-change-btn"><?php esc_html_e( 'Change', 'delivery-date-picker' ); ?></button>
				</div>

				<div class="ddp-calendar-wrap" id="ddp-calendar"></div>

				<?php if ( Settings::get( 'enable_time_slots' ) ) : ?>
					<div class="ddp-slots" id="ddp-slots" hidden>
						<h4 class="ddp-slots-title"><?php esc_html_e( 'Choose a time slot', 'delivery-date-picker' ); ?></h4>
						<div class="ddp-slots-grid" id="ddp-slots-grid"></div>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $note_on ) : ?>
				<p class="ddp-note-field form-row form-row-wide">
					<label for="ddp_delivery_note"><?php echo esc_html( $note_label ); ?></label>
					<textarea name="ddp_delivery_note" id="ddp_delivery_note" class="input-text" rows="2" placeholder="<?php esc_attr_e( 'e.g. Leave with the concierge, ring the bell…', 'delivery-date-picker' ); ?>"><?php echo esc_textarea( $saved_note ); ?></textarea>
				</p>
			<?php endif; ?>

			<input type="hidden" name="ddp_delivery_date" id="ddp_delivery_date" value="<?php echo esc_attr( $saved_date ); ?>" />
			<input type="hidden" name="ddp_slot_id" id="ddp_slot_id" value="<?php echo esc_attr( (string) $saved_slot ); ?>" />
			<div class="ddp-error" id="ddp-error" role="alert" hidden></div>
		</div>
		<?php
	}

	/**
	 * Validate the submitted delivery selection during checkout.
	 *
	 * @return void
	 */
	public function validate() {
		// WooCommerce verifies its own checkout nonce before this hook runs.
		$required = (int) Settings::get( 'required', 1 );
		$raw_date = isset( $_POST['ddp_delivery_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ddp_delivery_date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$slot_id  = isset( $_POST['ddp_slot_id'] ) ? absint( wp_unslash( $_POST['ddp_slot_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$label = (string) Settings::get( 'field_label', __( 'delivery date', 'delivery-date-picker' ) );

		if ( '' === $raw_date ) {
			if ( $required ) {
				wc_add_notice( sprintf( /* translators: %s field label */ __( 'Please choose a %s.', 'delivery-date-picker' ), $label ), 'error' );
			}
			return;
		}

		$date = ddp_validate_date( $raw_date );
		if ( ! $date ) {
			wc_add_notice( __( 'The delivery date you selected is not valid.', 'delivery-date-picker' ), 'error' );
			return;
		}

		$status = Availability::date_status( $date );
		if ( ! $status['available'] ) {
			wc_add_notice( $status['reason'] ? $status['reason'] : __( 'The selected delivery date is unavailable. Please choose another.', 'delivery-date-picker' ), 'error' );
			return;
		}

		// Time slot validation.
		if ( Settings::get( 'enable_time_slots' ) ) {
			$slot_required = (int) Settings::get( 'slot_required', 1 );
			if ( 0 === $slot_id ) {
				if ( $slot_required ) {
					wc_add_notice( __( 'Please choose a delivery time slot.', 'delivery-date-picker' ), 'error' );
				}
				return;
			}
			$slot = Availability::validate_slot( $slot_id, $date );
			if ( ! $slot['valid'] ) {
				wc_add_notice( $slot['reason'], 'error' );
			}
		}
	}

	/**
	 * Persist the selection onto the order (HPOS-safe CRUD).
	 *
	 * @param \WC_Order $order Order object.
	 * @param array     $data  Posted checkout data.
	 * @return void
	 */
	public function save_to_order( $order, $data ) {
		unset( $data );

		$raw_date = isset( $_POST['ddp_delivery_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ddp_delivery_date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$date     = ddp_validate_date( $raw_date );
		if ( ! $date ) {
			return;
		}

		$slot_id    = isset( $_POST['ddp_slot_id'] ) ? absint( wp_unslash( $_POST['ddp_slot_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$note       = isset( $_POST['ddp_delivery_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ddp_delivery_note'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$slot_label = '';

		if ( $slot_id > 0 && Settings::get( 'enable_time_slots' ) ) {
			$slot       = Availability::validate_slot( $slot_id, $date );
			$slot_label = $slot['label'];
		}

		$order->update_meta_data( '_ddp_delivery_date', $date );
		$order->update_meta_data( '_ddp_slot_id', $slot_id );
		$order->update_meta_data( '_ddp_slot_label', $slot_label );
		if ( '' !== $note ) {
			$order->update_meta_data( '_ddp_delivery_note', $note );
		}
	}

	/**
	 * Localised month names.
	 *
	 * @return array
	 */
	private function month_names() {
		global $wp_locale;
		$months = array();
		if ( $wp_locale && ! empty( $wp_locale->month ) ) {
			foreach ( $wp_locale->month as $m ) {
				$months[] = $m;
			}
			return $months;
		}
		return array( 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' );
	}

	/**
	 * Localised weekday initials.
	 *
	 * @return array
	 */
	private function weekday_initials() {
		global $wp_locale;
		$days = array();
		if ( $wp_locale && ! empty( $wp_locale->weekday_initial ) ) {
			foreach ( $wp_locale->weekday as $i => $full ) {
				$days[] = $wp_locale->weekday_initial[ $full ];
			}
			if ( count( $days ) === 7 ) {
				return $days;
			}
		}
		return array( 'S', 'M', 'T', 'W', 'T', 'F', 'S' );
	}
}
