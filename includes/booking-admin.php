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
