<?php
if (!defined('ABSPATH')) { exit; }

class WNM_Repository {
    private const BUNDLES = 'wnm_bundles';
    private const THRESHOLDS = 'wnm_thresholds';
    private const ALERT_STATES = 'wnm_alert_states';
    private const NOTIFICATION_EMAIL = 'wnm_notification_email';
    private const REMOVE_DATA_ON_UNINSTALL = 'wnm_remove_data_on_uninstall';

    public function getBundles(): array { return (array) get_option(self::BUNDLES, []); }
    public function getThresholds(): array { return (array) get_option(self::THRESHOLDS, []); }
    public function getAlertStates(): array { return (array) get_option(self::ALERT_STATES, []); }

    public function getNotificationEmail(string $fallback): string {
        $email = (string) get_option(self::NOTIFICATION_EMAIL, '');
        return $email !== '' ? $email : $fallback;
    }

    public function saveNotificationEmail(string $email): void {
        update_option(self::NOTIFICATION_EMAIL, $email, false);
    }

    public function getRemoveDataOnUninstall(): bool {
        return (bool) get_option(self::REMOVE_DATA_ON_UNINSTALL, false);
    }

    public function saveRemoveDataOnUninstall(bool $enabled): void {
        update_option(self::REMOVE_DATA_ON_UNINSTALL, $enabled ? 1 : 0, false);
    }

    public function saveBundle(int $bundleProductId, array $components): void {
        $bundles = $this->getBundles();
        $bundles[$bundleProductId] = [
            'bundle_product_id' => $bundleProductId,
            'components' => array_values($components),
        ];
        update_option(self::BUNDLES, $bundles, false);
        $this->cleanupOrphanedTrackingData();
    }

    public function deleteBundle(int $bundleProductId): void {
        $bundles = $this->getBundles();
        unset($bundles[$bundleProductId]);
        update_option(self::BUNDLES, $bundles, false);
        $this->cleanupOrphanedTrackingData();
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

    public function cleanupOrphanedTrackingData(): void {
        $tracked = array_fill_keys(array_map('intval', $this->trackedProductIds()), true);

        $thresholds = $this->getThresholds();
        $cleanThresholds = array_filter(
            $thresholds,
            static fn($value, $id) => isset($tracked[(int)$id]),
            ARRAY_FILTER_USE_BOTH
        );
        if ($cleanThresholds !== $thresholds) {
            update_option(self::THRESHOLDS, $cleanThresholds, false);
        }

        $states = $this->getAlertStates();
        $cleanStates = array_filter(
            $states,
            static fn($value, $id) => isset($tracked[(int)$id]),
            ARRAY_FILTER_USE_BOTH
        );
        if ($cleanStates !== $states) {
            update_option(self::ALERT_STATES, $cleanStates, false);
        }
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
