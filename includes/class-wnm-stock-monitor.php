<?php
if (!defined('ABSPATH')) { exit; }

class WNM_Stock_Monitor {
    private const RECONCILE_LOCK = 'wnm_reconcile_lock';
    private const RECONCILE_LOCK_TTL = 600;

    private WNM_Repository $repo;
    private WNM_Alert_State $alertState;

    public function __construct(WNM_Repository $repo, WNM_Alert_State $alertState) {
        $this->repo = $repo;
        $this->alertState = $alertState;
    }

    public function checkWooProduct($product): void {
        if ($product && is_a($product, 'WC_Product')) {
            $this->checkProductId((int)$product->get_id());
        }
    }

    public function checkProductId(int $productId): void {
        $thresholds = $this->repo->getThresholds();
        if (!array_key_exists($productId, $thresholds)) return;

        $product = wc_get_product($productId);
        if (!$product || !$product->managing_stock()) return;

        $stock = $product->get_stock_quantity();
        if ($stock === null) return;

        $threshold = max(0, (int)$thresholds[$productId]);
        $states = $this->repo->getAlertStates();
        $previous = isset($states[$productId]) ? (string)$states[$productId] : null;
        $transition = $this->alertState->transition($previous, (int)$stock, $threshold);

        if ($previous !== $transition['state']) {
            $this->repo->setAlertState($productId, $transition['state']);
        }

        if ($transition['notify']) {
            $this->sendLowStockEmail($product, (int)$stock, $threshold);
        }
    }

    public function reconcile(): void {
        if (!$this->acquireReconcileLock()) return;

        try {
            foreach ($this->repo->trackedProductIds() as $id) {
                $this->checkProductId((int)$id);
            }
        } finally {
            $this->releaseReconcileLock();
        }
    }

    private function acquireReconcileLock(): bool {
        $now = time();
        if (add_option(self::RECONCILE_LOCK, $now, '', false)) return true;

        $lockedAt = (int)get_option(self::RECONCILE_LOCK, 0);
        if ($lockedAt > 0 && $lockedAt < ($now - self::RECONCILE_LOCK_TTL)) {
            delete_option(self::RECONCILE_LOCK);
            return add_option(self::RECONCILE_LOCK, $now, '', false);
        }

        return false;
    }

    private function releaseReconcileLock(): void {
        delete_option(self::RECONCILE_LOCK);
    }

    private function sendLowStockEmail($product, int $stock, int $threshold): void {
        $names = [];
        foreach ($this->repo->bundlesUsingProduct((int)$product->get_id()) as $id) {
            $b = wc_get_product($id);
            if ($b) $names[] = $b->get_name();
        }

        $subject = sprintf('[Woo NM Manager] Low stock: %s', $product->get_name());
        $body = implode("\n", [
            sprintf('%s has fallen below its Woo NM Manager threshold.', $product->get_name()),
            sprintf('SKU: %s', $product->get_sku() ?: '—'),
            sprintf('Current stock: %d', $stock),
            sprintf('Threshold: %d', $threshold),
            sprintf('Used in: %s', $names ? implode(', ', $names) : '—'),
            admin_url('admin.php?page=woo-nm-manager'),
        ]);

        wp_mail(get_option('admin_email'), $subject, $body);
    }
}
