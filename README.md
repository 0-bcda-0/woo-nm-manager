# Woo NM Manager

Minimal read-only WooCommerce bundle/kit availability dashboard and product-level low-stock notifications.

## V1

- Map an existing WooCommerce product as a bundle.
- Add existing WooCommerce products as components with required quantities.
- Calculate informational `Possible kits` from current component stock.
- Configure one Woo NM Manager threshold per component product.
- Send one email when a product crosses below its threshold; re-arm only after recovery.
- Never mutate WooCommerce stock quantities or stock status.

## Install

Copy the plugin folder to `wp-content/plugins/woo-nm-manager` or zip the folder and upload it in WordPress → Plugins → Add New → Upload Plugin. Activate WooCommerce first, then Woo NM Manager.

Open **Woo NM Manager** in WP Admin.

Low-stock emails are sent to WordPress `admin_email` in V1.

## Tests

```bash
php tests/run.php
```
