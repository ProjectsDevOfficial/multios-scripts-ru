<?php

    namespace Tripay;
    
    use Exception;

    class Core
    {
        public static $gateway;
        public static $production_baseurl = 'https://tripay.co.id/api';
        public static $sandbox_baseurl = 'https://tripay.co.id/api-sandbox';
        
        public static function modules($code = null)
        {
            $modules = [
                'PERMATAVA' => [
                    'gateway'   => 'Tripay_permata_va',
                    'icon'      => 'permata-va.png',
                    'type'      => 'REDIRECT'
                ],
                'BRIVA' => [
                    'gateway'   => 'Tripay_bri_va',
                    'icon'      => 'bri-va.png',
                    'type'      => 'REDIRECT'
                ],
                'BNIVA' => [
                    'gateway'   => 'Tripay_bni_va',
                    'icon'      => 'bni-va.png',
                    'type'      => 'REDIRECT'
                ],
                'BCAVA' => [
                    'gateway'   => 'Tripay_bca_va',
                    'icon'      => 'bca-va.png',
                    'type'      => 'REDIRECT'
                ],
                'MANDIRIVA' => [
                    'gateway'   => 'Tripay_mandiri_va',
                    'icon'      => 'mandiri-va.png',
                    'type'      => 'REDIRECT'
                ],
                'MYBVA' => [
                    'gateway'   => 'Tripay_maybank_va',
                    'icon'      => 'maybank-va.png',
                    'type'      => 'REDIRECT'
                ],
                'SMSVA' => [
                    'gateway'   => 'Tripay_permatava',
                    'icon'      => 'sms-va.png',
                    'type'      => 'REDIRECT'
                ],
                'MUAMALATVA' => [
                    'gateway'   => 'Tripay_muamalat_va',
                    'icon'      => 'muamalat-va.png',
                    'type'      => 'REDIRECT'
                ],
                'CIMBVA' => [
                    'gateway'   => 'Tripay_cimb_va',
                    'icon'      => 'cimb-va.png',
                    'type'      => 'REDIRECT'
                ],
                'INDOMARET' => [
                    'gateway'   => 'Tripay_indomaret',
                    'icon'      => 'indomaret.png',
                    'type'      => 'REDIRECT'
                ],
                'ALFAMART' => [
                    'gateway'   => 'Tripay_alfamart',
                    'icon'      => 'alfamart.png',
                    'type'      => 'REDIRECT'
                ],
                'ALFAMIDI' => [
                    'gateway'   => 'Tripay_alfamidi',
                    'icon'      => 'alfamidi.png',
                    'type'      => 'REDIRECT'
                ],
                'QRIS' => [
                    'gateway'   => 'Tripay_permatava',
                    'icon'      => 'qris.png',
                    'type'      => 'REDIRECT'
                ],
                'QRISC' => [
                    'gateway'   => 'Tripay_permatava',
                    'icon'      => 'qris.png',
                    'type'      => 'REDIRECT'
                ],
                'CC' => [
                    'gateway'   => 'Tripay_cc',
                    'icon'      => 'cc.png',
                    'type'      => 'REDIRECT'
                ],
            ];

            $code = strtoupper($code);

            return $code ? (isset($modules[$code]) ? $modules[$code] : null) : $modules;
        }

        public static function getRedirectionUrl($baseUrl, $params, $apiKey = "")
        {
            $result = ApiRequestor::post(rtrim($baseUrl, '/') . '/transaction/create', $params, $apiKey);
            return $result->data->checkout_url;
        }

        public static function requestTransaction($baseUrl, $params, $apiKey = "")
        {
            $result = ApiRequestor::post(rtrim($baseUrl, '/') . '/transaction/create', $params, $apiKey);
            return $result->data;
        }

        public static function config()
        {
            return array(
                'mode' => [
                    'name'              => "Mode Integrasi",
                    'description'       => "<small><b>Sandbox</b> digunakan untuk masa pengembangan<br/><b>Production</b> digunakan untuk transaksi riil</small>",
                    'type'              => "dropdown",
                    'options'           => [
                        'sandbox' => 'Sandbox',
                        'production' => 'Production',
                    ],
                    'value'             => self::$gateway->config["settings"]["mode"] ?? 'sandbox',
                ],
                'merchantCode' => [
                    'name'              => "Merchant Code",
                    'description'       => '<small>Untuk mode <b>Sandbox</b> lihat <a href="https://tripay.co.id/simulator/merchant" target="_blank" style="text-decoration:underline">di sini</a><br/>Untuk mode <b>Production</b> lihat <a href="https://tripay.co.id/member/merchant" target="_blank" style="text-decoration:underline">di sini</a></small>',
                    'type'              => "text",
                    'value'             => self::$gateway->config["settings"]["merchantCode"] ?? '',
                ],
                'apiKey' => [
                    'name'              => "API Key",
                    'description'       => '<small>Untuk mode <b>Sandbox</b> lihat <a href="https://tripay.co.id/simulator/merchant" target="_blank" style="text-decoration:underline">di sini</a><br/>Untuk mode <b>Production</b> lihat <a href="https://tripay.co.id/member/merchant" target="_blank" style="text-decoration:underline">di sini</a> lalu klik tombol <b>Opsi > Edit</b></small>',
                    'type'              => "text",
                    'value'             => self::$gateway->config["settings"]["apiKey"] ?? '',
                ],
                'privateKey' => [
                    'name'              => "Private Key",
                    'description'       => '<small>Untuk mode <b>Sandbox</b> lihat <a href="https://payment.tripay.co.id/simulator/merchant" target="_blank" style="text-decoration:underline">di sini</a><br/>Untuk mode <b>Production</b> lihat <a href="https://tripay.co.id/member/merchant" target="_blank" style="text-decoration:underline">di sini</a> lalu klik tombol <b>Opsi > Edit</b></small>',
                    'type'              => "text",
                    'value'             => self::$gateway->config["settings"]["privateKey"] ?? '',
                ],
                'expired' => [
                    'name'              => "Masa Aktif",
                    'description'       => "<small>Masa Berlaku Kode Bayar/Nomor VA</small>",
                    'type'              => "dropdown",
                    'options'           => [
                        '1' => '1 Hari',
                        '2' => '2 Hari',
                        '3' => '3 Hari',
                        '4' => '4 Hari',
                        '5' => '5 Hari',
                        '6' => '6 Hari',
                        '7' => '7 Hari',
                        '8' => '8 Hari',
                        '9' => '9 Hari',
                        '10' => '10 Hari',
                        '11' => '11 Hari',
                        '12' => '12 Hari',
                        '13' => '13 Hari',
                        '14' => '14 Hari',
                    ],
                    'value'             => self::$gateway->config["settings"]["expired"] ?? '',
                ],
                'checkout_type' => [
                    'name'              => "Tipe Checkout",
                    'description'       => "",
                    'type'              => "dropdown",
                    'options'           => [
                        'REDIRECT' => 'REDIRECT',
                        'DIRECT' => 'DIRECT'
                    ],
                    'value'             => self::$gateway->config["settings"]["checkout_type"] ?? '',
                ],
                'verify_callback' => [
                    'name'              => "Verifikasi Data Callback",
                    'description'       => "<small>Aktifkan untuk mode Production dan nonaktifkan untuk mode Sandbox</small>",
                    'type'              => "dropdown",
                    'options'           => [
                        '0' => 'Tidak Aktif',
                        '1' => 'Aktif'
                    ],
                    'value'             => self::$gateway->config["settings"]["verify_callback"] ?? '',
                ]
            );
        }

        public static function return_url()
        {
            $protocol = self::getThisUrlProtocol();
            $hostname = self::getThisUrlHost();

            return rtrim($protocol.'://'.$hostname, '/').$_SERVER['REQUEST_URI'];
        }

        public static function callback_url()
        {
            return self::$gateway->links["callback"];
        }
        public static function link($params, $method_code)
        {
            $module = self::modules($method_code);

            if( is_null($module) ) {
                throw new Exception("Failed to generate payment link: Module not available");
            }

            $merchant_code = self::$gateway->config['settings']['merchantCode'];
            $amount = (int) $params['amount'];
            $order_id = self::$gateway->checkout_id;
            $apiKey = self::$gateway->config['settings']['apiKey'];
            $privateKey = self::$gateway->config['settings']['privateKey'];
            $baseurl = (isset(self::$gateway->config['settings']['mode']) && self::$gateway->config['settings']['mode'] == 'production') ? self::$production_baseurl : self::$sandbox_baseurl;
            $expired = (int) self::$gateway->config['settings']['expired'];
            $firstname = self::$gateway->clientInfo->name;
            $lastname =  self::$gateway->clientInfo->surname;
            $email =  self::$gateway->clientInfo->email;
            $phoneNumber =  self::$gateway->clientInfo->phone;
            $langPayNow =  self::$gateway->l_payNow;
            $checkout_type = isset(self::$gateway->config['settings']['checkout_type']) ? self::$gateway->config['settings']['checkout_type'] : 'REDIRECT';

            $ProducItem = array(
                'name' => 'Checkout ID :# '.self::$gateway->checkout_id,
                'price' => $amount,
                'quantity' => 1
            );

            $item_details = array($ProducItem);

            $signature = hash_hmac("sha256", $merchant_code.$order_id.$amount, $privateKey);

            if( !in_array(substr($phoneNumber, 0, 1), ['0', '+']) ) {
                $phoneNumber = '0'.$phoneNumber;
            }

            $expired = $expired <= 0 ? 1 : $expired;

            $params = array(
                "amount" => $amount,
                "method" => $method_code,
                "merchant_ref" => $order_id,
                "customer_name" => $firstname . " " .  $lastname,
                "customer_email" => $email,
                "customer_phone" => $phoneNumber,
                "expired_time" => (time()+(24*60*60*intval($expired))),
                "return_url" => self::return_url(),
                "order_items" => $item_details,
                "signature" => $signature,
            );

            try
            {
                $trx = self::requestTransaction($baseurl, $params, $apiKey);
                $redirUrl = !empty($trx->pay_url) ? $trx->pay_url : $trx->checkout_url;
                $payCode = $trx->pay_code;
                $expiredTime = $trx->expired_time;

                switch(date_default_timezone_get())
                {
                    case 'Asia/Jakarta':
                        $tz = 'WIB';
                        break;

                    case 'Asia/Makassar':
                    case 'Asia/Pontianak':
                        $tz = 'WITA';
                        break;

                    case 'Asia/Jayapura':
                        $tz = 'WIT';
                        break;

                    default:
                        $tz = '';
                        break;
                }
            }
            catch (Exception $e) {
                \Modules::save_log("Payment","Tripay","link",false,$e->getMessage());
                throw new Exception("Failed to generate payment link: ".$e->getMessage());
            }



            $htmlOutput = "";

            if( $checkout_type != 'DIRECT' || $module['type'] == 'REDIRECT' )
            {
                $htmlOutput .= '<div align="center"><button class="lbtn green" onclick="javascript:window.location.href=\'' . $redirUrl . '\'">' . $langPayNow . '</button></div>';
            }
            else
            {
                $htmlOutput .= 'Kode Bayar/No. VA : <b>'.$payCode.'</b><br/>';
                $htmlOutput .= 'Batas Pembayaran : <b>'.strftime('%d %B %Y %H:%M', $expiredTime).' '.$tz.'</b>';
            }

            return $htmlOutput;
        }

        public static function getThisUrlProtocol()
        {
            if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
                if ( $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' ) {
                    $_SERVER['HTTPS']       = 'on';
                    $_SERVER['SERVER_PORT'] = 443;
                }
            }
            $protocol = 'http';
            if (isset($_SERVER['HTTPS'])) {
                $protocol = (($_SERVER['HTTPS'] == 'on') ? 'https' : 'http');
            } else {
                $protocol = (isset($_SERVER["SERVER_PROTOCOL"]) ? $_SERVER["SERVER_PROTOCOL"] : 'http');
                $protocol = ((strtolower(substr($protocol, 0, 5)) =='https') ? 'https': 'http');
            }
            return $protocol;
        }

        public static function getThisUrlHost()
        {
            $currentPath = (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
            $pathInfo = pathinfo(dirname($currentPath));
            $hostName = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
            return $hostName;
        }

        public static function callback()
        {
            $json = file_get_contents("php://input");

            if( empty($json) ) exit("Invalid JSON");

            $data = json_decode($json);

            $paymentCode = isset($data->payment_method_code) ? strtoupper($data->payment_method_code) : '';

            $module = \Tripay\Core::modules($paymentCode);

            if( is_null($module) ) {
                \Modules::save_log("Payment","Tripay","callback","Unknown payment method", "TriPay Callback Invalid");
                exit("Unknown payment method");
            }

            $callbackSignature = isset($_SERVER['HTTP_X_CALLBACK_SIGNATURE']) ? $_SERVER['HTTP_X_CALLBACK_SIGNATURE'] : '';
            $signature = hash_hmac("sha256", $json, self::$gateway->config['settings']["privateKey"]);

            if( !hash_equals($signature, $callbackSignature) ){
                \Modules::save_log("Payment","Tripay","callback","Invalid Signature: incoming(".$callbackSignature.") vs local(".$signature.")", "TriPay Callback Invalid");
                exit("Invalid Signature");
            }

            $event = isset($_SERVER["HTTP_X_CALLBACK_EVENT"]) ? $_SERVER["HTTP_X_CALLBACK_EVENT"] : '';

            if( $event != 'payment_status')
            {
                \Modules::save_log("Payment","Tripay","callback",$event, "Unknown event type");
                exit('Unknown event type');
            }

            $reference = $data->reference;
            $order_id = $data->merchant_ref;
            $paymentAmount = $data->total_amount - $data->fee_customer;

            if( $data->status == 'PAID' )
            {
                $checkout   = self::$gateway->get_checkout($order_id);

                if(!$checkout)
                {
                    exit('Order ID is incorrect');
                }

                self::$gateway->set_checkout($checkout);

                if(!self::verify_callback($reference, ['PAID']))
                {
                    die("Callback verification failed");
                }

                return [
                    'status' => "successful",
                    'message'        => [
                        'Reference' => $reference,
                    ],
                    'callback_message' => json_encode(["success" => true, "message" => "Order ".$order_id." has been marked as PAID"]),
                ];
            }
            else
            {
                die("No action was taken. Current callback status is: ".$data->status);
            }
        }

        public static function verify_callback($reference, array $expectedStatus = [])
        {
            if( self::$gateway->connfig['settings']['verify_callback'] != '1' ) {
                return true;
            }

            $baseurl = (isset(self::$gateway->connfig['settings']['mode']) && self::$gateway->connfig['settings']['mode'] == 'production') ? \Tripay\Core::$production_baseurl : \Tripay\Core::$sandbox_baseurl;

            $url = rtrim($baseurl, '/').'/transaction/detail?reference='.$reference;

            try {
                $result = \Tripay\ApiRequestor::remoteCall($url, [], false, self::$gateway->connfig['settings']['apiKey']);
            } catch (\Exception $e) {
                die($e->getMessage());
            }

            if( empty($expectedStatus) || in_array(strtoupper($result->data->status), $expectedStatus) ) {
                return true;
            }

            return false;
        }
    }