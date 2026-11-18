<?php
/**
 * Per-session (per-date) operational status for booking items.
 *
 * A weekly/monthly booking is ONE WooCommerce order item with many session dates.
 * Operational status (booked / checked in / completed ...) is tracked per date in the
 * item meta `_eco_session_status` as a JSON map: {"2026-11-18":"completed", ...}.
 * A date missing from the map is "booked".
 *
 * Legacy meta (`_eco_checkin_status`, `_eco_checkin_dates`, `_eco_pre_checkin_status`)
 * is never deleted. For items that have no `_eco_session_status` yet, the per-date map
 * is derived from the legacy meta on read and persisted on the first write.
 * `_eco_checkin_status` is still kept up to date as a derived item-level summary.
 */

if (!defined('ABSPATH')) {
    exit;
}

function eco_is_valid_session_date($date)
{
    return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
}

/**
 * Sorted unique session dates of a booking payload.
 */
function eco_get_session_dates_from_booking($booking)
{
    $dates = array();
    foreach (eco_booking_slots_from_payload($booking) as $slot) {
        $date = isset($slot['date']) ? (string) $slot['date'] : '';
        if (eco_is_valid_session_date($date)) {
            $dates[$date] = true;
        }
    }
    $dates = array_keys($dates);
    sort($dates);
    return $dates;
}

/**
 * Derive a per-date status map from legacy item-level meta.
 *
 * @param WC_Order_Item $item
 * @param string[]      $dates Sorted session dates.
 * @param string|null   $today Y-m-d in site timezone (injectable for tests).
 * @return array<string,string>
 */
function eco_derive_legacy_session_statuses($item, $dates, $today = null)
{
    if ($today === null) {
        $today = wp_date('Y-m-d');
    }

    $legacy = sanitize_key((string) $item->get_meta('_eco_checkin_status', true));
    if ($legacy === '') {
        $legacy = 'booked';
    }

    $map = array();

    // Single-date bookings: the item status IS the session status.
    if (count($dates) <= 1) {
        foreach ($dates as $date) {
            $map[$date] = $legacy;
        }
        return $map;
    }

    $checkin_raw = (string) $item->get_meta('_eco_checkin_dates', true);
    $checkin_dates = ($checkin_raw !== '') ? json_decode($checkin_raw, true) : null;
    if (!is_array($checkin_dates)) {
        $checkin_dates = array();
    }
    $pre = sanitize_key((string) $item->get_meta('_eco_pre_checkin_status', true));
    if ($pre === '') {
        $pre = 'booked';
    }

    foreach ($dates as $date) {
        if ($legacy === 'checked_in' && !empty($checkin_dates)) {
            $map[$date] = isset($checkin_dates[$date]) ? 'checked_in' : $pre;
        } elseif ($legacy === 'completed' || $legacy === 'no_show') {
            // The old model applied "completed" to every date of the plan at once.
            // Only days that have already started can have genuinely been completed.
            $map[$date] = ($date <= $today) ? $legacy : 'booked';
        } else {
            // booked / assigned / cancelled / checked_in without a date map.
            $map[$date] = $legacy;
        }
    }

    return $map;
}

/**
 * Per-date status map for an item (always has an entry for every session date).
 *
 * @return array<string,string>
 */
function eco_get_item_session_statuses($item, $booking, $today = null)
{
    $dates = eco_get_session_dates_from_booking($booking);
    $allowed = array_keys(eco_get_ops_status_labels());

    $raw = (string) $item->get_meta('_eco_session_status', true);
    $stored = ($raw !== '') ? json_decode($raw, true) : null;

    if (!is_array($stored)) {
        return eco_derive_legacy_session_statuses($item, $dates, $today);
    }

    $map = array();
    foreach ($dates as $date) {
        $status = isset($stored[$date]) ? sanitize_key((string) $stored[$date]) : 'booked';
        $map[$date] = in_array($status, $allowed, true) ? $status : 'booked';
    }
    return $map;
}

/**
 * Item-level summary kept in `_eco_checkin_status` for backward compatibility.
 */
function eco_summarize_session_statuses($map)
{
    if (empty($map)) {
        return 'booked';
    }
    $unique = array_values(array_unique(array_values($map)));
    if (count($unique) === 1) {
        return $unique[0];
    }
    if (in_array('checked_in', $unique, true)) {
        return 'checked_in';
    }
    // Mixed: finished only if nothing is still pending.
    $pending = array_diff($unique, array('completed', 'no_show', 'cancelled'));
    if (empty($pending)) {
        return in_array('completed', $unique, true) ? 'completed' : $unique[0];
    }
    return 'booked';
}

/**
 * Set the status of one date (or every date when $session_date is '').
 * Persists the full map, so legacy items are migrated on first write.
 * Does NOT call $item->save().
 */
function eco_set_item_session_status($item, $booking, $ops_status, $session_date = '')
{
    $map = eco_get_item_session_statuses($item, $booking);

    if ($session_date !== '' && isset($map[$session_date])) {
        $map[$session_date] = $ops_status;
    } else {
        // No (or unknown) date given: apply to the whole booking.
        foreach ($map as $date => $unused) {
            $map[$date] = $ops_status;
        }
    }

    $item->update_meta_data('_eco_session_status', wp_json_encode($map));
    $item->update_meta_data('_eco_checkin_status', eco_summarize_session_statuses($map));

    return $map;
}

/**
 * Count sessions per ops status across all booking items of an order list.
 */
function eco_count_session_statuses_for_item($item, $booking, &$counts)
{
    foreach (eco_get_item_session_statuses($item, $booking) as $status) {
        if (isset($counts[$status])) {
            $counts[$status]++;
        }
    }
}
