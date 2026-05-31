=== Delivery Date Picker for WooCommerce ===
Contributors: deliverydatepicker
Tags: woocommerce, delivery date, checkout, time slots, schedule
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 9.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let customers choose exactly when their order is delivered. A premium calendar at checkout, time slots, capacity limits, holidays and a beautiful delivery schedule dashboard.

== Description ==

**Delivery Date Picker** gives your WooCommerce customers full control over *when* their order arrives. Perfect for florists, bakeries, food delivery, gift shops and custom product stores where the delivery date is part of the product.

* A premium, dependency-free calendar widget on the checkout page.
* Optional time slots (e.g. Morning / Afternoon / Evening) with per-slot capacity.
* Minimum lead time and maximum advance horizon.
* Disable specific weekdays and block holidays / closed days.
* Per-day capacity limits — full days are automatically disabled.
* A gorgeous, Stripe/Linear-style admin dashboard with KPIs and a 14-day trend chart.
* A schedule calendar with a delivery heatmap and per-day order list.
* Light, dark and auto themes. Fully responsive.
* HPOS (High-Performance Order Storage) compatible.
* Built-in REST API and transient caching for speed.

== Installation ==

1. Upload the `delivery-date-picker` folder to `/wp-content/plugins/`, or install the ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin through the **Plugins** screen.
3. Follow the onboarding wizard, or go to **Delivery → Settings** to configure.
4. Make sure WooCommerce is installed and active.

== Frequently Asked Questions ==

= Does it work with the new block-based checkout? =
The picker is fully supported on the classic (shortcode) checkout. The plugin declares
compatibility with the Cart & Checkout Blocks feature and stores delivery data safely; for
the richest experience we recommend the classic checkout block. See the troubleshooting guide.

= Is it compatible with High-Performance Order Storage (HPOS)? =
Yes. All order data is read and written through the WooCommerce CRUD API and the plugin
declares `custom_order_tables` compatibility.

= Can I limit how many orders per day or per slot? =
Yes. Set a global per-day limit in **Settings → Availability**, and a per-slot capacity in
**Settings → Time Slots**. Days/slots that reach the limit are shown as full.

== Screenshots ==

1. Premium calendar widget on checkout.
2. Admin dashboard with KPIs and trend chart.
3. Schedule calendar with delivery heatmap.
4. Settings with tabbed configuration.
5. Onboarding wizard.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
