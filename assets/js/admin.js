/**
 * DG Quantity Discounts — product edit screen tier repeater.
 */
jQuery( function ( $ ) {

	$( document ).on( 'click', '#dg-add-tier-row', function ( e ) {
		e.preventDefault();

		var $tbody = $( '#dg-qty-tiers-table tbody' );
		var $row   = $tbody.find( 'tr:first' ).clone();

		$row.find( 'input' ).val( '' );
		$tbody.append( $row );
	} );

	$( document ).on( 'click', '.dg-remove-tier-row', function ( e ) {
		e.preventDefault();

		var $tbody = $( this ).closest( 'tbody' );

		// Keep one empty row rather than leaving the table with no inputs at all.
		if ( $tbody.find( 'tr' ).length > 1 ) {
			$( this ).closest( 'tr' ).remove();
		} else {
			$( this ).closest( 'tr' ).find( 'input' ).val( '' );
		}
	} );

} );
