<?php
if (!defined('ABSPATH')) {
    exit;
}

const ECO_DAILY_PRICE = 4800;
const ECO_WEEKLY3_PRICE = 12000;
const ECO_WEEKLY5_PRICE = 20000;
const ECO_MONTHLY3_PRICE = 48000;
const ECO_MONTHLY5_PRICE = 80000;
const ECO_OPEN_HOUR = 9;
const ECO_CLOSE_HOUR = 20;
const ECO_RECURRING_SESSION_HOURS = 8;
const ECO_RECURRING_START_MIN_HOUR = 9;
const ECO_RECURRING_START_MAX_HOUR = 19;

/**
 * Strict date parser for Y-m-d values.
 */
function eco_parse_date($value)
{
    if (!is_string($value) || $value === '') {
        return false;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date) {
        return false;
    }

    $errors = DateTime::getLastErrors();
    if (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
        return false;
    }

    $date->setTime(0, 0, 0);
    return $date;
}

function eco_sanitize_preferred_days($days)
{
    if (!is_array($days)) {
        return array();
    }

    $clean = array();
    foreach ($days as $day) {
        $value = sanitize_text_field(wp_unslash($day));
        if ($value !== '') {
            $clean[] = $value;
        }
    }

    return $clean;
}

function eco_sanitize_preferred_start_times($times)
{
    if (!is_array($times)) {
        return array();
    }

    $clean = array();
    foreach ($times as $time) {
        $clean[] = (int) sanitize_text_field(wp_unslash($time));
    }

    return $clean;
}

function eco_sanitize_preferred_end_times($times)
{
    if (!is_array($times)) {
        return array();
    }

    $clean = array();
    foreach ($times as $time) {
        $clean[] = (int) sanitize_text_field(wp_unslash($time));
    }

    return $clean;
}

function eco_is_recurring_plan($plan)
{
    return in_array($plan, array('weekly3', 'weekly5', 'monthly3', 'monthly5'), true);
}

function eco_booked_slot_meta_key($date)
{
    return '_eco_booked_slots_' . $date;
}

function eco_get_booked_slots_for_date($product_id, $date)
{
    $meta_key = eco_booked_slot_meta_key($date);
    $stored = get_post_meta($product_id, $meta_key, true);
    if (!is_array($stored) || empty($stored)) {
        return array();
    }

    $filtered = array();
    $did_cleanup = false;

    foreach ($stored as $slot) {
        if (!is_array($slot) || !isset($slot['start_time']) || !isset($slot['end_time'])) {
            $did_cleanup = true;
            continue;
        }

        $order_id = isset($slot['order_id']) ? (int) $slot['order_id'] : 0;
        if (!eco_is_booking_blocking_order_status($order_id)) {
            $did_cleanup = true;
            continue;
        }

        $filtered[] = array(
            'order_id' => $order_id,
            'plan' => isset($slot['plan']) ? sanitize_text_field((string) $slot['plan']) : '',
            'start_time' => (int) $slot['start_time'],
            'end_time' => (int) $slot['end_time'],
        );
    }

    if ($did_cleanup) {
        if (empty($filtered)) {
            delete_post_meta($product_id, $meta_key);
        } else {
            update_post_meta($product_id, $meta_key, array_values($filtered));
        }
    }

    return $filtered;
}

function eco_is_booking_blocking_order_status($order_id)
{
    $order_id = (int) $order_id;
    if ($order_id <= 0) {
        return false;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return false;
    }

    $blocking_statuses = array('processing', 'on-hold', 'completed');
    return in_array($order->get_status(), $blocking_statuses, true);
}

function eco_do_slots_overlap($start_time, $end_time, $booked_start_time, $booked_end_time)
{
    return ((int) $start_time < (int) $booked_end_time) && ((int) $end_time > (int) $booked_start_time);
}

function eco_get_conflicting_booked_slot($product_id, $date, $start_time, $end_time)
{
    $booked_slots = eco_get_booked_slots_for_date($product_id, $date);
    foreach ($booked_slots as $slot) {
        if (!is_array($slot) || !isset($slot['start_time']) || !isset($slot['end_time'])) {
            continue;
        }

        if (eco_do_slots_overlap($start_time, $end_time, $slot['start_time'], $slot['end_time'])) {
            return $slot;
        }
    }

    return null;
}

function eco_validate_paid_slot_conflicts($product_id, $slots)
{
    foreach ($slots as $slot) {
        if (empty($slot['date']) || !isset($slot['start_time']) || !isset($slot['end_time'])) {
            continue;
        }

        $conflicting_slot = eco_get_conflicting_booked_slot($product_id, $slot['date'], $slot['start_time'], $slot['end_time']);
        if ($conflicting_slot) {
            return array(
                'ok' => false,
                'message' => sprintf(
                    __('%1$s (%2$s - %3$s) conflicts with an existing booking (%4$s - %5$s). Please choose a different time slot.', 'ecospace-booking'),
                    $slot['date'],
                    eco_hour_label($slot['start_time']),
                    eco_hour_label($slot['end_time']),
                    eco_hour_label($conflicting_slot['start_time']),
                    eco_hour_label($conflicting_slot['end_time'])
                ),
            );
        }
    }

    return array('ok' => true);
}

function eco_booking_slots_from_payload($booking)
{
    if (!is_array($booking) || empty($booking['plan'])) {
        return array();
    }

    if (eco_is_recurring_plan($booking['plan'])) {
        return isset($booking['preferred_slots']) && is_array($booking['preferred_slots'])
            ? $booking['preferred_slots']
            : array();
    }

    if (
        !empty($booking['start_date']) &&
        isset($booking['start_time']) &&
        isset($booking['end_time']) &&
        $booking['start_time'] !== '' &&
        $booking['end_time'] !== ''
    ) {
        return array(
            array(
                'date' => $booking['start_date'],
                'start_time' => (int) $booking['start_time'],
                'end_time' => (int) $booking['end_time'],
            ),
        );
    }

    return array();
}

function eco_get_product_booked_slot_map($product_id)
{
    $all_meta = get_post_meta($product_id);
    $slot_map = array();
    $prefix = '_eco_booked_slots_';

    foreach ($all_meta as $meta_key => $meta_values) {
        if (strpos($meta_key, $prefix) !== 0) {
            continue;
        }

        $date = substr($meta_key, strlen($prefix));
        if (!$date) {
            continue;
        }

        $stored = isset($meta_values[0]) ? maybe_unserialize($meta_values[0]) : array();
        if (!is_array($stored)) {
            continue;
        }

        $date_slots = array();
        foreach ($stored as $slot) {
            if (!is_array($slot) || !isset($slot['start_time']) || !isset($slot['end_time'])) {
                continue;
            }

            $date_slots[] = (int) $slot['start_time'] . '|' . (int) $slot['end_time'];
        }

        if (!empty($date_slots)) {
            $slot_map[$date] = array_values(array_unique($date_slots));
        }
    }

    return $slot_map;
}

function eco_lock_paid_order_slots($order_id)
{
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    if (!eco_is_booking_blocking_order_status($order_id)) {
        return;
    }

    if ($order->get_meta('_eco_slots_locked', true) === 'yes') {
        return;
    }

    foreach ($order->get_items() as $item) {
        $raw_payload = $item->get_meta('_eco_booking_payload', true);
        if (!is_string($raw_payload) || $raw_payload === '') {
            continue;
        }

        $booking = json_decode($raw_payload, true);
        if (!is_array($booking) || empty($booking['plan'])) {
            continue;
        }

        $slots_to_lock = eco_booking_slots_from_payload($booking);
        if (empty($slots_to_lock)) {
            continue;
        }

        $product_id = (int) $item->get_product_id();
        if ($product_id <= 0) {
            continue;
        }

        foreach ($slots_to_lock as $slot) {
            if (!is_array($slot) || empty($slot['date']) || !isset($slot['start_time']) || !isset($slot['end_time'])) {
                continue;
            }

            $date = $slot['date'];
            $start_time = (int) $slot['start_time'];
            $end_time = (int) $slot['end_time'];

            $meta_key = eco_booked_slot_meta_key($date);
            $booked_slots = get_post_meta($product_id, $meta_key, true);
            if (!is_array($booked_slots)) {
                $booked_slots = array();
            }

            $already_exists = false;
            foreach ($booked_slots as $booked_slot) {
                if (!is_array($booked_slot)) {
                    continue;
                }

                if (
                    ((int) ($booked_slot['order_id'] ?? 0) === (int) $order_id) &&
                    ((int) ($booked_slot['start_time'] ?? -1) === $start_time) &&
                    ((int) ($booked_slot['end_time'] ?? -1) === $end_time)
                ) {
                    $already_exists = true;
                    break;
                }
            }

            if ($already_exists) {
                continue;
            }

            $booked_slots[] = array(
                'order_id' => (int) $order_id,
                'plan' => sanitize_text_field($booking['plan']),
                'start_time' => $start_time,
                'end_time' => $end_time,
            );

            update_post_meta($product_id, $meta_key, $booked_slots);
        }
    }

    $order->update_meta_data('_eco_slots_locked', 'yes');
    $order->save();
}

function eco_unlock_order_slots($order_id)
{
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    foreach ($order->get_items() as $item) {
        $raw_payload = $item->get_meta('_eco_booking_payload', true);
        if (!is_string($raw_payload) || $raw_payload === '') {
            continue;
        }

        $booking = json_decode($raw_payload, true);
        if (!is_array($booking) || empty($booking['plan'])) {
            continue;
        }

        $slots_to_unlock = eco_booking_slots_from_payload($booking);
        if (empty($slots_to_unlock)) {
            continue;
        }

        $product_id = (int) $item->get_product_id();
        if ($product_id <= 0) {
            continue;
        }

        foreach ($slots_to_unlock as $slot) {
            if (!is_array($slot) || empty($slot['date']) || !isset($slot['start_time']) || !isset($slot['end_time'])) {
                continue;
            }

            $date = $slot['date'];
            $start_time = (int) $slot['start_time'];
            $end_time = (int) $slot['end_time'];
            $meta_key = eco_booked_slot_meta_key($date);
            $booked_slots = get_post_meta($product_id, $meta_key, true);

            if (!is_array($booked_slots) || empty($booked_slots)) {
                continue;
            }

            $filtered_slots = array_values(
                array_filter(
                    $booked_slots,
                    static function ($booked_slot) use ($order_id, $start_time, $end_time) {
                        if (!is_array($booked_slot)) {
                            return false;
                        }

                        return !(
                            ((int) ($booked_slot['order_id'] ?? 0) === (int) $order_id) &&
                            ((int) ($booked_slot['start_time'] ?? -1) === $start_time) &&
                            ((int) ($booked_slot['end_time'] ?? -1) === $end_time)
                        );
                    }
                )
            );

            if (empty($filtered_slots)) {
                delete_post_meta($product_id, $meta_key);
            } else {
                update_post_meta($product_id, $meta_key, $filtered_slots);
            }
        }
    }

    $order->update_meta_data('_eco_slots_locked', 'no');
    $order->save();
}

function eco_rebuild_product_booked_slots($product_id)
{
    $product_id = (int) $product_id;
    if ($product_id <= 0) {
        return array('dates' => 0, 'slots' => 0, 'orders' => 0);
    }

    $all_meta = get_post_meta($product_id);
    $prefix = '_eco_booked_slots_';
    foreach ($all_meta as $meta_key => $meta_values) {
        if (strpos($meta_key, $prefix) !== 0) {
            continue;
        }

        delete_post_meta($product_id, $meta_key);
    }

    $order_ids = wc_get_orders(
        array(
            'status' => array('processing', 'on-hold', 'completed'),
            'limit' => -1,
            'return' => 'ids',
        )
    );

    $slot_map = array();
    $orders_touched = 0;

    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            continue;
        }

        $order_has_product_slot = false;

        foreach ($order->get_items() as $item) {
            if ((int) $item->get_product_id() !== $product_id) {
                continue;
            }

            $raw_payload = $item->get_meta('_eco_booking_payload', true);
            if (!is_string($raw_payload) || $raw_payload === '') {
                continue;
            }

            $booking = json_decode($raw_payload, true);
            if (!is_array($booking) || empty($booking['plan'])) {
                continue;
            }

            $slots = eco_booking_slots_from_payload($booking);
            if (empty($slots)) {
                continue;
            }

            foreach ($slots as $slot) {
                if (!is_array($slot) || empty($slot['date']) || !isset($slot['start_time']) || !isset($slot['end_time'])) {
                    continue;
                }

                $date = $slot['date'];
                $start_time = (int) $slot['start_time'];
                $end_time = (int) $slot['end_time'];

                if (!isset($slot_map[$date])) {
                    $slot_map[$date] = array();
                }

                $slot_key = $order_id . '|' . $start_time . '|' . $end_time;
                $slot_map[$date][$slot_key] = array(
                    'order_id' => (int) $order_id,
                    'plan' => sanitize_text_field((string) $booking['plan']),
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                );
                $order_has_product_slot = true;
            }
        }

        if ($order_has_product_slot) {
            $orders_touched += 1;
            $order->update_meta_data('_eco_slots_locked', 'yes');
            $order->save();
        }
    }

    $total_slots = 0;
    foreach ($slot_map as $date => $slots_by_key) {
        $slots = array_values($slots_by_key);
        if (empty($slots)) {
            continue;
        }

        update_post_meta($product_id, eco_booked_slot_meta_key($date), $slots);
        $total_slots += count($slots);
    }

    return array(
        'dates' => count($slot_map),
        'slots' => $total_slots,
        'orders' => $orders_touched,
    );
}

function eco_plan_price($plan, $hourly_rate, $hours)
{
    switch ($plan) {
        case 'hourly':
            return max(0, (float) $hourly_rate) * max(0, (int) $hours);
        case 'daily':
            return ECO_DAILY_PRICE;
        case 'weekly3':
            return ECO_WEEKLY3_PRICE;
        case 'weekly5':
            return ECO_WEEKLY5_PRICE;
        case 'monthly3':
            return ECO_MONTHLY3_PRICE;
        case 'monthly5':
            return ECO_MONTHLY5_PRICE;
        default:
            return 0;
    }
}

function eco_expected_preferred_days($plan)
{
    switch ($plan) {
        case 'weekly3':
            return 3;
        case 'weekly5':
            return 5;
        case 'monthly3':
            return 12;
        case 'monthly5':
            return 20;
        default:
            return 0;
    }
}

/**
 * Validates posted booking data and returns normalized values.
 */
function eco_validate_booking_payload($product_id)
{
    $enabled = get_post_meta($product_id, '_eco_enable_booking', true);
    if ($enabled !== 'yes') {
        return array(
            'ok' => true,
            'data' => array(),
        );
    }

    $allowed_plans = array('hourly', 'daily', 'weekly3', 'weekly5', 'monthly3', 'monthly5');
    $plan = isset($_POST['eco_plan']) ? sanitize_text_field(wp_unslash($_POST['eco_plan'])) : '';
    if (!in_array($plan, $allowed_plans, true)) {
        return array('ok' => false, 'message' => __('Please choose a valid booking plan.', 'ecospace-booking'));
    }

    $start_date_raw = isset($_POST['eco_start_date']) ? sanitize_text_field(wp_unslash($_POST['eco_start_date'])) : '';
    $start_date = eco_parse_date($start_date_raw);
    if (!$start_date) {
        return array('ok' => false, 'message' => __('Please provide a valid start date.', 'ecospace-booking'));
    }

    $hourly_rate = (float) get_post_meta($product_id, '_eco_hourly_rate', true);
    $preferred_days = isset($_POST['eco_preferred_days']) ? eco_sanitize_preferred_days($_POST['eco_preferred_days']) : array();
    $preferred_start_times = isset($_POST['eco_preferred_start_times']) ? eco_sanitize_preferred_start_times($_POST['eco_preferred_start_times']) : array();
    $preferred_end_times = isset($_POST['eco_preferred_end_times']) ? eco_sanitize_preferred_end_times($_POST['eco_preferred_end_times']) : array();

    $payload = array(
        'plan' => $plan,
        'start_date' => $start_date->format('Y-m-d'),
        'end_date' => '',
        'preferred_days' => array(),
        'preferred_slots' => array(),
        'start_time' => '',
        'end_time' => '',
        'hours' => 0,
        'price' => 0,
        'hourly_rate' => $hourly_rate,
    );

    if ($plan === 'hourly') {
        $start_time = isset($_POST['eco_start_time']) ? (int) sanitize_text_field(wp_unslash($_POST['eco_start_time'])) : 0;
        $hours = isset($_POST['eco_hours']) ? (int) sanitize_text_field(wp_unslash($_POST['eco_hours'])) : 0;

        if ($hourly_rate <= 0) {
            return array('ok' => false, 'message' => __('Hourly rate is not configured for this workspace.', 'ecospace-booking'));
        }

        if ($start_time < ECO_OPEN_HOUR || $start_time > (ECO_CLOSE_HOUR - 1)) {
            return array('ok' => false, 'message' => __('Start time must be within workspace opening hours.', 'ecospace-booking'));
        }

        if ($hours < 1 || $hours > (ECO_CLOSE_HOUR - ECO_OPEN_HOUR)) {
            return array('ok' => false, 'message' => __('Please select a valid number of hours.', 'ecospace-booking'));
        }

        $end_time = $start_time + $hours;
        if ($end_time > ECO_CLOSE_HOUR) {
            return array('ok' => false, 'message' => __('Selected hours exceed closing time (8:00 PM).', 'ecospace-booking'));
        }

        $payload['start_time'] = $start_time;
        $payload['end_time'] = $end_time;
        $payload['hours'] = $hours;
        $payload['price'] = eco_plan_price($plan, $hourly_rate, $hours);

        $conflict_result = eco_validate_paid_slot_conflicts($product_id, eco_booking_slots_from_payload($payload));
        if (!$conflict_result['ok']) {
            return $conflict_result;
        }

        return array('ok' => true, 'data' => $payload);
    }

    if ($plan === 'daily') {
        $payload['start_time'] = ECO_OPEN_HOUR;
        $payload['end_time'] = ECO_CLOSE_HOUR;
        $payload['hours'] = ECO_CLOSE_HOUR - ECO_OPEN_HOUR;
        $payload['price'] = eco_plan_price($plan, $hourly_rate, 0);

        $conflict_result = eco_validate_paid_slot_conflicts($product_id, eco_booking_slots_from_payload($payload));
        if (!$conflict_result['ok']) {
            return $conflict_result;
        }

        return array('ok' => true, 'data' => $payload);
    }

    $end_date = clone $start_date;
    if (strpos($plan, 'weekly') === 0) {
        $end_date->modify('+7 days');
    } else {
        $end_date->modify('+1 month');
    }

    $expected_days = eco_expected_preferred_days($plan);
    if (count($preferred_days) !== $expected_days) {
        return array('ok' => false, 'message' => sprintf(__('Please select exactly %d preferred dates.', 'ecospace-booking'), $expected_days));
    }

    if (count($preferred_start_times) !== $expected_days) {
        return array('ok' => false, 'message' => sprintf(__('Please select a preferred start time for each of the %d preferred dates.', 'ecospace-booking'), $expected_days));
    }

    if (count($preferred_end_times) !== $expected_days) {
        return array('ok' => false, 'message' => sprintf(__('Please select a preferred end time for each of the %d preferred dates.', 'ecospace-booking'), $expected_days));
    }

    $parsed_preferred = array();
    $parsed_slots = array();
    $date_keys = array();
    $slot_keys = array();
    foreach ($preferred_days as $index => $day) {
        $d = eco_parse_date($day);
        if (!$d) {
            return array('ok' => false, 'message' => __('One or more preferred dates are invalid.', 'ecospace-booking'));
        }

        if ($d < $start_date || $d > $end_date) {
            return array('ok' => false, 'message' => __('Preferred dates must stay within the booking window.', 'ecospace-booking'));
        }

        $start_time = (int) $preferred_start_times[$index];
        if ($start_time < ECO_RECURRING_START_MIN_HOUR || $start_time > ECO_RECURRING_START_MAX_HOUR) {
            return array('ok' => false, 'message' => __('Recurring start time must be within workspace opening hours.', 'ecospace-booking'));
        }

        $end_time = (int) $preferred_end_times[$index];
        if ($end_time <= $start_time) {
            return array('ok' => false, 'message' => __('End time must be after start time for each preferred session.', 'ecospace-booking'));
        }

        if (($end_time - $start_time) > ECO_RECURRING_SESSION_HOURS) {
            return array('ok' => false, 'message' => __('Preferred session duration cannot exceed 8 hours.', 'ecospace-booking'));
        }

        if ($end_time > ECO_CLOSE_HOUR) {
            return array('ok' => false, 'message' => __('Recurring session exceeds closing time (8:00 PM).', 'ecospace-booking'));
        }

        $formatted_day = $d->format('Y-m-d');
        if (isset($date_keys[$formatted_day])) {
            return array('ok' => false, 'message' => __('Preferred dates must be unique.', 'ecospace-booking'));
        }
        $date_keys[$formatted_day] = true;

        $slot_key = $formatted_day . '|' . $start_time . '|' . $end_time;
        if (isset($slot_keys[$slot_key])) {
            return array('ok' => false, 'message' => __('Preferred date and time combinations must be unique.', 'ecospace-booking'));
        }
        $slot_keys[$slot_key] = true;

        $parsed_preferred[] = $formatted_day;
        $parsed_slots[] = array(
            'date' => $formatted_day,
            'start_time' => $start_time,
            'end_time' => $end_time,
        );
    }

    usort(
        $parsed_slots,
        static function ($a, $b) {
            if ($a['date'] === $b['date']) {
                return $a['start_time'] <=> $b['start_time'];
            }

            return strcmp($a['date'], $b['date']);
        }
    );

    $parsed_preferred = array_map(
        static function ($slot) {
            return $slot['date'];
        },
        $parsed_slots
    );

    $payload['end_date'] = $end_date->format('Y-m-d');
    $payload['preferred_days'] = $parsed_preferred;
    $payload['preferred_slots'] = $parsed_slots;
    $payload['price'] = eco_plan_price($plan, $hourly_rate, 0);

    $conflict_result = eco_validate_paid_slot_conflicts($product_id, $parsed_slots);
    if (!$conflict_result['ok']) {
        return $conflict_result;
    }

    return array('ok' => true, 'data' => $payload);
}

function eco_hour_label($hour)
{
    $hour = (int) $hour;
    $suffix = $hour >= 12 ? 'PM' : 'AM';
    $normalized = $hour % 12;
    if ($normalized === 0) {
        $normalized = 12;
    }

    return sprintf('%d:00 %s', $normalized, $suffix);
}

function eco_plan_label($plan)
{
    $labels = array(
        'hourly' => __('Hourly', 'ecospace-booking'),
        'daily' => __('Daily', 'ecospace-booking'),
        'weekly3' => __('Weekly (3x)', 'ecospace-booking'),
        'weekly5' => __('Weekly (5x)', 'ecospace-booking'),
        'monthly3' => __('Monthly (3x)', 'ecospace-booking'),
        'monthly5' => __('Monthly (5x)', 'ecospace-booking'),
    );

    return $labels[$plan] ?? $plan;
}

function eco_format_preferred_slot($slot)
{
    if (!is_array($slot) || empty($slot['date']) || !isset($slot['start_time']) || !isset($slot['end_time'])) {
        return '';
    }

    return sprintf(
        '%s (%s - %s)',
        $slot['date'],
        eco_hour_label((int) $slot['start_time']),
        eco_hour_label((int) $slot['end_time'])
    );
}
