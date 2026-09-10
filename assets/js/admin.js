/**
 * Add/remove rows on the tier table in the product editor.
 * Rows are plain name="x[]" inputs, so nothing needs reindexing after a remove.
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

		// Never remove the last row, just blank it. An empty table with no inputs
		// looks broken and there's no obvious way back from it.
		if ( $tbody.find( 'tr' ).length > 1 ) {
			$( this ).closest( 'tr' ).remove();
		} else {
			$( this ).closest( 'tr' ).find( 'input' ).val( '' );
		}
	} );

} );
