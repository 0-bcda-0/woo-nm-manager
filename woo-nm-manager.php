<?php
/**
 * Plugin Name: Woo NM Manager
 * Description: Read-only WooCommerce bundle availability dashboard and component low-stock alerts.
 * Version: 0.3.0
 * Author: Jan Jurjec
 * Requires Plugins: woocommerce
 * Text Domain: woo-nm-manager
 */

if (!defined('ABSPATH')) { exit; }

define('WNM_VERSION', '0.3.0');
define('WNM_FILE', __FILE__);

require_once __DIR__.'/includes/class-wnm-bundle-calculator.php';
require_once __DIR__.'/includes/class-wnm-alert-state.php';

register_activation_hook(__FILE__, function(){
    if (!wp_next_scheduled('wnm_reconcile_stock')) {
        wp_schedule_event(time()+300, 'hourly', 'wnm_reconcile_stock');
    }
});
register_deactivation_hook(__FILE__, function(){
    wp_clear_scheduled_hook('wnm_reconcile_stock');
    delete_option('wnm_reconcile_lock');
});

add_action('plugins_loaded', function(){
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function(){
            echo '<div class="notice notice-error"><p>Woo NM Manager requires WooCommerce to be active. Activate WooCommerce first, then return to this page.</p></div>';
        });
        return;
    }

    require_once __DIR__.'/includes/class-wnm-repository.php';
    require_once __DIR__.'/includes/class-wnm-stock-monitor.php';
    require_once __DIR__.'/includes/class-wnm-admin.php';

    $repo = new WNM_Repository();
    $monitor = new WNM_Stock_Monitor($repo, new WNM_Alert_State());
    $admin = new WNM_Admin($repo, new WNM_Bundle_Calculator());

    add_action('admin_menu', [$admin, 'register']);
    add_action('admin_enqueue_scripts', [$admin, 'enqueue']);
    add_action('woocommerce_product_set_stock', [$monitor, 'checkWooProduct']);
    add_action('woocommerce_variation_set_stock', [$monitor, 'checkWooProduct']);
    add_action('wnm_reconcile_stock', [$monitor, 'reconcile']);
});
