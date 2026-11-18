<?php
/**
 * EcoSpace admin: top-level menu and the Dashboard / Workspaces / Customers /
 * Payments / Active Sessions / Plans / Settings screens.
 *
 * Everything here is a VIEW over data the plugin already stores (WooCommerce orders,
 * order-item booking payloads, product booking meta). No new tables, post types or
 * booking-engine changes. The Bookings screen is the existing bookings interface.
 */

if (!defined('ABSPATH')) {
    exit;
}

// ─── Menu ─────────────────────────────────────────────────────────────────────

add_action('admin_menu', 'eco_register_main_admin_menu', 9);
function eco_register_main_admin_menu()
{
    $cap = 'manage_woocommerce';

    add_menu_page(__('EcoSpace', 'ecospace-booking'), __('EcoSpace', 'ecospace-booking'), $cap, 'ecospace', 'eco_render_dashboard_page', 'dashicons-building', 56);

    $pages = array(
        array('ecospace', __('Dashboard', 'ecospace-booking'), 'eco_render_dashboard_page'),
        array('eco-workspace-bookings', __('Bookings', 'ecospace-booking'), 'eco_render_workspace_bookings_page'),
        array('eco-workspaces', __('Workspaces', 'ecospace-booking'), 'eco_render_workspaces_page'),
        array('eco-customers', __('Customers', 'ecospace-booking'), 'eco_render_customers_page'),
        array('eco-payments', __('Payments', 'ecospace-booking'), 'eco_render_payments_page'),
        array('eco-active-sessions', __('Active Sessions', 'ecospace-booking'), 'eco_render_active_sessions_page'),
        array('eco-plans', __('Plans', 'ecospace-booking'), 'eco_render_plans_page'),
        array('eco-settings', __('Settings', 'ecospace-booking'), 'eco_render_settings_page'),
    );
    foreach ($pages as $page) {
        add_submenu_page('ecospace', $page[1], $page[1], $cap, $page[0], $page[2]);
    }

    // Convenience shortcut under WooCommerce. It is a plain link to the Bookings screen (no page
    // callback of its own), so it always opens the real page: admin.php?page=eco-workspace-bookings.
    add_submenu_page('woocommerce', __('Bookings', 'ecospace-booking'), __('Bookings', 'ecospace-booking'), $cap, 'admin.php?page=eco-workspace-bookings');
}

/**
 * Keep EcoSpace pages registered under the EcoSpace menu only.
 *
 * If another plugin (e.g. a menu editor with a saved layout) leaves an EcoSpace slug under a
 * different parent such as WooCommerce, WordPress resolves the page to that parent first, finds no
 * page hook registered there and answers "Sorry, you are not allowed to access this page" (and the
 * menu link loses its admin.php?page= prefix). This runs after all other admin_menu callbacks:
 * it removes such stray copies and re-adds any EcoSpace page that went missing.
 */
add_action('admin_menu', 'eco_repair_admin_menu', PHP_INT_MAX);
function eco_repair_admin_menu()
{
    global $submenu;
    if (!is_array($submenu)) {
        return;
    }

    $pages = array(
        'eco-workspace-bookings' => array(__('Bookings', 'ecospace-booking'), 'eco_render_workspace_bookings_page'),
        'eco-workspaces' => array(__('Workspaces', 'ecospace-booking'), 'eco_render_workspaces_page'),
        'eco-customers' => array(__('Customers', 'ecospace-booking'), 'eco_render_customers_page'),
        'eco-payments' => array(__('Payments', 'ecospace-booking'), 'eco_render_payments_page'),
        'eco-active-sessions' => array(__('Active Sessions', 'ecospace-booking'), 'eco_render_active_sessions_page'),
        'eco-plans' => array(__('Plans', 'ecospace-booking'), 'eco_render_plans_page'),
        'eco-settings' => array(__('Settings', 'ecospace-booking'), 'eco_render_settings_page'),
    );

    // 1. Remove copies registered under any other parent.
    foreach ($submenu as $parent => $items) {
        if ($parent === 'ecospace') {
            continue;
        }
        foreach ($items as $key => $item) {
            if (isset($item[2]) && isset($pages[$item[2]])) {
                unset($submenu[$parent][$key]);
            }
        }
    }

    // 2. Re-add anything missing from the EcoSpace menu.
    $present = array();
    foreach ((isset($submenu['ecospace']) ? $submenu['ecospace'] : array()) as $item) {
        if (isset($item[2])) {
            $present[$item[2]] = true;
        }
    }
    foreach ($pages as $slug => $page) {
        if (!isset($present[$slug])) {
            add_submenu_page('ecospace', $page[0], $page[0], 'manage_woocommerce', $slug, $page[1]);
        }
    }
}

function eco_get_admin_page_slugs()
{
    return array('ecospace', 'eco-workspace-bookings', 'eco-workspaces', 'eco-customers', 'eco-payments', 'eco-active-sessions', 'eco-plans', 'eco-settings', 'eco-seat-availability');
}

add_action('admin_enqueue_scripts', 'eco_enqueue_admin_styles');
function eco_enqueue_admin_styles()
{
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if (!in_array($page, eco_get_admin_page_slugs(), true)) {
        return;
    }
    wp_enqueue_style('eco-admin', ECO_BOOKING_URL . 'assets/css/admin.css', array(), ECO_BOOKING_VERSION);
}

// ─── Shared helpers ───────────────────────────────────────────────────────────

function eco_admin_page_url($slug, $args = array())
{
    return add_query_arg($args, admin_url('admin.php?page=' . $slug));
}

function eco_admin_open($title, $subtitle = '')
{
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You are not allowed to view this page.', 'ecospace-booking'));
    }
    echo '<div class="wrap eco-admin">';
    echo '<div class="eco-ui-header"><h1>' . esc_html($title) . '</h1>';
    if ($subtitle !== '') {
        echo '<p>' . esc_html($subtitle) . '</p>';
    }
    echo '</div>';
}

function eco_admin_close()
{
    echo '</div>';
}

function eco_admin_render_ops_notice()
{
    $notice = isset($_GET['eco_ops_notice']) ? sanitize_key(wp_unslash($_GET['eco_ops_notice'])) : '';
    $message = isset($_GET['eco_ops_message']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['eco_ops_message']))) : '';
    if ($notice && $message) {
        echo '<div class="notice ' . ($notice === 'success' ? 'notice-success' : 'notice-error') . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
}

function eco_admin_card($label, $value, $tone = 'neutral', $url = '', $hint = '')
{
    $tag = ($url !== '') ? 'a' : 'div';
    $href = ($url !== '') ? ' href="' . esc_url($url) . '"' : '';
    echo '<' . $tag . $href . ' class="eco-ui-card eco-tone-' . esc_attr($tone) . '">';
    echo '<span class="eco-ui-card-value">' . wp_kses_post((string) $value) . '</span>';
    echo '<span class="eco-ui-card-label">' . esc_html($label) . '</span>';
    if ($hint !== '') {
        echo '<span class="eco-ui-card-hint">' . wp_kses_post($hint) . '</span>';
    }
    echo '</' . $tag . '>';
}

function eco_admin_pill($label, $tone = 'neutral')
{
    return '<span class="eco-ui-pill eco-tone-' . esc_attr($tone) . '">' . esc_html($label) . '</span>';
}

function eco_admin_pagination($total_items, $per_page, $current, $base_url)
{
    $total_pages = (int) ceil($total_items / max(1, $per_page));
    if ($total_pages <= 1) {
        return;
    }
    $links = paginate_links(array(
        'base' => add_query_arg('eco_paged', '%#%', $base_url),
        'format' => '',
        'current' => $current,
        'total' => $total_pages,
        'type' => 'plain',
    ));
    echo '<div class="eco-ui-pagination">' . wp_kses_post((string) $links) . '</div>';
}

function eco_admin_current_page_number()
{
    return isset($_GET['eco_paged']) ? max(1, absint(wp_unslash($_GET['eco_paged']))) : 1;
}

function eco_admin_format_money($amount)
{
    return function_exists('wc_price') ? wc_price((float) $amount) : number_format((float) $amount, 2);
}

function eco_admin_row_ops_tone($ops_status)
{
    $map = array(
        'booked' => 'blue', 'assigned' => 'purple', 'checked_in' => 'amber',
        'completed' => 'green', 'no_show' => 'red', 'cancelled' => 'gray',
    );
    return isset($map[$ops_status]) ? $map[$ops_status] : 'neutral';
}

// ─── Payment state ────────────────────────────────────────────────────────────

function eco_get_payment_state_labels()
{
    return array(
        'paid' => __('Paid', 'ecospace-booking'),
        'unpaid' => __('Unpaid', 'ecospace-booking'),
        'pending' => __('Pending', 'ecospace-booking'),
        'refunded' => __('Refunded', 'ecospace-booking'),
        'failed' => __('Failed', 'ecospace-booking'),
        'cancelled' => __('Cancelled', 'ecospace-booking'),
        'draft' => __('Draft', 'ecospace-booking'),
    );
}

/**
 * Map a WooCommerce order status to a payment state.
 * paid = processing/completed, unpaid = awaiting payment, pending = on hold (awaiting confirmation).
 */
function eco_get_payment_state($wc_status)
{
    switch ($wc_status) {
        case 'processing':
        case 'completed':
            return 'paid';
        case 'pending':
            return 'unpaid';
        case 'on-hold':
            return 'pending';
        case 'refunded':
            return 'refunded';
        case 'failed':
            return 'failed';
        case 'checkout-draft':
            return 'draft';
        default:
            return 'cancelled';
    }
}

function eco_get_payment_state_tone($state)
{
    $map = array('paid' => 'green', 'unpaid' => 'amber', 'pending' => 'blue', 'refunded' => 'purple', 'failed' => 'red', 'cancelled' => 'gray', 'draft' => 'gray');
    return isset($map[$state]) ? $map[$state] : 'neutral';
}

// ─── Orders index (Customers / Payments / Dashboard totals) ───────────────────
//
// One paged scan over WooCommerce orders, reduced to small scalar records and cached for
// a few minutes, so Customers/Payments/Dashboard don't each re-load every order.

const ECO_ORDERS_INDEX_TRANSIENT = 'eco_orders_index_v1';

function eco_get_orders_index($force = false)
{
    if (!$force) {
        $cached = get_transient(ECO_ORDERS_INDEX_TRANSIENT);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $index = array();
    $page = 1;
    $batch_size = 200;

    do {
        $batch = wc_get_orders(array(
            'limit' => $batch_size,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => eco_get_all_wc_status_keys(),
            'type' => 'shop_order',
        ));

        foreach ($batch as $order) {
            $plans = array();
            $sessions = 0;
            $last_session = '';
            foreach ($order->get_items() as $item) {
                $booking = eco_parse_booking_payload_from_item($item);
                if (!is_array($booking)) {
                    continue;
                }
                $dates = eco_get_session_dates_from_booking($booking);
                $plans[sanitize_text_field((string) ($booking['plan'] ?? ''))] = true;
                $sessions += max(1, count($dates));
                if (!empty($dates)) {
                    $last = end($dates);
                    if ($last > $last_session) {
                        $last_session = $last;
                    }
                }
            }
            if (empty($plans)) {
                continue; // Not a booking order.
            }

            $email = strtolower(trim((string) $order->get_billing_email()));
            $name = trim((string) $order->get_formatted_billing_full_name());
            $created = $order->get_date_created();
            $status = $order->get_status();

            $index[] = array(
                'id' => (int) $order->get_id(),
                'ts' => $created ? (int) $created->getTimestamp() : 0,
                'status' => $status,
                'state' => eco_get_payment_state($status),
                'total' => (float) $order->get_total(),
                'key' => ($email !== '') ? $email : 'order-' . (int) $order->get_id(),
                'name' => ($name !== '') ? $name : __('Guest', 'ecospace-booking'),
                'email' => $email,
                'phone' => (string) $order->get_billing_phone(),
                'plans' => array_keys($plans),
                'sessions' => $sessions,
                'last_session' => $last_session,
                'url' => $order->get_edit_order_url(),
            );
        }

        $page++;
    } while (count($batch) === $batch_size);

    set_transient(ECO_ORDERS_INDEX_TRANSIENT, $index, 5 * MINUTE_IN_SECONDS);
    return $index;
}

add_action('woocommerce_new_order', 'eco_flush_orders_index');
add_action('woocommerce_order_status_changed', 'eco_flush_orders_index');
add_action('woocommerce_update_order', 'eco_flush_orders_index');
function eco_flush_orders_index()
{
    delete_transient(ECO_ORDERS_INDEX_TRANSIENT);
}

function eco_get_customers_from_index($index)
{
    $customers = array();
    $today = wp_date('Y-m-d');

    foreach ($index as $o) {
        $k = $o['key'];
        if (!isset($customers[$k])) {
            $customers[$k] = array(
                'key' => $k, 'name' => $o['name'], 'email' => $o['email'], 'phone' => $o['phone'],
                'orders' => array(), 'sessions' => 0, 'spent' => 0.0, 'last_ts' => 0, 'plans' => array(), 'active_plan' => '',
            );
        }
        $c = &$customers[$k];
        $c['orders'][] = $o;
        $c['sessions'] += $o['sessions'];
        if ($o['state'] === 'paid') {
            $c['spent'] += $o['total'];
            if ($o['last_session'] !== '' && $o['last_session'] >= $today && $c['active_plan'] === '') {
                $c['active_plan'] = implode(', ', array_map('eco_plan_label', $o['plans']));
            }
        }
        foreach ($o['plans'] as $plan) {
            $c['plans'][$plan] = true;
        }
        if ($o['ts'] > $c['last_ts']) {
            $c['last_ts'] = $o['ts'];
        }
        unset($c);
    }

    uasort($customers, function ($a, $b) {
        return $b['last_ts'] <=> $a['last_ts'];
    });
    return $customers;
}

// ─── Seat state (Dashboard / Workspaces) ──────────────────────────────────────

/**
 * Today's session rows plus a per-seat state: available / occupied / reserved / unavailable.
 */
function eco_get_seat_states_now()
{
    $seats = eco_get_booking_enabled_products_for_assignment();
    $today = current_time('Y-m-d');
    $hour = (int) current_time('G');

    $rows = eco_build_workspace_booking_rows(array(
        'date' => $today, 'plan' => '', 'order_status' => '', 'payment' => 'all',
        'ops_status' => 'all', 'search' => '', 'window_start' => 0, 'window_end' => 0,
    ));

    $states = array();
    foreach ($seats as $seat_id => $seat_name) {
        $states[$seat_id] = array('name' => $seat_name, 'state' => 'available', 'customer' => '', 'until' => 0);
    }

    $dead_orders = array('cancelled', 'failed', 'refunded', 'checkout-draft');
    foreach ($rows as $row) {
        $pid = (int) $row['assigned_product_id'];
        if (!isset($states[$pid]) || in_array($row['order_status'], $dead_orders, true)) {
            continue;
        }
        if (in_array($row['ops_status'], array('cancelled', 'no_show', 'completed'), true)) {
            continue;
        }
        if ($row['ops_status'] === 'checked_in') {
            $states[$pid] = array('name' => $states[$pid]['name'], 'state' => 'occupied', 'customer' => $row['customer_name'], 'until' => (int) $row['session_end']);
        } elseif ($states[$pid]['state'] === 'available' && (int) $row['session_end'] > $hour) {
            $states[$pid] = array('name' => $states[$pid]['name'], 'state' => 'reserved', 'customer' => $row['customer_name'], 'until' => (int) $row['session_start']);
        }
    }

    // Booking-enabled seats that are not published are unavailable.
    $unpublished = wc_get_products(array(
        'limit' => 100,
        'status' => array('draft', 'private', 'pending'),
        'return' => 'ids',
        'meta_query' => array(array('key' => '_eco_enable_booking', 'value' => 'yes', 'compare' => '=')),
    ));
    foreach ($unpublished as $pid) {
        $states[(int) $pid] = array('name' => get_the_title($pid), 'state' => 'unavailable', 'customer' => '', 'until' => 0);
    }

    return array('states' => $states, 'rows' => $rows);
}

function eco_get_seat_state_labels()
{
    return array(
        'available' => array(__('Available', 'ecospace-booking'), 'green'),
        'occupied' => array(__('Occupied', 'ecospace-booking'), 'amber'),
        'reserved' => array(__('Reserved', 'ecospace-booking'), 'blue'),
        'unavailable' => array(__('Unavailable', 'ecospace-booking'), 'gray'),
    );
}

// ─── Dashboard ────────────────────────────────────────────────────────────────

function eco_render_dashboard_page()
{
    eco_admin_open(__('Dashboard', 'ecospace-booking'), __('A live overview of EcoSpace: bookings, sessions in progress, and seat availability.', 'ecospace-booking'));

    $seat_data = eco_get_seat_states_now();
    $rows = $seat_data['rows'];
    $index = eco_get_orders_index();

    $counts = array('available' => 0, 'occupied' => 0, 'reserved' => 0, 'unavailable' => 0);
    foreach ($seat_data['states'] as $seat) {
        $counts[$seat['state']]++;
    }

    $sessions_today = 0;
    $active_now = 0;
    foreach ($rows as $row) {
        if ($row['ops_status'] !== 'cancelled') {
            $sessions_today++;
        }
        if ($row['ops_status'] === 'checked_in') {
            $active_now++;
        }
    }

    $month_start = strtotime(wp_date('Y-m-01 00:00:00'));
    $total_bookings = 0;
    $revenue_month = 0.0;
    $awaiting = 0;
    foreach ($index as $o) {
        if (!in_array($o['state'], array('draft', 'cancelled'), true)) {
            $total_bookings++;
        }
        if ($o['state'] === 'paid' && $o['ts'] >= $month_start) {
            $revenue_month += $o['total'];
        }
        if (in_array($o['state'], array('unpaid', 'pending'), true)) {
            $awaiting++;
        }
    }

    echo '<div class="eco-ui-cards">';
    eco_admin_card(__('Total bookings', 'ecospace-booking'), $total_bookings, 'neutral', eco_admin_page_url('eco-workspace-bookings', array('preset' => 'all')));
    eco_admin_card(__('Sessions today', 'ecospace-booking'), $sessions_today, 'blue', eco_admin_page_url('eco-workspace-bookings', array('preset' => 'today')));
    eco_admin_card(__('Active sessions', 'ecospace-booking'), $active_now, 'amber', eco_admin_page_url('eco-active-sessions'));
    eco_admin_card(__('Available seats', 'ecospace-booking'), $counts['available'], 'green', eco_admin_page_url('eco-workspaces'), esc_html(sprintf(__('of %d seats', 'ecospace-booking'), count($seat_data['states']))));
    eco_admin_card(__('Revenue this month', 'ecospace-booking'), eco_admin_format_money($revenue_month), 'green', eco_admin_page_url('eco-payments'));
    eco_admin_card(__('Awaiting payment', 'ecospace-booking'), $awaiting, $awaiting > 0 ? 'red' : 'gray', eco_admin_page_url('eco-payments', array('state' => 'unpaid')));
    echo '</div>';

    echo '<div class="eco-ui-panel"><div class="eco-ui-panel-head"><h2>' . esc_html__("Today's schedule", 'ecospace-booking') . '</h2><a href="' . esc_url(eco_admin_page_url('eco-workspace-bookings', array('preset' => 'today'))) . '">' . esc_html__('Manage in Bookings', 'ecospace-booking') . '</a></div>';
    usort($rows, function ($a, $b) {
        return $a['session_start'] <=> $b['session_start'];
    });
    if (empty($rows)) {
        echo '<p class="eco-ui-empty">' . esc_html__('No sessions scheduled for today.', 'ecospace-booking') . '</p>';
    } else {
        echo '<div class="eco-ui-table-wrap"><table class="eco-ui-table"><thead><tr><th>' . esc_html__('Time', 'ecospace-booking') . '</th><th>' . esc_html__('Customer', 'ecospace-booking') . '</th><th>' . esc_html__('Seat', 'ecospace-booking') . '</th><th>' . esc_html__('Plan', 'ecospace-booking') . '</th><th>' . esc_html__('Status', 'ecospace-booking') . '</th></tr></thead><tbody>';
        $labels = eco_get_ops_status_labels();
        foreach (array_slice($rows, 0, 12) as $row) {
            echo '<tr><td>' . esc_html($row['session_label']) . '</td><td>' . esc_html($row['customer_name']) . '</td><td>' . esc_html($row['assigned_seat_label']) . '</td><td>' . esc_html(eco_plan_label($row['plan'])) . '</td><td>'
                . eco_admin_pill($labels[$row['ops_status']] ?? $row['ops_status'], eco_admin_row_ops_tone($row['ops_status'])) // Escaped in eco_admin_pill.
                . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';

    eco_admin_close();
}

// ─── Workspaces ───────────────────────────────────────────────────────────────

function eco_render_workspaces_page()
{
    eco_admin_open(__('Workspaces', 'ecospace-booking'), __('Seats and rooms: what is available, occupied, reserved or unavailable right now.', 'ecospace-booking'));

    $seat_data = eco_get_seat_states_now();
    $labels = eco_get_seat_state_labels();
    $counts = array_fill_keys(array_keys($labels), 0);
    foreach ($seat_data['states'] as $seat) {
        $counts[$seat['state']]++;
    }

    echo '<div class="eco-ui-cards">';
    foreach ($labels as $key => $meta) {
        eco_admin_card($meta[0], $counts[$key], $meta[1]);
    }
    echo '</div>';

    echo '<div class="eco-ui-panel"><div class="eco-ui-panel-head"><h2>' . esc_html__('Seats', 'ecospace-booking') . '</h2></div>';
    if (empty($seat_data['states'])) {
        echo '<p class="eco-ui-empty">' . esc_html__('No booking-enabled workspaces found. Enable booking on a WooCommerce product to add one.', 'ecospace-booking') . '</p>';
    } else {
        echo '<div class="eco-ui-table-wrap"><table class="eco-ui-table"><thead><tr><th>' . esc_html__('Workspace', 'ecospace-booking') . '</th><th>' . esc_html__('Now', 'ecospace-booking') . '</th><th>' . esc_html__('Details', 'ecospace-booking') . '</th><th></th></tr></thead><tbody>';
        foreach ($seat_data['states'] as $seat_id => $seat) {
            $detail = '';
            if ($seat['state'] === 'occupied') {
                $detail = sprintf(__('%1$s, until %2$s', 'ecospace-booking'), $seat['customer'], eco_hour_label($seat['until']));
            } elseif ($seat['state'] === 'reserved') {
                $detail = sprintf(__('%1$s, from %2$s', 'ecospace-booking'), $seat['customer'], eco_hour_label($seat['until']));
            }
            $edit = get_edit_post_link($seat_id, 'raw');
            echo '<tr><td><strong>' . esc_html($seat['name']) . '</strong></td><td>'
                . eco_admin_pill($labels[$seat['state']][0], $labels[$seat['state']][1]) // Escaped in eco_admin_pill.
                . '</td><td>' . esc_html($detail) . '</td><td>' . ($edit ? '<a href="' . esc_url($edit) . '">' . esc_html__('Edit details', 'ecospace-booking') . '</a>' : '') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';

    echo '<div class="eco-ui-panel"><div class="eco-ui-panel-head"><h2>' . esc_html__('Seat grid', 'ecospace-booking') . '</h2></div>';
    eco_render_seat_availability_interface('eco-workspaces', false);
    echo '</div>';

    eco_admin_close();
}

// ─── Customers ────────────────────────────────────────────────────────────────

function eco_render_customers_page()
{
    eco_admin_open(__('Customers', 'ecospace-booking'), __('Everyone who has booked EcoSpace: contact details, booking history and plans.', 'ecospace-booking'));

    $customers = eco_get_customers_from_index(eco_get_orders_index());
    $selected = isset($_GET['customer']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['customer']))) : '';

    if ($selected !== '' && isset($customers[$selected])) {
        $c = $customers[$selected];
        echo '<p><a href="' . esc_url(eco_admin_page_url('eco-customers')) . '">&larr; ' . esc_html__('All customers', 'ecospace-booking') . '</a></p>';
        echo '<div class="eco-ui-panel"><div class="eco-ui-panel-head"><h2>' . esc_html($c['name']) . '</h2></div><div class="eco-ui-meta">';
        echo '<div><span>' . esc_html__('Email', 'ecospace-booking') . '</span>' . ($c['email'] !== '' ? '<a href="mailto:' . esc_attr($c['email']) . '">' . esc_html($c['email']) . '</a>' : '&mdash;') . '</div>';
        echo '<div><span>' . esc_html__('Phone', 'ecospace-booking') . '</span>' . esc_html($c['phone'] !== '' ? $c['phone'] : '—') . '</div>';
        echo '<div><span>' . esc_html__('Active plan', 'ecospace-booking') . '</span>' . esc_html($c['active_plan'] !== '' ? $c['active_plan'] : '—') . '</div>';
        echo '<div><span>' . esc_html__('Total paid', 'ecospace-booking') . '</span>' . wp_kses_post(eco_admin_format_money($c['spent'])) . '</div>';
        echo '</div></div>';

        echo '<div class="eco-ui-panel"><div class="eco-ui-panel-head"><h2>' . esc_html__('Booking history', 'ecospace-booking') . '</h2></div>';
        echo '<div class="eco-ui-table-wrap"><table class="eco-ui-table"><thead><tr><th>' . esc_html__('Order', 'ecospace-booking') . '</th><th>' . esc_html__('Date', 'ecospace-booking') . '</th><th>' . esc_html__('Plan', 'ecospace-booking') . '</th><th>' . esc_html__('Sessions', 'ecospace-booking') . '</th><th>' . esc_html__('Amount', 'ecospace-booking') . '</th><th>' . esc_html__('Payment', 'ecospace-booking') . '</th></tr></thead><tbody>';
        $state_labels = eco_get_payment_state_labels();
        foreach ($c['orders'] as $o) {
            echo '<tr><td><a href="' . esc_url($o['url']) . '">#' . esc_html((string) $o['id']) . '</a></td><td>' . esc_html(wp_date('M j, Y', $o['ts'])) . '</td><td>' . esc_html(implode(', ', array_map('eco_plan_label', $o['plans']))) . '</td><td>' . esc_html((string) $o['sessions']) . '</td><td>' . wp_kses_post(eco_admin_format_money($o['total'])) . '</td><td>'
                . eco_admin_pill($state_labels[$o['state']] ?? $o['state'], eco_get_payment_state_tone($o['state'])) // Escaped in eco_admin_pill.
                . '</td></tr>';
        }
        echo '</tbody></table></div></div>';
        eco_admin_close();
        return;
    }

    $search = isset($_GET['s']) ? strtolower(sanitize_text_field(wp_unslash($_GET['s']))) : '';
    if ($search !== '') {
        $customers = array_filter($customers, function ($c) use ($search) {
            return strpos(strtolower($c['name'] . ' ' . $c['email'] . ' ' . $c['phone']), $search) !== false;
        });
    }

    echo '<form method="get" class="eco-ui-filters"><input type="hidden" name="page" value="eco-customers"><input type="search" name="s" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Search name, email or phone', 'ecospace-booking') . '"> <button class="button button-primary" type="submit">' . esc_html__('Search', 'ecospace-booking') . '</button>';
    if ($search !== '') {
        echo ' <a class="button" href="' . esc_url(eco_admin_page_url('eco-customers')) . '">' . esc_html__('Reset', 'ecospace-booking') . '</a>';
    }
    echo '</form>';

    $per_page = 20;
    $total = count($customers);
    $current = min(eco_admin_current_page_number(), max(1, (int) ceil($total / $per_page)));
    $page_items = array_slice($customers, ($current - 1) * $per_page, $per_page);

    echo '<p class="eco-ui-count">' . esc_html(sprintf(_n('%d customer', '%d customers', $total, 'ecospace-booking'), $total)) . '</p>';
    echo '<div class="eco-ui-table-wrap"><table class="eco-ui-table"><thead><tr><th>' . esc_html__('Customer', 'ecospace-booking') . '</th><th>' . esc_html__('Contact', 'ecospace-booking') . '</th><th>' . esc_html__('Bookings', 'ecospace-booking') . '</th><th>' . esc_html__('Sessions', 'ecospace-booking') . '</th><th>' . esc_html__('Plans', 'ecospace-booking') . '</th><th>' . esc_html__('Total paid', 'ecospace-booking') . '</th><th>' . esc_html__('Last booking', 'ecospace-booking') . '</th><th></th></tr></thead><tbody>';
    if (empty($page_items)) {
        echo '<tr><td colspan="8" class="eco-ui-empty">' . esc_html__('No customers found.', 'ecospace-booking') . '</td></tr>';
    }
    foreach ($page_items as $c) {
        echo '<tr><td><strong>' . esc_html($c['name']) . '</strong></td><td>' . esc_html($c['email']) . '<br><small>' . esc_html($c['phone']) . '</small></td><td>' . esc_html((string) count($c['orders'])) . '</td><td>' . esc_html((string) $c['sessions']) . '</td><td>' . esc_html(implode(', ', array_map('eco_plan_label', array_keys($c['plans'])))) . '</td><td>' . wp_kses_post(eco_admin_format_money($c['spent'])) . '</td><td>' . esc_html($c['last_ts'] ? wp_date('M j, Y', $c['last_ts']) : '—') . '</td><td><a href="' . esc_url(eco_admin_page_url('eco-customers', array('customer' => rawurlencode($c['key'])))) . '">' . esc_html__('History', 'ecospace-booking') . '</a></td></tr>';
    }
    echo '</tbody></table></div>';
    eco_admin_pagination($total, $per_page, $current, eco_admin_page_url('eco-customers', $search !== '' ? array('s' => $search) : array()));

    eco_admin_close();
}

// ─── Payments ─────────────────────────────────────────────────────────────────

function eco_render_payments_page()
{
    eco_admin_open(__('Payments', 'ecospace-booking'), __('Track payments and transactions: paid, unpaid, pending, refunded and failed.', 'ecospace-booking'));

    $index = eco_get_orders_index();
    $labels = eco_get_payment_state_labels();
    $state = isset($_GET['state']) ? sanitize_key(wp_unslash($_GET['state'])) : '';
    if (!isset($labels[$state])) {
        $state = '';
    }

    $totals = array();
    foreach (array_keys($labels) as $key) {
        $totals[$key] = array('count' => 0, 'amount' => 0.0);
    }
    foreach ($index as $o) {
        $totals[$o['state']]['count']++;
        $totals[$o['state']]['amount'] += $o['total'];
    }

    echo '<div class="eco-ui-cards">';
    foreach (array('paid', 'unpaid', 'pending', 'refunded', 'failed') as $key) {
        eco_admin_card($labels[$key], $totals[$key]['count'], eco_get_payment_state_tone($key), eco_admin_page_url('eco-payments', array('state' => $key)), eco_admin_format_money($totals[$key]['amount']));
    }
    echo '</div>';

    $search = isset($_GET['s']) ? strtolower(sanitize_text_field(wp_unslash($_GET['s']))) : '';
    $items = array_values(array_filter($index, function ($o) use ($state, $search) {
        if ($state !== '' && $o['state'] !== $state) {
            return false;
        }
        if ($search !== '' && strpos(strtolower($o['name'] . ' ' . $o['email'] . ' #' . $o['id']), $search) === false) {
            return false;
        }
        return true;
    }));

    echo '<form method="get" class="eco-ui-filters"><input type="hidden" name="page" value="eco-payments"><select name="state"><option value="">' . esc_html__('All payment states', 'ecospace-booking') . '</option>';
    foreach ($labels as $key => $label) {
        echo '<option value="' . esc_attr($key) . '"' . selected($state, $key, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select> <input type="search" name="s" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Customer, email or order #', 'ecospace-booking') . '"> <button class="button button-primary" type="submit">' . esc_html__('Filter', 'ecospace-booking') . '</button> <a class="button" href="' . esc_url(eco_admin_page_url('eco-payments')) . '">' . esc_html__('Reset', 'ecospace-booking') . '</a></form>';

    $per_page = 25;
    $total = count($items);
    $current = min(eco_admin_current_page_number(), max(1, (int) ceil($total / $per_page)));
    $page_items = array_slice($items, ($current - 1) * $per_page, $per_page);

    echo '<p class="eco-ui-count">' . esc_html(sprintf(_n('%d transaction', '%d transactions', $total, 'ecospace-booking'), $total)) . '</p>';
    echo '<div class="eco-ui-table-wrap"><table class="eco-ui-table"><thead><tr><th>' . esc_html__('Order', 'ecospace-booking') . '</th><th>' . esc_html__('Date', 'ecospace-booking') . '</th><th>' . esc_html__('Customer', 'ecospace-booking') . '</th><th>' . esc_html__('Plan', 'ecospace-booking') . '</th><th>' . esc_html__('Amount', 'ecospace-booking') . '</th><th>' . esc_html__('Payment', 'ecospace-booking') . '</th><th>' . esc_html__('Order status', 'ecospace-booking') . '</th></tr></thead><tbody>';
    if (empty($page_items)) {
        echo '<tr><td colspan="7" class="eco-ui-empty">' . esc_html__('No transactions found.', 'ecospace-booking') . '</td></tr>';
    }
    foreach ($page_items as $o) {
        echo '<tr><td><a href="' . esc_url($o['url']) . '">#' . esc_html((string) $o['id']) . '</a></td><td>' . esc_html(wp_date('M j, Y', $o['ts'])) . '</td><td><strong>' . esc_html($o['name']) . '</strong><br><small>' . esc_html($o['email']) . '</small></td><td>' . esc_html(implode(', ', array_map('eco_plan_label', $o['plans']))) . '</td><td>' . wp_kses_post(eco_admin_format_money($o['total'])) . '</td><td>'
            . eco_admin_pill($labels[$o['state']] ?? $o['state'], eco_get_payment_state_tone($o['state'])) // Escaped in eco_admin_pill.
            . '</td><td>' . esc_html(ucfirst(str_replace('-', ' ', $o['status']))) . '</td></tr>';
    }
    echo '</tbody></table></div>';
    $base_args = array();
    if ($state !== '') {
        $base_args['state'] = $state;
    }
    if ($search !== '') {
        $base_args['s'] = $search;
    }
    eco_admin_pagination($total, $per_page, $current, eco_admin_page_url('eco-payments', $base_args));

    eco_admin_close();
}

// ─── Active Sessions ──────────────────────────────────────────────────────────

function eco_render_active_sessions_page()
{
    eco_admin_open(__('Active Sessions', 'ecospace-booking'), __('Customers currently checked in: seat, start and end time, and session status. Complete a session when the customer leaves.', 'ecospace-booking'));
    eco_admin_render_ops_notice();

    $rows = eco_build_workspace_booking_rows(array(
        'date' => '', 'plan' => '', 'order_status' => '', 'payment' => 'all',
        'ops_status' => 'checked_in', 'search' => '', 'window_start' => 0, 'window_end' => 0,
    ));
    $rows = array_reverse($rows); // Oldest first: stale sessions surface at the top.

    $today = current_time('Y-m-d');
    $now_minutes = ((int) current_time('G')) * 60 + (int) current_time('i');
    $current_url = eco_admin_page_url('eco-active-sessions');
    $labels = eco_get_ops_status_labels();

    echo '<p class="eco-ui-count">' . esc_html(sprintf(_n('%d active session', '%d active sessions', count($rows), 'ecospace-booking'), count($rows))) . ' &middot; ' . esc_html__('Refreshes every minute.', 'ecospace-booking') . '</p>';
    echo '<div class="eco-ui-table-wrap"><table class="eco-ui-table"><thead><tr><th>' . esc_html__('Customer', 'ecospace-booking') . '</th><th>' . esc_html__('Seat', 'ecospace-booking') . '</th><th>' . esc_html__('Date', 'ecospace-booking') . '</th><th>' . esc_html__('Start', 'ecospace-booking') . '</th><th>' . esc_html__('End', 'ecospace-booking') . '</th><th>' . esc_html__('Status', 'ecospace-booking') . '</th><th></th></tr></thead><tbody>';
    if (empty($rows)) {
        echo '<tr><td colspan="7" class="eco-ui-empty">' . esc_html__('Nobody is checked in right now.', 'ecospace-booking') . '</td></tr>';
    }
    foreach ($rows as $row) {
        if ($row['session_date'] < $today) {
            $note = __('Past session — mark complete', 'ecospace-booking');
        } else {
            $left = ((int) $row['session_end']) * 60 - $now_minutes;
            $note = ($left > 0) ? sprintf(__('Ends in %1$dh %2$02dm', 'ecospace-booking'), intdiv($left, 60), $left % 60) : __('Time is up — mark complete', 'ecospace-booking');
        }
        echo '<tr><td><strong>' . esc_html($row['customer_name']) . '</strong><br><small>' . esc_html($row['customer_phone']) . '</small></td><td>' . esc_html($row['assigned_seat_label']) . '</td><td>' . esc_html($row['session_date']) . '</td><td>' . esc_html(eco_hour_label($row['session_start'])) . '</td><td>' . esc_html(eco_hour_label($row['session_end'])) . '</td><td>'
            . eco_admin_pill($labels[$row['ops_status']] ?? $row['ops_status'], 'amber') // Escaped in eco_admin_pill.
            . '<small class="eco-ui-note">' . esc_html($note) . '</small></td><td><div class="eco-row-actions">'
            . eco_get_row_action_form_html($row, $current_url, 'completed', __('Complete', 'ecospace-booking'), 'button-primary') // Escaped in helper.
            . eco_get_row_action_form_html($row, $current_url, 'booked', __('Undo check-in', 'ecospace-booking'), 'button-secondary') // Escaped in helper.
            . '</div></td></tr>';
    }
    echo '</tbody></table></div>';
    echo '<script>setTimeout(function(){ if (!document.hidden) { location.reload(); } }, 60000);</script>';

    eco_admin_close();
}

// ─── Plans ────────────────────────────────────────────────────────────────────

function eco_render_plans_page()
{
    eco_admin_open(__('Plans', 'ecospace-booking'), __('Booking plans (Daily, Weekly, Monthly and more): price, duration, limits and availability for each workspace.', 'ecospace-booking'));

    $defaults = eco_get_default_booking_config();
    $products = eco_get_booking_enabled_products_for_assignment();
    $plan_order = eco_get_plan_order();

    echo '<div class="eco-ui-panel"><div class="eco-ui-panel-head"><h2>' . esc_html__('Plan defaults', 'ecospace-booking') . '</h2></div>';
    echo '<div class="eco-ui-table-wrap"><table class="eco-ui-table"><thead><tr><th>' . esc_html__('Plan', 'ecospace-booking') . '</th><th>' . esc_html__('Price', 'ecospace-booking') . '</th><th>' . esc_html__('Sessions / limits', 'ecospace-booking') . '</th><th>' . esc_html__('Duration', 'ecospace-booking') . '</th></tr></thead><tbody>';
    foreach ($plan_order as $key) {
        $p = $defaults['plans'][$key];
        $price = isset($p['price']) ? eco_admin_format_money($p['price']) : __('Hourly rate', 'ecospace-booking');
        if ($p['type'] === 'recurring') {
            $limits = sprintf(_n('%d session', '%d sessions', (int) $p['sessions'], 'ecospace-booking'), (int) $p['sessions']);
            $duration = sprintf('%d %s', (int) $p['window_value'], $p['window_unit']);
        } elseif ($p['type'] === 'daily') {
            $limits = sprintf(__('%1$s – %2$s', 'ecospace-booking'), eco_hour_label((int) $p['start_hour']), eco_hour_label((int) $p['end_hour']));
            $duration = sprintf(__('%d hours', 'ecospace-booking'), (int) $p['session_hours']);
        } else {
            $limits = sprintf(__('Minimum %d hour(s)', 'ecospace-booking'), (int) ($p['min_hours'] ?? 1));
            $duration = __('Per hour', 'ecospace-booking');
        }
        echo '<tr><td><strong>' . esc_html($p['label']) . '</strong></td><td>' . wp_kses_post($price) . '</td><td>' . esc_html($limits) . '</td><td>' . esc_html($duration) . '</td></tr>';
    }
    echo '</tbody></table></div></div>';

    echo '<div class="eco-ui-panel"><div class="eco-ui-panel-head"><h2>' . esc_html__('Availability by workspace', 'ecospace-booking') . '</h2></div>';
    if (empty($products)) {
        echo '<p class="eco-ui-empty">' . esc_html__('No booking-enabled workspaces found.', 'ecospace-booking') . '</p>';
    } else {
        echo '<div class="eco-ui-table-wrap"><table class="eco-ui-table"><thead><tr><th>' . esc_html__('Workspace', 'ecospace-booking') . '</th>';
        foreach ($plan_order as $key) {
            echo '<th>' . esc_html($defaults['plans'][$key]['label']) . '</th>';
        }
        echo '<th></th></tr></thead><tbody>';
        foreach ($products as $product_id => $product_name) {
            $config = eco_get_product_booking_config($product_id);
            echo '<tr><td><strong>' . esc_html($product_name) . '</strong></td>';
            foreach ($plan_order as $key) {
                $plan = $config['plans'][$key] ?? array();
                if (($plan['enabled'] ?? 'no') !== 'yes') {
                    echo '<td>' . eco_admin_pill(__('Off', 'ecospace-booking'), 'gray') . '</td>'; // Escaped in eco_admin_pill.
                } elseif ($key === 'hourly') {
                    $rate = (float) get_post_meta($product_id, '_eco_hourly_rate', true);
                    echo '<td>' . wp_kses_post(eco_admin_format_money($rate)) . '/hr</td>';
                } else {
                    echo '<td>' . wp_kses_post(eco_admin_format_money($plan['price'] ?? 0)) . '</td>';
                }
            }
            $edit = get_edit_post_link($product_id, 'raw');
            echo '<td>' . ($edit ? '<a href="' . esc_url($edit) . '">' . esc_html__('Edit plans', 'ecospace-booking') . '</a>' : '') . '</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<p class="eco-ui-note">' . esc_html__('Prices, limits and availability are edited in each workspace product (Booking settings), which keeps the booking form, pricing and slot logic in one place.', 'ecospace-booking') . '</p>';
    }
    echo '</div>';

    eco_admin_close();
}

// ─── Settings ─────────────────────────────────────────────────────────────────

add_action('admin_post_eco_refresh_cache', 'eco_refresh_cache_action');
function eco_refresh_cache_action()
{
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You are not allowed to do this.', 'ecospace-booking'));
    }
    check_admin_referer('eco_refresh_cache');
    eco_flush_orders_index();
    wp_safe_redirect(add_query_arg('eco_refreshed', '1', eco_admin_page_url('eco-settings')));
    exit;
}

function eco_render_settings_page()
{
    eco_admin_open(__('Settings', 'ecospace-booking'), __('Business hours, shortcodes and maintenance for EcoSpace booking.', 'ecospace-booking'));

    if (isset($_GET['eco_refreshed'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Dashboard data refreshed.', 'ecospace-booking') . '</p></div>';
    }

    $d = eco_get_default_booking_config();

    echo '<div class="eco-ui-panel"><div class="eco-ui-panel-head"><h2>' . esc_html__('Booking defaults', 'ecospace-booking') . '</h2></div><div class="eco-ui-meta">';
    echo '<div><span>' . esc_html__('Opening hour', 'ecospace-booking') . '</span>' . esc_html(eco_hour_label((int) $d['open_hour'])) . '</div>';
    echo '<div><span>' . esc_html__('Closing hour', 'ecospace-booking') . '</span>' . esc_html(eco_hour_label((int) $d['close_hour'])) . '</div>';
    echo '<div><span>' . esc_html__('Weekly/Monthly session length', 'ecospace-booking') . '</span>' . esc_html(sprintf(__('%d hours', 'ecospace-booking'), (int) $d['recurring_session_hours'])) . '</div>';
    echo '<div><span>' . esc_html__('Recurring start window', 'ecospace-booking') . '</span>' . esc_html(eco_hour_label((int) $d['recurring_start_min_hour']) . ' – ' . eco_hour_label((int) $d['recurring_start_max_hour'])) . '</div>';
    echo '</div><p class="eco-ui-note">' . esc_html__('Individual workspaces can override these under Booking settings on the product (Advanced configuration).', 'ecospace-booking') . '</p></div>';

    echo '<div class="eco-ui-panel"><div class="eco-ui-panel-head"><h2>' . esc_html__('Shortcode', 'ecospace-booking') . '</h2></div>';
    echo '<p>' . esc_html__('Show the staff bookings screen on a front-end page (staff login required):', 'ecospace-booking') . ' <code>[eco_workspace_bookings]</code></p></div>';

    echo '<div class="eco-ui-panel"><div class="eco-ui-panel-head"><h2>' . esc_html__('Maintenance', 'ecospace-booking') . '</h2></div>';
    echo '<p>' . esc_html__('Customers, Payments and the Dashboard totals are cached for a few minutes and refresh automatically when orders change.', 'ecospace-booking') . '</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('eco_refresh_cache');
    echo '<input type="hidden" name="action" value="eco_refresh_cache"><button type="submit" class="button">' . esc_html__('Refresh data now', 'ecospace-booking') . '</button></form>';
    echo '<p class="eco-ui-note">' . esc_html(sprintf(__('Plugin version %s.', 'ecospace-booking'), ECO_BOOKING_VERSION)) . '</p></div>';

    eco_admin_close();
}
