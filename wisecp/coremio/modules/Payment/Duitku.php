<?php
    class Duitku_ApiRequestor {

        public static function get($url, $data_hash)
        {
            return self::remoteCall($url, $data_hash, false);
        }

        public static function post($url, $data_hash)
        {
            return self::remoteCall($url, $data_hash, true);
        }

        public static function remoteCall($url, $data_hash, $post = true)
        {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Accept: application/json',
            ));

            if ($post) {
                curl_setopt($ch, CURLOPT_POST, 1);

                if ($data_hash) {
                    $body = json_encode($data_hash);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                }
                else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, '');
                }
            }

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            $result = curl_exec($ch);
            //curl_close($ch);

            if ($result === FALSE) {
                throw new Exception('CURL Error: ' . curl_error($ch), curl_errno($ch));
            }
            else {
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $result_array = json_decode($result);
                if ($httpcode != 200) {
                    $message = 'Duitku Error (' . $result . '): ';
                    throw new Exception($message, $httpcode);
                }
                else {
                    return $result_array;
                }
            }
        }
    }
    class Duitku_WebCore {

        public static function getRedirectionUrl($baseUrl, $params)
        {
            //$payloads = array();
            //$payloads = array_replace_recursive($payloads, $params);

            if ($params['paymentMethod'] == 'MG') {
                $result = Duitku_ApiRequestor::post($baseUrl . '/api/merchant/creditcard/inquiry',$params);
            } else {
                $result = Duitku_ApiRequestor::post($baseUrl . '/api/merchant/v2/inquiry',$params);
            }

            //var_dump($result);
            //die();
            return $result->paymentUrl;
        }

        public static function validateTransaction($baseUrl, $merchantCode, $order_id, $reference, $apikey)
        {
            $url = $baseUrl . '/api/merchant/transactionStatus';

            //generate Signature
            $signature = md5($merchantCode . $order_id . $apikey);

            // Prepare Parameters
            $params = array(
                'merchantCode' => $merchantCode, // API Key Merchant /
                'merchantOrderId' => $order_id,
                'signature' => $signature,
                'reference' => $reference,
            );

            //throw error if failed
            $result = Duitku_ApiRequestor::post($url,$params);

            if ($result->statusCode == "00")
                return true;
            else
                return false;
        }
    }
    class Duitku_Config {

        public static $serverKey;
        public static $apiVersion = 2;
        public static $isProduction = false;
        public static $isSanitized = true;

        const SANDBOX_BASE_URL = 'https://sandbox.duitku.com/webapi';
        const PRODUCTION_BASE_URL = 'https://passport.duitku.com/webapi';

        public static function getBaseUrl()
        {
            return Duitku_Config::$isProduction ?
                Duitku_Config::PRODUCTION_BASE_URL : Duitku_Config::SANDBOX_BASE_URL;
        }
    }

    Class Duitku
    {
        static $gateway;

        static function init($gateway)
        {
            self::$gateway = $gateway;

        }

        public static function config()
        {
            return [
                'merchant_code'             => [
                    'name'              => "Duitku Merchant Code",
                    'description'       => "Input Duitku Merchant Code.",
                    'type'              => "text",
                    'value'             => self::$gateway->config["settings"]["merchant_code"] ?? '',
                ],
                'server_key'                => [
                    'name'              => "Duitku API Key",
                    'description'       => "Input Duitku API Key.",
                    'type'              => "text",
                    'value'             => self::$gateway->config["settings"]["server_key"] ?? '',
                ],
                'endpoint'                  => [
                    'name'              => "Duitku Endpoint",
                    'description'       => "Duitku Endpoint, mohon isi merchant code dan api key sebelum mengakses endpoint.",
                    'type'              => "text",
                    'value'             => self::$gateway->config["settings"]["endpoint"] ?? 'https://passport.duitku.com/webapi',
                ],
                'expiry_period'             => [
                    'name'              => "Duitku Expiry Period",
                    'description'       => "The validity period of the transaction before it expires. Max 1440 in minutes.",
                    'type'              => "text",
                    'value'             => self::$gateway->config["settings"]["expiry_period"] ?? '1440',
                ],
            ];
        }

        public static function link($params=[])
        {
            $method             = self::$gateway->duitku_p_code;
            $merchant_code      = self::$gateway->config['settings']['merchant_code'];
            $amount             = $params['amount'];
            $order_id           = self::$gateway->checkout_id;
            $serverkey          = self::$gateway->config['settings']['server_key'];
            $endpoint           = self::$gateway->config['settings']['endpoint'];
            $expiryPeriod       = self::$gateway->config['settings']['expiry_period'];
            $credcode           = self::$gateway->config['settings']['credcode'] ?? '';

            if (empty($merchant_code) || empty($serverkey) || empty($endpoint))
                return "Please Check Duitku Configuration Payment";

            $companyName    = __("website/index/meta/title");
            $systemUrl      = APP_URI;
            $returnUrl      = self::$gateway->links["return"];
            $paymentMethod  = $method;

            // Client Parameters
            $firstname      = self::$gateway->clientInfo->name;
            $lastname       = self::$gateway->clientInfo->surname;
            $email          = self::$gateway->clientInfo->email;
            $phoneNumber    = self::$gateway->clientInfo->phone;
            $postalCode     = self::$gateway->clientInfo->address->zipcode;
            $country        = self::$gateway->clientInfo->address->country_code;
            $address1       = self::$gateway->clientInfo->address->address;
            $address2       = '';
            $city           = self::$gateway->clientInfo->address->city;
            $description    = self::$gateway->checkout["items"][0]['name'] ?? 'Invoice Payment';

            $ProducItem = array(
                'name' => $description,
                'price' => intval($amount),
                'quantity' => 1
            );

            $item_details = array ($ProducItem);

            $billing_address = array(
                'firstName' => $firstname,
                'lastName' => $lastname,
                'address' => $address1 . " " . $address2,
                'city' => $city,
                'postalCode' => $postalCode,
                'phone' => $phoneNumber,
                'countryCode' => $country
            );

            $customerDetails = array(
                'firstName' => $firstname,
                'lastName' => $lastname,
                'email' => $email,
                'phoneNumber' => $phoneNumber,
                'billingAddress' => $billing_address,
                'shippingAddress' => $billing_address
            );

            $signature = md5($merchant_code.$order_id.$amount.$serverkey);

            // Prepare Parameters
            $params = array(
                'merchantCode' => $merchant_code, // API Key Merchant /
                'paymentAmount' => $amount, //transform order into integer
                'paymentMethod' => $paymentMethod,
                'merchantOrderId' => $order_id,
                'productDetails' => $companyName . ' Order : #' . $order_id,
                'additionalParam' => '',
                'merchantUserInfo' => $firstname . " " .  $lastname,
                'customerVaName' => $firstname . " " .  $lastname,
                'email' => $email,
                'phoneNumber' => $phoneNumber,
                'signature' => $signature,
                'expiryPeriod' => $expiryPeriod,
                'returnUrl'     => self::$gateway->links["callback"]."?return=true",
                'callbackUrl'   => self::$gateway->links["callback"],
                'customerDetail' => $customerDetails,
                'itemDetails' => $item_details
            );

            if ($params['paymentMethod'] == 'MG') {
                $params['credCode'] = $credcode;
            }


            try {

                $redirUrl = Duitku_WebCore::getRedirectionUrl($endpoint, $params);

                //Set Log
                Modules::save_log("Payment",__CLASS__,"link",$params,$redirUrl);
            }
            catch (Exception $e) {
                return 'Caught exception: '.$e->getMessage();
            }

            header('Location: ' . $redirUrl);
            return 'Redirecting...';
        }

        public static function callback()
        {
            if(Filter::init("GET/return"))
            {

                if (empty($_REQUEST['resultCode']) || empty($_REQUEST['merchantOrderId']) || empty($_REQUEST['reference'])) exit('wrong query string please contact admin.');

                $order_id = (int) stripslashes($_REQUEST['merchantOrderId']);
                $status = stripslashes($_REQUEST['resultCode']);
                $reference = stripslashes($_REQUEST['reference']);

                $checkout       = self::$gateway->get_checkout($order_id);

                if(!$checkout)
                {
                    echo 'Checkout not found';
                    exit;
                }

                self::$gateway->set_checkout($checkout);


                if ($status == '00')
                    $url = self::$gateway->links["successful"];
                else if ($_REQUEST['resultCode'] == '01')
                    $url = self::$gateway->links["return"];
                else
                    $url = self::$gateway->links["failed"];

                header('Location: ' . $url);

                echo 'Redirecting...';

                exit;
            }

            $paymentCode= stripslashes($_POST['paymentCode']);

            if (empty($_POST['resultCode']) || empty($_POST['merchantOrderId']) || empty($_POST['reference']))
                return [
                    'status'        => "error",
                    'message'       => 'wrong query string please contact admin.',
                    'callback_message'       => 'wrong query string please contact admin.',
                ];

            $order_id = (int) stripslashes($_POST['merchantOrderId']);
            $status = stripslashes($_POST['resultCode']);
            $reference = stripslashes($_POST['reference']);
            $paymentAmount = stripslashes($_POST['amount']);
//set parameters for Duitku inquiry
            $merchant_code  = self::$gateway->config['settings']['merchant_code'];
            $api_key        = self::$gateway->config['settings']['server_key'];
            $endpoint       = self::$gateway->config['settings']['endpoint'];

            $success = false;

            $checkout           = self::$gateway->get_checkout($order_id);

            if(!$checkout)
                return [
                    'status'        => "error",
                    'message'       => 'checkout not found',
                    'callback_message' => 'checkout not found',
                ];

            self::$gateway->set_checkout($checkout);

            if($status == '00' && Duitku_WebCore::validateTransaction($endpoint, $merchant_code, $order_id, $reference, $api_key)) $success = true;
            else $success = false;


            if($success)
                return [
                    'status'        => 'successful',
                    'message'       => [
                        'Reference' => $reference,
                    ],
                    'callback_message' => "Payment success notification accepted",
                ];

            header("HTTP/1.0 406 Not Acceptable");
            Modules::save_log("Payment",__CLASS__,"callback",false,$_REQUEST,"Unsuccessful");
            return [
                'status'        => 'error',
                'message'       => "Payment success notification accepted",
                'callback_message' => "Payment success notification accepted",
            ];

        }
    }