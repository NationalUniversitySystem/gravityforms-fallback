<?php
/**
 * Plugin Name: Gravity Forms - Fallback
 * Description: Plugin to have fallback form functionality if Gravity Forms or sever is down.
 * Version: 0.0.6
 * Author: Mike Estrada
 *
 * @package GF_Fallback
 */

namespace GF_Fallback;

if ( ! defined( 'WPINC' ) ) {
	die( 'YOU SHALL! NOT! PASS!' );
}

define( 'GF_FALLBACK_PATH', plugin_dir_path( __FILE__ ) );
define( 'GF_FALLBACK_URL', plugin_dir_url( __FILE__ ) );
define( 'GF_FALLBACK_VERSION', '0.0.6' );

use GF_Fallback\Autoload\Init;

add_action( 'gform_loaded', function() {
	require_once GF_FALLBACK_PATH . 'autoload/autoloader.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant

	// Initializing file is in inc/class-init.php. Refer to file for setup.
	Init::singleton();
} );
