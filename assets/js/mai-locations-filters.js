window.initMap = function() {
	const url      = new URL( window.location.href );
	const searches = document.querySelectorAll( '.mailocations-autocomplete' );
	const filters  = document.querySelectorAll( '.mailocations-filter' );
	const submits  = document.querySelectorAll( '.mailocations-filter-submit' );
	const clears   = document.querySelectorAll( '.mailocations-filter-clear' );
	var   params   = {};

	/**
	 * Check for query string params and set defaults on page load.
	 */
	Object.keys( maiLocationsVars ).forEach( key => {
		var value = url.searchParams.get( key );
		value = Array.isArray( maiLocationsVars[key] ) ? value.split( ',' ) : value;
		params[key] = undefined === value || null === value ? maiLocationsVars[key] : value;
	});

	var refreshPage = function() {
		Object.keys( params ).forEach( key => {
			// Skip if not valid property.
			if ( ! ( key in maiLocationsVars ) ) {
				return;
			}

			// console.log( key, Array.isArray( maiLocationsVars[key] ), maiLocationsVars[key] );


			var value = Array.isArray( maiLocationsVars[key] ) ? params[key].push( params[key] ) : params[key];

			if ( Array.isArray( maiLocationsVars[key] ) ) {
				console.log( key, value );
			}


			// console.log( value );
			// console.log( params[key] );
			// console.log( params[key].length );

			// Add param.
			if ( '' !== params[key] ) {
				// url.searchParams.set( key, params[key].join( ',' ) );
				url.searchParams.set( key, value );
			}
			// Remove.
			else {
				url.searchParams.delete( key );
			}
		});

		// Refresh page.
		// window.location = url.href;
	};

	/**
	 * Set properties to the params object.
	 */
	// maiLocationsVars.taxonomies.forEach( taxonomy => {
	// 	var terms          = url.searchParams.get( taxonomy );
	// 	params[ taxonomy ] = terms ? terms.split( ',' ) : [];
	// });

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
		var units    = input.parentElement.parentElement.querySelectorAll( '.mailocations-autocomplete-units' );
		var clear    = input.parentElement.querySelectorAll( '.mailocations-autocomplete-clear' )[0];

		distance = 'undefined' !== distance ? distance[0] : null;
		units    = 'undefined' !== units ? units[0] : null;
		// distance = 'undefined' === distance ? 1000 : distance;
		// units    = 'undefined' === units ? 'mi' : units;

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

			refreshPage();
		});

		if ( distance ) {
			/**
			 *
			 */
			distance.addEventListener( 'change', function() {
				params[ 'distance' ] = this.value;

				if ( params['address'] ) {
					refreshPage();
				}
			});
		}

		if ( units ) {
			/**
			 *
			 */
			units.addEventListener( 'change', function() {
				params[ 'units' ] = this.value;

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
			params[ 'address' ] = '';
			params[ 'lat' ]     = '';
			params[ 'lng' ]     = '';

			// If there is a stored value.
			if ( value ) {
				refreshPage();
			}
		});
	});

	/**
	 * Handle location filter changes.
	 */
	filters.forEach( filter => {
		filter.addEventListener( 'change', function() {
			var select = 'select' === this.tagName.toLowerCase();
			var array  = Array.isArray( maiLocationsVars[ this.dataset.filter ] );

			// If choosing.
			if ( this.checked || select ) {
				if ( select && ! this.value ) {
					params[ this.dataset.filter ] = null;
				} else {
					// params[ this.dataset.filter ].push( this.value );
					params[ this.dataset.filter ] = array ? params[ this.dataset.filter ].push( this.value ) : this.value;
				}
			}
			// Remove.
			else {
				params[ this.dataset.filter ] = array ? params[ this.dataset.filter ].splice( params[ this.dataset.filter ].indexOf( this.value ), 1 ) : null;
			}

			refreshPage();
		});
	});

	/**
	 * Handle filter submit buttons.
	 */
	submits.forEach( submit => {
		submit.addEventListener( 'click', function() {
			refreshPage();
		});
	});

	/**
	 * Handle filter clear buttons.
	 */
	clears.forEach( clear => {
		clear.addEventListener( 'click', function() {
			Object.keys( params ).forEach( key => {
				params[key] = [];
			});

			searches.forEach( input => {
				input.setAttribute( 'value', '' );
			});

			refreshPage();
		});
	});
};
