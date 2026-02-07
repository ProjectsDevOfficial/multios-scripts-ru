<?php
    class AkbankPayten extends PaymentGatewayModule
    {

        function __construct(){
            $this->name             = __CLASS__;
            $this->standard_card    = true;

            parent::__construct();
        }


        public function config_fields()
        {
            return [
                'type'                      => [
                    'name'              => "Pos Tipi",
                    'description'       => "Pos tipi belirleyiniz.",
                    'type'              => "dropdown",
                    'value'             => $this->config["settings"]["type"] ?? '',
                    'options'           => [
                        "3d_pay_hosting"    => "3D PAY HOSTING",
                        "3d_pay"            => "3D PAY",
                    ],
                ],
                'merchant_id'               => [
                    'name'              => "Mağaza Numarası",
                    'description'       => "Bankanız tarafından iletilen Client ID (Mağaza numarasını) yazınız.",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["merchant_id"] ?? '',
                ],
                'username'                  => [
                    'name'             => "Kullanıcı Adı",
                    'description'      => "",
                    'type'             => "text",
                    'value'            => $this->config["settings"]["username"] ?? '',
                ],
                'password'                  => [
                    'name'             => "Parola",
                    'description'      => "",
                    'type'             => "password",
                    'value'            => $this->config["settings"]["password"] ?? '',
                ],
                'store_key'                 => [
                    'name'             => "Store Key",
                    'description'      => "",
                    'type'             => "text",
                    'value'            => $this->config["settings"]["store_key"] ?? '',
                ],
                'installment'               => [
                    'name'              => "Taksit Seçeneği",
                    'description'       => "Seçerseniz ödeme sırasında taksit seçeneği sunulur.",
                    'type'              => "approval",
                    'value'             => 1,
                    'checked'           => (boolean) (int) ($this->config["settings"]["installment"] ?? 0),
                ],
                'installment_commission'    => [
                    'name'              => "Taksit Komisyonu",
                    'description'       => "Komisyon oranı aşağıdaki gibi yazılmalıdır.<br>2 : 2.50<br>3 : 4.50<br>4 : 5.20",
                    'type'              => "textarea",
                    'value'           => $this->config["settings"]["installment_commission"] ?? '',
                ],
                'max_installment'           => [
                    'name'              => "Taksit Sınırı",
                    'description'       => "En fazla kaç taksit olacağını belirleyiniz.",
                    'type'              => "text",
                    'value'           => $this->config["settings"]["max_installment"] ?? '12',
                ],
                'sandbox'                   => [
                    'name'              => "Test Modu",
                    'description'       => "",
                    'type'              => "approval",
                    'value'             => 1,
                    'checked'           => (boolean) (int) ($this->config["settings"]["sandbox"] ?? 0),
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

        public function capture($params=[])
        {
            $storetype  = $this->config['settings']['type'] ?? "3d";
            $address = "https://www.sanalakpos.com/servlet/est3Dgate";
            $installment        = $params['installment'];
            $callback           = $this->links["callback"];
            $client_id          = $this->config['settings']['merchant_id'];
            $storeKey           = $this->config['settings']['store_key'];
            $rnd                = microtime();
            $amount             = $params["amount"]; //Transaction amount
            $transactionType    = "Auth"; //transaction type
            $oid                = $this->checkout_id; //Order Id. Must be unique. If left blank, system will generate a unique one.

            $form_params = [
                'pan'                                   => $params['num'],
                'cv2'                                   => $params['cvc'],
                'Ecom_Payment_Card_ExpDate_Year'        => $params['expiry_y'],
                'Ecom_Payment_Card_ExpDate_Month'       => $params['expiry_m'],
                'cardType'                              => 2,
                'clientid'                              => $client_id,
                'amount'                                => $amount,
                'okurl'                                 => $callback,
                'failUrl'                               => $callback,
                'TranType'                              => $transactionType,
                'Instalment'                            => $installment,
                'callbackUrl'                           => $callback,
                'currency'                              => $this->convert_curr_code($params["currency"]),
                'rnd'                                   => $rnd,
                'storetype'                             => $storetype,
                'hashAlgorithm'                         => "ver3",
                'lang'                                  => Bootstrap::$lang->clang == "tr" ? "tr" : "en",
                'BillToName'                            => "name",
                'BillToCompany'                         => "billToCompany",
            ];

            Session::set("AkbankPayten_checkout",Utility::jencode([
                'rnd' => $rnd,
                'oid' => $oid,
            ]),true);


            if($storetype == "3d_pay" || $storetype == "3d_pay_hosting")
            {

                $hashval = "";

                $postParams = array();
                foreach ($form_params as $key => $value){
                    array_push($postParams, $key);
                }

                natcasesort($postParams);

                foreach ($postParams as $param){
                    $paramValue = $form_params[$param];
                    $escapedParamValue = str_replace("|", "\\|", str_replace("\\", "\\\\", $paramValue));

                    $lowerParam = strtolower($param);
                    if($lowerParam != "hash" && $lowerParam != "encoding" )	{
                        $hashval = $hashval . $escapedParamValue . "|";
                    }
                }

                $escapedStoreKey = str_replace("|", "\\|", str_replace("\\", "\\\\", $storeKey));
                $hashval = $hashval . $escapedStoreKey;

                $calculatedHashValue = hash('sha512', $hashval);
                $hash = base64_encode (pack('H*',$calculatedHashValue));
                $form_params["HASH"] = $hash;
            }


            $request            = Utility::HttpRequest($address,[
                'post' => $form_params,
                'timeout' => 20,
            ]);

            $form_params["pan"] = Filter::censored($form_params["pan"]);
            Modules::save_log("Payment",__CLASS__,"capture",$form_params,htmlentities($request),Utility::$error);

            if(Utility::$error)
                return [
                    'status'        => 'error',
                    'message'       => Utility::$error,
                ];
            else
            {
                if(stristr($request,'moveWindow'))
                    $request .= '<script type="text/javascript">$(document).ready(function(){ moveWindow(); });</script>';
                return [
                    'status' => "output",
                    'output' => $request,
                ];
            }
        }

        public function callback()
        {
            $name=$this->config["settings"]["username"];       			//API user name
            $password=$this->config["settings"]["password"];    			//API password
            $clientid       = $this->config["settings"]["merchant_id"];
            $storekey       = $this->config["settings"]["store_key"];
            $mdStatus       = $_POST['mdStatus'];
            $storetype      = $this->config["settings"]["type"] ?? "3d";
            $oid            = 0;
            $transid        = $_POST["TransId"];
            $response       = $_POST["Response"];

            $checkout       = Session::get("AkbankPayten_checkout",true);
            $checkout       = Utility::jdecode($checkout,true);

            if($checkout)
            {
                $oid = $checkout["oid"];
                Session::delete("AkbankPayten_checkout");
            }


            if($storetype == "3d_pay" || $storetype == "3d_pay_hosting")
            {
                $postParams = array();
                foreach ($_POST as $key => $value){
                    array_push($postParams, $key);
                }

                natcasesort($postParams);

                $hashval = "";
                foreach ($postParams as $param){
                    $paramValue = $_POST[$param];
                    $escapedParamValue = str_replace("|", "\\|", str_replace("\\", "\\\\", $paramValue));

                    $lowerParam = strtolower($param);
                    if($lowerParam != "hash" && $lowerParam != "encoding" )	{
                        $hashval = $hashval . $escapedParamValue . "|";
                    }
                }

                $escapedStoreKey = str_replace("|", "\\|", str_replace("\\", "\\\\", $storekey));
                $hashval = $hashval . $escapedStoreKey;

                $calculatedHashValue = hash('sha512', $hashval);
                $actualHash = base64_encode (pack('H*',$calculatedHashValue));

                $retrievedHash = $_POST["HASH"];
                if($retrievedHash !== $actualHash )
                {
                    $this->error = "Security Alert. The digital signature is not valid.";
                    Modules::save_log("Payment",__CLASS__,"callback",$_POST,$this->error);
                    return false;
                }

                $mdStatus=$_POST['mdStatus'];

                if($mdStatus =="1" || $mdStatus == "2" || $mdStatus == "3" || $mdStatus == "4")
                {
                    $Response = $_POST["Response"];

                    if ( $Response == "Approved")
                    {

                    }
                    else
                    {
                        $this->error = "Your payment is not approved.";
                        Modules::save_log("Payment",__CLASS__,"callback",$_POST,$this->error);
                        return false;
                    }
                }
                else
                {
                    $this->error = "3D Authentication is not successful.";
                    Modules::save_log("Payment",__CLASS__,"callback",$_POST,$this->error);
                    return false;
                }
            }


            $checkout = $this->get_checkout($oid);

            if(!$checkout)
            {
                $this->error = "Checkout is incorrect";
                return false;
            }

            $this->set_checkout($checkout);

            if ($response == "Approved") {
                Modules::save_log("Payment",__CLASS__,"callback",false,$_POST,"Successful");

                return [
                    'status' => "successful",
                    'message' => [
                        'Transaction ID' => $transid,
                    ],
                ];
            }
            Modules::save_log("Payment",__CLASS__,"callback",false,$_POST,"Unsuccessful");

            return [
                'status' => "error",
            ];

        }

        public function refund($checkout=[])
        {
            $amount         = $checkout["data"]["total"];
            $currency       = $this->currency($checkout["data"]["currency"]);


            $request = "<CC5Request><Name>" . $this->config['settings']["username"] . "</Name><Password>" . $this->config['settings']["password"] . "</Password><ClientId>" . $this->config['settings']["merchant_id"] . "</ClientId><Mode>P</Mode><OrderId>" . $checkout["id"] . "</OrderId><Type>Credit</Type><Total>" . $amount . "</Total><Currency>949</Currency></CC5Request>";
            $response = Utility::HttpRequest("https://www.sanalakpos.com/servlet/cc5ApiServer",[
                'post' => $request,
                'timeout' => 20,
            ]);
            $return = json_decode(json_encode(simplexml_load_string($response)), true);

            Modules::save_log("Payment",__CLASS__,"Refund",$request,$response,$return);

            if ($return["Response"] == "Approved") return true;

            return false;
        }

        public function convert_curr_code($currency='')
        {
            $find = ["AED", "AFN", "ALL", "AMD", "ANG", "AOA", "ARS", "AUD", "AWG", "AZN", "BAM", "BBD", "BDT", "BGN", "BHD", "BIF", "BMD", "BND", "BOB", "BOV", "BRL", "BSD", "BTN", "BWP", "BYR", "BZD", "CAD", "CDF", "CHE", "CHF", "CHW", "CLF", "CLP", "CNH", "CNY", "COP", "COU", "CRC", "CUC", "CUP", "CVE", "CZK", "DJF", "DKK", "DOP", "DZD", "EGP", "ERN", "ETB", "EUR", "FJD", "FKP", "GBP", "GEL", "GHS", "GIP", "GMD", "GNF", "GTQ", "GYD", "HKD", "HNL", "HRK", "HTG", "HUF", "IDR", "ILS", "INR", "IQD", "IRR", "ISK", "JMD", "JOD", "JPY", "KES", "KGS", "KHR", "KMF", "KPW", "KRW", "KWD", "KYD", "KZT", "LAK", "LBP", "LKR", "LRD", "LSL", "LTL", "LYD", "MAD", "MDL", "MGA", "MKD", "MMK", "MNT", "MOP", "MRO", "MUR", "MVR", "MWK", "MXN", "MXV", "MYR", "MZN", "NAD", "NGN", "NIO", "NOK", "NPR", "NZD", "OMR", "PAB", "PEN", "PGK", "PHP", "PKR", "PLN", "PYG", "QAR", "RON", "RSD", "RUB", "RWF", "SAR", "SBD", "SCR", "SDG", "SEK", "SGD", "SHP", "SLL", "SOS", "SRD", "SSP", "STD", "SYP", "SZL", "THB", "TJS", "TMT", "TND", "TOP", "TRY", "TTD", "TWD", "TZS", "UAH", "UGX", "USD", "USN", "USS", "UYI", "UYU", "UZS", "VEF", "VND", "VUV", "WST", "XAF", "XAG", "XAU", "XBA", "XBB", "XBC", "XBD", "XCD", "XDR", "XFU", "XOF", "XPD", "XPF", "XPT", "XSU", "XTS", "XUA", "XXX", "YER", "ZAR", "ZMW", "ZWD"];
            $replace = ["784", "971", "008", "051", "532", "973", "032", "036", "533", "944", "977", "052", "050", "975", "048", "108", "060", "096", "068", "984", "986", "044", "064", "072", "974", "084", "124", "976", "947", "756", "948", "990", "152", "156", "156", "170", "970", "188", "931", "192", "132", "203", "262", "208", "214", "012", "818", "232", "230", "978", "242", "238", "826", "981", "936", "292", "270", "324", "320", "328", "344", "340", "191", "332", "348", "360", "376", "356", "368", "364", "352", "388", "400", "392", "404", "417", "116", "174", "408", "410", "414", "136", "398", "418", "422", "144", "430", "426", "440", "434", "504", "498", "969", "807", "104", "496", "446", "478", "480", "462", "454", "484", "979", "458", "943", "516", "566", "558", "578", "524", "554", "512", "590", "604", "598", "608", "586", "985", "600", "634", "946", "941", "643", "646", "682", "090", "690", "938", "752", "702", "654", "694", "706", "968", "728", "678", "760", "748", "764", "972", "934", "788", "776", "949", "780", "901", "834", "980", "800", "840", "997", "998", "940", "858", "860", "937", "704", "548", "882", "950", "961", "959", "955", "956", "957", "958", "951", "960", "Nil", "952", "964", "953", "962", "994", "963", "965", "999", "886", "710", "967", "932"];
            return str_replace($find, $replace, $currency);
        }
    }