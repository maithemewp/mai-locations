window.initMap = function() {
	const url      = new URL( window.location.href );
	const searches = document.querySelectorAll( '.mailocations-autocomplete' );
	const filters  = document.querySelectorAll( '.mailocations-filter' );
	const clears   = document.querySelectorAll( '.mailocations-filter-clear' );
	const defaults = maiLocationsVars.defaults;
	var   params   = maiLocationsVars.params;

	/**
	 * Refresh the page after adding/removing query strings based
	 * on searches and filters.
	 */
	var refreshPage = function() {
		Object.keys( defaults ).forEach( key => {
			// Skip if not a key that has changed.
			if ( ! ( key in params ) ) {
				return;
			}

			// Force strings.
			var value    = Array.isArray( params[key] ) ? params[key].join() : params[key].toString();
			var original = Array.isArray( defaults[key] ) ? defaults[key] : defaults[key].toString();

			// Add param if there is a value.
			if ( '' !== value && value !== original ) {
				url.searchParams.set( key, value );
			}
			// Remove.
			else {
				url.searchParams.delete( key );
			}
		});

		// Refresh page.
		window.location = url.href;
	};

	/**
	 * Handle autocomplete fields.
	 */
	searches.forEach( input => {
		// const center        = { lat: 50.064192, lng: -130.605469 };
		// const defaultBounds = {
		// 	north: center.lat + 0.1,
		// 	south: center.lat - 0.1,
		// 	east: center.lng + 0.1,
		// 	west: center.lng - 0.1,
		// };
		var distance = input.parentElement.parentElement.querySelectorAll( '.mailocations-autocomplete-distance' );
		var unit     = input.parentElement.parentElement.querySelectorAll( '.mailocations-autocomplete-unit' );
		var clear    = input.parentElement.querySelectorAll( '.mailocations-autocomplete-clear' )[0];

		distance = 'undefined' !== distance ? distance[0] : '';
		unit     = 'undefined' !== unit ? unit[0] : '';

		const autocomplete = new google.maps.places.Autocomplete(
			input,
			{
				// bounds: defaultBounds,
				componentRestrictions: { country: "us" },
				fields: [ 'geometry', 'name' ],
				strictBounds: false,
				// types: ["establishment"],
			}
		);

		/**
		 * Update url query parameters and refresh the page
		 * when and address is searched.
		 */
		autocomplete.addListener( 'place_changed', function() {
			var place = autocomplete.getPlace();
			var lat   = place.geometry.location.lat();
			var lng   = place.geometry.location.lng();

			// Set query params.
			params[ 'address' ] = input.value;
			params[ 'lat' ]     = lat;
			params[ 'lng' ]     = lng;

			// Refresh.
			refreshPage();
		});


		if ( distance ) {
			/**
			 * Set distance and only refresh if there is an address.
			 */
			distance.addEventListener( 'change', function() {
				params[ 'distance' ] = this.value;

				if ( params['address'] ) {
					refreshPage();
				}
			});
		}

		if ( unit ) {
			/**
			 * Set unit and only refresh if there is an address.
			 */
			unit.addEventListener( 'change', function() {
				params[ 'unit' ] = this.value;

				if ( params['address'] ) {
					refreshPage();
				}
			});
		}

		/**
		 * Clear the autocomplete field when clicking clear button.
		 */
		clear.addEventListener( 'click', function() {
			var value = input.getAttribute( 'value' );

			// Clear input value and params.
			input.setAttribute( 'value', '' ); // Empty attribute.
			input.value = ''; // Empty visual value.
			params[ 'address' ]  = '';
			params[ 'lat' ]      = '';
			params[ 'lng' ]      = '';

			if ( value ) {
				refreshPage();
			}
		});
	});

	/**
	 * Handle location filter changes.
	 */
	filters.forEach( filter => {
		var select;
		filter.addEventListener( 'change', function() {
			select                        = 'select' === this.tagName.toLowerCase();
			params[ this.dataset.filter ] = ! ( this.dataset.filter in params ) ? [] : params[ this.dataset.filter ]

			// If choosing.
			if ( this.checked || select ) {
				// If choosing empty select option (show all).
				if ( select && ! this.value ) {
					params[ this.dataset.filter ] = [];
				}
				// Add value.
				else {
					params[ this.dataset.filter ].push( this.value );
				}
			}
			// Remove.
			else {
				params[ this.dataset.filter ].splice( params[ this.dataset.filter ].indexOf( this.value ), 1 );
			}

			// Refresh.
			refreshPage();
		});
	});

	/**
	 * Handle filter clear buttons.
	 */
	clears.forEach( clear => {
		clear.addEventListener( 'click', function() {
			// Empty all params.
			Object.keys( params ).forEach( key => {
				params[key] = '';
			});

			// Refresh.
			refreshPage();
		});
	});
};
