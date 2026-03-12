
# Contributing Guide

Thank you for contributing to the Ecospace Workspace Booking Plugin.

This project powers a coworking booking system built on **WordPress + WooCommerce**.

## Development Goals

Contributions should aim to:

- Improve booking reliability
- Prevent double booking
- Improve calendar usability
- Maintain WooCommerce compatibility
- Keep code modular and readable

---

## Coding Standards

### PHP
- Follow WordPress coding standards
- Avoid direct output before headers
- Escape output using `esc_html()`, `esc_attr()` where necessary
- Sanitize user inputs

Example:

```php
$value = sanitize_text_field($_POST['field']);
echo esc_html($value);
```

---

### JavaScript
- Use clear function names
- Avoid global variable pollution
- Separate logic into reusable functions

Example:

```javascript
function calculateHourlyPrice(rate, hours){
  return rate * hours;
}
```

---

## Branch Strategy

Use feature branches.

Example:

```
main
feature/calendar-improvement
feature/capacity-tracking
bugfix/hourly-time-calculation
```

---

## Commit Messages

Use clear messages.

Good:

```
Add weekly booking date validation
Fix hourly end time calculation
Implement seat capacity indicator
```

Bad:

```
fix stuff
update code
```

---

## Pull Requests

When submitting a PR:

Include:

- Description of change
- Screenshot if UI change
- Steps to test

---

## Testing Checklist

Before submitting code:

- Plugin activates without warnings
- WooCommerce product page loads correctly
- Hourly bookings calculate correctly
- Weekly plans enforce date range
- Monthly plans enforce grouped week selections

---

## Future Contribution Areas

- Booking availability engine
- Real-time seat capacity tracking
- Admin booking calendar dashboard
- WooCommerce order integration improvements
- Booking conflict detection

