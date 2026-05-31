<?php
/**
 * Admin settings view (tabbed).
 *
 * @package DeliveryDatePicker
 * @var array $settings Current settings.
 */

namespace DDP;

defined( 'ABSPATH' ) || exit;

global $wp_locale;
$weekday_names = array();
for ( $i = 0; $i < 7; $i++ ) {
	$weekday_names[ $i ] = $wp_locale ? $wp_locale->get_weekday( $i ) : gmdate( 'l', strtotime( "Sunday +{$i} days" ) );
}
$disabled = array_map( 'intval', (array) ( isset( $settings['disabled_weekdays'] ) ? $settings['disabled_weekdays'] : array() ) );

/**
 * Render a checkbox toggle.
 *
 * @param string $key      Setting key.
 * @param string $label    Label.
 * @param array  $settings Settings array.
 * @param string $hint     Optional hint.
 * @return void
 */
if ( ! function_exists( 'DDP\\ddp_toggle' ) ) :
	function ddp_toggle( $key, $label, $settings, $hint = '' ) {
	$checked = ! empty( $settings[ $key ] );
	?>
	<div class="ddp-field ddp-field-toggle">
		<label class="ddp-switch">
			<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $checked ); ?> data-ddp-field="<?php echo esc_attr( $key ); ?>" />
			<span class="ddp-switch-track"><span class="ddp-switch-thumb"></span></span>
		</label>
		<div class="ddp-field-text">
			<span class="ddp-field-label"><?php echo esc_html( $label ); ?></span>
			<?php if ( '' !== $hint ) : ?><span class="ddp-field-hint"><?php echo esc_html( $hint ); ?></span><?php endif; ?>
		</div>
	</div>
	<?php
	}
endif;
?>
<div class="wrap ddp-wrap ddp-app" data-ddp-theme="<?php echo esc_attr( isset( $settings['theme'] ) ? $settings['theme'] : 'auto' ); ?>">

	<header class="ddp-topbar">
		<div class="ddp-brand">
			<span class="ddp-brand-mark" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg>
			</span>
			<div>
				<h1><?php esc_html_e( 'Settings', 'delivery-date-picker' ); ?></h1>
				<p class="ddp-subtitle"><?php esc_html_e( 'Configure how customers choose their delivery date.', 'delivery-date-picker' ); ?></p>
			</div>
		</div>
		<div class="ddp-topbar-actions">
			<span class="ddp-save-status" id="ddp-save-status" aria-live="polite"></span>
			<button type="button" class="ddp-btn ddp-btn-primary" id="ddp-save-settings">
				<?php esc_html_e( 'Save changes', 'delivery-date-picker' ); ?>
			</button>
		</div>
	</header>

	<div class="ddp-tabs" role="tablist">
		<button class="ddp-tab is-active" data-tab="general" role="tab"><?php esc_html_e( 'General', 'delivery-date-picker' ); ?></button>
		<button class="ddp-tab" data-tab="availability" role="tab"><?php esc_html_e( 'Availability', 'delivery-date-picker' ); ?></button>
		<button class="ddp-tab" data-tab="slots" role="tab"><?php esc_html_e( 'Time Slots', 'delivery-date-picker' ); ?></button>
		<button class="ddp-tab" data-tab="holidays" role="tab"><?php esc_html_e( 'Holidays', 'delivery-date-picker' ); ?></button>
		<button class="ddp-tab" data-tab="appearance" role="tab"><?php esc_html_e( 'Appearance', 'delivery-date-picker' ); ?></button>
	</div>

	<form id="ddp-settings-form" class="ddp-settings-form" autocomplete="off">

		<!-- GENERAL -->
		<section class="ddp-tab-panel is-active" data-panel="general">
			<div class="ddp-panel ddp-glass">
				<h2 class="ddp-panel-title"><?php esc_html_e( 'Checkout field', 'delivery-date-picker' ); ?></h2>
				<?php ddp_toggle( 'enabled', __( 'Enable the delivery date picker on checkout', 'delivery-date-picker' ), $settings ); ?>
				<?php ddp_toggle( 'required', __( 'Make the delivery date required', 'delivery-date-picker' ), $settings ); ?>

				<div class="ddp-field">
					<label class="ddp-field-label" for="ddp-field_label"><?php esc_html_e( 'Field label', 'delivery-date-picker' ); ?></label>
					<input type="text" id="ddp-field_label" class="ddp-input" name="field_label" data-ddp-field="field_label" value="<?php echo esc_attr( $settings['field_label'] ); ?>" />
				</div>
				<div class="ddp-field">
					<label class="ddp-field-label" for="ddp-field_description"><?php esc_html_e( 'Field description', 'delivery-date-picker' ); ?></label>
					<input type="text" id="ddp-field_description" class="ddp-input" name="field_description" data-ddp-field="field_description" value="<?php echo esc_attr( $settings['field_description'] ); ?>" />
				</div>
				<div class="ddp-field">
					<label class="ddp-field-label" for="ddp-placement"><?php esc_html_e( 'Field placement', 'delivery-date-picker' ); ?></label>
					<select id="ddp-placement" class="ddp-input" name="placement" data-ddp-field="placement">
						<option value="after_order_notes" <?php selected( $settings['placement'], 'after_order_notes' ); ?>><?php esc_html_e( 'After order notes', 'delivery-date-picker' ); ?></option>
						<option value="before_payment" <?php selected( $settings['placement'], 'before_payment' ); ?>><?php esc_html_e( 'Before payment', 'delivery-date-picker' ); ?></option>
						<option value="review_order" <?php selected( $settings['placement'], 'review_order' ); ?>><?php esc_html_e( 'Before order review', 'delivery-date-picker' ); ?></option>
					</select>
				</div>
			</div>

			<div class="ddp-panel ddp-glass">
				<h2 class="ddp-panel-title"><?php esc_html_e( 'Delivery instructions', 'delivery-date-picker' ); ?></h2>
				<?php ddp_toggle( 'enable_note', __( 'Show a delivery instructions field', 'delivery-date-picker' ), $settings ); ?>
				<div class="ddp-field">
					<label class="ddp-field-label" for="ddp-note_label"><?php esc_html_e( 'Instructions label', 'delivery-date-picker' ); ?></label>
					<input type="text" id="ddp-note_label" class="ddp-input" name="note_label" data-ddp-field="note_label" value="<?php echo esc_attr( $settings['note_label'] ); ?>" />
				</div>
			</div>
		</section>

		<!-- AVAILABILITY -->
		<section class="ddp-tab-panel" data-panel="availability">
			<div class="ddp-panel ddp-glass">
				<h2 class="ddp-panel-title"><?php esc_html_e( 'Lead time & horizon', 'delivery-date-picker' ); ?></h2>
				<div class="ddp-field-row">
					<div class="ddp-field">
						<label class="ddp-field-label" for="ddp-min_lead_days"><?php esc_html_e( 'Minimum lead time (days)', 'delivery-date-picker' ); ?></label>
						<input type="number" min="0" max="365" id="ddp-min_lead_days" class="ddp-input" name="min_lead_days" data-ddp-field="min_lead_days" value="<?php echo esc_attr( (string) $settings['min_lead_days'] ); ?>" />
						<span class="ddp-field-hint"><?php esc_html_e( 'How many days you need to prepare before delivering.', 'delivery-date-picker' ); ?></span>
					</div>
					<div class="ddp-field">
						<label class="ddp-field-label" for="ddp-max_advance_days"><?php esc_html_e( 'Maximum advance (days)', 'delivery-date-picker' ); ?></label>
						<input type="number" min="1" max="730" id="ddp-max_advance_days" class="ddp-input" name="max_advance_days" data-ddp-field="max_advance_days" value="<?php echo esc_attr( (string) $settings['max_advance_days'] ); ?>" />
						<span class="ddp-field-hint"><?php esc_html_e( 'How far ahead customers may book.', 'delivery-date-picker' ); ?></span>
					</div>
				</div>
				<div class="ddp-field">
					<label class="ddp-field-label" for="ddp-daily_capacity"><?php esc_html_e( 'Maximum deliveries per day', 'delivery-date-picker' ); ?></label>
					<input type="number" min="0" id="ddp-daily_capacity" class="ddp-input" name="daily_capacity" data-ddp-field="daily_capacity" value="<?php echo esc_attr( (string) $settings['daily_capacity'] ); ?>" />
					<span class="ddp-field-hint"><?php esc_html_e( '0 = unlimited. Days that reach this limit are shown as full.', 'delivery-date-picker' ); ?></span>
				</div>
				<div class="ddp-field">
					<label class="ddp-field-label" for="ddp-first_day"><?php esc_html_e( 'First day of week', 'delivery-date-picker' ); ?></label>
					<select id="ddp-first_day" class="ddp-input" name="first_day" data-ddp-field="first_day">
						<option value="0" <?php selected( (int) $settings['first_day'], 0 ); ?>><?php echo esc_html( $weekday_names[0] ); ?></option>
						<option value="1" <?php selected( (int) $settings['first_day'], 1 ); ?>><?php echo esc_html( $weekday_names[1] ); ?></option>
					</select>
				</div>
			</div>

			<div class="ddp-panel ddp-glass">
				<h2 class="ddp-panel-title"><?php esc_html_e( 'Non-delivery weekdays', 'delivery-date-picker' ); ?></h2>
				<p class="ddp-field-hint ddp-mb"><?php esc_html_e( 'Select the days of the week you never deliver.', 'delivery-date-picker' ); ?></p>
				<div class="ddp-weekday-grid">
					<?php for ( $i = 0; $i < 7; $i++ ) : ?>
						<label class="ddp-chip-check">
							<input type="checkbox" name="disabled_weekdays[]" value="<?php echo esc_attr( (string) $i ); ?>" data-ddp-weekday="<?php echo esc_attr( (string) $i ); ?>" <?php checked( in_array( $i, $disabled, true ) ); ?> />
							<span><?php echo esc_html( $weekday_names[ $i ] ); ?></span>
						</label>
					<?php endfor; ?>
				</div>
			</div>
		</section>

		<!-- TIME SLOTS -->
		<section class="ddp-tab-panel" data-panel="slots">
			<div class="ddp-panel ddp-glass">
				<h2 class="ddp-panel-title"><?php esc_html_e( 'Time slots', 'delivery-date-picker' ); ?></h2>
				<?php ddp_toggle( 'enable_time_slots', __( 'Let customers choose a time slot', 'delivery-date-picker' ), $settings ); ?>
				<?php ddp_toggle( 'slot_required', __( 'Require a time slot when slots are enabled', 'delivery-date-picker' ), $settings ); ?>
			</div>

			<div class="ddp-panel ddp-glass">
				<div class="ddp-panel-head">
					<h2 class="ddp-panel-title"><?php esc_html_e( 'Manage slots', 'delivery-date-picker' ); ?></h2>
					<button type="button" class="ddp-btn ddp-btn-soft" id="ddp-add-slot"><?php esc_html_e( '+ Add slot', 'delivery-date-picker' ); ?></button>
				</div>
				<div class="ddp-slot-list" id="ddp-slot-list">
					<p class="ddp-muted" id="ddp-slot-empty"><?php esc_html_e( 'Loading slots…', 'delivery-date-picker' ); ?></p>
				</div>
			</div>
		</section>

		<!-- HOLIDAYS -->
		<section class="ddp-tab-panel" data-panel="holidays">
			<div class="ddp-panel ddp-glass">
				<h2 class="ddp-panel-title"><?php esc_html_e( 'Add a holiday / closed day', 'delivery-date-picker' ); ?></h2>
				<div class="ddp-field-row ddp-holiday-add">
					<div class="ddp-field">
						<label class="ddp-field-label" for="ddp-holiday-date"><?php esc_html_e( 'Date', 'delivery-date-picker' ); ?></label>
						<input type="date" id="ddp-holiday-date" class="ddp-input" />
					</div>
					<div class="ddp-field ddp-grow">
						<label class="ddp-field-label" for="ddp-holiday-reason"><?php esc_html_e( 'Reason (optional)', 'delivery-date-picker' ); ?></label>
						<input type="text" id="ddp-holiday-reason" class="ddp-input" placeholder="<?php esc_attr_e( 'e.g. Public holiday', 'delivery-date-picker' ); ?>" />
					</div>
					<div class="ddp-field ddp-field-btn">
						<button type="button" class="ddp-btn ddp-btn-primary" id="ddp-add-holiday"><?php esc_html_e( 'Add', 'delivery-date-picker' ); ?></button>
					</div>
				</div>
			</div>

			<div class="ddp-panel ddp-glass">
				<h2 class="ddp-panel-title"><?php esc_html_e( 'Upcoming closed days', 'delivery-date-picker' ); ?></h2>
				<div class="ddp-holiday-list" id="ddp-holiday-list">
					<p class="ddp-muted" id="ddp-holiday-empty"><?php esc_html_e( 'Loading…', 'delivery-date-picker' ); ?></p>
				</div>
			</div>
		</section>

		<!-- APPEARANCE -->
		<section class="ddp-tab-panel" data-panel="appearance">
			<div class="ddp-panel ddp-glass">
				<h2 class="ddp-panel-title"><?php esc_html_e( 'Theme & colour', 'delivery-date-picker' ); ?></h2>
				<div class="ddp-field">
					<label class="ddp-field-label" for="ddp-theme"><?php esc_html_e( 'Color theme', 'delivery-date-picker' ); ?></label>
					<select id="ddp-theme" class="ddp-input" name="theme" data-ddp-field="theme">
						<option value="auto" <?php selected( $settings['theme'], 'auto' ); ?>><?php esc_html_e( 'Auto (match device)', 'delivery-date-picker' ); ?></option>
						<option value="light" <?php selected( $settings['theme'], 'light' ); ?>><?php esc_html_e( 'Light', 'delivery-date-picker' ); ?></option>
						<option value="dark" <?php selected( $settings['theme'], 'dark' ); ?>><?php esc_html_e( 'Dark', 'delivery-date-picker' ); ?></option>
					</select>
					<span class="ddp-field-hint"><?php esc_html_e( 'Applies to the checkout widget.', 'delivery-date-picker' ); ?></span>
				</div>
				<div class="ddp-field">
					<label class="ddp-field-label" for="ddp-accent_color"><?php esc_html_e( 'Accent color', 'delivery-date-picker' ); ?></label>
					<div class="ddp-color-row">
						<input type="color" id="ddp-accent_color" class="ddp-color" name="accent_color" data-ddp-field="accent_color" value="<?php echo esc_attr( $settings['accent_color'] ); ?>" />
						<code class="ddp-color-value" id="ddp-accent-value"><?php echo esc_html( $settings['accent_color'] ); ?></code>
					</div>
				</div>
			</div>
		</section>
	</form>

	<!-- Slot editor template -->
	<template id="ddp-slot-template">
		<div class="ddp-slot-row" data-id="">
			<div class="ddp-slot-grip" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>
			</div>
			<input type="text" class="ddp-input ddp-slot-label" placeholder="<?php esc_attr_e( 'Slot name', 'delivery-date-picker' ); ?>" />
			<input type="time" class="ddp-input ddp-slot-start" />
			<input type="time" class="ddp-input ddp-slot-end" />
			<input type="number" min="0" class="ddp-input ddp-slot-capacity" placeholder="0" title="<?php esc_attr_e( 'Capacity (0 = unlimited)', 'delivery-date-picker' ); ?>" />
			<label class="ddp-switch ddp-slot-enabled-wrap" title="<?php esc_attr_e( 'Enabled', 'delivery-date-picker' ); ?>">
				<input type="checkbox" class="ddp-slot-enabled" checked />
				<span class="ddp-switch-track"><span class="ddp-switch-thumb"></span></span>
			</label>
			<button type="button" class="ddp-icon-btn ddp-slot-save" title="<?php esc_attr_e( 'Save slot', 'delivery-date-picker' ); ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
			</button>
			<button type="button" class="ddp-icon-btn ddp-slot-delete" title="<?php esc_attr_e( 'Delete slot', 'delivery-date-picker' ); ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
			</button>
		</div>
	</template>
</div>
<script type="text/javascript">window.DDPScreen = 'settings';</script>
