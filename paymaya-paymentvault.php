<?php

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

    add_action( 'admin_notices', array( $this, 'do_ssl_check' ) );
    //add_action( 'admin_notices', array( $this, 'register_webhook' ) );

    if(is_admin()) {
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
			<div class="clear"></div>
		</fieldset>
		<?php
		
		if ( $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) {
			echo '<fieldset>' . $cvc_field . '</fieldset>';
		}
	}
	
	public function process_payment( $order_id ) {
		global $woocommerce;
		
		$order = new WC_Order( $order_id );
			
		$a = new WC_Payment_Gateway_CC();
		$c = $a->get_post_data();
		
		$d = new \PayMayaPaymentVault\CardDetails();
		$d->cardNumber = $c['paymaya_paymentvault-card-number'];
		
		$r = new \PayMayaPaymentVault\PaymentVault($this->public_facing_api_key, $this->secret_api_key, $this->environment);
		$r->totalAmount = $a->get_order_total();
		$r->currency = 'PHP';
		$r->CardDetails->cardNumber = '5123456789012346';
	  $r->CardDetails->cardExpiryMonth = '05';
	  $r->CardDetails->cardExpiryYear = '2017';
	  $r->CardDetails->cardCVC = '111';
	  $r->CustomerDetails->firstName = 'Dennis';
	  $r->CustomerDetails->middleName = 'Darang';
	  $r->CustomerDetails->lastName = 'Colinares';
	  $r->CustomerDetails->phone = '+63(2)1234567890';
	  $r->CustomerDetails->email = 'me@yahoo.com';
	  $r->CustomerDetails->line1 = 'Boni';
	  $r->CustomerDetails->line2 = 'Boni';
	  $r->CustomerDetails->city = 'Mandaluyong City';
	  $r->CustomerDetails->state = 'Metro Manila';
	  $r->CustomerDetails->zipCode = '12345';
	  $r->CustomerDetails->countryCode = 'PH';
			
	  $t = $r->createPayment();
			
		//wc_add_notice( __('Payment error:', 'woothemes') . serialize($a->get_post_data()), 'error' );
		wc_add_notice( __('Payment error:', 'woothemes') . serialize($t), 'error' );
	  
		return;
		
		/*return array(
	  'result'   => 'success',
	  'redirect' => $this->get_return_url($order),
	);*/
	}

}
