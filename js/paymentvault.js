(function($){
	
	var wc_checkout_form_loggedin = {
		order_review : $("form#order_review"),
		isSubmitted : false,
		init: function(){
			$form = this;
			
			this.order_review.submit(function(e){
				
				if($form.isSubmitted === true){
					$form.isSubmitted = false;
					return true;
				}
				
				var gatewayName = $form.order_review.find('#wc-gateway-name').val(),
						gatewayNonce = $form.order_review.find('#wc-gateway-nonce').val().replace(/\s/g,""),
						gatewayID = $form.order_review.find('#wc-gateway-id'),
						gatewaySandbox = $form.order_review.find('#wc-gateway-sandbox'),
						expMonth = $form.order_review.find('#'+gatewayName+'-card-expiry').val().replace(/\s/g,"").split(String.fromCharCode(47));
				
				PayMaya.publicKey = gatewayNonce;
				PayMaya.sandbox = (gatewaySandbox.prop('value') === 'yes')? 1 : 0;
				
				var card = {
					number: $form.order_review.find('#'+gatewayName+'-card-number').val().replace(/\s/g,""),
					expMonth: (expMonth.length === 2)? expMonth[0] : " ",
					expYear: (expMonth.length === 2)? expMonth[1] : " ",
					cvc: $form.order_review.find('#'+gatewayName+'-card-cvc').val().replace(/\s/g,"")
				};
				
				PayMaya.getTokenID(card, function(pObj, tokenData){
					if($.type(tokenData) === 'string'){
						gatewayID.prop('value', PayMaya.base64(tokenData));
					}
					else{
						gatewayID.prop('value', PayMaya.base64(tokenData.response));
					}
					
					$form.isSubmitted = true;
					$form.order_review.submit();
				});
				
				e.stopPropagation();
				e.preventDefault();
				
				return false;
			});
		}
	};
	
	wc_checkout_form_loggedin.init();
	
}(jQuery));