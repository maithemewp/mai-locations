jQuery( document ).ready( function( $ ) {
	if ( 'object' !== typeof acf ) {
		return;
	}

	function initSortableField( field ) {
		// Bail if not a sortable field.
		if ( ! field.$el.hasClass( 'mai-locations-sortable' ) ) {
			return;
		}

		// Add sortable.
		field.$el.find( '.acf-checkbox-list' ).sortable( {
			items: '> li',
			handle: '> .mai-locations-sortable-handle',
			// forceHelperSize: true,
			placeholder: 'sortable-checkbox-placeholder',
			forcePlaceholderSize: true,
			scroll: true,
			create: function( event, ui ) {
				$( this ).find( 'li' ).append( '<span class="mai-locations-sortable-handle"><i class="dashicons dashicons-menu"></i></span>' );
			},
			stop: function( event, ui ) {},
			update: function( event, ui ) {
				$( this ).find( 'input[type="checkbox"]' ).trigger( 'change' );
			}
		} );
	}

	/**
	 * ready & append (ACF5)
	 *
	 * These events are called when a field element is ready for initialization.
	 * - ready: on page load similar to $(document).ready()
	 * - append: on new DOM elements appended via repeater field or other AJAX calls
	 */
	acf.addAction( 'ready_field/key=mai_location_fields', initSortableField );
	acf.addAction( 'append_field/key=mai_location_fields', initSortableField );
});