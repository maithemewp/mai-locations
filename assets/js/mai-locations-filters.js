window.initMap = function() {
	const url          = new URL( window.location.href );
	const maps         = document.querySelectorAll( '.mailocations-map' );
	const searches     = document.querySelectorAll( '.mailocations-autocomplete' );
	const filters      = document.querySelectorAll( '.mailocations-filter' );
	const clears       = document.querySelectorAll( '.mailocations-filter-clear' );
	const defaults     = maiLocationsVars.defaults;
	var   params       = maiLocationsVars.params;
	var   autoComplete = maiLocationsVars.autoComplete;

	/**
	 */
	var initMarker = function( markerEl, map ) {
		// Create marker instance.
		var marker = new google.maps.Marker(
			{
				position: new google.maps.LatLng( parseFloat( markerEl.dataset.lat ), parseFloat( markerEl.dataset.lng ) ),
				map: map,
			}
		);

		// Append to reference for later use.
		map.markers.push( marker );

		// If marker contains HTML, add it to an infoWindow.
		if ( markerEl.innerHTML ) {
			// Create info window.
			var infowindow = new google.maps.InfoWindow(
				{
					content: markerEl.innerHTML
				}
			);

			// Show info window when marker is clicked.
			google.maps.event.addListener( marker, 'click', function() {
				infowindow.open( map, marker );
			});
		}
	}

	/**
	 */
	var centerMap = function( map ) {
		// Create map boundaries from all map markers.
		var bounds = new google.maps.LatLngBounds();

		// Loop through markers and extend bounds.
		for ( const marker of map.markers ) {
			bounds.extend({
				lat: marker.position.lat(),
				lng: marker.position.lng()
			});
		}

		// Single marker.
		if ( 1 === map.markers.length ) {
			map.setCenter( bounds.getCenter() );
		}
		// Multiple markers.
		else{
			map.fitBounds( bounds );
		}
	}

	// Loop through map elements.
	for ( const mapEl of maps ) {
		var markers = mapEl.querySelectorAll( '.marker' );
		var map     = new google.maps.Map( mapEl,
			{
				zoom: parseInt( mapEl.dataset.zoom ),
				mapTypeId: google.maps.MapTypeId.ROADMAP
			}
		);

		// Start markers property.
		map.markers = [];

		// Loop through and add markers.
		for ( const marker of markers ) {
			initMarker( marker, map );
		}

		// Add a marker clusterer to manage the markers.
		const mapMarkers    = map.markers;
		const markerCluster = new markerClusterer.MarkerClusterer({ map, mapMarkers });

		// Center map based on markers.
		centerMap( map );
	}

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
		var distance = input.parentElement.parentElement.querySelectorAll( '.mailocations-autocomplete-distance' );
		var unit     = input.parentElement.parentElement.querySelectorAll( '.mailocations-autocomplete-unit' );
		var clear    = input.parentElement.querySelectorAll( '.mailocations-autocomplete-clear' )[0];

		// Get elements.
		distance = 'undefined' !== distance ? distance[0] : '';
		unit     = 'undefined' !== unit ? unit[0] : '';

		// Build autcomplete object.
		const autocomplete = new google.maps.places.Autocomplete( input, autoComplete );

		/**
		 * Update url query parameters and refresh the page
		 * when and address is searched.
		 */
		autocomplete.addListener( 'place_changed', function() {
			const place = autocomplete.getPlace();

			/**
			 * Bail if we don't have a precise place.
			 * This happens for "Georgia" because it may be the state or country.
			 * A suggested option needs to be chosen.
			 */
			if ( ! place || ! place.geometry ) {
				return;
			}

			var lat      = place.geometry.location.lat();
			var lng      = place.geometry.location.lng();
			var country  = null;
			var state    = null;
			var province = null;

			// Get address.
			if ( place.address_components ) {
				for ( const component of place.address_components ) {
					var type = component.types[0];

					if ( 'country' === type ) {
						country = component.short_name;
					}

					if ( 'administrative_area_level_1' === type ) {
						state = component.short_name;
					}
				}

				// Maybe set state/province.
				if ( country ) {
					if ( 'US' === country ) {
						if ( state ) {
							params['state'] = state;
						}
					} else if ( province ) {
						params['province'] = province;
					}
				}
			}

			// Set query params.
			params['address'] = input.value;
			params['lat']     = lat;
			params['lng']     = lng;

			// Refresh.
			refreshPage();
		});


		if ( distance ) {
			/**
			 * Set distance and only refresh if there is an address.
			 */
			distance.addEventListener( 'change', function() {
				params['distance'] = this.value;

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
				params['unit'] = this.value;

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
			input.value        = '';  // Empty visual value.
			params['address']  = '';
			params['lat']      = '';
			params['lng']      = '';
			params['state']    = '';
			params['province'] = '';

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
