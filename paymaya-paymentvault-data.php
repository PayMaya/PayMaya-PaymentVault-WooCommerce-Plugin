<?php
	
	if ( ! defined( 'ABSPATH' ) ) {
		exit; // Exit if accessed directly
	}
	
	class WC_PaymentVaultData {
		public $payment_id;
		public $token_id;
		public $order_id;
		
		function __construct() {
			
		}
		
		function __destruct() {
			$this->order_id = null;
			$this->payment_id = null;
			$this->token_id = null;
		}
		
		public function getRow($col, $value){
			global $wpdb;
			
			$wpdb->hide_errors();
			
			$dbname = $wpdb->prefix . 'woocommerce_paymentvault_data';
			
			$query = $wpdb->prepare("SELECT * FROM $dbname WHERE $col = '%s' ORDER BY id DESC", $value );
			$results = $wpdb->get_row($query);
			
			if($wpdb->num_rows >= 1){
				$this->payment_id = $results->payment_id;
				$this->token_id = $results->token_id;
				$this->order_id = $results->order_id;
				return true;
			}
			
			return false;
		}
		
		public function save(){
			global $wpdb;
			
			$data = array(
				'order_id' => $this->order_id,
				'payment_id' => $this->payment_id,
				'token_id' => $this->token_id
			);
			
			return $wpdb->insert( 'wp_woocommerce_paymentvault_data', $data, array('%d','%s','%s'));
		}
	}