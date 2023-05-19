window.initMap = function() {
	const parser  = new URL( window.location );
	const search  = document.getElementById( 'mailocations-autocomplete' );
	const filters = document.querySelectorAll( '.mailocations-filter' )
	const submits = document.querySelectorAll( '.mailocations-filter-submit' )
	let   params  = {};

	// Set empty properties to the params object.
	// TODO: Check url for existing?
	filters.forEach( filter => {
		params[ filter.dataset.filter ] = [];
	});

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

		/**
		 * Update url query parameters and refresh the page
		 * when and address is searched.
		 */
		autocomplete.addListener( 'place_changed', function () {
			const place  = autocomplete.getPlace();
			const name   = place.name;
			const lat    = place.geometry.location.lat();
			const lng    = place.geometry.location.lng();

			// Set query params.
			addSearchParams(
				{
					'address': search.value,
					'lat': lat,
					'lng': lng,
				}
			);
		});
	}


	filters.forEach( filter => {
		filter.addEventListener( 'click', function() {
			if ( this.checked ) {
				params[ this.dataset.filter ].push( this.value );
			} else {
				params[ this.dataset.filter ].splice( params[ this.dataset.filter ].indexOf( this.value ), 1)
			}
		});
	});

	submits.forEach( submit => {
		submit.addEventListener( 'click', function() {
			Object.keys( params ).forEach( key => {
				if ( params[key].length ) {
					parser.searchParams.set( key, params[key].join( ',' ) );
				} else {
					parser.searchParams.delete( key );
				}
			});

			// Refresh page.
			window.location = parser.href;
		});
	});
};
