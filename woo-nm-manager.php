<?php
/**
 * Plugin Name: Woo NM Manager
 * Description: WooCommerce bundle availability dashboard, automatic bundle-stock synchronization, and component low-stock alerts.
 * Version: 0.4.0
 * Author: Jan Jurjec
 * Requires Plugins: woocommerce
 * Text Domain: woo-nm-manager
 */

if (!defined('ABSPATH')) { exit; }

define('WNM_VERSION', '0.4.0');
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
    require_once __DIR__.'/includes/class-wnm-bundle-stock-sync.php';
    require_once __DIR__.'/includes/class-wnm-admin.php';

    $repo = new WNM_Repository();
    $calculator = new WNM_Bundle_Calculator();
    $monitor = new WNM_Stock_Monitor($repo, new WNM_Alert_State());
    $bundleStockSync = new WNM_Bundle_Stock_Sync($repo, $calculator);
    $admin = new WNM_Admin($repo, $calculator);

    add_action('admin_menu', [$admin, 'register']);
    add_action('admin_enqueue_scripts', [$admin, 'enqueue']);

    add_action('woocommerce_product_set_stock', [$monitor, 'checkWooProduct']);
    add_action('woocommerce_variation_set_stock', [$monitor, 'checkWooProduct']);

    add_action('woocommerce_product_set_stock', [$bundleStockSync, 'handleComponentStockChange'], 20);
    add_action('woocommerce_variation_set_stock', [$bundleStockSync, 'handleComponentStockChange'], 20);
    add_action('wnm_bundle_saved', [$bundleStockSync, 'syncBundle']);

    add_action('wnm_reconcile_stock', [$monitor, 'reconcile']);
    add_action('wnm_reconcile_stock', [$bundleStockSync, 'syncAll'], 20);

    add_action('admin_init', function() use ($bundleStockSync) {
        if ((string)get_option('wnm_bundle_stock_sync_version', '') === WNM_VERSION) return;
        $bundleStockSync->syncAll();
        update_option('wnm_bundle_stock_sync_version', WNM_VERSION, false);
    });
});
