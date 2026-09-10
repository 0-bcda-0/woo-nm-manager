<?php
if (!defined('ABSPATH')) { exit; }

class WNM_Admin {
    private WNM_Repository $repo;
    private WNM_Bundle_Calculator $calculator;
    private array $productCache = [];

    public function __construct(WNM_Repository $repo, WNM_Bundle_Calculator $calculator) {
        $this->repo = $repo;
        $this->calculator = $calculator;
    }

    public function register(): void {
        add_menu_page('Woo NM Manager','Woo NM Manager','manage_woocommerce','woo-nm-manager',[$this,'render'],'dashicons-products',56);
    }

    public function enqueue(string $hook): void {
        if ($hook !== 'toplevel_page_woo-nm-manager') return;
        wp_enqueue_script('wc-enhanced-select');
        wp_enqueue_style('woocommerce_admin_styles');
    }

    public function render(): void {
        if (!current_user_can('manage_woocommerce')) wp_die('Forbidden');
        $this->handlePost();
        $bundles = $this->repo->getBundles();
        $thresholds = $this->repo->getThresholds();
        $editBundleId = isset($_GET['edit_bundle']) ? absint($_GET['edit_bundle']) : 0;
        $editBundle = $editBundleId && isset($bundles[$editBundleId]) ? $bundles[$editBundleId] : null;

        echo '<div class="wrap"><h1>Woo NM Manager</h1>';
        $this->renderNotice();
        $this->renderEmailSettings();
        $this->renderDataCleanupSettings();
        echo '<h2>Bundles / Kits</h2>';

        if (!$bundles) echo '<p>No bundles configured yet.</p>';
        else {
            echo '<table class="widefat striped"><thead><tr><th>Bundle</th><th>Possible kits</th><th>Components</th><th>Limiting component</th><th>Actions</th></tr></thead><tbody>';
            foreach ($bundles as $bundle) {
                $bundleId = (int)$bundle['bundle_product_id'];
                $bp = $this->product($bundleId);
                $calc = []; $parts = []; $limitingName = '—'; $limitingCapacity = null; $hasUnavailable = false;
                foreach (($bundle['components'] ?? []) as $c) {
                    $p = $this->product((int)$c['product_id']);
                    $stock = ($p && $p->managing_stock()) ? $p->get_stock_quantity() : null;
                    $required = max(1, (int)$c['qty']);
                    $calc[] = ['stock'=>$stock,'required'=>$required];
                    if (!$p || $stock === null) {
                        $hasUnavailable = true;
                        $parts[] = $p ? sprintf('%d × %s (stock: not managed)', $required, $p->get_name()) : 'Missing product';
                        continue;
                    }
                    $capacity = max(0, (int)floor(((int)$stock) / $required));
                    if ($limitingCapacity === null || $capacity < $limitingCapacity) {
                        $limitingCapacity = $capacity;
                        $limitingName = $p->get_name();
                    }
                    $parts[] = sprintf('%d × %s (stock: %s)', $required, $p->get_name(), $stock);
                }
                $possible = $this->calculator->possibleKits($calc);
                $limitingLabel = $hasUnavailable ? 'Missing / unavailable' : $limitingName;
                $editUrl = add_query_arg(['page'=>'woo-nm-manager','edit_bundle'=>$bundleId], admin_url('admin.php'));
                echo '<tr><td><strong>'.esc_html($bp?$bp->get_name():'Missing product').'</strong></td><td>'.esc_html($possible===null?'Unavailable':(string)$possible).'</td><td>'.esc_html(implode(' · ',$parts)).'</td><td>'.esc_html($limitingLabel).'</td><td>';
                echo '<a class="button" href="'.esc_url($editUrl).'">Edit</a> ';
                echo '<form method="post" style="display:inline">'; wp_nonce_field('wnm_delete_bundle');
                echo '<input type="hidden" name="wnm_action" value="delete_bundle"><input type="hidden" name="bundle_product_id" value="'.esc_attr($bundleId).'"><button class="button" onclick="return confirm(\'Remove this bundle mapping from Woo NM Manager?\')">Remove bundle</button></form></td></tr>';
            }
            echo '</tbody></table>';
        }

        $this->renderBundleEditor($editBundle);
        echo '<h2>Tracked products / Alerts</h2>';
        $ids = $this->repo->trackedProductIds();
        if (!$ids) echo '<p>Add bundle components first.</p>';
        else {
            echo '<form method="post">'; wp_nonce_field('wnm_save_thresholds');
            echo '<input type="hidden" name="wnm_action" value="save_thresholds"><table class="widefat striped"><thead><tr><th>Product</th><th>Stock</th><th>Threshold</th><th>Status</th><th>Used in</th></tr></thead><tbody>';
            foreach ($ids as $id) {
                $p=$this->product((int)$id); if(!$p) continue;
                $stock=$p->managing_stock()?$p->get_stock_quantity():null;
                $has=array_key_exists($id,$thresholds); $t=$has?(int)$thresholds[$id]:'';
                $status=(!$has||$stock===null)?'Not monitored':(((int)$stock<$t)?'Low stock':'OK');
                $names=[]; foreach($this->repo->bundlesUsingProduct((int)$id) as $bid){$b=$this->product((int)$bid);if($b)$names[]=$b->get_name();}
                echo '<tr><td>'.esc_html($p->get_name()).'</td><td>'.esc_html($stock===null?'Stock not managed':(string)$stock).'</td><td><input type="number" min="0" name="threshold['.esc_attr($id).']" value="'.esc_attr($t).'" style="width:100px"></td><td>'.esc_html($status).'</td><td>'.esc_html(implode(', ',$names)).'</td></tr>';
            }
            echo '</tbody></table>'; submit_button('Save thresholds'); echo '</form>';
        }
        echo '</div>';

        ob_start(); $this->componentRow(); $template=ob_get_clean();
        echo '<script>(()=>{const b=document.querySelector("#wnm-components tbody"),t='.wp_json_encode($template).';document.getElementById("wnm-add")?.addEventListener("click",()=>{b.insertAdjacentHTML("beforeend",t);if(window.jQuery){jQuery(document.body).trigger("wc-enhanced-select-init");}});b?.addEventListener("click",e=>{if(e.target.classList.contains("wnm-remove")){e.target.closest("tr").remove();}})})();</script>';
    }

    private function renderEmailSettings(): void {
        $email=$this->repo->getNotificationEmail((string)get_option('admin_email'));
        echo '<h2>Email notifications</h2><form method="post" style="max-width:700px">'; wp_nonce_field('wnm_email_settings');
        echo '<p><label for="wnm-notification-email"><strong>Recipient email</strong></label><br><input id="wnm-notification-email" type="email" class="regular-text" name="notification_email" value="'.esc_attr($email).'" required></p>';
        echo '<p><button class="button button-primary" name="wnm_action" value="save_email">Save email</button> <button class="button" name="wnm_action" value="send_test_email">Send test email</button></p>';
        echo '<p class="description">Low-stock alerts and test messages are sent only to this address. If no plugin-specific address is stored, WordPress admin email is used.</p></form>';
    }

    private function renderDataCleanupSettings(): void {
        $enabled = $this->repo->getRemoveDataOnUninstall();
        echo '<h2>Data cleanup</h2><form method="post" style="max-width:700px">';
        wp_nonce_field('wnm_cleanup_settings');
        echo '<input type="hidden" name="wnm_action" value="save_cleanup_settings">';
        echo '<label><input type="checkbox" name="remove_data_on_uninstall" value="1" '.checked($enabled, true, false).'> Delete Woo NM Manager data when the plugin is deleted</label>';
        echo '<p class="description">Disabled by default. Deactivation never removes bundle mappings, thresholds or notification settings.</p>';
        submit_button('Save cleanup setting', 'secondary', 'submit', false);
        echo '</form>';
    }

    private function renderBundleEditor(?array $bundle): void {
        $editing=is_array($bundle); $bundleId=$editing?(int)$bundle['bundle_product_id']:0;
        echo '<h2>'.($editing?'Edit bundle':'Add bundle').'</h2><form method="post">'; wp_nonce_field('wnm_save_bundle');
        echo '<input type="hidden" name="wnm_action" value="save_bundle"><p><label>Bundle product '; $this->productSearchSelect('bundle_product_id','Search for a WooCommerce product…',true,$bundleId);
        echo '</label></p><table class="widefat" id="wnm-components"><thead><tr><th>Component</th><th>Qty / kit</th><th></th></tr></thead><tbody>';
        if($editing&&!empty($bundle['components'])) foreach($bundle['components'] as $component) $this->componentRow((int)$component['product_id'],(int)$component['qty']); else $this->componentRow();
        echo '</tbody></table><p><button type="button" class="button" id="wnm-add">Add component</button></p>'; submit_button($editing?'Save bundle changes':'Save bundle');
        if($editing) echo ' <a class="button" href="'.esc_url(admin_url('admin.php?page=woo-nm-manager')).'">Cancel edit</a>'; echo '</form>';
    }

    private function renderNotice(): void {
        $notice=isset($_GET['wnm_notice'])?sanitize_key(wp_unslash($_GET['wnm_notice'])):'';
        $messages=['bundle_saved'=>['success','Bundle mapping saved.'],'bundle_removed'=>['success','Bundle mapping removed.'],'bundle_invalid'=>['error','Bundle must contain at least one valid component.'],'email_saved'=>['success','Notification email saved.'],'email_invalid'=>['error','Please enter a valid email address.'],'test_sent'=>['success','Test email sent successfully.'],'test_failed'=>['error','WordPress could not send the test email. Check your mail/SMTP configuration.'],'cleanup_saved'=>['success','Data cleanup setting saved.']];
        if(!isset($messages[$notice]))return; [$type,$message]=$messages[$notice]; echo '<div class="notice notice-'.esc_attr($type).' is-dismissible"><p>'.esc_html($message).'</p></div>';
    }

    private function handlePost(): void {
        if($_SERVER['REQUEST_METHOD']!=='POST'||empty($_POST['wnm_action']))return; if(!current_user_can('manage_woocommerce'))wp_die('Forbidden');
        $action=sanitize_key(wp_unslash($_POST['wnm_action']));
        if($action==='save_bundle'){
            check_admin_referer('wnm_save_bundle'); $bundle=absint($_POST['bundle_product_id']??0); $ids=array_map('absint',(array)($_POST['component_product_id']??[])); $qtys=array_map('absint',(array)($_POST['component_qty']??[])); $components=[];$seen=[];
            foreach($ids as $i=>$id){$q=max(1,$qtys[$i]??1);if(!$id||$id===$bundle||isset($seen[$id])||!$this->product($id))continue;$seen[$id]=true;$components[]=['product_id'=>$id,'qty'=>$q];}
            if(!$bundle||!$this->product($bundle)||!$components)$this->redirectWithNotice('bundle_invalid');
            $this->repo->saveBundle($bundle,$components); $this->redirectWithNotice('bundle_saved');
        }
        if($action==='delete_bundle'){check_admin_referer('wnm_delete_bundle');$this->repo->deleteBundle(absint($_POST['bundle_product_id']??0));$this->redirectWithNotice('bundle_removed');}
        if($action==='save_thresholds'){check_admin_referer('wnm_save_thresholds');$thresholds=[];foreach((array)($_POST['threshold']??[]) as $id=>$value)$thresholds[absint($id)]=max(0,absint($value));$this->repo->saveThresholds($thresholds);$this->redirectWithNotice('bundle_saved');}
        if($action==='save_cleanup_settings'){
            check_admin_referer('wnm_cleanup_settings');
            $this->repo->saveRemoveDataOnUninstall(!empty($_POST['remove_data_on_uninstall']));
            $this->redirectWithNotice('cleanup_saved');
        }
        if($action==='save_email'||$action==='send_test_email'){
            check_admin_referer('wnm_email_settings');$email=sanitize_email(wp_unslash($_POST['notification_email']??''));if(!$email||!is_email($email))$this->redirectWithNotice('email_invalid');
            if($action==='save_email'){$this->repo->saveNotificationEmail($email);$this->redirectWithNotice('email_saved');}
            $sent=wp_mail($email,'[Woo NM Manager] Test email',"Woo NM Manager email notifications are working.\n\nThis is a test message from your WordPress site.");$this->redirectWithNotice($sent?'test_sent':'test_failed');
        }
    }

    private function redirectWithNotice(string $notice): void {$url=add_query_arg(['page'=>'woo-nm-manager','wnm_notice'=>$notice],admin_url('admin.php'));wp_safe_redirect($url);exit;}
    private function product(int $id){if($id<1)return false;if(!array_key_exists($id,$this->productCache))$this->productCache[$id]=wc_get_product($id);return $this->productCache[$id];}
    private function productSearchSelect(string $name,string $placeholder,bool $required=false,int $selectedId=0): void {
        echo '<select class="wc-product-search" style="width:350px" name="'.esc_attr($name).'" data-placeholder="'.esc_attr($placeholder).'" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true"'.($required?' required':'').'>';
        if($selectedId>0){$product=$this->product($selectedId);if($product){$label=$product->get_name().($product->get_sku()?' ['.$product->get_sku().']':'');echo '<option value="'.esc_attr($selectedId).'" selected>'.esc_html($label).'</option>';}}
        echo '</select>';
    }
    private function componentRow(int $selectedId=0,int $qty=1): void {echo '<tr><td>';$this->productSearchSelect('component_product_id[]','Search for a component…',true,$selectedId);echo '</td><td><input type="number" min="1" value="'.esc_attr(max(1,$qty)).'" name="component_qty[]" required></td><td><button type="button" class="button wnm-remove">Remove</button></td></tr>';}
}
