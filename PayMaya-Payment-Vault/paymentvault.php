<?php
	
	namespace PayMayaPaymentVault;
	
	use Curl\Curl;
	
	require_once __DIR__ . "/Curl/Curl.php";
	require_once __DIR__ . "/Curl/ArrayUtil.php";
	require_once __DIR__ . "/Curl/CaseInsensitiveArray.php";
	require_once __DIR__ . "/Curl/MultiCurl.php";
	
	class CardDetailsObject {
		public $cardNumber;
		public $cardExpiryMonth;
		public $cardExpiryYear;
		public $cardCVC;
		public $cardType;
		public $cardId;
		
		function __construct() {
			
		}
		
		function __destruct() {
			$this->cardNumber = null;
			$this->cardExpiryMonth = null;
			$this->cardExpiryYear = null;
			$this->cardCVC = null;
			$this->cardType = null;
			$this->cardId = null;
		}
		
		function __get( $name ) {
			return $this->$name;
		}
		
		function  __set( $name, $value ) {
			$this->$name = str_replace(' ', '', trim($value));
		}
	}
	
	class RedirectionURLObject{
		public $success;
		public $failure;
		public $cancel;
		
		function __construct() {
			
		}
		
		function __destruct() {
			$this->success = null;
			$this->failure = null;
			$this->cancel = null;
		}
	}
	
	class RefundObject{
		public $refundID;
		public $paymentID;
		public $reason;
		public $totalAmount;
		public $currency;
		public $status;
		
		function __construct() {
			
		}
		
		function __destruct() {
			$this->refundID = null;
			$this->paymentID = null;
			$this->reason = null;
			$this->totalAmount = null;
			$this->currency = null;
			$this->status = null;
		}
	}
	
	class CustomersDetailsObject {
		public $firstName;
		public $middleName;
		public $lastName;
		public $phone;
		public $email;
		public $sex;
		public $birthday;
		public $line1;
		public $line2;
		public $city;
		public $state;
		public $zipCode;
		public $countryCode;
		
		function __construct() {
			
		}
		
		function __destruct() {
			$this->firstName = null;
			$this->middleName = null;
			$this->lastName = null;
			$this->phone = null;
			$this->email = null;
			$this->sex = null;
			$this->birthday = null;
			$this->line1 = null;
			$this->line2 = null;
			$this->city = null;
			$this->state = null;
			$this->zipCode = null;
			$this->countryCode = null;
		}
		
		function __get( $name ) {
			return $this->$name;
		}
		
		function  __set( $name, $value ) {
			$this->$name = trim($value);
		}
	}
	
	class PaymentVault {
		
		private $paymentId;
		private $customerId;
		private $tokenId;
		private $tokenState;
		private $pk_key;
		private $sk_key;
		private $url;
		private $is3dsEnabled = false;
		private $paymayaUrl = array(
			"sandbox" => 'https://pg-sandbox.paymaya.com/payments',
			"production" => 'https://pg.paymaya.com/payments'
		);
		private $urlPath = array(
			'createToken' => '/v1/payment-tokens',
			'payments' => '/v1/payments',
			'webhooks' => '/v1/webhooks',
			'refunds' => '/v1/payments'
		);
		
		public $totalAmount;
		public $currency;
		public $CustomerDetails;
		public $CardDetails;
		public $RedirectionURL;
		public $Refunds;
		
		function __construct($publicKey, $secretKey, $env) {
			$this->pk_key = trim($publicKey);
			$this->sk_key = trim($secretKey);
			$this->url = $this->paymayaUrl[($env == "yes"? "sandbox" : "production")];
			
			$this->CustomerDetails = new CustomersDetailsObject();
			$this->CardDetails = new CardDetailsObject();
			$this->RedirectionURL = new RedirectionURLObject();
			$this->Refunds = new RefundObject();
		}
		
		function __destruct() {
			$this->CustomerDetails = null;
			$this->CardDetails = null;
			$this->RedirectionURL = null;
		}
		
		function __get( $name ) {
			return $this->$name;
		}
		
		function  __set( $name, $value ) {
			$this->$name = $value;
		}
		
		public function hasToken(){
			return (strlen($this->tokenId) > 150? true : false);
		}
		
		public function hasPaymentId(){
			return (strlen($this->paymentId) > 0? true : false);
		}
		
		public function is3DSEnabled(){
			if($this->is3dsEnabled == true){
				return true;
			}
			return false;
		}
		
		public function getToken(){
			$ch = & $this->getCurlInstance();
			$ch->post($this->url . $this->urlPath['createToken'], json_encode($this->generateReqCreateToken()));
			
			if($ch->error){
				$retval = false;
			}
			else{
				$retval = $ch->response;
				$this->tokenId = (isset($retval->paymentTokenId)? $retval->paymentTokenId : ' ');
				$this->tokenState = (isset($retval->state)? $retval->state : ' ');
			}
			
			$ch->close();
			
			return $retval;
		}
		
		public function createPayment(){
			$numargs = func_num_args();
			
			if($numargs > 0){
				$this->tokenId = func_get_arg(0);
			}
			else{
				$this->getToken();
			}
			
			if ($this->hasToken() == false){
				return false;
			}
			
			$ch = & $this->getCurlInstance();
			$ch->post($this->url . $this->urlPath['payments'], json_encode($this->generateReqCreatePayment()));
			
			if ($ch->error) {
				$retval = false;
			} else {
				$retval = $ch->response;
				$this->paymentId = $retval->id;
				$this->is3dsEnabled = (isset($retval->verificationUrl) == true? true : false);
			}
			
			$ch->close();
			
			return $retval;
		}
		
		public function getPayment(){
			$numargs = func_num_args();
			$paymentId = '';
			
			if($numargs > 0){
				$this->paymentId = func_get_arg(0);
			}
			
			if ($this->hasPaymentId() == false){
				return false;
			}
			
			$ch = & $this->getCurlInstance();
			$ch->get($this->url . $this->urlPath['payments'] . chr(47) . $this->paymentId);
			
			if($ch->error){
				$retval = false;
			}
			else{
				$retval = $ch->response;
			}
			
			$ch->close();
			
			return $retval;
		}
		
		public function getPaymentID(){
			return $this->paymentId;
		}
		
		public function getTokenID(){
			return $this->tokenId;
		}
		
		public function setTokenId($value){
			$this->tokenId = $value;
		}
		
		public function getTokenState(){
			return $this->tokenState;
		}
		
		public function setTokenState($value){
			$this->tokenState = $value;
		}
		
		public function registerWebHook($name, $url){
			$ch = & $this->getCurlInstance();
			
			$data = array(
				'name' => $name,
				'callbackUrl' => $url
			);
			
			$ch->post($this->url . $this->urlPath['webhooks'], json_encode($data));
			
			if($ch->error){
				$retval = false;
			}
			else{
				$retval = $ch->response;
			}
			
			$ch->close();
			
			return $retval;
		}
		
		public function getListOfWebHooks(){
			$ch = & $this->getCurlInstance();
			$ch->get($this->url . $this->urlPath['webhooks']);
			
			if($ch->error){
				$retval = false;
			}
			else{
				$retval = $ch->response;
			}
			
			$ch->close();
			
			return $retval;
		}
		
		public function updateWebHooks($webhookID,$name, $url){
			$ch = & $this->getCurlInstance();
			
			$data = array(
				'name' => $name,
				'callbackUrl' => $url
			);
			
			$ch->put($this->url . $this->urlPath['webhooks'] . chr(47) . $webhookID, $data);
			
			if($ch->error){
				$retval = false;
			}
			else{
				$retval = $ch->response;
			}
			
			$ch->close();
			
			return $retval;
		}
		
		public function getWebHooks($webhookID){
			$ch = & $this->getCurlInstance();
			$ch->get($this->url . $this->urlPath['webhooks'] . chr(47) . $webhookID);
			
			if($ch->error){
				$retval = false;
			}
			else{
				$retval = $ch->response;
			}
			
			$ch->close();
			
			return $retval;
		}
		
		public function createRefunds(){
			$ch = & $this->getCurlInstance();
			
			settype($this->Refunds->totalAmount,'float');
			
			$data = array(
				'reason' => $this->Refunds->reason,
				'totalAmount' => array(
					'amount' => $this->Refunds->totalAmount,
					'currency' => $this->Refunds->currency
				)
			);
			
			$ch->post($this->url . $this->urlPath['refunds'] . chr(47) . $this->Refunds->paymentID . chr(47) . "refunds", $data);
			
			if($ch->error){
				$retval = false;
			}
			else{
				$obj = $ch->response;
				$retval = new RefundObject();
				$retval->refundID = $this->isObjExist($obj->id, '');
				$retval->reason = $this->isObjExist($obj->reason, '');
				$retval->totalAmount = $this->isObjExist($obj->amount, 0);
				$retval->currency = $this->isObjExist($obj->currency, 'PHP');
				$retval->status = $this->isObjExist($obj->status, '');
				$retval->paymentID = $this->isObjExist($obj->payment, '');
			}
			
			$ch->close();
			
			return $retval;
		}
		
		public function getRefunds(){
			$ch = & $this->getCurlInstance();
			$ch->get($this->url . $this->urlPath['refunds'] . chr(47) . $this->Refunds->paymentID . chr(47) . "refunds");
			
			if($ch->error){
				$retval = false;
			}
			else{
				$retval = $ch->response;
			}
			
			$ch->close();
			
			return $retval;
		}
		
		public function getRefundInfo(){
			$ch = & $this->getCurlInstance();
			$ch->get($this->url . $this->urlPath['refunds'] . chr(47) . $this->Refunds->paymentID . chr(47) . "refunds" . chr(47) . $this->Refunds->refundID);
			
			if($ch->error){
				$retval = false;
			}
			else{
				$retval = $ch->response;
			}
			
			$ch->close();
			
			return $retval;
		}
		
		private function generateReqCreateToken(){
			return array(
				'card' => array(
					'number' => $this->removeWhitespace($this->isObjExist($this->CardDetails->cardNumber, "")),
					'expMonth' => $this->removeWhitespace($this->isObjExist($this->CardDetails->cardExpiryMonth, "")),
					'expYear' => $this->removeWhitespace($this->isObjExist($this->CardDetails->cardExpiryYear, "")),
					'cvc' => $this->removeWhitespace($this->isObjExist($this->CardDetails->cardCVC, "")),
				)
			);
		}
		
		private function generateReqCreatePayment(){
			return array(
				'paymentTokenId' => $this->tokenId,
				'totalAmount' => array(
					"amount" => $this->isObjExist($this->totalAmount, 0),
                    "currency" => $this->isObjExist($this->currency, " ")
				),
				'buyer' => array(
					"firstName" => $this->isObjExist($this->CustomerDetails->firstName, " "),
				    "middleName" => $this->isObjExist($this->CustomerDetails->middleName, " "),
				    "lastName" => $this->isObjExist($this->CustomerDetails->lastName, " "),
				    "contact" => array(
					    "phone" => $this->isObjExist($this->CustomerDetails->phone, " "),
				        "email" => $this->isObjExist($this->CustomerDetails->email, " ")
				    ),
				    "billingAddress" => array(
					    "line1" => $this->isObjExist($this->CustomerDetails->line1, " "),
				        "line2" => $this->isObjExist($this->CustomerDetails->line2, " "),
				        "city" => $this->isObjExist($this->CustomerDetails->city, " "),
				        "state" => $this->isObjExist($this->CustomerDetails->state, " "),
				        "zipCode" => $this->isObjExist($this->CustomerDetails->zipCode, " "),
				        "countryCode" => $this->isObjExist($this->CustomerDetails->countryCode, " ")
				    )
				),
				"redirectUrl" => array(
					"success" => $this->removeWhitespace($this->isObjExist($this->RedirectionURL->success," ")),
				    "failure" => $this->removeWhitespace($this->isObjExist($this->RedirectionURL->failure," ")),
				    "cancel" => $this->removeWhitespace($this->isObjExist($this->RedirectionURL->cancel," "))
				)
			);
		}
		
		private function &getCurlInstance(){
			$curl = new Curl();
			$curl->setHeader('Content-Type', 'application/json');
			$curl->setHeader('Authorization', "Basic " . base64_encode($this->sk_key . ":"));
			return $curl;
		}
		
		private function isObjExist($obj, $replace){
			return (isset($obj)? $obj : $replace);
		}
		
		private function removeWhitespace($value){
			return str_replace(' ', '', trim($value));
		}
		
	}