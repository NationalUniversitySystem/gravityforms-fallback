<?php
/**
 * Manage fallback forms functionality
 *
 * @package GF_Fallback\Inc
 */

namespace GF_Fallback\Inc;

/**
 * Eloqua API class
 */
class Fallback_Forms {
	/**
	 * Instance of this class
	 *
	 * @var boolean
	 */
	public static $instance = false;

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
	 * Using construct method to add any actions and filters
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 99 );
		add_filter( 'gform_get_form_filter', [ $this, 'add_fallback_form' ], 10, 2 );
		add_action( 'rest_api_init', [ $this, 'register_endpoint' ] );
	}

	/**
	 * Singleton
	 *
	 * Returns a single instance of this class.
	 */
	public static function singleton() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Enqueue Assets
	 *
	 * Enqueues the necessary css and js files.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'gravityforms-fallback', GF_FALLBACK_URL . 'assets/js/main.min.js', [], filemtime( GF_FALLBACK_PATH . 'assets/js/main.min.js' ), true );
	}

	/**
	 * Add fallback forms to the Gravity Forms that have webhooks
	 *
	 * @param string $form_string The full markup of a Gravity Form.
	 * @param array  $form        Array that holds the data for the corresponding Gravity Form.
	 *
	 * @return string
	 */
	public function add_fallback_form( $form_string, $form ) {
		if ( ! $form_string || empty( $form['id'] ) || ! function_exists( 'gf_webhooks' ) ) {
			return $form_string;
		}

		$eloqua_site_id   = null;
		$eloqua_form_name = null;
		$backup_form      = [
			'gform_id' => $form['id'],
			'fields'   => [],
		];

		if ( is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				if ( 'administrative' === $field->visibility ) {
					continue;
				}

				// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$backup_form['fields'][ $field->id ] = [
					'id'          => $field->id,
					'type'        => $field->type,
					'label'       => 'hidden' !== $field->type ? $field->label : '',
					'input_name'  => $field->inputName,
					'description' => $field->description,
					'css_class'   => $field->cssClass,
					'choices'     => ! empty( $field->choices ) ? $field->choices : [],
					'required'    => $field->isRequired,
				];

				if ( in_array( $field->type, [ 'military', 'gdpr', 'consent' ], true ) ) {
					switch ( $field->type ) {
						case 'military':
						case 'gdpr':
							$backup_form['fields'][ $field->id ]['description'] = $field->choices[0]['text'];
							break;
						case 'consent':
							$backup_form['fields'][ $field->id ]['description']    = $field->checkboxLabel;
							$backup_form['fields'][ $field->id ]['privacy_policy'] = $field->description;
							break;
						default:
							break;
					}
				}

				if ( 'elqsiteid' === strtolower( $field->label ) && ! empty( $field->defaultValue ) ) {
					$eloqua_site_id = $field->defaultValue;
				}

				if ( 'elqformname' === strtolower( $field->label ) && ! empty( $field->defaultValue ) ) {
					$eloqua_form_name = $field->defaultValue;
				}
				// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}
		}

		$feeds = \GFAPI::get_feeds( null, $form['id'], 'gravityformswebhooks' );
		if ( ! empty( $feeds ) ) {
			foreach ( $feeds as $feed ) {
				if ( 'select_fields' === $feed['meta']['requestBodyType'] ) {
					// Set up each feed's main components and map it to the actual form fields.
					$feed_slug = str_replace( ' ', '-', strtolower( $feed['meta']['feedName'] ) );

					$backup_form['feeds'][ $feed_slug ] = [
						'name'   => $feed_slug,
						'action' => $feed['meta']['requestURL'],
						'method' => $feed['meta']['requestMethod'],
						'format' => $feed['meta']['requestFormat'],
						'fields' => wp_list_pluck( $feed['meta']['fieldValues'], 'custom_key', 'value' ),
					];

					if ( 'eloqua' === $feed_slug ) {
						$backup_form['feeds'][ $feed_slug ]['elqsiteid']   = $eloqua_site_id;
						$backup_form['feeds'][ $feed_slug ]['elqformname'] = $eloqua_form_name;
					}

					foreach ( $feed['meta']['fieldValues'] as $field ) {
						if ( array_key_exists( $field['value'], $backup_form['fields'] ) ) {
							$backup_form['fields'][ $field['value'] ][ 'data-key-' . $feed_slug ] = $field['custom_key'];
						}
					}
				}
			}
		}

		$backup_form_markup = apply_filters( 'gf_fallback_form_markup', $this->build_backup_form( $backup_form ) );

		return $form_string . $backup_form_markup;
	}

	/**
	 * Register a faux endpoint to ping and check for the server.
	 * This endpoint needs to be added to the cache bypass of Cloudflare or whatever cache system is in use.
	 *
	 * @return void
	 */
	public function register_endpoint() {
		$namespace = 'gravityforms-fallback/v' . GF_FALLBACK_VERSION;

		register_rest_route( $namespace, '/faux', [
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => [ $this, 'get_posts' ],
			'args'     => [],
		] );
	}

	/**
	 * Faux method to return some nonimportant data.
	 * In this case it's the IDs of the latest 3 pages.
	 *
	 * @return mixed
	 */
	public function get_posts() {
		$posts_query = new \WP_Query( [
			'post_type'              => 'page',
			'posts_per_page'         => 3,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,

		] );

		if ( ! $posts_query->have_posts() ) {
			return new \WP_Error( 'empty_posts', 'There are no posts', [ 'status' => 404 ] );
		}

		return new \WP_REST_Response( $posts_query->posts, 200 );
	}

	/**
	 * Build the actual markup and return it to be attached.
	 *
	 * @param array $backup_form The build array for the backup form.
	 *
	 * @return string
	 */
	private function build_backup_form( $backup_form ) {
		if ( empty( $backup_form ) || empty( $backup_form['fields'] ) ) {
			return;
		}

		ob_start();
		?>
		<form class="form form--fallback consent__below-submit d-none" id="gform_<?php echo esc_attr( $backup_form['gform_id'] ); ?>_fallback" action="" method="">
			<div class="form__message validation_error"></div>
			<div class="gform_fields">
				<?php
				foreach ( $backup_form['fields'] as $field ) :
					if ( in_array( $field['type'], $this->invalid_fields, true ) ) {
						continue;
					}

					$input_id = 'fb_input_' . $backup_form['gform_id'] . '_' . $field['id'];

					if ( 'consent' === $field['type'] && ! empty( $field['privacy_policy'] ) ) :
						$privacy_policy = sprintf(
							'<div class="gfield_description">%s</div>',
							$field['privacy_policy']
						);
					endif;
					?>
					<div class="<?php echo esc_attr( implode( ' ', $this->get_input_wrapper_classes( $field ) ) ); ?>">
						<?php
						$this->echo_label( $field, $input_id );

						$input_type    = ! in_array( $field['type'], $this->checkbox_fields, true ) ? $field['type'] : 'checkbox';
						$input_classes = implode( ' ', $this->get_input_classes( $field ) );

						if ( in_array( $field['type'], $this->select_fields, true ) ) {
							$required_attributes = $field['required'] ? ' required aria-required="true"' : ' aria-required="false"';

							$choices = $this->get_choices( $field );

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
								$choices // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							);

							if ( 'program' === $field['type'] ) {
								$programs = $this->get_programs_data();
								echo '<input type="hidden" name="programs-data" id="programs-data" value="' . esc_js( $programs ) . '">';
							}
						} else {
							printf(
								'<input id="%s" name="%s" class="%s" type="%s" %s %s>',
								esc_attr( $input_id ),
								esc_attr( $input_id ),
								esc_attr( $input_classes ),
								esc_attr( $input_type ),
								$field['required'] ? ' required' : '',
								$this->get_data_keys_attribute( $field ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							);
						}

						$this->echo_description( $field, $input_id );
						?>
					</div><!-- .form_group -->
					<?php
				endforeach;
				?>
			</div><!-- .gform_fields -->

			<div class="hooks">
				<?php
				foreach ( $backup_form['feeds'] as $feed ) :
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
			<input type="submit" value="Request Info" class="btn btn--bg-gold btn--navy icon icon--arrow-right icon--margin-left">

			<?php
			if ( ! empty( $privacy_policy ) ) {
				echo wp_kses_post( $privacy_policy );
			}
			?>
		</form>
		<?php
		return ob_get_clean();
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
