/**
 * The main function to get it started.
 */
function initLocations() {
	const url          = new URL( window.location.href );
	const maps         = document.querySelectorAll( '.mailocations-map' );
	const searches     = document.querySelectorAll( '.mailocations-autocomplete' );
	const filters      = document.querySelectorAll( '.mailocations-filter' );
	const clears       = document.querySelectorAll( '.mailocations-filter-clear' );
	const defaults     = maiLocationsVars.defaults;
	const params       = maiLocationsVars.params;
	const autoComplete = maiLocationsVars.autoComplete;

	// Loop through map elements.
	for ( const mapEl of maps ) {
		let   current = null;
		const markers = mapEl.querySelectorAll( '.marker' );
		const map     = new google.maps.Map( mapEl,
			{
				zoom: parseInt( mapEl.dataset.zoom ),
				mapTypeId: google.maps.MapTypeId.ROADMAP,
				center: params['lat'] && params['lng'] ? { lat: parseFloat( params['lat'] ), lng: parseFloat( params['lng'] ) } : null,
			}
		);

		// Start markers property.
		map.markers = [];

		// If we have a search.
		if ( params['lat'] && params['lng'] ) {
			// Define the Font Awesome person (male?) icon as SVG path
			const icon = {
				path: 'M96 0c35.346 0 64 28.654 64 64s-28.654 64-64 64-64-28.654-64-64S60.654 0 96 0m48 144h-11.36c-22.711 10.443-49.59 10.894-73.28 0H48c-26.51 0-48 21.49-48 48v136c0 13.255 10.745 24 24 24h16v136c0 13.255 10.745 24 24 24h64c13.255 0 24-10.745 24-24V352h16c13.255 0 24-10.745 24-24V192c0-26.51-21.49-48-48-48z',
				fillColor: 'rgba(0,0,0,0.8)',
				fillOpacity: 1,
				strokeWeight: 0,
				scale: .05,
				anchor: new google.maps.Point(110, 600),

			};

			// Create current search location marker.
			current = new google.maps.Marker({
				position: { lat: parseFloat( params['lat'] ), lng: parseFloat( params['lng'] ) },
				map: map,
				icon: icon,
				animation: google.maps.Animation.DROP,
			});

			// Add location marker.
			map.markers.push( current );
		}

		// Loop through and add markers.
		for ( const markerEl of markers ) {
			// Create marker instance.
			var marker = new google.maps.Marker({
				position: { lat: parseFloat( markerEl.dataset.lat ), lng: parseFloat( markerEl.dataset.lng ) },
				map: map,
			});

			// If marker contains HTML, add it to an infoWindow.
			if ( markerEl.innerHTML ) {
				// Create info window.
				var infowindow = new google.maps.InfoWindow({
					content: markerEl.innerHTML,
				});

				// Creates an infowindow 'key' in the marker.
				marker.infowindow = infowindow;

				// Show info window when marker is clicked.
				marker.addListener( 'click', function() {
					return this.infowindow.open( map, this );
				});
			}

			// Add marker.
			map.markers.push( marker );
		}

		// Add a marker clusterer to manage the markers.
		const markerCluster = new markerClusterer.MarkerClusterer({
			map: map,
			markers: map.markers,
		});

		// Create map boundaries from all map markers.
		const bounds = new google.maps.LatLngBounds();

		// Loop through markers and extend bounds.
		for ( const marker of map.markers ) {
			bounds.extend( marker.getPosition() );
		}

		// Single marker.
		if ( 1 === map.markers.length ) {
			map.setCenter( bounds.getCenter() );
		}
		// Multiple markers.
		else {
			map.fitBounds( bounds );
		}
	}

	// Loop through map elements.
	for ( const searchEl of searches ) {
		let distance = searchEl.parentElement.parentElement.querySelectorAll( '.mailocations-autocomplete-distance' );
		let unit     = searchEl.parentElement.parentElement.querySelectorAll( '.mailocations-autocomplete-unit' );
		let clear    = searchEl.parentElement.querySelectorAll( '.mailocations-autocomplete-clear' )[0];

		// Get elements.
		distance = 'undefined' !== distance ? distance[0] : '';
		unit     = 'undefined' !== unit ? unit[0] : '';

		// Build autcomplete object.
		const autocomplete = new google.maps.places.Autocomplete( searchEl, autoComplete );

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

			const lat      = place.geometry.location.lat();
			const lng      = place.geometry.location.lng();
			let   country  = null;
			let   state    = null;
			let   province = null;

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
			params['address'] = searchEl.value;
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
			var value = searchEl.getAttribute( 'value' );

			// Clear input value and params.
			searchEl.setAttribute( 'value', '' ); // Empty attribute.
			searchEl.value     = ''; // Empty visual value.
			params['address']  = '';
			params['lat']      = '';
			params['lng']      = '';
			params['state']    = '';
			params['province'] = '';
			// params['distance'] = ''; // Leave this setting incase they want to do another search.

			if ( value ) {
				refreshPage();
			}
		});
	}

	// Loop through map elements.
	for ( const filterEl of filters ) {
		let select;
		let radio;

		filterEl.addEventListener( 'change', function() {
			select = 'select' === this.tagName.toLowerCase();
			radio  = 'radio'  === this.getAttribute( 'type' );

			params[ this.dataset.filter ] = ! ( this.dataset.filter in params ) ? [] : params[ this.dataset.filter ]

			// If choosing.
			if ( this.checked || select ) {
				// If choosing empty select option (show all).
				if ( select && ! this.value ) {
					params[ this.dataset.filter ] = [];
				}
				// Add value.
				else {
					if ( radio || select ) {
						params[ this.dataset.filter ] = [ this.value ];
					} else {
						params[ this.dataset.filter ].push( this.value );
					}
				}
			}
			// Remove.
			else {
				params[ this.dataset.filter ].splice( params[ this.dataset.filter ].indexOf( this.value ), 1 );
			}

			// Refresh.
			refreshPage();
		});
	}

	// Loop through map elements.
	for ( const clearEl of clears ) {
		clearEl.addEventListener( 'click', function() {
			// Empty all params.
			Object.keys( params ).forEach( key => {
				params[key] = '';
			});

			// Refresh.
			refreshPage();
		});
	}

	/**
	 * Refresh the page after adding/removing query strings based
	 * on searches and filters.
	 */
	function refreshPage() {
		// Get main element.
		const main = document.getElementsByTagName( 'main' )[0];

		// Add mai-locations-loading class to body.
		main.classList.add( 'mai-locations-loading' );

		// Add div to main.
		const loading = document.createElement( 'div' );
		loading.classList.add( 'mai-locations-loader' );
		document.body.appendChild( loading );

		// Add img to div.
		const loader = document.createElement( 'img' );
		loader.src = maiLocationsVars.loaderUrl;
		loading.classList.add( 'mai-locations-loader-svg' );
		loading.appendChild( loader );

		// Loop through defaults and find params to add to url.
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

		// Refresh page, removing any pagination.
		window.location = url.href.replace( /\/page\/\d+/, '' );
	}
}

// On domcontent loaded.
document.addEventListener( 'DOMContentLoaded', function() {
	// If we have maps, load the API.
	if ( document.querySelectorAll( '.mailocations-map' ).length || document.querySelectorAll( '.mailocations-autocomplete' ).length ) {
		// Load the Google Maps API asynchronously.
		const target = document.getElementById( 'mai-locations-js' );
		const script = document.createElement( 'script' );
		let   src    = `https://maps.googleapis.com/maps/api/js?key=${maiLocationsVars.apiKey}`;

		// If we have autocomplete, add places library.
		if ( document.querySelectorAll( '.mailocations-autocomplete' ).length ) {
			src += '&libraries=places';
		}

		// Add script after this one.
		script.src = src += '&loading=async';
		script.src = src += '&callback=initLocations';
		target.parentElement.insertBefore( script, target );
	}
	// Otherwise run the function directly.
	else {
		initLocations();
	}
});