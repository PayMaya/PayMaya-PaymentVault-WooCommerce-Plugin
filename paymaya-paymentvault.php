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
    $this->supports = array();

    $this->init_form_fields();
    $this->init_settings();

    foreach($this->settings as $setting_key => $value) {
      $this->$setting_key = $value;
    }
	
	  add_action('wp_enqueue_scripts', array(&$this, 'override_frontend_scripts'));
	  add_action('wp_enqueue_scripts', array(&$this, 'paymentvault_scripts'));
	  add_action('woocommerce_api_'.strtolower(get_class($this)), array(&$this, 'paymaya_paymentvault_webhook_handler'));
		  
	  if(is_admin()) {
	    add_action( 'admin_notices', array( $this, 'do_ssl_check' ) );
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
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
      'public_facing_api_key' => array(
        'title'    => __( 'Public-facing API Key', 'paymaya-paymentvault' ),
        'type'     => 'text',
        'desc_tip' => __( 'Used to authenticate yourself to Payment Vault.', 'paymaya-paymentvault' ),
      ),
      'secret_api_key' => array(
        'title'    => __( 'Secret API Key', 'paymaya-paymentvault' ),
        'type'     => 'text',
        'desc_tip' => __( 'Used to authenticate yourself to PayMaya Payment Vault.', 'paymaya-paymentvault' ),
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
			$this->saved_payment_methods();
			$this->form();
			$this->save_payment_method_checkbox();
		} else {
			$this->form();
		}
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
	  wp_enqueue_script('paymentvaultjs');
		
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
		global $woocommerce;
		
		$returnURL = "";
		
		$order = new WC_Order($order_id);
			
		$wcpg = new WC_Payment_Gateway_CC();
	  $wcpgData = $wcpg->get_post_data();
		
		$pv = new \PayMayaPaymentVault\PaymentVault($this->public_facing_api_key, $this->secret_api_key, $this->environment);
		$pv->totalAmount = $wcpg->get_order_total();
		$pv->currency = $order->order_currency;
		
		$pv->RedirectionURL->success = $this->get_return_url($order);
	  $pv->RedirectionURL->failure = $order->get_checkout_payment_url(false);
	  $pv->RedirectionURL->cancel = $this->get_return_url($order);
	  
	  $tokenResp = json_decode(base64_decode($wcpgData['wc-gateway-id']));
			
	  $pv->setTokenState((isset($tokenResp->state)? $tokenResp->state : ' '));
	  
	  $pv->CustomerDetails->firstName = $wcpgData['billing_first_name'];
	  $pv->CustomerDetails->middleName = " ";
	  $pv->CustomerDetails->lastName = $wcpgData['billing_last_name'];
	  $pv->CustomerDetails->phone = $wcpgData['billing_phone'];
	  $pv->CustomerDetails->email = $wcpgData['billing_email'];
	  $pv->CustomerDetails->line1 = $wcpgData['billing_address_1'];
	  $pv->CustomerDetails->line2 = $wcpgData['billing_address_2'];
	  $pv->CustomerDetails->city = $wcpgData['billing_city'];
	  $pv->CustomerDetails->state = $wcpgData['billing_state'];
	  $pv->CustomerDetails->zipCode = $wcpgData['billing_postcode'];
	  $pv->CustomerDetails->countryCode = $wcpgData['billing_country'];
	  
	  //Delete Line:205-210 once it has a UI for webhook
		$webhook = $pv->getListOfWebHooks();
		if($webhook != false){
			for($i = 0; $i < count($webhook); $i++){
		    $pv->updateWebHooks($webhook[$i]->id, $webhook[$i]->name, $this->getWebHookUrl());
			}
		}
			
	  $retVal = $pv->createPayment((isset($tokenResp->paymentTokenId)? $tokenResp->paymentTokenId : ' '));
	  
	  $wcpvd = new WC_PaymentVaultData();
	  $wcpvd->order_id = $order_id;
	  $wcpvd->payment_id = $pv->getPaymentID();
	  $wcpvd->token_id = $pv->getTokenID();
	  $wcpvd->save();
	  
	  if($retVal !== false){
	    switch ($retVal->status){
		    case 'PAYMENT_SUCCESS':
			    $order->payment_complete();
			    return array('result' => 'success', 'redirect' => $this->get_return_url($order));
			    break;
		    case 'PENDING_PAYMENT':
			    if($pv->is3DSEnabled() == true){
				    return array('result' => 'success', 'redirect' => urldecode($retVal->verificationUrl));
			    }
			    else{
				    $order->update_status('on-hold', __('Awaiting cheque payment', 'woothemes'));
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
	
	public function paymaya_paymentvault_webhook_handler(){
			
	  if($this->environment == 'yes'){
	  	$this->debugWebHook();
	  }
	  
		$postData = (empty($_POST)? new stdClass() : json_decode(html_entity_decode($_POST)));
			
	  $wcpvd = new WC_PaymentVaultData();
	  
	  $results = $wcpvd->getRow('payment_id', $postData->id);
	  
	  if($results == true){
	  	$order = new WC_Order($wcpvd->order_id);
	  
	    if(isset($postData->status) == true){
	      switch ($postData->status){
		      case 'PAYMENT_SUCCESS':
			      $order->payment_complete();
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
	
	public function getWebHookUrl(){
	  global $woocommerce;
			
	  if($woocommerce->version > 2.0){
	  	$path = '/wc-api/paymaya_paymentvault';
	  }
	  else{
	    $path = '/?wc-api=paymaya_paymentvault';
	  }
	  
	  $httpXforwarded = (isset($_SERVER['HTTP_X_FORWARDED_PORT'])? $_SERVER['HTTP_X_FORWARDED_PORT'] : 0);
			  
	  $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443 || $httpXforwarded == 443) ? "https://" : "http://";
	  $url = $protocol . $_SERVER['HTTP_HOST'] . $path;
	  
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
	
	private function debugWebHook(){
	  $myfile = fopen(__DIR__ . "/webhook_dump.txt", "a+");
	  fwrite($myfile, "--------------------------------------------------------------------------------------------------------------------------". "\n");
	  $h = "8";
	  $hm = $h * 60;
	  $ms = $hm * 60;
	  $txt = "TIME:" . gmdate("m-d-y h:i:sa", time()+($ms)) . "\n";
	  fwrite($myfile, $txt);
	  $servertxt = $_SERVER;
	  fwrite($myfile, "SERVER DATA: " . json_encode($servertxt)  . "\n");
	  $gettxt = $_GET;
	  fwrite($myfile, "GET DATA: " . json_encode($gettxt) . "\n");
	  $posttxt = $_POST;
	  fwrite($myfile, "POST DATA: " . json_encode($posttxt) . "\n");
	  fwrite($myfile, "--------------------------------------------------------------------------------------------------------------------------". "\n");
	  fclose($myfile);
	}
}
