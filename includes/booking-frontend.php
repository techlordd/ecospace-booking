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
            'hourlyRate' => (float) get_post_meta($product->get_id(), '_eco_hourly_rate', true),
            'dailyPrice' => ECO_DAILY_PRICE,
            'weekly3Price' => ECO_WEEKLY3_PRICE,
            'weekly5Price' => ECO_WEEKLY5_PRICE,
            'monthly3Price' => ECO_MONTHLY3_PRICE,
            'monthly5Price' => ECO_MONTHLY5_PRICE,
            'openHour' => ECO_OPEN_HOUR,
            'closeHour' => ECO_CLOSE_HOUR,
            'recurringSessionHours' => ECO_RECURRING_SESSION_HOURS,
            'recurringStartMinHour' => ECO_RECURRING_START_MIN_HOUR,
            'recurringStartMaxHour' => ECO_RECURRING_START_MAX_HOUR,
            'bookedRecurringSlots' => eco_get_product_booked_slot_map($product->get_id()),
            'invalidHoursMessage' => __('Hours exceed closing time (8:00 PM)', 'ecospace-booking'),
            'bookedTimeRangeMessage' => __('This time range is already booked. Please choose another time slot.', 'ecospace-booking'),
            'dailyUnavailableMessage' => __('This date is no longer available for a daily booking.', 'ecospace-booking'),
            'availabilityRefreshErrorMessage' => __('Could not refresh availability right now. Please try again.', 'ecospace-booking'),
            'duplicateRecurringDateMessage' => __('Preferred dates must be unique across sessions. Please pick a different date.', 'ecospace-booking'),
            'duplicateRecurringSlotMessage' => __('You selected the same preferred date and time more than once. Please choose a unique slot.', 'ecospace-booking'),
            'bookedRecurringSlotMessage' => __('This preferred date and time is already booked. Please select another slot.', 'ecospace-booking'),
        )
    );
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

    $seat_capacity = (int) get_post_meta($product->get_id(), '_eco_seat_capacity', true);
    ?>
    <div class="ecospace-booking-ui">
        <p>
            <label for="eco_plan"><?php esc_html_e('Plan', 'ecospace-booking'); ?></label>
            <select id="eco_plan" name="eco_plan" required>
                <option value="hourly" selected><?php esc_html_e('Hourly', 'ecospace-booking'); ?></option>
                <option value="daily"><?php esc_html_e('Daily', 'ecospace-booking'); ?></option>
                <option value="weekly3"><?php esc_html_e('Weekly (3x)', 'ecospace-booking'); ?></option>
                <option value="weekly5"><?php esc_html_e('Weekly (5x)', 'ecospace-booking'); ?></option>
                <option value="monthly3"><?php esc_html_e('Monthly (3x)', 'ecospace-booking'); ?></option>
                <option value="monthly5"><?php esc_html_e('Monthly (5x)', 'ecospace-booking'); ?></option>
            </select>
        </p>

        <p>
            <label for="eco_start_date"><?php esc_html_e('Start Date', 'ecospace-booking'); ?></label>
            <input type="text" id="eco_start_date" name="eco_start_date" autocomplete="off" required>
        </p>

        <p id="eco_end_date_block" style="display:none;">
            <label for="eco_end_date"><?php esc_html_e('End Date', 'ecospace-booking'); ?></label>
            <input type="text" id="eco_end_date" name="eco_end_date" readonly>
        </p>

        <div id="eco_preferred_days"></div>
        <p id="eco_preferred_hint" class="eco-preferred-hint"></p>
        <p id="eco_preferred_error" class="eco-preferred-error" style="display:none;"></p>

        <div id="eco_hourly_fields">
            <p>
                <label for="eco_start_time"><?php esc_html_e('Start Time', 'ecospace-booking'); ?></label>
                <select id="eco_start_time" name="eco_start_time">
                    <option value=""><?php esc_html_e('Select', 'ecospace-booking'); ?></option>
                    <?php for ($hour = ECO_OPEN_HOUR; $hour <= ECO_CLOSE_HOUR - 1; $hour++) : ?>
                        <option value="<?php echo esc_attr($hour); ?>"><?php echo esc_html(eco_hour_label($hour)); ?></option>
                    <?php endfor; ?>
                </select>
            </p>
            <p>
                <label for="eco_hours"><?php esc_html_e('Hours', 'ecospace-booking'); ?></label>
                <input type="number" id="eco_hours" name="eco_hours" min="1" max="11" step="1">
            </p>
        </div>

        <p>
            <label for="eco_end_time"><?php esc_html_e('End Time', 'ecospace-booking'); ?></label>
            <input type="text" id="eco_end_time" name="eco_end_time" readonly>
        </p>

        <p class="eco-price-row">
            <strong><?php esc_html_e('Price:', 'ecospace-booking'); ?></strong>
            <span id="eco_price">0</span>
        </p>

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
            $cart_item['data']->set_price((float) $cart_item['eco_booking']['price']);
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
        'value' => eco_plan_label($booking['plan']),
    );

    $item_data[] = array(
        'key' => __('Start Date', 'ecospace-booking'),
        'value' => $booking['start_date'],
    );

    if (!empty($booking['start_time']) && !empty($booking['end_time'])) {
        $item_data[] = array(
            'key' => __('Time Window', 'ecospace-booking'),
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
                'key' => __('Preferred Sessions', 'ecospace-booking'),
                'value' => implode(', ', $slot_lines),
            );
        }
    } elseif (!empty($booking['preferred_days'])) {
        $item_data[] = array(
            'key' => __('Preferred Dates', 'ecospace-booking'),
            'value' => implode(', ', $booking['preferred_days']),
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

    $item->add_meta_data(__('Booking Plan', 'ecospace-booking'), eco_plan_label($booking['plan']), true);
    $item->add_meta_data(__('Start Date', 'ecospace-booking'), $booking['start_date'], true);

    if (!empty($booking['start_time']) && !empty($booking['end_time'])) {
        $item->add_meta_data(__('Start Time', 'ecospace-booking'), eco_hour_label($booking['start_time']), true);
        $item->add_meta_data(__('End Time', 'ecospace-booking'), eco_hour_label($booking['end_time']), true);
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
            $item->add_meta_data(__('Preferred Sessions', 'ecospace-booking'), implode(', ', $slot_lines), true);
        }
    } elseif (!empty($booking['preferred_days'])) {
        $item->add_meta_data(__('Preferred Dates', 'ecospace-booking'), implode(', ', $booking['preferred_days']), true);
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

add_action('woocommerce_payment_complete', 'eco_allocate_paid_recurring_slots', 10, 1);
add_action('woocommerce_order_status_processing', 'eco_allocate_paid_recurring_slots', 10, 1);
add_action('woocommerce_order_status_on-hold', 'eco_allocate_paid_recurring_slots', 10, 1);
add_action('woocommerce_order_status_completed', 'eco_allocate_paid_recurring_slots', 10, 1);
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
