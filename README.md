# Woo NM Manager

Lightweight WooCommerce bundle/kit stock manager and component low-stock notifier.

## Current behavior

- Map an existing WooCommerce product as a bundle.
- Add existing WooCommerce products as components with required quantities.
- Calculate `Possible kits` from current component stock.
- Automatically keep the bundle product's WooCommerce stock quantity equal to that calculated capacity.
- Automatically set the bundle `instock` / `outofstock` status from the calculated capacity.
- Enable WooCommerce `Manage stock` for mapped bundle products when needed.
- Recalculate only affected bundles when component stock changes, plus hourly reconciliation as a fallback.
- Skip unnecessary writes when bundle quantity/status already matches the calculated value.
- If a component is missing or does not have managed numeric stock, fail safe by setting bundle stock to `0`.
- Never modify component product stock. Component stock remains the source of truth.
- Configure one low-stock threshold per component product and send notification emails on threshold crossings.

## External inventory assumption

When a bundle is sold, the external warehouse/inventory system must reduce the individual component stocks in WooCommerce. Woo NM Manager then reads those updated component quantities and recalculates the bundle quantity.

## Install

Upload the plugin ZIP in WordPress → Plugins → Add New → Upload Plugin. Activate WooCommerce first, then Woo NM Manager.

Open **Woo NM Manager** in WP Admin.

## Performance

Product selection uses WooCommerce's existing AJAX product search. Bundle stock synchronization is event-driven and targets only bundle mappings that use the changed component. The existing hourly reconciliation remains a safety net for integrations that update stock without firing WooCommerce stock hooks.
