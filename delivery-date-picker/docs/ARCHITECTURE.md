# Delivery Date Picker — Architecture

## PHASE 2 — PLUGIN ARCHITECTURE

### Folder structure

```
delivery-date-picker/
├── delivery-date-picker.php        # Main plugin file: header, constants, autoloader, bootstrap
├── uninstall.php                   # Clean removal (tables + options) — capability checked
├── readme.txt                      # WordPress.org-style readme (install/config/FAQ/changelog)
├── includes/
│   ├── helpers.php                 # Namespaced helper functions (settings access, formatting)
│   ├── class-plugin.php            # Singleton bootstrap; wires all components & hooks
│   ├── class-installer.php         # Activation: dbDelta tables, default options, versioning
│   ├── class-settings.php          # Settings schema, defaults, sanitization, get/update
│   ├── class-availability.php      # Core engine: is_date_available, month map, capacity, slots
│   ├── class-checkout.php          # Frontend field render, validation, save, asset enqueue
│   ├── class-order.php             # Admin order display, list column, emails, booking sync
│   ├── class-rest-controller.php   # ddp/v1 REST routes (availability, slots, schedule, stats)
│   ├── class-admin.php             # Admin menu, screens, asset enqueue, localized data
│   └── class-ajax.php              # Admin-ajax handlers (settings save, slot/holiday CRUD)
├── admin/
│   └── views/
│       ├── dashboard.php           # KPI cards + trend chart + next deliveries
│       ├── schedule.php            # Calendar heatmap + day order list
│       ├── settings.php            # Tabbed settings UI
│       └── onboarding.php          # First-run wizard
├── assets/
│   ├── css/
│   │   ├── admin.css               # Premium SaaS admin styling (light/dark)
│   │   └── checkout.css            # Frontend calendar + card styling
│   └── js/
│       ├── ddp-calendar.js         # Reusable calendar component (UMD-ish global)
│       ├── checkout.js             # Checkout integration (uses calendar + slots)
│       └── admin.js                # Dashboard/schedule/settings/onboarding logic + charts
├── languages/
│   └── delivery-date-picker.pot    # Translation template
└── docs/
    ├── SPECIFICATION.md            # Phase 1 product spec
    └── ARCHITECTURE.md             # This document
```

### Database structure

All tables use the site prefix (`$wpdb->prefix`) and the DB charset/collation.

#### `{prefix}ddp_time_slots`
| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | PK |
| `label` | VARCHAR(120) | e.g. "Morning (9am–12pm)" |
| `start_time` | TIME | window start |
| `end_time` | TIME | window end |
| `capacity` | INT UNSIGNED | max orders/slot/day (0 = unlimited) |
| `sort_order` | INT | display order |
| `enabled` | TINYINT(1) | active flag |
| `created_at` | DATETIME | |

#### `{prefix}ddp_blocked_dates`
| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | PK |
| `blocked_date` | DATE | UNIQUE — closed day |
| `reason` | VARCHAR(190) | optional label |
| `created_at` | DATETIME | |

#### `{prefix}ddp_bookings`
| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | PK |
| `order_id` | BIGINT UNSIGNED | UNIQUE — WC order id |
| `delivery_date` | DATE | INDEX |
| `slot_id` | BIGINT UNSIGNED | INDEX (0 = none) |
| `slot_label` | VARCHAR(120) | snapshot for display |
| `status` | VARCHAR(20) | active / cancelled / completed |
| `created_at` | DATETIME | |

Indexes: `delivery_date`, `slot_id`, `status`, unique `order_id`.

#### Order meta (authoritative)
- `_ddp_delivery_date` (`Y-m-d`)
- `_ddp_slot_id` (int)
- `_ddp_slot_label` (string)
- `_ddp_delivery_note` (string)

### File responsibilities
Each `includes/class-*.php` maps 1:1 to a single responsibility (SRP). The `Plugin`
singleton constructs and registers each component; components never instantiate each other
directly except through static engine calls (`Availability`) and settings (`Settings`).

### User flow (screens)
1. **Checkout card** — calendar + (optional) slot chips + delivery note. Inline validation,
   disabled-day styling, theme-aware.
2. **Order received / emails** — read-only delivery summary line.

### Admin flow (screens)
1. **Onboarding wizard** — 4 steps; writes initial settings + sample slots.
2. **Dashboard** — KPIs, 14-day trend chart, next deliveries table.
3. **Schedule** — month heatmap calendar; day drawer with orders + block toggle.
4. **Settings** — General / Availability / Time Slots / Holidays / Appearance / Advanced.

### API flow
- Calendar boot → `GET /ddp/v1/availability?month` → render disabled/enabled days.
- Date click (slots on) → `GET /ddp/v1/slots?date` → render slot chips with remaining counts.
- Admin dashboard → `GET /ddp/v1/stats` (nonce + cap) → cards + chart.
- Admin schedule → `GET /ddp/v1/schedule?from&to` (nonce + cap) → calendar counts + day list.
- Admin writes go through `admin-ajax.php` with `ddp_admin` nonce + capability check.

### Security layer
Direct-access guards • nonces (AJAX + `wp_rest`) • capability checks • sanitize on input
(`DateTime` re-parse for dates, `absint`, `sanitize_text_field`, `wp_kses_post`) • escape on
output • `$wpdb->prepare()` everywhere • server-side availability re-validation • no eval/
dynamic SQL • uninstall guarded by capability + option flag.

### Performance layer
Indexed denormalized bookings • per-month transient cache with targeted invalidation •
single autoloaded settings option • conditional asset loading • dependency-free calendar +
SVG charts • date-bounded admin queries.
