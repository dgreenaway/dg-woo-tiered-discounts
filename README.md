# DG Quantity Discounts

Per-product "Buy more, save more" quantity discount tiers for WooCommerce.

Set quantity tiers on any product; the discount shows as a table on the product
page and applies automatically in the cart and at checkout. No settings page, no
page builder, no dependencies beyond WooCommerce.

## What it does

- Adds a **Quantity discount tiers** repeater to each product's Product data →
  General/Pricing panel (min quantity + discount %). Leave it empty and the
  product behaves normally.
- Renders a "Buy more, save more" table on the product page, between the
  quantity input and the Add to Cart button, with the row matching the
  currently-entered quantity highlighted.
- Applies the discounted price in the cart and at checkout, and adds a
  "Bulk discount: X% off" note to the line item.
- Mini-cart, checkout, order emails and the stored order all pick the price up
  automatically, since they read the same cart/order objects.

## Requirements

- WordPress 5.8+
- WooCommerce 6.0+
- PHP 7.4+

Compatible with High-Performance Order Storage (HPOS).

## Installing

1. Copy the plugin folder into `wp-content/plugins/`.
2. Activate it from Plugins.

## Data

Tiers are stored per product in the post meta key `_dgqd_tiers`, as an array of
`array( 'min_qty' => int, 'percent' => float )`, sorted ascending. Nothing else
is written — no options, no custom tables — so deactivating the plugin leaves
prices back at their normal values with the tier data intact.

## Notes / gotchas

Non-obvious things worth knowing before changing anything:

- **`woocommerce_after_add_to_cart_quantity`, not
  `woocommerce_before_add_to_cart_button`.** The latter fires *before* the
  quantity input as well, not between quantity and the button.
- **Price is recomputed from `get_regular_price()` on every pass** of
  `woocommerce_before_calculate_totals`, never adjusted in place. That hook can
  fire multiple times per request, and adjusting an already-adjusted price
  compounds the discount.
- **`min( $discounted, $base_price )`** stops a small bulk percentage from
  overriding a bigger sale price already set on the product.
- **The discount applies pre-tax**, and tax is then calculated on the reduced
  amount. That is the correct order of operations for VAT, and matches how
  WooCommerce's own sale prices and coupons behave.
- **Flex cart forms**: if the active theme lays out `form.cart` as a flex row,
  the block needs `flex-basis: 100%` and *no* `max-width` to drop onto its own
  line — a flex item's wrap size is clamped by `max-width`, which silently
  defeats it. See the theme compatibility section in
  `assets/css/frontend.css`.

## Styling

The plugin ships neutral defaults for its own markup. Theme-specific
presentation - mini-cart and checkout item-meta styling in particular - is
intentionally left alone, since restyling `dl.variation` would affect every
item-meta on the site rather than just this one. Override the `dg-qty-tiers*`
classes in your theme to restyle.

## License

GPL-2.0-or-later
