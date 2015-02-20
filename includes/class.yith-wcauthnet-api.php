<?php
/*  Copyright 2013  Your Inspiration Themes  (email : plugins@yithemes.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * API handler class
 *
 * @author Your Inspiration Themes
 * @package YITH WooCommerce Authorize.net
 * @version 1.0.0
 */

if ( ! defined( 'YITH_WCAUTHNET' ) ) {
	exit;
} // Exit if accessed directly

if( ! class_exists( 'YITH_WCAUTHNET_API' ) ){
	/**
	 * WooCommerce Authorize.net API handler class
	 *
	 * @since 1.0.0
	 */
	class YITH_WCAUTHNET_API {

		/**
		 * @var string Whether or not we're using a development env
		 */
		public $sandbox;

		/**
		 * @var string Authorize.net Login ID
		 */
		public $login_id;

		/**
		 * @var string Authorize.net transaction key
		 */
		public $transaction_key;

		/**
		 * Single instance of the class
		 *
		 * @var \YITH_WCAUTHNET_API
		 * @since 1.0.0
		 */
		protected static $instance;

		/**
		 * Returns single instance of the class
		 *
		 * @return \YITH_WCAUTHNET_API
		 * @since 1.0.0
		 */
		public static function get_instance(){
			if( is_null( self::$instance ) ){
				self::$instance = new self;
			}

			return self::$instance;
		}

		/**
		 * Make a request to Authorize.net servers, and returns data sent back
		 *
		 * * @param $request_type string AUTH_CAPTURE/AUTH_ONLY/CREDIT
		 * @param $request_method string CC/ECHECK
		 * @param $request_args array Query string of data to send
		 * @param $itemized bool True or false, indicating whether the transaction should be itemized or not
		 * @param $order \WC_Order Correct instance of WC_Order for the transaction
		 * @return string|bool Value returned by the server; false on failure
		 * @since 1.0.0
		 */
		public function do_request( $request_type, $request_method = false, $request_args = array(), $itemized = false, $order = null ) {
			if( empty( $this->login_id ) || empty( $this->transaction_key ) ){
				return false;
			}

			if( ! in_array( $request_type, array( 'AUTH_CAPTURE', 'AUTH_ONLY', 'CREDIT' ) ) ){
				return false;
			}

			if( ! empty( $request_method ) && ! in_array( $request_method, array( 'CC', 'ECHECK' ) ) ){
				return false;
			}

			if ( 'yes' == $this->sandbox ) {
				$process_url = YITH_WCAUTHNET_Credit_Card_Gateway::AUTHORIZE_NET_SANDBOX_PAYMENT_URL;
			}
			else {
				$process_url = YITH_WCAUTHNET_Credit_Card_Gateway::AUTHORIZE_NET_PRODUCTION_PAYMENT_URL;
			}

			$params = array_merge(
				array(
					'x_login' => $this->login_id,
					'x_tran_key' => $this->transaction_key,
					'x_type' => $request_type
				),
				( ! empty( $request_method ) ) ? array( 'x_method' => $request_method ) : false,
				$request_args
			);

			$query_args = $query_args = http_build_query( $params, '', '&' );

			if( $itemized ){
				$line_items = $order->get_items( 'line_item' );
				$taxes = $order->get_taxes();

				if( ! empty( $line_items ) && count( $line_items ) <= 30 ){
					foreach( $line_items as $key => $item ){
						$item_subtotal = $order->get_item_subtotal( $item );
						$item_taxable = ( $order->get_item_tax( $item ) != 0 ) ? 'Y' : 'N';

						$query_args .= "&x_line_item=" . urlencode( "{$key}<|>{$item['name']}<|><|>{$item['qty']}<|>{$item_subtotal}<|>{$item_taxable}" );
					}
				}

				if( ! empty( $taxes ) ){
					foreach( $taxes as $key => $tax ){
						$query_args .= "&x_tax=" . urlencode( "{$tax['label']}<|><|>{$tax['tax_amount']}" );
					}
				}
			}

			$request = curl_init( $process_url ); // initiate curl object
			curl_setopt( $request, CURLOPT_HEADER, 0 ); // set to 0 to eliminate header info from response
			curl_setopt( $request, CURLOPT_RETURNTRANSFER, 1 ); // Returns response data instead of TRUE(1)
			curl_setopt( $request, CURLOPT_POSTFIELDS, $query_args ); // use HTTP POST to send form data
			curl_setopt( $request, CURLOPT_SSL_VERIFYPEER, false ); // uncomment this line if you get no gateway response.
			$post_response = curl_exec( $request ); // execute curl post and store results in $post_response
			curl_close ( $request );

			return $post_response;
		}
	}
}

/**
 * Unique access to instance of YITH_WCAUTHNET_API class
 *
 * @return \YITH_WCAUTHNET_API
 * @since 1.0.0
 */
function YITH_WCAUTHNET_API(){
	return YITH_WCAUTHNET_API::get_instance();
}