<?php
/*
Plugin Name: Woocommerce - Accept Bitcoin and Payin Local Payment Gateway
Plugin URI: https://aligncommerce.com
Description: Add Align Commerce Payment Gateway for WooCommerce.
Version: 1.0.0
Author: Align Commerce Corporation
Author URI: https://aligncommerce.com
License: GPLv2
*/

require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

/* WooCommerce fallback notice. */
function woocommerce_btc_payment_fallback_notice() {
    echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Align Commerce Payment Gateways depends on the last version of %s to work!', 'woocommerce' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>' ) . '</p></div>';
}

if ( !function_exists('curl_init') ) 
{
    throw new Exception('Please install CURL PHP extension.','woocommerce');
}
if ( !function_exists('json_decode') ) 
{
    throw new Exception('Please install JSON PHP extension.','woocommerce');
}

register_uninstall_hook(    __FILE__, 'uninstall_ac_paymentGateways' );
function uninstall_ac_paymentGateways()
{
    delete_option('api_key');
    delete_option('api_secret');
    delete_option('al_username');
    delete_option('al_password');
    delete_option('enable_for_bitcoin_countries');
    delete_option('acbct_redirect_url');
    delete_option('acbct_ipn_url');
    delete_option('enable_for_bank_countries');
    delete_option('acbank_redirect_url');
    delete_option('acbank_ipn_url');
    
}

/* Load functions. */
function bitcoin_woocommerce_payment_gateway_load() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', 'woocommerce_btc_payment_fallback_notice' );
        $bct_plugin = plugin_dir_path( __FILE__ ).'/woocommerce-bitcoin-payment.php';
        deactivate_plugins($bct_plugin);
        return;
    }
    else{
    function wc_bct_payment_gateways( $methods ) {
        $methods[] = 'WC_Aligncom_Bitcoin_Pay';
        $methods[] = 'WC_Aligncom_Bank_Transfer';
     
        return $methods;
    }
	add_filter( 'woocommerce_payment_gateways', 'wc_bct_payment_gateways' );
	
	
    // Include the WooCommerce Custom Payment Gateways classes.
    require_once plugin_dir_path( __FILE__ ) . 'class-wc-bitcoin-pay.php';
    require_once plugin_dir_path( __FILE__ ) . 'class-wc-banktransfer-pay.php';
   
    }
}

add_action( 'plugins_loaded', 'bitcoin_woocommerce_payment_gateway_load', 0 );


//disable payment gateways for specific countries
function aligncomerce_payment_disable_country( $available_gateways ) {
   
global $woocommerce;
//for banktransfer payment
 $bankObject=new WC_Aligncom_Bank_Transfer();
if ( isset( $available_gateways['acBank'] ) && (!in_array($woocommerce->customer->get_country(),$bankObject->enable_for_countries)) || (get_option('ac_currency_id_bank')=='')) {
    unset(  $available_gateways['acBank'] );
}

//for bitvoin payment
 $bitObject=new WC_Aligncom_Bitcoin_Pay();
 if ( isset( $available_gateways['acBtc'] ) && (!in_array($woocommerce->customer->get_country(),$bitObject->enable_for_countries)) || (get_option('ac_currency_id_bit')=='')) {
    unset(  $available_gateways['acBtc'] );
}
return $available_gateways;
}
add_filter( 'woocommerce_available_payment_gateways', 'aligncomerce_payment_disable_country' );


function write_log_data ( $data )  {
    
    $str=PHP_EOL."-----------------------------------------------".PHP_EOL."New record".PHP_EOL."-----------------------------------------------".PHP_EOL;
        foreach($data as $key=>$val)
             {
                 $str.=$key." = ".$val.PHP_EOL;
             }
        $file = $_SERVER['DOCUMENT_ROOT']."/wp_woocommerce/response.log";
        // Open the file to get existing content
        $current = file_get_contents($file);
        // Append a new person to the file
        $current .= $str;
        // Write the contents back to the file
        file_put_contents($file, $current); 
}


//add_action('woocommerce_order_items_table','ac_order_notes_show');
add_action('woocommerce_order_details_after_order_table','ac_order_notes_show');
function ac_order_notes_show($order)
{
    global $woocommerce;
    $post_id=$order->id;
    $ac_fail_msg=get_post_meta( $post_id, 'ac_fail_message' );
    if($ac_fail_msg)
    {
        echo '<p class="order-info"><b>'.__('Order Note','woocommerce').' : </b> '.$ac_fail_msg[0].'<p>';
    }
}
?>