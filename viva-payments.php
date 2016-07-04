<?php
/*
  Plugin Name: Viva payments
  Description: Viva gateway.
  Version: 2.1
  Author: Viral Passion
  Text Domain: viva
  Domain Path: /vp-ucf
*/  
defined( 'ABSPATH' ) or die();

require 'plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = PucFactory::buildUpdateChecker(
    'http://assets.vpsites.eu/my_plugins/viva-payments-versions/update.json',
    __FILE__
);

add_action('plugins_loaded', 'woocommerce_Viva_init', 0);
function woocommerce_Viva_init(){
  if(!class_exists('WC_Payment_Gateway')) return;
 
  class WC_Viva extends WC_Payment_Gateway{
    public function __construct(){
      $this -> id = 'viva';
      $this -> medthod_title = 'Viva Payments';
      $this -> has_fields = false;
      
	  
      $this -> init_form_fields();
      $this -> init_settings();
      
	  $this -> icon = $this -> settings['image'];
      $this -> title = $this -> settings['title'];
      $this -> description = $this -> settings['description'];
      //$this -> liveurl = 'http://demo.vivapayments.com/';
 
      $this -> msg['message'] = "";
      $this -> msg['class'] = "";

 add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
 
 	  
            add_action( 'woocommerce_api_wc_viva', array( $this, 'check_ipn_response' ) );
  
 	  	
   }
    function init_form_fields(){
 
       $this->form_fields = array(
	'enabled' => array(
		'title' => __( 'Enable/Disable', 'woocommerce' ),
		'type' => 'checkbox',
		'label' => 'Enable Viva Payments',
		'default' => 'yes'
	),
	'title' => array(
		'title' => __( 'Title', 'woocommerce' ),
		'type' => 'text',
		'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
		'default' => 'Card',
		'desc_tip'      => true,
	),
	'description' => array(
		'title' => __( 'Customer Message', 'woocommerce' ),
		'type' => 'textarea',
		'default' => ''
	),
	'image' => array(
		'title' => __( 'Logo Url', 'woocommerce' ),
		'type' => 'text',
		'default' => plugins_url('cards_logo.png', __FILE__),
		'description' => 'default logo is: '.plugins_url('cards_logo.png', __FILE__)
	),
	'demo_mode' => array(
		'title' => 'Demo mode',
		'type' => 'checkbox',
		'label' => 'Enable demo mode',
		'description' => 'Enable this if you use a demo viva payments account',
		'default' => 'no',
		'desc_tip' => true
	),
	'source_code' => array(
		'title' => 'Source Code',
		'description' => 'You can find it at vivapayments.com, (Sales > Payment Sources)',
		'type' => 'text',
		'desc_tip' => true
	),
	'info_img' => array(
		'title' => 'Settings on Viva Payment Source',
		'description' => 'You can find it at vivapayments.com, (Sales > Payment Sources)',
		'type' => 'image',
		'default' => plugins_url('cards_logo.png', __FILE__),
		'desc_tip' => true
	),
	'Merchant_ID' => array(
		'title' => 'Merchant ID',
		'description' => 'You can find it at vivapayments.com, (Settings > API Access)',
		'type' => 'text',
		'desc_tip'      => true,
	),
	'API_Key' => array(
		'title' => 'API Key',
		'description' => 'You can find it at vivapayments.com, (Settings > API Access)',
		'type' => 'text',
		'desc_tip'      => true,
	)
);
    }
    
    
    
    function check_ipn_response() {
            
            	global $woocommerce;
            	//$order = new WC_Order( $order_id );
				// The POST URL and parameters
				
				$request =  'https://www.vivapayments.com/api/transactions/';	// production environment URL
				if($this -> settings['demo_mode']=='yes'){
					$request =  'http://demo.vivapayments.com/api/transactions/';	// demo environment URL
				}
				// Your merchant ID and API Key can be found in the 'Security' settings on your profile.
				$MerchantId = $this -> settings['Merchant_ID'];// '853c3324-25a1-4a7d-a466-d87eb25339ca';
				$APIKey = $this -> settings['API_Key'];//'weO.XU'; 	
		
				
				//Set the ID of the Initial Transaction
				$request .= $_GET['t'];
				
				
				//$qry_str  = 'ordercode='.$_GET['s'];
				
				// Get the curl session object
				$session = curl_init();
				

				// Set query data here with the URL

				//curl_setopt($ch, CURLOPT_TIMEOUT, '3');
				
				curl_setopt($session, CURLOPT_URL, $request . $qry_str); 
				// Set query data here with the URL
				//curl_setopt($session, CURLOPT_POST, true);
				//curl_setopt($session, CURLOPT_POSTFIELDS, $postargs);
				//curl_setopt($session, CURLOPT_HEADER, false);
				curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($session, CURLOPT_USERPWD, $MerchantId.':'.$APIKey);
				//curl_setopt($session, CURLOPT_SSL_CIPHER_LIST, 'TLSv1');
				
				$response = trim(curl_exec($session));
				curl_close($session);
				
				// Parse the JSON response
				try {
					$resultObj=json_decode($response);
				} catch( Exception $e ) {
					die ($e->getMessage());
					
				}
				
				if ($resultObj->ErrorCode==0){
					// print JSON output
					//die ('ok: '.json_encode($resultObj));
				
					$order_id = $resultObj -> Transactions[0] -> Order -> Tags[0];
					$order_id = str_replace("0=", "",$order_id);
					$order = wc_get_order( $order_id );
					if ($resultObj -> Transactions[0] -> StatusId == 'F'){
					
						// Reduce stock levels
						$order->reduce_order_stock();
						$woocommerce->cart->empty_cart();
					
						$amound = $resultObj -> Transactions[0] -> Amount;
						$commission = $resultObj -> Transactions[0] -> Commission;
						$TransactionId = $resultObj -> Transactions[0] -> TransactionId;
						$OrderCode = $resultObj -> Transactions[0] -> Order -> OrderCode;
					
						$card_no = $resultObj -> Transactions[0] -> CreditCard -> Number;
						$card_country = $resultObj -> Transactions[0] -> CreditCard -> CountryCode; 
						$card_bank = $resultObj -> Transactions[0] -> CreditCard -> IssuingBank;
						$card_holders_name = $resultObj -> Transactions[0] -> CreditCard -> CardHolderName;
						$card_type = $resultObj -> Transactions[0] -> CreditCard -> CardType -> Name;
					
						
						add_post_meta($order_id, 'Order_Code', $OrderCode);
						add_post_meta($order_id, 'Amount', $amound);
						add_post_meta($order_id, 'Commission', $commission);
						add_post_meta($order_id, 'Transaction_Id', $TransactionId);
						
						add_post_meta($order_id, 'Card_Number', $card_no);
						add_post_meta($order_id, 'Card_Holders_Name', $card_holders_name);
						add_post_meta($order_id, 'Card_Bank', $card_bank);
						add_post_meta($order_id, 'Card_Type', $card_type);
						add_post_meta($order_id, 'Card_Country', $card_country);
						
						
						if ((float)$amound == (float)$order->get_total()) {
							$order->update_status('processing','Is Payed via Viva Payments');
							$order->payment_complete();
							
							 
                                        
						}else{
							$order->update_status('failed','The amount paid is different from order\'s total');
							
						}
						
						
						
						//die($amound.' '.$commission.' '.$TransactionId.' '.$OrderCode.' '.$card_no.' '.$card_country.' '.$card_bank.' '.$card_holders_name.' '.$card_type);
						
						
					}
					
					else if ($resultObj -> Transactions[0] -> StatusId == 'E'){
						$order->update_status('failed','The transaction was not completed because of an error');
					}
					else if ($resultObj -> Transactions[0] -> StatusId == 'M'){
						$order->update_status('failed','The cardholder has disputed the transaction with the issuing Bank');
					}
					else if ($resultObj -> Transactions[0] -> StatusId == 'MS'){
						$order->update_status('failed','Suspected Dispute');
					}
					else if ($resultObj -> Transactions[0] -> StatusId == 'X'){
						$order->update_status('failed','The transaction was cancelled by the merchant');
					}
					else{
						$order->update_status('failed','Unknown error');
					}
					wp_redirect($this->get_return_url( $order ));
				
				}
				else{
					die (json_encode($resultObj));
					//die ($postargs.' '.$resultObj->ErrorText);
				}

            
					wp_redirect($this->get_return_url( $order ));
            
        }
		
        /**
     * Process the payment and return the result
     **/
    
    function get_viva_order_key($the_order){
			 // The POST URL and parameters
		$request =  'https://www.vivapayments.com/api/orders';	// production environment URL
		if($this -> settings['demo_mode']=='yes'){
			$request =  'http://demo.vivapayments.com/api/orders';	// demo environment URL
		}
		// Your merchant ID and API Key can be found in the 'Security' settings on your profile.
		$MerchantId = $this -> settings['Merchant_ID'];// '853c3324-25a1-4a7d-a466-d87eb25339ca';
		$APIKey = $this -> settings['API_Key'];//'weO.XU'; 	
		
		//Set the Payment Amount
		$Amount = ((float) $the_order->get_total())*100;	// Amount in cents
		
		//Set some optional parameters (Full list available here: https://github.com/VivaPayments/API/wiki/Optional-Parameters)
		$AllowRecurring = 'false'; // This flag will prompt the customer to accept recurring payments in tbe future.
		$RequestLang = 'el-GR'; //This will display the payment page in English (default language is Greek)
		$Source = 'Default'; // This will assign the transaction to the Source with Code = "Default". If left empty, the default source will be used.
		$Email = $the_order -> billing_email;
		$Phone = $the_order -> billing_phone;
		$FullName = $the_order -> billing_first_name .' '.$the_order -> billing_last_name;
		$PaymentTimeOut = 86400;
		$MaxInstallments = 1;
		$IsPreAuth = 'true';
		$MerchantTrns = "Your reference";
		$CustomerTrns = 'Order Id: '.$the_order -> id;
		$DisableIVR = 'true';
		$DisableCash = 'true';
		$DisablePayAtHome = 'true';
		$SourceCode =  $this -> settings['source_code'];
		$Tags = http_build_query(array($the_order -> id));
		
		$postargs = 'Amount='.urlencode($Amount).'&AllowRecurring='.$AllowRecurring.'&RequestLang='.$RequestLang.'&Source='.$Source.'&Email='.$Email.'&Phone='.$Phone.'&FullName='.$FullName.'&MerchantTrns='.$MerchantTrns.'&CustomerTrns='.$CustomerTrns.'&DisableIVR='.$DisableIVR.'&DisableCash='.$DisableCash.'&DisablePayAtHome='.$DisablePayAtHome.'&SourceCode='.urlencode($SourceCode).'&Tags='.$Tags;
		
		
		// Get the curl session object
		$session = curl_init($request);
		
		
		// Set the POST options.
		curl_setopt($session, CURLOPT_POST, true);
		curl_setopt($session, CURLOPT_POSTFIELDS, $postargs);
		curl_setopt($session, CURLOPT_HEADER, true);
		curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($session, CURLOPT_USERPWD, $MerchantId.':'.$APIKey);
		curl_setopt($session, CURLOPT_SSL_CIPHER_LIST, 'TLSv1');
		
		// Do the POST and then close the session
		$response = curl_exec($session);
		
		// Separate Header from Body
		$header_len = curl_getinfo($session, CURLINFO_HEADER_SIZE);
		$resHeader = substr($response, 0, $header_len);
		$resBody =  substr($response, $header_len);
		
		curl_close($session);
		
		// Parse the JSON response
		try {
			if(is_object(json_decode($resBody))){
			  	$resultObj=json_decode($resBody);
			}else{
				preg_match('#^HTTP/1.(?:0|1) [\d]{3} (.*)$#m', $resHeader, $match);
						throw new Exception("API Call failed! The error was: ".trim($match[1]));
			}
		} catch( Exception $e ) {
			echo $e->getMessage();
		}
		
		if ($resultObj->ErrorCode==0){	//success when ErrorCode = 0
			$orderId = $resultObj->OrderCode;
			return   $orderId;
		}	
		else{
			return 'error';// . $resultObj->ErrorText;
		}


 }
    
    function process_payment( $order_id ) {
	global $woocommerce;
	$order = new WC_Order( $order_id );

	// Mark as on-hold (we're awaiting the cheque)
	$order->update_status('pending','Pending Viva Payment');
	// Reduce stock levels
	//$order->reduce_order_stock();

	
	
	$viva_id = $this -> get_viva_order_key($order);
	
	if ($viva_id == 'error'){
		
	}
	else{
		// Return thankyou redirect
		if($this -> settings['demo_mode']=='yes'){
			return array(
				'result' => 'success',
				'redirect' => 'http://demo.vivapayments.com/web/newtransaction.aspx?ref='.$viva_id
			);
		}else{
			return array(
				'result' => 'success',
				'redirect' => 'https://www.vivapayments.com/web/newtransaction.aspx?ref='.$viva_id
			);
		}
	}
}
 
 		/*
 		public function receipt_page( $order ) {
            
            $viva_id = $this -> get_viva_order_key($order);
            
            $iframe = '<iframe src="'.$url.'" width="100%" height="700px" scrolling="yes" frameBorder="0">';
			$iframe .= '<p>Browser unable to load iFrame</p>';
			$iframe .= '</iframe>';
			echo($iframe);
        }
		*/
 
 
    
}
}



   /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_Viva_gateway($methods) {
        $methods[] = 'WC_Viva';
        return $methods;
    }
 
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_Viva_gateway' );
    
    ?>