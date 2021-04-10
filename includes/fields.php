<?php

add_action( 'acf/init', 'mailocations_add_field_groups' );
/**
 * Add Location Info and Locations field groups.
 *
 * @since 0.1.0
 *
 * @return void
 */
function mailocations_add_field_groups() {
	$plural   = mailocations_get_label_plural();
	$singular = mailocations_get_label_singular();

	// Settings.
	acf_add_options_sub_page(
		[
			'title'      => __( 'Settings Page', 'mai-locations' ),
			'parent'     => 'edit.php?post_type=mai_location',
			'menu_slug'  => 'location_settings',
			'capability' => 'manage_options'
		]
	);

		acf_add_local_field_group(
			[
				'key'    => 'group_6070b71fdaf26',
				'title'  => __( 'Mai Locations Settings', 'mai-locations' ),
				'fields' => [
					[
						'key'           => 'field_6070b73a79adf',
						'label'         => __( 'Plural Label', 'mai-locations' ),
						'name'          => 'location_label_plural',
						'type'          => 'text',
						'required'      => 1,
						'default_value' => __( 'Locations', 'mai-locations' ),
						'placeholder'   => __( 'Locations', 'mai-locations' ),
					],
					[
						'key'           => 'field_6070b75c79ae0',
						'label'         => __( 'Singular Label', 'mai-locations' ),
						'name'          => 'location_label_singular',
						'type'          => 'text',
						'required'      => 1,
						'default_value' => __( 'Location', 'mai-locations' ),
						'placeholder'   => __( 'Location', 'mai-locations' ),
					],
					[
						'key'           => 'field_6070b7a379ae1',
						'label'         => __( 'Base URL', 'mai-locations' ),
						'name'          => 'location_base_url',
						'type'          => 'text',
						'instructions'  => 'Visit Dashboard > Settings > Permalinks and hit "Save" if updating this setting.',
						'required'      => 1,
						'default_value' => 'locations',
						'placeholder'   => 'locations',
					],
				],
				'location' => [
					[
						[
							'param'    => 'options_page',
							'operator' => '==',
							'value'    => 'location_settings',
						],
					],
				],
				'menu_order'  => 10,
				'description' => '',
			]
		);

	// Location Info.
	acf_add_local_field_group(
		[
			'key'        => 'group_6884dd5bdf8ed',
			'title'      => sprintf( '%s %s', $singular, __( 'Info', 'mai-locations' ) ),
			'fields'     => mailocations_get_fields(),
			'menu_order' => 10, // Allow other field groups before or after by setting menu_order.
			'location'   => [
				[
					[
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'mai_location',
					],
				],
			],
		]
	);

	// Locations Table block.
	acf_add_local_field_group(
		[
			'key'    => 'group_6071bfd60bf2b',
			'title'  => sprintf( '%s %s', $plural, __( 'Table', 'mai-locations' ) ),
			'fields' => [
				[
					'key'         => 'field_6071bfebbfdab',
					'label'       => __( 'Title', 'mai-locations' ),
					'name'        => 'locations_table_title',
					'type'        => 'text',
					'placeholder' => sprintf( '%s %s', __( 'My', 'mai-locations' ), $plural ),
				],
				[
					'key'         => 'field_6071c00cbfdac',
					'label'       => __( 'Table Header', 'mai-location' ),
					'name'        => 'locations_table_header',
					'type'        => 'text',
					'placeholder' => $plural,
				],
				[
					'key'   => 'field_6071d22cdrdbd',
					'label' => __( 'No Results Message', 'mai-location' ),
					'name'  => 'locations_no_results',
					'type'  => 'textarea',
					'rows'  => 2,
				],
				[
					'key'          => 'field_6071c076bfdae',
					'label'        => sprintf( '%s %s', $singular, __( 'Edit Form', 'mai-locations' ) ),
					'instructions' => __( 'Allow editing of the following fields in addition to all custom fields', 'mai-locations' ),
					'name'         => 'location_edit_fields',
					'type'         => 'checkbox',
					'choices'      => [
						'title'   => 'Edit title',
						'content' => 'Edit content',
					],
					'default_value' => [
						'title',
						'content',
					],
				],
			],
			'location' => [
				[
					[
						'param'    => 'block',
						'operator' => '==',
						'value'    => 'acf/mai-locations-table',
					],
				],
			],
		]
	);

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Locations.
	acf_add_local_field_group(
		[
			'key'         => 'group_606f28b8452ba',
			'title'       => $plural,
			'description' => sprintf( '%s %s', $plural, __( 'Locations this user can manage' ) ),
			'fields'      => [
				[
					'key'           => 'field_606f28c86abee',
					'label'         => 'Locations',
					'name'          => 'user_locations',
					'type'          => 'post_object',
					'post_type'     => [
						'mai_location',
					],
					'allow_null'    => 1,
					'multiple'      => 1,
					'ui'            => 1,
					'return_format' => 'object',
				],
			],
			'location' => [
				[
					[
						'param'    => 'user_form',
						'operator' => '==',
						'value'    => 'edit',
					],
				],
			],
		]
	);
}

/**
 * Gets all fields for metabox.
 *
 * @since 0.1.0
 *
 * @return array
 */
function mailocations_get_fields() {
	static $fields = null;

	if ( ! is_null( $fields ) && is_array( $fields ) ) {
		return $fields;
	}

	$fields = [];
	$raw    = mailocations_get_fields_raw();

	if ( ! $raw ) {
		return $fields;
	}

	foreach ( $raw as $name => $values ) {
		$values['name'] = $name;
		$fields[]       = $values;
	}

	return $fields;
}

/**
 * Gets all tab fields.
 *
 * @since 0.1.0
 *
 * @return array
 */
function mailocations_get_fields_tabs() {
	static $tabs = null;

	if ( ! is_null( $tabs ) && is_array( $tabs ) ) {
		return $tabs;
	}

	$tabs   = [];
	$fields = mailocations_get_fields_raw();

	if ( ! $fields ) {
		return $tabs;
	}

	foreach ( $fields as $name => $values ) {
		if ( 'tab' !== $values['type'] ) {
			continue;
		}
		$tabs[ $name ] = $values;
	}

	return $tabs;
}

/**
 * Gets all fields before setup for ACF metabox.
 *
 * @since 0.1.0
 *
 * @return array
 */
function mailocations_get_fields_raw() {
	static $fields = null;

	if ( ! is_null( $fields ) && is_array( $fields ) ) {
		return $fields;
	}

	$general  = mailocations_get_general_fields();
	$location = mailocations_get_address_fields();
	$social   = mailocations_get_social_fields();
	$fields   = array_merge( $general, $location, $social );
	$fields   = apply_filters( 'mailocations_fields', $fields );

	return $fields;
}

/**
 * Gets field defaults.
 *
 * @since 0.1.0
 *
 * @return array
 */
function mailocations_get_fields_defaults() {
	static $fields = null;

	if ( ! is_null( $fields ) && is_array( $fields ) ) {
		return $fields;
	}

	$fields = mailocations_get_fields_raw();

	if ( ! $fields ) {
		return $fields;
	}

	foreach ( $fields as $name => $values ) {
		// Remove tabs.
		if ( 'tab' === $values['type'] ) {
			unset( $fields[ $name ] );
			continue;
		}

		// Add default.
		if ( ! isset( $values['default_value'] ) ) {
			$fields[ $name ]['default_value'] = '';
		}
	}

	$fields = wp_list_pluck( $fields, 'default_value' );

	return $fields;
}

/**
 * Gets general fields.
 *
 * @since 0.1.0
 *
 * @return array
 */
function mailocations_get_general_fields() {
	$fields = [
		'location_general_tab' => [
			'key'       => 'field_5773cc5befcc0',
			'label'     => __( 'General Info', 'mai-locations' ),
			'type'      => 'tab',
			'placement' => 'left',
		],
		'location_url' => [
			'key'   => 'field_5776a2650c6c7',
			'label' => __( 'Website URL', 'mai-locations' ),
			'type'  => 'url',
		],
		'location_phone' => [
			'key'   => 'field_5773cc5befcfe',
			'label' => __( 'Phone', 'mai-locations' ),
			'type'  => 'text',
		],
		'location_phone_2' => [
			'key'   => 'field_5773cc5befd0a',
			'label' => __( 'Secondary Phone', 'mai-locations' ),
			'type'  => 'text',
		],
		'location_email' => [
			'key'   => 'field_5773cc5befd24',
			'label' => __( 'Email', 'mai-locations' ),
			'type'  => 'email',
		],
	];

	$fields = apply_filters( 'mailocations_general_fields', $fields );

	return $fields;
}

/**
 * Gets address and map fields.
 *
 * @since 0.1.0
 *
 * @return array
 */
function mailocations_get_address_fields() {
	$fields = [
		'location_address_tab' => [
			'key'       => 'field_5773cc5befd31',
			'label'     => __( 'Address & Map', 'mai-locations' ),
			'type'      => 'tab',
			'placement' => 'left',
		],
		'address_country' => [
			'key'           => 'field_5773cc5befd87',
			'label'         => __( 'Country', 'mai-locations' ),
			'type'          => 'select',
			'default_value' => 'US',
			'choices'       => mailocations_get_country_choices(),
		],
		'address_street' => [
			'key'               => 'field_5773cc5befd4a',
			'label'             => __( 'Street', 'mai-locations' ),
			'type'              => 'text',
			'conditional_logic' => [
				[
					'field'    => 'field_5773cc5befd87',
					'operator' => '!=empty',
				],
			],
		],
		'address_street_2' => [
			'key'               => 'field_5773cc5befd56',
			'label'             => __( 'Street (2nd line)', 'mai-locations' ),
			'type'              => 'text',
			'conditional_logic' => [
				[
					'field'    => 'field_5773cc5befd87',
					'operator' => '!=empty',
				],
			],
		],
		'address_city' => [
			'key'     => 'field_5773cc5befd62',
			'label'   => __( 'City', 'mai-locations' ),
			'type'    => 'text',
			'wrapper' => [
				'width' => 50,
			],
			'conditional_logic' => [
				[
					'field'    => 'field_5773cc5befd87',
					'operator' => '!=empty',
				],
			],
		],
		'address_state' => [
			'key'     => 'field_5773cc5befd6f',
			'label'   => __( 'State', 'mai-locations' ),
			'type'    => 'select',
			'wrapper' => [
				'width' => 30,
			],
			'choices'           => mailocations_get_state_choices(),
			'conditional_logic' => [
				[
					'field'    => 'field_5773cc5befd87',
					'operator' => '!=empty',
				],
				[
					'field'    => 'field_5773cc5befd87',
					'operator' => '==',
					'value'    => 'US',
				],
			],
		],
		'address_state_int' => [
			'key'     => 'field_6883dd6cfgd7f',
			'label'   => __( 'State/Province', 'mai-locations' ),
			'type'    => 'text',
			'wrapper' => [
				'width' => 30,
			],
			'conditional_logic' => [
				[
					'field'    => 'field_5773cc5befd87',
					'operator' => '!=empty',
				],
				[
					'field'    => 'field_5773cc5befd87',
					'operator' => '!=',
					'value'    => 'US',
				],
			],
		],
		'address_postcode' => [
			'key'     => 'field_5773cc5befd7b',
			'label'   => __( 'Zipcode', 'mai-locations' ),
			'type'    => 'text',
			'wrapper' => [
				'width' => 20,
			],
			'conditional_logic' => [
				[
					'field'    => 'field_5773cc5befd87',
					'operator' => '!=empty',
				],
			],
		],
		'location' => [
			'key'        => 'field_5773cc5befd93',
			'label'      => __( 'Location', 'mai-locations' ),
			'type'       => 'google_map',
			'center_lat' => '38.500000',
			'center_lng' => '-98.000000',
			'zoom'       => 4,
			'height'     => '',
		],
	];

	$fields = apply_filters( 'mailocations_address_fields', $fields );

	return $fields;
}

/**
 * Gets social media fields.
 * TODO: These are pointless without any output.
 *
 * @since 0.1.0
 *
 * @return array
 */
function mailocations_get_social_fields() {
	return [];

	$fields = [
		'location_social_tab' => [
			'key'       => 'field_5773cc5befda0',
			'label'     => __( 'Social Media', 'mai-locations' ),
			'type'      => 'tab',
			'placement' => 'left',
		],
		'facebook' => [
			'key'          => 'field_5773cc5befdac',
			'label'        => 'Facebook',
			'type'         => 'url',
			'instructions' => __( 'Enter URL', 'mai-locations' ),
		],
		'twitter' => [
			'key'          => 'field_5773cc5befdb8',
			'label'        => 'Twitter',
			'type'         => 'text',
			'instructions' => __( 'Enter username without the @ symbol', 'mai-locations' ),
		],
		'youtube' => [
			'key'          => 'field_5773cc5befdd0',
			'label'        => 'YouTube',
			'type'         => 'url',
			'instructions' => __( 'Enter URL', 'mai-locations' ),
		],
		'linkedin' => [
			'key'          => 'field_5773cc5befddc',
			'label'        => 'LinkedIn',
			'type'         => 'url',
			'instructions' => __( 'Enter URL', 'mai-locations' ),
		],
		'instagram' => [
			'key'          => 'field_5773cc5befde8',
			'label'        => 'Instagram',
			'type'         => 'text',
			'instructions' => __( 'Enter username only', 'mai-locations' ),
		],
		'pinterest' => [
			'key'          => 'field_5773cc5befder',
			'label'        => 'Pinterest',
			'type'         => 'url',
			'instructions' => __( 'Enter URL', 'mai-locations' ),
		],
	];

	$fields = apply_filters( 'mailocations_social_fields', $fields );

	return $fields;
}

/**
 * Gets state field choices.
 *
 * @since 0.1.0
 *
 * @return array
 */
function mailocations_get_state_choices() {
	static $choices = null;

	if ( ! is_null( $choices ) ) {
		return $choices;
	}

	$choices = [
		''   => __( 'Choose a state', 'mai-locations' ),
		'AL' => __( 'Alabama', 'mai-locations' ),
		'AK' => __( 'Alaska', 'mai-locations' ),
		'AZ' => __( 'Arizona', 'mai-locations' ),
		'AR' => __( 'Arkansas', 'mai-locations' ),
		'CA' => __( 'California', 'mai-locations' ),
		'CO' => __( 'Colorado', 'mai-locations' ),
		'CT' => __( 'Connecticut', 'mai-locations' ),
		'DE' => __( 'Delaware', 'mai-locations' ),
		'DC' => __( 'District of Colombia', 'mai-locations' ),
		'FL' => __( 'Florida', 'mai-locations' ),
		'GA' => __( 'Georgia', 'mai-locations' ),
		'HI' => __( 'Hawaii', 'mai-locations' ),
		'ID' => __( 'Idaho', 'mai-locations' ),
		'IL' => __( 'Illinois', 'mai-locations' ),
		'IN' => __( 'Indiana', 'mai-locations' ),
		'IA' => __( 'Iowa', 'mai-locations' ),
		'KS' => __( 'Kansas', 'mai-locations' ),
		'KY' => __( 'Kentucky', 'mai-locations' ),
		'LA' => __( 'Louisiana', 'mai-locations' ),
		'ME' => __( 'Maine', 'mai-locations' ),
		'MD' => __( 'Maryland', 'mai-locations' ),
		'MA' => __( 'Massachusetts', 'mai-locations' ),
		'MI' => __( 'Michigan', 'mai-locations' ),
		'MN' => __( 'Minnesota', 'mai-locations' ),
		'MS' => __( 'Mississippi', 'mai-locations' ),
		'MO' => __( 'Missouri', 'mai-locations' ),
		'MT' => __( 'Montana', 'mai-locations' ),
		'NE' => __( 'Nebraska', 'mai-locations' ),
		'NV' => __( 'Nevada', 'mai-locations' ),
		'NH' => __( 'New Hampshire', 'mai-locations' ),
		'NJ' => __( 'New Jersey', 'mai-locations' ),
		'NM' => __( 'New Mexico', 'mai-locations' ),
		'NY' => __( 'New York', 'mai-locations' ),
		'NC' => __( 'North Carolina', 'mai-locations' ),
		'ND' => __( 'North Dakota', 'mai-locations' ),
		'OH' => __( 'Ohio', 'mai-locations' ),
		'OK' => __( 'Oklahoma', 'mai-locations' ),
		'OR' => __( 'Oregon', 'mai-locations' ),
		'PA' => __( 'Pennsylvania', 'mai-locations' ),
		'PR' => __( 'Puerto Rico', 'mai-locations' ),
		'RI' => __( 'Rhode Island', 'mai-locations' ),
		'SC' => __( 'South Carolina', 'mai-locations' ),
		'SD' => __( 'South Dakota', 'mai-locations' ),
		'TN' => __( 'Tennessee', 'mai-locations' ),
		'TX' => __( 'Texas', 'mai-locations' ),
		'UT' => __( 'Utah', 'mai-locations' ),
		'VT' => __( 'Vermont', 'mai-locations' ),
		'VA' => __( 'Virginia', 'mai-locations' ),
		'WA' => __( 'Washington', 'mai-locations' ),
		'WV' => __( 'West Virginia', 'mai-locations' ),
		'WI' => __( 'Wisconsin', 'mai-locations' ),
		'WY' => __( 'Wyoming', 'mai-locations' ),
	];

	return $choices;
}

/**
 * Gets country field choices.
 *
 * @since 0.1.0
 *
 * @return array
 */
function mailocations_get_country_choices() {
	static $choices = null;

	if ( ! is_null( $choices ) ) {
		return $choices;
	}

	$choices = [
		''   => __( 'Choose a country', 'mai-locations' ),
		'AX' => __( 'Åland Islands', 'mai-locations' ),
		'AF' => __( 'Afghanistan', 'mai-locations' ),
		'AL' => __( 'Albania', 'mai-locations' ),
		'DZ' => __( 'Algeria', 'mai-locations' ),
		'AD' => __( 'Andorra', 'mai-locations' ),
		'AO' => __( 'Angola', 'mai-locations' ),
		'AI' => __( 'Anguilla', 'mai-locations' ),
		'AQ' => __( 'Antarctica', 'mai-locations' ),
		'AG' => __( 'Antigua and Barbuda', 'mai-locations' ),
		'AR' => __( 'Argentina', 'mai-locations' ),
		'AM' => __( 'Armenia', 'mai-locations' ),
		'AW' => __( 'Aruba', 'mai-locations' ),
		'AU' => __( 'Australia', 'mai-locations' ),
		'AT' => __( 'Austria', 'mai-locations' ),
		'AZ' => __( 'Azerbaijan', 'mai-locations' ),
		'BS' => __( 'Bahamas', 'mai-locations' ),
		'BH' => __( 'Bahrain', 'mai-locations' ),
		'BD' => __( 'Bangladesh', 'mai-locations' ),
		'BB' => __( 'Barbados', 'mai-locations' ),
		'BY' => __( 'Belarus', 'mai-locations' ),
		'PW' => __( 'Belau', 'mai-locations' ),
		'BE' => __( 'Belgium', 'mai-locations' ),
		'BZ' => __( 'Belize', 'mai-locations' ),
		'BJ' => __( 'Benin', 'mai-locations' ),
		'BM' => __( 'Bermuda', 'mai-locations' ),
		'BT' => __( 'Bhutan', 'mai-locations' ),
		'BO' => __( 'Bolivia', 'mai-locations' ),
		'BQ' => __( 'Bonaire, Sint Eustatius and Saba', 'mai-locations' ),
		'BA' => __( 'Bosnia and Herzegovina', 'mai-locations' ),
		'BW' => __( 'Botswana', 'mai-locations' ),
		'BV' => __( 'Bouvet Island', 'mai-locations' ),
		'BR' => __( 'Brazil', 'mai-locations' ),
		'IO' => __( 'British Indian Ocean Territory', 'mai-locations' ),
		'VG' => __( 'British Virgin Islands', 'mai-locations' ),
		'BN' => __( 'Brunei', 'mai-locations' ),
		'BG' => __( 'Bulgaria', 'mai-locations' ),
		'BF' => __( 'Burkina Faso', 'mai-locations' ),
		'BI' => __( 'Burundi', 'mai-locations' ),
		'KH' => __( 'Cambodia', 'mai-locations' ),
		'CM' => __( 'Cameroon', 'mai-locations' ),
		'CA' => __( 'Canada', 'mai-locations' ),
		'CV' => __( 'Cape Verde', 'mai-locations' ),
		'KY' => __( 'Cayman Islands', 'mai-locations' ),
		'CF' => __( 'Central African Republic', 'mai-locations' ),
		'TD' => __( 'Chad', 'mai-locations' ),
		'CL' => __( 'Chile', 'mai-locations' ),
		'CN' => __( 'China', 'mai-locations' ),
		'CX' => __( 'Christmas Island', 'mai-locations' ),
		'CC' => __( 'Cocos (Keeling) Islands', 'mai-locations' ),
		'CO' => __( 'Colombia', 'mai-locations' ),
		'KM' => __( 'Comoros', 'mai-locations' ),
		'CG' => __( 'Congo (Brazzaville)', 'mai-locations' ),
		'CD' => __( 'Congo (Kinshasa)', 'mai-locations' ),
		'CK' => __( 'Cook Islands', 'mai-locations' ),
		'CR' => __( 'Costa Rica', 'mai-locations' ),
		'HR' => __( 'Croatia', 'mai-locations' ),
		'CU' => __( 'Cuba', 'mai-locations' ),
		'CW' => __( 'Curaçao', 'mai-locations' ),
		'CY' => __( 'Cyprus', 'mai-locations' ),
		'CZ' => __( 'Czech Republic', 'mai-locations' ),
		'DK' => __( 'Denmark', 'mai-locations' ),
		'DJ' => __( 'Djibouti', 'mai-locations' ),
		'DM' => __( 'Dominica', 'mai-locations' ),
		'DO' => __( 'Dominican Republic', 'mai-locations' ),
		'EC' => __( 'Ecuador', 'mai-locations' ),
		'EG' => __( 'Egypt', 'mai-locations' ),
		'SV' => __( 'El Salvador', 'mai-locations' ),
		'GQ' => __( 'Equatorial Guinea', 'mai-locations' ),
		'ER' => __( 'Eritrea', 'mai-locations' ),
		'EE' => __( 'Estonia', 'mai-locations' ),
		'ET' => __( 'Ethiopia', 'mai-locations' ),
		'FK' => __( 'Falkland Islands', 'mai-locations' ),
		'FO' => __( 'Faroe Islands', 'mai-locations' ),
		'FJ' => __( 'Fiji', 'mai-locations' ),
		'FI' => __( 'Finland', 'mai-locations' ),
		'FR' => __( 'France', 'mai-locations' ),
		'GF' => __( 'French Guiana', 'mai-locations' ),
		'PF' => __( 'French Polynesia', 'mai-locations' ),
		'TF' => __( 'French Southern Territories', 'mai-locations' ),
		'GA' => __( 'Gabon', 'mai-locations' ),
		'GM' => __( 'Gambia', 'mai-locations' ),
		'GE' => __( 'Georgia', 'mai-locations' ),
		'DE' => __( 'Germany', 'mai-locations' ),
		'GH' => __( 'Ghana', 'mai-locations' ),
		'GI' => __( 'Gibraltar', 'mai-locations' ),
		'GR' => __( 'Greece', 'mai-locations' ),
		'GL' => __( 'Greenland', 'mai-locations' ),
		'GD' => __( 'Grenada', 'mai-locations' ),
		'GP' => __( 'Guadeloupe', 'mai-locations' ),
		'GT' => __( 'Guatemala', 'mai-locations' ),
		'GG' => __( 'Guernsey', 'mai-locations' ),
		'GN' => __( 'Guinea', 'mai-locations' ),
		'GW' => __( 'Guinea-Bissau', 'mai-locations' ),
		'GY' => __( 'Guyana', 'mai-locations' ),
		'HT' => __( 'Haiti', 'mai-locations' ),
		'HM' => __( 'Heard Island and McDonald Islands', 'mai-locations' ),
		'HN' => __( 'Honduras', 'mai-locations' ),
		'HK' => __( 'Hong Kong', 'mai-locations' ),
		'HU' => __( 'Hungary', 'mai-locations' ),
		'IS' => __( 'Iceland', 'mai-locations' ),
		'IN' => __( 'India', 'mai-locations' ),
		'ID' => __( 'Indonesia', 'mai-locations' ),
		'IR' => __( 'Iran', 'mai-locations' ),
		'IQ' => __( 'Iraq', 'mai-locations' ),
		'IM' => __( 'Isle of Man', 'mai-locations' ),
		'IL' => __( 'Israel', 'mai-locations' ),
		'IT' => __( 'Italy', 'mai-locations' ),
		'CI' => __( 'Ivory Coast', 'mai-locations' ),
		'JM' => __( 'Jamaica', 'mai-locations' ),
		'JP' => __( 'Japan', 'mai-locations' ),
		'JE' => __( 'Jersey', 'mai-locations' ),
		'JO' => __( 'Jordan', 'mai-locations' ),
		'KZ' => __( 'Kazakhstan', 'mai-locations' ),
		'KE' => __( 'Kenya', 'mai-locations' ),
		'KI' => __( 'Kiribati', 'mai-locations' ),
		'KW' => __( 'Kuwait', 'mai-locations' ),
		'KG' => __( 'Kyrgyzstan', 'mai-locations' ),
		'LA' => __( 'Laos', 'mai-locations' ),
		'LV' => __( 'Latvia', 'mai-locations' ),
		'LB' => __( 'Lebanon', 'mai-locations' ),
		'LS' => __( 'Lesotho', 'mai-locations' ),
		'LR' => __( 'Liberia', 'mai-locations' ),
		'LY' => __( 'Libya', 'mai-locations' ),
		'LI' => __( 'Liechtenstein', 'mai-locations' ),
		'LT' => __( 'Lithuania', 'mai-locations' ),
		'LU' => __( 'Luxembourg', 'mai-locations' ),
		'MO' => __( 'Macao S.A.R., China', 'mai-locations' ),
		'MK' => __( 'Macedonia', 'mai-locations' ),
		'MG' => __( 'Madagascar', 'mai-locations' ),
		'MW' => __( 'Malawi', 'mai-locations' ),
		'MY' => __( 'Malaysia', 'mai-locations' ),
		'MV' => __( 'Maldives', 'mai-locations' ),
		'ML' => __( 'Mali', 'mai-locations' ),
		'MT' => __( 'Malta', 'mai-locations' ),
		'MH' => __( 'Marshall Islands', 'mai-locations' ),
		'MQ' => __( 'Martinique', 'mai-locations' ),
		'MR' => __( 'Mauritania', 'mai-locations' ),
		'MU' => __( 'Mauritius', 'mai-locations' ),
		'YT' => __( 'Mayotte', 'mai-locations' ),
		'MX' => __( 'Mexico', 'mai-locations' ),
		'FM' => __( 'Micronesia', 'mai-locations' ),
		'MD' => __( 'Moldova', 'mai-locations' ),
		'MC' => __( 'Monaco', 'mai-locations' ),
		'MN' => __( 'Mongolia', 'mai-locations' ),
		'ME' => __( 'Montenegro', 'mai-locations' ),
		'MS' => __( 'Montserrat', 'mai-locations' ),
		'MA' => __( 'Morocco', 'mai-locations' ),
		'MZ' => __( 'Mozambique', 'mai-locations' ),
		'MM' => __( 'Myanmar', 'mai-locations' ),
		'NA' => __( 'Namibia', 'mai-locations' ),
		'NR' => __( 'Nauru', 'mai-locations' ),
		'NP' => __( 'Nepal', 'mai-locations' ),
		'NL' => __( 'Netherlands', 'mai-locations' ),
		'AN' => __( 'Netherlands Antilles', 'mai-locations' ),
		'NC' => __( 'New Caledonia', 'mai-locations' ),
		'NZ' => __( 'New Zealand', 'mai-locations' ),
		'NI' => __( 'Nicaragua', 'mai-locations' ),
		'NE' => __( 'Niger', 'mai-locations' ),
		'NG' => __( 'Nigeria', 'mai-locations' ),
		'NU' => __( 'Niue', 'mai-locations' ),
		'NF' => __( 'Norfolk Island', 'mai-locations' ),
		'KP' => __( 'North Korea', 'mai-locations' ),
		'NO' => __( 'Norway', 'mai-locations' ),
		'OM' => __( 'Oman', 'mai-locations' ),
		'PK' => __( 'Pakistan', 'mai-locations' ),
		'PS' => __( 'Palestinian Territory', 'mai-locations' ),
		'PA' => __( 'Panama', 'mai-locations' ),
		'PG' => __( 'Papua New Guinea', 'mai-locations' ),
		'PY' => __( 'Paraguay', 'mai-locations' ),
		'PE' => __( 'Peru', 'mai-locations' ),
		'PH' => __( 'Philippines', 'mai-locations' ),
		'PN' => __( 'Pitcairn', 'mai-locations' ),
		'PL' => __( 'Poland', 'mai-locations' ),
		'PT' => __( 'Portugal', 'mai-locations' ),
		'QA' => __( 'Qatar', 'mai-locations' ),
		'IE' => __( 'Republic of Ireland', 'mai-locations' ),
		'RE' => __( 'Reunion', 'mai-locations' ),
		'RO' => __( 'Romania', 'mai-locations' ),
		'RU' => __( 'Russia', 'mai-locations' ),
		'RW' => __( 'Rwanda', 'mai-locations' ),
		'ST' => __( 'São Tomé and Príncipe', 'mai-locations' ),
		'BL' => __( 'Saint Barthélemy', 'mai-locations' ),
		'SH' => __( 'Saint Helena', 'mai-locations' ),
		'KN' => __( 'Saint Kitts and Nevis', 'mai-locations' ),
		'LC' => __( 'Saint Lucia', 'mai-locations' ),
		'SX' => __( 'Saint Martin (Dutch part)', 'mai-locations' ),
		'MF' => __( 'Saint Martin (French part)', 'mai-locations' ),
		'PM' => __( 'Saint Pierre and Miquelon', 'mai-locations' ),
		'VC' => __( 'Saint Vincent and the Grenadines', 'mai-locations' ),
		'SM' => __( 'San Marino', 'mai-locations' ),
		'SA' => __( 'Saudi Arabia', 'mai-locations' ),
		'SN' => __( 'Senegal', 'mai-locations' ),
		'RS' => __( 'Serbia', 'mai-locations' ),
		'SC' => __( 'Seychelles', 'mai-locations' ),
		'SL' => __( 'Sierra Leone', 'mai-locations' ),
		'SG' => __( 'Singapore', 'mai-locations' ),
		'SK' => __( 'Slovakia', 'mai-locations' ),
		'SI' => __( 'Slovenia', 'mai-locations' ),
		'SB' => __( 'Solomon Islands', 'mai-locations' ),
		'SO' => __( 'Somalia', 'mai-locations' ),
		'ZA' => __( 'South Africa', 'mai-locations' ),
		'GS' => __( 'South Georgia/Sandwich Islands', 'mai-locations' ),
		'KR' => __( 'South Korea', 'mai-locations' ),
		'SS' => __( 'South Sudan', 'mai-locations' ),
		'ES' => __( 'Spain', 'mai-locations' ),
		'LK' => __( 'Sri Lanka', 'mai-locations' ),
		'SD' => __( 'Sudan', 'mai-locations' ),
		'SR' => __( 'Suriname', 'mai-locations' ),
		'SJ' => __( 'Svalbard and Jan Mayen', 'mai-locations' ),
		'SZ' => __( 'Swaziland', 'mai-locations' ),
		'SE' => __( 'Sweden', 'mai-locations' ),
		'CH' => __( 'Switzerland', 'mai-locations' ),
		'SY' => __( 'Syria', 'mai-locations' ),
		'TW' => __( 'Taiwan', 'mai-locations' ),
		'TJ' => __( 'Tajikistan', 'mai-locations' ),
		'TZ' => __( 'Tanzania', 'mai-locations' ),
		'TH' => __( 'Thailand', 'mai-locations' ),
		'TL' => __( 'Timor-Leste', 'mai-locations' ),
		'TG' => __( 'Togo', 'mai-locations' ),
		'TK' => __( 'Tokelau', 'mai-locations' ),
		'TO' => __( 'Tonga', 'mai-locations' ),
		'TT' => __( 'Trinidad and Tobago', 'mai-locations' ),
		'TN' => __( 'Tunisia', 'mai-locations' ),
		'TR' => __( 'Turkey', 'mai-locations' ),
		'TM' => __( 'Turkmenistan', 'mai-locations' ),
		'TC' => __( 'Turks and Caicos Islands', 'mai-locations' ),
		'TV' => __( 'Tuvalu', 'mai-locations' ),
		'UG' => __( 'Uganda', 'mai-locations' ),
		'UA' => __( 'Ukraine', 'mai-locations' ),
		'AE' => __( 'United Arab Emirates', 'mai-locations' ),
		'GB' => __( 'United Kingdom', 'mai-locations' ),
		'US' => __( 'United States', 'mai-locations' ),
		'UY' => __( 'Uruguay', 'mai-locations' ),
		'UZ' => __( 'Uzbekistan', 'mai-locations' ),
		'VU' => __( 'Vanuatu', 'mai-locations' ),
		'VA' => __( 'Vatican', 'mai-locations' ),
		'VE' => __( 'Venezuela', 'mai-locations' ),
		'VN' => __( 'Vietnam', 'mai-locations' ),
		'WF' => __( 'Wallis and Futuna', 'mai-locations' ),
		'EH' => __( 'Western Sahara', 'mai-locations' ),
		'WS' => __( 'Western Samoa', 'mai-locations' ),
		'YE' => __( 'Yemen', 'mai-locations' ),
		'ZM' => __( 'Zambia', 'mai-locations' ),
		'ZW' => __( 'Zimbabwe', 'mai-locations' ),
	];

	return $choices;
}
