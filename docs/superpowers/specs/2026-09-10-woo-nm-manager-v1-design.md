# Woo NM Manager V1 Design

## Goal

Build a minimal WordPress/WooCommerce plugin that provides a read-only dashboard for bundle/kit availability and product-level low-stock notifications.

The plugin must never change WooCommerce stock quantities, order data, or stock status. WooCommerce remains the source of truth for product stock and bundle in-stock/out-of-stock state.

## Scope

V1 includes only:

1. Manual mapping of an existing WooCommerce product as a bundle/kit.
2. Manual assignment of component products and required quantities per bundle.
3. Informational calculation of how many complete bundles can currently be assembled from component stock.
4. A global low-stock threshold per component product, configured inside Woo NM Manager.
5. Email notification when a tracked product crosses from at/above its threshold to below it.
6. Dashboard views for bundles and tracked products.

V1 explicitly excludes:

- modifying WooCommerce stock;
- modifying WooCommerce product stock status;
- decrementing component stock when a bundle is sold;
- order processing logic;
- automatic bundle availability enforcement;
- purchasing, forecasting, analytics, supplier management, or warehouse write-back;
- per-bundle stock thresholds.

## Core Domain Model

### Bundle

A bundle is an existing WooCommerce product selected by an administrator. It has its own WooCommerce product ID and SKU.

Woo NM Manager stores only the bundle definition: which WooCommerce products are components and how many units of each are required for one complete bundle.

Example:

**Nishman Barber Starter Pack**

- 2 × Wax
- 1 × Shampoo
- 1 × Sea Salt Spray

The plugin does not create or maintain physical stock for the bundle itself.

### Component Product

A component is an existing WooCommerce product whose current stock quantity is read from WooCommerce.

A product may be used in any number of bundles.

### Product Threshold

Each tracked component product has one global low-stock threshold stored by Woo NM Manager.

Example:

- Shampoo threshold: 5

That threshold applies regardless of how many bundles use Shampoo. There is no bundle-specific threshold.

## Bundle Availability Calculation

For each component:

`possible_from_component = floor(current_stock / required_quantity)`

The informational bundle availability is:

`possible_bundles = min(possible_from_component for all components)`

Example:

- Wax stock: 20, required: 2 → 10 possible
- Shampoo stock: 8, required: 1 → 8 possible
- Sea Salt Spray stock: 12, required: 1 → 12 possible

Result:

`possible_bundles = 8`

This number is display-only.

If the calculation reaches zero, Woo NM Manager may display that the bundle cannot currently be assembled and identify limiting/missing components, but it must not change the WooCommerce bundle product's stock status.

## Read-Only Boundary

Woo NM Manager must never call WooCommerce APIs that mutate inventory or stock status as part of its bundle/monitoring features.

Specifically, V1 must not:

- call `wc_update_product_stock()`;
- call product `set_stock_quantity()`;
- call product `set_stock_status()`;
- write stock meta directly;
- react to bundle sales by changing component stock;
- alter orders.

The plugin may write only its own configuration/state data, such as bundle definitions, thresholds, recipient settings, and notification state.

## Low-Stock Notification Rules

A notification is triggered by a product, not by a bundle.

For a product with threshold `T` and current WooCommerce stock `S`:

- `S >= T`: normal state;
- `S < T`: low-stock state.

An email is sent only when the product transitions from normal state to low-stock state.

Example:

1. Shampoo stock = 8, threshold = 5 → normal.
2. Shampoo stock changes to 4 → send one email.
3. Shampoo remains at 4 or changes to 3 → do not send repeated emails.
4. Shampoo later returns to 6 → re-arm the alert.
5. Shampoo later falls to 4 again → send a new email.

This transition-based state prevents notification spam.

### Email Content

The email should identify:

- product name;
- SKU when available;
- current stock;
- configured threshold;
- bundle(s) in which the product is used, as context only;
- a link to the Woo NM Manager dashboard when practical.

Example subject:

`[Woo NM Manager] Low stock: Shampoo`

Example body meaning:

`Shampoo has fallen below its configured Woo NM Manager threshold. Current stock: 4. Threshold: 5. Used in: Nishman Barber Starter Pack.`

## Detection Strategy

The plugin must support stock changes that originate outside normal WooCommerce orders because warehouse/inventory synchronization can update WooCommerce stock independently.

Therefore V1 should not depend only on order-completed hooks.

Recommended approach:

1. Listen to relevant WooCommerce product stock-change hooks when available for fast detection.
2. Add a lightweight scheduled reconciliation task as a safety net so external synchronization mechanisms that bypass expected hooks are still detected.
3. On each check, compare current WooCommerce stock with the configured threshold and previously stored low-stock state.

The scheduled task remains read-only toward WooCommerce.

## Admin UX

Woo NM Manager should add one top-level or WooCommerce submenu admin page with two primary sections.

### Bundles

Show each configured bundle with:

- bundle WooCommerce product name;
- bundle SKU;
- current WooCommerce stock status for reference only;
- calculated `Possible kits` value;
- list of components;
- each component's required quantity;
- current component stock;
- limiting component indication when applicable.

Administrators can:

- add a bundle definition;
- select an existing WooCommerce product as the bundle product;
- search/select existing WooCommerce products as components;
- define positive integer quantities;
- edit or remove bundle definitions.

Deleting a bundle definition removes only Woo NM Manager metadata/configuration and never deletes the WooCommerce product.

### Tracked Products

Show one row per component product used by at least one bundle, with:

- product name;
- SKU;
- current WooCommerce stock;
- Woo NM Manager threshold;
- state: OK or Low stock;
- bundles using the product.

Administrators can edit the product's global threshold here.

A product appearing in multiple bundles must still appear once in this list and have exactly one threshold.

## Configuration Storage

V1 should keep plugin-owned data isolated from WooCommerce inventory data.

Recommended storage:

- bundle definitions in a dedicated WordPress option or compact custom table;
- product thresholds keyed by WooCommerce product ID;
- notification armed/low state keyed by product ID;
- notification recipient configuration in plugin settings.

For the expected V1 scale, WordPress options are preferred unless implementation constraints reveal a concrete reason for custom tables. This keeps the plugin minimal and avoids unnecessary schema management.

## Product Compatibility

V1 should operate on WooCommerce products that expose a numeric managed stock quantity.

If a selected product does not manage stock or its quantity is unavailable, the dashboard must show a clear non-numeric state such as `Stock not managed` rather than inventing a quantity.

Such a product cannot contribute a reliable numeric value to `Possible kits`; the bundle should therefore show availability as unavailable/incomplete until all required components expose usable numeric stock.

## Validation

Bundle configuration must reject:

- a bundle with no components;
- component quantity less than 1;
- duplicate component rows in the same bundle;
- using the bundle product itself as one of its own components;
- references to deleted/nonexistent WooCommerce products.

Thresholds must be integers greater than or equal to 0.

A threshold of 0 means the notification triggers only if the stock becomes negative. If this behavior is considered undesirable during implementation, the UI may instead require threshold >= 1, but the chosen rule must be explicit and tested before release.

## Permissions and Security

Only administrators or users with an appropriate WooCommerce management capability should be able to configure Woo NM Manager.

All admin writes must use WordPress capability checks and nonces.

All displayed product/bundle data must be escaped according to WordPress standards.

## Error Handling

If WooCommerce is not active, Woo NM Manager should fail gracefully and show an admin notice rather than causing a fatal error.

If a configured WooCommerce product is later deleted, the dashboard should mark the reference as missing and allow the administrator to repair or remove the mapping.

Notification failures must not affect WooCommerce inventory synchronization or storefront behavior.

## Testing Requirements

V1 must have automated tests around the core business rules, especially:

- bundle availability calculation;
- multiple quantities per component;
- limiting component selection;
- one global threshold shared across bundles;
- normal → low transition sends exactly one alert;
- repeated checks while low do not resend;
- low → normal re-arms notification;
- normal → low after re-arm sends again;
- missing/unmanaged stock behavior;
- validation preventing self-reference and duplicate components;
- proof that calculation/notification code does not mutate WooCommerce inventory.

Manual acceptance testing must confirm the dashboard against real WooCommerce products in a development WordPress installation.

## V1 Acceptance Criteria

V1 is complete when an administrator can:

1. Install and activate Woo NM Manager alongside WooCommerce.
2. Create a bundle mapping using existing WooCommerce products.
3. Define component quantities such as 2 × Wax, 1 × Shampoo, 1 × Spray.
4. See the current WooCommerce stock of each component.
5. See the correctly calculated number of possible complete kits.
6. Define one global Woo NM Manager threshold for each component product.
7. Receive one email when a product crosses below its threshold.
8. Avoid repeated email spam while stock remains below threshold.
9. Receive a new alert after stock recovers and later crosses below the threshold again.
10. See which bundles depend on a low-stock product.
11. Confirm that Woo NM Manager never changes component stock or bundle stock status.
12. Manually control the real WooCommerce bundle product's in-stock/out-of-stock state independently of Woo NM Manager.

## Architectural Principle

WooCommerce owns inventory. Woo NM Manager observes inventory.

The plugin's job is to answer two questions only:

1. `Given current component stock, how many of each configured kit could we assemble right now?`
2. `Which component products have crossed below the stock level we want to be warned about?`

Anything that writes inventory, automates purchasing, or changes storefront availability is outside V1.