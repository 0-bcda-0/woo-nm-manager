<?php
if (!defined('ABSPATH') && php_sapi_name() !== 'cli') { exit; }
class WNM_Bundle_Calculator {
    public function possibleKits(array $components): ?int {
        if ($components === []) return null;
        $possible = [];
        foreach ($components as $component) {
            $stock = $component['stock'] ?? null;
            $required = (int)($component['required'] ?? 0);
            if ($stock === null || $required < 1) return null;
            $possible[] = max(0, (int) floor(((int)$stock) / $required));
        }
        return min($possible);
    }
}
