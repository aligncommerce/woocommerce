<?php
/**
 * WC wcCpg2 Gateway Class.
 * Built the wcCpg2 method.
 */
//require_once  plugin_dir_path( __FILE__ ).'/class-wc-bitcoin-pay.php';
class WC_Aligncom_Bank_Transfer extends WC_Payment_Gateway {

    /**
     * Constructor for the gateway.
     *
     * @return void
     */
    
    public function __construct() {
        global $woocommerce;
       
       
        $this->id             = 'acBank';
        $this->icon           = apply_filters( 'woocommerce_wcCpg2_icon', '' );
        $this->has_fields     = false;
        $this->method_title   = __( 'Pay with Bank Transfer', 'woocommerce' );
       
        
         $this->invoice_url   = 'https://api.aligncommerce.com/invoice';
        $this->access_token_url  = 'https://api.aligncommerce.com/oauth/access_token';
          $this->currency_url='https://api.aligncommerce.com/currency';
        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Define user set variables.
        $this->title          = $this->settings['title'];
        $this->description    = $this->settings['description'];
        $this->enable_for_countries=$this->get_option('enable_for_bank_countries');
        $this->api_key       = $this->get_option( 'api_key' );
        $this->api_secret       = $this->get_option( 'api_secret' );
        $this->al_username       = $this->get_option( 'al_username' );
        $this->al_password       = $this->get_option( 'al_password' );
        //$this->acbank_redirect_url=$this->get_option('acbank_redirect_url');
        //$this->acbank_ipn_url=$this->get_option('acbank_ipn_url');       
		

        // Actions.
        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) )
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
        else
            add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            
        // Payment listener/API hook
       //add_action( 'valid_ac_banktransfer_ipn', array( $this, 'ac_bank_success' ) );
       add_action( 'woocommerce_api_wc_aligncom_bank_transfer', array( $this, 'check_ipn_aligncom_response_bank') );
       add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      //add_action( 'woocommerce_thankyou_acBank', array( $this, 'thankyou_page' ) );
        
        if ( ! $this->is_valid_for_use_bank() ) {
            $this->enabled = false;
        }


    }

     /**
     * Check if this gateway is enabled and available in the user's country
     *
     * @access public
     * @return bool
     */
    function is_valid_for_use_bank()
    {
         if(strtolower($_GET['section'])=='wc_aligncom_bank_transfer')
         {
        //$bitObject=new WC_Aligncom_Bitcoin_Pay();
        if($this->al_username!='')
        {
           
            $curl   = curl_init();
            curl_setopt($curl, CURLOPT_URL,$this->currency_url);

            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($curl, CURLOPT_USERPWD, $this->al_username.":" . $this->al_password);
            curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);


            curl_setopt($curl, CURLOPT_POST, 1);
            //curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
            curl_setopt($curl, CURLOPT_TIMEOUT, 10);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
            $contents = curl_exec ($curl);
            
            $cur_array = json_decode($contents, true);
            curl_close ($curl);
            if($cur_array['status']==400)
            {
                update_option( 'ac_currency_id_bank','');
                return false;
            }
            for($i=0;$i<count($cur_array['data']);$i++)
            {
                $allowed_currencies[]=$cur_array['data'][$i]['code'];
                if(get_woocommerce_currency()==$cur_array['data'][$i]['code'])
                {
                    update_option( 'ac_currency_id_bank', $cur_array['data'][$i]['currency_id'] );
                    
                }
            }
          
            if ( ! in_array( get_woocommerce_currency(), $allowed_currencies ) ) {
                 update_option( 'ac_currency_id_bank','');
                return false;
            }
    }     }
        return true;
    }
    
    /* Admin Panel Options.*/
	function admin_options() {
       
       
		if ( $this->is_valid_for_use_bank() ) {?>
		<h3><?php _e('Pay with Bank Transfer','ac_woocommerce_payment'); ?></h3>
    	<table class="form-table">
    		<?php $this->generate_settings_html(); ?>
		</table> <?php } else {
            
            ?>
            <div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woocommerce' ); ?></strong>: <?php _e( 'Aligncommerce Bank Transfer does not support your store currency OR you entered wrong credentials.', 'woocommerce' ); ?></p></div>
            <h3><?php _e('Pay with Bank Transfer','ac_woocommerce_payment'); ?></h3>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table><?php
        }
        
    }

    /* Initialise Gateway Settings Form Fields. */
    public function init_form_fields() {
    	global $woocommerce;

    	if(file_exists(dirname( plugin_basename( __FILE__ ) ) .  '/i18n/countries.php' ))
        {
            $woocommerce_countries = apply_filters( 'woocommerce_countries', include( dirname( plugin_basename( __FILE__ ) ) .  '/i18n/countries.php' ) );
        }
        else
        {
            $woocommerce_countries=($woocommerce->countries);
            $woocommerce_countries=$woocommerce_countries->countries;
        }

         
			
        $this->form_fields = array(
            'enabled' => array(
                'title' => __( 'Enable/Disable', 'woocommerce' ),
                'type' => 'checkbox',
                'label' => __( 'Enable Pay with Bank Transfer', 'woocommerce' ),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __( 'Title', 'woocommerce' ),
                'type' => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                'desc_tip' => true,
                'default' => __( 'Pay with Bank Transfer', 'woocommerce' )
            ),
            'description' => array(
                'title' => __( 'Description', 'woocommerce' ),
                'type' => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
                'default' => __( 'Make your payment directly into our bank account.', 'woocommerce' )
            ),
            'api_key' => array(
                'title'         => __( 'Align Commerce API key', 'woocommerce' ),
                'type'             => 'text',
                'default'         => '',
                'description'     => __( 'Please add your API key here.', 'woocommerce' ),
                'desc_tip'      => true,
            ),
            'api_secret' => array(
                'title'         => __( 'Align Commerce API Secret', 'woocommerce' ),
                'type'             => 'text',
                'default'         => '',
                'description'     => __( 'Please add your API Secret key here.', 'woocommerce' ),
                'desc_tip'      => true,
            ),
            'al_username' => array(
                'title'         => __( 'Align Commerce Account Username', 'woocommerce' ),
                'type'             => 'text',
                'default'         => '',
                'description'     => __( 'Please add your Align Commerce username.', 'woocommerce' ),
                'desc_tip'      => true,
            ),
            'al_password' => array(
                'title'         => __( 'Align Commerce account password', 'woocommerce' ),
                'type'             => 'password',
                'default'         => '',
                'description'     => __( 'Please add your Align Commerce password.', 'woocommerce' ),
                'desc_tip'      => true,
            ),
            'enable_for_bank_countries' => array(
                'title'         => __( 'Enable for Countries', 'woocommerce' ),
                'type'             => 'multiselect',
                'class'            => 'chosen_select',
                'css'            => 'width: 450px;',
                'default'         => '',
                'description'     => __( 'Only for selected country this payment gateway will be enabled.', 'woocommerce' ),
                'options'        => $woocommerce_countries,
                'desc_tip'      => true,
            )/*,
            'acbank_redirect_url' => array(
                'title' => __( 'Redirect URL', 'woocommerce' ),
                'type' => 'text',
                'description' => __( 'Redirect the customer to appropriate result page.', 'woocommerce' ),
                'desc_tip' => true,
                'default' => ''
            ),
            'acbank_ipn_url' => array(
                'title' => __( 'IPN URL', 'woocommerce' ),
                'type' => 'text',
                'description' => __( 'Redirect the customer after payment process.', 'woocommerce' ),
                'desc_tip' => true,
                'default' => ''
            )  */
        );

    }




    /* Process the payment and return the result. */
	function process_payment ($order_id) {
          
		global $woocommerce;
        $order = new WC_Order( $order_id );
          //$order       = wc_get_order( $order_id );                           
        $bitObject=new WC_Aligncom_Bitcoin_Pay();
        
		$post = array(
            'grant_type' => 'client_credentials',
            'client_id' => $this->api_key,
            'client_secret' => $this->api_secret,
            'scope' => 'products,invoice,buyer'
        );

 

        $curl   = curl_init();
        curl_setopt($curl, CURLOPT_URL,$this->access_token_url);

        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, $this->al_username.":" . $this->al_password);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);


        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);

        $contents = curl_exec ($curl);
        
        $response = json_decode($contents, true);
         
        $access_token = $response['access_token'];
        curl_close ($curl);
       
    
        
        //Create invoice
        //debugbreak();
         //$shipping_cost=$order->get_total_shipping();
         $shipping_cost=$order->get_shipping();
         //$shipping_cost=round( $order->get_total_shipping() + $order->get_shipping_tax(), 2 );
        $line_items = $order->get_items(); 
        $productAry=array();
        $i=0;
        foreach($line_items as $item)
        {
             
             /*if($i==0){$shipping_cost=$shipping_cost;}
             else{$shipping_cost=0;}*/
             $product = new WC_Product( $item['product_id'] );
             $price = $product->price;
             if ( get_option( 'woocommerce_prices_include_tax' ) == 'yes' ) {
             $tax+=$item['line_subtotal_tax'];
             }
           $productAry[]= array(
                    'product_name' => $item['name'],
                    //'product_price' => $price,
                    'product_price' => $order->get_item_subtotal( $item, false ),
                    'quantity' => $item['qty'],
                    'product_shipping' => 0);
                    $i++;
                   
        }
       /* if($shipping_cost>0)
        {
            $productAry[]= array(
                        'product_name' => 'Total Shipping',
                        'product_price' => 0,
                        'quantity' => 1,
                        'product_shipping' => round($shipping_cost,2));
        }
        if ( get_option( 'woocommerce_prices_include_tax' ) == 'yes' ) {
            $tax=$tax;
         $tax1=$order->get_shipping_tax()+$tax;}
            else{$tax1=$order->get_total_tax();}
        //$tax1=$order->get_shipping_tax()+;
      
        $tax=round($tax1,2) ;
       // echo $order->get_total();
        if($tax>0  && 'yes' === get_option( 'woocommerce_calc_taxes' ))
        {
        $productAry[]= array(
                    'product_name' => 'Tax Amount',
                    'product_price' => $tax,
                    'quantity' => 1,
                    'product_shipping' => 0);
        }
         $discount1=round( $order->get_order_discount(), 2 );
         $discount2=round($order->get_cart_discount(),2);
         $discount=$discount1+$discount2;
        if($discount>0)
        {
        $productAry[]= array(
                    'product_name' => 'Discount',
                    'product_price' => -($discount),
                    'quantity' => 1,
                    'product_shipping' => 0);
        }*/
        
        global $woocommerce;

        if(file_exists(dirname( plugin_basename( __FILE__ ) ) .  '/i18n/countries.php' ))
        {
            $woocommerce_countries = apply_filters( 'woocommerce_countries', include( dirname( plugin_basename( __FILE__ ) ) .  '/i18n/countries.php' ) );
        }
        else
        {
            $woocommerce_countries=($woocommerce->countries);
            $woocommerce_countries=$woocommerce_countries->countries;
        }
        
        $bill_states = $woocommerce->countries->get_states( $order->billing_country );
         $ship_states = $woocommerce->countries->get_states( $order->shipping_country );
        $discount1=round( $order->get_order_discount(), 2 );
         $discount2=round($order->get_cart_discount(),2);
         $discount=$discount1+$discount2;
         
         if ( get_option( 'woocommerce_prices_include_tax' ) == 'yes' ) {
            $tax=$tax;
         $tax1=$order->get_shipping_tax()+$tax;}
         else{$tax1=$order->get_total_tax();}
        //$tax1=$order->get_shipping_tax()+;
         $tax=round($tax1,2) ;
      
       
         $invoice_post =  array(
            'access_token' => $access_token,
            'checkout_type' => 'bank_transfer',
            'order_id'=>$order_id,
            'order_id'=>$order_id,
            //'currency' =>(get_woocommerce_currency()),
            'currency_id'=>get_option('ac_currency_id_bank'),
            'products' =>$productAry,
            'buyer_info' => array(
                            'first_name' => $order->billing_first_name,
                            'last_name' => $order->billing_last_name,
                            'email' => $order->billing_email,
                            'address_1' => $order->billing_address_1,
                            'address_2' => $order->billing_address_2,
                            'address_number' => "",
                            'city' => $order->billing_city,
                            'state' => $bill_states[$order->billing_state],
                            'zip' => $order->billing_postcode,
                            'country' => $woocommerce_countries[$order->billing_country],
                            'phone' => $order->billing_phone),
            'shipping' => array(
                          'description' => 'Shipping',
                          'amount' => round($shipping_cost,2)
                             ),
             'shipping_address' => array(
                            'first_name' => $order->shipping_first_name,
                            'last_name' => $order->shipping_last_name,
                            'email' => $order->billing_email,
                            'address_1' => $order->shipping_address_1,
                            'address_2' => $order->shipping_address_2,
                            'address_number' => "",
                            'city' => $order->shipping_city,
                            'state' => $ship_states[$order->shipping_state],
                            'zip' => $order->shipping_postcode,
                            'country' => $woocommerce_countries[$order->shipping_country],
                            'phone' => $order->billing_phone)
        );
        if($tax>0)
        {
               $invoice_post['tax_rate']= array(
                  'description' => 'Tax',
                  //'percent' => '',
                  'amount' => $tax);
        }
        if($discount>0)
        {
             $invoice_post['discount']= array(
                          'description' => 'Discount',
                          //'percent_off' => '',
                          'amount_off' => $discount);
        }

        /*mail('chaitali@pm.biztechconsultancy.com','ac_invoice_post',print_r($invoice_post,true));
         write_log_data($invoice_post); */
        $curl1   = curl_init($this->invoice_url);
        curl_setopt($curl1, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl1, CURLOPT_USERPWD, $this->al_username.":" . $this->al_password);
        curl_setopt($curl1, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        curl_setopt($curl1, CURLOPT_POST, 1);
        curl_setopt($curl1, CURLOPT_POSTFIELDS, http_build_query($invoice_post));
        curl_setopt($curl1, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl1, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl1, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl1, CURLOPT_SSL_VERIFYHOST, 0);

        $contents1 = curl_exec ($curl1);
        
        $response = json_decode($contents1, true);
        switch($response['status'])
        {
            case 200:
            $redirect_url=$response['data']['invoice_url'];
            break;
            
            default:
            $redirect_url='';
            break;
            
        }
       // die();
        curl_close ($curl1);
        $invoice_post='';
   
         if($redirect_url=='')
         {
             if(is_array($response['error_message']))
             {
                 $err_msg=implode("<br>",$response['error_message']);
             }
             else{$err_msg=$response['error_message'];}
            wc_add_notice( __( 'Align Commerce Error : ', 'woocommerce' ) . $response['status']." - ".$err_msg, 'error' );
            return false;
         }
         else
         {
           // Mark as on-hold
		    $order->update_status('pending', __( 'Your order wont be shipped until the funds have cleared on our account.', 'woocommerce' ));

		    // Reduce stock levels
		    $order->reduce_order_stock();
             //WC()->cart->empty_cart();
		   // Return thankyou redirect
		    return array(
			    'result' 	=> 'success',
			    'redirect'	=>  $redirect_url
              
		    );
         }
	}


    /* Output for the order received page.   */
	function thankyou_page() {
       if ( $this->instructions )
            echo wpautop( wptexturize( $this->instructions ) );
	}
    
    /*********check ipn response******************/
     function check_ipn_aligncom_response_bank()
     {   
         @ob_clean();
        // debugbreak(); 
         global $woocommerce;
          $ipn_response=$_REQUEST;
          write_log_data($ipn_response); 
           //mail('chaitali@pm.biztechconsultancy.com','bank_woo',print_r($ipn_response,true));
           //$ipn_response=array('checkout_type'=>'bank_transfer','status'=>'cancel','order_id'=>'76');
         if($ipn_response['checkout_type']=='btc')
         {
             
              $order_id=$ipn_response['order_id'];
               $status=$ipn_response['status'];
             $order       = wc_get_order( $order_id);
             switch($ipn_response['status'])
            {
                 case 'success':
                $order->update_status('processing', __( 'Your order wont be shipped until the funds have cleared on our account.', 'woocommerce' ));
                $order->reduce_order_stock();
                delete_post_meta($order_id, 'ac_fail_message');
                 $return_url= $this->get_return_url( $order );    
                break;
                
                case 'cancel':
                $order->update_status('cancelled', __( 'Order was canclled.', 'woocommerce' ));
                 $return_url= $order->get_cancel_order_url();
                 update_post_meta($order_id, 'ac_fail_message', $ipn_response['status_message']);
                break;
                
                case 'fail':
                $order->update_status('failed', __( 'Order was failed.', 'woocommerce' ));
                //$order->update_status('failed', __( 'This order is failed because an error.', 'woocommerce' ));
                update_post_meta($order_id, 'ac_fail_message', $ipn_response['status_message']);
                $return_url= $order->get_cancel_order_url();
                break;
                
                case 'processing':
                $order->update_status('pending', __( 'Your order wont be shipped until the funds have cleared on our account.', 'woocommerce' ));
                 $return_url= $this->get_return_url( $order );
                  delete_post_meta($order_id, 'ac_fail_message');
                break;
                
                case 'refund':
                $order->update_status('refunded', __( 'Order was refunded.', 'woocommerce' ));
                $return_url=$this->get_return_url( $order );
                 delete_post_meta($order_id, 'ac_fail_message');
                break;
                
            }
            
            echo  $return_url;
            exit;
         }
         if($ipn_response['checkout_type']=='bank_transfer')
         {
             
             $order_id=$ipn_response['order_id'];
               $status=$ipn_response['status'];
               $order       = wc_get_order( $order_id);
              
               switch($ipn_response['status'])
                {
                    case 'success':
                    $order->update_status('processing', __( 'Your order wont be shipped until the funds have cleared on our account.', 'woocommerce' ));
                    $order->reduce_order_stock();
                    delete_post_meta($order_id, 'ac_fail_message');
                     $return_url= $this->get_return_url( $order );    
                    break;
                    
                    case 'cancel':
                    $order->update_status('cancelled', __( 'Order was canclled.', 'woocommerce' ));
                     $return_url= $order->get_cancel_order_url();
                     update_post_meta($order_id, 'ac_fail_message', $ipn_response['status_message']);
                    break;
                    
                    case 'fail':
                    $order->update_status('failed', __( 'Order was failed.', 'woocommerce' ));
                    //$order->update_status('failed', __( 'This order is failed because an error.', 'woocommerce' ));
                    update_post_meta($order_id, 'ac_fail_message', $ipn_response['status_message']);
                    $return_url= $order->get_cancel_order_url();
                    break;
                    
                    case 'processing':
                    $order->update_status('pending', __( 'Your order wont be shipped until the funds have cleared on our account.', 'woocommerce' ));
                    $return_url= $this->get_return_url( $order );
                    delete_post_meta($order_id, 'ac_fail_message');
                    break;
                    
                    case 'refund':
                    $order->update_status('refunded', __( 'Order was refunded.', 'woocommerce' ));
                    $return_url=$this->get_return_url( $order );
                     delete_post_meta($order_id, 'ac_fail_message');
                    break;
                    
                }
              //  wp_redirect( $return_url);
              
              echo   $return_url;
              exit;
         }  
        
     }
     
     


}
