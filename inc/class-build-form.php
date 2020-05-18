<?php
/**
 * Manage fallback forms functionality
 *
 * @package GF_Fallback\Inc
 */

namespace GF_Fallback\Inc;

/**
 * Build_Form class
 */
class Build_Form {
	/**
	 * Invalid fields (field non grata)
	 *
	 * @var array
	 */
	private $invalid_fields = [
		'nu_honey',
		'html',
	];

	/**
	 * Fields classified as checkboxes
	 *
	 * @var array
	 */
	private $checkbox_fields = [
		'checkbox',
		'military',
		'gdpr',
		'consent',
	];

	/**
	 * Fields classified as select/dropdown
	 *
	 * @var array
	 */
	private $select_fields = [
		'select',
		'degree',
		'program',
		'country-code',
	];

	/**
	 * Store our form markup
	 *
	 * @var string
	 */
	private $form_markup = '';

	/**
	 * Using construct method to add any actions and filters
	 *
	 * @param array $form All the data for building the backup form encapsulated in an array.
	 */
	public function __construct( $form ) {
		if ( empty( $form ) || empty( $form['fields'] ) ) {
			return;
		}

		$this->form = $form;

		$this->build_form();
	}

	/**
	 * Build the FULL markup for the form.
	 *
	 * @return void
	 */
	private function build_form() {
		ob_start();
		?>
		<form class="form form--fallback consent__below-submit d-none" id="gform_<?php echo esc_attr( $this->form['gform_id'] ); ?>_fallback" action="" method="">
			<div class="form__message validation_error"></div>
			<div class="gform_fields">
				<?php
				foreach ( $this->form['fields'] as $field ) :
					if ( in_array( $field['type'], $this->invalid_fields, true ) ) {
						continue;
					}

					$input_id      = 'fb_input_' . $this->form['gform_id'] . '_' . $field['id'];
					$input_type    = ! in_array( $field['type'], $this->checkbox_fields, true ) ? $field['type'] : 'checkbox';
					$input_classes = implode( ' ', $this->get_input_classes( $field ) );

					if ( 'consent' === $field['type'] && ! empty( $field['privacy_policy'] ) ) :
						$privacy_policy = sprintf(
							'<div class="gfield_description">%s</div>',
							$field['privacy_policy']
						);
					endif;

					if ( 'hidden' !== $field['type'] ) {
						?>
						<div class="<?php echo esc_attr( implode( ' ', $this->get_input_wrapper_classes( $field ) ) ); ?>">
						<?php
					}

					$this->echo_label( $field, $input_id );

					if ( in_array( $field['type'], $this->select_fields, true ) ) {
						$required_attributes = $field['required'] ? ' required aria-required="true"' : ' aria-required="false"';

						printf(
							'<select name="%s" id="%s" class="%s" %s %s>
								<option value="" label=" " selected disabled></option>
								%s
							</select>',
							esc_attr( $input_id ),
							esc_attr( $input_id ),
							esc_attr( $input_classes ),
							$required_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							$this->get_data_keys_attribute( $field ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							$this->get_choices( $field ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						);

						if ( 'program' === $field['type'] ) {
							$programs = $this->get_programs_data();
							echo '<input type="hidden" name="programs-data" id="programs-data" value="' . esc_js( $programs ) . '">';
						}
					} else {
						$placeholder = 'hidden' !== $field['type'] && ! empty( $field['placeholder'] ) ? ' placeholder="' . $field['placeholder'] . '"' : '';
						printf(
							'<input id="%s" name="%s" class="%s" type="%s" value="%s"%s%s %s>',
							esc_attr( $input_id ),
							esc_attr( $input_id ),
							esc_attr( $input_classes ),
							esc_attr( $input_type ),
							esc_attr( $field['default_value'] ),
							$field['required'] ? ' required' : '',
							$placeholder,
							$this->get_data_keys_attribute( $field ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						);
					}

					$this->echo_description( $field, $input_id );

					if ( 'hidden' !== $field['type'] ) {
						?>
						</div><!-- field wrapper div -->
						<?php
					}
				endforeach;
				?>
			</div><!-- .gform_fields -->

			<?php if ( ! empty( $this->form['feeds'] ) ) : ?>
				<div class="hooks">
					<?php
					foreach ( $this->form['feeds'] as $feed ) :
						$input_markup = sprintf(
							'<input type="hidden" name="feeds" data-feed-name="%s" data-feed-action="%s" data-feed-method="%s" data-feed-format="%s">',
							esc_attr( $feed['name'] ),
							esc_attr( $feed['action'] ),
							esc_attr( $feed['method'] ),
							esc_attr( $feed['format'] )
						);

						if ( 'eloqua' === $feed['name'] ) {
							if ( ! empty( $feed['elqsiteid'] ) ) {
								$input_markup = str_replace( 'data-feed-name', 'data-elqsiteid="' . $feed['elqsiteid'] . '" data-feed-name', $input_markup );
							}
							if ( ! empty( $feed['elqformname'] ) ) {
								$input_markup = str_replace( 'data-feed-name', 'data-elqformname="' . $feed['elqformname'] . '" data-feed-name', $input_markup );
							}
						}

						echo $input_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					endforeach;
					?>
				</div>
			<?php endif; ?>

			<?php
			if ( ! empty( $this->form['confirmation'] ) ) {
				if ( 'page' === $this->form['confirmation']['type'] ) {
					// Remove any dynamically populated parameters since we won't have access to them.
					$query_parameters = explode( '&', $this->form['confirmation']['queryString'] );
					$query_parameters = array_filter( $query_parameters, function( $parameter ) {
						return ! preg_match( '/[{}]/', $parameter );
					} );

					$page_url  = get_page_link( $this->form['confirmation']['pageId'] );
					$page_url .= ! empty( $query_parameters ) ? '?' . implode( '&', $query_parameters ) : '';

					printf(
						'<input type="hidden" name="confirmation" data-type="page" data-url="%s">',
						esc_url( $page_url )
					);
				} elseif ( 'message' === $this->form['confirmation']['type'] ) {
					printf(
						'<input type="hidden" name="confirmation" data-type="message" data-message="%s">',
						esc_attr( $this->form['confirmation']['message'] )
					);
				} elseif ( 'redirect' === $this->form['confirmation']['type'] ) {
					printf(
						'<input type="hidden" name="confirmation" data-type="redirect" data-url="%s">',
						esc_url( $this->form['confirmation']['url'] )
					);
				}
			}
			?>

			<input type="submit" value="Request Info" class="btn btn--bg-gold btn--navy icon icon--arrow-right icon--margin-left">

			<?php
			if ( ! empty( $privacy_policy ) ) {
				echo wp_kses_post( $privacy_policy );
			}
			?>
		</form>
		<?php
		$this->form_markup = ob_get_clean();
	}

	/**
	 * Allow fetch/get of the actual markup.
	 *
	 * @return string
	 */
	public function get_form_markup() {
		return $this->form_markup;
	}

	/**
	 * Utility function to get the input wrappers' classes in array format.
	 * Filter provided for theme modification.
	 *
	 * @param array $field Backup field being generated.
	 *
	 * @return array
	 */
	private function get_input_wrapper_classes( $field ) {
		$classes = [];

		if ( 'hidden' !== $field['type'] ) {
			$classes[] = 'form__group';
			$classes[] = 'form__group--' . $field['type'];
		}

		if ( ! empty( $field['css_class'] ) ) {
			$classes[] = $field['css_class'];
		}

		if ( ! empty( $field['choices'] ) ) {
			$has_selected_value = array_filter( array_column( $field['choices'], 'isSelected' ) );
			if ( ! empty( $has_selected_value ) ) {
				$classes[] = 'form__group--active';
			}
		}

		return apply_filters( 'gf_fallback_input_wrapper_classes', $classes, $field );
	}

	/**
	 * Echo the label for the field
	 *
	 * @param array  $field    Backup field being generated.
	 * @param string $input_id Input ID and name.
	 *
	 * @return void
	 */
	private function echo_label( $field, $input_id ) {
		if ( ! empty( $field['label'] ) && ! in_array( $field['type'], $this->checkbox_fields, true ) ) {
			?>
			<label class="form__label" for="<?php echo esc_attr( $input_id ); ?>">
				<?php
				echo esc_html( $field['label'] );

				if ( ! empty( $field['required'] ) ) {
					echo '<span class="required-label">*</span>';
				}
				?>
			</label>
			<?php
		}
	}

	/**
	 * Generate ALL the data-keys in the field based on hooks/feeds
	 *
	 * @param array $field Backup field being generated.
	 *
	 * @return string
	 */
	private function get_data_keys_attribute( $field ) {
		$data_keys = array_filter( $field, function( $value, $key ) {
			return 0 === strpos( $key, 'data-key-' );
		}, ARRAY_FILTER_USE_BOTH );

		$data_keys_attribute = '';
		if ( ! empty( $data_keys ) ) {
			foreach ( $data_keys as $data_key => $data_value ) {
				$data_keys_attribute = ' ' . $data_key . '="' . $data_value . '"';
			}
		}

		return $data_keys_attribute;
	}

	/**
	 * Echo the description for the field
	 *
	 * @param array  $field    Backup field being generated.
	 * @param string $input_id Input ID and name.
	 *
	 * @return void
	 */
	private function echo_description( $field, $input_id ) {
		if ( ! empty( $field['description'] ) ) {
			if ( in_array( $field['type'], $this->checkbox_fields, true ) ) {
				printf(
					'<label for="%s" class="m-0">%s</label>',
					esc_attr( $input_id ),
					wp_kses_post( $field['description'] )
				);
				if ( ! empty( $field['required'] ) ) {
					echo '<span class="required-label">*</span>';
				}
			} else {
				printf(
					'<span class="form__description">%s</span>',
					wp_kses_post( $field['description'] )
				);
			}
		}
	}

	/**
	 * Utility function to get the input's classes in array format.
	 * Filter provided for theme modification.
	 *
	 * @param array $field Backup field being generated.
	 *
	 * @return array
	 */
	private function get_input_classes( $field ) {
		$classes = [
			'input',
			'input--styled',
			'input--' . trim( $field['type'] ),
		];

		if ( ! empty( $field['input_name'] ) ) {
			$classes[] = $field['input_name'];
		}

		// All fields get "col-12" class except checkboxes.
		if ( in_array( $field['type'], $this->checkbox_fields, true ) ) {
			$classes[] = 'input--checkbox';
		} else {
			$classes[] = 'col-12';
		}

		if ( in_array( $field['type'], $this->select_fields, true ) ) {
			$classes[] = 'input--select';
		}

		return apply_filters( 'gf_fallback_input_classes', $classes, $field );
	}

	/**
	 * Utility function to get a dropdown/radio field's choices and to keep things organized
	 *
	 * @param array $field Backup field being generated.
	 *
	 * @return string
	 */
	private function get_choices( $field ) {
		$choices = '';

		foreach ( $field['choices'] as $choice ) {
			$choices .= sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $choice['value'] ),
				! empty( $choice['isSelected'] ) ? ' selected' : '',
				esc_html( $choice['text'] )
			);
		}

		return $choices;
	}

	/**
	 * Utility function to fetch programs in JSON format to use in a hidden field for JS use.
	 *
	 * @return json_string
	 */
	private function get_programs_data() {
		$programs_data = get_transient( 'fallback_programs_data' );
		if ( ! empty( $programs_data ) ) {
			return $programs_data;
		}

		$programs_query_args = [
			'order'                  => 'ASC',
			'orderby'                => 'title',
			'post_type'              => 'program',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];
		$programs_query      = new \WP_Query( $programs_query_args );

		wp_reset_postdata();

		$programs_data = [];

		if ( ! empty( $programs_query->posts ) ) {
			foreach ( $programs_query->posts as $program ) {
				$teachout = get_post_meta( $program->ID, 'teachout', true );
				if ( 'yes' !== $teachout ) {
					$degree_types = wp_get_post_terms(
						$program->ID,
						'degree-type',
						[
							'fields' => 'id=>name',
						]
					);

					foreach ( $degree_types as $id => $title ) {
						if ( ! isset( $programs_data[ $title ] ) ) {
							$programs_data[ $title ] = [
								'id'       => $id,
								'title'    => $title,
								'programs' => [],
							];
						}

						$programs_data[ $title ]['programs'][ $program->ID ] = [
							'ID'    => $program->ID,
							'slug'  => $program->post_name,
							'title' => $program->post_title,
						];
					}
				}
			}
		}

		$programs_data = wp_json_encode( $programs_data );

		set_transient( 'fallback_programs_data', $programs_data, HOUR_IN_SECONDS );

		return $programs_data;
	}
}
