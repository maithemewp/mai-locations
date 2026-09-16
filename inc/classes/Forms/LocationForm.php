<?php

declare(strict_types=1);

namespace Mai\Locations\Forms;

use WP_HTML_Tag_Processor;

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * Shared wrapper for the front-end location forms. The child classes build the form itself.
 *
 * Was Mai_Locations_Location_Form in classes/class-location-form.php. That name still works,
 * via inc/aliases.php.
 *
 * @since TBD
 */
class LocationForm {

	/**
	 * The form args.
	 *
	 * @var array<string, mixed>
	 */
	protected $args;

	/**
	 * Construct the class.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $args The args.
	 */
	public function __construct( $args ) {
		$args = wp_parse_args( $args,
			[
				'location_id' => 0,
				'fields'      => [],
				'post_type'   => 'mai_location',
				'status'      => 'pending',
				'redirect'    => '',
				'emails'      => '',
				'class'       => '',
				'preview'     => false,
			]
		);

		// Sanitize.
		$args['location_id'] = (int) $args['location_id'];
		$args['fields']      = (array) $args['fields'];
		$args['post_type']   = sanitize_key( $args['post_type'] );
		$args['status']      = sanitize_key( $args['status'] );
		$args['redirect']    = $args['redirect'] ? esc_url( $args['redirect'] ) : '';
		$args['emails']      = $args['emails'] ? sanitize_text_field( $args['emails'] ) : '';
		$args['class']       = sanitize_text_field( $args['class'] );
		$args['preview']     = rest_sanitize_boolean( $args['preview'] );

		// Assign.
		$this->args = $args;
	}

	/**
	 * Removes conditional logic from a field.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function remove_conditions( $field ) {
		$field['conditional_logic'] = 0;

		return $field;
	}

	/**
	 * Gets the wrapped form.
	 *
	 * TODO: returns null, not '', when there are no fields, and a custom class runs into the
	 * default one because trim() removes the space that was just added. See TODO.md.
	 *
	 * @since TBD
	 *
	 * @return string|null
	 */
	public function get() {
		// Bail if no fields.
		if ( ! $this->args['fields'] ) {
			return;
		}

		// Maybe add filter to remove state field conditions.
		if ( ! in_array( 'mai_location_address_country', $this->args['fields'] ) ) {
			add_filter( 'acf/prepare_field/key=mai_address_state',     [ $this, 'remove_conditions' ] );
			add_filter( 'acf/prepare_field/key=mai_address_state_int', [ $this, 'remove_conditions' ] );
		}

		// Enqueue styles.
		wp_enqueue_style( 'mai-locations-form' );

		// Start HTML.
		$html  = '';
		$class = 'mailocations-form';

		// Add class. The trim() took the separating space with it, so a custom class ran into the
		// default one. Fixed September 16, 2026.
		if ( $this->args['class'] ) {
			$class .= ' ' . esc_attr( trim( (string) $this->args['class'] ) );
		}

		// Open form wrapper.
		$html .= sprintf( '<div class="%s">', $class );
			// Get form.
			$form = $this->get_form();

			// Mai Engine.
			if ( class_exists( 'Mai_Engine' ) && $form ) {
				// Set up tag processor.
				$tags = new WP_HTML_Tag_Processor( $form );

				// Loop through buttons.
				while ( $tags->next_tag( [ 'tag_name' => 'a', 'class_name' => 'acf-button' ] ) ) {
					$tags->remove_class( 'button-primary' );
					$tags->add_class( 'button-secondary' );
					$tags->add_class( 'button-small' );
				}

				// Update form.
				$form = $tags->get_updated_html();

				// Set up tag processor.
				$tags = new WP_HTML_Tag_Processor( $form );

				// Loop through buttons.
				while ( $tags->next_tag( [ 'tag_name' => 'input', 'class_name' => 'acf-button' ] ) ) {
					$tags->remove_class( 'button-primary' );
					$tags->remove_class( 'button-large' );
				}

				// Update form.
				$form = $tags->get_updated_html();
			}

			// Apply filters and add form.
			$html .= apply_filters( 'mailocations_location_acf_form', $form, $this->args );

		// Close form wrapper.
		$html .= '</div>';

		// Maybe remove filter so state field conditions return as normal.
		if ( ! in_array( 'mai_location_address_country', $this->args['fields'] ) ) {
			remove_filter( 'acf/prepare_field/key=mai_address_state',     [ $this, 'remove_conditions' ] );
			remove_filter( 'acf/prepare_field/key=mai_address_state_int', [ $this, 'remove_conditions' ] );
		}

		// Apply filters.
		$html = apply_filters( 'mailocations_location_form', $html, $this->args );

		return $html;
	}

	/**
	 * Gets the form itself. Implemented by the child classes.
	 *
	 * @since TBD
	 *
	 * @return string
	 */
	public function get_form() {
		return '';
	}
}
