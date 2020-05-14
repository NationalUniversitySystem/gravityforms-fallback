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
		add_action( 'rest_api_init', [ $this, 'register_endpoint' ] );
		add_action( 'save_post_program', [ $this, 'flush_transient' ], 10, 3 );
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

		$backup_form_object = new Build_Form( $backup_form );
		$backup_form_markup = apply_filters( 'gf_fallback_form_markup', $backup_form_object->get_form_markup() );

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
	 * Flush the transient for programs when we save/update a program.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated or not.
	 *
	 * @return void
	 */
	public function flush_transient( $post_id, $post, $update ) {
		delete_transient( 'fallback_programs_data' );
	}
}
