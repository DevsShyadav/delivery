<?php
/**
 * Onboarding wizard view.
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
$theme = isset( $settings['theme'] ) ? $settings['theme'] : 'auto';
?>
<div class="ddp-onboarding ddp-app" data-ddp-theme="<?php echo esc_attr( $theme ); ?>">
	<div class="ddp-onboarding-card ddp-glass">

		<div class="ddp-onboarding-aside">
			<div class="ddp-onboarding-brand">
				<span class="ddp-brand-mark" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="m9 16 2 2 4-4"/></svg>
				</span>
				<strong><?php esc_html_e( 'Delivery Date Picker', 'delivery-date-picker' ); ?></strong>
			</div>
			<h2><?php esc_html_e( 'Let’s get you set up in under a minute.', 'delivery-date-picker' ); ?></h2>
			<p><?php esc_html_e( 'Give your customers control over when their order arrives — and never miss a delivery again.', 'delivery-date-picker' ); ?></p>
			<ul class="ddp-onboarding-steps" id="ddp-step-indicator">
				<li class="is-active" data-step-dot="1"><span>1</span><?php esc_html_e( 'Business', 'delivery-date-picker' ); ?></li>
				<li data-step-dot="2"><span>2</span><?php esc_html_e( 'Lead time', 'delivery-date-picker' ); ?></li>
				<li data-step-dot="3"><span>3</span><?php esc_html_e( 'Working days', 'delivery-date-picker' ); ?></li>
				<li data-step-dot="4"><span>4</span><?php esc_html_e( 'Slots', 'delivery-date-picker' ); ?></li>
			</ul>
		</div>

		<div class="ddp-onboarding-main">
			<div class="ddp-onboarding-progress"><span id="ddp-progress-bar" style="width:25%"></span></div>

			<!-- Step 1 -->
			<section class="ddp-step is-active" data-step="1">
				<h3><?php esc_html_e( 'What type of store do you run?', 'delivery-date-picker' ); ?></h3>
				<p class="ddp-muted"><?php esc_html_e( 'We’ll tune sensible defaults for you. You can change everything later.', 'delivery-date-picker' ); ?></p>
				<div class="ddp-choice-grid">
					<button type="button" class="ddp-choice" data-biz="flowers" data-lead="1"><span>💐</span><?php esc_html_e( 'Flowers', 'delivery-date-picker' ); ?></button>
					<button type="button" class="ddp-choice" data-biz="bakery" data-lead="1"><span>🧁</span><?php esc_html_e( 'Bakery', 'delivery-date-picker' ); ?></button>
					<button type="button" class="ddp-choice" data-biz="food" data-lead="0"><span>🍱</span><?php esc_html_e( 'Food delivery', 'delivery-date-picker' ); ?></button>
					<button type="button" class="ddp-choice" data-biz="gifts" data-lead="2"><span>🎁</span><?php esc_html_e( 'Gifts', 'delivery-date-picker' ); ?></button>
					<button type="button" class="ddp-choice" data-biz="custom" data-lead="3"><span>🛠️</span><?php esc_html_e( 'Custom products', 'delivery-date-picker' ); ?></button>
					<button type="button" class="ddp-choice" data-biz="other" data-lead="1"><span>📦</span><?php esc_html_e( 'Something else', 'delivery-date-picker' ); ?></button>
				</div>
			</section>

			<!-- Step 2 -->
			<section class="ddp-step" data-step="2">
				<h3><?php esc_html_e( 'How much notice do you need?', 'delivery-date-picker' ); ?></h3>
				<p class="ddp-muted"><?php esc_html_e( 'The earliest a customer can choose is today plus this many days.', 'delivery-date-picker' ); ?></p>
				<div class="ddp-field">
					<label class="ddp-field-label" for="ddp-ob-lead"><?php esc_html_e( 'Minimum lead time (days)', 'delivery-date-picker' ); ?></label>
					<input type="number" min="0" max="60" id="ddp-ob-lead" class="ddp-input" value="1" />
				</div>
				<div class="ddp-field">
					<label class="ddp-field-label" for="ddp-ob-advance"><?php esc_html_e( 'How far ahead can customers book? (days)', 'delivery-date-picker' ); ?></label>
					<input type="number" min="1" max="365" id="ddp-ob-advance" class="ddp-input" value="60" />
				</div>
			</section>

			<!-- Step 3 -->
			<section class="ddp-step" data-step="3">
				<h3><?php esc_html_e( 'Which days do you deliver?', 'delivery-date-picker' ); ?></h3>
				<p class="ddp-muted"><?php esc_html_e( 'Untick any days you are closed.', 'delivery-date-picker' ); ?></p>
				<div class="ddp-weekday-grid">
					<?php for ( $i = 0; $i < 7; $i++ ) : ?>
						<label class="ddp-chip-check">
							<input type="checkbox" class="ddp-ob-weekday" value="<?php echo esc_attr( (string) $i ); ?>" checked />
							<span><?php echo esc_html( $weekday_names[ $i ] ); ?></span>
						</label>
					<?php endfor; ?>
				</div>
			</section>

			<!-- Step 4 -->
			<section class="ddp-step" data-step="4">
				<h3><?php esc_html_e( 'Do you offer time slots?', 'delivery-date-picker' ); ?></h3>
				<p class="ddp-muted"><?php esc_html_e( 'Let customers pick a window like “Morning” or “Evening”. We’ve added a few defaults you can edit later.', 'delivery-date-picker' ); ?></p>
				<div class="ddp-field ddp-field-toggle">
					<label class="ddp-switch">
						<input type="checkbox" id="ddp-ob-slots" checked />
						<span class="ddp-switch-track"><span class="ddp-switch-thumb"></span></span>
					</label>
					<div class="ddp-field-text">
						<span class="ddp-field-label"><?php esc_html_e( 'Enable time slots', 'delivery-date-picker' ); ?></span>
						<span class="ddp-field-hint"><?php esc_html_e( 'Morning, afternoon and evening windows are pre-configured.', 'delivery-date-picker' ); ?></span>
					</div>
				</div>
				<div class="ddp-onboarding-done">
					<div class="ddp-done-emoji">🚀</div>
					<p><?php esc_html_e( 'That’s everything — your store is ready to take scheduled deliveries.', 'delivery-date-picker' ); ?></p>
				</div>
			</section>

			<div class="ddp-onboarding-nav">
				<button type="button" class="ddp-btn ddp-btn-ghost" id="ddp-ob-back" hidden><?php esc_html_e( 'Back', 'delivery-date-picker' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ddp-dashboard' ) ); ?>" class="ddp-skip" id="ddp-ob-skip"><?php esc_html_e( 'Skip setup', 'delivery-date-picker' ); ?></a>
				<button type="button" class="ddp-btn ddp-btn-primary" id="ddp-ob-next"><?php esc_html_e( 'Continue', 'delivery-date-picker' ); ?></button>
			</div>
		</div>
	</div>
</div>
<script type="text/javascript">window.DDPScreen = 'onboarding';</script>
