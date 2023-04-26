<?php
/*
	Plugin Name: Oceanpayment CreditCard One-Page Gateway
	Plugin URI: http://www.oceanpayment.com/
	Description: WooCommerce Oceanpayment CreditCard One-Page Gateway.
	Version: 6.0
	Author: Oceanpayment
	Requires at least: 4.0
	Tested up to: 6.1
    Text Domain: oceanpayment-creditcard-One-Page-gateway
*/


/**
 * Plugin updates
 */

load_plugin_textdomain( 'wc_oceancreditcardonepage', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );

add_action( 'plugins_loaded', 'woocommerce_oceancreditcardonepage_init', 0 );

/**
 * Initialize the gateway.
 *
 * @since 1.0
 */
function woocommerce_oceancreditcardonepage_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	require_once( plugin_basename( 'class-wc-oceancreditcardonepage.php' ) );

	add_filter('woocommerce_payment_gateways', 'woocommerce_oceancreditcardonepage_add_gateway' );

} // End woocommerce_oceancreditcard_init()

/**
 * Add the gateway to WooCommerce
 *
 * @since 1.0
 */
function woocommerce_oceancreditcardonepage_add_gateway( $methods ) {
	$methods[] = 'WC_Gateway_Oceancreditcardonepage';
	return $methods;
} // End woocommerce_oceancreditcard_add_gateway()