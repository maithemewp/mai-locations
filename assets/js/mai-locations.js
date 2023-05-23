window.initMap = function() {
	const url     = new URL( window.location.href );
	const search  = document.getElementById( 'mailocations-autocomplete' );
	const filters = document.querySelectorAll( '.mailocations-filter' )
	const submits = document.querySelectorAll( '.mailocations-filter-submit' )
	let   params  = {};

	/**
	 * Set properties to the params object.
	 */
	maiLocationsVars.taxonomies.forEach( taxonomy => {
		var terms          = url.searchParams.get( taxonomy );
		params[ taxonomy ] = terms ? terms.split( ',' ) : [];
	});

	/**
	 * If we have a search field.
	 */
	if ( search ) {
		const center        = { lat: 50.064192, lng: -130.605469 };
		const defaultBounds = {
			north: center.lat + 0.1,
			south: center.lat - 0.1,
			east: center.lng + 0.1,
			west: center.lng - 0.1,
		};
		const autocomplete = new google.maps.places.Autocomplete(
			search,
			{
				bounds: defaultBounds,
				componentRestrictions: { country: "us" },
				fields: [ 'geometry', 'name' ],
				strictBounds: false,
				// types: ["establishment"],
			}
		);

		// search.addEventListener( 'focusout', function() {
		// 	console.log( 'empty' );
		// 	if ( search.value.length ) {

		// 		console.log( search.value.length );

		// 		setTimeout( function() {
		// 			params[ 'address' ] = [];
		// 			params[ 'lat' ]     = [];
		// 			params[ 'lng' ]     = [];

		// 			console.log( 'clear' );
		// 		}, 100 );
		// 	}
		// });

		/**
		 * Update url query parameters and refresh the page
		 * when and address is searched.
		 */
		autocomplete.addListener( 'place_changed', function() {
			const place = autocomplete.getPlace();
			const lat   = place.geometry.location.lat();
			const lng   = place.geometry.location.lng();

			// Set query params.
			params[ 'address' ] = [ search.value ];
			params[ 'lat' ]     = [ lat ];
			params[ 'lng' ]     = [ lng ];
		});
	}

	/**
	 * Handle location filter changes.
	 */
	filters.forEach( filter => {
		filter.addEventListener( 'click', function() {
			// If choosing.
			if ( this.checked ) {
				params[ this.dataset.filter ].push( this.value );
			}
			// Remove.
			else {
				params[ this.dataset.filter ].splice( params[ this.dataset.filter ].indexOf( this.value ), 1 );
			}
		});
	});

	/**
	 * Handle filter submit buttons.
	 */
	submits.forEach( submit => {
		submit.addEventListener( 'click', function() {
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
		});
	});
};
