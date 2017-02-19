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
	
	class PaymentFacilitatorObject{
		public $smi;
		public $smn;
		public $mci;
		public $mpc;
		public $mco;
		public $mst;
		
		function __construct() {
			
		}
		
		function __destruct() {
			$this->smi = null;
			$this->smn = null;
			$this->mci = null;
			$this->mpc = null;
			$this->mco = null;
			$this->mst = null;
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
		
		public $debugLogging = false;
		public $totalAmount;
		public $currency;
		public $customerDetails;
		public $cardDetails;
		public $redirectionURL;
		public $refunds;
		public $paymentFacilitator;
		
		function __construct($publicKey, $secretKey, $env) {
			$this->pk_key = trim($publicKey);
			$this->sk_key = trim($secretKey);
			$this->url = $this->paymayaUrl[($env == "yes"? "sandbox" : "production")];
			
			$this->customerDetails = new CustomersDetailsObject();
			$this->cardDetails = new CardDetailsObject();
			$this->redirectionURL = new RedirectionURLObject();
			$this->refunds = new RefundObject();
			$this->paymentFacilitator = new PaymentFacilitatorObject();
		}
		
		function __destruct() {
			$this->customerDetails = null;
			$this->cardDetails     = null;
			$this->redirectionURL  = null;
			$this->refunds = null;
			$this->paymentFacilitator = null;
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
			
			$this->errorLogging('getToken',$ch->response);
			
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
			
			$this->errorLogging('createPayment',$ch->response);
			
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
			
			$this->errorLogging('getPayment',$ch->response);
			
			$ch->close();
			
			return $retval;
		}
		
		public function getPaymentID(){
			$this->errorLogging('getPaymentID',$this->paymentId);
			return $this->paymentId;
		}
		
		public function getTokenID(){
			$this->errorLogging('getTokenID',$this->tokenId);
			return $this->tokenId;
		}
		
		public function setTokenId($value){
			$this->tokenId = $value;
		}
		
		public function getTokenState(){
			$this->errorLogging('getTokenState',$this->tokenState);
			return $this->tokenState;
		}
		
		public function setTokenState($value){
			$this->tokenState = $value;
		}
		
		public function registerWebHook($name, $url){
			$ch = & $this->getCurlInstance();
			
			$data = $this->generateReqWebHooks($name, $url);
			$ch->post($this->url . $this->urlPath['webhooks'], json_encode($data));
			
			if($ch->error){
				$retval = false;
			}
			else{
				$retval = $ch->response;
			}
			
			$this->errorLogging('registerWebHook', $ch->response);
			
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
			
			$this->errorLogging('getListOfWebHooks', $ch->response);
			
			$ch->close();
			
			return $retval;
		}
		
		public function updateWebHooks($webhookID,$name, $url){
			$ch = & $this->getCurlInstance();
			
			$data = $this->generateReqWebHooks($name, $url);
			$ch->put($this->url . $this->urlPath['webhooks'] . chr(47) . $webhookID, $data);
			
			if($ch->error){
				$retval = false;
			}
			else{
				$retval = $ch->response;
			}
			
			$this->errorLogging('updateWebHooks', $ch->response);
			
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
			
			$this->errorLogging('getWebHooks', $ch->response);
			
			$ch->close();
			
			return $retval;
		}
		
		public function createRefunds(){
			$ch = & $this->getCurlInstance();
			
			$data = $this->generateReqRefunds();
			$ch->post($this->url . $this->urlPath['refunds'] . chr(47) . $this->refunds->paymentID . chr(47) . "refunds", $data);
			
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
			
			$this->errorLogging('createRefunds', $ch->response);
			
			$ch->close();
			
			return $retval;
		}
		
		public function getRefunds(){
			$ch = & $this->getCurlInstance();
			$ch->get($this->url . $this->urlPath['refunds'] . chr(47) . $this->refunds->paymentID . chr(47) . "refunds");
			
			if($ch->error){
				$retval = false;
			}
			else{
				$retval = $ch->response;
			}
			
			$this->errorLogging('getRefunds', $ch->response);
			
			$ch->close();
			
			return $retval;
		}
		
		public function getRefundInfo(){
			$ch = & $this->getCurlInstance();
			$ch->get($this->url . $this->urlPath['refunds'] . chr(47) . $this->refunds->paymentID . chr(47) . "refunds" . chr(47) . $this->refunds->refundID);
			
			if($ch->error){
				$retval = false;
			}
			else{
				$retval = $ch->response;
			}
			
			$this->errorLogging('getRefundInfo', $ch->response);
			
			$ch->close();
			
			return $retval;
		}
		
		private function generateReqRefunds(){
			$amount = $this->stripCharacters($this->isObjExist($this->refunds->totalAmount, 0), 20);
			settype($amount,'float');
			
			return array(
				'reason' => $this->stripCharacters($this->isObjExist($this->refunds->reason, 'Refunded'), 255),
				'totalAmount' => array(
					'amount' => $amount,
					'currency' => $this->stripCharacters($this->isObjExist($this->refunds->currency, 'PHP'), 20)
				)
			);
		}
		
		private function generateReqWebHooks($name, $url){
			return array(
				'name' => $this->stripCharacters($this->isObjExist($name, ' '), 50),
				'callbackUrl' => $this->stripCharacters($this->isObjExist($url, ' '), 2000)
			);
		}
		
		private function generateReqCreateToken(){
			return array(
				'card' => array(
					'number' => $this->stripCharacters($this->removeWhitespace($this->isObjExist($this->cardDetails->cardNumber, "0000000000000000")), 16),
					'expMonth' => $this->stripCharacters($this->removeWhitespace($this->isObjExist($this->cardDetails->cardExpiryMonth, "00")), 2),
					'expYear' => $this->stripCharacters($this->removeWhitespace($this->isObjExist($this->cardDetails->cardExpiryYear, "0000")), 4),
					'cvc' => $this->stripCharacters($this->removeWhitespace($this->isObjExist($this->cardDetails->cardCVC, "000")), 4),
				)
			);
		}
		
		private function generateReqCreatePayment(){
			$ret = array(
				'paymentTokenId' => $this->removeWhitespace($this->isObjExist($this->tokenId, ' ')),
				'totalAmount' => array(
					"amount" => $this->isObjExist($this->totalAmount, 0),
                    "currency" =>  $this->stripCharacters($this->isObjExist($this->currency, "PHP"), 3)
				),
				'buyer' => array(
					"firstName" => $this->stripCharacters($this->isObjExist($this->customerDetails->firstName, " "), 50),
				    "middleName" => $this->stripCharacters($this->isObjExist($this->customerDetails->middleName, " "), 50),
				    "lastName" => $this->stripCharacters($this->isObjExist($this->customerDetails->lastName, " "), 50),
				    "contact" => array(
					    "phone" => $this->stripCharacters($this->isObjExist($this->customerDetails->phone, " "), 50),
				        "email" => $this->stripCharacters($this->isObjExist($this->customerDetails->email, " "), 253)
				    ),
				    "billingAddress" => array(
					    "line1" => $this->stripCharacters($this->isObjExist($this->customerDetails->line1, " "), 150),
				        "line2" => $this->stripCharacters($this->isObjExist($this->customerDetails->line2, " "), 150),
				        "city" => $this->stripCharacters($this->isObjExist($this->customerDetails->city, " "), 50),
				        "state" => $this->stripCharacters($this->isObjExist($this->customerDetails->state, " "), 50),
				        "zipCode" => $this->stripCharacters($this->isObjExist($this->customerDetails->zipCode, " "), 20),
				        "countryCode" => $this->stripCharacters($this->isObjExist($this->customerDetails->countryCode, "PH"), 2)
				    )
				),
				"redirectUrl" => array(
					"success" => $this->removeWhitespace($this->isObjExist($this->redirectionURL->success," ")),
				    "failure" => $this->removeWhitespace($this->isObjExist($this->redirectionURL->failure," ")),
				    "cancel" => $this->removeWhitespace($this->isObjExist($this->redirectionURL->cancel," "))
				),
				"metadata" =>  array(
					"pf" =>  array(
						"smi" => $this->stripCharacters($this->isObjExist($this->paymentFacilitator->smi, ' '), 15),
						"smn" => $this->stripCharacters($this->isObjExist($this->paymentFacilitator->smn, ' '), 9),
						"mci" => $this->stripCharacters($this->isObjExist($this->paymentFacilitator->mci, ' '), 13),
						"mpc" => $this->stripCharacters($this->isObjExist($this->paymentFacilitator->mpc, ' '), 3),
						"mco" => $this->stripCharacters($this->isObjExist($this->paymentFacilitator->mco, ' '), 3)
					)
				)
			);
			
			if(isset($this->paymentFacilitator->mpc) == true || strlen($this->paymentFacilitator->mpc) > 0){
				if($this->paymentFacilitator->mpc == 840){
					$ret['metadata']['pf']['mst'] = $this->stripCharacters($this->isObjExist($this->paymentFacilitator->mst, ' '), 3);
				}
			}
			
			if(isset($this->customerDetails->middleName) == false || strlen($this->customerDetails->middleName) == 0){
				unset($ret['buyer']['middleName']);
			}
			
			if(isset($this->customerDetails->line2) == false || strlen($this->customerDetails->line2) == 0){
				unset($ret['buyer']['billingAddress']['line2']);
			}
			
			$this->errorLogging('generateReqCreatePayment', $ret);
			
			return $ret;
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
		
		private function stripCharacters($value, $length){
			if(gettype($value) <> 'string'){
				return false;
			}
			
			if(strlen($value) == 0){
				return false;
			}
			
			return substr($value, 0, $length);
		}
		
		public function errorLogging($method, $msg){
			if($this->debugLogging == false){
				return false;
			}
			
			if(gettype($msg) == 'object' || gettype($msg) == 'array'){
				$msg = json_encode($msg);
			}
			elseif (gettype($msg) == 'boolean'){
				settype($msg, 'string');
			}
			
			$filepath = __DIR__ . "/paymentvault_error.log";
			
			$fileSize = filesize($filepath);
			
			if(($fileSize / 1024) >= 14648){
				//delete all contents
				$file = fopen($filepath, 'w');
				fwrite($file, "");
				fclose($file);
			}
			
			$file = fopen($filepath, 'a+');
			$h = "8";
			$hm = $h * 60;
			$ms = $hm * 60;
			$txt = "[" . gmdate("m-d-y h:i:sa", time()+($ms)) . "](". strtoupper($method) .") : " . $msg;
			fwrite($file, $txt);
			fwrite($file, "\n--------------------------------------------------------------------------------------------------------------------------\n");
			fclose($file);
		}
	}