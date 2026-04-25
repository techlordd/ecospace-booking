<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_head', 'eco_booking_admin_styles');
function eco_booking_admin_styles()
{
    if (!is_admin() || !function_exists('get_current_screen')) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'product') {
        return;
    }

    echo '<style>
        .eco-booking-panel {
            padding: 0 12px 12px;
            max-width: 960px;
        }

        .eco-booking-panel-description {
            margin: 8px 0 0;
            color: #50575e;
        }

        .eco-booking-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px 16px;
        }

        .eco-booking-plan-list {
            display: grid;
            gap: 12px;
        }

        .eco-booking-plan-card {
            border: 1px solid #dcdcde;
            border-radius: 8px;
            background: #fff;
            overflow: hidden;
        }

        .eco-booking-plan-card[open] {
            border-color: #2271b1;
            box-shadow: 0 0 0 1px rgba(34, 113, 177, 0.12);
        }

        .eco-booking-plan-card summary {
            cursor: pointer;
            list-style: none;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            background: #f6f7f7;
        }

        .eco-booking-plan-card summary::-webkit-details-marker {
            display: none;
        }

        .eco-booking-plan-title {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            color: #1d2327;
        }

        .eco-booking-plan-subtitle {
            display: block;
            margin-top: 4px;
            color: #50575e;
            font-size: 12px;
        }

        .eco-booking-plan-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 74px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #e7f5ea;
            color: #126b2e;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .eco-booking-plan-status.is-disabled {
            background: #f0f0f1;
            color: #50575e;
        }

        .eco-booking-plan-body {
            padding: 16px;
        }

        .eco-booking-field {
            min-width: 0;
        }

        .eco-booking-field label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #1d2327;
        }

        .eco-booking-field input[type="text"],
        .eco-booking-field input[type="number"],
        .eco-booking-field select {
            width: 100%;
            max-width: 100%;
        }

        .eco-booking-field-description {
            margin: 6px 0 0;
            color: #646970;
            font-size: 12px;
        }

        .eco-booking-checkbox-field {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        .eco-booking-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 32px;
            font-weight: 600;
            color: #1d2327;
        }

        .eco-booking-inline-pair {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 8px;
            align-items: center;
        }

        .eco-booking-inline-pair span {
            color: #50575e;
            text-align: center;
            white-space: nowrap;
        }

        @media (max-width: 782px) {
            .eco-booking-panel {
                padding-left: 0;
                padding-right: 0;
            }

            .eco-booking-plan-card summary {
                flex-direction: column;
            }

            .eco-booking-inline-pair {
                grid-template-columns: 1fr;
            }

            .eco-booking-inline-pair span {
                text-align: left;
            }
        }
    </style>';
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

function eco_get_booking_admin_select_html($field_name, $selected_value, $options)
{
    ob_start();
    eco_render_booking_admin_select($field_name, $selected_value, $options);
    return (string) ob_get_clean();
}

function eco_render_booking_admin_field($label, $control_html, $description = '', $extra_class = '')
{
    echo '<div class="eco-booking-field ' . esc_attr($extra_class) . '">';
    if ($label !== '') {
        echo '<label>' . esc_html($label) . '</label>';
    }
    echo $control_html;
    if ($description !== '') {
        echo '<p class="eco-booking-field-description">' . esc_html($description) . '</p>';
    }
    echo '</div>';
}

add_action('woocommerce_product_options_general_product_data', 'eco_product_fields');
function eco_product_fields()
{
    $product_id = get_the_ID();
    $config = $product_id ? eco_get_product_booking_config($product_id, true) : eco_get_default_booking_config();
    $hour_options = eco_admin_booking_hour_options();
    $advanced_enabled = $product_id ? eco_is_advanced_booking_config_enabled($product_id) : false;

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

    woocommerce_wp_checkbox(
        array(
            'id' => '_eco_use_advanced_config',
            'label' => __('Use Advanced Booking Configuration', 'ecospace-booking'),
            'description' => __('Enable the new configurable pricing, hours, plan visibility, and booking windows for this product. Leave this off to preserve the legacy live behavior.', 'ecospace-booking'),
            'desc_tip' => true,
            'value' => $advanced_enabled ? 'yes' : 'no',
        )
    );

    echo '<div class="form-field">';
    echo '<label>' . esc_html__('Workspace Hours', 'ecospace-booking') . '</label>';
    echo '<div class="eco-booking-panel">';
    echo '<div class="eco-booking-grid">';
    eco_render_booking_admin_field(
        __('Opening Hour', 'ecospace-booking'),
        eco_get_booking_admin_select_html('_eco_open_hour', $config['open_hour'], $hour_options)
    );
    eco_render_booking_admin_field(
        __('Closing Hour', 'ecospace-booking'),
        eco_get_booking_admin_select_html('_eco_close_hour', $config['close_hour'], $hour_options)
    );
    eco_render_booking_admin_field(
        __('Recurring Start Min', 'ecospace-booking'),
        eco_get_booking_admin_select_html('_eco_recurring_start_min_hour', $config['recurring_start_min_hour'], $hour_options)
    );
    eco_render_booking_admin_field(
        __('Recurring Start Max', 'ecospace-booking'),
        eco_get_booking_admin_select_html('_eco_recurring_start_max_hour', $config['recurring_start_max_hour'], $hour_options)
    );
    eco_render_booking_admin_field(
        __('Recurring Max Session Hours', 'ecospace-booking'),
        '<input type="number" name="_eco_recurring_session_hours" value="' . esc_attr((string) $config['recurring_session_hours']) . '" min="1" step="1">'
    );
    echo '</div>';
    echo '<p class="eco-booking-panel-description">' . esc_html__('These values power validation and the product page selectors. If you leave them unchanged, the current booking behavior stays the same.', 'ecospace-booking') . '</p>';
    echo '</div>';
    echo '</div>';

    echo '<div class="form-field">';
    echo '<label>' . esc_html__('Plan Configuration', 'ecospace-booking') . '</label>';
    echo '<div class="eco-booking-panel">';
    echo '<div class="eco-booking-plan-list">';

    foreach (eco_get_plan_order() as $plan_key) {
        $plan = $config['plans'][$plan_key];
        $is_enabled = ($plan['enabled'] ?? 'no') === 'yes';
        $status_class = $is_enabled ? 'eco-booking-plan-status' : 'eco-booking-plan-status is-disabled';

        if (($plan['type'] ?? '') === 'hourly') {
            $subtitle = __('Uses the product hourly rate with a configurable minimum default duration.', 'ecospace-booking');
        } elseif (($plan['type'] ?? '') === 'daily') {
            $subtitle = __('Flexible daily booking with a user-selected start time, fixed access hours, and a configurable allowed window.', 'ecospace-booking');
        } else {
            $subtitle = __('Recurring booking with configurable price, session count, and booking window.', 'ecospace-booking');
        }

        echo '<details class="eco-booking-plan-card"' . ($is_enabled ? ' open' : '') . '>';
        echo '<summary>';
        echo '<div>';
        echo '<p class="eco-booking-plan-title">' . esc_html(eco_plan_label($plan_key)) . '</p>';
        echo '<span class="eco-booking-plan-subtitle">' . esc_html($subtitle) . '</span>';
        echo '</div>';
        echo '<span class="' . esc_attr($status_class) . '">' . esc_html($is_enabled ? __('Enabled', 'ecospace-booking') : __('Disabled', 'ecospace-booking')) . '</span>';
        echo '</summary>';
        echo '<div class="eco-booking-plan-body">';
        echo '<div class="eco-booking-grid">';

        eco_render_booking_admin_field(
            '',
            '<label class="eco-booking-checkbox"><input type="checkbox" name="_eco_' . esc_attr($plan_key) . '_enabled" value="yes" ' . checked($plan['enabled'], 'yes', false) . '> ' . esc_html__('Show on product page', 'ecospace-booking') . '</label>',
            __('Turn this plan on or off for customers.', 'ecospace-booking'),
            'eco-booking-checkbox-field'
        );

        eco_render_booking_admin_field(
            __('Customer Label', 'ecospace-booking'),
            '<input type="text" name="_eco_' . esc_attr($plan_key) . '_label" value="' . esc_attr((string) $plan['label']) . '">'
        );

        if (($plan['type'] ?? '') === 'hourly') {
            eco_render_booking_admin_field(
                __('Pricing', 'ecospace-booking'),
                '<div class="eco-booking-field-description" style="margin-top:0;">' . esc_html__('Uses Hourly Rate', 'ecospace-booking') . '</div>'
            );
            eco_render_booking_admin_field(
                __('Minimum Default Hours', 'ecospace-booking'),
                '<input type="number" name="_eco_hourly_min_hours" value="' . esc_attr((string) $plan['min_hours']) . '" min="1" step="1">',
                __('Used as the default hourly duration when advanced configuration is enabled.', 'ecospace-booking')
            );
        } elseif (($plan['type'] ?? '') === 'daily') {
            eco_render_booking_admin_field(
                __('Price', 'ecospace-booking'),
                '<input type="number" name="_eco_daily_price" value="' . esc_attr(wc_format_localized_price((string) $plan['price'])) . '" min="0" step="0.01">'
            );
            eco_render_booking_admin_field(
                __('Daily Access Hours', 'ecospace-booking'),
                '<input type="number" name="_eco_daily_session_hours" value="' . esc_attr((string) ($plan['session_hours'] ?? 8)) . '" min="1" step="1">',
                __('Customers can book this many hours within the daily plan window.', 'ecospace-booking')
            );
            eco_render_booking_admin_field(
                __('Allowed Daily Window', 'ecospace-booking'),
                '<div class="eco-booking-inline-pair">' .
                    eco_get_booking_admin_select_html('_eco_daily_start_hour', $plan['start_hour'], $hour_options) .
                    '<span>' . esc_html__('to', 'ecospace-booking') . '</span>' .
                    eco_get_booking_admin_select_html('_eco_daily_end_hour', $plan['end_hour'], $hour_options) .
                '</div>',
                __('Customers choose a start time inside this range, and the daily plan ends after the configured access hours.', 'ecospace-booking')
            );
        } else {
            $window_unit_label = (($plan['window_unit'] ?? 'days') === 'months') ? __('month(s)', 'ecospace-booking') : __('day(s)', 'ecospace-booking');

            eco_render_booking_admin_field(
                __('Price', 'ecospace-booking'),
                '<input type="number" name="_eco_' . esc_attr($plan_key) . '_price" value="' . esc_attr(wc_format_localized_price((string) $plan['price'])) . '" min="0" step="0.01">'
            );
            eco_render_booking_admin_field(
                __('Preferred Sessions', 'ecospace-booking'),
                '<input type="number" name="_eco_' . esc_attr($plan_key) . '_sessions" value="' . esc_attr((string) $plan['sessions']) . '" min="1" step="1">'
            );
            eco_render_booking_admin_field(
                __('Booking Window', 'ecospace-booking'),
                '<input type="number" name="_eco_' . esc_attr($plan_key) . '_window_value" value="' . esc_attr((string) $plan['window_value']) . '" min="1" step="1">',
                $window_unit_label
            );
        }

        echo '</div>';
        echo '</div>';
        echo '</details>';
    }

    echo '</div>';
    echo '<p class="eco-booking-panel-description">' . esc_html__('Use these plan cards to control which plans appear on the product page, adjust labels, change prices, and set the number of preferred dates or hours without editing code.', 'ecospace-booking') . '</p>';
    echo '</div>';
    echo '</div>';

    // ── Discount / Promotion panel ──────────────────────────────────────────
    $discount_enabled     = get_post_meta($product_id, '_eco_discount_enabled', true) === 'yes';
    $discount_percent     = (float) get_post_meta($product_id, '_eco_discount_percent', true);
    $discount_start       = (string) get_post_meta($product_id, '_eco_discount_start', true);
    $discount_end         = (string) get_post_meta($product_id, '_eco_discount_end', true);
    $discount_plans_saved = (array)  get_post_meta($product_id, '_eco_discount_plans', true);

    echo '<div class="eco-booking-panel" style="margin-top:20px;">';
    echo '<h3 style="margin:0 0 12px;">' . esc_html__('Promotion / Discount', 'ecospace-booking') . '</h3>';

    echo '<p class="form-field">';
    echo '<label style="display:inline-flex;align-items:center;gap:6px;font-weight:600;">';
    echo '<input type="checkbox" name="_eco_discount_enabled" value="yes" ' . checked($discount_enabled, true, false) . '>';
    echo esc_html__('Enable discount for this product', 'ecospace-booking');
    echo '</label>';
    echo '</p>';

    echo '<p class="form-field"><label for="_eco_discount_percent">' . esc_html__('Discount %', 'ecospace-booking') . '</label>';
    echo '<input type="number" id="_eco_discount_percent" name="_eco_discount_percent" value="' . esc_attr((string) $discount_percent) . '" min="0" max="100" step="0.01" style="max-width:120px;">';
    echo '<span class="description">&nbsp;' . esc_html__('Enter 100 for fully free. Enter 50 for half price.', 'ecospace-booking') . '</span>';
    echo '</p>';

    echo '<p class="form-field"><label for="_eco_discount_start">' . esc_html__('Valid From', 'ecospace-booking') . '</label>';
    echo '<input type="date" id="_eco_discount_start" name="_eco_discount_start" value="' . esc_attr($discount_start) . '" style="max-width:180px;">';
    echo '<span class="description">&nbsp;' . esc_html__('Leave blank for no start restriction.', 'ecospace-booking') . '</span>';
    echo '</p>';

    echo '<p class="form-field"><label for="_eco_discount_end">' . esc_html__('Valid Until', 'ecospace-booking') . '</label>';
    echo '<input type="date" id="_eco_discount_end" name="_eco_discount_end" value="' . esc_attr($discount_end) . '" style="max-width:180px;">';
    echo '<span class="description">&nbsp;' . esc_html__('Leave blank for no expiry.', 'ecospace-booking') . '</span>';
    echo '</p>';

    echo '<p class="form-field"><label>' . esc_html__('Apply to Plans', 'ecospace-booking') . '</label>';
    echo '<span class="description" style="display:block;margin-bottom:6px;">' . esc_html__('Leave all unchecked to apply to all plans.', 'ecospace-booking') . '</span>';
    foreach (array('hourly', 'daily', 'weekly3', 'weekly5', 'monthly3', 'monthly5') as $pk) {
        $pk_checked = in_array($pk, $discount_plans_saved, true);
        echo '<label style="display:inline-flex;align-items:center;gap:4px;margin-right:12px;">';
        echo '<input type="checkbox" name="_eco_discount_plans[]" value="' . esc_attr($pk) . '" ' . checked($pk_checked, true, false) . '>';
        echo esc_html(eco_plan_label($pk, $product_id));
        echo '</label>';
    }
    echo '</p>';
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
    update_post_meta($post_id, '_eco_use_advanced_config', isset($_POST['_eco_use_advanced_config']) ? 'yes' : 'no');

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
        '_eco_daily_session_hours',
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

    // Discount meta
    update_post_meta($post_id, '_eco_discount_enabled', isset($_POST['_eco_discount_enabled']) ? 'yes' : 'no');

    if (isset($_POST['_eco_discount_percent'])) {
        $pct = min(100.0, max(0.0, (float) wp_unslash($_POST['_eco_discount_percent'])));
        update_post_meta($post_id, '_eco_discount_percent', $pct);
    }

    if (isset($_POST['_eco_discount_start'])) {
        $start = sanitize_text_field(wp_unslash($_POST['_eco_discount_start']));
        update_post_meta($post_id, '_eco_discount_start', preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) ? $start : '');
    }

    if (isset($_POST['_eco_discount_end'])) {
        $end = sanitize_text_field(wp_unslash($_POST['_eco_discount_end']));
        update_post_meta($post_id, '_eco_discount_end', preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) ? $end : '');
    }

    $plans_posted = isset($_POST['_eco_discount_plans']) ? (array) $_POST['_eco_discount_plans'] : array();
    $allowed_plans = array('hourly', 'daily', 'weekly3', 'weekly5', 'monthly3', 'monthly5');
    update_post_meta($post_id, '_eco_discount_plans', array_values(array_intersect($plans_posted, $allowed_plans)));
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
                    'customer_phone' => sanitize_text_field((string) $order->get_billing_phone()),
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
    $redirect = '';

    if (isset($_POST['redirect_to'])) {
        $redirect = wp_unslash($_POST['redirect_to']);
    } elseif (isset($_GET['redirect_to'])) {
        $redirect = wp_unslash($_GET['redirect_to']);
    }

    if ($redirect !== '') {
        $redirect = wp_validate_redirect($redirect, '');
    }

    if ($redirect === '') {
        $redirect = wp_get_referer();
    }

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
    return eco_build_workspace_bookings_url_for_base(admin_url('admin.php?page=eco-workspace-bookings'), $params);
}

function eco_build_workspace_bookings_url_for_base($base_url, $params = array())
{
    $request_keys = array('filter_date', 'filter_plan', 'filter_order_status', 'filter_payment', 'filter_ops_status', 's', 'preset', 'eco_paged');
    $base_params = array();

    foreach ($request_keys as $key) {
        if (isset($_GET[$key])) {
            $base_params[$key] = sanitize_text_field(wp_unslash($_GET[$key]));
        }
    }

    $merged = array_merge($base_params, $params);
    return add_query_arg($merged, $base_url);
}

function eco_render_workspace_bookings_styles($context = 'admin')
{
    static $printed = array();

    if (isset($printed[$context])) {
        return;
    }

    $printed[$context] = true;

    echo '<style>
    .eco-workspace-bookings-shell {
        margin-top: 16px;
    }

    .eco-workspace-bookings-shell .eco-bookings-toolbar,
    .eco-workspace-bookings-shell .eco-bookings-filters,
    .eco-workspace-bookings-shell .eco-bookings-bulk-form {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: end;
    }

    .eco-workspace-bookings-shell .eco-bookings-toolbar {
        margin-bottom: 12px;
    }

    .eco-workspace-bookings-shell .eco-bookings-filters {
        margin-bottom: 16px;
    }

    .eco-workspace-bookings-shell .eco-bookings-filters p,
    .eco-workspace-bookings-shell .eco-bookings-bulk-form p {
        margin: 0;
    }

    .eco-workspace-bookings-shell label {
        display: block;
        margin-bottom: 6px;
        font-weight: 600;
    }

    .eco-workspace-bookings-shell input[type="date"],
    .eco-workspace-bookings-shell input[type="search"],
    .eco-workspace-bookings-shell select {
        min-height: 38px;
        min-width: 160px;
    }

    .eco-workspace-bookings-shell .eco-bookings-count,
    .eco-workspace-bookings-shell .eco-bookings-refresh-note {
        margin: 0 0 12px;
    }

    .eco-workspace-bookings-shell .eco-bookings-table-wrap {
        overflow-x: auto;
        border: 1px solid #dcdcde;
        border-radius: 8px;
        background: #fff;
    }

    .eco-workspace-bookings-shell table {
        width: 100%;
        border-collapse: collapse;
    }

    .eco-workspace-bookings-shell th,
    .eco-workspace-bookings-shell td {
        padding: 12px;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: top;
        text-align: left;
        white-space: nowrap;
    }

    .eco-workspace-bookings-shell thead th {
        background: #f1f5f9;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #475467;
        position: sticky;
        top: 0;
        z-index: 2;
    }

    .eco-workspace-bookings-shell tbody tr:nth-child(odd) {
        background: #ffffff;
    }

    .eco-workspace-bookings-shell tbody tr:nth-child(even) {
        background: #f8fafc;
    }

    .eco-workspace-bookings-shell tbody tr:hover {
        background: #eef2ff;
        transition: background 0.12s ease;
    }

    .eco-workspace-bookings-shell .eco-bookings-inline-form {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .eco-workspace-bookings-shell .eco-bookings-inline-form select {
        min-width: 140px;
        min-height: 32px;
        font-size: 12px;
    }

    .eco-workspace-bookings-shell .eco-bookings-inline-form .button {
        align-self: flex-start;
    }

    .eco-workspace-bookings-shell .eco-bookings-empty {
        padding: 24px 18px;
        color: #667085;
        font-style: italic;
    }

    .eco-workspace-bookings-shell .notice,
    .eco-workspace-bookings-shortcode .notice {
        margin: 0 0 16px;
        padding: 12px 14px;
        border-left: 4px solid #2271b1;
        background: #fff;
        box-shadow: 0 1px 2px rgba(16, 24, 40, 0.06);
    }

    .eco-workspace-bookings-shell .notice-success,
    .eco-workspace-bookings-shortcode .notice-success {
        border-left-color: #16a34a;
    }

    .eco-workspace-bookings-shell .notice-error,
    .eco-workspace-bookings-shortcode .notice-error {
        border-left-color: #dc2626;
    }

    .eco-workspace-bookings-shortcode {
        margin: 24px 0;
        padding: 20px;
        border: 1px solid #e4e7ec;
        border-radius: 12px;
        background: #F0F0F1;
    }

    .eco-workspace-bookings-shortcode h2 {
        margin: 0 0 8px;
    }

    .eco-workspace-bookings-shortcode .button,
    .eco-workspace-bookings-shortcode button,
    .eco-workspace-bookings-shortcode input[type="submit"] {
        min-height: 38px;
    }

    /* Status badges */
    .eco-status-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 50px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.03em;
        line-height: 1.6;
        white-space: nowrap;
        text-transform: capitalize;
    }
    .eco-badge-ops-booked      { background: #dbeafe; color: #1d4ed8; }
    .eco-badge-ops-assigned    { background: #ede9fe; color: #6d28d9; }
    .eco-badge-ops-checked_in  { background: #fef3c7; color: #b45309; }
    .eco-badge-ops-completed   { background: #dcfce7; color: #15803d; }
    .eco-badge-ops-no_show     { background: #fee2e2; color: #b91c1c; }
    .eco-badge-ops-cancelled   { background: #f3f4f6; color: #4b5563; }
    .eco-badge-paid   { background: #dcfce7; color: #15803d; }
    .eco-badge-unpaid { background: #fff7ed; color: #c2410c; }
    .eco-badge-plan   { background: #f0f4ff; color: #3b4fa0; }

    /* Date/time cell */
    .eco-date-cell {
        display: block;
        font-weight: 600;
        color: #101828;
        font-size: 13px;
    }
    .eco-time-cell {
        display: block;
        font-size: 12px;
        color: #667085;
        margin-top: 2px;
    }

    /* Pagination */
    .eco-workspace-bookings-shell .eco-bookings-pagination {
        display: flex;
        align-items: center;
        gap: 4px;
        margin-top: 16px;
        flex-wrap: wrap;
    }
    .eco-workspace-bookings-shell .eco-bookings-pagination .eco-page-link,
    .eco-workspace-bookings-shell .eco-bookings-pagination .eco-page-current,
    .eco-workspace-bookings-shell .eco-bookings-pagination .eco-page-dots,
    .eco-workspace-bookings-shell .eco-bookings-pagination .eco-page-disabled {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 34px;
        height: 34px;
        padding: 0 12px;
        border-radius: 6px;
        font-size: 13px;
        line-height: 1;
        text-decoration: none;
    }
    .eco-workspace-bookings-shell .eco-bookings-pagination .eco-page-link {
        border: 1px solid #d1d5db;
        background: #fff;
        color: #374151;
    }
    .eco-workspace-bookings-shell .eco-bookings-pagination .eco-page-link:hover {
        background: #f3f4f6;
        border-color: #9ca3af;
    }
    .eco-workspace-bookings-shell .eco-bookings-pagination .eco-page-current {
        border: 1px solid #4f46e5;
        background: #4f46e5;
        color: #fff;
        font-weight: 600;
    }
    .eco-workspace-bookings-shell .eco-bookings-pagination .eco-page-dots {
        border: none;
        background: transparent;
        color: #6b7280;
    }
    .eco-workspace-bookings-shell .eco-bookings-pagination .eco-page-disabled {
        border: 1px solid #e5e7eb;
        background: #f9fafb;
        color: #d1d5db;
        cursor: default;
    }

    @media (max-width: 782px) {
        .eco-workspace-bookings-shell .eco-bookings-toolbar,
        .eco-workspace-bookings-shell .eco-bookings-filters,
        .eco-workspace-bookings-shell .eco-bookings-bulk-form,
        .eco-workspace-bookings-shell .eco-bookings-inline-form {
            flex-direction: column;
            align-items: stretch;
        }

        .eco-workspace-bookings-shell input[type="date"],
        .eco-workspace-bookings-shell input[type="search"],
        .eco-workspace-bookings-shell select {
            width: 100%;
            min-width: 0;
        }
    }

    /* Customer detail modal */
    .eco-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.55);
        z-index: 100000;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .eco-modal-box {
        background: #fff;
        border-radius: 12px;
        padding: 28px 32px;
        max-width: 480px;
        width: 94%;
        position: relative;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.18);
    }
    .eco-modal-box h3 {
        margin: 0 0 20px;
        font-size: 18px;
        color: #101828;
    }
    .eco-modal-close {
        position: absolute;
        top: 14px;
        right: 16px;
        background: none;
        border: none;
        font-size: 22px;
        line-height: 1;
        cursor: pointer;
        color: #6b7280;
        padding: 4px 8px;
    }
    .eco-modal-close:hover { color: #101828; }
    .eco-modal-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        font-size: 13px;
    }
    .eco-modal-table th,
    .eco-modal-table td {
        padding: 7px 10px;
        border-bottom: 1px solid #f3f4f6;
        text-align: left;
        vertical-align: top;
    }
    .eco-modal-table th {
        width: 36%;
        color: #6b7280;
        font-weight: 600;
    }
    .eco-modal-table td { color: #101828; word-break: break-word; }
    .eco-modal-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .eco-view-customer { margin-bottom: 8px; display: block; }

    /* Checked-in countdown timer */
    .eco-timer-countdown {
        display: block;
        margin-top: 5px;
        font-size: 11px;
        font-weight: 600;
        color: #b45309;
        letter-spacing: 0.02em;
    }
    .eco-timer-expired {
        display: block;
        margin-top: 5px;
        font-size: 11px;
        font-weight: 600;
        color: #b91c1c;
        letter-spacing: 0.02em;
    }

    /* Session-ended banner */
    .eco-session-ended-banner {
        position: fixed;
        top: 32px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 200000;
        background: #fef3c7;
        border: 1px solid #f59e0b;
        border-radius: 8px;
        padding: 12px 20px 12px 16px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.14);
        min-width: 280px;
        max-width: 480px;
        font-size: 13px;
        color: #78350f;
    }
    .eco-session-ended-banner strong { display: block; margin-bottom: 4px; font-size: 14px; }
    .eco-session-ended-banner ul { margin: 4px 0 0 16px; padding: 0; }
    .eco-session-ended-banner .eco-banner-dismiss {
        position: absolute;
        top: 8px;
        right: 10px;
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        color: #92400e;
        line-height: 1;
        padding: 0 4px;
    }
    </style>';
}

function eco_get_pagination_page_range($current_page, $total_pages)
{
    if ($total_pages <= 7) {
        return range(1, $total_pages);
    }

    $window_start = max(2, $current_page - 2);
    $window_end   = min($total_pages - 1, $current_page + 2);
    $pages        = array(1);

    if ($window_start > 2) {
        $pages[] = '...';
    }
    for ($i = $window_start; $i <= $window_end; $i++) {
        $pages[] = $i;
    }
    if ($window_end < $total_pages - 1) {
        $pages[] = '...';
    }
    $pages[] = $total_pages;

    return $pages;
}

function eco_render_bookings_pagination_nav($current_page, $total_pages, $base_url)
{
    if ($total_pages <= 1) {
        return;
    }

    echo '<nav class="eco-bookings-pagination" aria-label="' . esc_attr__('Booking pages', 'ecospace-booking') . '">';

    if ($current_page > 1) {
        echo '<a class="eco-page-link" href="' . esc_url(eco_build_workspace_bookings_url_for_base($base_url, array('eco_paged' => $current_page - 1))) . '">&laquo; ' . esc_html__('Prev', 'ecospace-booking') . '</a>';
    } else {
        echo '<span class="eco-page-disabled">&laquo; ' . esc_html__('Prev', 'ecospace-booking') . '</span>';
    }

    foreach (eco_get_pagination_page_range($current_page, $total_pages) as $p) {
        if ($p === '...') {
            echo '<span class="eco-page-dots">&hellip;</span>';
        } elseif ((int) $p === $current_page) {
            echo '<span class="eco-page-current">' . esc_html((string) $p) . '</span>';
        } else {
            echo '<a class="eco-page-link" href="' . esc_url(eco_build_workspace_bookings_url_for_base($base_url, array('eco_paged' => (int) $p))) . '">' . esc_html((string) $p) . '</a>';
        }
    }

    if ($current_page < $total_pages) {
        echo '<a class="eco-page-link" href="' . esc_url(eco_build_workspace_bookings_url_for_base($base_url, array('eco_paged' => $current_page + 1))) . '">' . esc_html__('Next', 'ecospace-booking') . ' &raquo;</a>';
    } else {
        echo '<span class="eco-page-disabled">' . esc_html__('Next', 'ecospace-booking') . ' &raquo;</span>';
    }

    echo '</nav>';
}

function eco_render_workspace_bookings_interface($args = array())
{
    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    $args = wp_parse_args(
        $args,
        array(
            'context' => 'admin',
            'base_url' => admin_url('admin.php?page=eco-workspace-bookings'),
            'title' => __('Workspace Bookings', 'ecospace-booking'),
            'description' => __('Track arrivals, verify payment state, and assign seats for booked sessions.', 'ecospace-booking'),
            'reset_url' => admin_url('admin.php?page=eco-workspace-bookings'),
            'heading_tag' => 'h1',
        )
    );

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
    $per_page     = 25;
    $total_rows   = count($rows);
    $total_pages  = max(1, (int) ceil($total_rows / $per_page));
    $current_page = isset($_GET['eco_paged']) ? max(1, min($total_pages, absint(wp_unslash($_GET['eco_paged'])))) : 1;
    $paged_offset = ($current_page - 1) * $per_page;
    $paged_rows   = array_slice($rows, $paged_offset, $per_page);
    $ops_labels = eco_get_ops_status_labels();
    $seat_products = eco_get_booking_enabled_products_for_assignment();
    $order_statuses = wc_get_order_statuses();
    $notice = isset($_GET['eco_ops_notice']) ? sanitize_key(wp_unslash($_GET['eco_ops_notice'])) : '';
    $message = isset($_GET['eco_ops_message']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['eco_ops_message']))) : '';
    $current_url = eco_build_workspace_bookings_url_for_base($args['base_url']);

    eco_render_workspace_bookings_styles($args['context']);

    echo '<div class="eco-workspace-bookings-shell' . ($args['context'] === 'frontend' ? ' eco-workspace-bookings-shortcode' : '') . '">';
    echo '<' . tag_escape($args['heading_tag']) . '>' . esc_html($args['title']) . '</' . tag_escape($args['heading_tag']) . '>';
    echo '<p>' . esc_html($args['description']) . '</p>';

    if ($notice && $message) {
        $notice_class = ($notice === 'success') ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr($notice_class) . '"><p>' . esc_html($message) . '</p></div>';
    }

    echo '<div class="eco-bookings-toolbar">';
    echo '<a class="button" href="' . esc_url(eco_build_workspace_bookings_url_for_base($args['base_url'], array('preset' => 'today', 'eco_paged' => 1))) . '">' . esc_html__('Today', 'ecospace-booking') . '</a>';
    echo '<a class="button" href="' . esc_url(eco_build_workspace_bookings_url_for_base($args['base_url'], array('preset' => 'next_2_hours', 'eco_paged' => 1))) . '">' . esc_html__('Next 2 Hours', 'ecospace-booking') . '</a>';
    echo '<a class="button" href="' . esc_url(eco_build_workspace_bookings_url_for_base($args['base_url'], array('preset' => 'action_needed', 'eco_paged' => 1))) . '">' . esc_html__('Action Needed', 'ecospace-booking') . '</a>';
    echo '<a class="button" href="' . esc_url(eco_build_workspace_bookings_url_for_base($args['base_url'], array('preset' => 'draft', 'eco_paged' => 1))) . '">' . esc_html__('Draft', 'ecospace-booking') . '</a>';
    echo '<a class="button" href="' . esc_url(eco_build_workspace_bookings_url_for_base($args['base_url'], array('preset' => 'active_sessions', 'eco_paged' => 1))) . '">' . esc_html__('Active Sessions', 'ecospace-booking') . '</a>';
    echo '<a class="button" href="' . esc_url(eco_build_workspace_bookings_url_for_base($args['base_url'], array('preset' => 'no_show', 'eco_paged' => 1))) . '">' . esc_html__('No Show', 'ecospace-booking') . '</a>';
    echo '</div>';

    $eco_form_parsed = parse_url($args['base_url']);
    $eco_form_action = ($eco_form_parsed['scheme'] ?? 'https') . '://' . ($eco_form_parsed['host'] ?? '') . ($eco_form_parsed['path'] ?? '/');
    $eco_form_base_params = array();
    if (!empty($eco_form_parsed['query'])) {
        parse_str($eco_form_parsed['query'], $eco_form_base_params);
    }
    echo '<form method="get" action="' . esc_url($eco_form_action) . '" class="eco-bookings-filters">';
    foreach ($eco_form_base_params as $eco_param_key => $eco_param_val) {
        echo '<input type="hidden" name="' . esc_attr($eco_param_key) . '" value="' . esc_attr($eco_param_val) . '">';
    }
    echo '<p><label for="eco_filter_date">' . esc_html__('Date', 'ecospace-booking') . '</label><input type="date" id="eco_filter_date" name="filter_date" value="' . esc_attr($filters['date']) . '"></p>';
    echo '<p><label for="eco_filter_plan">' . esc_html__('Plan', 'ecospace-booking') . '</label>';
    echo '<select id="eco_filter_plan" name="filter_plan">';
    echo '<option value="">' . esc_html__('All Plans', 'ecospace-booking') . '</option>';
    foreach (array('hourly', 'daily', 'weekly3', 'weekly5', 'monthly3', 'monthly5') as $plan_key) {
        echo '<option value="' . esc_attr($plan_key) . '" ' . selected($filters['plan'], $plan_key, false) . '>' . esc_html(eco_plan_label($plan_key)) . '</option>';
    }
    echo '</select></p>';

    echo '<p><label for="eco_filter_order_status">' . esc_html__('Order Status', 'ecospace-booking') . '</label>';
    echo '<select id="eco_filter_order_status" name="filter_order_status">';
    echo '<option value="">' . esc_html__('All Statuses', 'ecospace-booking') . '</option>';
    foreach ($order_statuses as $status_key => $status_label) {
        $status_value = str_replace('wc-', '', $status_key);
        echo '<option value="' . esc_attr($status_value) . '" ' . selected($filters['order_status'], $status_value, false) . '>' . esc_html($status_label) . '</option>';
    }
    echo '</select></p>';

    echo '<p><label for="eco_filter_payment">' . esc_html__('Payment', 'ecospace-booking') . '</label>';
    echo '<select id="eco_filter_payment" name="filter_payment">';
    echo '<option value="all" ' . selected($filters['payment'], 'all', false) . '>' . esc_html__('All', 'ecospace-booking') . '</option>';
    echo '<option value="paid" ' . selected($filters['payment'], 'paid', false) . '>' . esc_html__('Paid', 'ecospace-booking') . '</option>';
    echo '<option value="unpaid" ' . selected($filters['payment'], 'unpaid', false) . '>' . esc_html__('Unpaid', 'ecospace-booking') . '</option>';
    echo '</select></p>';

    echo '<p><label for="eco_filter_ops_status">' . esc_html__('Ops Status', 'ecospace-booking') . '</label>';
    echo '<select id="eco_filter_ops_status" name="filter_ops_status">';
    echo '<option value="all">' . esc_html__('All Ops Statuses', 'ecospace-booking') . '</option>';
    foreach ($ops_labels as $ops_key => $ops_label) {
        echo '<option value="' . esc_attr($ops_key) . '" ' . selected($filters['ops_status'], $ops_key, false) . '>' . esc_html($ops_label) . '</option>';
    }
    echo '</select></p>';

    echo '<p><label for="eco_filter_search">' . esc_html__('Search', 'ecospace-booking') . '</label><input type="search" id="eco_filter_search" name="s" value="' . esc_attr($filters['search']) . '" placeholder="' . esc_attr__('Customer, seat, order ID', 'ecospace-booking') . '"></p>';
    echo '<p><button class="button button-primary" type="submit">' . esc_html__('Filter', 'ecospace-booking') . '</button></p>';
    echo '<p><a class="button" href="' . esc_url($args['reset_url']) . '">' . esc_html__('Reset', 'ecospace-booking') . '</a></p>';
    echo '</form>';

    if ($total_rows === 0) {
        $count_text = __('No sessions found.', 'ecospace-booking');
    } elseif ($total_rows <= $per_page) {
        $count_text = sprintf(
            _n('%d session found.', '%d sessions found.', $total_rows, 'ecospace-booking'),
            $total_rows
        );
    } else {
        $range_start = $paged_offset + 1;
        $range_end   = min($paged_offset + $per_page, $total_rows);
        $count_text  = sprintf(
            __('Showing %1$d–%2$d of %3$d sessions.', 'ecospace-booking'),
            $range_start,
            $range_end,
            $total_rows
        );
    }
    echo '<p class="eco-bookings-count"><strong>' . esc_html($count_text) . '</strong></p>';
    echo '<p id="eco_ops_refresh_note" class="eco-bookings-refresh-note">' . esc_html__('Auto-refresh every 10 minutes.', 'ecospace-booking') . ' <button type="button" class="button button-link" id="eco_ops_refresh_toggle">' . esc_html__('Pause', 'ecospace-booking') . '</button></p>';

    // TODO: Temporarily hidden — bulk "Apply to Selected" form
    /* echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="eco_bulk_ops_form" class="eco-bookings-bulk-form">';
    wp_nonce_field('eco_bulk_booking_ops_action');
    echo '<input type="hidden" name="action" value="eco_bulk_update_booking_ops">';
    echo '<input type="hidden" name="redirect_to" value="' . esc_url($current_url) . '">';
    echo '<p><select name="bulk_assigned_product_id">';
    echo '<option value="0">' . esc_html__('Keep current seat', 'ecospace-booking') . '</option>';
    foreach ($seat_products as $seat_id => $seat_name) {
        echo '<option value="' . esc_attr((string) $seat_id) . '">' . esc_html($seat_name) . '</option>';
    }
    echo '</select></p>';
    echo '<p><select name="bulk_ops_status">';
    foreach ($ops_labels as $ops_key => $ops_label) {
        echo '<option value="' . esc_attr($ops_key) . '">' . esc_html($ops_label) . '</option>';
    }
    echo '</select></p>';
    echo '<p><button class="button button-primary" type="submit">' . esc_html__('Apply to Selected', 'ecospace-booking') . '</button></p>';
    echo '</form>'; */

    echo '<div class="eco-bookings-table-wrap">';
    echo '<table id="eco_ops_table">';
    echo '<thead><tr>';
    echo '<th><input type="checkbox" id="eco_select_all_rows"></th>';
    echo '<th>' . esc_html__('Date & Time', 'ecospace-booking') . '</th>';
    echo '<th>' . esc_html__('Customer', 'ecospace-booking') . '</th>';
    echo '<th>' . esc_html__('Booked Seat', 'ecospace-booking') . '</th>';
    echo '<th>' . esc_html__('Plan', 'ecospace-booking') . '</th>';
    echo '<th>' . esc_html__('Payment/Order', 'ecospace-booking') . '</th>';
    echo '<th>' . esc_html__('Ops Status', 'ecospace-booking') . '</th>';
    echo '<th>' . esc_html__('Action', 'ecospace-booking') . '</th>';
    echo '</tr></thead><tbody>';

    if (empty($rows)) {
        echo '<tr><td colspan="8" class="eco-bookings-empty">' . esc_html__('No bookings found for current filters.', 'ecospace-booking') . '</td></tr>';
    } else {
        foreach ($paged_rows as $row) {
            $ops_slug = sanitize_html_class($row['ops_status']);
            echo '<tr'
                . ' data-row-key="'  . esc_attr($row['order_id'] . ':' . $row['item_id'])    . '"'
                . ' data-ops="'      . esc_attr($row['ops_status'])                           . '"'
                . ' data-end-date="' . esc_attr($row['session_date'])                         . '"'
                . ' data-end-hour="' . esc_attr((string) $row['session_end'])                 . '"'
                . ' data-customer="' . esc_attr($row['customer_name'])                        . '"'
                . ' data-seat="'     . esc_attr($row['assigned_seat_label'])                  . '"'
                . '>';
            echo '<td><input type="checkbox" class="eco-row-select" value="' . esc_attr((string) $row['order_id'] . ':' . (string) $row['item_id']) . '"></td>';
            echo '<td><span class="eco-date-cell">' . esc_html($row['session_date']) . '</span><span class="eco-time-cell">' . esc_html($row['session_label']) . '</span></td>';
            echo '<td><strong>' . esc_html($row['customer_name']) . '</strong><br><small>' . esc_html($row['customer_email']) . '</small><br><a href="' . esc_url(admin_url('post.php?post=' . (int) $row['order_id'] . '&action=edit')) . '">#' . esc_html((string) $row['order_id']) . '</a></td>';
            echo '<td>' . esc_html($row['product_name']) . '</td>';
            echo '<td><span class="eco-status-badge eco-badge-plan">' . esc_html($row['plan']) . '</span></td>';
            echo '<td>' . esc_html(ucfirst($row['order_status'])) . '<br><span class="eco-status-badge ' . ($row['is_paid'] ? 'eco-badge-paid' : 'eco-badge-unpaid') . '">' . esc_html($row['is_paid'] ? __('Paid', 'ecospace-booking') : __('Unpaid', 'ecospace-booking')) . '</span></td>';
            echo '<td class="eco-ops-td"><span class="eco-status-badge eco-badge-ops-' . esc_attr($ops_slug) . '">' . esc_html($ops_labels[$row['ops_status']] ?? ucfirst($row['ops_status'])) . '</span></td>';
            echo '<td>';
            echo '<button type="button" class="button button-secondary eco-view-customer"'
                . ' data-name="'   . esc_attr($row['customer_name'])         . '"'
                . ' data-email="'  . esc_attr($row['customer_email'])        . '"'
                . ' data-phone="'  . esc_attr($row['customer_phone'])        . '"'
                . ' data-order="'  . esc_attr((string) $row['order_id'])     . '"'
                . ' data-status="' . esc_attr(ucfirst($row['order_status'])) . '"'
                . ' data-plan="'   . esc_attr($row['plan'])                  . '"'
                . ' data-date="'   . esc_attr($row['session_date'])          . '"'
                . ' data-time="'   . esc_attr($row['session_label'])         . '"'
                . ' data-seat="'   . esc_attr($row['assigned_seat_label'])   . '"'
                . '>' . esc_html__('View Customer', 'ecospace-booking') . '</button>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="eco-bookings-inline-form">';
            wp_nonce_field('eco_booking_ops_' . (int) $row['order_id'] . '_' . (int) $row['item_id']);
            echo '<input type="hidden" name="action" value="eco_update_booking_ops">';
            echo '<input type="hidden" name="redirect_to" value="' . esc_url($current_url) . '">';
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
    echo '</div>';

    eco_render_bookings_pagination_nav($current_page, $total_pages, $args['base_url']);

    echo '<div id="eco_customer_modal" class="eco-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="eco_modal_title" aria-hidden="true">';
    echo '<div class="eco-modal-box">';
    echo '<button type="button" class="eco-modal-close" id="eco_modal_close" aria-label="' . esc_attr__('Close', 'ecospace-booking') . '">&times;</button>';
    echo '<h3 id="eco_modal_title">' . esc_html__('Customer Details', 'ecospace-booking') . '</h3>';
    echo '<table class="eco-modal-table">';
    echo '<tr><th>' . esc_html__('Name', 'ecospace-booking')         . '</th><td id="eco_modal_name"></td></tr>';
    echo '<tr><th>' . esc_html__('Email', 'ecospace-booking')        . '</th><td id="eco_modal_email"></td></tr>';
    echo '<tr><th>' . esc_html__('Phone', 'ecospace-booking')        . '</th><td id="eco_modal_phone"></td></tr>';
    echo '<tr><th>' . esc_html__('Order', 'ecospace-booking')        . '</th><td id="eco_modal_order"></td></tr>';
    echo '<tr><th>' . esc_html__('Order Status', 'ecospace-booking') . '</th><td id="eco_modal_status"></td></tr>';
    echo '<tr><th>' . esc_html__('Plan', 'ecospace-booking')         . '</th><td id="eco_modal_plan"></td></tr>';
    echo '<tr><th>' . esc_html__('Date', 'ecospace-booking')         . '</th><td id="eco_modal_date"></td></tr>';
    echo '<tr><th>' . esc_html__('Time', 'ecospace-booking')         . '</th><td id="eco_modal_time"></td></tr>';
    echo '<tr><th>' . esc_html__('Seat', 'ecospace-booking')         . '</th><td id="eco_modal_seat"></td></tr>';
    echo '</table>';
    echo '<div class="eco-modal-actions">';
    echo '<a id="eco_modal_email_link" href="#" class="button button-primary" target="_blank" rel="noopener noreferrer">'    . esc_html__('Send Email', 'ecospace-booking')  . '</a>';
    echo '<a id="eco_modal_order_link" href="#" class="button button-secondary" target="_blank" rel="noopener noreferrer">' . esc_html__('View Order', 'ecospace-booking')   . '</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

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
            refreshLabel.firstChild.textContent = paused ? "Auto-refresh paused. " : "Auto-refresh every 10 minutes. ";
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
    }, 600000);

      // Customer detail modal
      var ecoModal      = document.getElementById("eco_customer_modal");
      var ecoModalClose = document.getElementById("eco_modal_close");
      var ecoOrderBase  = ' . wp_json_encode(admin_url('post.php')) . ';

      document.querySelectorAll(".eco-view-customer").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var d = btn.dataset;
          document.getElementById("eco_modal_name").textContent   = d.name   || "\u2014";
          document.getElementById("eco_modal_email").textContent  = d.email  || "\u2014";
          document.getElementById("eco_modal_phone").textContent  = d.phone  || "\u2014";
          document.getElementById("eco_modal_order").textContent  = d.order  ? "#" + d.order : "\u2014";
          document.getElementById("eco_modal_status").textContent = d.status || "\u2014";
          document.getElementById("eco_modal_plan").textContent   = d.plan   || "\u2014";
          document.getElementById("eco_modal_date").textContent   = d.date   || "\u2014";
          document.getElementById("eco_modal_time").textContent   = d.time   || "\u2014";
          document.getElementById("eco_modal_seat").textContent   = d.seat   || "\u2014";
          document.getElementById("eco_modal_email_link").href = d.email ? "mailto:" + d.email : "#";
          document.getElementById("eco_modal_order_link").href = d.order
            ? ecoOrderBase + "?post=" + encodeURIComponent(d.order) + "&action=edit"
            : "#";
          ecoModal.style.display = "flex";
          ecoModal.setAttribute("aria-hidden", "false");
        });
      });

      function ecoCloseModal() {
        if (ecoModal) {
          ecoModal.style.display = "none";
          ecoModal.setAttribute("aria-hidden", "true");
        }
      }

      if (ecoModalClose) { ecoModalClose.addEventListener("click", ecoCloseModal); }
      if (ecoModal) {
        ecoModal.addEventListener("click", function (e) {
          if (e.target === ecoModal) { ecoCloseModal(); }
        });
      }
      document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && ecoModal && ecoModal.style.display !== "none") { ecoCloseModal(); }
      });

      // ── Checked-in countdown timer ─────────────────────────────────────────
      var ecoTimerRows = Array.prototype.slice.call(
        document.querySelectorAll("tr[data-ops=checked_in]")
      );

      if (ecoTimerRows.length > 0 && "Notification" in window && Notification.permission === "default") {
        Notification.requestPermission();
      }

      function ecoFormatCountdown(ms) {
        if (ms <= 0) { return null; }
        var totalSec = Math.floor(ms / 1000);
        var h = Math.floor(totalSec / 3600);
        var m = Math.floor((totalSec % 3600) / 60);
        var s = totalSec % 60;
        if (h > 0) { return h + "h " + m + "m left"; }
        if (m > 0) { return m + "m " + s + "s left"; }
        return s + "s left";
      }

      function ecoGetOrCreateTimerEl(opsTd) {
        var el = opsTd.querySelector(".eco-timer-countdown, .eco-timer-expired");
        if (!el) {
          el = document.createElement("span");
          el.className = "eco-timer-countdown";
          opsTd.appendChild(el);
        }
        return el;
      }

      function ecoShowBanner(entries) {
        var banner = document.getElementById("eco_session_ended_banner");
        if (!banner) {
          banner = document.createElement("div");
          banner.id = "eco_session_ended_banner";
          banner.className = "eco-session-ended-banner";
          var dismiss = document.createElement("button");
          dismiss.className = "eco-banner-dismiss";
          dismiss.type = "button";
          dismiss.setAttribute("aria-label", "Dismiss");
          dismiss.textContent = "\u00d7";
          dismiss.addEventListener("click", function () { banner.remove(); });
          banner.appendChild(dismiss);
          var title = document.createElement("strong");
          title.textContent = "Session ended";
          banner.appendChild(title);
          var list = document.createElement("ul");
          list.id = "eco_banner_list";
          banner.appendChild(list);
          document.body.appendChild(banner);
        }
        var list = document.getElementById("eco_banner_list");
        entries.forEach(function (text) {
          var li = document.createElement("li");
          li.textContent = text;
          list.appendChild(li);
        });
      }

      function ecoFireNotification(customer, seat) {
        if ("Notification" in window && Notification.permission === "granted") {
          try {
            new Notification("Session Ended", {
              body: customer + " \u2014 " + seat,
              icon: ""
            });
          } catch (e) { /* silently ignore in contexts where Notification fails */ }
        }
      }

      // Process rows already past end time on load (tab was closed during session)
      ecoTimerRows.forEach(function (tr) {
        var d = tr.dataset;
        var endTs = new Date(d.endDate + "T" + (d.endHour.length < 2 ? "0" + d.endHour : d.endHour) + ":00:00").getTime();
        if (endTs && Date.now() >= endTs) {
          var key = "eco_timer_" + d.rowKey;
          var opsTd = tr.querySelector(".eco-ops-td");
          if (opsTd) {
            var el = ecoGetOrCreateTimerEl(opsTd);
            el.className = "eco-timer-expired";
            el.textContent = "Session ended";
          }
          if (!localStorage.getItem(key)) {
            localStorage.setItem(key, "notified");
            ecoShowBanner([d.customer + " \u2014 " + d.seat]);
          }
        }
      });

      // Live countdown tick
      setInterval(function () {
        var expired = [];
        ecoTimerRows.forEach(function (tr) {
          var d = tr.dataset;
          var endTs = new Date(d.endDate + "T" + (d.endHour.length < 2 ? "0" + d.endHour : d.endHour) + ":00:00").getTime();
          if (!endTs) { return; }
          var remaining = endTs - Date.now();
          var opsTd = tr.querySelector(".eco-ops-td");
          if (!opsTd) { return; }
          var el = ecoGetOrCreateTimerEl(opsTd);
          if (remaining > 0) {
            el.className = "eco-timer-countdown";
            el.textContent = ecoFormatCountdown(remaining);
          } else {
            var key = "eco_timer_" + d.rowKey;
            el.className = "eco-timer-expired";
            el.textContent = "Session ended";
            if (!localStorage.getItem(key)) {
              localStorage.setItem(key, "notified");
              expired.push(d.customer + " \u2014 " + d.seat);
              ecoFireNotification(d.customer, d.seat);
            }
          }
        });
        if (expired.length) { ecoShowBanner(expired); }
      }, 1000);
    })();
    </script>';

    echo '</div>';
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
    } elseif ($preset === 'draft') {
        $filters['order_status'] = 'checkout-draft';
    } elseif ($preset === 'active_sessions') {
        $current_hour = (int) gmdate('G', $now + (int) $offset_seconds);
        $filters['date']        = gmdate('Y-m-d', $now + (int) $offset_seconds);
        $filters['ops_status']  = 'checked_in';
        $filters['window_start'] = $current_hour;
        $filters['window_end']   = 24;
    } elseif ($preset === 'no_show') {
        $filters['ops_status'] = 'no_show';
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

        echo '<div class="wrap">';
        eco_render_workspace_bookings_interface();
        echo '</div>';
}
