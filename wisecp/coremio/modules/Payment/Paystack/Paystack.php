<?php
    if(!class_exists('wisecp_paystack_plugin_tracker'))
    {
        class wisecp_paystack_plugin_tracker {
            var $public_key;
            var $plugin_name;
            function __construct($plugin, $pk){
                //configure plugin name
                //configure public key
                $this->plugin_name = $plugin;
                $this->public_key = $pk;
            }



            function log_transaction_success($trx_ref){
                //send reference to logger along with plugin name and public key
                $url = "https://plugin-tracker.paystackintegrations.com/log/charge_success";

                $fields = [
                    'plugin_name'  => $this->plugin_name,
                    'transaction_reference' => $trx_ref,
                    'public_key' => $this->public_key
                ];

                $fields_string = http_build_query($fields);

                $ch = curl_init();

                curl_setopt($ch,CURLOPT_URL, $url);
                curl_setopt($ch,CURLOPT_POST, true);
                curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);

                curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);

                //execute post
                $result = curl_exec($ch);
                //  echo $result;
            }
        }
    }

    class Paystack extends PaymentGatewayModule
    {
        function __construct()
        {
            $this->name             = __CLASS__;

            parent::__construct();
        }

        public function config_fields()
        {
            return [
                'liveSecretKey'          => [
                    'name'              => "Live Secret Key",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["liveSecretKey"] ?? '',
                    'placeholder'       => "sk_live_xxx",
                ],
                'livePublicKey'          => [
                    'name'              => "Live Public Key",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["livePublicKey"] ?? '',
                    'placeholder'       => "pk_live_xxx",
                ],
                'testSecretKey'          => [
                    'name'              => "Test Secret Key",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["testSecretKey"] ?? '',
                    'placeholder'       => "sk_test_xxx",
                ],
                'testPublicKey'          => [
                    'name'              => "Test Public Key",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["testPublicKey"] ?? '',
                    'placeholder'       => "pk_test_xxx",
                ],
                'testMode'          => [
                    'name'              => "Test Mode",
                    'description'       => "",
                    'type'              => "approval",
                    'value'             => 1,
                    'checked'           => (boolean) (int) ($this->config["settings"]["testMode"] ?? 0),
                ]
            ];
        }

        public function area($params=[])
        {
            // Client
            $email = $this->clientInfo->email;
            $phone = $this->clientInfo->phone;

            // Config Options
            if ($this->config['settings']['testMode']) {
                $publicKey = $this->config['settings']['testPublicKey'];
                $secretKey = $this->config['settings']['testSecretKey'];
            } else {
                $publicKey = $this->config['settings']['livePublicKey'];
                $secretKey = $this->config['settings']['liveSecretKey'];
            }

            // check if there is an id in the GET meaning the invoice was loaded directly
            $paynowload = ( !array_key_exists('id', $_GET) );

            // Invoice
            $invoiceId = $this->checkout_id;
            $amountinkobo = intval(floatval($params['amount'])*100);
            $currency = $params['currency'];
            ///Transaction_reference
            $txnref         = $invoiceId . '_' .time();


            if (!in_array(strtoupper($currency), [ 'NGN', 'USD', 'GHS', 'ZAR' ])) {
                return ("<b style='color:red;margin:2px;padding:2px;border:1px dotted;display: block;border-radius: 10px;font-size: 13px;'>Sorry, this version of the Paystack WISECP plugin only accepts NGN, USD, GHS, and ZAR payments. <i>$currency</i> not yet supported.</b>");
            }

            $isSSL = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443);
            $fallbackUrl = $this->links["callback"].http_build_query(
                    array(
                        'invoiceid' =>$invoiceId,
                        'email'=>$email,
                        'phone'=>$phone,
                        'reference' => $txnref,
                        'amountinkobo'=>$amountinkobo,
                        'go'=>'standard'
                    )
                );
            $callbackUrl = $this->links["callback"].'?'.
                http_build_query(
                    array(
                        'invoiceid'=>$invoiceId
                    )
                );

            $code = '
    <form target="hiddenIFrame" action="about:blank">
        <script src="https://js.paystack.co/v1/inline.js"></script>
        <div class="payment-btn-container2"></div>
        <script>
            // load jQuery 1.12.3 if not loaded
            (typeof $ === \'undefined\') && document.write("<scr" + "ipt type=\"text\/javascript\" '.
                'src=\"https:\/\/code.jquery.com\/jquery-1.12.3.min.js\"><\/scr" + "ipt>");
        </script>
        <script>
            $(function() {
                $(\'.payment-btn-container2\').hide();
                var toAppend = \'<button type="button"'.
                ' onclick="payWithPaystack()"'.
                ' style="padding: 10px 25px; margin: 10px;border-radius: 5px;background: #021C32; color:#fff">'.
                addslashes($this->lang["pay-button"]).'</button>'.
                '<img style="width: 150px; display: block; margin: 0 auto;"'.
                ' src="https://cdn-assets-cloud.frontify.com/s3/frontify-cloud-files-us/eyJwYXRoIjoiZnJvbnRpZnlcL2FjY291bnRzXC8yYVwvMTQxNzczXC9wcm9qZWN0c1wvMTc4NjE0XC9hc3NldHNcLzdmXC8yNzQ2ODcxXC83NDY5OGViODMzMzhlMWJiNjVhMDk4MTYwNjkzY2FlOC0xNTQwMDM5NjA0LnBuZyJ9:cloud:YYqwtVK3Tb8KMGeFiXCl_w9flKcsEY9D022GMOK9oFc"/>\';

                $(\'.payment-btn-container\').append(toAppend);
                if($(\'.payment-btn-container\').length===0){
                    $(\'.payment-btn-container2\').after(toAppend); 
               }

            });
        </script>
    </form>
    <div class="hidden" style="display:none"><iframe name="hiddenIFrame"></iframe></div>
    <script>
        var paystackIframeOpened = false;
        var button_created = false;
        var paystackHandler = PaystackPop.setup({
            key: \''.addslashes(trim($publicKey)).'\',
            email: \''.addslashes(trim($email)).'\',
            phone: \''.addslashes(trim($phone)).'\',
            amount: '.$amountinkobo.',
            currency: \''.addslashes(trim($currency)).'\',
            ref:\''.$txnref.'\',
            metadata:{
                "custom_fields":[
                  {
                    "display_name":"Plugin",
                    "variable_name":"plugin",
                    "value":"wisecp"
                  }
                ]
              },
            callback: function(response){
                $(\'div.alert.alert-info.text-center\').hide();
                $(\'.payment-btn-container2\').hide();
                
                window.location.href = \''.addslashes($callbackUrl).'&trxref=\' + response.trxref;
            },
            onClose: function(){
                paystackIframeOpened = false;
            }
        });
        function payWithPaystack(){
            if (paystackHandler.fallback || paystackIframeOpened) {
                // Handle non-support of iframes or
                // Being able to click PayWithPaystack even though iframe already open
                window.location.href = \''.addslashes($fallbackUrl).'\';
            } else {
                paystackHandler.openIframe();
                paystackIframeOpened = true;
                $(\'img[alt="Loading"]\').hide();
                $(\'div.alert.alert-info.text-center\').html(\'Click the button below to retry payment...\');
                create_button();
            }
       }
       function create_button(){
        if(!button_created){
            button_created = true;
            $(\'.payment-btn-container2\').append(\'<button type="button"'.
                ' onClick="window.location.reload()"'.
                ' style="padding: 10px 25px; margin: 10px;border-radius: 5px;background: #021C32; color:#fff">'.
                addslashes($this->lang["pay-button"]).'</button>'.
                '<img style="width: 150px; display: block; margin: 0 auto;"'.
                ' src="https://cdn-assets-cloud.frontify.com/s3/frontify-cloud-files-us/eyJwYXRoIjoiZnJvbnRpZnlcL2FjY291bnRzXC8yYVwvMTQxNzczXC9wcm9qZWN0c1wvMTc4NjE0XC9hc3NldHNcLzdmXC8yNzQ2ODcxXC83NDY5OGViODMzMzhlMWJiNjVhMDk4MTYwNjkzY2FlOC0xNTQwMDM5NjA0LnBuZyJ9:cloud:YYqwtVK3Tb8KMGeFiXCl_w9flKcsEY9D022GMOK9oFc"/>\');
        }
       }     
    </script>';
            return $code;
        }

        private function verifyTransaction($trxref, $secretKey)
        {
            $ch = curl_init();
            $txStatus = new stdClass();

            // set url
            curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/verify/" . rawurlencode($trxref));

            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array(
                    'Authorization: Bearer '. trim($secretKey)
                )
            );

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_SSLVERSION, 6);

            // exec the cURL
            $response = curl_exec($ch);

            // should be 0
            if (curl_errno($ch)) {
                // curl ended with an error
                $txStatus->error = "cURL said:" . curl_error($ch);
                curl_close($ch);
            } else {
                //close connection
                curl_close($ch);

                // Then, after your curl_exec call:
                $body = json_decode($response);
                if (!$body->status) {
                    // paystack has an error message for us
                    $txStatus->error = "Paystack API said: " . $body->message;
                } else {
                    // get body returned by Paystack API
                    $txStatus = $body->data;
                }
            }

            return $txStatus;
        }

        public function callback()
        {

            // Retrieve data returned in payment gateway callback
            $invoiceId      = Filter::init("GET/invoiceid","numbers");
            $txnref         = $invoiceId . '_' .time();
            $trxref         = filter_input(INPUT_GET, "trxref");

            if ($this->config['settings']['testMode']) {
                $secretKey = $this->config['settings']['testSecretKey'];
            } else {
                $secretKey = $this->config['settings']['liveSecretKey'];
            }

            $checkout       = $this->get_checkout($invoiceId);

            if($checkout)
            {
                $this->set_checkout($checkout);
            }


            if(strtolower(filter_input(INPUT_GET, 'go'))==='standard'){
                // falling back to standard
                $ch = curl_init();

                $isSSL = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443);

                $amountinkobo = filter_input(INPUT_GET, 'amountinkobo');
                $email = filter_input(INPUT_GET, 'email');
                $phone = filter_input(INPUT_GET, 'phone');

                $callback_url = $this->links['callback'].'?invoiceid=' . rawurlencode($invoiceId);

                $txStatus = new stdClass();
                // set url
                curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/initialize/");

                curl_setopt(
                    $ch,
                    CURLOPT_HTTPHEADER,
                    array(
                        'Authorization: Bearer '. trim($secretKey),
                        'Content-Type: application/json'
                    )
                );

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt(
                    $ch,
                    CURLOPT_POSTFIELDS,
                    json_encode(
                        array(
                            "amount"=>$amountinkobo,
                            "email"=>$email,
                            "phone"=>$phone,
                            "reference" => $txnref,
                            "callback_url"=>$callback_url
                        )
                    )
                );
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt($ch, CURLOPT_SSLVERSION, 6);

                // exec the cURL
                $response = curl_exec($ch);

                // should be 0
                if (curl_errno($ch)) {
                    // curl ended with an error
                    $txStatus->error = "cURL said:" . curl_error($ch);
                    curl_close($ch);
                } else {
                    //close connection
                    curl_close($ch);

                    // Then, after your curl_exec call:
                    $body = json_decode($response);
                    if (!$body->status) {
                        // paystack has an error message for us
                        $txStatus->error = "Paystack API said: " . $body->message;
                    } else {
                        // get body returned by Paystack API
                        $txStatus = $body->data;
                    }
                }
                if(!$txStatus->error){
                    header('Location: ' . $txStatus->authorization_url);
                    die('<meta http-equiv="refresh" content="0;url='.$txStatus->authorization_url.'" />
        Redirecting to <a href=\''.$txStatus->authorization_url.'\'>'.$txStatus->authorization_url.'</a>...');
                }
                else {
                    $output = "Transaction Initialize failed"
                        . "\r\nReason: {$txStatus->error}";
                    Modules::save_log("Payment",'Paystack','transaction',false,$output);
                    die($txStatus->error);
                }
            }
            $input = @file_get_contents("php://input");
            $event = json_decode($input);
            if (isset($event->event)) {
                // echo "<pre>";
                if(!$_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] || ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] !== hash_hmac('sha512', $input, $secretKey))){
                    exit();
                }

                switch($event->event){
                    case 'subscription.create':

                        break;
                    case 'subscription.disable':
                        break;
                    case 'charge.success':

                        $trxref = $event->data->reference;

                        //PSTK Logger

                        if ($this->config['settings']['testMode']) {
                            $pk = $this->config['settings']['testPublicKey'];
                        } else {
                            $pk = $this->config['settings']['livePublicKey'];
                        }
                        $pstk_logger = new wisecp_paystack_plugin_tracker('wisecp',$pk );
                        $pstk_logger->log_transaction_success($trxref);


                        //-------------------------------------


                        $order_details  = explode( '_', $trxref);
                        $invoiceId       = (int) $order_details[0];

                        break;
                    case 'invoice.create':
                        // Recurring payments
                    case 'invoice.update':
                        // Recurring payments

                        break;
                }
                http_response_code(200);


                // exit();
            }
            $txStatus = $this->verifyTransaction($trxref, $secretKey);

            if ($txStatus->error) {
                $output = "Transaction ref: " . $trxref
                    . "\r\nInvoice ID: " . $invoiceId
                    . "\r\nStatus: failed"
                    . "\r\nReason: {$txStatus->error}";
                Modules::save_log("Payment",'Paystack','transaction',false,$output,'Unsuccessful');
                $success = false;
            }
            elseif ($txStatus->status == 'success') {
                $output = "Transaction ref: " . $trxref
                    . "\r\nInvoice ID: " . $invoiceId
                    . "\r\nStatus: succeeded";
                Modules::save_log("Payment",'Paystack','transaction',false,$output,'Successful');


                //PSTK Logger

                if ($this->config['settings']['testMode']) {
                    $pk = $this->config['settings']['testPublicKey'];
                } else {
                    $pk = $this->config['settings']['livePublicKey'];
                }
                $pstk_logger_ = new wisecp_paystack_plugin_tracker('wisecp',$pk );
                $pstk_logger_->log_transaction_success($trxref);


                //-------------------------------------

                $success = true;
            }
            else {
                $output = "Transaction ref: " . $trxref
                    . "\r\nInvoice ID: " . $invoiceId
                    . "\r\nStatus: {$txStatus->status}";
                Modules::save_log("Payment",'Paystack','transaction',false,$output,'Unsuccessful');
                $success = false;
            }
            if ($success) {

                // print_r($txStatus);
                // die();
                /**
                 * Validate Callback Invoice ID.
                 *
                 * Checks invoice ID is a valid invoice number.
                 *
                 * Performs a die upon encountering an invalid Invoice ID.
                 *
                 * Returns a normalised invoice ID.
                 */
                if(!$checkout)
                {
                    $this->error = 'Checkout problem';
                    return false;
                }

                return [
                    'status'            => 'successful',
                ];
            }
            else {
                die($txStatus->error . ' ; ' . $txStatus->status);
            }
        }

    }