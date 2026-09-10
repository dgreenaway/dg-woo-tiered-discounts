# DG Quantity Discounts

Per-product "buy more, save more" pricing for WooCommerce.

Set quantity tiers on a product, the discount shows up as a small table on the
product page and gets applied automatically once the customer's quantity reaches
it. No settings page, no upsells, nothing to configure globally. Just WooCommerce.

I wrote this because every bulk pricing plugin I looked at either wanted a
subscription or dragged in half a page builder to render a four row table.

## What it does

- Adds a **Quantity discount tiers** repeater to the product's Product data >
  General panel, under the price fields. Min quantity and a percentage. Leave it
  empty and nothing changes for that product.
- Puts a "Buy more, save more" table on the product page between the quantity
  box and the Add to Cart button, and highlights whichever row the current
  quantity falls into.
- Applies the discount in the cart and at checkout, with a
  "Bulk discount: 2.5% off" note on the line item so it doesn't look like a bug.
- Rewrites the line total on any discounted row to show the old price struck
  through, the new price, and what they saved in money rather than just a
  percentage. Follows the shop's inc/ex tax display setting.
- Mini cart, checkout, order emails and the saved order all follow along for
  free, they're reading the same cart and order objects.

## Requirements

- WordPress 5.8+
- WooCommerce 6.0+
- PHP 7.4+

HPOS compatible.

## Installing

Drop the folder in `wp-content/plugins/` and activate it. That's it.

## Data

Tiers live in one post meta key per product, `_dgqd_tiers`, as an array of
`array( 'min_qty' => int, 'percent' => float )` sorted low to high. No options,
no custom tables. Deactivate and prices go straight back to normal with the tier
data still sat there if you turn it back on.

## Things that will bite you if you change it

Most of these cost me an hour each, so they're written down.

- **The hook is `woocommerce_after_add_to_cart_quantity`.** Not
  `woocommerce_before_add_to_cart_button`, which sounds right but fires above
  the quantity box too, so your table ends up in the wrong place and you start
  fighting it with CSS instead of just moving the hook.
- **Always recalculate from `get_regular_price()`.**
  `woocommerce_before_calculate_totals` fires several times in a single request.
  If you adjust whatever price is currently set rather than starting fresh, you
  discount the discount and the totals drift on every refresh.
- **`get_tier_prices()` is the only place the maths happens**, and both the
  charging and the "was / now / save" display go through it. Work it out twice
  in two places and eventually the cart charges one thing while the saving line
  claims another, which is worse than showing no saving at all.
- **The `min()` in there is load bearing.** It stops a 2% bulk tier from
  overriding a product that's already 30% off in a sale. Cheapest price for the
  customer wins.
- **Discount comes off the ex-tax price**, tax is then worked out on the reduced
  amount. Right way round for VAT, and it's what WooCommerce does for its own
  sale prices and coupons anyway.
- **Flex cart forms.** If the theme lays `form.cart` out as a flex row, the block
  needs `flex-basis: 100%` and no `max-width` or it just sits next to the
  quantity box. A flex item's wrap width gets clamped by `max-width`, so setting
  one there kills the basis without any obvious sign it's done so. There's a
  longer note in `assets/css/frontend.css`.

## Styling

Ships deliberately plain so it doesn't clash with whatever theme it lands in.
Override the `dg-qty-tiers*` classes and you're away.

The one thing it doesn't style is the cart line item note, because WooCommerce
renders that as a `dl.variation` and touching that selector restyles every item
meta on the site rather than just this one. Do that in your theme if you want it.

## License

GPL-2.0-or-later
