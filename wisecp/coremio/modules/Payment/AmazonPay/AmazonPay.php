<?php
    class AmazonPay extends PaymentGatewayModule
    {
        function __construct()
        {
            $this->name             = __CLASS__;

            parent::__construct();
        }

        public function config_fields()
        {
            return [
                'merchantid'          => [
                    'name'              => "Merchant ID",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["merchantid"] ?? '',
                ],
                'accesskey'          => [
                    'name'              => "Access Key",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["accesskey"] ?? '',
                ],
                'secretkey'          => [
                    'name'              => "Secret Key",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["secretkey"] ?? '',
                ],
                'clientid'          => [
                    'name'              => "Client ID",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["clientid"] ?? '',
                ],
                'region'          => [
                    'name'              => "Region",
                    'description'       => "",
                    'type'              => "dropdown",
                    'options'           => [
                        'us'            => 'United States',
                        'uk'            => 'United Kingdom',
                        'de'            => 'Germany',
                        'jp'            => 'Japan',
                    ],
                    'value'             => $this->config["settings"]["region"] ?? '',
                ],
                'sandbox'          => [
                    'name'              => "Test Mode",
                    'description'       => "Enable Sandbox",
                    'type'              => "approval",
                    'value'             => 1,
                    'checked'           => (boolean) (int) ($this->config["settings"]["sandbox"] ?? 0),
                ],
            ];
        }

        public function area($params=[])
        {
            require_once(__DIR__.DS.'AmazonPaySDK'.DS.'Client.php');

            # Gateway Specific Variables
            $gatewaymerchantid       = $this->config['settings']['merchantid'];
            $gatewayclientid         = $this->config['settings']['clientid'];
            $gatewayallowedreturnurl = $this->links['callback'];

            # Invoice Variables
            $invoiceid   = $this->checkout_id;
            $description = 'Invoice Payment : #'.$this->checkout_id;
            $amount      = $params['amount']; # Format: ##.##
            $currency    = $params['currency']; # Currency Code


            # System Variables
            $companyname            = __("website/index/meta/title");
            $returnToInvoiceUrl     = $this->links["return"];
            $transdetails       = urlencode(base64_encode($invoiceid . '::' . $amount . '::' . $description . '::' . $companyname . '::' . $returnToInvoiceUrl));


            if ($this->config['settings']['sandbox'])
            {

                switch ($this->config['settings']['region'])
                {
                    case 'us':
                        $endpointurl = "<script type='text/javascript' src='https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js'></script>";
                        break;

                    case 'uk':
                        $endpointurl = "<script type='text/javascript' src='https://static-eu.payments-amazon.com/OffAmazonPayments/uk/sandbox/lpa/js/Widgets.js'></script>";
                        break;

                    case 'de':
                        $endpointurl = "<script type='text/javascript' src='https://static-eu.payments-amazon.com/OffAmazonPayments/uk/sandbox/js/Widgets.js'></script>";
                        break;

                    case 'jp':
                        $endpointurl = "<script type='text/javascript' src='https://origin-na.ssl-images-amazon.com/images/G/09/EP/offAmazonPayments/sandbox/prod/lpa/js/Widgets.js'></script>";
                        break;

                    default:
                        $endpointurl = "<script type='text/javascript' src='https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js'></script>";
                        break;
                }
            } else {
                switch ($this->config['settings']['region']) {
                    case 'us':
                        $endpointurl = "<script type='text/javascript' src='https://static-na.payments-amazon.com/OffAmazonPayments/us/js/Widgets.js'></script>";
                        break;

                    case 'uk':
                        $endpointurl = "<script type='text/javascript' src='https://static-eu.payments-amazon.com/OffAmazonPayments/uk/js/Widgets.js'></script>";
                        break;

                    case 'de':
                        $endpointurl = "<script type='text/javascript' src='https://static-eu.payments-amazon.com/OffAmazonPayments/eur/lpa/js/Widgets.js'></script>";
                        break;

                    case 'jp':
                        $endpointurl = "<script type='text/javascript' src='https://origin-na.ssl-images-amazon.com/images/G/09/EP/offAmazonPayments/prod/lpa/js/Widgets.js'></script>";
                        break;

                    default:
                        $endpointurl = "<script type='text/javascript' src='https://static-na.payments-amazon.com/OffAmazonPayments/us/js/Widgets.js'></script>";
                        break;
                }
            }

            $code = '<div id="AmazonLoginButton"></div>';
            $code .= '<script type=\'text/javascript\'>
    window.onAmazonLoginReady = function () {
        amazon.Login.setClientId(\'' . $gatewayclientid . '\');
        amazon.Login.logout(); //we log out so every time user gets to choose explicitly which amazon account to use
    };
</script>';

            $code .= $endpointurl;
            $code .= '<script type=\'text/javascript\'>
    var authRequest;
    OffAmazonPayments.Button("AmazonLoginButton", "' . $gatewaymerchantid . '", {
        type: "PwA",
        color: "DarkGray",
        popup: false,
        authorization: function () {
            loginOptions = { scope: "profile postal_code payments:widget payments:shipping_address", popup: true };
            authRequest = amazon.Login.authorize(loginOptions, "' . $gatewayallowedreturnurl . '?trd=' . $transdetails . '");
        },
        onError: function (error) {
            // something bad happened
        }
    });
</script>';
            return $code;
        }

        public function callback()
        {
            require_once(__DIR__.DS.'AmazonPaySDK'.DS.'Client.php');

            $invoiceDetails = explode('::', urldecode(base64_decode($_REQUEST['trd'])));
            $amazonConfig = array(
                'merchant_id' => $this->config['settings']['merchantid'],
                'access_key' => $this->config['settings']['accesskey'],
                'secret_key' => $this->config['settings']['secretkey'],
                'client_id' => $this->config['settings']['clientid'],
                'region' => $this->config['settings']['region']
            );

            $amazonClient = new AmazonPay\Client($amazonConfig);

            if ($this->config['settings']['sandbox']) {
                $amazonClient->setSandbox(true);
                switch ($this->config['settings']['region']) {
                    case 'us':
                        $endpointurl = "<script type='text/javascript' src='https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js'></script>";
                        break;

                    case 'uk':
                        $endpointurl = "<script type='text/javascript' src='https://static-eu.payments-amazon.com/OffAmazonPayments/uk/sandbox/js/Widgets.js'></script>";
                        break;

                    case 'de':
                        $endpointurl = "<script type='text/javascript' src='https://static-eu.payments-amazon.com/OffAmazonPayments/uk/sandbox/js/Widgets.js'></script>";
                        break;

                    case 'jp':
                        $endpointurl = "<script type='text/javascript' src='https://origin-na.ssl-images-amazon.com/images/G/09/EP/offAmazonPayments/sandbox/prod/lpa/js/Widgets.js'></script>";
                        break;

                    default:
                        $endpointurl = "<script type='text/javascript' src='https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js'></script>";
                        break;
                }
            } else {
                switch ($this->config['settings']['region']) {
                    case 'us':
                        $endpointurl = "<script type='text/javascript' src='https://static-na.payments-amazon.com/OffAmazonPayments/us/js/Widgets.js'></script>";
                        break;

                    case 'uk':
                        $endpointurl = "<script type='text/javascript' src='https://static-eu.payments-amazon.com/OffAmazonPayments/uk/js/Widgets.js'></script>";
                        break;

                    case 'de':
                        $endpointurl = "<script type='text/javascript' src='https://static-eu.payments-amazon.com/OffAmazonPayments/uk/js/Widgets.js'></script>";
                        break;

                    case 'jp':
                        $endpointurl = "<script type='text/javascript' src='https://origin-na.ssl-images-amazon.com/images/G/09/EP/offAmazonPayments/prod/lpa/js/Widgets.js'></script>";
                        break;

                    default:
                        $endpointurl = "<script type='text/javascript' src='https://static-na.payments-amazon.com/OffAmazonPayments/us/js/Widgets.js'></script>";
                        break;
                }
            }

            $code = '<html><header>
<style type="text/css">
#btn-place-order{display:inline-block;width:159px;background:none repeat scroll 0 0 #d8dde6;border:1px solid;border-color:#b7b7b7 #aaa #a0a0a0;border-image:none;height:35px;overflow:hidden;text-align:center;text-decoration:none!important;vertical-align:middle;color:#05a;cursor:pointer}
#btn-place-order{border-color:#be952c #a68226 #9b7924;background-color:#eeba37}
#btn-place-order{background:#f6d073;background:-moz-linear-gradient(top,#fee6b0,#eeba37);background:-webkit-gradient(linear,left top,left bottom,color-stop(0%,#fee6b0),color-stop(100%,#eeba37));background:-webkit-linear-gradient(top,#fee6b0,#eeba37);background:-o-linear-gradient(top,#fee6b0,#eeba37);background:-ms-linear-gradient(top,#fee6b0,#eeba37);background:linear-gradient(top,#fee6b0,#eeba37);filter:progid:DXImageTransform.Microsoft.gradient(startColorstr="#fee6b0",endColorstr="#eeba37",GradientType=0);zoom:1;}
#btn-place-order:hover{background:#f5c85b;background:-moz-linear-gradient(top,#fede97,#ecb21f);background:-webkit-gradient(linear,left top,left bottom,color-stop(0%,#fede97),color-stop(100%,#ecb21f));background:-webkit-linear-gradient(top,#fede97,#ecb21f);background:-o-linear-gradient(top,#fede97,#ecb21f);background:-ms-linear-gradient(top,#fede97,#ecb21f);background:linear-gradient(top,#fede97,#ecb21f);filter:progid:DXImageTransform.Microsoft.gradient(startColorstr="#fede97",endColorstr="#ecb21f",GradientType=0);zoom:1}
#btn-place-order:active{-webkit-box-shadow:0 1px 3px rgba(0,0,0,0.2) inset;-moz-box-shadow:0 1px 3px rgba(0,0,0,0.2) inset;box-shadow:0 1px 3px rgba(0,0,0,0.2) inset;background-color:#eeba37;background-image:none;filter:none}
#btn-place-order{line-height:33px;background-color:transparent;color:#111;display:block;font-family:Arial,sans-serif;font-size:15px;outline:0 none;text-align:center;white-space:nowrap;cursor:pointer;}
#btn-place-order:link {text-decoration: none;}
</style></header><body>';

            $code .= '<!-- <div id="addressBookWidgetDiv" style="width:400px; height:240px;"></div> --!>
<div align="center" style="margin:5%;">
		<img src="https://images-na.ssl-images-amazon.com/images/G/01/amazonservices/payments/website/Secondary-logo-amazonpay-fullcolor_tools._V534864886_.png">
		<br/>
		<p><span id="helloMessage"></span><br/><br/>Pay <b>$' . $invoiceDetails[1] . ' USD</b> to ' . $invoiceDetails[3] . '</p>
		<div id="walletWidgetDiv" align="center" style="width:400px; height:240px;"></div>
		<br>
		<a id="btn-place-order" style="display: none;">Place your order</a>
</div>';

            $code .= '<script type=\'text/javascript\'>
    window.onAmazonLoginReady = function () {
        amazon.Login.setClientId(\'' . $this->config['settings']['clientid'] . '\');
				amazon.Login.retrieveProfile(function (response) {
						// Display profile information.
						document.getElementById("helloMessage").innerHTML = "Hello, " + response.profile.Name + ".";
				});
    };
</script>';

            $code .= $endpointurl;

            $code .= '<script type="text/javascript">
		var orderReferenceId;
	  var enableOrderButton = function(orderReferenceId) {
		    var placeOrderBtn = document.getElementById("btn-place-order");
				placeOrderBtn.style.display = "block";
				placeOrderBtn.href = window.location.href + "&AmazonOrderReferenceId=" + orderReferenceId;
		};
	/* No need
    new OffAmazonPayments.Widgets.AddressBook({
        sellerId: \'' . $this->config['settings']['merchantid'] . '\',
        onOrderReferenceCreate: function (orderReference) {
           orderReferenceId = orderReference.getAmazonOrderReferenceId();
					 enableOrderButton(orderReferenceId);
		       //window.location = window.location.href + "&AmazonOrderReferenceId=" + orderReferenceId;
        },
        onAddressSelect: function () {
            // do stuff here like recalculate tax and/or shipping
        },
        design: {
            designMode: \'responsive\'
        },
        onError: function (error) {
            console.log(error.getErrorCode(),error.getErrorMessage());
            // your error handling code
        }
    }).bind("addressBookWidgetDiv");
  */
    new OffAmazonPayments.Widgets.Wallet({
        sellerId: \'' . $this->config['settings']['merchantid'] . '\',
				onOrderReferenceCreate: function (orderReference) {
           orderReferenceId = orderReference.getAmazonOrderReferenceId();
					 enableOrderButton(orderReferenceId);
        },
        onPaymentSelect: function (orderReference) {
           orderReferenceId = orderReference.getAmazonOrderReferenceId ? orderReference.getAmazonOrderReferenceId() : orderReferenceId;
					 enableOrderButton(orderReferenceId);
        },
        design: {
            designMode: \'responsive\'
        },
        onError: function (error) {
            console.log(error.getErrorCode(),error.getErrorMessage());
            // your error handling code
        }
    }).bind("walletWidgetDiv");
</script>';

            $code .= '</body></html>';

            if (!isset($_REQUEST['AmazonOrderReferenceId'])) {
                echo $code;
                exit();
            }


            $requestParameters       = array();
            $gAmazonOrderReferenceId = $_REQUEST['AmazonOrderReferenceId'];

            // Create the parameters array to set the order
            $requestParameters['amazon_order_reference_id'] = $gAmazonOrderReferenceId;
            $requestParameters['amount']                    = $invoiceDetails[1];
            $requestParameters['currency_code']             = 'USD';
            $requestParameters['seller_note']               = $invoiceDetails[2];
            $requestParameters['seller_order_id']           = $invoiceDetails[0];
            $requestParameters['store_name']                = $invoiceDetails[3];

            // Set the Order details by making the SetOrderReferenceDetails API call
            $response = $amazonClient->SetOrderReferenceDetails($requestParameters);

            // If the API call was a success Get the Order Details by making the GetOrderReferenceDetails API call
            if ($amazonClient->success) {
                $requestParameters['address_consent_token'] = null;
                $response                                   = $amazonClient->GetOrderReferenceDetails($requestParameters);
            }
            // Pretty print the Json and then echo it for the Ajax success to take in
            $json = json_decode($response->toJson());

            // Confirm the order by making the ConfirmOrderReference API call
            $response = $amazonClient->confirmOrderReference($requestParameters);

            $responsearray['confirm'] = json_decode($response->toJson());


            // If the API call was a success make the Authorize API call


            if ($amazonClient->success) {
                $requestParameters['authorization_amount']       = $invoiceDetails[1];
                $requestParameters['authorization_reference_id'] = md5($gAmazonOrderReferenceId);
                $requestParameters['seller_authorization_note']  = 'Authorizing payment';
                $requestParameters['capture_now']                = TRUE;
                $requestParameters['transaction_timeout']        = 1440; # 24 hours

                $response                   = $amazonClient->authorize($requestParameters);
                $responsearray['authorize'] = json_decode($response->toJson());
            }

            # Get Returned Variables - Adjust for Post Variable Names from your Gateway's Documentation

            $responseCapture = json_decode($response->toJson(), true);

            $invoiceid = $invoiceDetails[0];
            $transid   = $gAmazonOrderReferenceId;
            $auth_status = $responseCapture['AuthorizeResult']['AuthorizationDetails']['AuthorizationStatus']['State'];

            if(!$invoiceid){
                $this->error = 'ERROR: checkout id not found.';
                return false;
            }

            $checkout       = $this->get_checkout($invoiceid);

            // Checkout invalid error
            if(!$checkout)
            {
                $this->error = 'Checkout ID unknown';
                return false;
            }

            $this->set_checkout($checkout);

            $callback_msg   = '';
            $status         = 'pending';

            if ($responseCapture["ResponseStatus"] == 200 && $auth_status == "Pending") {
                Modules::save_log("Payment","AmazonPay","callback",false,$responseCapture,"Pending");  # Save to Gateway Log: name, data array, status
                $callback_msg .=  "<p>Thank you! The transaction process has been initiated, please give us upto 24 hours. Once done, we will notify you.<br/>Sincerely, IPBurger.</p>";
                $callback_msg .=  "<p><a href='" . $invoiceDetails[4] . "'>Click here to go back to your invoice.</a></p>";
            }
            else
            {
                $status = 'error';
                # Unsuccessful
                Modules::save_log("Payment","AmazonPay","callback",false,$responseCapture,"Failed"); # Save to Gateway Log: name, data array, status
                $callback_msg .= "<p>There was an error processing your payment. Please try another payment method, or contact support.<br/>Sincerely, IPBurger.</p>";
                $callback_msg .=  "<script type='text/javascript'>setTimeout(function() {window.location = '" . $invoiceDetails[4] . ";'}, 5000);</script>";
                exit;
            }

            return  [
                'status' => $status,
                'callback_message' => $callback_msg,
            ];

        }

    }