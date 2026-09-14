<?php
if (!defined('ABSPATH')) define('ABSPATH', __DIR__.'/');

require_once __DIR__.'/../includes/class-wnm-bundle-calculator.php';
require_once __DIR__.'/../includes/class-wnm-repository.php';
require_once __DIR__.'/../includes/class-wnm-bundle-stock-sync.php';

function wnmStockAssertSame($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
        exit(1);
    }
}

class WC_Product {
    private int $id;
    private bool $manageStock;
    private ?int $stock;
    private string $status;
    public int $saveCalls = 0;

    public function __construct(int $id, bool $manageStock, ?int $stock, string $status = 'instock') {
        $this->id = $id;
        $this->manageStock = $manageStock;
        $this->stock = $stock;
        $this->status = $status;
    }
    public function get_id(): int { return $this->id; }
    public function managing_stock(): bool { return $this->manageStock; }
    public function set_manage_stock($enabled): void { $this->manageStock = (bool)$enabled; }
    public function get_stock_quantity(): ?int { return $this->stock; }
    public function set_stock_quantity_for_test(?int $stock): void { $this->stock = $stock; }
    public function get_stock_status(): string { return $this->status; }
    public function set_stock_status_for_test(string $status): void { $this->status = $status; }
    public function save(): void { $this->saveCalls++; }
}

$GLOBALS['wnm_products'] = [];
$GLOBALS['wnm_stock_writes'] = [];
$GLOBALS['wnm_status_writes'] = [];
function wc_get_product($id) { return $GLOBALS['wnm_products'][(int)$id] ?? false; }
function wc_update_product_stock($product, $qty, $operation = 'set', $updating = false) {
    $GLOBALS['wnm_stock_writes'][] = [$product->get_id(), (int)$qty, $operation];
    $product->set_stock_quantity_for_test((int)$qty);
    return (int)$qty;
}
function wc_update_product_stock_status($id, $status) {
    $GLOBALS['wnm_status_writes'][] = [(int)$id, $status];
    $product = wc_get_product((int)$id);
    if ($product) $product->set_stock_status_for_test($status);
}

class FakeBundleRepo extends WNM_Repository {
    public array $bundles = [];
    public function getBundles(): array { return $this->bundles; }
    public function bundlesUsingProduct(int $productId): array {
        $ids = [];
        foreach ($this->bundles as $bundle) {
            foreach (($bundle['components'] ?? []) as $component) {
                if ((int)$component['product_id'] === $productId) {
                    $ids[] = (int)$bundle['bundle_product_id'];
                    break;
                }
            }
        }
        return $ids;
    }
}

$repo = new FakeBundleRepo();
$repo->bundles = [100 => [
    'bundle_product_id' => 100,
    'components' => [
        ['product_id' => 1, 'qty' => 2],
        ['product_id' => 2, 'qty' => 1],
    ],
]];
$GLOBALS['wnm_products'] = [
    1 => new WC_Product(1, true, 10),
    2 => new WC_Product(2, true, 8),
    100 => new WC_Product(100, false, 99, 'instock'),
];

$sync = new WNM_Bundle_Stock_Sync($repo, new WNM_Bundle_Calculator());
$sync->syncBundle(100);
wnmStockAssertSame(5, $GLOBALS['wnm_products'][100]->get_stock_quantity(), 'bundle stock equals calculated capacity');
wnmStockAssertSame(true, $GLOBALS['wnm_products'][100]->managing_stock(), 'bundle stock management is enabled');
wnmStockAssertSame([[100, 5, 'set']], $GLOBALS['wnm_stock_writes'], 'stock is written once using set operation');

$GLOBALS['wnm_stock_writes'] = [];
$GLOBALS['wnm_status_writes'] = [];
$sync->syncBundle(100);
wnmStockAssertSame([], $GLOBALS['wnm_stock_writes'], 'unchanged bundle quantity is not rewritten');
wnmStockAssertSame([], $GLOBALS['wnm_status_writes'], 'unchanged bundle status is not rewritten');

$GLOBALS['wnm_products'][1]->set_stock_quantity_for_test(0);
$sync->handleComponentStockChange($GLOBALS['wnm_products'][1]);
wnmStockAssertSame(0, $GLOBALS['wnm_products'][100]->get_stock_quantity(), 'component stock change recalculates affected bundle');
wnmStockAssertSame('outofstock', $GLOBALS['wnm_products'][100]->get_stock_status(), 'zero capacity marks bundle out of stock');

$GLOBALS['wnm_products'][1]->set_stock_quantity_for_test(6);
$sync->handleComponentStockChange($GLOBALS['wnm_products'][1]);
wnmStockAssertSame(3, $GLOBALS['wnm_products'][100]->get_stock_quantity(), 'bundle capacity recovers when component stock increases');
wnmStockAssertSame('instock', $GLOBALS['wnm_products'][100]->get_stock_status(), 'positive capacity marks bundle in stock');

$GLOBALS['wnm_products'][2] = new WC_Product(2, false, null);
$sync->syncBundle(100);
wnmStockAssertSame(0, $GLOBALS['wnm_products'][100]->get_stock_quantity(), 'unavailable component stock fails safe to zero bundle stock');

echo "Bundle stock sync tests passed\n";
