<?php
if (!defined('ABSPATH')) {
    exit;
}

function eco_admin_booking_hour_options()
{
    $options = array();
    for ($hour = 0; $hour <= 23; $hour++) {
        $options[(string) $hour] = eco_hour_label($hour);
    }

    return $options;
}

function eco_render_booking_admin_select($field_name, $selected_value, $options)
{
    echo '<select name="' . esc_attr($field_name) . '">';
    foreach ($options as $option_value => $option_label) {
        echo '<option value="' . esc_attr((string) $option_value) . '" ' . selected((string) $selected_value, (string) $option_value, false) . '>' . esc_html($option_label) . '</option>';
    }
    echo '</select>';
}

add_action('woocommerce_product_options_general_product_data', 'eco_product_fields');
function eco_product_fields()
{
    $product_id = get_the_ID();
    $config = $product_id ? eco_get_product_booking_config($product_id) : eco_get_default_booking_config();
    $hour_options = eco_admin_booking_hour_options();

    echo '<div class="options_group">';

    woocommerce_wp_checkbox(
        array(
            'id' => '_eco_enable_booking',
            'label' => __('Enable Workspace Booking', 'ecospace-booking'),
        )
    );

    woocommerce_wp_text_input(
        array(
            'id' => '_eco_hourly_rate',
            'label' => __('Hourly Rate', 'ecospace-booking'),
            'type' => 'number',
            'custom_attributes' => array(
                'min' => '0',
                'step' => '0.01',
            ),
        )
    );

    woocommerce_wp_text_input(
        array(
            'id' => '_eco_seat_capacity',
            'label' => __('Seat Capacity', 'ecospace-booking'),
            'type' => 'number',
            'custom_attributes' => array(
                'min' => '0',
                'step' => '1',
            ),
        )
    );

    echo '<div class="form-field">';
    echo '<label>' . esc_html__('Workspace Hours', 'ecospace-booking') . '</label>';
    echo '<div style="padding:0 12px 12px;">';
    echo '<table class="widefat striped" style="max-width:960px;">';
    echo '<tbody>';
    echo '<tr>';
    echo '<th style="width:180px;">' . esc_html__('Opening Hour', 'ecospace-booking') . '</th>';
    echo '<td>';
    eco_render_booking_admin_select('_eco_open_hour', $config['open_hour'], $hour_options);
    echo '</td>';
    echo '<th style="width:180px;">' . esc_html__('Closing Hour', 'ecospace-booking') . '</th>';
    echo '<td>';
    eco_render_booking_admin_select('_eco_close_hour', $config['close_hour'], $hour_options);
    echo '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th>' . esc_html__('Recurring Start Min', 'ecospace-booking') . '</th>';
    echo '<td>';
    eco_render_booking_admin_select('_eco_recurring_start_min_hour', $config['recurring_start_min_hour'], $hour_options);
    echo '</td>';
    echo '<th>' . esc_html__('Recurring Start Max', 'ecospace-booking') . '</th>';
    echo '<td>';
    eco_render_booking_admin_select('_eco_recurring_start_max_hour', $config['recurring_start_max_hour'], $hour_options);
    echo '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th>' . esc_html__('Recurring Max Session Hours', 'ecospace-booking') . '</th>';
    echo '<td colspan="3"><input type="number" name="_eco_recurring_session_hours" value="' . esc_attr((string) $config['recurring_session_hours']) . '" min="1" step="1" style="width:120px;"></td>';
    echo '</tr>';
    echo '</tbody>';
    echo '</table>';
    echo '<p class="description" style="margin-top:8px;">' . esc_html__('These values power validation and the product page selectors. If you leave them unchanged, the current booking behavior stays the same.', 'ecospace-booking') . '</p>';
    echo '</div>';
    echo '</div>';

    echo '<div class="form-field">';
    echo '<label>' . esc_html__('Plan Configuration', 'ecospace-booking') . '</label>';
    echo '<div style="padding:0 12px 12px;">';
    echo '<table class="widefat striped" style="max-width:960px;">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Plan', 'ecospace-booking') . '</th>';
    echo '<th>' . esc_html__('Enabled', 'ecospace-booking') . '</th>';
    echo '<th>' . esc_html__('Customer Label', 'ecospace-booking') . '</th>';
    echo '<th>' . esc_html__('Price', 'ecospace-booking') . '</th>';
    echo '<th>' . esc_html__('Dates / Hours', 'ecospace-booking') . '</th>';
    echo '<th>' . esc_html__('Window / Time', 'ecospace-booking') . '</th>';
    echo '</tr></thead><tbody>';

    foreach (eco_get_plan_order() as $plan_key) {
        $plan = $config['plans'][$plan_key];

        echo '<tr>';
        echo '<td><strong>' . esc_html(eco_plan_label($plan_key)) . '</strong></td>';
        echo '<td><label><input type="checkbox" name="_eco_' . esc_attr($plan_key) . '_enabled" value="yes" ' . checked($plan['enabled'], 'yes', false) . '> ' . esc_html__('Show on product page', 'ecospace-booking') . '</label></td>';
        echo '<td><input type="text" name="_eco_' . esc_attr($plan_key) . '_label" value="' . esc_attr((string) $plan['label']) . '" style="width:100%;"></td>';

        if (($plan['type'] ?? '') === 'hourly') {
            echo '<td><span class="description">' . esc_html__('Uses Hourly Rate', 'ecospace-booking') . '</span></td>';
            echo '<td><label>' . esc_html__('Minimum default hours', 'ecospace-booking') . ' <input type="number" name="_eco_hourly_min_hours" value="' . esc_attr((string) $plan['min_hours']) . '" min="1" step="1" style="width:90px;"></label></td>';
            echo '<td><span class="description">' . esc_html__('Customers can book within the workspace opening window.', 'ecospace-booking') . '</span></td>';
        } elseif (($plan['type'] ?? '') === 'daily') {
            echo '<td><input type="number" name="_eco_daily_price" value="' . esc_attr(wc_format_localized_price((string) $plan['price'])) . '" min="0" step="0.01" style="width:120px;"></td>';
            echo '<td><span class="description">' . esc_html__('Fixed daily booking block', 'ecospace-booking') . '</span></td>';
            echo '<td style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">';
            echo '<span>' . esc_html__('From', 'ecospace-booking') . '</span>';
            eco_render_booking_admin_select('_eco_daily_start_hour', $plan['start_hour'], $hour_options);
            echo '<span>' . esc_html__('to', 'ecospace-booking') . '</span>';
            eco_render_booking_admin_select('_eco_daily_end_hour', $plan['end_hour'], $hour_options);
            echo '</td>';
        } else {
            $window_unit_label = (($plan['window_unit'] ?? 'days') === 'months') ? __('month(s)', 'ecospace-booking') : __('day(s)', 'ecospace-booking');

            echo '<td><input type="number" name="_eco_' . esc_attr($plan_key) . '_price" value="' . esc_attr(wc_format_localized_price((string) $plan['price'])) . '" min="0" step="0.01" style="width:120px;"></td>';
            echo '<td><label>' . esc_html__('Preferred sessions', 'ecospace-booking') . ' <input type="number" name="_eco_' . esc_attr($plan_key) . '_sessions" value="' . esc_attr((string) $plan['sessions']) . '" min="1" step="1" style="width:90px;"></label></td>';
            echo '<td><label>' . esc_html__('Booking window', 'ecospace-booking') . ' <input type="number" name="_eco_' . esc_attr($plan_key) . '_window_value" value="' . esc_attr((string) $plan['window_value']) . '" min="1" step="1" style="width:90px;"></label> <span class="description">' . esc_html($window_unit_label) . '</span></td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '<p class="description" style="margin-top:8px;">' . esc_html__('Use this table to control which plans appear on the product page, adjust labels, change prices, and set the number of preferred dates or hours without editing code.', 'ecospace-booking') . '</p>';
    echo '</div>';
    echo '</div>';

    echo '<p class="form-field">';
    echo '<span class="description">' . esc_html__('Recurring plans still require a preferred date, start time, and end time per session. Validation now follows the product settings above instead of fixed code values.', 'ecospace-booking') . '</span>';
    echo '</p>';

    if ($product_id && current_user_can('edit_post', $product_id)) {
        $rebuild_url = wp_nonce_url(
            add_query_arg(
                array(
                    'action' => 'eco_rebuild_product_slots',
                    'product_id' => (int) $product_id,
                ),
                admin_url('admin-post.php')
            ),
            'eco_rebuild_product_slots_' . (int) $product_id
        );

        echo '<p class="form-field">';
        echo '<a class="button button-secondary" href="' . esc_url($rebuild_url) . '">' . esc_html__('Rebuild booked slots from valid orders', 'ecospace-booking') . '</a>';
        echo '<span class="description" style="display:block; margin-top:8px;">' . esc_html__('Use this if historical booking slots look incorrect. It rebuilds this product\'s booked slot meta from processing, on-hold, and completed orders.', 'ecospace-booking') . '</span>';
        echo '</p>';
    }

    echo '</div>';
}

add_action('woocommerce_process_product_meta', 'eco_save_fields');
function eco_save_fields($post_id)
{
    update_post_meta($post_id, '_eco_enable_booking', isset($_POST['_eco_enable_booking']) ? 'yes' : 'no');

    if (isset($_POST['_eco_hourly_rate'])) {
        update_post_meta($post_id, '_eco_hourly_rate', wc_format_decimal(wp_unslash($_POST['_eco_hourly_rate'])));
    }

    if (isset($_POST['_eco_seat_capacity'])) {
        update_post_meta($post_id, '_eco_seat_capacity', absint(wp_unslash($_POST['_eco_seat_capacity'])));
    }

    foreach (array('hourly', 'daily', 'weekly3', 'weekly5', 'monthly3', 'monthly5') as $plan_key) {
        update_post_meta($post_id, '_eco_' . $plan_key . '_enabled', isset($_POST['_eco_' . $plan_key . '_enabled']) ? 'yes' : 'no');

        if (isset($_POST['_eco_' . $plan_key . '_label'])) {
            update_post_meta($post_id, '_eco_' . $plan_key . '_label', sanitize_text_field(wp_unslash($_POST['_eco_' . $plan_key . '_label'])));
        }
    }

    foreach (array(
        '_eco_open_hour',
        '_eco_close_hour',
        '_eco_recurring_start_min_hour',
        '_eco_recurring_start_max_hour',
        '_eco_recurring_session_hours',
        '_eco_hourly_min_hours',
        '_eco_daily_start_hour',
        '_eco_daily_end_hour',
        '_eco_weekly3_sessions',
        '_eco_weekly5_sessions',
        '_eco_monthly3_sessions',
        '_eco_monthly5_sessions',
        '_eco_weekly3_window_value',
        '_eco_weekly5_window_value',
        '_eco_monthly3_window_value',
        '_eco_monthly5_window_value',
    ) as $meta_key) {
        if (isset($_POST[$meta_key])) {
            update_post_meta($post_id, $meta_key, absint(wp_unslash($_POST[$meta_key])));
        }
    }

    foreach (array(
        '_eco_daily_price',
        '_eco_weekly3_price',
        '_eco_weekly5_price',
        '_eco_monthly3_price',
        '_eco_monthly5_price',
    ) as $meta_key) {
        if (isset($_POST[$meta_key])) {
            update_post_meta($post_id, $meta_key, wc_format_decimal(wp_unslash($_POST[$meta_key])));
        }
    }
}

add_action('admin_post_eco_rebuild_product_slots', 'eco_rebuild_product_slots_action');
function eco_rebuild_product_slots_action()
{
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You are not allowed to rebuild booking slots.', 'ecospace-booking'));
    }

    $product_id = isset($_GET['product_id']) ? absint(wp_unslash($_GET['product_id'])) : 0;
    if ($product_id <= 0 || !current_user_can('edit_post', $product_id)) {
        wp_die(esc_html__('Invalid product for slot rebuild.', 'ecospace-booking'));
    }

    check_admin_referer('eco_rebuild_product_slots_' . $product_id);

    $result = eco_rebuild_product_booked_slots($product_id);

    $redirect_url = add_query_arg(
        array(
            'post' => $product_id,
            'action' => 'edit',
            'eco_slots_rebuilt' => '1',
            'eco_slots_rebuilt_dates' => (int) ($result['dates'] ?? 0),
            'eco_slots_rebuilt_total' => (int) ($result['slots'] ?? 0),
            'eco_slots_rebuilt_orders' => (int) ($result['orders'] ?? 0),
        ),
        admin_url('post.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}

add_action('admin_notices', 'eco_rebuild_slots_admin_notice');
function eco_rebuild_slots_admin_notice()
{
    if (!is_admin()) {
        return;
    }

    if (!isset($_GET['eco_slots_rebuilt']) || sanitize_text_field(wp_unslash($_GET['eco_slots_rebuilt'])) !== '1') {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && $screen->id !== 'product') {
        return;
    }

    $dates = isset($_GET['eco_slots_rebuilt_dates']) ? absint(wp_unslash($_GET['eco_slots_rebuilt_dates'])) : 0;
    $slots = isset($_GET['eco_slots_rebuilt_total']) ? absint(wp_unslash($_GET['eco_slots_rebuilt_total'])) : 0;
    $orders = isset($_GET['eco_slots_rebuilt_orders']) ? absint(wp_unslash($_GET['eco_slots_rebuilt_orders'])) : 0;

    printf(
        '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
        esc_html(
            sprintf(
                __('Booked slots rebuilt. Orders scanned: %1$d, dates updated: %2$d, slots written: %3$d.', 'ecospace-booking'),
                $orders,
                $dates,
                $slots
            )
        )
    );
}

add_action('admin_menu', 'eco_register_workspace_bookings_menu');
function eco_register_workspace_bookings_menu()
{
    add_submenu_page(
        'woocommerce',
        __('Workspace Bookings', 'ecospace-booking'),
        __('Workspace Bookings', 'ecospace-booking'),
        'manage_woocommerce',
        'eco-workspace-bookings',
        'eco_render_workspace_bookings_page'
    );
}

add_action('admin_post_eco_update_booking_ops', 'eco_update_booking_ops_action');
add_action('admin_post_eco_bulk_update_booking_ops', 'eco_bulk_update_booking_ops_action');

function eco_get_all_wc_status_keys()
{
    $keys = array();
    foreach (array_keys(wc_get_order_statuses()) as $status_key) {
        $keys[] = str_replace('wc-', '', $status_key);
    }

    return array_values(array_unique($keys));
}

function eco_get_ops_status_labels()
{
    return array(
        'booked' => __('Booked', 'ecospace-booking'),
        'assigned' => __('Assigned', 'ecospace-booking'),
        'checked_in' => __('Checked In', 'ecospace-booking'),
        'completed' => __('Completed', 'ecospace-booking'),
        'no_show' => __('No Show', 'ecospace-booking'),
        'cancelled' => __('Cancelled', 'ecospace-booking'),
    );
}

function eco_get_booking_enabled_products_for_assignment()
{
    $product_ids = wc_get_products(
        array(
            'limit' => 300,
            'status' => 'publish',
            'return' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_eco_enable_booking',
                    'value' => 'yes',
                    'compare' => '=',
                ),
            ),
        )
    );

    $options = array();
    foreach ($product_ids as $product_id) {
        $options[(int) $product_id] = get_the_title($product_id);
    }

    asort($options);
    return $options;
}

function eco_parse_booking_payload_from_item($item)
{
    $payload = $item->get_meta('_eco_booking_payload', true);
    if (is_array($payload)) {
        return $payload;
    }

    if (!is_string($payload) || $payload === '') {
        return null;
    }

    $decoded = json_decode($payload, true);
    return is_array($decoded) ? $decoded : null;
}

function eco_should_include_ops_row($row, $filters)
{
    if (!empty($filters['date']) && $row['session_date'] !== $filters['date']) {
        return false;
    }

    if (!empty($filters['window_start']) && !empty($filters['window_end'])) {
        if ($row['session_end'] <= (int) $filters['window_start'] || $row['session_start'] >= (int) $filters['window_end']) {
            return false;
        }
    }

    if (!empty($filters['plan']) && $row['plan'] !== $filters['plan']) {
        return false;
    }

    if (!empty($filters['order_status']) && $row['order_status'] !== $filters['order_status']) {
        return false;
    }

    if (!empty($filters['payment']) && $filters['payment'] !== 'all') {
        if ($filters['payment'] === 'paid' && !$row['is_paid']) {
            return false;
        }

        if ($filters['payment'] === 'unpaid' && $row['is_paid']) {
            return false;
        }
    }

    if (!empty($filters['ops_status']) && $filters['ops_status'] !== 'all' && $row['ops_status'] !== $filters['ops_status']) {
        return false;
    }

    if (!empty($filters['search'])) {
        $haystack = strtolower(
            implode(
                ' ',
                array(
                    (string) $row['customer_name'],
                    (string) $row['customer_email'],
                    (string) $row['product_name'],
                    (string) $row['assigned_seat_label'],
                    (string) $row['order_id'],
                )
            )
        );

        if (strpos($haystack, strtolower($filters['search'])) === false) {
            return false;
        }
    }

    return true;
}

function eco_build_workspace_booking_rows($filters)
{
    $rows = array();
    $orders = wc_get_orders(
        array(
            'limit' => 300,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => eco_get_all_wc_status_keys(),
            'type' => 'shop_order',
        )
    );

    foreach ($orders as $order) {
        $order_status = $order->get_status();
        $customer_name = trim($order->get_formatted_billing_full_name());
        if ($customer_name === '') {
            $customer_name = __('Guest', 'ecospace-booking');
        }

        foreach ($order->get_items() as $item) {
            $booking = eco_parse_booking_payload_from_item($item);
            if (!is_array($booking)) {
                continue;
            }

            $product_id = (int) $item->get_product_id();
            $product_name = $item->get_name();
            $ops_status = sanitize_key((string) $item->get_meta('_eco_checkin_status', true));
            if ($ops_status === '') {
                $ops_status = 'booked';
            }

            $assigned_product_id = (int) $item->get_meta('_eco_assigned_product_id', true);
            if ($assigned_product_id <= 0) {
                $assigned_product_id = $product_id;
            }

            $assigned_seat_label = sanitize_text_field((string) $item->get_meta('_eco_assigned_seat_label', true));
            if ($assigned_seat_label === '') {
                $assigned_seat_label = get_the_title($assigned_product_id);
            }

            $slots = eco_booking_slots_from_payload($booking);
            if (empty($slots)) {
                continue;
            }

            foreach ($slots as $slot) {
                $session_date = isset($slot['date']) ? sanitize_text_field((string) $slot['date']) : '';
                $session_start = isset($slot['start_time']) ? (int) $slot['start_time'] : 0;
                $session_end = isset($slot['end_time']) ? (int) $slot['end_time'] : 0;

                $row = array(
                    'order_id' => (int) $order->get_id(),
                    'item_id' => (int) $item->get_id(),
                    'customer_name' => $customer_name,
                    'customer_email' => sanitize_email((string) $order->get_billing_email()),
                    'product_id' => $product_id,
                    'product_name' => $product_name,
                    'plan' => sanitize_text_field((string) ($booking['plan'] ?? '')),
                    'session_date' => $session_date,
                    'session_start' => $session_start,
                    'session_end' => $session_end,
                    'session_label' => eco_hour_label($session_start) . ' - ' . eco_hour_label($session_end),
                    'order_status' => $order_status,
                    'is_paid' => (bool) $order->is_paid(),
                    'ops_status' => $ops_status,
                    'assigned_product_id' => $assigned_product_id,
                    'assigned_seat_label' => $assigned_seat_label,
                );

                if (!eco_should_include_ops_row($row, $filters)) {
                    continue;
                }

                $rows[] = $row;
            }
        }
    }

    usort(
        $rows,
        function ($a, $b) {
            $a_key = $a['session_date'] . '|' . str_pad((string) $a['session_start'], 2, '0', STR_PAD_LEFT);
            $b_key = $b['session_date'] . '|' . str_pad((string) $b['session_start'], 2, '0', STR_PAD_LEFT);

            if ($a_key === $b_key) {
                return $b['order_id'] <=> $a['order_id'];
            }

            return strcmp($b_key, $a_key);
        }
    );

    return $rows;
}

function eco_assignment_has_conflict($order_id, $slots, $target_product_id)
{
    foreach ($slots as $slot) {
        if (empty($slot['date']) || !isset($slot['start_time']) || !isset($slot['end_time'])) {
            continue;
        }

        $booked_slots = eco_get_booked_slots_for_date($target_product_id, $slot['date']);
        foreach ($booked_slots as $booked_slot) {
            if (!is_array($booked_slot)) {
                continue;
            }

            $booked_order_id = isset($booked_slot['order_id']) ? (int) $booked_slot['order_id'] : 0;
            if ($booked_order_id === (int) $order_id) {
                continue;
            }

            if (eco_do_slots_overlap($slot['start_time'], $slot['end_time'], $booked_slot['start_time'], $booked_slot['end_time'])) {
                return true;
            }
        }
    }

    return false;
}

function eco_booking_ops_redirect($notice, $message)
{
    $redirect = wp_get_referer();
    if (!$redirect) {
        $redirect = admin_url('admin.php?page=eco-workspace-bookings');
    }

    $redirect = remove_query_arg(array('eco_ops_notice', 'eco_ops_message'), $redirect);
    $redirect = add_query_arg(
        array(
            'eco_ops_notice' => sanitize_key((string) $notice),
            'eco_ops_message' => rawurlencode((string) $message),
        ),
        $redirect
    );

    wp_safe_redirect($redirect);
    exit;
}

function eco_update_single_booking_ops($order_id, $item_id, $ops_status, $assigned_product_id, &$error_message)
{
    $order = wc_get_order($order_id);
    if (!$order) {
        $error_message = __('Order not found.', 'ecospace-booking');
        return false;
    }

    $item = $order->get_item($item_id);
    if (!$item) {
        $error_message = __('Booking item not found.', 'ecospace-booking');
        return false;
    }

    $allowed_ops = array_keys(eco_get_ops_status_labels());
    if (!in_array($ops_status, $allowed_ops, true)) {
        $ops_status = 'booked';
    }

    $booking = eco_parse_booking_payload_from_item($item);
    if (!is_array($booking)) {
        $error_message = __('Booking payload is missing for this order item.', 'ecospace-booking');
        return false;
    }

    $source_product_id = (int) $item->get_product_id();
    if ($assigned_product_id <= 0) {
        $assigned_product_id = $source_product_id;
    }

    $slots = eco_booking_slots_from_payload($booking);
    if (!empty($slots) && eco_assignment_has_conflict($order_id, $slots, $assigned_product_id)) {
        $error_message = __('Seat assignment conflicts with another active booking at the same time.', 'ecospace-booking');
        return false;
    }

    $assigned_label = get_the_title($assigned_product_id);
    if ($assigned_label === '') {
        $assigned_label = __('Unknown Seat', 'ecospace-booking');
    }

    $item->update_meta_data('_eco_assigned_product_id', $assigned_product_id);
    $item->update_meta_data('_eco_assigned_seat_label', $assigned_label);
    $item->update_meta_data('_eco_assigned_by', get_current_user_id());
    $item->update_meta_data('_eco_assigned_at', current_time('mysql'));
    $item->update_meta_data('_eco_checkin_status', $ops_status);
    $item->update_meta_data('_eco_checkin_updated_by', get_current_user_id());
    $item->update_meta_data('_eco_checkin_updated_at', current_time('mysql'));
    $item->save();

    return true;
}

function eco_build_workspace_bookings_url($params = array())
{
    $request_keys = array('filter_date', 'filter_plan', 'filter_order_status', 'filter_payment', 'filter_ops_status', 's', 'preset');
    $base_params = array('page' => 'eco-workspace-bookings');

    foreach ($request_keys as $key) {
        if (isset($_GET[$key])) {
            $base_params[$key] = sanitize_text_field(wp_unslash($_GET[$key]));
        }
    }

    $merged = array_merge($base_params, $params);
    return add_query_arg($merged, admin_url('admin.php'));
}

function eco_apply_workspace_bookings_preset($preset, $filters)
{
    $preset = sanitize_key((string) $preset);
    $now = current_time('timestamp');
    $offset_seconds = ((float) get_option('gmt_offset')) * HOUR_IN_SECONDS;
    $close_hour = eco_get_default_close_hour();

    if ($preset === 'today') {
        $filters['date'] = gmdate('Y-m-d', $now + (int) $offset_seconds);
    } elseif ($preset === 'next_2_hours') {
        $current_hour = (int) gmdate('G', $now + (int) $offset_seconds);
        $filters['date'] = gmdate('Y-m-d', $now + (int) $offset_seconds);
        $filters['window_start'] = $current_hour;
        $filters['window_end'] = min($current_hour + 2, $close_hour);
    } elseif ($preset === 'action_needed') {
        $filters['payment'] = 'paid';
        $filters['ops_status'] = 'booked';
    }

    return $filters;
}

function eco_update_booking_ops_action()
{
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You are not allowed to update booking operations.', 'ecospace-booking'));
    }

    $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;
    $item_id = isset($_POST['item_id']) ? absint(wp_unslash($_POST['item_id'])) : 0;
    $ops_status = isset($_POST['ops_status']) ? sanitize_key(wp_unslash($_POST['ops_status'])) : '';
    $assigned_product_id = isset($_POST['assigned_product_id']) ? absint(wp_unslash($_POST['assigned_product_id'])) : 0;

    if ($order_id <= 0 || $item_id <= 0) {
        eco_booking_ops_redirect('error', __('Invalid booking item.', 'ecospace-booking'));
    }

    check_admin_referer('eco_booking_ops_' . $order_id . '_' . $item_id);

    $error_message = '';
    if (!eco_update_single_booking_ops($order_id, $item_id, $ops_status, $assigned_product_id, $error_message)) {
        eco_booking_ops_redirect('error', $error_message);
    }

    eco_booking_ops_redirect('success', __('Booking operation updated successfully.', 'ecospace-booking'));
}

function eco_bulk_update_booking_ops_action()
{
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You are not allowed to update booking operations.', 'ecospace-booking'));
    }

    check_admin_referer('eco_bulk_booking_ops_action');

    $selected_rows = isset($_POST['selected_rows']) ? (array) wp_unslash($_POST['selected_rows']) : array();
    $ops_status = isset($_POST['bulk_ops_status']) ? sanitize_key(wp_unslash($_POST['bulk_ops_status'])) : 'booked';
    $assigned_product_id = isset($_POST['bulk_assigned_product_id']) ? absint(wp_unslash($_POST['bulk_assigned_product_id'])) : 0;

    if (empty($selected_rows)) {
        eco_booking_ops_redirect('error', __('No bookings were selected for bulk update.', 'ecospace-booking'));
    }

    $success_count = 0;
    $failed_count = 0;
    $last_error = '';

    foreach ($selected_rows as $selected_row) {
        $parts = explode(':', sanitize_text_field((string) $selected_row));
        if (count($parts) !== 2) {
            $failed_count++;
            continue;
        }

        $order_id = absint($parts[0]);
        $item_id = absint($parts[1]);
        if ($order_id <= 0 || $item_id <= 0) {
            $failed_count++;
            continue;
        }

        $error_message = '';
        if (eco_update_single_booking_ops($order_id, $item_id, $ops_status, $assigned_product_id, $error_message)) {
            $success_count++;
        } else {
            $failed_count++;
            $last_error = $error_message;
        }
    }

    if ($failed_count > 0) {
        $message = sprintf(
            __('Bulk update partially completed. Updated: %1$d, failed: %2$d. %3$s', 'ecospace-booking'),
            $success_count,
            $failed_count,
            $last_error
        );
        eco_booking_ops_redirect('error', $message);
    }

    eco_booking_ops_redirect('success', sprintf(__('Bulk update completed. Updated: %d bookings.', 'ecospace-booking'), $success_count));
}

function eco_render_workspace_bookings_page()
{
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You are not allowed to view workspace bookings.', 'ecospace-booking'));
    }

    $filters = array(
        'date' => isset($_GET['filter_date']) ? sanitize_text_field(wp_unslash($_GET['filter_date'])) : '',
        'plan' => isset($_GET['filter_plan']) ? sanitize_text_field(wp_unslash($_GET['filter_plan'])) : '',
        'order_status' => isset($_GET['filter_order_status']) ? sanitize_text_field(wp_unslash($_GET['filter_order_status'])) : '',
        'payment' => isset($_GET['filter_payment']) ? sanitize_text_field(wp_unslash($_GET['filter_payment'])) : 'all',
        'ops_status' => isset($_GET['filter_ops_status']) ? sanitize_text_field(wp_unslash($_GET['filter_ops_status'])) : 'all',
        'search' => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
        'window_start' => 0,
        'window_end' => 0,
    );

    $preset = isset($_GET['preset']) ? sanitize_key(wp_unslash($_GET['preset'])) : '';
    if ($preset !== '') {
        $filters = eco_apply_workspace_bookings_preset($preset, $filters);
    }

    $rows = eco_build_workspace_booking_rows($filters);
    $ops_labels = eco_get_ops_status_labels();
    $seat_products = eco_get_booking_enabled_products_for_assignment();
    $order_statuses = wc_get_order_statuses();
    $notice = isset($_GET['eco_ops_notice']) ? sanitize_key(wp_unslash($_GET['eco_ops_notice'])) : '';
    $message = isset($_GET['eco_ops_message']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['eco_ops_message']))) : '';

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Workspace Bookings', 'ecospace-booking') . '</h1>';
    echo '<p>' . esc_html__('Track arrivals, verify payment state, and assign seats for booked sessions.', 'ecospace-booking') . '</p>';

    if ($notice && $message) {
        $notice_class = ($notice === 'success') ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    echo '<div style="margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap;">';
    echo '<a class="button" href="' . esc_url(eco_build_workspace_bookings_url(array('preset' => 'today'))) . '">' . esc_html__('Today', 'ecospace-booking') . '</a>';
    echo '<a class="button" href="' . esc_url(eco_build_workspace_bookings_url(array('preset' => 'next_2_hours'))) . '">' . esc_html__('Next 2 Hours', 'ecospace-booking') . '</a>';
    echo '<a class="button" href="' . esc_url(eco_build_workspace_bookings_url(array('preset' => 'action_needed'))) . '">' . esc_html__('Action Needed', 'ecospace-booking') . '</a>';
    echo '</div>';

    echo '<form method="get" style="margin-bottom:16px; display:flex; gap:8px; align-items:end; flex-wrap:wrap;">';
    echo '<input type="hidden" name="page" value="eco-workspace-bookings">';
    echo '<p><label for="eco_filter_date">' . esc_html__('Date', 'ecospace-booking') . '</label><br><input type="date" id="eco_filter_date" name="filter_date" value="' . esc_attr($filters['date']) . '"></p>';
    echo '<p><label for="eco_filter_plan">' . esc_html__('Plan', 'ecospace-booking') . '</label><br>';
    echo '<select id="eco_filter_plan" name="filter_plan">';
    echo '<option value="">' . esc_html__('All Plans', 'ecospace-booking') . '</option>';
    foreach (array('hourly', 'daily', 'weekly3', 'weekly5', 'monthly3', 'monthly5') as $plan_key) {
        echo '<option value="' . esc_attr($plan_key) . '" ' . selected($filters['plan'], $plan_key, false) . '>' . esc_html(eco_plan_label($plan_key)) . '</option>';
    }
    echo '</select></p>';

    echo '<p><label for="eco_filter_order_status">' . esc_html__('Order Status', 'ecospace-booking') . '</label><br>';
    echo '<select id="eco_filter_order_status" name="filter_order_status">';
    echo '<option value="">' . esc_html__('All Statuses', 'ecospace-booking') . '</option>';
    foreach ($order_statuses as $status_key => $status_label) {
        $status_value = str_replace('wc-', '', $status_key);
        echo '<option value="' . esc_attr($status_value) . '" ' . selected($filters['order_status'], $status_value, false) . '>' . esc_html($status_label) . '</option>';
    }
    echo '</select></p>';

    echo '<p><label for="eco_filter_payment">' . esc_html__('Payment', 'ecospace-booking') . '</label><br>';
    echo '<select id="eco_filter_payment" name="filter_payment">';
    echo '<option value="all" ' . selected($filters['payment'], 'all', false) . '>' . esc_html__('All', 'ecospace-booking') . '</option>';
    echo '<option value="paid" ' . selected($filters['payment'], 'paid', false) . '>' . esc_html__('Paid', 'ecospace-booking') . '</option>';
    echo '<option value="unpaid" ' . selected($filters['payment'], 'unpaid', false) . '>' . esc_html__('Unpaid', 'ecospace-booking') . '</option>';
    echo '</select></p>';

    echo '<p><label for="eco_filter_ops_status">' . esc_html__('Ops Status', 'ecospace-booking') . '</label><br>';
    echo '<select id="eco_filter_ops_status" name="filter_ops_status">';
    echo '<option value="all">' . esc_html__('All Ops Statuses', 'ecospace-booking') . '</option>';
    foreach ($ops_labels as $ops_key => $ops_label) {
        echo '<option value="' . esc_attr($ops_key) . '" ' . selected($filters['ops_status'], $ops_key, false) . '>' . esc_html($ops_label) . '</option>';
    }
    echo '</select></p>';

    echo '<p><label for="eco_filter_search">' . esc_html__('Search', 'ecospace-booking') . '</label><br><input type="search" id="eco_filter_search" name="s" value="' . esc_attr($filters['search']) . '" placeholder="' . esc_attr__('Customer, seat, order ID', 'ecospace-booking') . '"></p>';

    echo '<p><button class="button button-primary" type="submit">' . esc_html__('Filter', 'ecospace-booking') . '</button></p>';
    echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=eco-workspace-bookings')) . '">' . esc_html__('Reset', 'ecospace-booking') . '</a></p>';
    echo '</form>';

    echo '<p><strong>' . esc_html(sprintf(__('Sessions found: %d', 'ecospace-booking'), count($rows))) . '</strong></p>';
    echo '<p id="eco_ops_refresh_note" style="margin-top:0; color:#555;">' . esc_html__('Auto-refresh every 45 seconds.', 'ecospace-booking') . ' <button type="button" class="button button-link" id="eco_ops_refresh_toggle">' . esc_html__('Pause', 'ecospace-booking') . '</button></p>';

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="eco_bulk_ops_form" style="margin-bottom:12px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">';
    wp_nonce_field('eco_bulk_booking_ops_action');
    echo '<input type="hidden" name="action" value="eco_bulk_update_booking_ops">';
    echo '<select name="bulk_assigned_product_id">';
    echo '<option value="0">' . esc_html__('Keep current seat', 'ecospace-booking') . '</option>';
    foreach ($seat_products as $seat_id => $seat_name) {
        echo '<option value="' . esc_attr((string) $seat_id) . '">' . esc_html($seat_name) . '</option>';
    }
    echo '</select>';
    echo '<select name="bulk_ops_status">';
    foreach ($ops_labels as $ops_key => $ops_label) {
        echo '<option value="' . esc_attr($ops_key) . '">' . esc_html($ops_label) . '</option>';
    }
    echo '</select>';
    echo '<button class="button button-primary" type="submit">' . esc_html__('Apply to Selected', 'ecospace-booking') . '</button>';
    echo '</form>';

    echo '<table class="widefat striped" id="eco_ops_table">';
    echo '<thead><tr>';
    echo '<th><input type="checkbox" id="eco_select_all_rows"></th>';
    echo '<th>' . esc_html__('Date', 'ecospace-booking') . '</th>';
    echo '<th>' . esc_html__('Time', 'ecospace-booking') . '</th>';
    echo '<th>' . esc_html__('Customer', 'ecospace-booking') . '</th>';
    echo '<th>' . esc_html__('Booked Seat', 'ecospace-booking') . '</th>';
    echo '<th>' . esc_html__('Assigned Seat', 'ecospace-booking') . '</th>';
    echo '<th>' . esc_html__('Plan', 'ecospace-booking') . '</th>';
    echo '<th>' . esc_html__('Payment/Order', 'ecospace-booking') . '</th>';
    echo '<th>' . esc_html__('Ops Status', 'ecospace-booking') . '</th>';
    echo '<th>' . esc_html__('Action', 'ecospace-booking') . '</th>';
    echo '</tr></thead><tbody>';

    if (empty($rows)) {
        echo '<tr><td colspan="10">' . esc_html__('No bookings found for current filters.', 'ecospace-booking') . '</td></tr>';
    } else {
        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td><input type="checkbox" class="eco-row-select" value="' . esc_attr((string) $row['order_id'] . ':' . (string) $row['item_id']) . '"></td>';
            echo '<td>' . esc_html($row['session_date']) . '</td>';
            echo '<td>' . esc_html($row['session_label']) . '</td>';
            echo '<td><strong>' . esc_html($row['customer_name']) . '</strong><br><small>' . esc_html($row['customer_email']) . '</small><br><a href="' . esc_url(admin_url('post.php?post=' . (int) $row['order_id'] . '&action=edit')) . '">#' . esc_html((string) $row['order_id']) . '</a></td>';
            echo '<td>' . esc_html($row['product_name']) . '</td>';
            echo '<td>' . esc_html($row['assigned_seat_label']) . '</td>';
            echo '<td>' . esc_html($row['plan']) . '</td>';
            echo '<td>' . esc_html(ucfirst($row['order_status'])) . ' / ' . esc_html($row['is_paid'] ? __('Paid', 'ecospace-booking') : __('Unpaid', 'ecospace-booking')) . '</td>';
            echo '<td>' . esc_html($ops_labels[$row['ops_status']] ?? ucfirst($row['ops_status'])) . '</td>';
            echo '<td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">';
            wp_nonce_field('eco_booking_ops_' . (int) $row['order_id'] . '_' . (int) $row['item_id']);
            echo '<input type="hidden" name="action" value="eco_update_booking_ops">';
            echo '<input type="hidden" name="order_id" value="' . esc_attr((string) $row['order_id']) . '">';
            echo '<input type="hidden" name="item_id" value="' . esc_attr((string) $row['item_id']) . '">';
            echo '<select name="assigned_product_id">';
            foreach ($seat_products as $seat_id => $seat_name) {
                echo '<option value="' . esc_attr((string) $seat_id) . '" ' . selected((int) $row['assigned_product_id'], (int) $seat_id, false) . '>' . esc_html($seat_name) . '</option>';
            }
            echo '</select>';
            echo '<select name="ops_status">';
            foreach ($ops_labels as $ops_key => $ops_label) {
                echo '<option value="' . esc_attr($ops_key) . '" ' . selected($row['ops_status'], $ops_key, false) . '>' . esc_html($ops_label) . '</option>';
            }
            echo '</select>';
            echo '<button class="button button-secondary" type="submit">' . esc_html__('Update', 'ecospace-booking') . '</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    echo '<script>
    (function () {
      var paused = false;
      var refreshBtn = document.getElementById("eco_ops_refresh_toggle");
      var refreshLabel = document.getElementById("eco_ops_refresh_note");
      var selectAll = document.getElementById("eco_select_all_rows");
      var rowChecks = Array.prototype.slice.call(document.querySelectorAll(".eco-row-select"));
      var bulkForm = document.getElementById("eco_bulk_ops_form");

      if (refreshBtn) {
        refreshBtn.addEventListener("click", function () {
          paused = !paused;
          refreshBtn.textContent = paused ? "Resume" : "Pause";
          if (refreshLabel) {
            refreshLabel.firstChild.textContent = paused ? "Auto-refresh paused. " : "Auto-refresh every 45 seconds. ";
          }
        });
      }

      if (selectAll) {
        selectAll.addEventListener("change", function () {
          rowChecks.forEach(function (box) { box.checked = selectAll.checked; });
        });
      }

      if (bulkForm) {
        bulkForm.addEventListener("submit", function (event) {
          var selected = rowChecks.filter(function (box) { return box.checked; });
          bulkForm.querySelectorAll("input[name=\"selected_rows[]\"]").forEach(function (node) { node.remove(); });

          if (!selected.length) {
            event.preventDefault();
            alert("Select at least one booking row first.");
            return;
          }

          selected.forEach(function (box) {
            var input = document.createElement("input");
            input.type = "hidden";
            input.name = "selected_rows[]";
            input.value = box.value;
            bulkForm.appendChild(input);
          });
        });
      }

      setInterval(function () {
        if (paused || document.hidden) {
          return;
        }

        if (rowChecks.some(function (box) { return box.checked; })) {
          return;
        }

        window.location.reload();
      }, 45000);
    })();
    </script>';
    echo '</div>';
}
