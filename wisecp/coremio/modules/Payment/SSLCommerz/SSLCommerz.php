<?php
    class SSLCommerz extends PaymentGatewayModule
    {
        function __construct()
        {
            $this->name             = __CLASS__;

            parent::__construct();
        }

        public function config_fields()
        {
            return [
                'store_id'              => [
                    'name'              => "Store ID",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["store_id"] ?? '',
                ],
                'store_password'        => [
                    'name'              => "Store Password",
                    'type'              => "password",
                    'value'             => $this->config["settings"]["store_id"] ?? '',
                ],
                'testmode'          => [
                    'name'              => "Test Mode",
                    'description'       => "",
                    'type'              => "approval",
                    'value'             => 1,
                    'checked'           => (int) ($this->config["settings"]["testmode"] ?? 0),
                ],
                'easyCheckout'          => [
                    'name'              => "easyCheckout",
                    'description'       => "Enable for easyCheckout Popup",
                    'type'              => "approval",
                    'value'             => 1,
                    'checked'           => (int) ($this->config["settings"]["easyCheckout"] ?? 0),
                ],
            ];
        }

        public function area($params=[])
        {

            $gatewaystore_id        = trim($this->config['settings']['store_id']);
            $gatewaystore_password  = trim($this->config['settings']['store_password']);
            $gatewaybutton_text     = $this->lang["pay-button"];
            $gatewaytestmode        = $this->config['settings']['testmode'];
            $easyCheckout           = $this->config['settings']['easyCheckout'];

            if ($gatewaytestmode) {
                $url ='https://sandbox.sslcommerz.com/gwprocess/v4/api.php';
                $easy_url = 'https://sandbox.sslcommerz.com/embed.min.js';
            }
            else
            {
                $url ='https://securepay.sslcommerz.com/gwprocess/v4/api.php';
                $easy_url = 'https://seamless-epay.sslcommerz.com/embed.min.js';
            }

            $invoiceid              = $this->checkout_id;
            $description            = 'Invoice Payment';
            $amount                 = $params['amount']; # Format: ##.##
            $currency               = $params['currency']; # Currency Code
            $product                = 'Domain - Web Hosting';

            $firstname              = $this->clientInfo->name;
            $lastname               = $this->clientInfo->surname;
            $email                  = $this->clientInfo->email;
            $address1               = $this->clientInfo->address->address;
            $address2               = '';
            $city                   = $this->clientInfo->address->city;
            $state                  = $this->clientInfo->address->counti;
            $postcode               = $this->clientInfo->address->zipcode;
            $country                = $this->clientInfo->address->country_code;
            $phone                  = $this->clientInfo->phone;
            $uuid                   = $this->clientInfo->id;


            $companyname            = __("website/meta/title");
            $systemurl              = APP_URI;
            $currency               = $params['currency'];
            $returnurl              = $this->links['callback'];

            $success_url            = $this->links["successful"];
            $fail_url               = $this->links["failed"];
            $cancel_url             = $this->links["failed"];
            $ipn_url                = $this->links["callback"]."?callback_type=ipn";
            $easy_end_point         = $this->links["callback"]."?callback_type=checkout";

            $api_endpoint               = $url;


            $post_data = array();
            $post_data['store_id']      = $gatewaystore_id;
            $post_data['store_passwd']  = $gatewaystore_password;

            $post_data['total_amount']  = $amount;
            $post_data['currency']      = $currency;
            $post_data['tran_id']       = $invoiceid;
            $post_data['success_url']   = $success_url;
            $post_data['fail_url']      = $fail_url;
            $post_data['cancel_url']    = $cancel_url;
            $post_data['ipn_url']       = $ipn_url;
            $post_data['cus_name']      = $firstname.' '.$lastname;
            $post_data['cus_email']     = $email;
            $post_data['cus_phone']     = $phone;
            $post_data['cus_add1']      = $address1;
            $post_data['cus_city']      = $city;
            $post_data['cus_state']     = $state;
            $post_data['cus_postcode']  = $postcode;
            $post_data['cus_country']   = $country;
            $post_data['value_a']       = $description;
            $post_data['value_b']       = $returnurl;

            $post_data['shipping_method'] = 'NO';
            $post_data['num_of_item'] = '1';
            $post_data['product_name'] = $product;
            $post_data['product_profile'] = 'general';
            $post_data['product_category'] = 'Domain-Hosting';


            if($easyCheckout)
            {
                return '
                <script type="text/javascript">
                    (function (window, document) {
                        var loader = function () {
                            var script = document.createElement("script"), tag = document.getElementsByTagName("script")[0];
                            script.src = "'.$easy_url.'?" + Math.random().toString(36).substring(7);
                            tag.parentNode.insertBefore(script, tag);
                        };

                        window.addEventListener ? window.addEventListener("load", loader, false) : window.attachEvent("onload", loader);
                    })(window, document);
                </script>
                <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
                
                <button class="btn btn-success" id="sslczPayBtn"
            token="'.$uuid.'"
            postdata=""
            order="'.$invoiceid.'"
            endpoint="'.$easy_end_point.'">'.$gatewaybutton_text.'</button>
            
            <script>
                function changeObj() {
                    var obj = {};
                    var obj = { store_id: "'.$gatewaystore_id.'", tran_id: "'.$invoiceid.'", total_amount: "'.$amount.'", success_url: "'.$success_url.'", fail_url: "'.$fail_url.'", cancel_url: "'.$cancel_url.'", ipn_url: "'.$ipn_url.'", currency: "'.$currency.'", cus_name: "'.$firstname.' '.$lastname.'", cus_add1: "'.$address1.'", cus_add2: "'.$address2.'", cus_city: "'.$city.'", cus_state: "'.$state.'", cus_postcode: "'.$postcode.'", cus_country: "'.$country.'", cus_phone: "'.$phone.'", cus_email: "'.$email.'", value_a: "'.$description.'", value_b: "'.$returnurl.'", product_name: "'.$product.'"};
                    $("#sslczPayBtn").prop("postdata", obj);
                }
                changeObj();
            </script>';

            }
            else
            {
                $handle = curl_init();
                curl_setopt($handle, CURLOPT_URL, $api_endpoint );
                curl_setopt($handle, CURLOPT_TIMEOUT, 30);
                curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
                curl_setopt($handle, CURLOPT_POST, 1 );
                curl_setopt($handle, CURLOPT_POSTFIELDS, $post_data);
                curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, FALSE); # KEEP IT FALSE IF YOU RUN FROM LOCAL PC


                $content = curl_exec($handle);
                $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
                if($code == 200 && !( curl_errno($handle))) {
                    curl_close( $handle);
                    $sslcommerzResponse = $content;
                }
                else {
                    curl_close( $handle);
                    echo "FAILED TO CONNECT WITH SSLCOMMERZ API";
                    exit;
                }

                # PARSE THE JSON RESPONSE
                $sslcz = json_decode($sslcommerzResponse, true );

                if(isset($sslcz['GatewayPageURL']) && $sslcz['GatewayPageURL']!="")
                {
                    $code = '<form method="POST" action="'.$sslcz['GatewayPageURL'].'">
        		<input type="hidden" name="store_id" value="'.$gatewaystore_id.'" />
        		<input type="hidden" name="tran_id" value="'.$invoiceid.'" />
        		<input type="hidden" name="total_amount" value="'.$amount.'" />
        		<input type="hidden" name="success_url" value="'.$success_url.'" />
        		<input type="hidden" name="fail_url" value="'.$fail_url.'" />
        		<input type="hidden" name="cancel_url" value="'.$cancel_url.'" />
        		<input type="hidden" name="ipn_url" value="'.$ipn_url.'" />
        		<input type="hidden" name="currency" value="'.$currency.'" />
        		<input type="hidden" name="cus_name" value="'.$firstname.' '.$lastname.'" />
        		<input type="hidden" name="cus_add1" value="'.$address1.'" />
        		<input type="hidden" name="cus_add2" value="'.$address2.'" />
        		<input type="hidden" name="cus_city" value="'.$city.'" />
        		<input type="hidden" name="cus_state" value="'.$state.'" />
        		<input type="hidden" name="cus_postcode" value="'.$postcode.'" />
        
        		<input type="hidden" name="cus_country" value="'.$country.'" />
        		<input type="hidden" name="cus_phone" value="'.$phone.'" />
        		<input type="hidden" name="cus_email" value="'.$email.'" />
        
        		<input type="submit" class="lbtn" style="width:25%;" value="'.$gatewaybutton_text.'" />
        		
        		</form>';
                    return $code;
                }
                else {
                    echo "Failed Reason: ".$sslcz['failedreason'];
                }
            }

        }

        public function callback()
        {
            $c_type = Filter::init("GET/callback_type","hclear");
            if($c_type == "ipn")
            {

                $invoiceid  = (int) $_POST["tran_id"];
                $transid    = (int) $_POST["tran_id"];

                $checkout       = $this->get_checkout($invoiceid);

                if(!$checkout)
                {
                    $this->error = 'Checkout ID unknown';
                    return false;
                }


                $this->set_checkout($checkout);

                $store_id           = $this->config["settings"]["store_id"];
                $store_passwd       = $this->config["settings"]["store_password"];

                $val_id         = Filter::init("POST/val_id");

                $order_amount = $checkout["data"]['total'];


                if(!($_POST['status']=='VALID' && (isset($_POST['val_id']) && $_POST['val_id'] != "") && (isset($_POST['tran_id']) && $_POST['tran_id'] != "")))
                {
                    Modules::save_log("Payment",__CLASS__,"callback",false,$_POST);
                    return [
                        'status'        => "error",
                        'message'       => 'Payment Failed',
                    ];
                }


                if ($this->config["settings"]["testmode"])
                {
                    $requested_url = ("https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php?val_id=".$val_id."&Store_Id=".$store_id."&Store_Passwd=".$store_passwd."&v=1&format=json");
                }
                else
                {
                    $requested_url = ("https://securepay.sslcommerz.com/validator/api/validationserverAPI.php?val_id=".$val_id."&Store_Id=".$store_id."&Store_Passwd=".$store_passwd."&v=1&format=json");
                }

                $handle = curl_init();
                curl_setopt($handle, CURLOPT_URL, $requested_url);
                curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
                $result = curl_exec($handle);
                $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

                $status = 'failed';

                if($code == 200 && !( curl_errno($handle)))
                {
                    $result = json_decode($result);

                    $status = $result->status;
                    $tran_date = $result->tran_date;
                    $tran_id = $result->tran_id;
                    $val_id = $result->val_id;
                    $amount = intval($result->amount);
                    $store_amount = $result->store_amount;
                    $amount = intval($result->amount);
                    $bank_tran_id = $result->bank_tran_id;
                    $card_type = $result->card_type;
                    $base_amount = $result->currency_amount;
                    $risk_level = $result->risk_level;
                    $base_fair=$result->base_fair;
                    $value_total=$result->value_b;

                    if(($status=='VALID' || $status=='VALIDATED') && ($order_amount == $base_amount) && $risk_level == 0)
                    {
                        $status = 'success';
                    }
                }

                if($status != "success")
                {
                    $this->error = "Payment status failed";
                    return false;
                }


            }
            elseif($c_type == "checkout")
            {

                $gatewaytestmode        = $this->config["settings"]['testmode'];
                $easyCheckout           = $this->config["settings"]['gateway_type'];

                if ($gatewaytestmode)
                    $url ='https://sandbox.sslcommerz.com/gwprocess/v4/api.php';
                else
                    $url ='https://securepay.sslcommerz.com/gwprocess/v4/api.php';


                $tran_id = $_REQUEST['order'];
                $json_data = json_decode(html_entity_decode($_REQUEST['cart_json']), true);

                $post_data = array();
                $post_data['store_id']      = $this->config["settings"]['store_id'];
                $post_data['store_passwd']  = $this->config["settings"]['store_password'];
                $post_data['tran_id']       = $tran_id;
                $post_data['total_amount']  = $json_data['total_amount'];
                $post_data['currency']      = $json_data['currency'];
                $post_data['success_url']   = $json_data['success_url'];
                $post_data['fail_url']      = $json_data['fail_url'];
                $post_data['cancel_url']    = $json_data['cancel_url'];
                $post_data['ipn_url']       = $json_data['ipn_url'];
                $post_data['cus_name']      = $json_data['cus_name'];
                $post_data['cus_email']     = $json_data['cus_email'];
                $post_data['cus_phone']     = $json_data['cus_phone'];
                $post_data['cus_add1']      = $json_data['cus_add1'];
                $post_data['cus_city']      = $json_data['cus_city'];
                $post_data['cus_state']     = $json_data['cus_state'];
                $post_data['cus_postcode']  = $json_data['cus_postcode'];
                $post_data['cus_country']   = $json_data['cus_country'];
                $post_data['value_a']       = $json_data['value_a'];
                $post_data['value_b']       = $json_data['value_b'];
                $post_data['shipping_method'] = 'NO';
                $post_data['num_of_item'] = '1';
                $post_data['product_name'] = $json_data['product_name'];
                $post_data['product_profile'] = 'general';
                $post_data['product_category'] = 'Domain-Hosting';

                $handle = curl_init();
                curl_setopt($handle, CURLOPT_URL, $url);
                curl_setopt($handle, CURLOPT_POST, 1);
                curl_setopt($handle, CURLOPT_POSTFIELDS, $post_data);
                curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

                $content = curl_exec($handle);

                $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

                if(!($code == 200 && !(curl_errno($handle))))
                {
                    $this->error = 'payment failed';
                    return false;
                }

                curl_close($handle);
                $sslcommerzResponse = $content;

                # PARSE THE JSON RESPONSE
                if($sslcResponse = json_decode($sslcommerzResponse, true))
                {
                    if (isset($sslcResponse['status']) && $sslcResponse['status'] == 'SUCCESS')
                    {
                        if(isset($sslcResponse['GatewayPageURL']) && $sslcResponse['GatewayPageURL']!="")
                        {
                            if($gatewaytestmode)
                            {
                                echo json_encode(['status' => 'success', 'data' => $sslcResponse['GatewayPageURL'], 'logo' => $sslcResponse['storeLogo'] ]);
                            }
                            else
                            {
                                echo json_encode(['status' => 'SUCCESS', 'data' => $sslcResponse['GatewayPageURL'], 'logo' => $sslcResponse['storeLogo'] ]);
                            }
                            exit;
                        }
                        else
                        {
                            echo json_encode(['status' => 'FAILED', 'data' => null, 'message' => $sslcResponse['failedreason'] ]);
                        }
                    }
                    else
                    {
                        echo "API Response: ".$sslcResponse['failedreason'];
                    }
                }
                else
                {
                    echo "Connectivity Issue With API";
                }

                exit;
            }
            else
            {
                $invoiceid  = (int) $_POST["tran_id"];
                $transid    = (int) $_POST["tran_id"];


                $checkout       = $this->get_checkout($invoiceid);

                if(!$checkout)
                {
                    $this->error = 'Checkout ID unknown';
                    return false;
                }


                $this->set_checkout($checkout);


                $store_id           = $this->config["settings"]["store_id"];
                $store_passwd       = $this->config["settings"]["store_password"];

                if(!($_POST['status']=='VALID' && (isset($_POST['val_id']) && $_POST['val_id'] != "") && (isset($_POST['tran_id']) && $_POST['tran_id'] != "")))
                {
                    Modules::save_log("Payment",__CLASS__,"callback",false,$_POST);
                    return [
                        'status'        => "error",
                        'message'       => 'Payment Failed',
                    ];
                }

                $val_id         = Filter::init("POST/val_id");

                $order_amount = $checkout["data"]['total'];

                if($this->config["settings"]["testmode"])
                    $requested_url = ("https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php?val_id=".$val_id."&Store_Id=".$store_id."&Store_Passwd=".$store_passwd."&v=1&format=json");
                else
                    $requested_url = ("https://securepay.sslcommerz.com/validator/api/validationserverAPI.php?val_id=".$val_id."&Store_Id=".$store_id."&Store_Passwd=".$store_passwd."&v=1&format=json");


                $handle = curl_init();
                curl_setopt($handle, CURLOPT_URL, $requested_url);
                curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
                $results = curl_exec($handle);
                $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

                $status = 'failed';

                if($code == 200 && !( curl_errno($handle)))
                {
                    $result = json_decode($results);

                    $status = $result->status;
                    $tran_date = $result->tran_date;
                    $tran_id = $result->tran_id;
                    $val_id = $result->val_id;
                    $amount = intval($result->amount);
                    $store_amount = $result->store_amount;
                    $amount = intval($result->amount);
                    $bank_tran_id = $result->bank_tran_id;
                    $card_type = $result->card_type;
                    $base_amount = $result->currency_amount;
                    $risk_level = $result->risk_level;
                    $base_fair=$result->base_fair;
                    $value_total=$result->value_b;

                    if(($status=='VALID' || $status=='VALIDATED') && ($order_amount == $base_amount) && $risk_level == 0)
                    {
                        $status = 'success';
                    }
                }

                if($status != "success")
                {
                    $this->error = "Payment status failed";
                    return false;
                }
            }

            return [
                'status'            => 'successful',
            ];
        }

        /*
         * If your payment service provider does not support the refund feature, you can remove the functionality.
         */
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