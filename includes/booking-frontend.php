<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'eco_enqueue_booking_assets');
function eco_enqueue_booking_assets()
{
    if (!function_exists('is_product') || !is_product()) {
        return;
    }

    $product = wc_get_product(get_the_ID());
    if (!$product instanceof WC_Product) {
        return;
    }

    $enabled = get_post_meta($product->get_id(), '_eco_enable_booking', true);
    if ($enabled !== 'yes') {
        return;
    }

    $advanced_enabled = eco_is_advanced_booking_config_enabled($product->get_id());
    $booking_config = eco_get_product_booking_config($product->get_id());

    wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', array(), '4.6.13');
    wp_enqueue_style('eco-booking-style', ECO_BOOKING_URL . 'assets/css/booking.css', array('flatpickr'), ECO_BOOKING_VERSION);

    wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', array(), '4.6.13', true);
    wp_enqueue_script('eco-booking-script', ECO_BOOKING_URL . 'assets/js/booking.js', array('flatpickr'), ECO_BOOKING_VERSION, true);

    wp_localize_script(
        'eco-booking-script',
        'ecoBookingData',
        array(
            'productId' => (int) $product->get_id(),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'availabilityNonce' => wp_create_nonce('eco_booking_availability_' . $product->get_id()),
            'availabilityRefreshMs' => 15000,
            'currencySymbol' => get_woocommerce_currency_symbol(),
            'useAdvancedConfig' => $advanced_enabled,
            'hourlyRate' => (float) get_post_meta($product->get_id(), '_eco_hourly_rate', true),
            'plans' => $booking_config['plans'],
            'defaultPlan' => eco_get_default_booking_plan($product->get_id()),
            'defaultPrice' => $advanced_enabled ? eco_get_default_booking_product_price($product->get_id()) : 0,
            'openHour' => (int) $booking_config['open_hour'],
            'closeHour' => (int) $booking_config['close_hour'],
            'recurringSessionHours' => (int) $booking_config['recurring_session_hours'],
            'recurringStartMinHour' => (int) $booking_config['recurring_start_min_hour'],
            'recurringStartMaxHour' => (int) $booking_config['recurring_start_max_hour'],
            'bookedRecurringSlots' => eco_get_product_booked_slot_map($product->get_id()),
            'invalidHoursMessage' => sprintf(__('Hours exceed closing time (%s)', 'ecospace-booking'), eco_hour_label($booking_config['close_hour'])),
            'bookedTimeRangeMessage' => __('This time range is already booked. Please choose another time slot.', 'ecospace-booking'),
            'dailyUnavailableMessage' => __('This date is no longer available for a daily booking.', 'ecospace-booking'),
            'availabilityRefreshErrorMessage' => __('Could not refresh availability right now. Please try again.', 'ecospace-booking'),
            'duplicateRecurringDateMessage' => __('Office dates must be unique across office days. Please pick a different date.', 'ecospace-booking'),
            'duplicateRecurringSlotMessage' => __('You selected the same office date and time more than once. Please choose a unique office day.', 'ecospace-booking'),
            'bookedRecurringSlotMessage' => __('This office date and time is already booked. Please select another office day.', 'ecospace-booking'),
            'noTimeSlotsMessage' => __('No time slots available — this date is fully booked.', 'ecospace-booking'),
            'noEndTimeSlotsMessage' => __('No end times available — this start time is fully booked.', 'ecospace-booking'),
            'discount' => array(
                'enabled' => get_post_meta($product->get_id(), '_eco_discount_enabled', true) === 'yes',
                'percent' => (float) get_post_meta($product->get_id(), '_eco_discount_percent', true),
                'start'   => (string) get_post_meta($product->get_id(), '_eco_discount_start', true),
                'end'     => (string) get_post_meta($product->get_id(), '_eco_discount_end', true),
                'plans'   => (array)  get_post_meta($product->get_id(), '_eco_discount_plans', true),
                'today'   => current_time('Y-m-d'),
            ),
        )
    );
}

add_filter('woocommerce_get_price_html', 'eco_booking_product_price_html', 20, 2);
function eco_booking_product_price_html($price_html, $product)
{
    if (!$product instanceof WC_Product) {
        return $price_html;
    }

    if (is_admin() && !wp_doing_ajax()) {
        return $price_html;
    }

    if ((function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout())) {
        return $price_html;
    }

    if (get_post_meta($product->get_id(), '_eco_enable_booking', true) !== 'yes') {
        return $price_html;
    }

    if (!eco_is_advanced_booking_config_enabled($product->get_id())) {
        return $price_html;
    }

    $display_price = eco_get_default_booking_product_price($product->get_id());
    if ($display_price <= 0) {
        return $price_html;
    }

    return wc_price($display_price);
}

add_action('wp_ajax_eco_refresh_booking_availability', 'eco_refresh_booking_availability');
add_action('wp_ajax_nopriv_eco_refresh_booking_availability', 'eco_refresh_booking_availability');
function eco_refresh_booking_availability()
{
    $product_id = isset($_POST['product_id']) ? absint(wp_unslash($_POST['product_id'])) : 0;
    if ($product_id <= 0) {
        wp_send_json_error(array('message' => __('Invalid booking product.', 'ecospace-booking')), 400);
    }

    check_ajax_referer('eco_booking_availability_' . $product_id, 'nonce');

    $product = wc_get_product($product_id);
    if (!$product instanceof WC_Product) {
        wp_send_json_error(array('message' => __('Booking product not found.', 'ecospace-booking')), 404);
    }

    if (get_post_meta($product_id, '_eco_enable_booking', true) !== 'yes') {
        wp_send_json_error(array('message' => __('Booking is not enabled for this product.', 'ecospace-booking')), 400);
    }

    wp_send_json_success(
        array(
            'bookedRecurringSlots' => eco_get_product_booked_slot_map($product_id),
        )
    );
}

add_action('woocommerce_before_add_to_cart_button', 'eco_booking_ui');
function eco_booking_ui()
{
    global $product;

    if (!$product instanceof WC_Product) {
        return;
    }

    $enabled = get_post_meta($product->get_id(), '_eco_enable_booking', true);
    if ($enabled !== 'yes') {
        return;
    }

    $advanced_enabled = eco_is_advanced_booking_config_enabled($product->get_id());
    $booking_config = eco_get_product_booking_config($product->get_id());
    $enabled_plans = eco_get_enabled_booking_plans($product->get_id());
    if (empty($enabled_plans)) {
        echo '<div class="ecospace-booking-ui"><p class="eco-preferred-error" style="display:block;">' . esc_html__('Booking is enabled, but no booking plans are currently available for this product.', 'ecospace-booking') . '</p></div>';
        return;
    }

    $default_plan = eco_get_default_booking_plan($product->get_id());
    $hourly_min_hours = (int) ($booking_config['plans']['hourly']['min_hours'] ?? 1);
    $hourly_default_value = $advanced_enabled ? (string) $hourly_min_hours : '';
    $initial_booking_price = $advanced_enabled ? eco_get_default_booking_product_price($product->get_id()) : 0;
    $daily_session_hours = (int) ($booking_config['plans']['daily']['session_hours'] ?? 8);
    $seat_capacity = (int) get_post_meta($product->get_id(), '_eco_seat_capacity', true);
    ?>
    <div class="ecospace-booking-ui">
        <p>
            <label for="eco_plan"><?php esc_html_e('Plan', 'ecospace-booking'); ?></label>
            <select id="eco_plan" name="eco_plan" required>
                <?php foreach ($enabled_plans as $plan_key => $plan_config) : ?>
                    <option value="<?php echo esc_attr($plan_key); ?>" <?php selected($default_plan, $plan_key); ?>><?php echo esc_html($plan_config['label']); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="eco_start_date"><?php esc_html_e('Office Date', 'ecospace-booking'); ?></label>
            <input type="text" id="eco_start_date" name="eco_start_date" autocomplete="off" required>
        </p>

        <p id="eco_end_date_block" style="display:none;">
            <label for="eco_end_date"><?php esc_html_e('End Date', 'ecospace-booking'); ?></label>
            <input type="text" id="eco_end_date" name="eco_end_date" readonly>
        </p>

        <p id="eco_preferred_heading" class="eco-preferred-heading" style="display:none;">
            <?php esc_html_e("Please select the days you're coming.", 'ecospace-booking'); ?>
        </p>
        <div id="eco_preferred_days"></div>
        <p id="eco_preferred_hint" class="eco-preferred-hint"></p>
        <p id="eco_preferred_error" class="eco-preferred-error" style="display:none;"></p>

        <div id="eco_hourly_fields">
            <p>
                <label for="eco_start_time"><?php esc_html_e('Time In', 'ecospace-booking'); ?></label>
                <select id="eco_start_time" name="eco_start_time">
                    <option value=""><?php esc_html_e('Select', 'ecospace-booking'); ?></option>
                    <?php for ($hour = $booking_config['open_hour']; $hour <= $booking_config['close_hour'] - 1; $hour++) : ?>
                        <option value="<?php echo esc_attr($hour); ?>"><?php echo esc_html(eco_hour_label($hour)); ?></option>
                    <?php endfor; ?>
                </select>
            </p>
            <p id="eco_hours_field">
                <label for="eco_hours"><?php esc_html_e('Hours', 'ecospace-booking'); ?></label>
                <input type="number" id="eco_hours" name="eco_hours" min="<?php echo esc_attr($hourly_min_hours); ?>" max="<?php echo esc_attr($booking_config['close_hour'] - $booking_config['open_hour']); ?>" step="1" value="<?php echo esc_attr($hourly_default_value); ?>">
            </p>
        </div>

        <p id="eco_end_time_block">
            <label for="eco_end_time"><?php esc_html_e('Time Out', 'ecospace-booking'); ?></label>
            <input type="text" id="eco_end_time" name="eco_end_time" readonly>
        </p>

        <p class="eco-price-row">
            <strong><?php esc_html_e('Price:', 'ecospace-booking'); ?></strong>
            <span id="eco_original_price" class="eco-original-price" style="display:none;"></span>
            <span id="eco_price"><?php echo esc_html(wp_strip_all_tags(wc_price($initial_booking_price))); ?></span>
            <span id="eco_discount_badge" class="eco-discount-badge" style="display:none;"></span>
        </p>

        <?php if ($advanced_enabled) : ?>
            <p id="eco_daily_hint" class="eco-preferred-hint" style="display:none;">
                <?php
                printf(
                    esc_html__('Daily plan access is limited to %d hours from your selected time in.', 'ecospace-booking'),
                    esc_html($daily_session_hours)
                );
                ?>
            </p>
        <?php endif; ?>

        <?php if ($seat_capacity > 0) : ?>
            <p class="eco-capacity-row">
                <?php
                printf(
                    esc_html__('Seat capacity: %d', 'ecospace-booking'),
                    esc_html($seat_capacity)
                );
                ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}

add_filter('woocommerce_add_to_cart_validation', 'eco_validate_add_to_cart_booking', 10, 3);
function eco_validate_add_to_cart_booking($passed, $product_id)
{
    $result = eco_validate_booking_payload($product_id);
    if (!$result['ok']) {
        wc_add_notice($result['message'], 'error');
        return false;
    }

    return $passed;
}

add_filter('woocommerce_add_cart_item_data', 'eco_add_cart_item_booking_data', 10, 3);
function eco_add_cart_item_booking_data($cart_item_data, $product_id)
{
    $result = eco_validate_booking_payload($product_id);
    if (!$result['ok'] || empty($result['data'])) {
        return $cart_item_data;
    }

    $cart_item_data['eco_booking'] = $result['data'];
    $cart_item_data['eco_booking']['product_id'] = (int) $product_id;
    $cart_item_data['eco_booking']['unique_key'] = md5(microtime(true) . wp_rand());

    return $cart_item_data;
}

add_action('woocommerce_before_calculate_totals', 'eco_apply_booking_price_to_cart', 10, 1);
function eco_apply_booking_price_to_cart($cart)
{
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    if (!$cart instanceof WC_Cart) {
        return;
    }

    foreach ($cart->get_cart() as $cart_item) {
        if (!empty($cart_item['eco_booking']) && isset($cart_item['eco_booking']['price'])) {
            $booking_price = (float) $cart_item['eco_booking']['price'];
            $base_price    = isset($cart_item['eco_booking']['base_price']) ? (float) $cart_item['eco_booking']['base_price'] : 0.0;

            $cart_item['data']->set_price($booking_price);

            if ($base_price > 0 && $booking_price < $base_price) {
                // Discount active: show crossed-out base price + discounted sale price
                $cart_item['data']->set_regular_price($base_price);
                $cart_item['data']->set_sale_price($booking_price);
            } else {
                // No discount: clear sale price so WC shows a plain price
                $cart_item['data']->set_regular_price($booking_price);
                $cart_item['data']->set_sale_price('');
            }
        }
    }
}

add_filter('woocommerce_get_item_data', 'eco_render_booking_item_data', 10, 2);
function eco_render_booking_item_data($item_data, $cart_item)
{
    if (empty($cart_item['eco_booking'])) {
        return $item_data;
    }

    $booking = $cart_item['eco_booking'];

    $item_data[] = array(
        'key' => __('Booking Plan', 'ecospace-booking'),
        'value' => eco_plan_label($booking['plan'], (int) ($booking['product_id'] ?? 0)),
    );

    $item_data[] = array(
        'key' => __('Office Date', 'ecospace-booking'),
        'value' => eco_format_display_date($booking['start_date']),
    );

    if (!empty($booking['start_time']) && !empty($booking['end_time'])) {
        $item_data[] = array(
            'key' => __('Time In / Time Out', 'ecospace-booking'),
            'value' => eco_hour_label($booking['start_time']) . ' - ' . eco_hour_label($booking['end_time']),
        );
    }

    if (!empty($booking['preferred_slots']) && is_array($booking['preferred_slots'])) {
        $slot_lines = array();
        foreach ($booking['preferred_slots'] as $slot) {
            $line = eco_format_preferred_slot($slot);
            if ($line !== '') {
                $slot_lines[] = $line;
            }
        }

        if (!empty($slot_lines)) {
            $item_data[] = array(
                'key' => __('Office Days', 'ecospace-booking'),
                'value' => implode(', ', $slot_lines),
            );
        }
    } elseif (!empty($booking['preferred_days'])) {
        $item_data[] = array(
            'key' => __('Office Dates', 'ecospace-booking'),
            'value' => implode(', ', array_map('eco_format_display_date', $booking['preferred_days'])),
        );
    }

    return $item_data;
}

add_action('woocommerce_checkout_create_order_line_item', 'eco_add_booking_meta_to_order_item', 10, 4);
function eco_add_booking_meta_to_order_item($item, $cart_item_key, $values)
{
    if (empty($values['eco_booking'])) {
        return;
    }

    $booking = $values['eco_booking'];

    $item->add_meta_data(__('Booking Plan', 'ecospace-booking'), eco_plan_label($booking['plan'], (int) $item->get_product_id()), true);
    $item->add_meta_data(__('Office Date', 'ecospace-booking'), eco_format_display_date($booking['start_date']), true);

    if (!empty($booking['start_time']) && !empty($booking['end_time'])) {
        $item->add_meta_data(__('Time In', 'ecospace-booking'), eco_hour_label($booking['start_time']), true);
        $item->add_meta_data(__('Time Out', 'ecospace-booking'), eco_hour_label($booking['end_time']), true);
    }

    if (!empty($booking['preferred_slots']) && is_array($booking['preferred_slots'])) {
        $slot_lines = array();
        foreach ($booking['preferred_slots'] as $slot) {
            $line = eco_format_preferred_slot($slot);
            if ($line !== '') {
                $slot_lines[] = $line;
            }
        }

        if (!empty($slot_lines)) {
            $item->add_meta_data(__('Office Days', 'ecospace-booking'), implode(', ', $slot_lines), true);
        }
    } elseif (!empty($booking['preferred_days'])) {
        $item->add_meta_data(__('Office Dates', 'ecospace-booking'), implode(', ', array_map('eco_format_display_date', $booking['preferred_days'])), true);
    }

    $item->add_meta_data('_eco_booking_payload', wp_json_encode($booking), true);
}

add_action('woocommerce_after_checkout_validation', 'eco_revalidate_slots_before_checkout', 10, 2);
function eco_revalidate_slots_before_checkout($posted_data, $errors)
{
    if (!WC()->cart instanceof WC_Cart) {
        return;
    }

    foreach (WC()->cart->get_cart() as $cart_item) {
        if (empty($cart_item['eco_booking']) || empty($cart_item['eco_booking']['plan'])) {
            continue;
        }

        $product_id = (int) ($cart_item['eco_booking']['product_id'] ?? 0);
        if ($product_id <= 0) {
            continue;
        }

        $slots = eco_booking_slots_from_payload($cart_item['eco_booking']);

        $conflict_result = eco_validate_paid_slot_conflicts($product_id, $slots);
        if (!$conflict_result['ok']) {
            $errors->add('eco_booking_slot_unavailable', $conflict_result['message']);
            return;
        }
    }
}

add_action('woocommerce_checkout_order_processed', 'eco_allocate_pending_order_slots', 10, 1);
add_action('woocommerce_payment_complete', 'eco_allocate_paid_recurring_slots', 10, 1);
add_action('woocommerce_order_status_processing', 'eco_allocate_paid_recurring_slots', 10, 1);
add_action('woocommerce_order_status_on-hold', 'eco_allocate_paid_recurring_slots', 10, 1);
add_action('woocommerce_order_status_completed', 'eco_allocate_paid_recurring_slots', 10, 1);
add_action('woocommerce_thankyou', 'eco_allocate_paid_recurring_slots', 20, 1);
function eco_allocate_pending_order_slots($order_id)
{
    eco_lock_paid_order_slots($order_id);
}

function eco_allocate_paid_recurring_slots($order_id)
{
    eco_lock_paid_order_slots($order_id);
}

add_action('woocommerce_order_status_refunded', 'eco_release_order_slots', 10, 1);
add_action('woocommerce_order_status_cancelled', 'eco_release_order_slots', 10, 1);
add_action('woocommerce_order_status_failed', 'eco_release_order_slots', 10, 1);
function eco_release_order_slots($order_id)
{
    eco_unlock_order_slots($order_id);
}

add_action('woocommerce_order_status_changed', 'eco_handle_order_slot_status_transition', 10, 4);
function eco_handle_order_slot_status_transition($order_id, $from_status, $to_status, $order)
{
    $to_status = is_string($to_status) ? $to_status : '';

    if (in_array($to_status, array('processing', 'on-hold', 'completed'), true)) {
        eco_lock_paid_order_slots($order_id);
        return;
    }

    if (in_array($to_status, array('refunded', 'cancelled', 'failed'), true)) {
        eco_unlock_order_slots($order_id);
    }
}

add_shortcode('eco_workspace_bookings', 'eco_workspace_bookings_shortcode');
function eco_workspace_bookings_shortcode($atts = array())
{
    if (!is_user_logged_in()) {
        return '<div class="eco-workspace-bookings-shortcode"><p>' . esc_html__('Please log in with a staff account to manage workspace bookings.', 'ecospace-booking') . '</p></div>';
    }

    if (!current_user_can('manage_woocommerce')) {
        return '<div class="eco-workspace-bookings-shortcode"><p>' . esc_html__('You are not allowed to manage workspace bookings from this page.', 'ecospace-booking') . '</p></div>';
    }

    $page_id = get_queried_object_id();
    $base_url = $page_id ? get_permalink($page_id) : home_url('/');

    ob_start();
    eco_render_workspace_bookings_interface(
        array(
            'context' => 'frontend',
            'base_url' => $base_url,
            'reset_url' => $base_url,
            'heading_tag' => 'h2',
        )
    );
    return (string) ob_get_clean();
}
