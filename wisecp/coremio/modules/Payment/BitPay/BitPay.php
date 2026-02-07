<?php
    if(!function_exists('BPC_autoloader'))
    {
        function BPC_autoloader($class)
        {
            if (strpos($class, 'BPC_') !== false):
                if (!class_exists('BitPayLib/' . $class, false)):
                    #doesnt exist so include it
                    include_once __DIR__.DS.'BitPayLib' . DS . $class . '.php';
                endif;
            endif;

        }
    }
    spl_autoload_register('BPC_autoloader');

    class BitPay extends PaymentGatewayModule
    {
        function __construct()
        {
            $this->name             = __CLASS__;
            parent::__construct();
            $this->define_function('ipn');
        }

        public function config_fields()
        {
            return [
                'bitpay_checkout_token_dev'          => [
                    'name'              => "Development Token",
                    'description'       => 'Your <b>development</b> merchant token.  <a href="https://test.bitpay.com/dashboard/merchant/api-tokens" target="_blank">Create one here</a> and <b>uncheck</b> `Require Authentication`.',
                    'type'              => "text",
                    'value'             => $this->config["settings"]["bitpay_checkout_token_dev"] ?? '',
                ],
                'bitpay_checkout_token_prod'          => [
                    'name'              => "Production Token",
                    'description'       => 'Your <b>production</b> merchant token.  <a href = "https://www.bitpay.com/dashboard/merchant/api-tokens" target = "_blank">Create one here</a> and <b>uncheck</b> `Require Authentication`.',
                    'type'              => "text",
                    'value'             => $this->config["settings"]["bitpay_checkout_token_prod"] ?? '',
                ],
                'bitpay_checkout_endpoint'          => [
                    'name'              => "Endpoint",
                    'description'       => 'Select <b>Test</b> for testing the plugin, <b>Production</b> when you are ready to go live.',
                    'type'              => "dropdown",
                    'options'           => 'Test,Production',
                    'value'             => $this->config["settings"]["bitpay_checkout_endpoint"] ?? '',
                ],
                'bitpay_checkout_mode'          => [
                    'name'              => "Payment UX",
                    'description'       => 'Select <b>Modal</b> to keep the user on the invoice page, or  <b>Redirect</b> to have them view the invoice at BitPay.com, and be redirected after payment.',
                    'type'              => "dropdown",
                    'options'           => 'Modal,Redirect',
                    'value'             => $this->config["settings"]["bitpay_checkout_mode"] ?? '',
                ],
            ];
        }

        public function area($params=[])
        {

            // Invoice Parameters
            $invoiceId = $this->checkout_id;
            $description = 'Invoice Payment';
            $amount = $params['amount'];
            $currencyCode = $params['currency'];
            // Client Parameters
            $firstname  = $this->clientInfo->name;
            $lastname   = $this->clientInfo->surname;
            $email      = $this->clientInfo->email;
            $address1   = $this->clientInfo->address->address;
            $address2   = '';
            $city       = $this->clientInfo->address->city;
            $state      = $this->clientInfo->address->counti;
            $postcode   = $this->clientInfo->address->zipcode;
            $country    = $this->clientInfo->address->country_code;
            $phone      = $this->clientInfo->phone;

            // System Parameters

            $returnUrl = $this->links['return'];
            $langPayNow = $this->l_payNow;

            $url                      = '';


            $postfields = array();
            $postfields['username'] = '';
            $postfields['invoice_id'] = $invoiceId;
            $postfields['description'] = $description;
            $postfields['amount'] = $amount;
            $postfields['currency'] = $currencyCode;
            $postfields['first_name'] = $firstname;
            $postfields['last_name'] = $lastname;
            $postfields['email'] = $email;
            $postfields['address1'] = $address1;
            $postfields['address2'] = $address2;
            $postfields['city'] = $city;
            $postfields['state'] = $state;
            $postfields['postcode'] = $postcode;
            $postfields['country'] = $country;
            $postfields['phone'] = $phone;
            $postfields['return_url'] = $returnUrl;

            $htmlOutput = '<form method="post" action="' . $url . '">';
            foreach ($postfields as $k => $v) {
                $htmlOutput .= '<input type="hidden" name="' . $k . '" value="' . urlencode($v) . '" />';
            }



            #BITPAY INVOICE DETAILS
            $wh = new BPC_Wh();
            $this->config['settings']['bitpay_checkout_endpoint'] = strtolower($this->config['settings']['bitpay_checkout_endpoint']);
            $this->config['settings']['bitpay_checkout_mode'] = strtolower($this->config['settings']['bitpay_checkout_mode']);

            $bitpay_checkout_token = $wh->BPC_getBitPayToken($this->config['settings']['bitpay_checkout_endpoint'], $this);
            $bitpay_checkout_endpoint = $this->config['settings']['bitpay_checkout_endpoint'];
            $bitpay_checkout_mode = $this->config['settings']['bitpay_checkout_mode'];

            $config = new BPC_Configuration($bitpay_checkout_token, $this->config['settings']['bitpay_checkout_endpoint']);

            $params = new stdClass();

            $callback_url               = $this->links["callback"];
            $params->extension_version  = 'BitPay_Checkout_WHMCS'. '_4.0.1';
            $params->price = $amount;
            $params->currency = $currencyCode;
            $params->orderId = trim($invoiceId);
            $params->notificationURL = $this->links["ipn"];
            $params->redirectURL = $this->links["return"];
            $params->extendedNotifications = true;


            #set the transaction speed in the plugin and override the plugin

            $params->acceptanceWindow = 1200000;
            if (!empty($email)):
                $buyerInfo = new stdClass();
                $buyerInfo->name = $firstname . ' ' . $lastname;
                $buyerInfo->email = $email;
                $params->buyer = $buyerInfo;
            endif;

            $item = new BPC_Item($config, $params);
            $invoice = new BPC_Invoice($item);
            //this creates the invoice with all of the config params from the item
            $invoice->BPC_createInvoice();
            $invoiceData = json_decode($invoice->BPC_getInvoiceData());
            $invoiceID = $invoiceData->data->id;

            error_log("=======USER LOADED BITPAY CHECKOUT INVOICE=====");
            error_log(date('d.m.Y H:i:s'));
            # error_log(print_r($invoiceData, true));
            error_log("=======END OF INVOICE==========================");
            error_log(print_r($params,true));

            $this->save_custom_data([
                'transaction_id'        => $invoiceID,
                'transaction_status'    => 'new',
                ]);

            if($bitpay_checkout_mode == 'modal'):
                $htmlOutput .= '<div align="center"><button name = "bitpay-payment" class = "lbtn green" onclick = "showModal(\'' . base64_encode($invoice->BPC_getInvoiceData()) . '\');return false;">' . $langPayNow . '</button></div>';
            else:
                $htmlOutput .= '<div align="center"><button name = "bitpay-payment" class = "lbtn green" onclick = "redirectURL(\'' . $invoiceData->data->url. '\');return false;">' . $langPayNow . '</button></div>';

            endif;

            ?>
            <script src="//bitpay.com/bitpay.min.js" type="text/javascript"></script>
            <script src="//ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
            <script type='text/javascript'>
                function redirectURL($url){
                    window.location=$url;
                }
                function showModal(invoiceData) {
                    $post_url = '<?php echo $callback_url; ?>'
                    $idx = $post_url.indexOf('https')
                    if($idx == -1 && location.protocol == 'https:'){
                        $post_url = $post_url.replace('http','https')
                    }


                    $encodedData = invoiceData
                    invoiceData = atob(invoiceData);

                    var payment_status = null;
                    var is_paid = false
                    window.addEventListener("message", function(event) {
                        payment_status = event.data.status;
                        if(payment_status == 'paid' || payment_status == 'confirmed' || payment_status == 'complete'){
                            is_paid = true
                        }
                        if (is_paid == true) {
                            //just some test stuff
                            var saveData = jQuery.ajax({
                                type: 'POST',
                                url: $post_url,
                                data: $encodedData,
                                dataType: "text",
                                success: function(resultData) {
                                    location.reload();
                                },
                                error: function(resultData) {
                                    //console.log('error', resultData)
                                }
                            });
                        }
                    }, false);

                    //show the modal
                    <?php if ($bitpay_checkout_endpoint == 'test'): ?>
                    bitpay.enableTestMode()
                    <?php endif;?>
                    bitpay.showInvoice('<?php echo $invoiceID; ?>');
                }
            </script>
            <?php

            $htmlOutput .= '</form>';
            return $htmlOutput;

        }

        public function callback()
        {
            $all_data = (file_get_contents("php://input"));
            $all_data=base64_decode($all_data);
            $all_data = json_decode($all_data);

            // Retrieve data returned in payment gateway callback
// Varies per payment gateway
            $success = true;
            $invoiceId = $all_data->data->orderId;
            $transactionId =$all_data->data->id;


            $custom_id      = (int) $invoiceId;

            if(!$custom_id){
                $this->error = 'ERROR: checkout id is wrong';
                return false;
            }

            $checkout       = $this->get_checkout($custom_id);

            // Checkout invalid error
            if(!$checkout)
            {
                $this->error = 'Checkout ID unknown';
                return false;
            }

            // You introduce checkout to the system
            $this->set_checkout($checkout);

            if ($success) {
                $custom_data        = $this->get_custom_data();
                $custom_data['transaction_status'] = "paid";
                $this->save_custom_data($custom_data);
            }

            return [
                'status'            => 'pending',
                'message'        => [
                    'Transaction ID' => $transactionId,
                ],
                // Write if you want to show a message to the person on the callback page.
                'callback_message'        => 'Successful',
            ];
        }

        public function ipn()
        {
            $all_data = json_decode(file_get_contents("php://input"), true);
            $file = $this->dir.'bitpay.txt';
            $err = $this->dir."bitpay_err.txt";

            file_put_contents($file,"===========INCOMING IPN=========================",FILE_APPEND);
            file_put_contents($file,date('d.m.Y H:i:s'),FILE_APPEND);
            file_put_contents($file,print_r($all_data, true),FILE_APPEND);
            file_put_contents($file,"===========END OF IPN===========================",FILE_APPEND);

            $data = $all_data['data'];
            $order_status = $data['status'];
            $order_invoice = $data['id'];
            $endpoint = $this->config['settings']['bitpay_checkout_endpoint'];
            if($endpoint == "Test"):
                $url_check = 'https://test.bitpay.com/invoices/'.$order_invoice;
            else:
                $url_check = 'https://www.bitpay.com/invoices/'.$order_invoice;
            endif;
            $invoiceStatus = json_decode($this->checkInvoiceStatus($url_check));

            $event = $all_data['event'];
            $orderid = $invoiceStatus->data->orderId;
            $price = $invoiceStatus->data->price;

            $checkout = $this->get_checkout($orderid);

            if(!$checkout)
            {
                file_put_contents($err,$orderid.' not found',FILE_APPEND);
                exit('checkout is wrong');
            }

            $this->set_checkout($checkout);
            $rowdata     = $this->get_custom_data();
            $btn_id     = $rowdata['transaction_id'] ?? 0;

            $invoice_ids    = [];

            if(isset($this->checkout['data']['invoice_id']))
                $invoice_ids[] = $this->checkout['data']['invoice_id'];

            if(isset($this->checkout['data']['invoices']))
                $invoice_ids = $this->checkout['data']['invoices'];



            if($btn_id):
                switch ($event['name']) {
                    #complete, update invoice table to Paid
                    case 'invoice_confirmed':
                        if($invoice_ids) foreach($invoice_ids AS $inv_id) Invoices::MakeOperation('paid',$inv_id,true);
                        $rowdata['status'] = $event['name'];
                        $this->save_custom_data($rowdata);
                        break;
                    #processing - put in Payment Pending
                    case 'invoice_paidInFull':
                        if($invoice_ids) foreach($invoice_ids AS $inv_id) Invoices::MakeOperation('waiting',$inv_id,true);
                        $rowdata['status'] = $event['name'];
                        $this->save_custom_data($rowdata);
                        break;
                    #update both table to refunded
                    case 'invoice_refundComplete':
                        if($invoice_ids) foreach($invoice_ids AS $inv_id) Invoices::MakeOperation('refund',$inv_id,true);
                        $rowdata['status'] = $event['name'];
                        $this->save_custom_data($rowdata);
                        break;
                }
                http_response_code(200);
            endif;#end of the table lookup
        }

        private function checkInvoiceStatus($url){
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);
            return $result;
        }

        public function refund($checkout=[])
        {
            $custom_id      = $checkout["id"];
            $api_key        = $this->config["settings"]["example1"] ?? 'N/A';
            $secret_key     = $this->config["settings"]["example2"] ?? 'N/A';
            $amount         = $checkout["total"];
            $currency       = $this->currency($checkout["currency"]);

            // Here we are making an API call.
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, "api.sample.com/refund/".$custom_id);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'APIKEY: '.$api_key,
                'SECRET: '.$secret_key,
                'Content-Type: application/json',
            ));
            $result = curl_exec($curl);
            if(curl_errno($curl))
            {
                $result      = false;
                $this->error = curl_error($curl);
            }
            $result             = json_decode($result,true);

            if($result && $result['status'] == 'OK') $result = true;
            else
            {
                $this->error = $result['message'] ?? 'something went wrong';
                $result = false;
            }

            return $result;
        }

    }