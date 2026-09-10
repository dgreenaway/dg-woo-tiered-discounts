/**
 * DG Quantity Discounts — highlights the tier row matching the quantity entered.
 */
jQuery( function ( $ ) {

	var $rows = $( '.dg-qty-tiers-table tr[data-min-qty]' );

	if ( ! $rows.length ) {
		return;
	}

	function highlightTier() {
		var qty  = parseInt( $( '.single_add_to_cart_button' ).closest( 'form' ).find( 'input.qty' ).val(), 10 ) || 1;
		var best = null;

		// Rows are ascending, so the last one the quantity reaches is the active tier.
		$rows.removeClass( 'dg-active-tier' ).each( function () {
			if ( qty >= parseInt( $( this ).data( 'min-qty' ), 10 ) ) {
				best = $( this );
			}
		} );

		if ( best ) {
			best.addClass( 'dg-active-tier' );
		}
	}

	highlightTier();
	$( document ).on( 'change input', 'form.cart input.qty', highlightTier );

} );
