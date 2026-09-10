<?php
/**
 * Plugin Name:       DG Quantity Discounts
 * Description:       Per-product "Buy more, save more" quantity discount tiers for WooCommerce. Tiers are set on each product, shown as a table on the product page, and applied automatically in the cart and at checkout.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Daniel Greenaway
 * License:           GPL-2.0-or-later
 * Text Domain:       dg-quantity-discounts
 *
 * WC requires at least: 6.0
 */

defined( 'ABSPATH' ) || exit;

define( 'DGQD_VERSION', '1.0.0' );
define( 'DGQD_FILE', __FILE__ );
define( 'DGQD_PATH', plugin_dir_path( __FILE__ ) );
define( 'DGQD_URL', plugin_dir_url( __FILE__ ) );

/**
 * Post meta key holding a product's tiers.
 *
 * Value is an array of array( 'min_qty' => int, 'percent' => float ), sorted ascending.
 */
define( 'DGQD_META_KEY', '_dgqd_tiers' );

final class DGQD_Plugin {

	public static function init() {
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_hpos_compatibility' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'bootstrap' ) );
	}

	/**
	 * This plugin only reads/writes product post meta and uses the cart API, so it is
	 * compatible with High-Performance Order Storage.
	 */
	public static function declare_hpos_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DGQD_FILE, true );
		}
	}

	public static function bootstrap() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice_requires_woocommerce' ) );
			return;
		}

		self::add_hooks();
	}

	public static function notice_requires_woocommerce() {
		echo '<div class="notice notice-error"><p>';
		esc_html_e( 'DG Quantity Discounts requires WooCommerce to be installed and active.', 'dg-quantity-discounts' );
		echo '</p></div>';
	}

	private static function add_hooks() {
		// Admin: tier repeater in the product Pricing panel
		add_action( 'woocommerce_product_options_pricing', array( __CLASS__, 'render_admin_field' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_tiers' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

		// Front end: the "Buy more, save more" table
		add_action( 'woocommerce_after_add_to_cart_quantity', array( __CLASS__, 'render_product_table' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );

		// Pricing + cart display
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_tier_pricing' ) );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'cart_item_data' ), 10, 2 );
	}

	/* ---------------------------------------------------------------------
	 * Data helpers
	 * ------------------------------------------------------------------ */

	public static function get_tiers( $product_id ) {
		$tiers = get_post_meta( $product_id, DGQD_META_KEY, true );
		return is_array( $tiers ) ? $tiers : array();
	}

	/**
	 * Highest tier the given quantity qualifies for, or null.
	 *
	 * Tiers are stored ascending, so the last match wins.
	 */
	public static function get_tier_for_quantity( $tiers, $quantity ) {
		$applicable = null;
		foreach ( $tiers as $tier ) {
			if ( $quantity >= $tier['min_qty'] ) {
				$applicable = $tier;
			}
		}
		return $applicable;
	}

	/** 2.50 => "2.5", 10.00 => "10" */
	public static function format_percent( $percent ) {
		return rtrim( rtrim( number_format( (float) $percent, 2 ), '0' ), '.' );
	}

	/* ---------------------------------------------------------------------
	 * Admin
	 * ------------------------------------------------------------------ */

	public static function enqueue_admin_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'dgqd-admin',
			DGQD_URL . 'assets/css/admin.css',
			array(),
			self::asset_version( 'assets/css/admin.css' )
		);
		wp_enqueue_script(
			'dgqd-admin',
			DGQD_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			self::asset_version( 'assets/js/admin.js' ),
			true
		);
	}

	public static function render_admin_field() {
		global $post;
		$tiers = self::get_tiers( $post->ID );
		?>
		<div class="options_group dg-qty-tiers-field">
			<p class="form-field">
				<label><?php esc_html_e( 'Quantity discount tiers', 'dg-quantity-discounts' ); ?></label>
				<span class="description">
					<?php esc_html_e( 'Shown as a "Buy more, save more" table on the product page and applied automatically in the cart. Leave empty for no quantity discounts.', 'dg-quantity-discounts' ); ?>
				</span>
			</p>
			<table id="dg-qty-tiers-table" class="dg-qty-tiers-admin-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Min. quantity', 'dg-quantity-discounts' ); ?></th>
						<th><?php esc_html_e( 'Discount %', 'dg-quantity-discounts' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $tiers ) ) : ?>
					<tr>
						<td><input type="number" min="2" step="1" name="dgqd_tier_min_qty[]" value=""></td>
						<td><input type="number" min="0" step="0.01" name="dgqd_tier_discount[]" value=""></td>
						<td class="dg-tier-remove"><a href="#" class="dg-remove-tier-row" title="<?php esc_attr_e( 'Remove tier', 'dg-quantity-discounts' ); ?>">&times;</a></td>
					</tr>
					<?php else : foreach ( $tiers as $tier ) : ?>
					<tr>
						<td><input type="number" min="2" step="1" name="dgqd_tier_min_qty[]" value="<?php echo esc_attr( $tier['min_qty'] ); ?>"></td>
						<td><input type="number" min="0" step="0.01" name="dgqd_tier_discount[]" value="<?php echo esc_attr( $tier['percent'] ); ?>"></td>
						<td class="dg-tier-remove"><a href="#" class="dg-remove-tier-row" title="<?php esc_attr_e( 'Remove tier', 'dg-quantity-discounts' ); ?>">&times;</a></td>
					</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>
			<p class="dg-qty-tiers-add">
				<a href="#" id="dg-add-tier-row" class="button"><?php esc_html_e( '+ Add tier', 'dg-quantity-discounts' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Nonce and capability are already verified by WC_Admin_Meta_Boxes::save_meta_boxes()
	 * before woocommerce_process_product_meta fires.
	 */
	public static function save_tiers( $post_id ) {
		$mins  = isset( $_POST['dgqd_tier_min_qty'] ) ? wp_unslash( $_POST['dgqd_tier_min_qty'] ) : array();
		$pcts  = isset( $_POST['dgqd_tier_discount'] ) ? wp_unslash( $_POST['dgqd_tier_discount'] ) : array();
		$tiers = array();

		foreach ( (array) $mins as $i => $min_qty ) {
			$min_qty = absint( $min_qty );
			$percent = isset( $pcts[ $i ] ) ? floatval( $pcts[ $i ] ) : 0;
			if ( $min_qty >= 2 && $percent > 0 ) {
				$tiers[] = array( 'min_qty' => $min_qty, 'percent' => $percent );
			}
		}

		usort( $tiers, function ( $a, $b ) {
			return $a['min_qty'] <=> $b['min_qty'];
		} );

		if ( empty( $tiers ) ) {
			delete_post_meta( $post_id, DGQD_META_KEY );
		} else {
			update_post_meta( $post_id, DGQD_META_KEY, $tiers );
		}
	}

	/* ---------------------------------------------------------------------
	 * Front end
	 * ------------------------------------------------------------------ */

	public static function enqueue_frontend_assets() {
		if ( ! is_product() && ! is_cart() && ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'dgqd-frontend',
			DGQD_URL . 'assets/css/frontend.css',
			array(),
			self::asset_version( 'assets/css/frontend.css' )
		);

		if ( is_product() ) {
			wp_enqueue_script(
				'dgqd-frontend',
				DGQD_URL . 'assets/js/frontend.js',
				array( 'jquery' ),
				self::asset_version( 'assets/js/frontend.js' ),
				true
			);
		}
	}

	/**
	 * Hooked to woocommerce_after_add_to_cart_quantity, which sits between the
	 * quantity input and the Add to Cart button. (woocommerce_before_add_to_cart_button
	 * is NOT the equivalent — it fires before the quantity input as well.)
	 */
	public static function render_product_table() {
		global $product;
		if ( ! $product ) {
			return;
		}

		$tiers = self::get_tiers( $product->get_id() );
		if ( empty( $tiers ) ) {
			return;
		}
		?>
		<div class="dg-qty-tiers">
			<p class="dg-qty-tiers-heading"><?php esc_html_e( 'Buy more, save more', 'dg-quantity-discounts' ); ?></p>
			<div class="dg-qty-tiers-tablewrap">
				<table class="dg-qty-tiers-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Buy', 'dg-quantity-discounts' ); ?></th>
							<th><?php esc_html_e( 'Get', 'dg-quantity-discounts' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $tiers as $i => $tier ) :
							$next  = isset( $tiers[ $i + 1 ] ) ? $tiers[ $i + 1 ]['min_qty'] - 1 : null;
							$range = $next ? $tier['min_qty'] . ' - ' . $next : $tier['min_qty'] . '+';
							?>
						<tr data-min-qty="<?php echo esc_attr( $tier['min_qty'] ); ?>">
							<td><?php echo esc_html( $range ); ?></td>
							<td><?php echo esc_html( self::format_percent( $tier['percent'] ) ); ?>% <?php esc_html_e( 'Off', 'dg-quantity-discounts' ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Pricing
	 * ------------------------------------------------------------------ */

	/**
	 * Applies the discounted unit price for whichever tier the line quantity reaches.
	 *
	 * The price is recomputed from get_regular_price() on every pass rather than
	 * adjusted in place, because this hook can fire several times per request —
	 * adjusting an already-adjusted price would compound the discount.
	 */
	public static function apply_tier_pricing( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			$tiers   = self::get_tiers( $product->get_id() );
			if ( empty( $tiers ) ) {
				continue;
			}

			$regular_price = (float) $product->get_regular_price();
			if ( $regular_price <= 0 ) {
				continue;
			}

			$base_price = $product->is_on_sale() ? (float) $product->get_sale_price() : $regular_price;
			$tier       = self::get_tier_for_quantity( $tiers, $cart_item['quantity'] );

			if ( $tier ) {
				$discounted = round( $regular_price * ( 1 - $tier['percent'] / 100 ), 2 );
				// Never let a smaller bulk % override a bigger existing sale discount.
				$product->set_price( min( $discounted, $base_price ) );
			} else {
				$product->set_price( $base_price );
			}
		}
	}

	/** Adds a "Bulk discount: X% off" note to the cart/checkout line item. */
	public static function cart_item_data( $item_data, $cart_item ) {
		$tiers = self::get_tiers( $cart_item['data']->get_id() );
		if ( empty( $tiers ) ) {
			return $item_data;
		}

		$tier = self::get_tier_for_quantity( $tiers, $cart_item['quantity'] );
		if ( $tier ) {
			$item_data[] = array(
				'name'  => __( 'Bulk discount', 'dg-quantity-discounts' ),
				'value' => self::format_percent( $tier['percent'] ) . '% off',
			);
		}
		return $item_data;
	}

	/* ------------------------------------------------------------------ */

	private static function asset_version( $relative_path ) {
		$file = DGQD_PATH . $relative_path;
		return file_exists( $file ) ? filemtime( $file ) : DGQD_VERSION;
	}
}

DGQD_Plugin::init();
