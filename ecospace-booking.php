<?php
/**
 * Plugin Name: Ecospace Workspace Booking
 * Description: Coworking booking system with hourly, daily, weekly and monthly plans with calendar picker.
 * Version: 18.7
 * Author: Ecospace
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ECO_BOOKING_VERSION', '18.5');
define('ECO_BOOKING_PATH', plugin_dir_path(__FILE__));
define('ECO_BOOKING_URL', plugin_dir_url(__FILE__));

require_once ECO_BOOKING_PATH . 'includes/booking-engine.php';
require_once ECO_BOOKING_PATH . 'includes/booking-admin.php';
require_once ECO_BOOKING_PATH . 'includes/booking-frontend.php';
