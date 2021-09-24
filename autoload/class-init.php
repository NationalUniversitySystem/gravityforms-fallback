<?php
/**
 * Manage Plugin initialization
 *
 * @package GF_Fallback\Autoload
 */

namespace GF_Fallback\Autoload;

/**
 * Init
 */
class Init {
	/**
	 * Classes (namespace structure)
	 * In specific order to loop through so things load accordingly.
	 *
	 * @var array
	 */
	private $class_names = [
		'Inc\Fallback_Forms',
	];

	/**
	 * Using construct method to add any actions and filters
	 */
	public function __construct() {
		$this->initiate_classes();
	}

	/**
	 * Call our classes so they are instantiated (one level)
	 *
	 * @return void
	 */
	public function initiate_classes() {
		foreach ( $this->class_names as $class_name ) {
			$full_name = 'GF_Fallback\\' . $class_name;

			if ( class_exists( $full_name ) ) {
				new $full_name();
			}
		}
	}
}
