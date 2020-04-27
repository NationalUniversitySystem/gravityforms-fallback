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
	 * Using construct method to add any actions and filters
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 99 );
		add_filter( 'gform_get_form_filter', [ $this, 'add_fallback_form' ], 10, 2 );
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
				// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				if ( 'visible' === $field->visibility ) {
					$backup_form['fields'][ $field->id ] = [
						'id'          => $field->id,
						'type'        => $field->type,
						'label'       => 'hidden' !== $field->type ? $field->label : '',
						'input_name'  => $field->inputName,
						'description' => $field->description,
						'css-class'   => $field->cssClass,
					];
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

		$backup_form_markup = $this->build_backup_form( $backup_form );

		return $form_string . $backup_form_markup;
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
		<form class="form form--fallback d-none" id="gform_<?php echo esc_attr( $backup_form['gform_id'] ); ?>_fallback" action="" method="">
			<div class="form__message validation_error"></div>
			<div class="gform_fields">
				<?php
				foreach ( $backup_form['fields'] as $field ) :
					$invalid_fields = [
						'program',
						'degree',
						'nu_honey',
					];

					if ( in_array( $field['type'], $invalid_fields, true ) ) {
						continue;
					}

					$wrapper_classes = [];

					if ( 'hidden' !== $field['type'] ) {
						$wrapper_classes[] = 'form__group';
					}

					if ( ! empty( $field['css-class'] ) ) {
						$wrapper_classes[] = $field['css-class'];
					}

					?>
					<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>">
					<?php
					if ( ! empty( $field['label'] ) ) :
						?>
						<label class="form__label" for="input_<?php echo esc_attr( $field['id'] ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
						<?php
					endif;

					// Data keys.
					$data_keys = array_filter( $field, function( $value, $key ) {
						return 0 === strpos( $key, 'data-key-' );
					}, ARRAY_FILTER_USE_BOTH );

						$data_keys_attribute = '';
					if ( ! empty( $data_keys ) ) {
						foreach ( $data_keys as $data_key => $data_value ) {
							$data_keys_attribute = ' ' . $data_key . '="' . $data_value . '"';
						}
					}

					$checkboxes = [
						'checkbox',
						'military',
						'gdpr',
					];

					$input_type = in_array( $field['type'], $checkboxes, true ) ? 'checkbox' : $field['type'];

					printf(
						'<input class="input input--checkbox input--styled %s" type="%s" name="input_%s" id="input_%s" %s>',
						esc_attr( $field['input_name'] ),
						esc_attr( $input_type ),
						esc_attr( $field['id'] ),
						esc_attr( $field['id'] ),
						$data_keys_attribute // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					);
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
		</form>
		<?php
		return ob_get_clean();
	}
}
