<?php
/*----------------------------------------------------------------------------------------------------------------------
Plugin Name: WooCommerce DHL Kurier Export
Description: Adds a CSV export capability for DHL Kurier shipments on the WooCommerce orders overview screen.
Version: 1.0.0
Author: New Order Studios
Author URI: http://neworderstudios.com/
----------------------------------------------------------------------------------------------------------------------*/

if ( is_admin() ) {
    new wcKurierCSV();
}

class wcKurierCSV {

	protected $columns = array(	
		'Empfänger Vorname',			// First name
		'Empfänger Nachname',			// Last name
		'Empfänger Straße',				// Street
		'Empfänger Hausnummer',			// Number
		'Empfänger PLZ',				// Postcode
		'Empfänger Stadt',				// City
		'Empfänger Adresszusatz',		// Address line 2
		'Empfänger Telefonnummer',		// Phone
		'Empfänger E-Mail',				// Email
		'Abholdatum',					// Collection date
		'Nachbarschaftszustellung',		// Neighbor delivery
		'Name Nachbar',					// Neighbor name
		'Kommentar Zustellung',			// Comment
		'Leergut',						// Empties
		'Anzahl Leergut',				// # Empties
		'Zustellzeitfenster',			// Deilvery timing
		'Sendungsnummer',				// Tracking #
		'Größe',						// Size
		'Gewicht',						// Weight
		'Kühlpflichtig',				// Cooling
		'Mehrweg'						// Reusable
	);

	public function __construct() {

		load_plugin_textdomain( 'woocommerce-dhl-kurier-export', false, basename( dirname(__FILE__) ) . '/i18n' );
		add_action( 'admin_footer', array( $this, 'add_export_options' ) );
		add_action( 'load-edit.php', array( $this, 'generate_csv' ) );

	}

	/**
	 * Let's add some JS to append our new CSV export option to the bulk actions list.
	 */
	public function add_export_options() {

		global $post_type;

		if ( $post_type == 'shop_order' ) {
			?>
			<script type="text/javascript">
			jQuery('document').ready(function($){
				$('<option>').val('generate_dhl_csv').text('<?php _e( 'Export CSV for DHL Kurier', 'woocommerce-dhl-kurier-export' )?>').appendTo("select[name='action'],select[name='action2']");
			});
			</script>
			<?php
		}

	}

	/**
	 * Let's generate the CSV and send it to the browser.
	 */
	public function generate_csv() {

		$wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
		$action = $wp_list_table->current_action();
		
		// Do we want to get involved?
		if ( strpos( $action, 'generate_dhl_csv' ) === false ) return;

		// Yes.
		$post_ids = array_map( 'absint', (array) $_REQUEST['post'] );
		$orders = array();
		$psv = implode( '|', $this->columns );

		foreach ( $post_ids as $post_id ) {
			$order = wc_get_order( $post_id );

			$weight = 0;
			foreach( $order->get_items() as $item ) {
				if ( $item['product_id'] > 0 ) {
					$_product = $order->get_product_from_item( $item );
					if ( ! $_product->is_virtual() ) $weight += $_product->get_weight() * $item['qty'];
				}
			}

			$orders[$post_id] = array(
				$order->billing_first_name,
				$order->last_name,
				$order->shipping_address_1,
				null,							 // TODO: split out the street & house number fields in orders
				$order->shipping_postcode,
				$order->shipping_city,
				$order->shipping_address_2,
				$order->billing_phone,
				$order->billing_email,
				date( 'Ymd' ),
				'N',
				'',
				'',
				'N',
				'',
				'',								// TODO: set up delivery time field for orders
				'',
				'M',							// TODO: need confirmation from the client
				$weight,
				'J',
				'N'
			);

			$psv .= "\n" . implode( '|', $orders[$post_id] );
		}

		header( 'Content-Encoding: UTF-8' );
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=kurier_orders_' . date( 'Ymd' ) . '.csv' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Content-Length: ' . strlen( $psv ) );

		$output = fopen('php://output', 'w');
		fwrite( $output, "\xEF\xBB\xBF" . $psv );
		die();

	}

	/**
	 * Let's display the shipment type for listed orders (same-day / overnight)
	 */
	public function add_order_display_logic() {

		/**
		 * TODO: determine whether this order is delivered within the same-day zone based on zip code (TBD)
		 */

	}

}
