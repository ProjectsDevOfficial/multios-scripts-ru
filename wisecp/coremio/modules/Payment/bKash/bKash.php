<?php
    class bKash extends PaymentGatewayModule
    {
        private $base_url,
            $token_url,
            $payment_url,
            $query_url,
            $app_key,
            $app_secret,
            $username,
            $password,
            $sandbox;


        function __construct()
        {
            $this->name             = __CLASS__;
            parent::__construct();

            $this->sandbox          = $this->config["settings"]["sandbox"] ?? false;

            if($this->sandbox)
                $this->base_url         = "https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout";
            else
                $this->base_url         = "https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout";

            $this->token_url        = $this->base_url . "/token/grant";
            $this->payment_url      = $this->base_url . "/create";
            $this->query_url        = $this->base_url . "/execute";
            $this->app_key          = $this->config["settings"]["appKey"] ?? 'na';
            $this->app_secret       = $this->config["settings"]["appSecret"] ?? 'na';
            $this->username         = $this->config["settings"]["username"] ?? 'na';
            $this->password         = $this->config["settings"]["password"] ?? 'na';

        }

        public function config_fields()
        {
            return [
                'username'          => [
                    'name'              => "Username",
                    'description'       => "Enter your bKash merchant username",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["username"] ?? '',
                ],
                'password'          => [
                    'name'              => "Password",
                    'description'       => "Enter your bKash merchant password",
                    'type'              => "password",
                    'value'             => $this->config["settings"]["password"] ?? '',
                ],
                'appKey'          => [
                    'name'              => "App Key",
                    'description'       => "Enter the bKash app key",
                    'type'              => "password",
                    'value'             => $this->config["settings"]["appKey"] ?? '',
                ],
                'appSecret'          => [
                    'name'              => "App Secret",
                    'description'       => "Enter the bKash app secret",
                    'type'              => "password",
                    'value'             => $this->config["settings"]["appSecret"] ?? '',
                ],
                'fee'          => [
                    'name'              => "Fee",
                    'description'       => "Gateway fee if you want to add",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["fee"] ?? '',
                ],
                'sandbox'          => [
                    'name'              => "Sandbox",
                    'description'       => "Tick to enable sandbox mode",
                    'type'              => "approval",
                    'value'             => 1,
                    'checked'           => (boolean) (int) ($this->config["settings"]["sandbox"] ?? 0),
                ],
            ];
        }

        public function area($params=[])
        {
            try {
                $token = $this->get_access_token();
                if(!$token) throw new Exception($this->error);

                // Create a payment request
                $headers = [
                    "Content-Type: application/json",
                    "Authorization:".$token,
                    "X-APP-Key:".$this->app_key
                ];

                $data = [
                    'mode'                  => '0011',
                    "intent"                => "sale",
                    "amount"                => $params['amount'] ?? 0,
                    "currency"              => $this->currency($params['currency'] ?? 'USD'),
                    "merchantInvoiceNumber" => $this->checkout_id,
                    "payerReference"        => $this->clientInfo->phone ?: rand(100000000,999999999),
                    "callbackURL"           => $this->links["callback"],
                ];

                Session::set("bKash_checkout_id",$this->checkout_id);

                $ch = curl_init($this->payment_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

                $response   = curl_exec($ch);
                $error      = curl_errno($ch) ? curl_error($ch) : null;
                curl_close($ch);

                if($error) throw new Exception('Error: '.$error);
                else
                {
                    $result = json_decode($response, true);
                    if ($result['statusCode'] == '0000')
                    {
                        // Payment was successful
                        $link = $result['bkashURL'];
                        header("Location: " . $link);
                        return 'You are being redirected, please wait...<br>If you\'re having trouble redirecting, <a href="'.$link.'">click here.</a>';
                    }
                    else
                        throw new Exception("Error: " . $result['statusMessage']);
                }
            }
            catch (Exception $e)
            {
                return $e->getMessage();
            }
        }

        public function callback()
        {
            try {
                $paymentID  = Filter::init("GET/paymentID","hclear");
                $status     = Filter::init("GET/status"); # failure

                if(!$paymentID) throw new Exception("Payment ID not found");
                $token      = $this->get_access_token();
                if(!$token) throw new Exception($this->error);

                $headers = [
                    "Content-Type:application/json",
                    "Authorization:".$token,
                    "X-APP-Key:".$this->app_key
                ];

                $query_data = [
                    "paymentID" => $paymentID
                ];

                $ch = curl_init($this->query_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($query_data));
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");

                $response   = curl_exec($ch);
                $error      = curl_errno($ch) ? curl_error($ch) : null;
                curl_close($ch);
                Modules::save_log("Payment",__CLASS__,"callback-execute",$query_data,$response,$error);

                if ($error) throw new Exception($error);
                else
                {
                    $result         = json_decode($response, true);

                    if ($result['statusCode'] == '0000' && $result['transactionStatus'] == 'Completed')
                    {
                        $checkout_id    = $result["merchantInvoiceNumber"] ?? false;

                        $checkout       = $this->get_checkout($checkout_id);

                        if(!$checkout) throw new Exception('Checkout ID unknown');

                        $this->set_checkout($checkout);


                        return [
                            'status' => 'successful',
                            'message'        => [
                                'Transaction ID' => $result['trxID'],
                            ],
                        ];
                    }
                    else
                        throw new Exception($result['statusMessage']);
                }

            }
            catch(Exception $e)
            {
                $checkout_id = Session::get("bKash_checkout_id");
                $checkout       = $this->get_checkout($checkout_id);
                if($checkout) $this->set_checkout($checkout);

                $this->error = $e->getMessage();
                Modules::save_log("Payment",__CLASS__,"callback",[],$_POST,$this->error);
                return false;
            }
        }

        private function get_access_token()
        {
            $request_data = [
                "app_key" => $this->app_key,
                "app_secret" => $this->app_secret
            ];
            $header = [
                "Content-Type: application/json",
                "username:".$this->username,
                "password:".$this->password,
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->token_url);
            curl_setopt($ch,CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch,CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));

            $result     = curl_exec($ch);
            $error      = curl_errno($ch) ? curl_error($ch) : null;
            curl_close($ch);

            Modules::save_log("Payment",__CLASS__,"access_token",$request_data,$result,$error);

            if ($error)
            {
                $this->error = 'cURL Error: ' . $error;
                $result = NULL;
            }
            else
            {
                $response   = json_decode($result, true);
                $message    = $response["message"] ?? ($response["statusMessage"] ?? "");

                $result = $response['id_token'] ?? false;
                if(!$result)
                {
                    $this->error = $message ?: 'Access token not found';
                    $result = NULL;
                }
            }

            return $result;
        }

    }