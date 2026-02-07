<?php
    class PayTR_BankTransfer {
        public $name,$commission=true;
        public $config=[],$lang=[],$page_type = "in-page",$callback_type="server-sided";
        public $payform=false;
        public $checkout_id=0,$checkout;

        function __construct(){
            $this->config     = Modules::Config("Payment",__CLASS__);
            $this->lang       = Modules::Lang("Payment",__CLASS__);
            $this->name       = __CLASS__;
            $this->payform    = __DIR__.DS."pages".DS."payform";
        }

        public function get_auth_token(){
            $syskey = Config::get("crypt/system");
            $token  = md5(Crypt::encode("PayTR-Bank-Transfer-Auth-Token=".$syskey,$syskey));
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

        private function cid_convert_code($id=0){
            Helper::Load("Money");
            $currency   = Money::Currency($id);
            if($currency) return $currency["code"];
            return false;
        }

        public function get_iframe($ok_url,$fail_url){
            if(!$this->checkout) $this->checkout = Basket::get_checkout($this->checkout_id);
            if(!$this->checkout){
                echo "Checkout Data Not Found";
                return false;
            }

            $checkout_items         = $this->checkout["items"];
            $checkout_data          = $this->checkout["data"];
            $user_data              = $checkout_data["user_data"];

            $merchant_id            = $this->config["settings"]["merchant_id"];
            $merchant_salt          = $this->config["settings"]["merchant_salt"];
            $merchant_key           = $this->config["settings"]["merchant_key"];
            $test_mode              = $this->config["settings"]["test_mode"];
            $debug_on               = $this->config["settings"]["debug_on"];
            $user_ip                = $this->get_ip();
            $merchant_oid           = $this->checkout_id;
            $email                  = $user_data["email"];
            $payment_amount         = number_format($checkout_data["total"], 2, '.', '');
            $payment_amount         = $payment_amount * 100;
            $merchant_ok_url        = $ok_url;
            $merchant_fail_url      = $fail_url;
            $payment_type           = "eft";


            $hash_str               = $merchant_id.$user_ip.$merchant_oid.$email.$payment_amount.$payment_type.$test_mode;
            $paytr_token            = base64_encode(hash_hmac('sha256',$hash_str.$merchant_salt,$merchant_key,true));

            $post_vals=array(
                'merchant_id'=>$merchant_id,
                'user_ip'=>$user_ip,
                'merchant_oid'=>$merchant_oid,
                'email'=>$email,
                'payment_amount'=>$payment_amount,
                'payment_type' => $payment_type,
                'paytr_token'=>$paytr_token,
                'debug_on'=>$debug_on,
                'merchant_ok_url'=>$merchant_ok_url,
                'merchant_fail_url'=>$merchant_fail_url,
                'timeout_limit'=>30,
                'test_mode' =>$test_mode,
            );

            $error      = '';

            $ch=curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://www.paytr.com/odeme/api/get-token");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1) ;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vals);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $result     = @curl_exec($ch);

            if(curl_errno($ch)) $error = "PAYTR IFRAME connection error. err:".curl_error($ch);
            else{
                $result=json_decode($result,1);

                if($result['status']!='success') $error = "PAYTR IFRAME failed. reason:".$result['reason'];
                else{
                    $token=$result['token'];
                    ?>
                    <iframe src="https://www.paytr.com/odeme/api/<?php echo $token;?>" onload="iFrameResize({})" id="paytriframe" frameborder="0" scrolling="no" style="width: 100%;"></iframe>
                    <script type="text/javascript" src="https://www.paytr.com/js/iframeResizer.min.js"></script>
                    <script type="text/javascript">
                        setTimeout(function () {
                            iFrameResize({},'#paytriframe');
                        },0);
                    </script>
                    <?php
                }
                echo isset($error) && $error != '' ? $error : false;
            }
            curl_close($ch);
        }

        public function payment_result(){
            $merchant_key 	= $this->config["settings"]["merchant_key"];
            $merchant_salt	= $this->config["settings"]["merchant_salt"];
            $amount_paid    = Filter::POST("total_amount");
            $status         = Filter::POST("status");
            $merchant_oid   = Filter::POST("merchant_oid");
            $failed_rcode   = (int) Filter::POST("failed_reason_code");
            $failed_rmsg    = Filter::POST("failed_reason_msg");
            $post_hash      = Filter::POST("hash");
            $hash = base64_encode( hash_hmac('sha256', $merchant_oid.$merchant_salt.$status.$amount_paid,$merchant_key,true));
            if($hash != $post_hash)
                return [
                    'status' => "ERROR",
                    'status_msg' => "PAYTR notification failed: bad hash",
                    'return_msg' => "OK",
                ];

            $checkout_id        = (int) Filter::POST("merchant_oid");
            $checkout           = Basket::get_checkout($checkout_id);

            if(!$checkout)
                return [
                    'status' => "ERROR",
                    'status_msg' => Bootstrap::$lang->get("errors/error6",Config::get("general/local")),
                    'return_msg' => "OK",
                ];

            if($failed_rcode == 6)
                return [
                    'status' => "ERROR",
                    'status_msg' => $failed_rmsg,
                    'return_msg' => "OK",
                ];

            if($failed_rcode == 6)
                return [
                    'status' => "ERROR",
                    'status_msg' => $failed_rmsg,
                    'return_msg' => "OK",
                ];

            if($status != "success")
                return [
                    'status' => "ERROR",
                    'status_msg' => $failed_rmsg,
                    'return_msg' => "OK",
                ];


            $this->set_checkout($checkout);

            Basket::set_checkout($checkout["id"],['status' => "paid"]);

            return [
                'status'         => "SUCCESS",
                'checkout'       => $checkout,
                'return_msg'     => "OK",
            ];

        }


    }