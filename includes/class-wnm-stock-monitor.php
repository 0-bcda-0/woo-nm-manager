<?php
if (!defined('ABSPATH')) { exit; }
class WNM_Stock_Monitor {
    private WNM_Repository $repo; private WNM_Alert_State $alertState;
    public function __construct(WNM_Repository $repo, WNM_Alert_State $alertState){$this->repo=$repo;$this->alertState=$alertState;}
    public function checkWooProduct($product): void { if($product && is_a($product,'WC_Product')) $this->checkProductId((int)$product->get_id()); }
    public function checkProductId(int $productId): void {
        $thresholds=$this->repo->getThresholds(); if(!array_key_exists($productId,$thresholds)) return;
        $product=wc_get_product($productId); if(!$product || !$product->managing_stock()) return; $stock=$product->get_stock_quantity(); if($stock===null) return;
        $threshold=max(0,(int)$thresholds[$productId]); $states=$this->repo->getAlertStates(); $previous=isset($states[$productId])?(string)$states[$productId]:null;
        $transition=$this->alertState->transition($previous,(int)$stock,$threshold); $this->repo->setAlertState($productId,$transition['state']);
        if($transition['notify']) $this->sendLowStockEmail($product,(int)$stock,$threshold);
    }
    public function reconcile(): void { foreach($this->repo->trackedProductIds() as $id) $this->checkProductId((int)$id); }
    private function sendLowStockEmail($product,int $stock,int $threshold): void {
        $names=[]; foreach($this->repo->bundlesUsingProduct((int)$product->get_id()) as $id){$b=wc_get_product($id);if($b)$names[]=$b->get_name();}
        $subject=sprintf('[Woo NM Manager] Low stock: %s',$product->get_name());
        $body=implode("\n",[sprintf('%s has fallen below its Woo NM Manager threshold.',$product->get_name()),sprintf('SKU: %s',$product->get_sku()?:'—'),sprintf('Current stock: %d',$stock),sprintf('Threshold: %d',$threshold),sprintf('Used in: %s',$names?implode(', ',$names):'—'),admin_url('admin.php?page=woo-nm-manager')]);
        wp_mail(get_option('admin_email'),$subject,$body);
    }
}
