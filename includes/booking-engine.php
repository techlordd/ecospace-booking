<?php
if (!defined('ABSPATH')) {
    exit;
}

function eco_get_plan_order()
{
    return array('hourly', 'daily', 'weekly3', 'weekly5', 'monthly3', 'monthly5');
}

function eco_get_default_booking_config()
{
    return array(
        'open_hour' => 9,
        'close_hour' => 20,
        'recurring_session_hours' => 8,
        'recurring_start_min_hour' => 9,
        'recurring_start_max_hour' => 19,
        'plans' => array(
            'hourly' => array(
                'enabled' => 'yes',
                'type' => 'hourly',
                'label' => __('Hourly', 'ecospace-booking'),
                'min_hours' => 1,
            ),
            'daily' => array(
                'enabled' => 'yes',
                'type' => 'daily',
                'label' => __('Daily', 'ecospace-booking'),
                'price' => 4800,
                'start_hour' => 9,
                'end_hour' => 20,
                'session_hours' => 8,
            ),
            'weekly3' => array(
                'enabled' => 'yes',
                'type' => 'recurring',
                'label' => __('Weekly (3x)', 'ecospace-booking'),
                'price' => 12000,
                'sessions' => 3,
                'window_value' => 7,
                'window_unit' => 'days',
            ),
            'weekly5' => array(
                'enabled' => 'yes',
                'type' => 'recurring',
                'label' => __('Weekly (5x)', 'ecospace-booking'),
                'price' => 20000,
                'sessions' => 5,
                'window_value' => 7,
                'window_unit' => 'days',
            ),
            'monthly3' => array(
                'enabled' => 'yes',
                'type' => 'recurring',
                'label' => __('Monthly (3x)', 'ecospace-booking'),
                'price' => 48000,
                'sessions' => 12,
                'window_value' => 1,
                'window_unit' => 'months',
            ),
            'monthly5' => array(
                'enabled' => 'yes',
                'type' => 'recurring',
                'label' => __('Monthly (5x)', 'ecospace-booking'),
                'price' => 80000,
                'sessions' => 20,
                'window_value' => 1,
                'window_unit' => 'months',
            ),
        ),
    );
}

function eco_get_booking_meta_value($product_id, $meta_key, $default = '')
{
    $stored = get_post_meta($product_id, $meta_key, true);
    return $stored === '' ? $default : $stored;
}

function eco_is_advanced_booking_config_enabled($product_id)
{
    $product_id = (int) $product_id;
    if ($product_id <= 0) {
        return false;
    }

    return get_post_meta($product_id, '_eco_use_advanced_config', true) === 'yes';
}

function eco_normalize_hour_value($value, $minimum, $maximum)
{
    $value = (int) $value;
    if ($value < $minimum) {
        return $minimum;
    }

    if ($value > $maximum) {
        return $maximum;
    }

    return $value;
}

function eco_get_product_booking_config($product_id, $ignore_advanced_toggle = false)
{
    $defaults = eco_get_default_booking_config();
    $product_id = (int) $product_id;

    if ($product_id <= 0) {
        return $defaults;
    }

    if (!$ignore_advanced_toggle && !eco_is_advanced_booking_config_enabled($product_id)) {
        return $defaults;
    }

    $open_hour = eco_normalize_hour_value(eco_get_booking_meta_value($product_id, '_eco_open_hour', $defaults['open_hour']), 0, 22);
    $close_hour = eco_normalize_hour_value(eco_get_booking_meta_value($product_id, '_eco_close_hour', $defaults['close_hour']), $open_hour + 1, 23);
    $recurring_start_min_hour = eco_normalize_hour_value(
        eco_get_booking_meta_value($product_id, '_eco_recurring_start_min_hour', $defaults['recurring_start_min_hour']),
        $open_hour,
        $close_hour - 1
    );
    $recurring_start_max_hour = eco_normalize_hour_value(
        eco_get_booking_meta_value($product_id, '_eco_recurring_start_max_hour', $defaults['recurring_start_max_hour']),
        $recurring_start_min_hour,
        $close_hour - 1
    );
    $recurring_session_hours = max(
        1,
        min(
            $close_hour - $recurring_start_min_hour,
            (int) eco_get_booking_meta_value($product_id, '_eco_recurring_session_hours', $defaults['recurring_session_hours'])
        )
    );

    $config = array(
        'open_hour' => $open_hour,
        'close_hour' => $close_hour,
        'recurring_session_hours' => $recurring_session_hours,
        'recurring_start_min_hour' => $recurring_start_min_hour,
        'recurring_start_max_hour' => $recurring_start_max_hour,
        'plans' => array(),
    );

    foreach (eco_get_plan_order() as $plan_key) {
        $plan_defaults = $defaults['plans'][$plan_key];
        $plan_config = $plan_defaults;

        $stored_enabled = get_post_meta($product_id, '_eco_' . $plan_key . '_enabled', true);
        if ($stored_enabled !== '') {
            $plan_config['enabled'] = ($stored_enabled === 'yes') ? 'yes' : 'no';
        }

        $stored_label = sanitize_text_field((string) eco_get_booking_meta_value($product_id, '_eco_' . $plan_key . '_label', $plan_defaults['label']));
        if ($stored_label !== '') {
            $plan_config['label'] = $stored_label;
        }

        if ($plan_key === 'hourly') {
            $plan_config['min_hours'] = max(
                1,
                min(
                    $close_hour - $open_hour,
                    absint(eco_get_booking_meta_value($product_id, '_eco_hourly_min_hours', $plan_defaults['min_hours']))
                )
            );
        } elseif ($plan_key === 'daily') {
            $plan_config['price'] = max(0, (float) eco_get_booking_meta_value($product_id, '_eco_daily_price', $plan_defaults['price']));
            $plan_config['start_hour'] = eco_normalize_hour_value(
                eco_get_booking_meta_value($product_id, '_eco_daily_start_hour', $plan_defaults['start_hour']),
                $open_hour,
                $close_hour - 1
            );
            $plan_config['end_hour'] = eco_normalize_hour_value(
                eco_get_booking_meta_value($product_id, '_eco_daily_end_hour', $plan_defaults['end_hour']),
                $plan_config['start_hour'] + 1,
                $close_hour
            );
            $plan_config['session_hours'] = max(
                1,
                min(
                    $plan_config['end_hour'] - $plan_config['start_hour'],
                    absint(eco_get_booking_meta_value($product_id, '_eco_daily_session_hours', $plan_defaults['session_hours']))
                )
            );
        } else {
            $plan_config['price'] = max(0, (float) eco_get_booking_meta_value($product_id, '_eco_' . $plan_key . '_price', $plan_defaults['price']));
            $plan_config['sessions'] = max(1, absint(eco_get_booking_meta_value($product_id, '_eco_' . $plan_key . '_sessions', $plan_defaults['sessions'])));

            $max_window_value = ($plan_defaults['window_unit'] === 'months') ? 12 : 365;
            $plan_config['window_value'] = max(
                1,
                min(
                    $max_window_value,
                    absint(eco_get_booking_meta_value($product_id, '_eco_' . $plan_key . '_window_value', $plan_defaults['window_value']))
                )
            );
        }

        $config['plans'][$plan_key] = $plan_config;
    }

    return $config;
}

function eco_get_booking_plan($product_id, $plan)
{
    $config = eco_get_product_booking_config($product_id);
    return $config['plans'][$plan] ?? null;
}

function eco_get_enabled_booking_plans($product_id)
{
    $config = eco_get_product_booking_config($product_id);
    $plans = array();

    foreach (eco_get_plan_order() as $plan_key) {
        if (($config['plans'][$plan_key]['enabled'] ?? 'no') !== 'yes') {
            continue;
        }

        $plans[$plan_key] = $config['plans'][$plan_key];
    }

    return $plans;
}

function eco_get_default_booking_plan($product_id)
{
    $enabled_plans = eco_get_enabled_booking_plans($product_id);
    $keys = array_keys($enabled_plans);
    return !empty($keys) ? (string) $keys[0] : '';
}

function eco_get_default_booking_product_price($product_id)
{
    $config = eco_get_product_booking_config($product_id);
    $hourly_rate = (float) get_post_meta($product_id, '_eco_hourly_rate', true);
    $hourly_plan = $config['plans']['hourly'] ?? array();

    if (($hourly_plan['enabled'] ?? 'no') === 'yes' && $hourly_rate > 0) {
        return max(0, $hourly_rate) * max(1, (int) ($hourly_plan['min_hours'] ?? 1));
    }

    foreach (eco_get_plan_order() as $plan_key) {
        if ($plan_key === 'hourly') {
            continue;
        }

        $plan_config = $config['plans'][$plan_key] ?? array();
        if (($plan_config['enabled'] ?? 'no') !== 'yes') {
            continue;
        }

        return max(0, (float) ($plan_config['price'] ?? 0));
    }

    return 0;
}

function eco_get_default_close_hour()
{
    $defaults = eco_get_default_booking_config();
    return (int) $defaults['close_hour'];
}

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
    $defaults = eco_get_default_booking_config();
    return isset($defaults['plans'][$plan]) && ($defaults['plans'][$plan]['type'] ?? '') === 'recurring';
}

function eco_booked_slot_meta_key($date)
{
    return '_eco_booked_slots_' . $date;
}

function eco_get_booking_pending_hold_minutes()
{
    $minutes = (int) apply_filters('eco_booking_pending_hold_minutes', 15);
    return max(1, $minutes);
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

    $status = $order->get_status();
    $blocking_statuses = array('processing', 'on-hold', 'completed');
    if (in_array($status, $blocking_statuses, true)) {
        return true;
    }

    if ($status !== 'pending') {
        return false;
    }

    $created_at = $order->get_date_created();
    if (!$created_at) {
        return false;
    }

    $hold_seconds = eco_get_booking_pending_hold_minutes() * MINUTE_IN_SECONDS;
    $age_seconds = current_time('timestamp', true) - $created_at->getTimestamp();

    return $age_seconds < $hold_seconds;
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
                    eco_format_display_date($slot['date']),
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

        $stored = eco_get_booked_slots_for_date($product_id, $date);

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

function eco_get_active_product_discount($product_id, $plan)
{
    if (get_post_meta($product_id, '_eco_discount_enabled', true) !== 'yes') {
        return 0.0;
    }

    $pct = (float) get_post_meta($product_id, '_eco_discount_percent', true);
    if ($pct <= 0) {
        return 0.0;
    }

    $today = current_time('Y-m-d');
    $start = (string) get_post_meta($product_id, '_eco_discount_start', true);
    $end   = (string) get_post_meta($product_id, '_eco_discount_end', true);

    if ($start !== '' && $today < $start) {
        return 0.0;
    }

    if ($end !== '' && $today > $end) {
        return 0.0;
    }

    $plans = (array) get_post_meta($product_id, '_eco_discount_plans', true);
    if (!empty($plans) && !in_array($plan, $plans, true)) {
        return 0.0;
    }

    return min(100.0, $pct);
}

function eco_plan_price($plan, $hourly_rate, $hours, $product_id = 0)
{
    $plan_config = eco_get_booking_plan($product_id, $plan);
    if (!$plan_config) {
        return 0;
    }

    if (($plan_config['type'] ?? '') === 'hourly') {
        $base_price = max(0, (float) $hourly_rate) * max(0, (int) $hours);
    } else {
        $base_price = max(0, (float) ($plan_config['price'] ?? 0));
    }

    if ($product_id > 0) {
        $discount_pct = eco_get_active_product_discount($product_id, $plan);
        if ($discount_pct > 0) {
            return round($base_price * (1 - $discount_pct / 100.0), 2);
        }
    }

    return $base_price;
}

function eco_get_booking_base_price($plan, $hourly_rate, $hours, $product_id = 0)
{
    $plan_config = eco_get_booking_plan($product_id, $plan);
    if (!$plan_config) {
        return 0;
    }

    if (($plan_config['type'] ?? '') === 'hourly') {
        return max(0, (float) $hourly_rate) * max(0, (int) $hours);
    }

    return max(0, (float) ($plan_config['price'] ?? 0));
}

function eco_expected_preferred_days($plan, $product_id = 0)
{
    $plan_config = eco_get_booking_plan($product_id, $plan);
    if (!$plan_config || ($plan_config['type'] ?? '') !== 'recurring') {
        return 0;
    }

    return max(0, (int) ($plan_config['sessions'] ?? 0));
}

function eco_get_plan_window_end_date($plan, DateTime $start_date, $product_id = 0)
{
    $plan_config = eco_get_booking_plan($product_id, $plan);
    $end_date = clone $start_date;

    if (!$plan_config || ($plan_config['type'] ?? '') !== 'recurring') {
        return $end_date;
    }

    $window_value = max(1, (int) ($plan_config['window_value'] ?? 1));
    if (($plan_config['window_unit'] ?? 'days') === 'months') {
        $end_date->modify('+' . $window_value . ' month' . ($window_value === 1 ? '' : 's'));
    } else {
        $end_date->modify('+' . $window_value . ' days');
    }

    return $end_date;
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

    $config = eco_get_product_booking_config($product_id);
    $allowed_plans = array_keys(eco_get_enabled_booking_plans($product_id));
    $plan = isset($_POST['eco_plan']) ? sanitize_text_field(wp_unslash($_POST['eco_plan'])) : '';
    if (!in_array($plan, $allowed_plans, true)) {
        return array('ok' => false, 'message' => __('Please choose a valid booking plan.', 'ecospace-booking'));
    }

    $start_date_raw = isset($_POST['eco_start_date']) ? sanitize_text_field(wp_unslash($_POST['eco_start_date'])) : '';
    $start_date = eco_parse_date($start_date_raw);
    if (!$start_date) {
        return array('ok' => false, 'message' => __('Please provide a valid start date.', 'ecospace-booking'));
    }

    $today = new DateTime(current_time('Y-m-d'));
    $today->setTime(0, 0, 0);
    if ($start_date < $today) {
        return array('ok' => false, 'message' => __('Bookings cannot be made for past dates.', 'ecospace-booking'));
    }

    $hourly_rate = (float) get_post_meta($product_id, '_eco_hourly_rate', true);
    $hourly_plan = $config['plans']['hourly'] ?? array();
    $daily_plan = $config['plans']['daily'] ?? array();
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
        'base_price' => 0,
        'price' => 0,
        'hourly_rate' => $hourly_rate,
    );

    if ($plan === 'hourly') {
        $start_time = isset($_POST['eco_start_time']) ? (int) sanitize_text_field(wp_unslash($_POST['eco_start_time'])) : 0;
        $hours = isset($_POST['eco_hours']) ? (int) sanitize_text_field(wp_unslash($_POST['eco_hours'])) : 0;
        $minimum_hours = max(1, (int) ($hourly_plan['min_hours'] ?? 1));

        if ($hourly_rate <= 0) {
            return array('ok' => false, 'message' => __('Hourly rate is not configured for this workspace.', 'ecospace-booking'));
        }

        if ($start_time < $config['open_hour'] || $start_time > ($config['close_hour'] - 1)) {
            return array('ok' => false, 'message' => __('Start time must be within workspace opening hours.', 'ecospace-booking'));
        }

        if ($start_date->format('Y-m-d') === $today->format('Y-m-d') && $start_time <= (int) current_time('G')) {
            return array('ok' => false, 'message' => __('Please select a future start time for today\'s booking.', 'ecospace-booking'));
        }

        if ($hours < $minimum_hours || $hours > ($config['close_hour'] - $config['open_hour'])) {
            return array('ok' => false, 'message' => sprintf(__('Please select at least %d hour(s) and stay within workspace opening hours.', 'ecospace-booking'), $minimum_hours));
        }

        $end_time = $start_time + $hours;
        if ($end_time > $config['close_hour']) {
            return array('ok' => false, 'message' => sprintf(__('Selected hours exceed closing time (%s).', 'ecospace-booking'), eco_hour_label($config['close_hour'])));
        }

        $payload['start_time'] = $start_time;
        $payload['end_time'] = $end_time;
        $payload['hours'] = $hours;
        $payload['base_price'] = eco_get_booking_base_price($plan, $hourly_rate, $hours, $product_id);
        $payload['price'] = eco_plan_price($plan, $hourly_rate, $hours, $product_id);

        $conflict_result = eco_validate_paid_slot_conflicts($product_id, eco_booking_slots_from_payload($payload));
        if (!$conflict_result['ok']) {
            return $conflict_result;
        }

        return array('ok' => true, 'data' => $payload);
    }

    if ($plan === 'daily') {
        $daily_window_start = (int) ($daily_plan['start_hour'] ?? $config['open_hour']);
        $daily_window_end = (int) ($daily_plan['end_hour'] ?? $config['close_hour']);
        $daily_session_hours = max(1, (int) ($daily_plan['session_hours'] ?? 8));
        $start_time = isset($_POST['eco_start_time']) ? (int) sanitize_text_field(wp_unslash($_POST['eco_start_time'])) : 0;

        if ($start_time < $daily_window_start || $start_time > ($daily_window_end - $daily_session_hours)) {
            return array('ok' => false, 'message' => __('Please select a valid daily time in.', 'ecospace-booking'));
        }

        if ($start_date->format('Y-m-d') === $today->format('Y-m-d') && $start_time <= (int) current_time('G')) {
            return array('ok' => false, 'message' => __('Please select a future start time for today\'s booking.', 'ecospace-booking'));
        }

        $payload['start_time'] = $start_time;
        $payload['end_time'] = $start_time + $daily_session_hours;

        if ($payload['end_time'] > $daily_window_end || $payload['end_time'] > $config['close_hour']) {
            return array('ok' => false, 'message' => sprintf(__('Daily access must end before %s.', 'ecospace-booking'), eco_hour_label(min($daily_window_end, $config['close_hour']))));
        }

        $payload['hours'] = $payload['end_time'] - $payload['start_time'];
        $payload['base_price'] = eco_get_booking_base_price($plan, $hourly_rate, 0, $product_id);
        $payload['price'] = eco_plan_price($plan, $hourly_rate, 0, $product_id);

        $conflict_result = eco_validate_paid_slot_conflicts($product_id, eco_booking_slots_from_payload($payload));
        if (!$conflict_result['ok']) {
            return $conflict_result;
        }

        return array('ok' => true, 'data' => $payload);
    }

    $end_date = eco_get_plan_window_end_date($plan, $start_date, $product_id);

    $expected_days = eco_expected_preferred_days($plan, $product_id);
    if (count($preferred_days) !== $expected_days) {
        return array('ok' => false, 'message' => sprintf(__('Please select exactly %d office dates.', 'ecospace-booking'), $expected_days));
    }

    if (count($preferred_start_times) !== $expected_days) {
        return array('ok' => false, 'message' => sprintf(__('Please select a time in for each of the %d office dates.', 'ecospace-booking'), $expected_days));
    }

    if (count($preferred_end_times) !== $expected_days) {
        return array('ok' => false, 'message' => sprintf(__('Please select a time out for each of the %d office dates.', 'ecospace-booking'), $expected_days));
    }

    $parsed_preferred = array();
    $parsed_slots = array();
    $date_keys = array();
    $slot_keys = array();
    foreach ($preferred_days as $index => $day) {
        $d = eco_parse_date($day);
        if (!$d) {
            return array('ok' => false, 'message' => __('One or more office dates are invalid.', 'ecospace-booking'));
        }

        if ($d < $start_date || $d > $end_date) {
            return array('ok' => false, 'message' => __('Office dates must stay within the booking window.', 'ecospace-booking'));
        }

        $start_time = (int) $preferred_start_times[$index];
        if ($start_time < $config['recurring_start_min_hour'] || $start_time > $config['recurring_start_max_hour']) {
            return array('ok' => false, 'message' => __('Time in must be within workspace opening hours.', 'ecospace-booking'));
        }

        if ($d->format('Y-m-d') === $today->format('Y-m-d') && $start_time <= (int) current_time('G')) {
            return array('ok' => false, 'message' => __('Please select a future start time for today\'s office dates.', 'ecospace-booking'));
        }

        $end_time = (int) $preferred_end_times[$index];
        if ($end_time <= $start_time) {
            return array('ok' => false, 'message' => __('Time out must be after time in for each office day.', 'ecospace-booking'));
        }

        if (($end_time - $start_time) > $config['recurring_session_hours']) {
            return array('ok' => false, 'message' => sprintf(__('Office day duration cannot exceed %d hours.', 'ecospace-booking'), $config['recurring_session_hours']));
        }

        if ($end_time > $config['close_hour']) {
            return array('ok' => false, 'message' => sprintf(__('Office day exceeds closing time (%s).', 'ecospace-booking'), eco_hour_label($config['close_hour'])));
        }

        $formatted_day = $d->format('Y-m-d');
        if (isset($date_keys[$formatted_day])) {
            return array('ok' => false, 'message' => __('Office dates must be unique.', 'ecospace-booking'));
        }
        $date_keys[$formatted_day] = true;

        $slot_key = $formatted_day . '|' . $start_time . '|' . $end_time;
        if (isset($slot_keys[$slot_key])) {
            return array('ok' => false, 'message' => __('Office date and time combinations must be unique.', 'ecospace-booking'));
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
    $payload['base_price'] = eco_get_booking_base_price($plan, $hourly_rate, 0, $product_id);
    $payload['price'] = eco_plan_price($plan, $hourly_rate, 0, $product_id);

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

/**
 * Format a Y-m-d date string for customer-facing display (e.g. "April 17, 2026").
 * Internal storage and comparisons continue to use Y-m-d unchanged.
 *
 * @param string $ymd Date in Y-m-d format.
 * @return string Formatted date, or the original value if parsing fails.
 */
function eco_format_display_date($ymd)
{
    if (empty($ymd)) {
        return $ymd;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $ymd);
    if (!($dt instanceof DateTime)) {
        return $ymd;
    }
    return date_i18n('F j, Y', $dt->getTimestamp());
}

function eco_plan_label($plan, $product_id = 0)
{
    $plan_config = eco_get_booking_plan($product_id, $plan);
    if ($plan_config && !empty($plan_config['label'])) {
        return (string) $plan_config['label'];
    }

    $defaults = eco_get_default_booking_config();
    return $defaults['plans'][$plan]['label'] ?? $plan;
}

function eco_format_preferred_slot($slot)
{
    if (!is_array($slot) || empty($slot['date']) || !isset($slot['start_time']) || !isset($slot['end_time'])) {
        return '';
    }

    return sprintf(
        '%s (%s - %s)',
        eco_format_display_date($slot['date']),
        eco_hour_label((int) $slot['start_time']),
        eco_hour_label((int) $slot['end_time'])
    );
}
