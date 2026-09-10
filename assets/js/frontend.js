/**
 * Highlights whichever tier row the current quantity falls into.
 * Purely cosmetic, the actual discount is worked out server side.
 */
jQuery( function ( $ ) {

	var $rows = $( '.dg-qty-tiers-table tr[data-min-qty]' );

	if ( ! $rows.length ) {
		return;
	}

	function highlightTier() {
		var qty  = parseInt( $( '.single_add_to_cart_button' ).closest( 'form' ).find( 'input.qty' ).val(), 10 ) || 1;
		var best = null;

		// rows are in ascending order, so the last one we clear is the winner
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

	// input as well as change, otherwise typing a qty does nothing until blur
	$( document ).on( 'change input', 'form.cart input.qty', highlightTier );

} );
