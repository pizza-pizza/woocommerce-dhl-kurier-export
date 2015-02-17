<?php
/*----------------------------------------------------------------------------------------------------------------------
Plugin Name: WooCommerce DHL Kurier Export
Description: Adds a CSV export capability for DHL Kurier shipments on the WooCommerce orders overview screen.
Version: 1.0
Author: New Order Studios
Author URI: http://neworderstudios.com/
----------------------------------------------------------------------------------------------------------------------*/

if ( is_admin() && !@$_REQUEST['post_ID'] ) {
    new wcKurierCSV();
}

class wcKurierCSV {

	public function __construct() {
		load_plugin_textdomain( 'woocommerce-dhl-kurier-export', false, basename( dirname(__FILE__) ) . '/i18n' );
	}
	

}
