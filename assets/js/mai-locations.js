/**
 * The main function to get it started.
 */
function initLocations() {
	const params = maiLocationsVars.params;
	const maps   = document.querySelectorAll( '.mailocations-map' );
	const forms  = document.querySelectorAll( '.mai-locations-filters' );
	let   mapId  = 0;

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

			// If we have a distance and a unit.
			let distance = params['distance'];
			let unit     = params['units'];

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
			// If no coordinates.
			if ( ! ( lat && lng ) ) {
				// Center coordinates of the US.
				map.setCenter({ lat: 37.0902, lng: -95.7129 });
				// Zoom level can be adjusted as needed.
				map.setZoom(4);
			}
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
			// No radius. This shouldn't happen, but we have a fallback if it does.
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

	// Loop through forms.
	for ( const formEl of forms ) {
		const parent  = formEl.querySelector( '.mailocations-autocomplete-input-container' );
		const search  = formEl.querySelector( '.mailocations-autocomplete' );
		const address = formEl.querySelector( '.mailocations-address' );
		const submit  = formEl.querySelector( '.mailocations-filter-submit' );

		// If we have everything we need.
		if ( parent && search && address ) {
			const clear     = parent.querySelector( '.mailocations-autocomplete-clear' );
			const countries = search.dataset.countries;
			const options   = { fields: [ 'geometry', 'name' ] };
			const keys      = [];

			// If we're limiting to a country, add restrictions.
			if ( countries ) {
				options['componentRestrictions'] = { country: countries.split( ',' ) };
			}

			// Build autcomplete object.
			const autocomplete = new google.maps.places.Autocomplete( search, options );

			/**
			 * Update the hidden address field with the selected place.
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
				let   values   = {};

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
								// params['state'] = state;
								values['state'] = state;
							}
						} else if ( province ) {
							// params['province'] = province;
							values['province'] = province;
						}
					}
				}

				// Set values.
				values['address'] = search.value;
				values['lat']     = lat;
				values['lng']     = lng;

				// If we have hidden address field, updated it with values.
				if ( address ) {
					address.value = JSON.stringify( values );
				}
			});

			// If we have a clear link.
			if ( clear ) {
				console.log( clear );
				/**
				 * Clear the autocomplete field when clicking clear button.
				 */
				clear.addEventListener( 'click', function(e) {
					// Prevent form submission.
					e.preventDefault();

					// Clear input value and params.
					search.value = ''; // Empty visual value.
					search.setAttribute( 'value', '' ); // Empty attribute.
					search.focus(); // Focus on the input.

					// If we have hidden address fields.
					if ( address ) {
						address.value = '';
					}
				});
			}
		}

		// If we have a submit button.
		if ( submit ) {
			/**
			 * Add loader icon when a submit button is clicked.
			 */
			submit.addEventListener( 'click', function() {
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
			});
		}
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
		let value   = null;
		let element = document.querySelector( selector );

		// If we have a elementlement, get the value.
		if ( element ) {
			value = element.value;
		}

		// Return value or fallback.
		return value || fallback;
	}
}

/**
 * Load the Google Maps API and run the initLocations function.
 *
 * @returns {void}
 */
document.addEventListener( 'DOMContentLoaded', function() {
	// If we have maps, load the API.
	if ( document.querySelector( '.mailocations-map' ) || document.querySelector( '.mailocations-autocomplete' ) ) {
		// Load the Google Maps API asynchronously.
		const target    = document.getElementById( 'mai-locations-js' );
		const script    = document.createElement( 'script' );
		let   src       = `https://maps.googleapis.com/maps/api/js?key=${maiLocationsVars.apiKey}`;
		let   libraries = [ 'marker' ];
		// If we have a signature, add it.
		if ( maiLocationsVars.apiSig ) {
			src += '&signature=' + maiLocationsVars.apiSig;
		}

		// If we have autocomplete, add places library.
		if ( document.querySelector( '.mailocations-autocomplete' ) ) {
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