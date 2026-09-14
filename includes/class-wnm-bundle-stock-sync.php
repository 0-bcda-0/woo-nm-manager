<?php
if (!defined('ABSPATH') && php_sapi_name() !== 'cli') { exit; }

class WNM_Bundle_Stock_Sync {
    private WNM_Repository $repo;
    private WNM_Bundle_Calculator $calculator;
    private array $syncingBundles = [];

    public function __construct(WNM_Repository $repo, WNM_Bundle_Calculator $calculator) {
        $this->repo = $repo;
        $this->calculator = $calculator;
    }

    public function handleComponentStockChange($product): void {
        if (!$product || !is_a($product, 'WC_Product')) return;

        foreach ($this->repo->bundlesUsingProduct((int)$product->get_id()) as $bundleId) {
            $this->syncBundle((int)$bundleId);
        }
    }

    public function syncAll(): void {
        foreach ($this->repo->getBundles() as $bundle) {
            $this->syncBundle((int)($bundle['bundle_product_id'] ?? 0));
        }
    }

    public function syncBundle(int $bundleProductId): void {
        if ($bundleProductId < 1 || isset($this->syncingBundles[$bundleProductId])) return;

        $bundles = $this->repo->getBundles();
        $bundle = $bundles[$bundleProductId] ?? null;
        if (!is_array($bundle)) return;

        $bundleProduct = wc_get_product($bundleProductId);
        if (!$bundleProduct || !is_a($bundleProduct, 'WC_Product')) return;

        $this->syncingBundles[$bundleProductId] = true;
        try {
            $components = [];
            foreach (($bundle['components'] ?? []) as $component) {
                $componentProduct = wc_get_product((int)($component['product_id'] ?? 0));
                $required = max(1, (int)($component['qty'] ?? 1));
                $stock = ($componentProduct && $componentProduct->managing_stock())
                    ? $componentProduct->get_stock_quantity()
                    : null;
                $components[] = ['stock' => $stock, 'required' => $required];
            }

            $possible = $this->calculator->possibleKits($components);
            $targetStock = $possible === null ? 0 : max(0, (int)$possible);
            $targetStatus = $targetStock > 0 ? 'instock' : 'outofstock';

            if (!$bundleProduct->managing_stock()) {
                $bundleProduct->set_manage_stock(true);
                $bundleProduct->save();
            }

            $currentStock = $bundleProduct->get_stock_quantity();
            if ($currentStock === null || (int)$currentStock !== $targetStock) {
                wc_update_product_stock($bundleProduct, $targetStock, 'set');
            }

            if ((string)$bundleProduct->get_stock_status() !== $targetStatus) {
                wc_update_product_stock_status($bundleProductId, $targetStatus);
            }
        } finally {
            unset($this->syncingBundles[$bundleProductId]);
        }
    }
}
