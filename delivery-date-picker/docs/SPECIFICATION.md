# Delivery Date Picker for WooCommerce — Product Specification

> Commercial WooCommerce extension that lets customers choose **when** their order is
> delivered, and gives store owners a premium scheduling dashboard.

---

## PHASE 1 — IDEA ANALYSIS

### 1.1 What this plugin does

A calendar (and optional time-slot) selector is injected into the WooCommerce checkout.
Customers pick the exact date — and optionally a time window — they want their order to
arrive. The store owner configures availability rules (lead time, weekends off, holidays,
daily/slot capacity). Orders are tagged with the chosen delivery date, surfaced in the
order screen, emails, and a dedicated **Delivery Schedule** dashboard.

**Target stores:** florists, bakeries, food delivery, gift shops, custom/handmade products,
subscription boxes — businesses that physically *cannot* operate without delivery-date control.

### 1.2 How it works internally

```
Customer checkout ──> DDP calendar widget (vanilla JS)
        │                     │  fetches availability
        │                     ▼
        │            REST: GET /ddp/v1/availability?month=YYYY-MM
        │                     │  (Availability engine + transient cache)
        ▼                     ▼
Place order ──> validate (woocommerce_checkout_process)
            ──> save meta   (woocommerce_checkout_create_order)
            ──> create booking row (woocommerce_checkout_order_processed)
                              │
                              ▼
                   wp_ddp_bookings (fast schedule queries)
                              │
                              ▼
            Admin Dashboard / Schedule / REST endpoints
```

The **Availability Engine** is the single source of truth. It combines settings
(lead time, max horizon, disabled weekdays), the blocked-dates table (holidays), and live
capacity counts from the bookings table to decide whether a date/slot is selectable.

### 1.3 User (customer) workflow

1. Adds products to cart, proceeds to checkout.
2. Sees a **"Delivery Date"** card under the order notes.
3. Opens the calendar — unavailable days (past, lead-time, weekends off, holidays, full)
   are visibly disabled.
4. Picks a date. If time slots are enabled, available slots load for that date with
   remaining capacity.
5. Optionally adds a delivery note (e.g. "leave at reception").
6. Places the order. The selection is validated server-side and stored on the order.
7. Confirmation email + order details show the chosen delivery date/slot.

### 1.4 Admin (store owner) workflow

1. Activates plugin → guided **onboarding wizard** (business type, lead time, working days,
   time slots).
2. **Dashboard:** KPIs (today's deliveries, upcoming 7 days, total scheduled, busiest day) +
   trend chart + next deliveries list.
3. **Schedule:** month calendar with a per-day delivery count heatmap; clicking a day lists
   its orders with customer, items count, slot, and status. One-click block/unblock a day.
4. **Settings:** tabbed configuration (General, Availability, Time Slots, Holidays,
   Appearance, Advanced).
5. Day-to-day: each order shows its delivery date in the order list (sortable column) and on
   the order detail screen.

### 1.5 Database structure (summary)

| Storage | Purpose |
|---|---|
| `wp_options` (`ddp_settings`) | All configuration (single autoloaded option). |
| `wp_ddp_time_slots` | Definable delivery windows with per-slot capacity. |
| `wp_ddp_blocked_dates` | Holidays / one-off closed days. |
| `wp_ddp_bookings` | One row per scheduled order — powers fast schedule + capacity. |
| Order meta (`_ddp_*`) | Authoritative delivery data on the order (HPOS-safe CRUD). |

Full schema in `ARCHITECTURE.md`.

### 1.6 WordPress / WooCommerce hooks used

- `woocommerce_after_order_notes` — render the picker (classic checkout).
- `woocommerce_checkout_process` — server-side validation.
- `woocommerce_checkout_create_order` — persist delivery meta (HPOS CRUD).
- `woocommerce_checkout_order_processed` — create the booking row.
- `woocommerce_admin_order_data_after_shipping_address` — admin order display.
- `woocommerce_order_details_after_order_table` / `woocommerce_email_after_order_table` — customer display.
- Order list columns: `manage_woocommerce_page_wc-orders_columns` (HPOS) and
  `manage_edit-shop_order_columns` (legacy posts) + matching render hooks.
- `rest_api_init` — register `ddp/v1` routes.
- `admin_menu`, `admin_enqueue_scripts`, `wp_enqueue_scripts`, `wp_ajax_*`.
- `before_woocommerce_init` — declare HPOS + Cart/Checkout-Blocks compatibility.

### 1.7 APIs

Internal REST namespace `ddp/v1` (no third-party API required):

| Method | Route | Auth | Purpose |
|---|---|---|---|
| GET | `/availability` | public | Month availability map for the calendar. |
| GET | `/slots` | public | Time slots + remaining capacity for a date. |
| GET | `/schedule` | `manage_woocommerce` | Orders for a date/range (admin). |
| GET | `/stats` | `manage_woocommerce` | Dashboard KPIs + trend series. |

### 1.8 Complete feature list (free core)

- Checkout date picker with custom premium calendar (no jQuery UI dependency).
- Optional time slots with per-slot daily capacity.
- Lead time (min days before delivery) and max advance horizon.
- Disable specific weekdays (e.g. Sun/Mon closed).
- Holiday / blocked-date manager.
- Per-day global capacity limit (auto "Sold out" days).
- Optional required/optional field + customizable label.
- Delivery note field.
- Admin schedule dashboard + calendar heatmap.
- KPI dashboard with trend chart.
- Order list column (sortable) + order/email display.
- REST API + transient caching.
- HPOS + block-checkout compatible.
- Light/dark/auto themes, fully responsive.
- Onboarding wizard, import/export settings.
- Translation-ready (`.pot`).

### 1.9 Premium feature suggestions (upsell)

- Per-product / per-category / per-shipping-method date rules.
- Surcharge / fee per date or slot (e.g. weekend premium, same-day fee).
- Cut-off time per day (e.g. order before 2pm for next-day).
- Multi-location / multi-vendor schedules.
- Google Calendar / iCal export + .ics in emails.
- Pickup vs delivery toggle with separate calendars.
- Recurring/subscription deliveries.
- SMS/email delivery reminders.
- Driver route export (CSV/PDF) & printable run sheets.
- Customer "edit delivery date" from My Account.

### 1.10 Security considerations

- Nonce verification on every AJAX + REST write (and REST nonce for reads where relevant).
- `current_user_can()` capability gates on all admin endpoints (`manage_woocommerce`).
- Strict input sanitization (dates re-parsed via `DateTime`, ints cast, text sanitized).
- Output escaping everywhere (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`).
- `$wpdb->prepare()` for every query; no string-built SQL.
- Server-side re-validation of availability (never trust the client calendar).
- Direct-file-access guards (`ABSPATH`) on every PHP file.
- Capability-checked uninstall cleanup.

### 1.11 Performance considerations

- Denormalized `wp_ddp_bookings` table with indexes on `delivery_date` + `slot_id`.
- Per-month availability cached in transients; invalidated on order/settings/holiday change.
- Single autoloaded settings option (one row, no N+1 option reads).
- Lightweight vanilla-JS calendar + dependency-free SVG charts (no chart library payload).
- Conditional asset loading (checkout assets only on checkout; admin assets only on DDP screens).
- Capacity counts read from the bookings table, never by scanning all orders.

### 1.12 Scalability considerations

- Bookings table scales to large order volumes; queries are index-bound and date-ranged.
- Caching layer keeps the calendar O(1) for repeat views within a month.
- Status column on bookings allows soft-cancel without row churn.
- Schedule endpoints are paginated/date-bounded.

### 1.13 Potential issues & solutions

| Issue | Solution |
|---|---|
| Block (React) checkout vs classic | Declare blocks compatibility; render via integration; classic fully supported, blocks via Store API meta passthrough + graceful notice. |
| HPOS vs legacy meta | Use `$order` CRUD (`update_meta_data`) and dual order-list column hooks. |
| Timezone drift | All comparisons use site timezone via `wp_timezone()`; dates stored as `Y-m-d`. |
| Stale capacity after cancellation | Booking status updated on order status change; caches flushed. |
| Race on last slot | Capacity re-checked at validation time within the order flow. |

### 1.14 Monetization

- One-time / annual license for premium add-on (Freemius/EDD-style).
- Tiered pricing: Single site / 5 sites / unlimited.
- Premium = advanced rules, fees, calendar sync, route sheets.
- Base price point: **$19** (per the brief) for the core commercial plugin.

### 1.15 Future upgrades

- Native Gutenberg block-checkout field component.
- Delivery zones by postcode.
- AI demand forecasting for capacity planning.
- Mobile companion (driver app) consuming the REST API.

---

See `ARCHITECTURE.md` for folder/file/DB/flow/security/performance detail and
`README.md` (or `readme.txt`) for installation, configuration, testing, and troubleshooting.
