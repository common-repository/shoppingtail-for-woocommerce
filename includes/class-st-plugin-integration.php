<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( !class_exists( 'ST_Plugin_Integration' ) ):

final class ST_Plugin_Integration extends WC_Integration {

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		global $woocommerce;
		$this->id                 = 'shoppingtail';
		$this->method_title       = __( 'Shoppingtail Integration', 'wp-shoppingtail' );
		$this->method_description = __( 'Adds Shoppingtail tracking to WooCommerce.', 'wp-shoppingtail' );
		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();
		// Define user set variables.
		$this->api_key          = $this->get_option( 'api_key' );
		$this->api_url          = $this->get_option( 'api_url' );
		$this->debug            = $this->get_option( 'debug' );

		// Meta tags.
		add_action( 'get_the_generator_html', array( $this, 'output_meta' ), 10, 2 );
		add_action( 'get_the_generator_xhtml', array( $this, 'output_meta' ), 10, 2 );

		// Actions.
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );

		// Hook before headers are sent that we can use to set tracking cookie
		add_action( 'send_headers', array( $this, 'set_correlation_id_cookie' ) );

		// Hook for adding extra meta data when order is created in checkout
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'attach_correlation_id_to_order' ) );

		// Hooks for when to send order data to Shoppingtail
		$order_hooks = array(
			// Created hooks
			'woocommerce_checkout_order_processed',
			'woocommerce_process_shop_order_meta',
			'woocommerce_api_create_order',
			// Updated hooks
			'woocommerce_process_shop_order_meta',
			'woocommerce_api_edit_order',
			'woocommerce_order_edit_status',
			'woocommerce_order_status_changed'
		);

		foreach ( $order_hooks as $hook ) {
			add_action( $hook, array( $this, 'send_webhook' ) );
		}
	}

	public function set_correlation_id_cookie() {
		if ( ! empty( $_GET['_st'] ) ) {
			$correlation_id = $_GET['_st'];
			setcookie( 'st_correlation_id', $correlation_id, strtotime( '+45 days' ), COOKIEPATH, COOKIE_DOMAIN );
		}
	}

	public function attach_correlation_id_to_order( $order_id ) {
		if ( ! empty( $_COOKIE['st_correlation_id'] ) ) {
			update_post_meta( $order_id, 'shoppingtail_tracker', $_COOKIE['st_correlation_id'] );
		}
	}

	private function base64url_encode($data) {
		return rtrim( strtr( base64_encode($data), '+/', '-_' ), '=' ); 
	}

	public function output_meta( $gen, $type ) {
		$timestamp = pack( 'L', time() );
		$signature = hash_hmac( 'sha256', $timestamp, $this->api_key, true );
		$base64 = $this->base64url_encode( $timestamp ) . '.' . $this->base64url_encode( $signature );

		switch ( $type ) {
			case 'html':
				$gen .= "\n" . '<meta name="shoppingtail:signature" content="' . esc_attr( $base64 ) . '">';
				$gen .= "\n" . '<meta name="shoppingtail:version" content="' . esc_attr( SHOPPINGTAIL_VERSION ) . '">';
				break;
			case 'xhtml':
				$gen .= "\n" . '<meta name="shoppingtail:signature" content="' . esc_attr( $base64 ) . '" />';
				$gen .= "\n" . '<meta name="shoppingtail:version" content="' . esc_attr( SHOPPINGTAIL_VERSION ) . '" />';
				break;
		}
		return $gen;
	}

	private function cents( $number ) {
		// Round to cents using bankers' rounding
		return round( 100.0 * $number, 0, PHP_ROUND_HALF_EVEN );
	}

	private function time_string( $usec, $sec ) {
		return date( 'Y-m-d\TH:i:s', $sec ) . substr( $usec, 1, 4 ) . 'Z';
	}

	private function build_payload ( $order_id, $correlation_id ) {
		$post = get_post( $order_id );
		$order = wc_get_order( $post );

		$created_at_sec = strtotime( $post->post_date_gmt );
		$created_at = $this->time_string( 0, $created_at_sec );

		list( $modified_at_usec, $modified_at_sec ) = explode( ' ', microtime() );
		$modified_at = $this->time_string( $modified_at_usec, $modified_at_sec );

		$data = array(
			'order_id'     => (string)$order->get_order_number(),
			'tracking_id'  => $correlation_id,
			'currency'     => $order->get_order_currency(),
			'purchases'    => array(),
			'is_completed' => $order->get_status() === 'completed',
			'created_at'   => $created_at,
			'modified_at'  => $modified_at
		);

		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $order->get_product_from_item( $item );

			// Check if the product exists
			if ( ! is_object( $product ) ) {
				continue;
			}

			$price = $order->get_item_total( $item, false, false );
			$vat = $order->get_item_total( $item, true, false ) - $price;

			$line_item = array(
				'name'        => $item['name'],
				'product_id'  => (string)$product->id,
				'price'       => $this->cents( $price ),
				'vat'         => $this->cents( $vat ),
				'quantity'    => wc_stock_amount( $item['qty'] )
			);

			$data['purchases'][] = $line_item;
		}

		return $data;
	}

	public function send_webhook( $order_id ) {
		global $woocommerce;

		$correlation_id = get_post_meta( $order_id, 'shoppingtail_tracker', true );
		if ( empty( $correlation_id ) ) {
			return;
		}

		$payload = $this->build_payload( $order_id, $correlation_id );

		// Setup request args
		$http_args = array(
			'method'      => 'POST',
			'timeout'     => MINUTE_IN_SECONDS,
			'redirection' => 0,
			'httpversion' => '1.0',
			'blocking'    => true,
			'user-agent'  => sprintf( 'Shoppingtail/%s WooCommerce/%s (WordPress/%s)', SHOPPINGTAIL_VERSION, WC_VERSION, $GLOBALS['wp_version'] ),
			'body'        => trim( json_encode( $payload ) ),
			'cookies'     => array(),
			'headers'     => array(
				'Content-Type' => 'application/json',
				'X-Api-Key' => $this->api_key
			)
		);

		error_log( 'Sending webhook to ' . $this->api_url );
		error_log( 'http_args = ' . print_r( $http_args, true ) );

		$start_time = microtime( true );

		if ( WP_DEBUG ) {
			$response = wp_remote_request( $this->api_url, $http_args );
		} else {
			$response = wp_safe_remote_request( $this->api_url, $http_args );
		}

		if ( is_wp_error( $response ) ) {
			// TODO: Log errors
		}

		error_log( 'http_response = ' . print_r( $response, true ) );

		// TODO: Log success
		$duration = round( microtime( true ) - $start_time, 5 );
		// $this->log_delivery( $delivery_id, $http_args, $response, $duration );

		error_log( 'duration = ' . $duration . ' ms' );
	}

	/**
	 * Initialize integration settings form fields. Called from base class.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'api_key' => array(
				'title'             => __( 'API Key', 'wp-shoppingtail' ),
				'type'              => 'text',
				'description'       => __( 'Enter your Shoppingtail API Key. You must create one under "Account Settings" in the Shoppingtail Console.', 'wp-shoppingtail' ),
				'desc_tip'          => true,
				'default'           => ''
			),
			'api_url' => array(
				'title'             => __( 'API root URL', 'wp-shoppingtail' ),
				'type'              => 'text',
				'description'       => __( 'The URL to the Shoppingtail API. Don\'t change this unless you know what you are doing.', 'wp-shoppingtail' ),
				'desc_tip'          => true,
				'default'           => 'https://api.shoppingtail.com/transactions'
			),
			'debug' => array(
				'title'             => __( 'Debug Log', 'wp-shoppingtail' ),
				'type'              => 'checkbox',
				'label'             => __( 'Enable logging', 'wp-shoppingtail' ),
				'default'           => 'no',
				'description'       => __( 'Log events such as API requests', 'wp-shoppingtail' ),
			),
		);
	}
}

endif;
