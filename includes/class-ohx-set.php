<?php

/**
 * The file that defines the class to handle the sest during the 
 * quick settings session
 * 
 * @link       https://jnl.local
 * @since      1.0.0
 *
 * @package    Ohx
 * @subpackage Ohx/includes
 */

/**
 * The set plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Ohx
 * @subpackage Ohx/includes
 * @author     JNL <admin@mcpe.ch>
 */
class OhxSet {

	private $postID;
	

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'OHX_VERSION' ) ) {
			$this->version = OHX_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'ohx';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}


}
