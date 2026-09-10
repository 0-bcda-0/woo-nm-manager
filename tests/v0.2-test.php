<?php
$GLOBALS['wnm_test_options'] = [];
function get_option($key, $default = false) { return $GLOBALS['wnm_test_options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['wnm_test_options'][$key] = $value; return true; }
function delete_option($key) { unset($GLOBALS['wnm_test_options'][$key]); return true; }

if (!defined('ABSPATH')) define('ABSPATH', __DIR__);
require_once __DIR__ . '/../includes/class-wnm-repository.php';

function expectSameV02($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $message\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
        exit(1);
    }
}

$repo = new WNM_Repository();
$repo->saveBundle(100, [
    ['product_id' => 10, 'qty' => 2],
    ['product_id' => 20, 'qty' => 1],
]);
$repo->saveBundle(100, [
    ['product_id' => 10, 'qty' => 3],
    ['product_id' => 30, 'qty' => 1],
]);
expectSameV02([
    'bundle_product_id' => 100,
    'components' => [
        ['product_id' => 10, 'qty' => 3],
        ['product_id' => 30, 'qty' => 1],
    ],
], $repo->getBundles()[100], 'editing a bundle replaces its component mapping');

$repo->deleteBundle(100);
expectSameV02([], $repo->getBundles(), 'deleting a bundle removes only its manager mapping');

expectSameV02('fallback@example.test', $repo->getNotificationEmail('fallback@example.test'), 'notification email falls back before custom value is saved');
$repo->saveNotificationEmail('alerts@example.test');
expectSameV02('alerts@example.test', $repo->getNotificationEmail('fallback@example.test'), 'custom notification email is persisted');

echo "V0.2 tests passed\n";
