# Delivery Date Picker for WooCommerce

> Let customers choose **when** their order is delivered — a premium calendar at checkout,
> time slots, capacity limits, holidays, and a beautiful delivery schedule dashboard.

Built for florists, bakeries, food delivery, gift shops and custom-product stores where the
delivery date is part of the product.

- **Spec:** [`docs/SPECIFICATION.md`](docs/SPECIFICATION.md)
- **Architecture:** [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)

---

## ✨ Features

- Premium, dependency-free calendar widget on the checkout page.
- Optional time slots (Morning / Afternoon / Evening …) with per-slot daily capacity.
- Minimum lead time + maximum advance horizon.
- Disable specific weekdays and block holidays / closed days.
- Per-day capacity limits — full days auto-disable.
- SaaS-grade admin: KPI dashboard, 14-day trend chart, schedule heatmap calendar.
- Order list column (delivery date + slot), order screen panel, customer + email display.
- Light / dark / auto themes. Fully responsive.
- HPOS compatible. Built-in REST API. Transient caching.
- Onboarding wizard. Translation-ready.

---

## 🚀 Installation

### From a ZIP (recommended for stores)

1. Zip the `delivery-date-picker` folder so the archive contains
   `delivery-date-picker/delivery-date-picker.php` at its root.
2. In WordPress: **Plugins → Add New → Upload Plugin** → choose the ZIP → **Install Now**.
3. Click **Activate**.

### From the repository (for developers)

```bash
# Copy the plugin folder into your WordPress install
cp -r delivery-date-picker /path/to/wp-content/plugins/
```

Then activate it under **Plugins** in wp-admin.

**Requirements:** WordPress 6.2+, PHP 7.4+, WooCommerce 7.0+ (active).

On first activation you are taken to a short **onboarding wizard**. You can skip it and
configure everything later under **Delivery → Settings**.

---

## ⚙️ Configuration Guide

Open **Delivery → Settings**. Settings are grouped into tabs:

| Tab | What you configure |
|---|---|
| **General** | Enable/disable the picker, field label & description, required toggle, field placement, delivery-instructions field. |
| **Availability** | Minimum lead time (days), maximum advance (days), max deliveries per day, first day of week, non-delivery weekdays. |
| **Time Slots** | Enable slots, require a slot, and add/edit/delete slots (label, start, end, capacity). |
| **Holidays** | Add specific closed days with an optional reason. |
| **Appearance** | Light/dark/auto theme and accent colour for the checkout widget. |

Changes save instantly via AJAX (the **Save changes** button) — no page reload.

### Day-to-day

- **Delivery → Dashboard** — today's deliveries, next 7 days, total upcoming, busiest day,
  a 14-day trend chart and your next deliveries.
- **Delivery → Schedule** — a month calendar with a delivery heatmap. Click any day to see
  its orders, or block/unblock the day in one click.
- Each order shows its delivery date in the **Orders** list (Delivery column) and on the
  order detail screen.

---

## 🗄️ Database

Three custom tables (prefixed with your site's table prefix):

- `…_ddp_time_slots` — delivery windows + per-slot capacity.
- `…_ddp_blocked_dates` — holidays / closed days.
- `…_ddp_bookings` — one row per scheduled order (powers the schedule + capacity checks).

Authoritative delivery data is also stored on each order as meta:
`_ddp_delivery_date`, `_ddp_slot_id`, `_ddp_slot_label`, `_ddp_delivery_note`.

> Uninstalling the plugin drops the three tables and deletes plugin options/transients.
> Order meta is intentionally preserved so historical orders keep their delivery info.

---

## 🔌 REST API (`ddp/v1`)

| Method | Route | Auth | Purpose |
|---|---|---|---|
| GET | `/availability?month=YYYY-MM` | public | Month availability map for the calendar. |
| GET | `/slots?date=YYYY-MM-DD` | public | Time slots + remaining capacity for a date. |
| GET | `/schedule?from=&to=` | `manage_woocommerce` | Orders + counts for a date range. |
| GET | `/stats` | `manage_woocommerce` | Dashboard KPIs + trend + next deliveries. |

---

## ✅ Testing Instructions

A quick manual test plan that covers every feature:

1. **Activation** — activate with WooCommerce active. Confirm no PHP errors and the
   onboarding wizard appears. Complete it.
2. **Checkout (classic)** — add a product, go to the classic checkout. The "Delivery Date"
   card appears. Past dates, lead-time days, off-weekdays and holidays are disabled.
3. **Slot selection** — pick an available date; time slots load with remaining counts.
   Selecting a slot is required if configured.
4. **Validation** — try to place the order without a date (when required) → blocked with a
   notice. Choose a valid date/slot → order completes.
5. **Order data** — open the new order in wp-admin: the Delivery Schedule panel shows the
   date/slot/instructions. The **Orders** list shows the Delivery column. The confirmation
   email shows the delivery details.
6. **Dashboard** — KPIs, trend chart and "next deliveries" reflect the new order.
7. **Schedule** — the chosen day shows a count badge + heatmap dot; clicking it lists the
   order. Block the day → it becomes unavailable on the checkout calendar.
8. **Capacity** — set a slot capacity of 1, book it, then confirm that slot shows **Full**
   and the day becomes full if it was the only slot.
9. **Settings** — change label, lead time, weekends-off, theme; confirm the checkout widget
   reflects each change.
10. **Holidays** — add a holiday; confirm it is disabled on checkout and shown as blocked on
    the schedule.
11. **Uninstall** — delete the plugin; confirm tables/options are removed.

### Static checks performed

- `php -l` on every PHP file (syntax).
- `node --check` on every JS file (syntax).

---

## 🛠️ Troubleshooting

**The date picker doesn't show on checkout.**
- Confirm **Settings → General → Enable** is on.
- The picker renders on the **classic** WooCommerce checkout. If you use the **block**
  (React) checkout, switch the Checkout page to the classic `[woocommerce_checkout]`
  shortcode, or use the "Classic Checkout" block. (Native block-checkout field is on the
  roadmap.)

**All days look disabled.**
- Check your lead time and non-delivery weekdays in **Settings → Availability**.
- If a per-day capacity is set, days at the limit show as full.

**Times look off by a few hours.**
- The plugin uses your **WordPress timezone** (Settings → General in WP). Set it correctly.

**Capacity didn't free up after a cancellation.**
- Bookings follow order status. Cancelled/refunded/failed orders release capacity
  automatically; caches refresh on the next change.

**Admin screens look unstyled / data won't load.**
- Hard-refresh to clear cached assets. Ensure your user has the `manage_woocommerce`
  capability (the REST/AJAX endpoints require it).

---

## 📄 License

GPL-2.0-or-later.
