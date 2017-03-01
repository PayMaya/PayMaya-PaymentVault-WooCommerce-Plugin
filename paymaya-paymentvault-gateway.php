<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit; // Exit if accessed directly
	}
/*
Plugin Name: PayMaya PaymentVault WooCommerce Gateway
Plugin URI: https://developers.paymaya.com/
Description: PayMaya Payment Vault page extension for WooCommerce.
Version: 1.0
Author: Dennis Colinares, Voyager Innovations
Author URI: https://developers.paymaya.com/
*/
	
require_once __DIR__ . "/paymaya-paymentvault-data.php";

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'paymaya_paymentvault_init', 0 );

function paymaya_paymentvault_init() {
	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	// If we made it this far, then include our Gateway Class
	include_once( 'paymaya-paymentvault.php' );

	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'paymaya_paymentvault_add_gateway' );
}
	
function paymaya_paymentvault_add_gateway($methods ) {
	$methods[] = 'PayMaya_Paymentvault';
	return $methods;
}

// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'paymaya_paymentvault_action_links' );

function paymaya_paymentvault_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'paymaya_paymentvault' ) . '</a>',
	);

	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );
}

add_action('activated_plugin', 'paymaya_paymentvault_loadfirst');

function paymaya_paymentvault_loadfirst(){
	global $woocommerce;
	global $wpdb;
}
	
register_activation_hook( __FILE__,'paymaya_paymentvault_data_activate');

function paymaya_paymentvault_data_activate() {
	global $wpdb;
	
	try{
		// create custom order data table
		$wpdb->query('
		  CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'woocommerce_paymentvault_data (
		    `id` integer NOT NULL AUTO_INCREMENT,
			`payment_id` varchar(255) NOT NULL DEFAULT \'\',
			`token_id` varchar(255) NOT NULL,
			`order_id` integer NOT NULL,
			`date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`)
		  ) ENGINE = MYISAM;
		');
	}
	catch (Exception $e){
		
	}
}