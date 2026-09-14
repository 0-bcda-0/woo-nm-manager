<?php
$repo = file_get_contents(__DIR__.'/../includes/class-wnm-repository.php');
$admin = file_get_contents(__DIR__.'/../includes/class-wnm-admin.php');
$main = file_get_contents(__DIR__.'/../woo-nm-manager.php');
$calc = file_get_contents(__DIR__.'/../includes/class-wnm-bundle-calculator.php');
$sync = file_get_contents(__DIR__.'/../includes/class-wnm-bundle-stock-sync.php');
$uninstall = is_file(__DIR__.'/../uninstall.php') ? file_get_contents(__DIR__.'/../uninstall.php') : '';
function wnmAssert($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }
wnmAssert(strpos($admin, 'Limiting component') !== false, 'dashboard must identify the limiting component');
wnmAssert(strpos($admin, 'Missing / unavailable') !== false, 'dashboard must make missing or unmanaged stock obvious');
wnmAssert(strpos($repo, 'cleanupOrphanedTrackingData') !== false, 'repository must clean thresholds and alert states for products no longer used by bundles');
wnmAssert(strpos($repo, "do_action('wnm_bundle_saved'") !== false, 'saving a bundle must trigger immediate bundle stock sync');
wnmAssert(strpos($main, 'Woo NM Manager requires WooCommerce to be active') !== false, 'inactive WooCommerce notice must be explicit');
wnmAssert(strpos($uninstall, "get_option('wnm_remove_data_on_uninstall'") !== false, 'uninstall cleanup must be opt-in');
wnmAssert(strpos($uninstall, "delete_option('wnm_bundles')") !== false, 'uninstall cleanup must remove bundle option when opted in');
wnmAssert(strpos($uninstall, "delete_option('wnm_bundle_stock_sync_version')") !== false, 'uninstall cleanup must remove bundle sync version option');
wnmAssert(strpos($calc, 'max(0') !== false, 'bundle capacity must never become negative');
wnmAssert(strpos($sync, 'set_manage_stock(true)') !== false, 'mapped bundle products must enable WooCommerce stock management');
wnmAssert(strpos($sync, "'outofstock'") !== false && strpos($sync, "'instock'") !== false, 'bundle stock status must follow calculated capacity');
wnmAssert(strpos($admin, "'limit' => -1") === false && strpos($admin, "'limit'=>-1") === false, 'admin must not regress to full catalog loading');
wnmAssert(strpos($main, 'Version: 0.4.0') !== false, 'plugin version must be 0.4.0');
echo "Production hardening tests passed\n";
