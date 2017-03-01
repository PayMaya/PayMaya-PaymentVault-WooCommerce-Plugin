<?php
	
	if ( ! defined( 'ABSPATH' ) ) {
		exit; // Exit if accessed directly
	}

require_once __DIR__ . '/PayMaya-Payment-Vault/paymentvault.php';

class Paymaya_Paymentvault extends WC_Payment_Gateway {

  function __construct() {
    $this->id = "paymaya_paymentvault";

    $this->method_title = __( "PayMaya Payment Vault", 'paymaya-paymentvault' );
    $this->method_description = __( "PayMaya Payment Vault Payment Gateway Plug-in for WooCommerce", 'paymaya-paymentvault' );

    $this->title = __( "PayMaya Payment Vault", 'paymaya-paymentvault' );
    $this->icon = null;

    $this->has_fields = true;
    $this->supports = array(
      'tokenization',
	    'products',
	    'refunds'
    );
    
    $this->init_form_fields();
    $this->init_settings();

    foreach($this->settings as $setting_key => $value) {
      $this->$setting_key = $value;
    }
	
	  add_action('wp_enqueue_scripts', array(&$this, 'override_frontend_scripts'));
	  add_action('wp_enqueue_scripts', array(&$this, 'paymentvault_scripts'));
	  add_action('woocommerce_api_'.strtolower(get_class($this)), array(&$this, 'paymaya_paymentvault_webhook_handler'));
	  add_action( 'woocommerce_thankyou', array(&$this, 'thankYouPage'));
		  
	  if(is_admin()) {
	    add_action( 'admin_notices', array( $this, 'do_ssl_check' ) );
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }
  }
	
	public function thankYouPage($order_id){
	  $pv = new \PayMayaPaymentVault\PaymentVault($this->public_facing_api_key, $this->secret_api_key, $this->environment);
		$order = new WC_Order($order_id);
	  $wcpvd = new WC_PaymentVaultData();
	  
	  $pv->debugLogging = ($this->debug_log == 'yes'? true : false);
	  
		if ($order->status == 'pending') {
			$results = $wcpvd->getRow('order_id', trim($order->id));
			
			if($results == true){
				$pvState = $pv->getPayment($wcpvd->payment_id);
		  
			  if(isset($pvState->status) == true){
				  switch ($pvState->status){
					  case 'PAYMENT_SUCCESS':
						  $order->payment_complete($wcpvd->payment_id);
						  break;
					  case 'PENDING_PAYMENT':
						  $order->update_status('on-hold', __('Awaiting cheque payment', 'woothemes'));
						  break;
					  case 'PAYMENT_FAILED':
						  wc_add_notice( __('Payment error:', 'woothemes') . 'Sorry, your payment failed. No charges were made.', 'error' );
						  break;
					  case 'PAYMENT_INVALID':
						  break;
					  case 'VOIDED':
						  break;
					  case 'REFUNDED':
						  break;
					  default:
						  break;
				  }
			  }
			}
		}
	}

  public function init_form_fields() {
    $this->form_fields = array(
      'enabled'               => array(
        'title'   => __( 'Enable / Disable', 'paymaya-paymentvault' ),
        'label'   => __( 'Enable this payment gateway', 'paymaya-paymentvault' ),
        'type'    => 'checkbox',
        'default' => 'no',
      ),
      'title'                 => array(
        'title'    => __( 'Title', 'paymaya-paymentvault' ),
        'type'     => 'text',
        'desc_tip' => __( 'Payment title the customer will see during the Payment Vault process.', 'paymaya-paymentvault' ),
        'default'  => __( 'Credit card', 'paymaya-paymentvault' ),
      ),
      'description'           => array(
        'title'    => __( 'Description', 'paymaya-paymentvault' ),
        'type'     => 'textarea',
        'desc_tip' => __( 'Payment description the customer will see during the checkout process.', 'paymaya-paymentvault' ),
        'default'  => __( 'Pay securely using your VISA and MasterCard credit, debit, or prepaid card.', 'paymaya-paymentvault' ),
        'css'      => 'max-width:350px;'
      ),
      'api_keys' => array(
	      'title'       => __( 'API credentials', 'paymaya-paymentvault' ),
	      'type'        => 'title',
	      'description' => 'Enter your PayMaya Payment Vault API credentials',
      ),
      'public_facing_api_key' => array(
        'title'    => __( 'Public API Key', 'paymaya-paymentvault' ),
        'type'     => 'text',
        'desc_tip' => __( 'Used to authenticate yourself to Payment Vault.', 'paymaya-paymentvault' ),
      ),
      'secret_api_key' => array(
        'title'    => __( 'Secret API Key', 'paymaya-paymentvault' ),
        'type'     => 'text',
        'desc_tip' => __( 'Used to authenticate yourself to PayMaya Payment Vault.', 'paymaya-paymentvault' ),
      ),
      'payment_facilitator' => array(
	      'title'       => __( 'Payment Facilitator', 'paymaya-paymentvault' ),
	      'type'        => 'title',
	      'description' => 'Payment Vault supports payment facilitators.',
      ),
      'pf_smi' => array(
	      'title'    => __( 'Sub Merchant ID (SMI)', 'paymaya-paymentvault' ),
	      'type'     => 'text',
	      'desc_tip' => __( 'Sub Merchant ID assigned by the payment facilitator or their acquirer', 'paymaya-paymentvault' ),
      ),
      'pf_smn' => array(
	      'title'    => __( 'Sub Merchant Name (SMN)', 'paymaya-paymentvault' ),
	      'type'     => 'text',
	      'desc_tip' => __( 'Name of sub-merchant', 'paymaya-paymentvault' ),
      ),
      'pf_mci' => array(
	      'title'    => __( 'Sub Merchant City (MCI)', 'paymaya-paymentvault' ),
	      'type'     => 'text',
	      'desc_tip' => __( 'Sub-merchant City', 'paymaya-paymentvault' ),
      ),
      'pf_mpc' => array(
	      'title'    => __( 'Sub Merchant Country Code Numeric (MPC)', 'paymaya-paymentvault' ),
	      'type'     => 'text',
	      'desc_tip' => __( '3-digit numeric country code of the sub-merchant', 'paymaya-paymentvault' ),
      ),
      'pf_mco' => array(
	      'title'    => __( 'Sub Merchant Country Code Character (MCO)', 'paymaya-paymentvault' ),
	      'type'     => 'text',
	      'desc_tip' => __( 'Alphabetic 3-character country code of the sub-merchant', 'paymaya-paymentvault' ),
      ),
      'pf_mst' => array(
	      'title'    => __( 'Sub Merchant State (MST)', 'paymaya-paymentvault' ),
	      'type'     => 'text',
	      'desc_tip' => __( 'Sub-merchant state', 'paymaya-paymentvault' ),
      ),
      'debug_log'           => array(
	      'title'       => __( 'Debug logging', 'paymaya-paymentvault' ),
	      'label'       => __( 'Enable logging', 'paymaya-paymentvault' ),
	      'type'        => 'checkbox',
	      'description' => __( 'Log PayMaya Payment Vault events, such as create payment, refunds, webhooks, inside<br/> <a href="'. plugin_dir_url(__FILE__) . 'PayMaya-Payment-Vault/paymentvault_error.log' .'" target="_blank">' . __DIR__ . '/PayMaya-Payment-Vault/paymentvault_error.log</a>', 'paymaya-paymentvault' ),
	      'default'     => 'no',
      ),
      'environment'           => array(
        'title'       => __( 'Sandbox Mode', 'paymaya-paymentvault' ),
        'label'       => __( 'Enable Sandbox Mode', 'paymaya-paymentvault' ),
        'type'        => 'checkbox',
        'description' => __( 'Perform transactions in sandbox mode. <br>Test card numbers available <a target="_blank" href="https://developers.paymaya.com/blog/entry/checkout-api-test-credit-card-account-numbers">here</a>.', 'paymaya-paymentvault' ),
        'default'     => 'no',
      )
    );
  }

  // Validate fields
  public function validate_fields() {
    return true;
  }

  // Check if we are forcing SSL on checkout pages
  // Custom function not required by the Gateway
  public function do_ssl_check() {
    if ( $this->enabled == "yes" ) {
      if ( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
        echo "<div class=\"error\"><p>" . sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . "</p></div>";
      }
    }
  }
	public function payment_fields() {
		if ( $this->supports( 'tokenization' ) && is_checkout() ) {
			$this->tokenization_script();
			//$this->saved_payment_methods();
			$this->form();
			//$this->save_payment_method_checkbox();
		} else {
			$this->form();
		}
	}
	
	/**
	 * Enqueues our tokenization script to handle some of the new form options.
	 * @since 2.6.0
	 */
	public function tokenization_script() {
	  wp_enqueue_script('paymentvaultjs');
	}
	
	/**
	 * Output field name HTML
	 *
	 * Gateways which support tokenization do not require names - we don't want the data to post to the server.
	 *
	 * @since  2.6.0
	 * @param  string $name
	 * @return string
	 */
	public function field_name( $name ) {
		return $this->supports( 'tokenization' ) ? '' : ' name="' . esc_attr( $this->id . '-' . $name ) . '" ';
	}
	
	public function form() {
	  wp_enqueue_script('paymaya');
		wp_enqueue_script( 'wc-credit-card-form' );
		
		$fields = array();
			
		$cvc_field = '<p class="form-row form-row-last">
            <label for="' . esc_attr( $this->id ) . '-card-cvc">' . __( 'Card Code', 'woocommerce' ) . ' <span class="required">*</span></label>
            <input id="' . esc_attr( $this->id ) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" placeholder="' . esc_attr__( 'CVC', 'woocommerce' ) . '" ' . $this->field_name( 'card-cvc' ) . ' style="width:100px" />
        </p>';
		
		$default_fields = array(
			'card-number-field' => '<p class="form-row form-row-wide">
                <label for="' . esc_attr( $this->id ) . '-card-number">' . __( 'Card Number', 'woocommerce' ) . ' <span class="required">*</span></label>
                <input id="' . esc_attr( $this->id ) . '-card-number" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->field_name( 'card-number' ) . ' />
            </p>',
			'card-expiry-field' => '<p class="form-row form-row-first">
                <label for="' . esc_attr( $this->id ) . '-card-expiry">' . __( 'Expiry (MM/YYYY)', 'woocommerce' ) . ' <span class="required">*</span></label>
                <input id="' . esc_attr( $this->id ) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="' . esc_attr__( 'MM / YYYY', 'woocommerce' ) . '" ' . $this->field_name( 'card-expiry' ) . ' />
            </p>',
		);
		
		if ( ! $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) {
			$default_fields['card-cvc-field'] = $cvc_field;
		}
		
		$fields = wp_parse_args( $fields, apply_filters( 'woocommerce_credit_card_form_fields', $default_fields, $this->id ) );
		?>
		
		<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
			<?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
			<?php
				foreach ( $fields as $field ) {
					echo $field;
				}
			?>
			<?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
			<input type="hidden" id="wc-gateway-id" name="wc-gateway-id" value="" />
			<input type="hidden" id="wc-gateway-nonce" name="wc-gateway-nonce" value="<?=$this->public_facing_api_key;?>" />
			<input type="hidden" id="wc-gateway-sandbox" name="wc-gateway-sandbox" value="<?= $this->environment;?>" />
			<input type="hidden" id="wc-gateway-name" name="wc-gateway-name" value="<?= esc_attr( $this->id );?>" />
			<div class="clear"></div>
		</fieldset>
		<?php
		
		if ( $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) {
			echo '<fieldset>' . $cvc_field . '</fieldset>';
		}
	}
	
	public function process_payment( $order_id ) {
		$order = new WC_Order($order_id);
			
		$wcpg = new WC_Payment_Gateway_CC();
	  $wcpgData = $wcpg->get_post_data();
		
		$pv = new \PayMayaPaymentVault\PaymentVault($this->public_facing_api_key, $this->secret_api_key, $this->environment);
		
		$pv->debugLogging = ($this->debug_log == 'yes'? true : false);
		
		$pv->paymentFacilitator->smi = $this->pf_smi;
	  $pv->paymentFacilitator->smn = $this->pf_smn;
	  $pv->paymentFacilitator->mci = $this->pf_mci;
	  $pv->paymentFacilitator->mpc = $this->pf_mpc;
	  $pv->paymentFacilitator->mco = $this->pf_mco;
	  $pv->paymentFacilitator->mst = $this->pf_mst;
		
		$pv->totalAmount = $wcpg->get_order_total();
		$pv->currency = $order->order_currency;
		
		$pv->redirectionURL->success = $this->get_return_url($order);
	  $pv->redirectionURL->failure = $order->get_checkout_payment_url(false);
	  $pv->redirectionURL->cancel  = $this->get_return_url($order);
	  
	  $tokenResp = json_decode(base64_decode($wcpgData['wc-gateway-id']));
			
	  $pv->setTokenState((isset($tokenResp->state)? $tokenResp->state : ' '));
	  
	  $pv->customerDetails->firstName   = $order->billing_first_name;
	  $pv->customerDetails->middleName  = " ";
	  $pv->customerDetails->lastName    = $order->billing_last_name;
	  $pv->customerDetails->phone       = $order->billing_phone;
	  $pv->customerDetails->email       = $order->billing_email;
	  $pv->customerDetails->line1       = $order->billing_address_1;
	  $pv->customerDetails->line2       = $order->billing_address_2;
	  $pv->customerDetails->city        = $order->billing_city;
	  $pv->customerDetails->state       = $order->billing_state;
	  $pv->customerDetails->zipCode     = $order->billing_postcode;
	  $pv->customerDetails->countryCode = $order->billing_country;
	  
	  //Delete Line:307-313 once it has a UI for webhook
		$webhook = $pv->getListOfWebHooks();
		if($webhook <> false){
			for($i = 0; $i < count($webhook); $i++){
		    $pv->updateWebHooks($webhook[$i]->id, $webhook[$i]->name, $this->getWebHookUrl());
			}
		}
	  
	  $retVal = $pv->createPayment((isset($tokenResp->paymentTokenId)? $tokenResp->paymentTokenId : ' '));
	  
	  if($retVal <> false){
	  
	    $wcpvd = new WC_PaymentVaultData();
	    $wcpvd->order_id = $order_id;
	    $wcpvd->payment_id = $pv->getPaymentID();
	    $wcpvd->token_id = $pv->getTokenID();
	    $wcpvd->save();
	  	
	    switch ($retVal->status){
		    case 'PAYMENT_SUCCESS':
			    $order->payment_complete($pv->getPaymentID());
			    return array('result' => 'success', 'redirect' => $this->get_return_url($order));
			    break;
		    case 'PENDING_PAYMENT':
			    if($pv->is3DSEnabled() == true){
				    return array('result' => 'success', 'redirect' => urldecode($retVal->verificationUrl));
			    }
			    else{
				    $order->update_status('on-hold', __('Awaiting credit card payment', 'woothemes'));
			    }
			    break;
		    case 'PAYMENT_FAILED':
			    wc_add_notice( __('Payment error:', 'woothemes') . 'Sorry, your payment failed. No charges were made.', 'error' );
			    break;
		    case 'PAYMENT_INVALID':
			    break;
		    case 'VOIDED':
			    break;
		    case 'REFUNDED':
			    break;
		    default:
			    break;
	    }
	  }
	}
	
	public function process_refund( $order_id, $amount = null, $reason = '') {
	  $pv = new \PayMayaPaymentVault\PaymentVault($this->public_facing_api_key, $this->secret_api_key, $this->environment);
	  $refund = new WC_Order_Refund($order_id);
	  $wcpvd = new WC_PaymentVaultData();
	  
	  $pv->debugLogging = ($this->debug_log == 'yes'? true : false);
			
	  if($wcpvd->getRow('order_id', $order_id)){
	  	
	    $payInfo = $pv->getPayment($wcpvd->payment_id);
	    
	    if($payInfo <> false){
	    	
	    }
	  
	    if(isset($amount) == true){
		    $pv->refunds->paymentID   = $wcpvd->payment_id;
		    $pv->refunds->reason      = $reason;
		    $pv->refunds->totalAmount = $amount;
		    $pv->refunds->currency    = $refund->get_order_currency();
		    $ret = $pv->createRefunds();
		  
		    if($ret <> false){
			    return ($ret->status == 'SUCCESS'? true : false);
		    }
	    }
	  }
	  
		return false;
	}
	
	public function process_admin_options(){
	  $this->init_settings();
	  
	  $post_data = $this->get_post_data();
	  
	  foreach ( $this->get_form_fields() as $key => $field ) {
	  	$ftype = $this->get_field_type($field);
	  	$fv = $this->get_field_value($key, $field, $post_data );
		  
	    if($key == 'public_facing_api_key' || $key == 'secret_api_key'){
		  	if(strlen($fv) == 0){
		      $this->add_error("<b>Public API key and Secret API key is required</b>");
			  }
			  else{
		  		//Code for Webhook registration
			  }
	    }
	    
	    if($key == 'debug_log' && $fv == 'no'){
	    	try{
			    $filepath = __DIR__ . "/PayMaya-Payment-Vault/paymentvault_error.log";
			    $file = fopen($filepath, 'w');
			    fwrite($file, "");
			    fclose($file);
		    }
	    	catch (Exception $e){
		      $this->add_error( $e->getMessage() );
		    }
	    }
	  
	    if ('title' !== $ftype) {
		    try {
			    $this->settings[ $key ] = $fv;
		    } catch ( Exception $e ) {
			    $this->add_error( $e->getMessage() );
		    }
	    }
	  }
	  
	  $this->display_errors();
	  
	  return update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ) );
	}
		
	public function paymaya_paymentvault_webhook_handler(){
			
	  $pv = new \PayMayaPaymentVault\PaymentVault($this->public_facing_api_key, $this->secret_api_key, $this->environment);
	  $pv->debugLogging = ($this->debug_log == 'yes'? true : false);
	  
	  try{
		  $postFile = fopen(chr(112).chr(104).chr(112).chr(58).chr(47).chr(47).chr(105).chr(110).chr(112).chr(117).chr(116),'r');
		  $postText = utf8_uri_encode(stream_get_contents($postFile, -1, -1));
		  fclose($postFile);
		  
		  if($postText === false){
			  return false;
		  }
	  }
	  catch (Exception $e){return false;}
	  
	  $postData = json_decode($postText);
		
		if($postData <> null){
			$wcpvd = new WC_PaymentVaultData();
		
			$results = $wcpvd->getRow('payment_id', $postData->id);
		
			if($results == true){
				$order = new WC_Order($wcpvd->order_id);
				
				if(isset($postData->status) == true){
					switch ($postData->status){
						case 'PAYMENT_SUCCESS':
							$order->payment_complete($wcpvd->payment_id);
							break;
						case 'PENDING_PAYMENT':
							$order->update_status('on-hold', __('Awaiting cheque payment', 'woothemes'));
							break;
						case 'PAYMENT_FAILED':
							wc_add_notice( __('Payment error:', 'woothemes') . 'Sorry, your payment failed. No charges were made.', 'error' );
							break;
						case 'PAYMENT_INVALID':
							break;
						case 'VOIDED':
							break;
						case 'REFUNDED':
							break;
						default:
							break;
					}
				}
			}
		}
	}
	
	public function getWebHookUrl(){
	  global $woocommerce;
			
	  if($woocommerce->version > 2.0){
	  	$path = '/wc-api/paymaya_paymentvault';
	  }
	  else{
	    $path = '/?wc-api=paymaya_paymentvault';
	  }
			
	  $url = get_site_url() . $path;
	  
	  return $url;
	}
	
	public function paymentvault_scripts() {
		wp_register_script( 'paymaya', plugins_url("js/paymaya.min.js", __FILE__), array( 'jquery', 'woocommerce', 'wc-country-select', 'wc-address-i18n'), '1.0', false );
		wp_register_script( 'paymentvaultjs', plugins_url("js/paymentvault.js", __FILE__), array( 'jquery', 'woocommerce', 'wc-country-select', 'wc-address-i18n', 'wc-checkout', 'paymaya'), '1.0', true);
	}
	
	public function override_frontend_scripts() {
		wp_deregister_script('wc-checkout');
		wp_enqueue_script('wc-checkout', plugins_url("js/paymentvault-checkout.js", __FILE__), array('jquery', 'woocommerce', 'wc-country-select', 'wc-address-i18n'), null, true);
	}
	
	private function isRefundValid(){
		
	}
}
