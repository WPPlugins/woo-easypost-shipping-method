<?php
/*
	Plugin Name: Easypost WooCommerce Extension (BASIC)
	Plugin URI: https://www.xadapter.com/product/woocommerce-easypost-shipping-method-plugin/
	Description: Using Easypost shipping APIs obtain USPS, UPS and FedEx real time shipping rates.
	Version: 1.0.8
	Author: XAdapter
	Author URI: www.xadapter.com/
*/
//Dev Version: 1.1.1

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Required functions
if ( ! function_exists( 'wf_is_woocommerce_active' ) ) {
	require_once( 'wf-includes/wf-functions.php' );
}

// WC active check
if ( ! wf_is_woocommerce_active() ) {
	return;
}

//define("WF_USPS_EASYPOST_API_KEY", "A5DgeMLnX5ATAeZu3dKixg"); // This is test key , You can change it with live key when going production
define("WF_EASYPOST_ID", "wf_easypost_id");
define("WF_EASYPOST_ADV_DEBUG_MODE", "on"); // Turn "off" to disable advanced logs.

function wf_easyshop_basic_activation_check(){
    if ( is_plugin_active('easypost-woocommerce-shipping/easypost-woocommerce-shipping.php') ){
        deactivate_plugins( basename( __FILE__ ) );
		wp_die(__("Is everything fine? You already have the Premium version installed in your website. For any issues, kindly raise a ticket via <a target='_blank' href='//support.xadapter.com/'>support.xadapter.com</a>","wf-easypost"), "", array('back_link' => 1 ));
	}

    if (!function_exists('curl_init')) {
        deactivate_plugins(basename(__FILE__));
        wp_die(__('EasyPost needs the CURL PHP extension.', 'wf-easypost'));
    }

    if (!function_exists('json_decode')) {
        deactivate_plugins(basename(__FILE__));
        wp_die(__('EasyPost needs the JSON PHP extension.', 'wf-easypost'));
    }
}
register_activation_hook( __FILE__, 'wf_easyshop_basic_activation_check' );

/**
 * WC_USPS class
 */
if(!class_exists('USPS_Easypost_WooCommerce_Shipping')){
	class USPS_Easypost_WooCommerce_Shipping {
		/**
		 * Constructor
		 */
		public function __construct() {
			add_action( 'init', array( $this, 'init' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			add_action( 'woocommerce_shipping_init', array( $this, 'shipping_init' ) );
			add_filter( 'woocommerce_shipping_methods', array( $this, 'add_method' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
		}

		/**
		 * Localisation
		 */
		public function init() {			
			if ( ! class_exists( 'wf_order' ) ) {
				include_once 'includes/class-wf-legacy.php';
			}		

			// WF Print Shipping Label.
			load_plugin_textdomain( 'wf-easypost', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		/**
		 * Plugin page links
		 */
		public function plugin_action_links( $links ) {
			$plugin_links = array(
				'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping&section=wf_easypost' ) . '">' . __( 'Settings', 'wf-easypost' ) . '</a>',

				'<a href="https://wordpress.org/support/plugin/woo-easypost-shipping-method" target="_blank">' . __( 'Support', 'wf-easypost' ) . '</a>',

				'<a href="https://www.xadapter.com/product/woocommerce-easypost-shipping-method-plugin/" target="_blank">' . __( 'Premium Upgrade', 'wf-easypost' ) . '</a>'

			);
			return array_merge( $plugin_links, $links );
		}

		/**
		 * Load gateway class
		 */
		public function shipping_init() {
			include_once( 'includes/class-wf-shipping-easypost.php' );
		}

		/**
		 * Add method to WC
		 */
		public function add_method( $methods ) {
			$methods[] = 'WF_Easypost';
			return $methods;
		}

		/**
		 * Enqueue scripts
		 */
		public function scripts() {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'jquery-ui-sortable' );
		}
	}
	new USPS_Easypost_WooCommerce_Shipping();
}

