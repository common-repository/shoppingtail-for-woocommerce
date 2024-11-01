<?php
/*
Plugin Name: Shoppingtail for WooCommerce
Plugin URI: https://shoppingtail.com/integrations
Description: Shoppingtail Integration plugin for use with WooCommerce.
Version: 1.0.2
Author: Shoppingtail
Author URI: https://github.com/shoppingtail/
License: GPLv2 or later
Text Domain: wp-shoppingtail
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'Shoppingtail' ) ) :

final class Shoppingtail {

	public $version = '1.0.2';

	public function __construct() {
		load_plugin_textdomain( 'wp-shoppingtail', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		add_action( 'plugins_loaded', array( $this, 'init' ) );

		if ( ! defined( 'SHOPPINGTAIL_VERSION' ) ) {
			define( 'SHOPPINGTAIL_VERSION', $this->version );
		}
	}

	public function init() {
		// Checks if WooCommerce is installed.
		if ( class_exists( 'WC_Integration' ) ) {
			include_once dirname( __FILE__ ) . '/includes/class-st-plugin-integration.php';
			add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
		} else {
			// TODO: Throw admin error.
		}
	}

	/**
	 * Add a new integration to WooCommerce.
	 */
	public function add_integration( $integrations ) {
		$integrations[] = 'ST_Plugin_Integration';
		return $integrations;
	}
}

$Shoppingtail = new Shoppingtail( __FILE__ );

endif;
