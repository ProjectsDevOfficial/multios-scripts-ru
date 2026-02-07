<?php
    class PayTR extends PaymentGatewayModule {
        public $installment_cards = ['world','axess','maximum','cardfinans','paraf','advantage','combo','bonus'];
        function __construct(){
            $this->card_storage     = true;
            $this->name             = __CLASS__;
            parent::__construct();

            $this->links['generateForm']    = Controllers::$init->CRLink("payment",[__CLASS__,"function","generateForm"]);

            $this->call_function['generateForm'] = function(){
                $chid           = Filter::init("POST/chid","numbers");
                $card_num       = Filter::init("POST/card_num","numbers");
                $card_bin       = substr($card_num,0,6);
                $card_name      = Filter::init("POST/card_name","hclear");
                $card_expiry    = Filter::init("POST/card_expiry","numbers","\/");
                $card_cvc       = Filter::init("POST/card_cvc","numbers");
                $installment    = Filter::init("POST/installment","numbers");
                $save_card      = Filter::init("POST/save_card","numbers");
                $auto_card      = Filter::init("POST/auto_card","numbers");
                $o_u            = Filter::init("POST/ok_url","hclear");
                $f_u            = Filter::init("POST/fail_url","hclear");
                $stored_card    = Filter::init("POST/stored_card","numbers");
                $identification = Filter::init("POST/identification","numbers");

                Helper::Load(["Basket","Money"]);


                if(!$stored_card)
                {
                    if(strlen($card_bin) !== 6)
                    {
                        echo Utility::jencode([
                            'status' => "error",
                            'message' => "Must be 6 characters",
                        ]);
                        return false;
                    }
                    if(strlen($card_name) < 5)
                    {
                        echo Utility::jencode([
                            'status' => "error",
                            'message' => Bootstrap::$lang->get_cm("website/payment/card-tx25"),
                        ]);
                        return false;
                    }
                    if(strlen($card_expiry) < 5)
                    {
                        echo Utility::jencode([
                            'status' => "error",
                            'message' => Bootstrap::$lang->get_cm("website/payment/card-tx26")
                        ]);
                        return false;
                    }

                    $card_expiry_s  = explode("/",$card_expiry);
                    $card_expiry_m  = $card_expiry_s[0];
                    $card_expiry_y  = $card_expiry_s[1];
                    $now_m          = DateManager::Now("m");
                    $now_y          = substr(DateManager::Now("Y"),-2);

                    if($card_expiry_y < $now_y || ($card_expiry_y == $now_y && $card_expiry_m < $now_m))
                    {
                        echo Utility::jencode([
                            'status' => "error",
                            'message' => Bootstrap::$lang->get_cm("website/payment/card-tx26"),
                        ]);
                        return false;
                    }

                    if(strlen($card_cvc) < 3)
                    {
                        echo Utility::jencode([
                            'status' => "error",
                            'message' => Bootstrap::$lang->get_cm("website/payment/card-tx27"),
                        ]);
                        return false;
                    }

                    $bin_result             = $this->bin_check($card_bin);

                    if(!$bin_result && $this->error == "INTERNATIONAL")
                        $bin_result = $this->bin_check_international($card_bin);

                    if(!$bin_result)
                    {
                        echo Utility::jencode([
                            'status' => "error",
                            'message' => $this->error,
                        ]);
                        return false;
                    }
                }

                if($identification)
                {
                    $installment    = 0;
                    $save_card      = 1;
                    $auto_card      = 0;
                    $chid           = $this->generate_card_identification_checkout(__CLASS__);
                    if(!$chid &&  $this->error == "no-address")
                    {
                        echo Utility::jencode([
                            'status' => "error",
                            'message' => "",
                        ]);
                        return false;
                    }
                }

                $checkout   = Basket::get_checkout($chid);

                if(!$checkout)
                {
                    echo Utility::jencode([
                        'status' => "error",
                        'message' => "Invalid checkout ID",
                    ]);
                    return false;
                }

                $checkout_items         = $checkout["items"];
                $checkout_data          = $checkout["data"];
                $user_data              = $checkout_data["user_data"];


                if($stored_card)
                {
                    $bin_result = [];

                    // Get stored card data
                    $g_stored = $this->get_stored_card($stored_card,$user_data["id"]);
                    if(!$g_stored)
                    {
                        echo Utility::jencode([
                            'status' => "error",
                            'message' => "Invalid Stored Card",
                        ]);
                        return false;
                    }

                    $bin_result["country"]          = $g_stored["card_country"];
                    $bin_result["type"]             = $g_stored["card_type"];
                    $bin_result["schema"]           = $g_stored["card_schema"];
                    $bin_result["brand"]            = $g_stored["card_brand"];
                }

                $force_curr     = $this->config["settings"]["force_convert_to"] ?? false;
                $org_curr       = $checkout_data["currency"];

                $new_amount     = $checkout_data["total"];
                $new_curr       = $org_curr;

                if($force_curr && $force_curr != $checkout_data["currency"])
                {
                    $new_amount        = Money::exChange($new_amount,$new_curr,$force_curr);
                    $new_curr           = $force_curr;
                }


                #$non_3d                 = $stored_card && ($g_stored ?? false) ? 1 : 0;
                $non_3d                 = 0;
                $card_country           = $bin_result["country"];

                $ok_url                 = $o_u;
                $fail_url               = $f_u;


                $merchant_id            = $this->config["settings"]["merchant_id"];
                $merchant_salt          = $this->config["settings"]["merchant_salt"];
                $merchant_key           = $this->config["settings"]["merchant_key"];

                $no_installment         = $this->config["settings"]["no_installment"];
                $max_installment        = $this->config["settings"]["max_installment"];
                $test_mode              = $this->config["settings"]["test_mode"];
                $debug_on               = $this->config["settings"]["debug_on"];
                $currency               = $this->cid_convert_code2($new_curr);
                $user_ip                = $this->get_ip();
                $merchant_oid           = $checkout["id"];
                $email                  = $user_data["email"];
                $payment_amount         = round($new_amount, 2);
                $user_basket            = "";
                $user_name              = $user_data["full_name"];
                if($user_data["company_name"]) $user_name .= " ".$user_data["company_name"];
                $user_address           = $user_data["address"]["country_name"];
                $user_address           .= " / ".$user_data["address"]["city"];
                $user_address           .= " / ".$user_data["address"]["counti"];
                $user_address           .= " / ".$user_data["address"]["address"];
                $user_phone             = $user_data["gsm_cc"].$user_data["gsm"];

                if(!$user_phone){
                    $user_info          = User::getInfo($user_data["id"],['gsm_cc','gsm']);
                    $user_phone         = $user_info["gsm_cc"].$user_info["gsm"];
                }

                $merchant_ok_url        = $ok_url;
                $merchant_fail_url      = $fail_url;
                $lang                   = strtolower(Bootstrap::$lang->get("package/code"));
                if($lang !== "tr") $lang = "en";

                if($checkout_items && is_array($checkout_items)){
                    $user_basket = [];
                    foreach($checkout_items AS $item){
                        if((!isset($item["name"]) || !$item["name"]) && isset($item["description"]))
                            $item["name"] = $item["description"];

                        if($force_curr && $force_curr != $org_curr)
                            $item["amount"] = Money::exChange($item["amount"],$org_curr,$force_curr);

                        $user_basket[] = [$item["name"],round($item["amount"],2),$item["quantity"]];
                    }
                    $user_basket = htmlentities(json_encode($user_basket));
                }

                $payment_type           = "card";
                $card_type              = "";
                $installment_count = (int) $installment;
                if($card_country == "TR") $card_type = $bin_result["brand"];

                if($card_type && $installment_count)
                {
                    $rates  = $this->installment_rates($bin_result);
                    if($rates){
                        $t_rate             = $rates[$installment_count];
                        if($t_rate)
                        {
                            $payment_amount = round((($payment_amount * $t_rate) / 100) + $payment_amount,2);
                        }
                    }
                }

                $currency_paytr         = $currency;

                $hash_str               = $merchant_id . $user_ip . $merchant_oid . $email . $payment_amount . $payment_type . $installment_count. $currency_paytr. $test_mode. $non_3d;
                $token                  = base64_encode(hash_hmac('sha256',$hash_str.$merchant_salt,$merchant_key,true));

                $returnData             = ['status' => "successful"];

                $returnData['data'] = [
                    'merchant_id'       => $merchant_id,
                    'user_ip'           => $user_ip,
                    'merchant_oid'      => $merchant_oid,
                    'email'             => $email,
                    'payment_type'      => $payment_type,
                    'payment_amount'    => $payment_amount,
                    'currency'          => $currency_paytr,
                    'test_mode'         => $test_mode,
                    'non_3d'            => $non_3d,
                    'merchant_ok_url'   => $merchant_ok_url,
                    'merchant_fail_url' => $merchant_fail_url,
                    'user_name'         => $user_name,
                    'user_address'      => $user_address,
                    'user_phone'        => $user_phone,
                    'user_basket'       => $user_basket,
                    'debug_on'          => $debug_on,
                    'client_lang'       => $lang,
                    'paytr_token'       => $token,
                    'non3d_test_failed' => 0,
                    'installment_count' => $installment_count,
                    'card_type'         => $card_type,
                ];
                if($g_stored['cvc'] ?? false) $returnData['data']['cvv'] = $g_stored['cvc'];

                if($save_card) $returnData['data']['store_card'] = 1;

                if($stored_card)
                {
                    $ctoken         = Filter::init("POST/ctoken","letters_numbers");
                    $returnData['data']['utoken'] = $g_stored["token"]["utoken"];
                    $returnData['data']['ctoken'] = $ctoken;
                    if(strlen($card_cvc) > 1) $returnData['data']['cvv'] = $card_cvc;
                    $checkout_data["pmethod_stored_card"]   = $stored_card;

                    Modules::save_log("Payment",__CLASS__,'formData',json_encode($returnData,JSON_PRETTY_PRINT));
                }
                else
                {
                    $returnData['data']['cc_owner']          = $card_name;
                    $returnData['data']['card_number']       = $card_num;
                    $returnData['data']['expiry_month']      = $card_expiry_m;
                    $returnData['data']['expiry_year']       = $card_expiry_y;
                    $returnData['data']['cvv']               = $card_cvc;


                    $checkout_data["pmethod_card_country"]      = $bin_result["country"];
                    $checkout_data["pmethod_card_type"]         = $bin_result["card_type"];
                    $checkout_data["pmethod_card_schema"]       = $bin_result["schema"];
                    $checkout_data["pmethod_bank_name"]         = $bin_result["bank_name"];
                    $checkout_data["pmethod_card_brand"]        = $bin_result["brand"];
                    $checkout_data["pmethod_card_ln4"]          = substr($card_num,-4);
                    $checkout_data["pmethod_card_cvc"]          = $card_cvc;
                    $checkout_data["pmethod_name"]              = $card_name;
                    $checkout_data["pmethod_expiry_month"]      = $card_expiry_m;
                    $checkout_data["pmethod_expiry_year"]       = $card_expiry_y;

                    $checkout_data['pmethod_store_new_card']    = $save_card;

                }

                $checkout_data['pmethod_auto_pay']          = $auto_card;

                Basket::set_checkout($chid,['data' => Utility::jencode($checkout_data)]);

                echo Utility::jencode($returnData);
            };
        }


        public function installment_rates($bin=[])
        {
            if($this->config["settings"]["no_installment"]) return [];
            $file       = FileManager::file_read(__DIR__.DS."installment-rates.json");
            $return     = $file ? Utility::jdecode($file,true) : [];


            if(!isset($bin['brand']) || !$bin["brand"]) return false;


            if(!isset($return[$bin["brand"]]) || !$return[$bin["brand"]]) return false;

            $new_return = [];

            foreach($return[$bin["brand"]] AS $k => $v) $new_return[substr($k,7)] = $v;

            return $new_return;
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

            $force_curr     = $this->config["settings"]["force_convert_to"] ?? false;
            $org_curr       = $checkout_data["currency"];

            if($force_curr && $force_curr != $checkout_data["currency"])
            {
                $checkout_data["total"]        = Money::exChange($checkout_data["total"],$org_curr,$force_curr);
                $checkout_data["currency"]     = $force_curr;
            }


            $merchant_id            = $this->config["settings"]["merchant_id"];
            $merchant_salt          = $this->config["settings"]["merchant_salt"];
            $merchant_key           = $this->config["settings"]["merchant_key"];
            $no_installment         = $this->config["settings"]["no_installment"];
            $max_installment        = $this->config["settings"]["max_installment"];
            $test_mode              = $this->config["settings"]["test_mode"];
            $debug_on               = $this->config["settings"]["debug_on"];
            $currency               = $this->cid_convert_code2($checkout_data["currency"]);
            $user_ip                = $this->get_ip();
            $merchant_oid           = $this->checkout_id;
            $email                  = $user_data["email"];
            $payment_amount         = number_format($checkout_data["total"], 2, '.', '');
            $payment_amount         = $payment_amount * 100;
            $user_basket            = "";
            $user_name              = $user_data["full_name"];
            if($user_data["company_name"]) $user_name .= " ".$user_data["company_name"];
            $user_address           = $user_data["address"]["country_name"];
            $user_address           .= " / ".$user_data["address"]["city"];
            $user_address           .= " / ".$user_data["address"]["counti"];
            $user_address           .= " / ".$user_data["address"]["address"];
            $user_phone             = $user_data["gsm_cc"].$user_data["gsm"];

            if(!$user_phone){
                $user_info          = User::getInfo($user_data["id"],['gsm_cc','gsm']);
                $user_phone         = $user_info["gsm_cc"].$user_info["gsm"];
            }

            $merchant_ok_url        = $ok_url;
            $merchant_fail_url      = $fail_url;
            $lang                   = strtolower(Bootstrap::$lang->get("package/code"));
            if($lang !== "tr") $lang = "en";

            if($checkout_items && is_array($checkout_items)){
                $user_basket = [];
                foreach($checkout_items AS $item){
                    if((!isset($item["name"]) || !$item["name"]) && isset($item["description"]))
                        $item["name"] = $item["description"];

                    if($force_curr && $force_curr != $org_curr)
                        $item["amount"] = Money::exChange($item["amount"],$org_curr,$force_curr);

                    $user_basket[] = [$item["name"],round($item["amount"],2),$item["quantity"]];
                }
                $user_basket = base64_encode(json_encode($user_basket));
            }

            $hash_str = $merchant_id .$user_ip .$merchant_oid .$email .$payment_amount .$user_basket.$no_installment.$max_installment.$currency.$test_mode;
            $paytr_token=base64_encode(hash_hmac('sha256',$hash_str.$merchant_salt,$merchant_key,true));

            $post_vals=array(
                'ref_id' => '6b14b12e397c6ad9f5dd08ed15c6925ad239e775d112e0386da8f7b6fe90c3fd',
                'merchant_id'=>$merchant_id,
                'user_ip'=>$user_ip,
                'merchant_oid'=>$merchant_oid,
                'email'=>$email,
                'payment_amount'=>$payment_amount,
                'paytr_token'=>$paytr_token,
                'user_basket'=>$user_basket,
                'debug_on'=>$debug_on,
                'no_installment'=>$no_installment,
                'max_installment'=>$max_installment,
                'user_name'=>$user_name,
                'user_address'=>$user_address,
                'user_phone'=>$user_phone,
                'merchant_ok_url'=>$merchant_ok_url,
                'merchant_fail_url'=>$merchant_fail_url,
                'timeout_limit'=>30,
                'currency'=>$currency,
                'test_mode'=>$test_mode,
                'lang'=>$lang,
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
                    <iframe src="https://www.paytr.com/odeme/guvenli/<?php echo $token;?>" id="paytriframe" frameborder="0" scrolling="no" style="width: 100%;"></iframe>
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

        public function bin_check($bin_number = "")
        {
            $merchant_id            = $this->config["settings"]["merchant_id"];
            $merchant_salt          = $this->config["settings"]["merchant_salt"];
            $merchant_key           = $this->config["settings"]["merchant_key"];

            $hash_str = $bin_number . $merchant_id . $merchant_salt;
            $paytr_token=base64_encode(hash_hmac('sha256', $hash_str, $merchant_key, true));
            $post_vals=array(
                'merchant_id'=>$merchant_id,
                'bin_number'=>$bin_number,
                'paytr_token'=>$paytr_token
            );

            $ch     = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://www.paytr.com/odeme/api/bin-detail");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1) ;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vals);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $result = @curl_exec($ch);



            if(curl_errno($ch))
            {
                $this->error = "PAYTR BIN detail request timeout. err:".curl_error($ch);
                curl_close($ch);

                Modules::save_log("Payment",__CLASS__,'bin_check',$post_vals,$result,$this->error);

                return false;
            }

            curl_close($ch);

            $resultx = $result;

            $result=json_decode($result,true);

            if(!$result && $resultx)
            {
                $this->error = $resultx;
                Modules::save_log("Payment",__CLASS__,'bin_check',$post_vals,$result);
                return false;
            }


            if($result['status']=='error')
            {
                $this->error = "PAYTR BIN detail request error. Error:".$result['err_msg'];
                Modules::save_log("Payment",__CLASS__,'bin_check',$post_vals,$result,$this->error);
                return false;
            }
            elseif($result['status']=='failed')
            {
                $this->error = "INTERNATIONAL";
                return false;
            }

            return [
                'country'       => "TR",
                'card_type'     => $result['cardType'],
                'schema'        => $result['schema'],
                'bank_name'     => $result['bank'],
                'brand'         => $result['brand'],
            ];
        }

        public function api_stored_cards($utoken='')
        {
            $merchant_id            = $this->config["settings"]["merchant_id"];
            $merchant_salt          = $this->config["settings"]["merchant_salt"];
            $merchant_key           = $this->config["settings"]["merchant_key"];

            $hash_str           = $utoken . $merchant_salt;
            $paytr_token        = base64_encode(hash_hmac('sha256', $hash_str, $merchant_key, true));
            $post_vals = array(
                'merchant_id'       => $merchant_id,
                'utoken'            => $utoken,
                'paytr_token'       => $paytr_token
            );
            ############################################################################################

            $ch=curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://www.paytr.com/odeme/capi/list");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1) ;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vals);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);

            $result = @curl_exec($ch);

            curl_close($ch);

            $result     = Utility::jdecode($result,1);

            if(isset($result['status']) && $result['status']=='error')
            {
                $this->error = "PAYTR CAPI list failed. Error:".$result['err_msg'];
                return false;
            }
            return $result;
        }

        public function stored_cards($checkout=[],$capture=false)
        {
            $user_data              = UserManager::LoginData();
            if(!$user_data && !$capture) return [];
            $user_id                = $capture ? $checkout["data"]["user_data"]["id"] : $user_data["id"];

            if($user_data && $user_id && $user_data["id"] != $user_id) return [];

            $stmt = Models::$init->db->select("id")->from("users_stored_cards");
            $stmt->where("module","=","PayTR","&&");
            $stmt->where("user_id","=",$user_id);
            $stmt = $stmt->build() ? $stmt->fetch_assoc() : [];
            $returnData = [];
            if($stmt)
            {
                foreach($stmt AS $row)
                {
                    $row                = $this->get_stored_card($row["id"],$user_id);
                    $utoken             = isset($row["token"]["utoken"]) ? $row["token"]["utoken"] : '';
                    $result             = $this->api_stored_cards($utoken);
                    if($result)
                    {
                        foreach($result AS $row_2)
                        {
                            if($row["ln4"] == $row_2["last_4"])
                            {
                                $row["token"]["ctoken"]     = $row_2["ctoken"];
                                $row["require_cvc"]         = $row_2["require_cvv"];
                                $returnData[$row["id"]]     = $row;
                            }
                        }
                    }
                }
            }
            return $returnData;
        }

        public function remove_stored_card($stored_card=[])
        {
            if(!$stored_card) return false;

            $utoken             = $stored_card["token"]["utoken"];
            $ctoken             = '';

            $cards             = $this->api_stored_cards($utoken);
            if(!$cards)
            {
                Modules::save_log("Payment",__CLASS__,"remove_stored_card",['token' => $utoken,'ln4' => $stored_card["ln4"]],'Not found stored card');
                return true;
            }

            foreach($cards AS $row) if($stored_card["ln4"] == $row["last_4"]) $ctoken = $row["ctoken"];


            $merchant_id            = $this->config["settings"]["merchant_id"];
            $merchant_salt          = $this->config["settings"]["merchant_salt"];
            $merchant_key           = $this->config["settings"]["merchant_key"];

            $hash_str = $ctoken . $utoken . $merchant_salt;
            $paytr_token=base64_encode(hash_hmac('sha256', $hash_str, $merchant_key, true));
            $post_vals=array(
                'merchant_id'=>$merchant_id,
                'ctoken'=>$ctoken,
                'utoken'=>$utoken,
                'paytr_token'=>$paytr_token
            );

            $ch     = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://www.paytr.com/odeme/capi/delete");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1) ;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vals);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);

            $result = @curl_exec($ch);

            if(curl_errno($ch))
            {
                $this->error = "PAYTR CAPI Delete connection error. err:".curl_error($ch);
                curl_close($ch);
                return false;
            }

            curl_close($ch);

            $result     = json_decode($result,1);

            if($result['status']!='success')
            {
                $this->error = "PAYTR CAPI Delete failed. Error:".$result['err_msg'];
                return false;
            }

            return true;
        }

        public function payment_result()
        {
            $merchant_key 	= $this->config["settings"]["merchant_key"];
            $merchant_salt	= $this->config["settings"]["merchant_salt"];
            $amount_paid    = Filter::POST("total_amount");
            $status         = Filter::POST("status");
            $merchant_oid   = Filter::POST("merchant_oid");
            $failed_rcode   = (int) Filter::POST("failed_reason_code");
            $failed_rmsg    = Filter::POST("failed_reason_msg");
            $post_hash      = Filter::POST("hash");
            $utoken         = Filter::POST("utoken");
            $currency_p     = Filter::POST("currency");

            if($currency_p == "TL") $currency_p = "TRY";


            $hash = base64_encode( hash_hmac('sha256', $merchant_oid.$merchant_salt.$status.$amount_paid,$merchant_key,true));
            if($hash != $post_hash)
            {
                Modules::save_log("Payment",__CLASS__,'callback',false,[
                    'POST'      => $_POST,
                    'hash'      => $hash,
                    'merchant_oid'  => $merchant_oid,
                    'merchant_salt' => $merchant_salt,
                    'status'        => $status,
                    'amount_paid'   => $amount_paid,
                    'merchant_key' => $merchant_key,
                ],'notification failed: bad hash');
                return [
                    'status' => "ERROR",
                    'status_msg' => "PAYTR notification failed: bad hash",
                    'return_msg' => "OK",
                ];
            }

            $checkout_id        = (int) Filter::POST("merchant_oid");
            $checkout           = Basket::get_checkout($checkout_id);

            if(!$checkout) {
                Modules::save_log("Payment",__CLASS__,'callback',false,[
                    'POST'      => $_POST,
                ],Bootstrap::$lang->get("errors/error6", Config::get("general/local")));
                return [
                    'status'     => "ERROR",
                    'status_msg' => Bootstrap::$lang->get("errors/error6", Config::get("general/local")),
                    'return_msg' => "OK",
                ];
            }

            if($failed_rcode == 6 || $status == "failed")
            {
                Modules::save_log("Payment",__CLASS__,'callback',false,[
                    'POST'      => $_POST,
                ],'Status failed, message: '.$failed_rmsg);

                return [
                    'status' => "ERROR",
                    'status_msg' => $failed_rmsg,
                    'return_msg' => "OK",
                ];
            }

            $amount_paid    = $amount_paid / 100;
            $amount_curr    = $currency_p;

            $amount_paid    = Money::deformatter($amount_paid,$amount_curr);

            $amount_curr_x      = Money::Currency($amount_curr);
            if($amount_curr_x) $amount_curr = $amount_curr_x["id"];
            $checkout_curr      = $checkout["data"]["currency"];

            if($checkout_curr != $amount_curr_x)
            {
                $amount_paid = Money::exChange($amount_paid,$amount_curr,$checkout_curr);
                $amount_curr = $checkout_curr;
            }


            Modules::save_log("Payment",__CLASS__,"callback",false,['GET' => $_GET,'POST' => $_POST]);


            $this->set_checkout($checkout);

            if($utoken && isset($checkout["data"]["pmethod_store_new_card"]) && $checkout["data"]["pmethod_store_new_card"]) $checkout["data"]["pmethod_token"] = ['utoken' => $utoken];

            Basket::set_checkout($checkout["id"],['status' => "paid",'data' => Utility::jencode($checkout["data"])]);

            if($checkout["data"]["type"] == "card-identification")
            {
                if(!class_exists("Events")) Helper::Load(["Events"]);
                Events::add_scheduled_operation([
                    'owner'             => "Refund",
                    'owner_id'          => 0,
                    'name'              => "refund-on-payment-module",
                    'period'            => 'minute',
                    'time'              => 3,
                    'module'            => __CLASS__,
                    'needs'             => ['checkout_id' => $checkout_id],
                ]);
            }

            return [
                'paid'           => [
                    'amount'        => round($amount_paid,2),
                    'currency'      => $amount_curr,
                ],
                'status'         => "SUCCESS",
                'checkout'       => $checkout,
                'return_msg'     => "OK",
            ];
        }

        public function capture($params=[])
        {
            $stored_card    = $params["data"]["pmethod_stored_card"];

            Helper::Load(["Basket","Money"]);

            $params_items         = $params["items"];
            $params_data          = $params["data"];
            $user_data            = $params_data["user_data"];
            $bin_result           = [];

            $stored_cards           = $this->stored_cards($params,true);
            if(!$stored_cards)
            {
                $this->error = Bootstrap::$lang->get_cm("website/payment/card-tx28");
                return false;
            }
            $g_stored       = [];
            foreach($stored_cards AS $d) if($d["id"] == $stored_card) $g_stored = $d;
            $utoken         = $g_stored["token"]["utoken"];
            $ctoken         = $g_stored["token"]["ctoken"];
            $require_cvc    = $g_stored["require_cvc"];


            $bin_result["country"]          = $g_stored["card_country"];
            $bin_result["type"]             = $g_stored["card_type"];
            $bin_result["schema"]           = $g_stored["card_schema"];
            $bin_result["brand"]            = $g_stored["card_brand"];
            $card_country                   = $bin_result["country"];
            $non_3d                         = 1;
            $installment                    = 0;
            $ok_url                         = APP_URI."/PAYMENT-OK";
            $fail_url                       = APP_URI."/PAYMENT-FAIL";


            $force_curr     = $this->config["settings"]["force_convert_to"] ?? false;
            $org_curr       = $params_data["currency"];

            $new_amount     = $params_data["total"];
            $new_curr       = $org_curr;

            if($force_curr && $force_curr != $params_data["currency"])
            {
                $new_amount        = Money::exChange($new_amount,$new_curr,$force_curr);
                $new_curr           = $force_curr;
            }



            $merchant_id            = $this->config["settings"]["merchant_id"];
            $merchant_salt          = $this->config["settings"]["merchant_salt"];
            $merchant_key           = $this->config["settings"]["merchant_key"];

            $no_installment         = $this->config["settings"]["no_installment"];
            $max_installment        = $this->config["settings"]["max_installment"];
            $test_mode              = $this->config["settings"]["test_mode"];
            $debug_on               = $this->config["settings"]["debug_on"];
            $currency               = $this->cid_convert_code2($new_curr);
            $user_ip                = $this->get_ip();
            $merchant_oid           = $params["id"];
            $email                  = $user_data["email"];
            $payment_amount         = number_format($new_amount, 2, '.', '');
            $user_basket            = "";
            $user_name              = $user_data["full_name"];
            if($user_data["company_name"]) $user_name .= " ".$user_data["company_name"];
            $user_address           = $user_data["address"]["country_name"];
            $user_address           .= " / ".$user_data["address"]["city"];
            $user_address           .= " / ".$user_data["address"]["counti"];
            $user_address           .= " / ".$user_data["address"]["address"];
            $user_phone             = $user_data["gsm_cc"].$user_data["gsm"];

            if(!$user_phone){
                $user_info          = User::getInfo($user_data["id"],['gsm_cc','gsm']);
                $user_phone         = $user_info["gsm_cc"].$user_info["gsm"];
            }

            $merchant_ok_url        = $ok_url;
            $merchant_fail_url      = $fail_url;
            $lang                   = strtolower(Bootstrap::$lang->get("package/code"));
            if($lang !== "tr") $lang = "en";

            if($params_items && is_array($params_items)){
                $user_basket = [];
                foreach($params_items AS $item){
                    if($force_curr && $force_curr != $org_curr)
                        $item["amount"] = Money::exChange($item["amount"],$org_curr,$force_curr);

                    $user_basket[] = [$item["name"],round($item["amount"],2),$item["quantity"]];
                }
                $user_basket = htmlentities(json_encode($user_basket));
            }

            $payment_type           = "card";
            $card_type              = "";
            $installment_count = (int) $installment;
            if($card_country == "TR") $card_type = $bin_result["brand"];

            $hash_str               = $merchant_id . $user_ip . $merchant_oid . $email . $payment_amount . $payment_type . $installment_count. $currency. $test_mode. $non_3d;
            $token                  = base64_encode(hash_hmac('sha256',$hash_str.$merchant_salt,$merchant_key,true));

            $post_vals             = [
                'ref_id'            => '6b14b12e397c6ad9f5dd08ed15c6925ad239e775d112e0386da8f7b6fe90c3fd',
                'merchant_id'       => $merchant_id,
                'user_ip'           => $user_ip,
                'merchant_oid'      => $merchant_oid,
                'email'             => $email,
                'payment_type'      => $payment_type,
                'payment_amount'    => $payment_amount,
                'currency'          => $currency,
                'test_mode'         => $test_mode,
                'non_3d'            => $non_3d,
                'merchant_ok_url'   => $merchant_ok_url,
                'merchant_fail_url' => $merchant_fail_url,
                'user_name'         => $user_name,
                'user_address'      => $user_address,
                'user_phone'        => $user_phone,
                'user_basket'       => $user_basket,
                'debug_on'          => $debug_on,
                'client_lang'       => $lang,
                'paytr_token'       => $token,
                'non3d_test_failed' => 0,
                'installment_count' => $installment_count,
                'card_type'         => $card_type,
            ];
            $post_vals['utoken'] = $utoken;
            $post_vals['ctoken'] = $ctoken;
            $post_vals['cvv']    = $g_stored["cvc"];

            $ch=curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://www.paytr.com/odeme");
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1) ;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vals);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 90);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 90);
            $result = @curl_exec($ch);
            if(curl_errno($ch))
            {
                $this->error = curl_error($ch);
                curl_close($ch);
                return false;
            }
            $header_size    = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);

            $header         = substr($result, 0, $header_size);
            $result         = substr($result, $header_size);

            Modules::save_log("Payment",__CLASS__,"capture",$post_vals,$result);

            preg_match('/name="fail_message" value="(.*?)">/m', $result, $error_message);
            $error_message = isset($error_message[1]) && $error_message[1] ? $error_message[1] : '';

            if(strlen($error_message) >= 3)
            {
                $this->error = $error_message;
                return false;
            }

            $result_dc        = Utility::jdecode(strip_tags($result),true);

            if(is_array($result_dc) && isset($result_dc["status"]) && $result_dc["status"] == "failed")
            {
                $this->error = $result_dc["reason"];
                return false;
            }

            if(stristr($header,$merchant_ok_url)) return true;

            $this->error = "Payment Could Not Be Collected";

            return false;
        }

        public function refund($params=[])
        {
            $merchant_id            = $this->config["settings"]["merchant_id"];
            $merchant_salt          = $this->config["settings"]["merchant_salt"];
            $merchant_key           = $this->config["settings"]["merchant_key"];
            $merchant_oid           = $params["id"];
            $params_data            = $params["data"];

            $force_curr     = $this->config["settings"]["force_convert_to"] ?? false;
            $org_curr       = $params_data["currency"];

            if($force_curr && $force_curr != $params_data["currency"])
            {
                $params_data["total"]        = Money::exChange($params_data["total"],$org_curr,$force_curr);
                $params_data["currency"]     = $force_curr;
            }

            $return_amount          = $params_data["total"];


            $paytr_token            = base64_encode(hash_hmac('sha256',$merchant_id.$merchant_oid.$return_amount.$merchant_salt,$merchant_key,true));

            $post_vals  = [
                'merchant_id'=>$merchant_id,
                'merchant_oid'=>$merchant_oid,
                'return_amount'=>$return_amount,
                'paytr_token'=>$paytr_token
            ];

            $ch     = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://www.paytr.com/odeme/iade");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1) ;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vals);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 90);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 90);
            $result = @curl_exec($ch);

            if(curl_errno($ch))
            {
                $this->error = curl_error($ch);
                curl_close($ch);
                return false;
            }
            curl_close($ch);

            $result     = json_decode($result,1);

            if($result["status"] != 'success')
            {
                $this->error = $result['err_no'].' - '.$result['err_msg'];
                return false;
            }

            return true;
        }

        public function update_installment_rates()
        {
            $merchant_id            = $this->config["settings"]["merchant_id"];
            $merchant_salt          = $this->config["settings"]["merchant_salt"];
            $merchant_key           = $this->config["settings"]["merchant_key"];
            $request_id             = time();
            $paytr_token            = base64_encode(hash_hmac('sha256',$merchant_id.$request_id.$merchant_salt,$merchant_key,true));

            $post_vals=array(
                'merchant_id'=>$merchant_id,
                'request_id'=>$request_id,
                'paytr_token'=>$paytr_token
            );

            $ch=curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://www.paytr.com/odeme/taksit-oranlari");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1) ;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vals);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 90);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 90);

            $result = @curl_exec($ch);

            if(curl_errno($ch))
            {
                $this->error = curl_error($ch);
                curl_close($ch);
                return false;
            }

            curl_close($ch);
            $result     = json_decode($result,1);

            if($result["status"] != 'success')
            {
                $this->error = $result["err_msg"];
                return false;
            }

            $rates      = $result["oranlar"];
            $rates      = Utility::jencode($rates);


            return FileManager::file_write(__DIR__.DS."installment-rates.json",$rates);
        }

        private function cid_convert_code2($id=0){
            Helper::Load(["Money","User"]);
            $currency   = Money::Currency($id);
            if($currency)
            {
                $code = $currency["code"];
                if($code == "TRY") $code = "TL";

                return $code;
            }
            return false;
        }
    }
