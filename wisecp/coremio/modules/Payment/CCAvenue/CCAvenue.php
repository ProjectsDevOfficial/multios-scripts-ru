<?php
    use phpseclib3\Crypt\Rijndael;
    class CCAvenueSDK
    {
        protected $key = NULL;
        protected $crypt = NULL;
        public static function factory($key)
        {
            $self = new self();
            $self->key = hex2bin(md5($key));
            $crypt = new \phpseclib3\Crypt\Rijndael('cbc');
            $crypt->setIV(pack("C*", 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15));
            $crypt->setKey($self->key);
            $self->crypt = $crypt;
            return $self;
        }
        public function encrypt($plainText)
        {
            return bin2hex($this->crypt->encrypt($plainText));
        }
        public function decrypt($encryptedText)
        {
            return $this->crypt->decrypt(hex2bin($encryptedText));
        }
    }
    class CCAvenue {
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
            $token  = md5(Crypt::encode("CCAvenue-Auth-Token=".$syskey,$syskey));
            return $token;
        }

        public function set_checkout($checkout){
            $this->checkout_id = $checkout["id"];
            $this->checkout    = $checkout;
        }

        public function commission_fee_calculator($amount){
            $rate = $this->get_commission_rate();
            if(!$rate) return 0;
            $calculate = Money::get_discount_amount($amount,$rate);
            return $calculate;
        }


        public function get_commission_rate(){
            return $this->config["settings"]["commission_rate"];
        }

        public function cid_convert_code($id=0){
            Helper::Load("Money");
            $currency   = Money::Currency($id);
            if($currency) return $currency["code"];
            return false;
        }

        public function get_ip(){
            return UserManager::GetIP();
        }

        public function cdec($num)
        {
            for ($n = 0; $n < strlen($num); $n++)
            {
                $temp = $num[$n];
                $dec = $dec + $temp * pow(2, strlen($num) - $n - 1);
            }
            return $dec;
        }
        public function leftshift($str, $num)
        {
            $str = DecBin($str);
            for ($i = 0; $i < 64 - strlen($str); $i++) {
                $str = "0" . $str;
            }
            for ($i = 0; $i < $num; $i++) {
                $str = $str . "0";
                $str = substr($str, 1);
            }
            return $this->cdec($str);
        }
        public function adler32($adler, $str)
        {
            $BASE = 65521;
            $s1 = $adler & 65535;
            $s2 = $adler >> 16 & 65535;
            for ($i = 0; $i < strlen($str); $i++) {
                $s1 = ($s1 + Ord($str[$i])) % $BASE;
                $s2 = ($s2 + $s1) % $BASE;
            }
            return $this->leftshift($s2, 16) + $s1;
        }
        public function getchecksum($MerchantId, $Amount, $OrderId, $URL, $WorkingKey)
        {
            $str = (string) $MerchantId . "|" . $OrderId . "|" . $Amount . "|" . $URL . "|" . $WorkingKey;
            $adler = 1;
            $adler = $this->adler32($adler, $str);
            return $adler;
        }

        public function verifychecksum($MerchantId, $OrderId, $Amount, $AuthDesc, $CheckSum, $WorkingKey)
        {
            $str = (string) $MerchantId . "|" . $OrderId . "|" . $Amount . "|" . $AuthDesc . "|" . $WorkingKey;
            $adler = 1;
            $adler = $this->adler32($adler, $str);
            if ($adler == $CheckSum) {
                return "true";
            }
            return "false";
        }

        public function payment_link_v1()
        {
            $Merchant_Id        = $this->config["settings"]["merchantid"];
            $WorkingKey         = $this->config["settings"]["workingkey"];
            $Amount             = sprintf("%.2f",$this->checkout["total"]);
            $Order_Id           = $this->checkout_id . "_" . DateManager::Now("YmdHis");
            $Redirect_Url       = Controllers::$init->CRLink("payment",['CCAvenue',$this->get_auth_token(),'callback']);
            $Checksum           = $this->getCheckSum($Merchant_Id, $Amount, $Order_Id, $Redirect_Url, $WorkingKey);

            $strRet = "<form name=ccavenue method=\"post\" action=\"https://www.ccavenue.com/shopzone/cc_details.jsp\">";
            $strRet .= "<input type=hidden name=Merchant_Id value=\"" . $Merchant_Id . "\">";
            $strRet .= "<input type=hidden name=Amount value=\"" . $Amount . "\">";
            $strRet .= "<input type=hidden name=Order_Id value=\"" . $Order_Id . "\">";
            $strRet .= "<input type=hidden name=Redirect_Url value=\"" . $Redirect_Url . "\">";
            $strRet .= "<input type=hidden name=Checksum value=\"" . $Checksum . "\">";
            $strRet .= "<input type=\"hidden\" name=\"billing_cust_name\" value=\"" . $this->checkout["data"]["user_data"]["name"] . " " . $this->checkout["data"]["user_data"]["surname"] . "\">";
            $strRet .= "<input type=\"hidden\" name=\"billing_cust_address\" value=\"" . $this->checkout["data"]["user_data"]["address"]["address"] . "\">";
            $strRet .= "<input type=\"hidden\" name=\"billing_cust_country\" value=\"" . $this->checkout["data"]["user_data"]["address"]["country_name"] . "\">";
            $strRet .= "<input type=\"hidden\" name=\"billing_cust_tel\" value=\"" . $this->checkout["data"]["user_data"]["phone"] . "\">";
            $strRet .= "<input type=\"hidden\" name=\"billing_cust_email\" value=\"" . $this->checkout["data"]["user_data"]["email"] . "\">";
            $strRet .= "<input type=\"hidden\" name=\"delivery_cust_name\" value=\"" . $this->checkout["data"]["user_data"]["name"] . " " . $this->checkout["data"]["user_data"]["surname"] . "\">";
            $strRet .= "<input type=\"hidden\" name=\"delivery_cust_address\" value=\"" . $this->checkout["data"]["user_data"]["address"]["address"] . "\">";
            $strRet .= "<input type=\"hidden\" name=\"delivery_cust_tel\" value=\"" . $this->checkout["data"]["user_data"]["phone"] . "\">";
            $strRet .= "<input type=\"hidden\" name=\"delivery_cust_notes\" value=\"Checkout ID #" . $Order_Id . "\">";
            $strRet .= "<input type=\"submit\" value=\"" . $this->lang["pay-now"] . "\">";
            $strRet .= "</form>";
            $strRet .= "<br />" . $this->config["settings"]["infomsg"];
            return $strRet;

        }


        public function payment_link()
        {
            $url = "https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction";

            if(isset($this->checkout["data"]["user_data"]["phone"]))
                $phone = $this->checkout["data"]["user_data"]["phone"];
            else
            {
                $phone      = $this->checkout["data"]["user_data"]["gsm_cc"];
                if($phone && $this->checkout["data"]["user_data"]["gsm"])
                    $phone .= $this->checkout["data"]["user_data"]["gsm"];
                else
                    $phone = '';
            }

            $values = [
                'merchant_id'       => $this->config["settings"]["merchantid"] ?? '',
                'sub_account_id'    => $this->config["settings"]["sub_account_id"] ?? 'Evil_70',
                'order_id'          => $this->checkout_id,
                'currency'          => $this->cid_convert_code($this->checkout["data"]["currency"]),
                'amount'            => round($this->checkout["data"]["total"],2),
                'redirect_url'      => Controllers::$init->CRLink("payment",['CCAvenue',$this->get_auth_token(),'callback']),
                'cancel_url'        => $this->checkout["data"]["redirect"]["failed"] ?? APP_URI,
                'language'          => 'EN',
                'billing_name'      => $this->checkout["data"]["user_data"]["full_name"],
                'billing_address'   => $this->checkout["data"]["user_data"]["address"]["address"],
                'billing_city'      => $this->checkout["data"]["user_data"]["address"]["city"],
                'billing_state'     => $this->checkout["data"]["user_data"]["address"]["counti"],
                'billing_zip'       => $this->checkout["data"]["user_data"]["address"]["zipcode"],
                'billing_country'   => $this->checkout["data"]["user_data"]["address"]["country_name"],
                'billing_tel'       => $phone,
                'billing_email'     => $this->checkout["data"]["user_data"]["email"],
            ];
            $data = "";
            foreach ($values as $key => $value) $data .= $key . "=" . $value . "&";

            try {
                $encryptedData = CCAvenueSDK::factory($this->config["settings"]["workingkey"])->encrypt($data);
                $payNow = $this->lang["pay-now"];
                return "<form action=\"" . $url . "\" id=\"ccAvenuePaymentForm\" method=\"POST\">\n    <input type=\"hidden\" name=\"encRequest\" value=\"" . $encryptedData . "\" />\n    <input type=\"hidden\" name=\"access_code\" value=\"" . $this->config["settings"]["accesscode"] . "\" />\n    <input type=\"submit\" value=\"" . $payNow . "\">\n</form>";
            } catch (Exception $e) {
                Modules::save_log("Payment",'CCAvenue','redirect',$values,$e->getMessage());
                return "<div class=\"red-info\">An Error Occurred - Please Contact Support</div>";
            }
        }


        public function payment_result_v1(){

            $error          = false;

            $Order_Id           = $_POST["Order_Id"];
            $WorkingKey         = $this->config["settings"]["workingkey"];
            $Amount             = $_POST["Amount"];
            $AuthDesc           = $_POST["AuthDesc"];
            $Checksum           = $_POST["Checksum"];
            $Merchant_Id        = $_POST["Merchant_Id"];
            $Checksum           = $this->verifyChecksum($Merchant_Id, $Order_Id, $Amount, $AuthDesc, $Checksum, $WorkingKey);
            $invoiceid          = explode("_", $Order_Id);
            $invoiceid          = $invoiceid[0];

            $checkout           = Basket::get_checkout($invoiceid);

            if(!$checkout) $error = 'Checkout not found';


            if ($Checksum == "true" && $AuthDesc == "Y")
                $transactionStatus = "Successful";
            else
                $transactionStatus = "Error";

            if($transactionStatus == "Error")
                $error = "Transcation status failed";

            if($error)
                return [
                    'checkout' => $checkout,
                    'status' => "ERROR",
                    'status_msg' => $error,
                ];

            $this->set_checkout($checkout);

            Basket::set_checkout($this->checkout_id,['status' => "paid"]);

            return [
                'status' => "SUCCESS",
                'checkout'    => $checkout,
                'status_msg' => '',
            ];
        }

        public function payment_result()
        {
            $passedInvoiceId    = (int) Filter::init("REQUEST/orderNo");
            $encodedResponse    = Filter::init("REQUEST/encResp");


            try {
                $decryptedResponse = CCAvenueSDK::factory($this->config["settings"]["workingkey"])->decrypt($encodedResponse);
                $returnedVariables = array();
                parse_str($decryptedResponse, $returnedVariables);
                $currency = $returnedVariables["currency"];
                $transactionId = $returnedVariables["tracking_id"];
                $amount = $returnedVariables["amount"];
                $orderStatus = $returnedVariables["order_status"];
                $invoiceId = $returnedVariables["order_id"];
                if ($invoiceId != $passedInvoiceId) exit("Invalid Access Attempt");
            } catch (Exception $e) {
                $orderStatus = "invalid";
                $returnedVariables = ['status' => 'error','message' => $e->getMessage()];
                $invoiceId = 0;
            }

            $checkout       = Basket::get_checkout($invoiceId);

            if(!$checkout)
            {
                return [
                    'status'        => 'ERROR',
                    'status_msg'    => 'Checkout data not found',
                ];
            }

            if($orderStatus != 'Success')
            {
                Modules::save_log("Payment",__CLASS__,"callback",'',$returnedVariables,$orderStatus);
                return [
                    'checkout'      => $checkout,
                    'status'        => 'ERROR',
                    'status_msg'    => 'Payment failed',
                ];
            }


            $this->set_checkout($checkout);

            Basket::set_checkout($this->checkout_id,['status' => "paid"]);

            return [
                'status' => "SUCCESS",
                'checkout'    => $checkout,
                'status_msg' => '',
            ];
        }

    }
