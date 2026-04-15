
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
v15.6
v15.7
v15.8
v16.0
v16.1
v16.2
v16.3
v16.4
v16.5
v16.6
v16.7
v16.8
v16.9
v17.0
v17.1
v17.2
v17.3
v17.4
v17.5
v17.6

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

Latest update (v15.6):

- improved paid/completed slot locking reliability by removing stale in-request order-status caching
- added checkout thankyou fallback locking hook so completed recurring sessions are persisted as unavailable for other users

Latest update (v15.7):

- recurring booking calendars now disable dates that already have booked slot records
- prevents users from selecting previously occupied recurring dates directly from the date picker

Latest update (v15.8):

- hourly booking calendar now disables dates that already contain booked slot ranges
- fixed live availability refresh so it no longer resets non-hourly plan prices to 0 naira

Latest update (v16.0):

- added a new WooCommerce admin Workspace Bookings operations screen with table filters for date, plan, order status, payment status, and ops status
- front desk staff can update booking lifecycle states (Booked, Assigned, Checked In, Completed, No Show, Cancelled) directly from each booking row
- added seat assignment controls per booking row, including conflict checks against overlapping active bookings before assignment is saved

Latest update (v16.1):

- added one-click quick filter presets on the admin bookings page: Today, Next 2 Hours, and Action Needed
- added bulk update actions so staff can select multiple booking rows and apply seat/status updates in one submission
- added auto-refresh (45 seconds) with pause/resume control to keep front-desk booking data fresh during operations

Latest update (v16.2):

- recurring weekly and monthly session rows are now collapsible for a cleaner booking form interface
- monthly session rows now default to collapsed state with quick Expand all and Collapse all controls
- each recurring session header now shows a live summary of selected date and time and auto-expands rows with validation errors

Latest update (v16.3):

- improved recurring session toggle header contrast so session title and summary remain clearly readable
- fixed booking form mobile layout so inputs and selectors are fully visible and easier to use on small screens
- added mobile-specific responsive rules for recurring controls and collapsed session headers

Latest update (v16.4):

- product page price now dynamically updates to reflect the selected booking plan price
- syncs with WooCommerce price display in real-time as booking options change
- restores original product price when plan selection is cleared

Latest update (v16.5):

- moved booking plan prices, visible plan options, booking windows, fixed daily hours, and recurring session counts into product-level settings instead of code constants
- added hourly minimum default hours so the product page can prefill the minimum booking duration and use that value as the displayed product price
- kept existing booking behavior as the fallback default for products that have not been reconfigured yet

Latest update (v16.6):

- added a per-product advanced booking configuration toggle so existing live products keep legacy behavior until you explicitly opt in
- legacy storefront price display and booked-date blocking now remain unchanged unless advanced configuration is enabled for that product
- advanced settings can still be preconfigured in the admin before switching a product over

Latest update (v16.7):

- replaced the advanced booking configuration table with responsive plan cards in the WooCommerce product editor
- reworked the workspace-hours controls into a grid layout so labels, prices, and time selectors no longer overlap in narrow admin panels
- kept the advanced booking configuration behavior unchanged while improving the admin editing experience

Latest update (v16.8):

- advanced daily plan now lets customers choose a start time on the product page while automatically enforcing a fixed access duration
- added daily access hours plus allowed daily window controls to the advanced booking configuration in the WooCommerce product editor
- legacy daily-plan behavior remains unchanged for products that do not enable advanced configuration

Latest update (v16.9):

- fixed recurring Expand all and Collapse all controls so they work consistently for weekly 3x and weekly 5x plans, not only monthly plans
- Collapse all now always collapses every recurring office-day row instead of reusing the plan's default open state

Latest update (v17.0):

- fixed the recurring guidance heading so it remains visible when customers switch to weekly and monthly plans on the product page
- updated the recurring instruction copy to read "Please select the days you're coming."

Latest update (v17.1):

- made the booking form enforce the necessary plan-specific fields before submit so users cannot book with missing time selections
- added clear color separation for weekly and monthly recurring accordions so completed office days are visually distinct from incomplete ones

Latest update (v17.2):

- pending orders now place a temporary hold on booked slots so the same date and time cannot be taken by someone else during checkout
- storefront availability now uses the same filtered blocking-slot rules as backend validation, so stale refunded or expired holds stop appearing unavailable

Latest update (v17.3):

- added a frontend workspace bookings management screen via the `[eco_workspace_bookings]` shortcode for authorized staff users
- reused the same booking filters, seat assignment, ops-status updates, bulk actions, and auto-refresh flow from the WooCommerce admin bookings page

Latest update (v17.6):

- added pagination to the workspace bookings management table with 25 rows per page
- shows "Showing X–Y of N sessions" when results span multiple pages and "N sessions found" for single-page results
- numbered page links with Prev and Next controls, smart ellipsis gaps for large page counts
- pagination works on both the WooCommerce admin bookings page and the frontend shortcode page
- preset filter buttons (Today, Next 2 Hours, Action Needed) always reset to page 1
- all active filters are preserved across page navigation

Latest update (v17.5):

- removed the Assigned Seat column from the bookings table and merged Date and Time into a single Date & Time column
- added striped alternating row colors and a hover highlight for easier row tracking
- made the table header sticky so column labels stay visible while scrolling long lists
- added colored status badges for ops status (Booked/Assigned/Checked In/Completed/No Show/Cancelled), payment state (Paid/Unpaid), and plan type
- improved the Action column inline form to stack controls vertically for a cleaner appearance

Latest update (v17.4):

- changed the workspace bookings auto-refresh interval from 45 seconds to 10 minutes for both the admin dashboard page and the frontend shortcode page

---

# Maintainer

Ecospace Development Team
