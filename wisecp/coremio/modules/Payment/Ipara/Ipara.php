<?php
    class Ipara extends PaymentGatewayModule
    {
        function __construct()
        {
            $this->name             = __CLASS__;
            $this->standard_card    = true;

            parent::__construct();
        }

        public function config_fields()
        {

            return [
                'public_key'          => [
                    'name'              => "Mağaza açık anahtarı (PublicKey)",
                    'description'       => "Açık anahtar (publicKey) değerini iPara hesabınızdan (ipara.com) edinebilirsiniz.",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["public_key"] ?? '',
                ],
                'private_key'          => [
                    'name'              => "Kapalı Anahtar (PrivateKey)",
                    'description'       => "Kapalı anahtar (privateKey) değerini iPara hesabınızdan (ipara.com) edinebilirsiniz.",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["private_key"] ?? '',
                ],
                '3dmode'                   => [
                    'name'              => "3D Secure Yönlendirme Modu",
                    'description'       => "3D-Secure yöntemini seçiniz",
                    'type'              => "dropdown",
                    'options'           => [
                        'auto' => 'Otomatik (ÖNERİLİR) (iPara webservis ile seç)',
                        'on' => 'Tüm ödemeleri 3D Secure ile yaptır. (Daha güvenli)',
                        'off' => 'Tüm ödemeleri API ile yaptır. (Daha kolay daha hızlı)',
                    ],
                    'value'             => $this->config["settings"]["3dmode"] ?? '',
                ],
                'installment'              => [
                    'name'              => "Taksit Seçeneği",
                    'description'       => "Seçerseniz ödeme sırasında taksit seçeneği sunulur.",
                    'type'              => "approval",
                    'value'             => 1,
                    'checked'           => (boolean) (int) ($this->config["settings"]["installment"] ?? 0),
                ],
                'installment_commission'   => [
                    'name'              => "Taksit Komisyonu",
                    'description'       => "Komisyon oranı aşağıdaki gibi yazılmalıdır.<br>2 : 2.50<br>3 : 4.50<br>4 : 5.20",
                    'type'              => "textarea",
                    'value'           => $this->config["settings"]["installment_commission"] ?? '',
                ],
                'max_installment'          => [
                    'name'              => "Taksit Sınırı",
                    'description'       => "En fazla kaç taksit olacağını belirleyiniz.",
                    'type'              => "text",
                    'value'           => $this->config["settings"]["max_installment"] ?? '12',
                ],
            ];
        }

        public function installment_rates($card_bin = [])
        {
            if(!$this->config["settings"]["installment"]) return false;
            $rates      = $this->config['settings']['installment_commission'] ?? '';
            if(!$rates) return false;
            $lines      = explode("\n",$rates);
            $new_rate   = [];

            if($lines)
            {
                foreach($lines AS $line)
                {
                    $column = explode(" : ",$line);
                    $new_rate[$column[0]] = $column[1];
                }
            }

            return $new_rate;
        }

        public function post2iPara($params=[])
        {
            if(!class_exists('iParaPayment')) require_once(__DIR__ . DS . 'ipara_payment.php');

            $amount             = (float) ($params['amount']);
            $installment        = (int) $params["installment"] ?? 0;
            $fee                = number_format($amount, 2);
            $orderid            = 'WISECP' . $this->checkout_id . '-' . time();

            $public_key         = $this->config['settings']['public_key'];
            $private_key        = $this->config['settings']['private_key'];
            $ipara_3d_mode      = $this->config['settings']['3dmode'];
            $ipara_products     = [];  // aşağıda düzenlenecek;
            $ipara_address      = [];  //aşağıda düzenlenecek
            $ipara_purchaser    = [];  // aşağıda düzenlenecek


            // Kredi kartı bilgileri

            $ipara_card = [
                'owner_name'    => $params['holder_name'],
                'number'        => $params['num'],
                'expire_month'  => $params['expiry_m'],
                'expire_year'   => $params['expiry_y'],
                'cvc'           => $params['cvc']
            ];

            $record = [
                'id_cart'           => $this->checkout_id,
                'id_customer'       => $this->clientInfo->id,
                'amount'            => $amount,
                'amount_paid'       => 0,
                'fee'               => $fee,
                'cc_name'           => $ipara_card['owner_name'],
                'cc_expiry'         => $ipara_card['expire_month'] . $ipara_card['expire_year'],
                'cc_number'         => substr($ipara_card['number'], 0, 6) . 'XXXXXXXX' . substr($ipara_card['number'], -2),
                'id_ipara'          => $orderid,
                'result_code'       => '0',
                'result_message'    => '',
                'result'            => false
            ];

            if($installment > 0) $record['installment'] = $installment;




            // Müşteri
            $ipara_purchaser['name'] = $this->clientInfo->name;
            $ipara_purchaser['surname'] = $this->clientInfo->surname;
            $ipara_purchaser['email'] = $this->clientInfo->email;
            $ipara_purchaser['birthdate'] = NULL;
            $ipara_purchaser['gsm_number'] = NULL;
            $ipara_purchaser['tc_certificate_number'] = NULL;

            // ADRES
            $ipara_address['name'] = $this->clientInfo->name;
            $ipara_address['surname'] = $this->clientInfo->surname;
            $ipara_address['address'] = $this->clientInfo->address->address;
            $ipara_address['zipcode'] = $this->clientInfo->address->zipcode;
            $ipara_address['city_code'] = 34;
            $ipara_address['city_text'] = $this->clientInfo->address->city;
            $ipara_address['country_code'] = $this->clientInfo->address->country_code;
            $ipara_address['country_text'] = $this->clientInfo->address->country_name;
            $ipara_address['phone_number'] = $this->clientInfo->phone;
            $ipara_address['tax_number'] = NULL;
            $ipara_address['tax_office'] = NULL;
            $ipara_address['tc_certificate_number'] = NULL;
            $ipara_address['company_name'] = $this->clientInfo->company_name;

            // ÜRÜNLER
            $ipara_products[0]['title'] = $this->checkout["items"][0]['name'];
            $ipara_products[0]['code'] = $this->checkout_id;
            $ipara_products[0]['quantity'] = 1;
            $ipara_products[0]['price'] = $params['amount'];



            $obj = new iParaPayment();
            $obj->public_key = $public_key;
            $obj->private_key = $private_key;
            $obj->mode = "P";
            $obj->order_id = $orderid;
            if($installment > 0)
                $obj->installment = $installment;
            $obj->amount = $amount;
            $obj->vendor_id = 4;
            $obj->echo = "echo message";
            $obj->products = $ipara_products;
            $obj->shipping_address = $ipara_address;
            $obj->invoice_address = $ipara_address;
            $obj->card = $ipara_card;
            $obj->purchaser = $ipara_purchaser;
            $obj->success_url = $this->links["callback"]."?order_id=".$this->checkout_id;
            $obj->failure_url = $this->links["callback"]."?order_id=".$this->checkout_id;

            $check_ipara = $this->getiParaOptions($ipara_card['number']);

            if (!$check_ipara OR $check_ipara == NULL) {
                $check_ipara = (object) array(
                    'result_code' => "Webservis çalışmıyor",
                    'supportsInstallment' => "1",
                    'cardThreeDSecureMandatory' => "1",
                    'merchantThreeDSecureMandatory' => "1",
                    'result' => "1",
                );
            }


            if ($check_ipara->result == '0') {
                $record['result_code'] = 'REST-' . $check_ipara->errorCode;
                $record['result_message'] = 'WebServis Hatası ' . $check_ipara->errorMessage;
                $record['result'] = false;
                return $record;
            }
            if ($check_ipara->supportsInstallment != '1' AND $installment > 0) {
                $record['result_code'] = 'REST-3D-1';
                $record['result_message'] = 'Kartınız taksitli alışverişi desteklemiyor. Lütfen tek çekim olarak deneyiniz';
                $record['result'] = false;
                return $record;
            }

            $td_mode = true;

            if ($check_ipara->cardThreeDSecureMandatory == '0'
                AND $check_ipara->merchantThreeDSecureMandatory == '0')
                $td_mode = false;

            if ($ipara_3d_mode == 'on')
                $td_mode = true;
            if ($ipara_3d_mode == 'off')
                $td_mode = false;



            if ($td_mode) {
                try {
                    $record['result_code'] = '3D-R';
                    $record['result_message'] = '3D yönlendimesi yapıldı. Dönüş bekleniyor';
                    $record['result'] = false;
                    return $obj->payThreeD();
                } catch (Exception $e) {
                    $record['result_code'] = 'IPARA-LIB-ERROR';
                    $record['result_message'] = $e->getMessage();
                    $record['result'] = false;
                    return $record;
                }
            }

            $response = $obj->pay();
            $record['result_code'] = $response['error_code'];
            $record['id_ipara'] = $response['order_id'];
            $record['result_message'] = $response['error_message'];
            $record['result'] = (string) $response['result'] == "1" ? true : false;
            $record['amount_paid'] = $amount;

        }

        public function capture($params=[])
        {
            $response       = $this->post2iPara($params);

            if($response && !is_array($response))
            {
                $response = str_replace('id="three_d_form"/>','id="three_d_form">',$response);

                return [
                    'status'        => "output",
                    'output'        => $response,
                ];
            }

            return [
                'status'    => "error",
                'message'   => $response['result_code'] . ':' . $response['result_message'],
            ];
        }

        private function getiParaOptions($cc)
        {
            $publicKey      = $this->config['settings']['public_key'];
            $privateKey     = $this->config['settings']['private_key'];
            $binNumber      = substr($cc, 0, 6);
            $transactionDate = date("Y-m-d H:i:s");
            $token = $publicKey . ":" . base64_encode(sha1($privateKey . $binNumber . $transactionDate, true));
            $data = array("binNumber" => $binNumber);
            $data_string = json_encode($data);

            $ch = curl_init('https://api.ipara.com/rest/payment/bin/lookup');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length:' . strlen($data_string),
                'token:' . $token,
                'transactionDate:' . $transactionDate,
                'version:' . '1.0',
            ));

            $response = curl_exec($ch);
            return json_decode($response);
        }

        public function callback()
        {

            Modules::save_log("Payment",__CLASS__,"callback",false,$_REQUEST,"Callback Information");

            $order_id       = (int) Filter::init("GET/order_id","numbers");

            $checkout       = $this->get_checkout($order_id);


            if(!$checkout)
            {
                $this->error = "Checkout not found";
                return false;
            }


            $record                 = $this->validate3d($checkout);
            $errormessage_ipara     = 'Hata: ('.$record['result_code'].') '.$record['result_message'];


            if($record['result'])
                return  [
                    'status' => "successful",
                    'message' => [
                        'Transaction ID' => $record['orderId'],
                    ],
                ];

            return [
                'status'        => "error",
                'message'       => $errormessage_ipara
            ];
        }

        public function validate3d($params)
        {

            if(!class_exists('iParaPayment')) require_once(__DIR__ . DS . 'ipara_payment.php');

            $public_key     = $this->config['settings']['public_key'];
            $private_key    = $this->config['settings']['private_key'];

            $error_message = false;

            $response = $_POST;

            $record = [
                'id_cart'           => $this->checkout_id,
                'id_customer'       => $params['data']['user_data']['id'],
                'amount'            => $response['amount'] / 100,
                'amount_paid'       => 0,
                'id_ipara'          => $response['orderId'],
                'result_code'       => $response['errorCode'],
                'result_message'    => $response['errorMessage'],
                'result'            => false,
            ];

            $record['result_code']          = $_POST['errorCode'];
            $record['result_message']       = $_POST['errorMessage'];
            $record['id_ipara']             = $_POST['orderId'];
            $record['result']               = false;

            $hash_text = $response['orderId']
                . $response['result']
                . $response['amount']
                . $response['mode']
                . $response['errorCode']
                . $response['errorMessage']
                . $response['transactionDate']
                . $response['publicKey']
                . $private_key;
            $hash = base64_encode(sha1($hash_text, true));

            if ($hash != $response['hash']) { // has yanlışsa
                $record['result_message'] = "Hash uyumlu değil";
                return $record;
            }

            if ($response['result'] == 1)
            {
                $amount             = $response['amount'];
                $orderid            = $response['orderId'];
                $ipara_products     = array();  // aşağıda düzenlenecek;
                $ipara_address      = array();  //aşağıda düzenlenecek
                $ipara_purchaser    = array();  // aşağıda düzenlenecek

                // Müşteri
                $ipara_purchaser['name']                    = $params['data']['user_data']['name'];
                $ipara_purchaser['surname']                 = $params['data']['user_data']['surname'];
                $ipara_purchaser['email']                   = $params['data']['user_data']['email'];
                $ipara_purchaser['birthdate']               = NULL;
                $ipara_purchaser['gsm_number']              = NULL;
                $ipara_purchaser['tc_certificate_number']   = NULL;

                $ipara_address['name']                      = $params['data']['user_data']['name'];
                $ipara_address['surname']                   = $params['data']['user_data']['surname'];
                $ipara_address['address']                   = $params['data']['user_data']['address']['address'];
                $ipara_address['zipcode']                   = $params['data']['user_data']['address']['zipcode'];
                $ipara_address['city_code']                 = 34;
                $ipara_address['city_text']                 = $params['data']['user_data']['address']['city'];
                $ipara_address['country_code']              = $params['data']['user_data']['address']['country_code'];
                $ipara_address['country_text']              = $params['data']['user_data']['address']['country_name'];
                $ipara_address['phone_number']              = $params['data']['user_data']['phone'];
                $ipara_address['tax_number']                = NULL;
                $ipara_address['tax_office']                = NULL;
                $ipara_address['tc_certificate_number']     = NULL;
                $ipara_address['company_name']              = $params['data']['user_data']['company_name'];

                // ÜRÜNLER

                $ipara_products[0]['title']                 = $params['items'][0]['name'];
                $ipara_products[0]['code']                  = $params['id'];
                $ipara_products[0]['quantity']              = 1;
                $ipara_products[0]['price']                 = $amount;

                $obj = new iParaPayment();
                $obj->public_key = $public_key;
                $obj->private_key = $private_key;
                $obj->mode = "P";
                $obj->three_d_secure_code = $response['threeDSecureCode'];
                $obj->order_id = $response['orderId'];
                $obj->amount = $response['amount'] / 100;
                $obj->echo = "EticSoft";
                $obj->vendor_id = 4;
                $obj->products = $ipara_products;
                $obj->shipping_address = $ipara_address;
                $obj->invoice_address = $ipara_address;
                $obj->purchaser = $ipara_purchaser;

                try {
                    $xml_response = $obj->pay();
                } catch (Exception $e) {
                    $record['result_message'].= "Post error after 3DS";
                    $record['result_code'] = "8888";
                    return $record;
                }

                $record['result'] = (string)$xml_response['result'] == '1' ? true : false;
                $record['result_message'] = (string)$xml_response['error_message'];
                $record['result_code'] = (string)$xml_response['error_code'];
            }
            return $record;
        }

    }