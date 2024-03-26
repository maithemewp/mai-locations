/**
 * The main function to get it started.
 */
function initLocations() {
	const url          = new URL( window.location.href );
	const maps         = document.querySelectorAll( '.mailocations-map' );
	const searches     = document.querySelectorAll( '.mailocations-autocomplete' );
	const filters      = document.querySelectorAll( '.mailocations-filter' );
	const submits      = document.querySelectorAll( '.mailocations-filter-submit' );
	const clears       = document.querySelectorAll( '.mailocations-filter-clear' );
	const defaults     = maiLocationsVars.defaults;
	const params       = maiLocationsVars.params;
	const autoComplete = maiLocationsVars.autoComplete;
	let   mapId        = 0;

	// Loop through map elements.
	for ( const mapEl of maps ) {
		let   current = null;    // Current location marker.
		let   radius  = 0; // 25 miles in meters.
		let   lat     = parseFloat( params['lat'] );
		let   lng     = parseFloat( params['lng'] );
		const markers = mapEl.querySelectorAll( '.marker' );
		const map     = new google.maps.Map( mapEl,
			{
				zoom: parseInt( mapEl.dataset.zoom ),
				mapTypeId: google.maps.MapTypeId.ROADMAP,
				center: lat && lng ? { lat: lat, lng: lng } : null,
				mapId: 'mailocationsMap' + mapId++, // Map ID is required for advanced markers.
			}
		);

		// Start markers property.
		map.markers = [];

		// If we have a search.
		if ( lat && lng ) {
			// Create pin element.
			const pin = new google.maps.marker.PinElement({
				background: 'var(--color-primary, #333)',
				borderColor: 'var(--color-primary-dark, #000)',
				glyphColor: 'var(--color-primary-dark, rgba(0, 0, 0, 0.2))',
				scale: 0.6,
			});

			// Create current search location marker.
			current = new google.maps.marker.AdvancedMarkerElement({
				position: { lat: lat, lng: lng },
				map: map,
				content: pin.element,
			});

			// Add location marker.
			map.markers.push( current );

			// If we have a distance and a unit.
			let distance = params['distance'];
			let unit     = params['unit'];

			// If no distance, check for distance element.
			if ( ! distance ) {
				distance = getDefaultValue( '.mailocations-autocomplete-distance', null );
			}

			// If no unit, check for unit element.
			if ( ! unit ) {
				unit = getDefaultValue( '.mailocations-autocomplete-unit', 'mi' );
			}

			// If we have a distance, convert distance to meters.
			if ( distance ) {
				radius = parseFloat( distance );

				// If miles.
				if ( 'mi' === unit ) {
					radius *= 1609.34;
				}
				// If kilometers.
				else if ( 'km' === unit ) {
					radius *= 1000;
				}

				// Add circle overlay for search radius.
				const circle = new google.maps.Circle({
					strokeColor: '#ff0000',
					strokeOpacity: 0.5,
					strokeWeight: 1,
					fillColor: '#ff0000',
					fillOpacity: 0.05,
					map: map,
					center: { lat: lat, lng: lng },
					radius: radius, // Specify radius in meters.
				});
			}
		}

		// Loop through and add markers.
		for ( const markerEl of markers ) {
			// Create marker instance.
			const marker = new google.maps.marker.AdvancedMarkerElement({
				position: { lat: parseFloat( markerEl.dataset.lat ), lng: parseFloat( markerEl.dataset.lng ) },
				map: map,
			});

			// If marker contains HTML, add it to an infoWindow.
			if ( markerEl.innerHTML ) {
				// Create info window.
				const infowindow = new google.maps.InfoWindow({
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

		// If we have no map markers, set the center of the map.
		if ( ! map.markers.length ) {
			// Center coordinates of the US.
			map.setCenter({ lat: 37.0902, lng: -95.7129 });
			// Zoom level can be adjusted as needed.
			map.setZoom(4);
		}
		// Handle markers.
		else {
			// Add a marker clusterer to manage the markers.
			const markerCluster = new markerClusterer.MarkerClusterer({
				map: map,
				markers: map.markers,
			});

			// If radius.
			if ( radius ) {
				// Get the center coordinates of the map.
				const mapCenter = map.getCenter();

				// Convert radius from meters to degrees (approximately).
				const radiusInDegrees = radius / 111300; // 111300 meters = 1 degree of latitude

				// Calculate the latitude and longitude offsets.
				const latOffset = radiusInDegrees;
				const lngOffset = radiusInDegrees / Math.cos( mapCenter.lat() * Math.PI / 180 );

				// Calculate the map bounds based on the center and offsets.
				const bounds = new google.maps.LatLngBounds(
					new google.maps.LatLng( mapCenter.lat() - latOffset, mapCenter.lng() - lngOffset ),
					new google.maps.LatLng( mapCenter.lat() + latOffset, mapCenter.lng() + lngOffset )
				);

				// Fit the map to the bounds
				map.fitBounds( bounds );
			}
			// No radius.
			else {
				// Create map boundaries.
				const bounds = new google.maps.LatLngBounds();

				// Loop through markers and extend bounds.
				for ( const marker of map.markers ) {
					bounds.extend( marker.position );
				}

				// Single marker, set new center to the marker.
				if ( 1 === map.markers.length ) {
					map.setCenter( bounds.getCenter() );
				}
				// Multiple markers.
				else {
					map.fitBounds( bounds );
				}
			}
		}
	} // End map loop.

	// Loop through search elements.
	for ( const searchEl of searches ) {
		let distance     = searchEl.parentElement.parentElement.querySelectorAll( '.mailocations-autocomplete-distance' );
		let unit         = searchEl.parentElement.parentElement.querySelectorAll( '.mailocations-autocomplete-unit' );
		let clear        = searchEl.parentElement.querySelectorAll( '.mailocations-autocomplete-clear' )[0];
		let countries    = searchEl.dataset.countries;
		let options      = { fields: [ 'geometry', 'name' ] };

		// Set first.
		distance = distance.length ? distance[0] : '';
		unit     = unit.length ? unit[0] : '';

		// If we're limiting to a country, add restrictions.
		if ( countries ) {
			options['componentRestrictions'] = { country: countries.split( ',' ) };
		}

		// Build autcomplete object.
		const autocomplete = new google.maps.places.Autocomplete( searchEl, options );

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

			// Set initial vars.
			const lat      = place.geometry.location.lat();
			const lng      = place.geometry.location.lng();
			let   country  = null;
			let   state    = null;
			let   province = null;

			// Get address.
			if ( place.address_components ) {
				// Get country/state.
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

		// If we have a distance.
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

		// If we have a unit value.
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

		// If we have a clear link.
		if ( clear ) {
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

				// If submit buttons, focus on the search input because this won't force a refresh.
				if ( submits.length ) {
					searchEl.focus();
				}

				// If a value is getting cleared.
				if ( value ) {
					refreshPage();
				}
			});
		}
	}

	// Loop through map elements.
	for ( const filterEl of filters ) {
		let select;
		let radio;

		/**
		 * Update url query parameters and refresh the page when a filter changes.
		 */
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

	// Loop through submit button elements.
	for ( const submitEl of submits ) {
		/**
		 * Add loader icon and refresh the page when a submit button is clicked.
		 */
		submitEl.addEventListener( 'click', function() {
			// Add loading spinner.
			this.innerHTML = `&nbsp;<svg class="mailocations-loading-svg" width="36" height="12" viewBox="0 0 36 12" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
				<style>
				.mailocations-loading-svg {
					position: absolute;
					top: 50%;
					left: 50%;
					transform: translate(-50%, -50%);
				}
				.mailocations-spinner {
					animation: spinner_xe7Q .8s linear infinite;
				}
				.mailocations-spinner2 {
					animation-delay: -.65s;
				}
				.mailocations-spinner3 {
					animation-delay: -.5s;
				}
				@keyframes spinner_xe7Q{
					93.75%,100% { r:3px; }
					46.875% { r:.2px; }
				}
				</style>
				<circle class="mailocations-spinner" cx="4" cy="6" r="3"/>
				<circle class="mailocations-spinner mailocations-spinner2" cx="18" cy="6" r="3"/>
				<circle class="mailocations-spinner mailocations-spinner3" cx="30" cy="6" r="3"/>
			</svg>`;

			// Refresh.
			refreshPage( true );
		});
	}

	// Loop through map elements.
	for ( const clearEl of clears ) {
		/**
		 * Clear all filters and refresh the page when a clear button is clicked.
		 */
		clearEl.addEventListener( 'click', function() {
			// Empty all params.
			Object.keys( params ).forEach( key => {
				params[key] = '';
			});

			// Refresh.
			refreshPage( true );
		});
	}

	/**
	 * Gets a default value from a selector or returns a fallback.
	 *
	 * @param {string} selector
	 * @param {mixed}  fallback
	 *
	 * @returns {mixed}
	 */
	function getDefaultValue( selector, fallback ) {
		let value    = null;
		let elements = document.querySelectorAll( selector );
		let element  = elements.length ? elements[0] : '';

		// If we have a elementlement, get the value.
		if ( element ) {
			value = element.value;
		}

		// Return value or fallback.
		return value || fallback;
	}

	/**
	 * Refresh the page after adding/removing query strings based
	 * on searches and filters.
	 *
	 * @param {boolean} force Force a refresh.
	 *
	 * @returns {void}
	 */
	function refreshPage( force = false ) {
		// Bail if we're not forcing a refresh and there is a submit button on the page.
		// This means we're requiring the submit button to refresh.
		// If there is no submit button, we'll automatically refresh the page when a filter is changed.
		if ( ! force && submits.length ) {
			return;
		}

		// Get target element.
		let target = document.getElementsByTagName( 'main' );
			target = target.length ? target[0] : document.body;

		// Lower opacity on the target element.
		target.style.opacity = 0.5;

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

/**
 * Load the Google Maps API and run the initLocations function.
 *
 * @returns {void}
 */
document.addEventListener( 'DOMContentLoaded', function() {
	// If we have maps, load the API.
	if ( document.querySelectorAll( '.mailocations-map' ).length || document.querySelectorAll( '.mailocations-autocomplete' ).length ) {
		// Load the Google Maps API asynchronously.
		const target    = document.getElementById( 'mai-locations-js' );
		const script    = document.createElement( 'script' );
		let   src       = `https://maps.googleapis.com/maps/api/js?key=${maiLocationsVars.apiKey}`;
		let   libraries = [ 'marker' ];

		// If we have autocomplete, add places library.
		if ( document.querySelectorAll( '.mailocations-autocomplete' ).length ) {
			libraries.push( 'places' );
		}

		// Add libraries.
		src += '&libraries=' + libraries.join( ',' );

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