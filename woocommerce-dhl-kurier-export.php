<?php
/*----------------------------------------------------------------------------------------------------------------------
Plugin Name: WooCommerce DHL Kurier Export
Description: Adds a CSV export capability for DHL Kurier shipments on the WooCommerce orders overview screen.
Version: 1.5.6
Author: New Order Studios
Author URI: http://neworderstudios.com/
----------------------------------------------------------------------------------------------------------------------*/

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('wcKurierCSV')) {
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
			add_filter( 'manage_shop_order_posts_columns', array( $this, 'add_order_column_header' ), 20 );
			add_action( 'manage_shop_order_posts_custom_column', array( $this, 'add_order_column' ), 20 );
			add_filter( 'manage_edit-shop_order_sortable_columns', array( $this, 'add_order_column_sortable' ), 20 );
			add_action( 'pre_get_posts', array( $this, 'manage_order_column_sorting' ), 1 );
			add_action( 'admin_footer', array( $this, 'add_export_options' ) );
			add_action( 'gwi_delivery_time', array( $this, 'add_checkout_preferred_delivery' ) );
			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'update_preferred_delivery' ) );
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
					$('<option>').val('generate_dhl_csv').text('<?php _e( 'Export CSV for DHL Kurier', 'woocommerce-dhl-kurier-export' ) ?>').appendTo("select[name='action'],select[name='action2']");
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
			$psv = implode( '|', $this->columns ) . "\n";

			foreach ( $post_ids as $post_id ) {
				$order = wc_get_order( $post_id );

				$weight = 0;
				foreach( $order->get_items() as $item ) {
					if ( $item['product_id'] > 0 ) {
						$_product = $order->get_product_from_item( $item );
						if ( ! $_product->is_virtual() ) $weight += $_product->get_weight() * $item['qty'];
					}
				}

				// Split out the house number
				$matches = array();
				$house_number = null;
				if ( preg_match( '/(?P<address>[^\d]+) (?P<number>\d+.?)/', $order->shipping_address_1, $matches ) ) {
					$street_address = $matches['address'];
					$house_number = (int)$matches['number'];
				} else $street_address = $order->shipping_address_1;

				// sanitize phone number
				$phone = preg_replace('/\s/', '', $order->billing_phone);
				$phone = preg_replace('/^((0049)|(49)|(\+49))/', '0', $phone);

				$orders[$post_id] = array(
					$order->billing_first_name,
					$order->billing_last_name,
					$street_address,
					$house_number,
					$order->shipping_postcode,
					$order->shipping_city,
					$order->shipping_address_2,
					$phone,
					$order->billing_email,
					date( 'Ymd' ),
					'N',
					'',
					'',
					'N',
					'',
					function_exists('get_field') ? get_field( 'preferred_delivery_time', $order->id ) : '',
					'',
					'L',
					'10', // Using a fixed 10kg weight instead of $weight, per GWI
					'J',
					'J'
				);

				$psv .= implode( '|', $orders[$post_id] ) . "\n";
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
		 * Let's display the shipping zone column header.
		 */
		public function add_order_column_header( $columns ) {

			if( !function_exists('woocommerce_get_shipping_zone') ) return $columns;
			$new_cols = array();

			foreach ( $columns as $k => $c ) {
				if ( $k == 'shipping_address' ){
					$new_cols['order_location'] = _( 'Shipping Type', 'woocommerce-dhl-kurier-export' );
					$new_cols['order_shipdate'] = 'Sendungsdatum';
				}
				$new_cols[$k] = $c;
			}

			return $new_cols;

		}

		/**
		 * Let's make the shipdate column sortable.
		 */
		public function add_order_column_sortable( $columns ) {

			$columns['order_shipdate'] = 'Sendungsdatum';
			return $columns;

		}

		/**
		 * Let's sort on the right meta field
		 */
		public function manage_order_column_sorting( $query ) {

			if ( $query->is_main_query() && $query->get( 'orderby' ) == 'Sendungsdatum' ) {
				$query->set( 'meta_key', 'preferred_delivery_date' );
				$query->set( 'orderby', 'meta_value_num' );
			}

		}


		/**
		 * Let's display the shipping zone column.
		 */
		public function add_order_column( $column ) {

			if( !function_exists('woocommerce_get_shipping_zone') ) return;
			global $post, $woocommerce, $the_order;

			$destination = array( 'destination' => array(
				'postcode'	=> $the_order->shipping_postcode,
				'state'		=> $the_order->shipping_state,
				'country'	=> $the_order->shipping_country
			));

			$zone = woocommerce_get_shipping_zone( $destination );

			if ( $column == 'order_location' ) {
				echo ( $zone->zone_id == 0 ? 'Overnight' : $zone->zone_name );
				$delivery = get_field( 'preferred_delivery_date', $post->id );
				if ( $delivery ) echo "<br /><br />Gewünschte Lieferung: " . $delivery;
			} elseif ( $column == 'order_shipdate' ) {
				$delivery = get_field( 'preferred_delivery_date', $post->id );
				if ( $delivery ) {
					$ship_date = new DateTime( $delivery );
					echo date_i18n( get_option( 'date_format' ), $ship_date->modify( $zone->zone_id == 1 ? '+0 day' : '-1 day' )->format( 'U' ) );
				}
			}

		}

		/*
		 * Let's add the preferred delivery dropdown on the checkout page.
		 */
		public function add_checkout_preferred_delivery() {

			if ( function_exists( 'get_field' ) ) {
				$delivery_field = get_field_object( 'field_5537b78f95074' );
				$date_options = null;

				if ( !class_exists( 'GWIDeliveryEstimates' ) ) require_once( get_template_directory() . '/lib/delivery_estimates.php' );
				if ( !function_exists( 'Roots\Sage\Extras\get_visitor_info' ) ) equire_once( get_template_directory() . '/lib/extras.php' );

				$postcode = Roots\Sage\Extras\get_postcode_from_address(get_current_user_id());
				if ( !$postcode ) $postcode = Roots\Sage\Extras\get_cookie_postcode();

				if ( $postcode ) {
					$gwi_delivery_estimates = new GWIDeliveryEstimates();
					$delivery_info = $gwi_delivery_estimates->get_delivery_date( $postcode );

					$start = (int)$delivery_info->format( 'N' );
					$date_options = array();
					for ( $i = 0; $i <= 5 - $start; $i++ ) {
						$date_options[$delivery_info->format( 'Ymd' )] = date_i18n( 'l d.m', $delivery_info->format( 'U' ) );
						$delivery_info->modify( '+1 day' );
					}
				}
				?>

				<p class="form-row form-row-wide gwi-delivery-preference">
					<label for="preferred_delivery_time" class=""><?= __('Dein Lieferzeitraum'); ?></label>

					<?php if ( $date_options ) { ?>
						<select name="preferred_delivery_date" id="preferred_delivery_date">
							<?php foreach ( $date_options as $k => $d ) { ?>
								<option value="<?php echo $k; ?>"><?php echo $d; ?></option>
							<?php } ?>
						</select> um
					<?php } ?>

					<select name="preferred_delivery_time" id="preferred_delivery_time">
						<?php foreach ( $delivery_field['choices'] as $k => $c ) { ?>
							<option value="<?php echo $k; ?>"><?php echo $k; ?></option>
						<?php } ?>
					</select>
				</p>

				<?php
			}

		}

		/*
		 * We want to write the delivery time preference.
		 */
		public function update_preferred_delivery( $order_id ) {

			if ( $_POST['preferred_delivery_time'] ) {
				update_field( 'field_5537b78f95074', $_POST['preferred_delivery_time'], $order_id );
			}
			if ( $_POST['preferred_delivery_date'] ) {
				update_field( 'field_558d1a9b1bd2b', $_POST['preferred_delivery_date'], $order_id );
			}

		}

	}

	new wcKurierCSV();
}
