
# Ecospace Workspace Booking Plugin

A custom **WordPress + WooCommerce booking plugin** designed for coworking spaces that allow users to book seats using hourly, daily, weekly, and monthly plans.

This plugin powers the **Ecospace coworking booking system**.

---

# Project Goal

Provide a flexible booking system for coworking spaces where users can:

- book seats hourly
- book a full day
- book recurring weekly sessions
- book recurring monthly sessions
- select preferred working days
- see remaining seat capacity
- avoid booking outside allowed date ranges

The plugin integrates directly with **WooCommerce products**.

---

# Key Features

## Booking Plans

| Plan | Description |
|-----|------|
Hourly | User selects hours and price is calculated automatically |
Daily | Fixed day booking (9AM–8PM) |
Weekly 3x | 3 preferred days within 1 week |
Weekly 5x | 5 preferred days within 1 week |
Monthly 3x | 3 days per week for 4 weeks |
Monthly 5x | 5 days per week for 4 weeks |

---

# Pricing Logic

## Hourly

Price = hourly_rate × hours_selected

Example

Rate: ₦750/hour  
Hours: 4  
Total: ₦3000

---

## Daily

Price = ₦4800  
Start Time = 9:00 AM  
End Time = 8:00 PM

---

## Weekly Plans

| Plan | Price |
|-----|-----|
Weekly 5x | ₦20,000 |
Weekly 3x | ₦12,000 |

End date rule:

End Date = Start Date + 7 days

---

## Monthly Plans

End date rule:

End Date = Start Date + 1 month

Preferred days are grouped weekly:

Week 1  
Week 2  
Week 3  
Week 4  

Monthly requirements:

| Plan | Days |
|-----|-----|
Monthly 3x | 12 dates |
Monthly 5x | 20 dates |

---

# Booking Time Rules

Workspace opening hours:

9:00 AM → 8:00 PM

Maximum hourly booking:

Start Time + Hours ≤ 8:00 PM

Example:

Start: 9 AM  
Hours: 4  
End: 1 PM

---

# Seat Capacity

Each product can define seat capacity.

Example:

Engineering Room → 8 seats  
Conference Room → 6 seats

Remaining seats are displayed on the product page.

Future versions will automatically:

- reduce seat availability
- prevent overbooking
- grey out fully booked dates

---

# Calendar System

The plugin uses **Flatpickr** for date selection.

Benefits:

- clean calendar interface
- disables invalid dates
- prevents duplicate selections
- supports grouped weekly selections

---

# Booking Validation Rules

Preferred dates must satisfy:

Start Date ≤ Preferred Date ≤ End Date

Users cannot:

- select duplicate dates
- select dates outside booking range
- select hours outside workspace time

---

# WooCommerce Integration

The booking system extends WooCommerce products.

Admin settings:

- Enable Workspace Booking
- Hourly Rate
- Seat Capacity

Booking details are stored inside WooCommerce cart data so they appear in orders and checkout.

---

# Project Structure

Recommended structure:

ecospace-booking
│
├─ ecospace-booking.php
├─ README.md
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

Future versions will move JavaScript and logic into modular files.

---

# Technology Stack

| Component | Technology |
|------|------|
WordPress | Plugin architecture |
WooCommerce | Product and checkout system |
Flatpickr | Calendar UI |
JavaScript | Dynamic booking logic |
PHP | Server-side booking logic |

---

# Development Goals

Future versions will add:

## Seat availability engine

Track booked seats  
Prevent double booking

---

## Calendar availability

Grey out full days  
Disable booked time slots

---

## Admin booking dashboard

Example view:

Calendar of all bookings  
Seat usage per room  
Upcoming reservations

---

## Booking conflict detection

Example:

User A  
Monday 9AM–5PM

User B cannot book same seat/time

---

# Versioning

Versioning example:

v13.2  
v13.3  
v14.0  
v14.1
v15.0
v15.1
v15.2
v15.3
v15.4
v15.5

Each version improves:

- booking logic
- UI behavior
- validation rules

Latest update (v15.1):

- recurring slot availability now stays blocked after paid/completed orders
- slot locking runs on payment complete and processing/completed order transitions
- refunded/cancelled/failed orders now release their locked slots automatically
- stale slots linked to non-blocking or missing orders are cleaned during reads

Latest update (v15.2):

- added admin product action to rebuild booked slot meta from valid WooCommerce orders
- useful for repairing historical slot data if old bookings appear inconsistent

Latest update (v15.3):

- booking conflicts now block overlapping time ranges, not only exact same start and end times
- hourly and recurring selectors now use booked slot ranges so previously booked time windows stop appearing selectable
- daily bookings now mark already-booked dates as unavailable in the date picker

Latest update (v15.4):

- added live AJAX availability refresh on product pages so booking availability updates without a full page reload
- booking form refreshes availability on plan/date changes, when the page regains focus, on a short interval, and immediately before submit

Latest update (v15.5):

- fixed the Book now flow so AJAX availability refresh preserves the original WooCommerce add-to-cart submit button
- prevents product pages from doing a plain reload when booking data is valid and should be added to cart

---

# Maintainer

Ecospace Development Team
