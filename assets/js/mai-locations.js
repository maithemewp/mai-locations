window.initMap = function() {
	const url         = new URL( window.location.href );
	const searches    = document.querySelectorAll( '.mailocations-autocomplete' );
	const filters     = document.querySelectorAll( '.mailocations-filter' );
	const submits     = document.querySelectorAll( '.mailocations-filter-submit' );
	const clears      = document.querySelectorAll( '.mailocations-filter-clear' );
	let   params      = {};
	var   refreshPage = function() {
		Object.keys( params ).forEach( key => {
			// Add param.
			if ( params[key].length ) {
				url.searchParams.set( key, params[key].join( ',' ) );
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
	 * Set properties to the params object.
	 */
	maiLocationsVars.taxonomies.forEach( taxonomy => {
		var terms          = url.searchParams.get( taxonomy );
		params[ taxonomy ] = terms ? terms.split( ',' ) : [];
	});

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
		const clear        = input.parentElement.querySelectorAll( '.mailocations-autocomplete-clear' )[0];
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
			const place = autocomplete.getPlace();
			const lat   = place.geometry.location.lat();
			const lng   = place.geometry.location.lng();

			// Set query params.
			params[ 'address' ] = [ input.value ];
			params[ 'lat' ]     = [ lat ];
			params[ 'lng' ]     = [ lng ];

			refreshPage();
		});

		/**
		 * Clear the autocomplete field when clicking clear button.
		 */
		clear.addEventListener( 'click', function() {
			var value = input.value;

			// Clear input value and params.
			input.setAttribute( 'value', '' );
			params[ 'address' ] = [];
			params[ 'lat' ]     = [];
			params[ 'lng' ]     = [];

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

			// If choosing.
			if ( this.checked || select ) {
				if ( select && ! this.value ) {
					params[ this.dataset.filter ] = [];
				} else {
					params[ this.dataset.filter ].push( this.value );
				}
			}
			// Remove.
			else {
				params[ this.dataset.filter ].splice( params[ this.dataset.filter ].indexOf( this.value ), 1 );
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
