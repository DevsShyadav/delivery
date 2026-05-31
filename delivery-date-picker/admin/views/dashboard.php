<?php
/**
 * Admin dashboard view.
 *
 * @package DeliveryDatePicker
 * @var array $settings Current settings (provided by Admin::render_view).
 */

namespace DDP;

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ddp-wrap ddp-app" data-ddp-theme="<?php echo esc_attr( isset( $settings['theme'] ) ? $settings['theme'] : 'auto' ); ?>">

	<header class="ddp-topbar">
		<div class="ddp-brand">
			<span class="ddp-brand-mark" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="m9 16 2 2 4-4"/></svg>
			</span>
			<div>
				<h1><?php esc_html_e( 'Delivery Dashboard', 'delivery-date-picker' ); ?></h1>
				<p class="ddp-subtitle"><?php esc_html_e( 'Your upcoming delivery schedule at a glance.', 'delivery-date-picker' ); ?></p>
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
			<a class="ddp-btn ddp-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=ddp-schedule' ) ); ?>">
				<?php esc_html_e( 'Open Schedule', 'delivery-date-picker' ); ?>
			</a>
		</div>
	</header>

	<div class="ddp-stats-grid" id="ddp-stats">
		<div class="ddp-stat-card ddp-glass">
			<div class="ddp-stat-icon ddp-grad-indigo" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
			</div>
			<div class="ddp-stat-body">
				<span class="ddp-stat-label"><?php esc_html_e( 'Today', 'delivery-date-picker' ); ?></span>
				<span class="ddp-stat-value" data-stat="today">—</span>
			</div>
		</div>
		<div class="ddp-stat-card ddp-glass">
			<div class="ddp-stat-icon ddp-grad-emerald" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
			</div>
			<div class="ddp-stat-body">
				<span class="ddp-stat-label"><?php esc_html_e( 'Next 7 days', 'delivery-date-picker' ); ?></span>
				<span class="ddp-stat-value" data-stat="week">—</span>
			</div>
		</div>
		<div class="ddp-stat-card ddp-glass">
			<div class="ddp-stat-icon ddp-grad-amber" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20v-6M6 20V10M18 20V4"/></svg>
			</div>
			<div class="ddp-stat-body">
				<span class="ddp-stat-label"><?php esc_html_e( 'Total upcoming', 'delivery-date-picker' ); ?></span>
				<span class="ddp-stat-value" data-stat="upcoming">—</span>
			</div>
		</div>
		<div class="ddp-stat-card ddp-glass">
			<div class="ddp-stat-icon ddp-grad-rose" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
			</div>
			<div class="ddp-stat-body">
				<span class="ddp-stat-label"><?php esc_html_e( 'Busiest day', 'delivery-date-picker' ); ?></span>
				<span class="ddp-stat-value ddp-stat-value-sm" data-stat="busiest">—</span>
			</div>
		</div>
	</div>

	<div class="ddp-grid-2">
		<section class="ddp-panel ddp-glass">
			<div class="ddp-panel-head">
				<h2><?php esc_html_e( 'Deliveries — next 14 days', 'delivery-date-picker' ); ?></h2>
				<span class="ddp-pill" id="ddp-trend-total">—</span>
			</div>
			<div class="ddp-chart" id="ddp-trend-chart" aria-hidden="false">
				<div class="ddp-chart-empty"><?php esc_html_e( 'Loading…', 'delivery-date-picker' ); ?></div>
			</div>
		</section>

		<section class="ddp-panel ddp-glass">
			<div class="ddp-panel-head">
				<h2><?php esc_html_e( 'Next deliveries', 'delivery-date-picker' ); ?></h2>
			</div>
			<ul class="ddp-next-list" id="ddp-next-list">
				<li class="ddp-next-empty"><?php esc_html_e( 'Loading…', 'delivery-date-picker' ); ?></li>
			</ul>
		</section>
	</div>

	<footer class="ddp-footer">
		<span><?php echo esc_html( sprintf( /* translators: %s version */ __( 'Delivery Date Picker v%s', 'delivery-date-picker' ), DDP_VERSION ) ); ?></span>
	</footer>
</div>
<script type="text/javascript">window.DDPScreen = 'dashboard';</script>
