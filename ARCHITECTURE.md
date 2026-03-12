
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

