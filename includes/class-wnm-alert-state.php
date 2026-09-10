<?php
if (!defined('ABSPATH') && php_sapi_name() !== 'cli') { exit; }

class WNM_Alert_State {
    public function transition(?string $previousState, int $stock, int $threshold): array {
        $newState = $stock < $threshold ? 'low' : 'normal';
        return [
            'state' => $newState,
            'notify' => $newState === 'low' && $previousState !== 'low',
        ];
    }
}
