<?php
$admin = file_get_contents(__DIR__.'/../includes/class-wnm-admin.php');
$main = file_get_contents(__DIR__.'/../woo-nm-manager.php');
$monitor = file_get_contents(__DIR__.'/../includes/class-wnm-stock-monitor.php');

function assertTrue($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

assertTrue(strpos($admin, "'limit'=>-1") === false && strpos($admin, "'limit' => -1") === false, 'admin must not load the entire WooCommerce product catalog');
assertTrue(strpos($admin, 'wc-product-search') !== false, 'admin must use WooCommerce AJAX product search');
assertTrue(strpos($monitor, 'acquireReconcileLock') !== false, 'reconciliation must guard against overlapping runs');
assertTrue(strpos($main, 'Version: 0.1.1') !== false, 'plugin version must be bumped to 0.1.1');

echo "Performance hardening tests passed\n";
