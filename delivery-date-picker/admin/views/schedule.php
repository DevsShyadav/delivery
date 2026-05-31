<?php
/**
 * Admin schedule view (calendar heatmap + day order list).
 *
 * @package DeliveryDatePicker
 * @var array $settings Current settings.
 */

namespace DDP;

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ddp-wrap ddp-app" data-ddp-theme="<?php echo esc_attr( isset( $settings['theme'] ) ? $settings['theme'] : 'auto' ); ?>">

	<header class="ddp-topbar">
		<div class="ddp-brand">
			<span class="ddp-brand-mark" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
			</span>
			<div>
				<h1><?php esc_html_e( 'Delivery Schedule', 'delivery-date-picker' ); ?></h1>
				<p class="ddp-subtitle"><?php esc_html_e( 'Browse the calendar and manage availability.', 'delivery-date-picker' ); ?></p>
			</div>
		</div>
		<div class="ddp-topbar-actions">
			<button type="button" class="ddp-btn ddp-btn-ghost" id="ddp-theme-toggle" title="<?php esc_attr_e( 'Toggle theme', 'delivery-date-picker' ); ?>">
				<span class="ddp-theme-icon-light" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
				</span>
				<span class="ddp-theme-icon-dark" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
				</span>
			</button>
		</div>
	</header>

	<div class="ddp-schedule-layout">
		<section class="ddp-panel ddp-glass ddp-schedule-cal">
			<div class="ddp-cal-toolbar">
				<button type="button" class="ddp-cal-nav" id="ddp-cal-prev" aria-label="<?php esc_attr_e( 'Previous month', 'delivery-date-picker' ); ?>">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
				</button>
				<h2 class="ddp-cal-title" id="ddp-cal-title">—</h2>
				<button type="button" class="ddp-cal-nav" id="ddp-cal-next" aria-label="<?php esc_attr_e( 'Next month', 'delivery-date-picker' ); ?>">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
				</button>
			</div>
			<div class="ddp-cal-grid-head" id="ddp-cal-weekdays"></div>
			<div class="ddp-cal-grid" id="ddp-schedule-grid"></div>
			<div class="ddp-cal-legend">
				<span class="ddp-legend-item"><i class="ddp-dot ddp-dot-0"></i><?php esc_html_e( 'None', 'delivery-date-picker' ); ?></span>
				<span class="ddp-legend-item"><i class="ddp-dot ddp-dot-1"></i><?php esc_html_e( 'Light', 'delivery-date-picker' ); ?></span>
				<span class="ddp-legend-item"><i class="ddp-dot ddp-dot-2"></i><?php esc_html_e( 'Busy', 'delivery-date-picker' ); ?></span>
				<span class="ddp-legend-item"><i class="ddp-dot ddp-dot-3"></i><?php esc_html_e( 'Peak', 'delivery-date-picker' ); ?></span>
				<span class="ddp-legend-item"><i class="ddp-dot ddp-dot-blocked"></i><?php esc_html_e( 'Blocked', 'delivery-date-picker' ); ?></span>
			</div>
		</section>

		<aside class="ddp-panel ddp-glass ddp-schedule-day">
			<div class="ddp-panel-head">
				<h2 id="ddp-day-title"><?php esc_html_e( 'Select a day', 'delivery-date-picker' ); ?></h2>
				<button type="button" class="ddp-btn ddp-btn-soft" id="ddp-block-toggle" hidden>
					<?php esc_html_e( 'Block this day', 'delivery-date-picker' ); ?>
				</button>
			</div>
			<div class="ddp-day-orders" id="ddp-day-orders">
				<p class="ddp-muted"><?php esc_html_e( 'Pick a date on the calendar to see its deliveries.', 'delivery-date-picker' ); ?></p>
			</div>
		</aside>
	</div>
</div>
<script type="text/javascript">window.DDPScreen = 'schedule';</script>
