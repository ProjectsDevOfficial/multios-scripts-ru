<?php
    use Stripe\Util\Util as Util;

    class Stripe {
        public $checkout_id,$checkout;
        public $name,$commission=true;
        public $config=[],$lang=[],$page_type = "in-page",$callback_type="client-sided";
        public $payform=false;

        function __construct(){
            $this->config     = Modules::Config("Payment",__CLASS__);
            $this->lang       = Modules::Lang("Payment",__CLASS__);
            $this->name       = __CLASS__;
            $this->payform   = __DIR__.DS."pages".DS."payform";
        }

        public function get_auth_token(){
            $syskey = Config::get("crypt/system");
            $token  = md5(Crypt::encode("Stripe-Auth-Token=".$syskey,$syskey));
            return $token;
        }

        public function set_checkout($checkout){
            $this->checkout_id = $checkout["id"];
            $this->checkout    = $checkout;
        }

        public function commission_fee_calculator($amount){
            $rate = $this->config["settings"]["commission_rate"];
            $calculate = Money::get_discount_amount($amount,$rate);
            return $calculate;
        }


        public function get_commission_rate(){
            return $this->config["settings"]["commission_rate"];
        }

        public function cid_convert_code($id=0){
            Helper::Load("Money");
            $currency   = Money::Currency($id);
            if($currency) return strtolower($currency["code"]);
            return false;
        }

        public function get_ip(){
            if( isset( $_SERVER["HTTP_CLIENT_IP"] ) ) {
                $ip = $_SERVER["HTTP_CLIENT_IP"];
            } elseif( isset( $_SERVER["HTTP_X_FORWARDED_FOR"] ) ) {
                $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
            } else {
                $ip = $_SERVER["REMOTE_ADDR"];
            }
            return $ip;
        }

        public function checkout_info($links=[]){

            if(!defined("included_stripe")){
                define("included_stripe",true);
                include __DIR__.DS."init.php";
            }

            $publishable_key    = $this->config['settings']["live_publishable_key"];
            $secret_key         = $this->config['settings']["live_secret_key"];


            $ok_link                = $links["successful-page"];
            $fail_link              = $links["failed-page"];
            $callback_link          = $links["callback"];

            $checkout_items         = $this->checkout["items"];
            $checkout_data          = $this->checkout["data"];
            $user_data              = $checkout_data["user_data"];

            $email                  = $user_data["email"];
            $user_name              = $user_data["full_name"];
            if($user_data["company_name"]) $user_name .= " ".$user_data["company_name"];
            $payable_total          = number_format($checkout_data["total"], 2, '.', '');
            $payable_total          = $payable_total * 100;
            $currency               = $this->cid_convert_code($checkout_data["currency"]);
            $phone                  = NULL;
            $address_line           = NULL;
            $address_city           = NULL;
            $address_state          = NULL;
            $address_country        = NULL;
            $address_p_code         = NULL;
            $description            = "Checkout ID: ".$this->checkout_id;

            if($currency == "xaf") $payable_total = ($payable_total / 100);
            if($currency == "xof") $payable_total = ($payable_total / 100);

            /*
            if($this->checkout["type"] == "bill" || $this->checkout["type"] == "invoice-bulk-payment")
                $description = "Invoice Payment";
            */

            if(isset($user_data["address"]["address"])) $address_line = $user_data["address"]["address"];
            if(isset($user_data["phone"]) && $user_data["phone"]) $phone = "+".$user_data["phone"];
            if(isset($user_data["address"]["country_code"])) $address_country = $user_data["address"]["country_code"];
            if(isset($user_data["address"]["city"])) $address_city = $user_data["address"]["city"];
            if(isset($user_data["address"]["counti"])) $address_state = $user_data["address"]["counti"];
            if(isset($user_data["address"]["zipcode"])) $address_p_code = $user_data["address"]["zipcode"];



            \Stripe\Stripe::setApiKey($secret_key);

            $data = [
                "description"          => $description,
                "amount"               => $payable_total,
                "currency"             => $currency,
                "payment_method_types" => ["card"],
                "receipt_email"        => $email,
                "metadata"             => [
                    "order_id"         => $this->checkout["id"],
                ],
                "shipping"             => [
                    "name"             => $user_name,
                    "phone"            => $phone,
                    "address"          => [
                        "line1"        => $address_line,
                        "state"        => $address_state,
                        "city"         => $address_city,
                        "country"      => $address_country,
                        "postal_code"  => $address_p_code,
                    ],
                ],
            ];

            try {
                $intent = \Stripe\PaymentIntent::create($data);
            }catch (Exception $e){
                echo $e->getMessage();
                return false;
            }


            return [
                'key'           => $publishable_key,
                'intent'        => $intent,
                'payable_total' => $payable_total,
                'currency'      => $currency,
            ];
        }

        public function payment_result(){

            if(!defined("included_stripe")){
                define("included_stripe",true);
                include __DIR__.DS."init.php";
            }

            $endpoint_secret = $this->config["settings"]["endpoint_secret"];

            $payload        = @file_get_contents('php://input');
            $sig_header     = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : false;
            $event          = null;

            try{
                $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
            } catch(\UnexpectedValueException $e){
                return [
                    'status' => "ERROR",
                    'status_msg' => "Failed Authorization",
                    'return_msg' => "Failed Authorization",
                ];
            } catch(\Stripe\Error\SignatureVerification $e) {
                return [
                    'status' => "ERROR",
                    'status_msg' => "Invalid Signature",
                    'return_msg' => "Invalid Signature",
                ];
            }

             if($event->type == "payment_intent.payment_failed"){
                $intent = $event->data->object;
                $error_message = $intent->last_payment_error ? $intent->last_payment_error->message : "";

                return [
                    'status' => "ERROR",
                    'status_msg' => "Failed: ".$intent->id.", ".$error_message,
                    'return_msg' => "Failed: ".$intent->id.", ".$error_message,
                ];
            }

            if($event->type != "payment_intent.succeeded"){
                return [
                    'status' => "ERROR",
                    'status_msg' => "Payment Failed",
                    'return_msg' => "Payment Failed",
                ];
            }

            $intent         = $event->data->object;

            $o_id           = isset($intent->metadata->order_id) ? $intent->metadata->order_id : 0;

            $return_msg     =  "Succeeded: ".$intent->id;


            if(!$o_id)
                return [
                    'status'        => "ERROR",
                    'status_msg'    => "Not found order_id",
                    'return_msg'    => $return_msg,
                ];


            $merchant_oid       = (int) Filter::numbers($o_id);
            $checkout           = Basket::get_checkout($merchant_oid);
            if(!$checkout)
                return [
                    'status' => "ERROR",
                    'status_msg' => "Invalid Transaction ID",
                    'return_msg' => $return_msg,
                ];

            $this->set_checkout($checkout);

            $checkout_items         = $checkout["items"];
            $checkout_data          = $checkout["data"];
            $user_data              = $checkout_data["user_data"];

            $email                  = $user_data["email"];
            $payable_total          = number_format($checkout_data["total"], 2, '.', '');
            $payable_total          = $payable_total * 100;
            $currency               = $this->cid_convert_code($checkout_data["currency"]);

            Basket::set_checkout($checkout["id"],['status' => "paid"]);

            return [
                'status' => "SUCCESS",
                'checkout'    => $checkout,
                'return_msg' => $return_msg,
            ];

        }
    }