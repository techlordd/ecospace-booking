
# Ecospace Booking Plugin Architecture

This document explains how the coworking booking plugin works internally.

The system is designed around **WooCommerce products acting as bookable spaces**.

Example:

```
Engineering Room
Conference Room
```

Each product can have:

```
Seat Capacity
Hourly Rate
Booking Enabled
```

---

# Core Booking Flow

User visits WooCommerce product page.

Steps:

1. Select booking plan
2. Select start date
3. Select preferred dates
4. System calculates price
5. Booking added to cart
6. Order stored in WooCommerce

---

# Booking Plans Logic

## Hourly

Fields:

```
Start Date
Start Time
Hours
```

Rules:

```
End Time = Start Time + Hours
End Time must not exceed 8PM
```

Price:

```
Price = Hourly Rate × Hours
```

---

## Daily

Rules:

```
Start Time = 9:00 AM
End Time = 8:00 PM
```

Price:

```
₦4,800
```

---

## Weekly Plans

Weekly 3x:

```
3 preferred days within one week
Price = ₦12,000
```

Weekly 5x:

```
5 preferred days within one week
Price = ₦20,000
```

End date:

```
Start Date + 7 days
```

---

## Monthly Plans

Monthly 3x:

```
3 days per week × 4 weeks
Total = 12 days
```

Monthly 5x:

```
5 days per week × 4 weeks
Total = 20 days
```

End date:

```
Start Date + 1 month
```

Dates are grouped by week in the UI.

---

# Calendar Engine

The plugin uses **Flatpickr** for date picking.

Responsibilities:

- Disable invalid dates
- Restrict selection to booking range
- Prevent duplicate selections

Future improvement:

```
Grey out fully booked days
```

---

# Seat Capacity System

Each product defines:

```
Seat Capacity
```

Example:

```
Engineering Room → 8 seats
```

Future system will:

```
Track booked seats per date
Prevent booking when capacity reached
```

---

# WooCommerce Integration

Booking data is stored using:

```
woocommerce_add_cart_item_data
```

This allows:

- Checkout integration
- Order storage
- Booking metadata

Example data stored:

```
Plan
Start Date
Preferred Dates
Start Time
End Time
```

---

# Recommended File Structure

```
ecospace-booking
│
├─ ecospace-booking.php
├─ README.md
├─ CONTRIBUTING.md
├─ ARCHITECTURE.md
│
├─ assets
│   ├─ css
│   └─ js
│
├─ includes
│   ├─ booking-engine.php
│   ├─ booking-calendar.php
│   └─ booking-admin.php
│
└─ languages
```

---

# Future Architecture

Planned modules:

### Booking Engine
Handles:

```
availability
capacity tracking
date validation
```

### Calendar Service

Responsible for:

```
rendering availability
disabled dates
user date selection
```

### Admin Dashboard

Features:

```
booking calendar view
room utilization
seat tracking
```

---

# Long Term Goal

Transform the plugin into a **full coworking booking system similar to WeWork scheduling platforms**.


---

# Session Status (per date)

A weekly/monthly booking is ONE WooCommerce order item with many session dates, so
operational status is tracked **per date** in the item meta `_eco_session_status`:

```
{"2026-11-02":"completed","2026-11-09":"checked_in"}
```

- A date missing from the map is `booked`.
- `eco_set_item_session_status()` changes one date; the Bookings row buttons
  (Check in → Complete, No-show, Cancel, Reopen) and the countdown auto-complete only
  ever touch the row's own date.
- Legacy items (no `_eco_session_status`) are derived on read from
  `_eco_checkin_status` / `_eco_checkin_dates` / `_eco_pre_checkin_status`:
  a legacy "completed" plan shows only days up to today as completed; future days are booked.
  The first write migrates the item. Legacy meta is never deleted.
- `_eco_checkin_status` is still maintained as an item-level summary for compatibility.
- The WooCommerce order status and the slot locking logic are unaffected.
- Seat assignment is still per item (whole booking).

# Admin Navigation

Top-level **EcoSpace** menu (`includes/admin-pages.php`):
Dashboard, Bookings, Workspaces, Customers, Payments, Active Sessions, Plans, Settings.

- All screens are read views over existing data; no new tables or post types.
- Customers / Payments / Dashboard totals use `eco_get_orders_index()`, a paged scan of
  WooCommerce orders cached in a 5-minute transient (flushed on order changes).
- Plans are viewed here and edited in each workspace product's Booking settings.
- Old URLs (`page=eco-workspace-bookings`, `page=eco-seat-availability`) still work, and the
  `[eco_workspace_bookings]` shortcode uses the same Bookings interface.
- Styles: `assets/css/admin.css` (new screens) and `assets/css/bookings-ui.css` (the Bookings
  interface, linked directly so the front-end shortcode can use it too).
- The bookings row builder pages through ALL orders (no fixed cap), so the table and the
  status cards always agree. Order links use `get_edit_order_url()` (HPOS-safe).
