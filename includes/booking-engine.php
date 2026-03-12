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

    $payload = array(
        'plan' => $plan,
        'start_date' => $start_date->format('Y-m-d'),
        'end_date' => '',
        'preferred_days' => array(),
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

        return array('ok' => true, 'data' => $payload);
    }

    if ($plan === 'daily') {
        $payload['start_time'] = ECO_OPEN_HOUR;
        $payload['end_time'] = ECO_CLOSE_HOUR;
        $payload['hours'] = ECO_CLOSE_HOUR - ECO_OPEN_HOUR;
        $payload['price'] = eco_plan_price($plan, $hourly_rate, 0);

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

    if (count(array_unique($preferred_days)) !== count($preferred_days)) {
        return array('ok' => false, 'message' => __('Preferred dates must be unique.', 'ecospace-booking'));
    }

    $parsed_preferred = array();
    foreach ($preferred_days as $day) {
        $d = eco_parse_date($day);
        if (!$d) {
            return array('ok' => false, 'message' => __('One or more preferred dates are invalid.', 'ecospace-booking'));
        }

        if ($d < $start_date || $d > $end_date) {
            return array('ok' => false, 'message' => __('Preferred dates must stay within the booking window.', 'ecospace-booking'));
        }

        $parsed_preferred[] = $d->format('Y-m-d');
    }

    sort($parsed_preferred);

    $payload['end_date'] = $end_date->format('Y-m-d');
    $payload['preferred_days'] = $parsed_preferred;
    $payload['price'] = eco_plan_price($plan, $hourly_rate, 0);

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
