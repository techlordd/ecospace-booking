<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('woocommerce_product_options_general_product_data', 'eco_product_fields');
function eco_product_fields()
{
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

    echo '<p class="form-field">';
    echo '<span class="description">' . esc_html__('Recurring plans (Weekly 3x, Weekly 5x, Monthly 3x, Monthly 5x) require a preferred date, start time, and end time per session. Session duration must not exceed 8 hours and must stay within workspace opening hours.', 'ecospace-booking') . '</span>';
    echo '</p>';

    $product_id = get_the_ID();
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
