<?php
/**
 * Order-side integration: booking sync, admin & email display, list columns.
 *
 * @package DeliveryDatePicker
 */

namespace DDP;

defined( 'ABSPATH' ) || exit;

use function DDP\ddp_format_date;
use function DDP\ddp_validate_date;

/**
 * Connects orders to the bookings table and renders delivery info everywhere.
 */
class Order {

	/**
	 * Order statuses that release (cancel) a booking's capacity.
	 *
	 * @var string[]
	 */
	private $releasing_statuses = array( 'cancelled', 'refunded', 'failed', 'trash' );

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		// Create / refresh booking row after an order is placed.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'sync_booking' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'sync_booking' ), 20, 1 );

		// Keep booking capacity in sync with order status.
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_change' ), 10, 4 );

		// Admin order screen display.
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'admin_order_display' ), 10, 1 );

		// Customer-facing (order received + My Account view + emails).
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'customer_display' ), 10, 1 );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'email_display' ), 10, 4 );

		// Order list column — HPOS.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_order_column' ), 20 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_order_column_hpos' ), 20, 2 );

		// Order list column — legacy posts table.
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_order_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_order_column_legacy' ), 20, 2 );
	}

	/**
	 * Create or update the booking row from order meta.
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public function sync_booking( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$date = ddp_validate_date( (string) $order->get_meta( '_ddp_delivery_date' ) );
		if ( ! $date ) {
			return;
		}

		$slot_id    = (int) $order->get_meta( '_ddp_slot_id' );
		$slot_label = (string) $order->get_meta( '_ddp_slot_label' );

		global $wpdb;
		$table = Installer::table( 'bookings' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE order_id = %d", $order_id ) );

		$row = array(
			'order_id'      => (int) $order_id,
			'delivery_date' => $date,
			'slot_id'       => $slot_id,
			'slot_label'    => $slot_label,
			'status'        => 'active',
		);

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				$row,
				array( 'id' => (int) $existing ),
				array( '%d', '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$row['created_at'] = current_time( 'mysql' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$table,
				$row,
				array( '%d', '%s', '%d', '%s', '%s', '%s' )
			);
		}

		Cache::flush_all();
	}

	/**
	 * Update booking status when the order status changes.
	 *
	 * @param int    $order_id   Order id.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 * @param object $order      Order object.
	 * @return void
	 */
	public function on_status_change( $order_id, $old_status, $new_status, $order ) {
		unset( $old_status, $order );

		global $wpdb;
		$table = Installer::table( 'bookings' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE order_id = %d", $order_id ) );
		if ( ! $exists ) {
			return;
		}

		if ( in_array( $new_status, $this->releasing_statuses, true ) ) {
			$status = 'cancelled';
		} elseif ( 'completed' === $new_status ) {
			$status = 'completed';
		} else {
			$status = 'active';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$table,
			array( 'status' => $status ),
			array( 'order_id' => (int) $order_id ),
			array( '%s' ),
			array( '%d' )
		);

		Cache::flush_all();
	}

	/**
	 * Build a single human-readable delivery line from an order.
	 *
	 * @param \WC_Order $order Order.
	 * @return string Escaped-safe plain text (caller escapes).
	 */
	private function delivery_summary( $order ) {
		$date = ddp_validate_date( (string) $order->get_meta( '_ddp_delivery_date' ) );
		if ( ! $date ) {
			return '';
		}
		$pretty = ddp_format_date( $date );
		$slot   = (string) $order->get_meta( '_ddp_slot_label' );
		if ( '' !== $slot ) {
			return $pretty . ' · ' . $slot;
		}
		return $pretty;
	}

	/**
	 * Admin order screen panel.
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	public function admin_order_display( $order ) {
		$summary = $this->delivery_summary( $order );
		if ( '' === $summary ) {
			return;
		}
		$note = (string) $order->get_meta( '_ddp_delivery_note' );
		?>
		<div class="ddp-admin-order-box">
			<h3><?php esc_html_e( 'Delivery Schedule', 'delivery-date-picker' ); ?></h3>
			<p><strong><?php esc_html_e( 'When:', 'delivery-date-picker' ); ?></strong> <?php echo esc_html( $summary ); ?></p>
			<?php if ( '' !== $note ) : ?>
				<p><strong><?php esc_html_e( 'Instructions:', 'delivery-date-picker' ); ?></strong> <?php echo esc_html( $note ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Customer-facing order details (thank-you page, My Account).
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	public function customer_display( $order ) {
		$summary = $this->delivery_summary( $order );
		if ( '' === $summary ) {
			return;
		}
		?>
		<section class="ddp-order-delivery">
			<h2><?php esc_html_e( 'Delivery Details', 'delivery-date-picker' ); ?></h2>
			<p><?php echo esc_html( $summary ); ?></p>
		</section>
		<?php
	}

	/**
	 * Email display.
	 *
	 * @param \WC_Order $order         Order.
	 * @param bool      $sent_to_admin Sent to admin flag.
	 * @param bool      $plain_text    Plain text flag.
	 * @param object    $email         Email object.
	 * @return void
	 */
	public function email_display( $order, $sent_to_admin, $plain_text, $email ) {
		unset( $sent_to_admin, $email );

		$summary = $this->delivery_summary( $order );
		if ( '' === $summary ) {
			return;
		}

		if ( $plain_text ) {
			echo "\n" . esc_html__( 'Delivery Details', 'delivery-date-picker' ) . "\n";
			echo esc_html( $summary ) . "\n";
			return;
		}
		?>
		<h2><?php esc_html_e( 'Delivery Details', 'delivery-date-picker' ); ?></h2>
		<p style="margin:0 0 16px;"><?php echo esc_html( $summary ); ?></p>
		<?php
	}

	/**
	 * Add the "Delivery" order list column.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_order_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$new['ddp_delivery'] = __( 'Delivery', 'delivery-date-picker' );
			}
		}
		if ( ! isset( $new['ddp_delivery'] ) ) {
			$new['ddp_delivery'] = __( 'Delivery', 'delivery-date-picker' );
		}
		return $new;
	}

	/**
	 * Render the column for HPOS order tables.
	 *
	 * @param string $column Column key.
	 * @param object $order  Order.
	 * @return void
	 */
	public function render_order_column_hpos( $column, $order ) {
		if ( 'ddp_delivery' !== $column ) {
			return;
		}
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order );
		}
		if ( $order ) {
			$this->print_column_value( $order );
		}
	}

	/**
	 * Render the column for the legacy posts table.
	 *
	 * @param string $column   Column key.
	 * @param int    $post_id  Post (order) id.
	 * @return void
	 */
	public function render_order_column_legacy( $column, $post_id ) {
		if ( 'ddp_delivery' !== $column ) {
			return;
		}
		$order = wc_get_order( $post_id );
		if ( $order ) {
			$this->print_column_value( $order );
		}
	}

	/**
	 * Output the delivery cell value.
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	private function print_column_value( $order ) {
		$date = ddp_validate_date( (string) $order->get_meta( '_ddp_delivery_date' ) );
		if ( ! $date ) {
			echo '<span class="ddp-col-empty" aria-hidden="true">&mdash;</span>';
			return;
		}
		$slot = (string) $order->get_meta( '_ddp_slot_label' );
		echo '<span class="ddp-col-date">' . esc_html( ddp_format_date( $date ) ) . '</span>';
		if ( '' !== $slot ) {
			echo '<br><small class="ddp-col-slot">' . esc_html( $slot ) . '</small>';
		}
	}
}
