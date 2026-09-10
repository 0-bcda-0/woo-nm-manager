<?php
if (!defined('ABSPATH')) { exit; }

class WNM_Repository {
    private const BUNDLES = 'wnm_bundles';
    private const THRESHOLDS = 'wnm_thresholds';
    private const ALERT_STATES = 'wnm_alert_states';

    public function getBundles(): array { return (array) get_option(self::BUNDLES, []); }
    public function getThresholds(): array { return (array) get_option(self::THRESHOLDS, []); }
    public function getAlertStates(): array { return (array) get_option(self::ALERT_STATES, []); }

    public function saveBundle(int $bundleProductId, array $components): void {
        $bundles = $this->getBundles();
        $bundles[$bundleProductId] = [
            'bundle_product_id' => $bundleProductId,
            'components' => array_values($components),
        ];
        update_option(self::BUNDLES, $bundles, false);
    }

    public function deleteBundle(int $bundleProductId): void {
        $bundles = $this->getBundles();
        unset($bundles[$bundleProductId]);
        update_option(self::BUNDLES, $bundles, false);
    }

    public function saveThresholds(array $thresholds): void { update_option(self::THRESHOLDS, $thresholds, false); }

    public function setAlertState(int $productId, string $state): void {
        $states = $this->getAlertStates();
        $states[$productId] = $state;
        update_option(self::ALERT_STATES, $states, false);
    }

    public function trackedProductIds(): array {
        $ids = [];
        foreach ($this->getBundles() as $bundle) {
            foreach (($bundle['components'] ?? []) as $component) {
                $id = (int) ($component['product_id'] ?? 0);
                if ($id > 0) $ids[$id] = true;
            }
        }
        return array_keys($ids);
    }

    public function bundlesUsingProduct(int $productId): array {
        $matches = [];
        foreach ($this->getBundles() as $bundle) {
            foreach (($bundle['components'] ?? []) as $component) {
                if ((int)($component['product_id'] ?? 0) === $productId) {
                    $matches[] = (int)$bundle['bundle_product_id'];
                    break;
                }
            }
        }
        return $matches;
    }
}
