<?php
    class Garanti extends PaymentGatewayModule
    {

        function __construct(){
            $this->name             = __CLASS__;
            $this->standard_card    = true;

            parent::__construct();
            $this->define_function("ThreeDRedirect","ThreeDRedirect");
        }


        public function config_fields()
        {
            return [
                'type'          => [
                    'name'              => "Sanal Pos Tipi (3D Security Level)",
                    'description'       => "",
                    'type'              => "dropdown",
                    'value'             => $this->config["settings"]["type"] ?? '',
                    'options'           => '3D_FULL,3D_PAY,3D_HALF',
                ],

                'merchant_id'          => [
                    'name'              => "Mağaza Numarası (Merchant ID)",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["merchant_id"] ?? '',
                ],

                'password'          => [
                    'name'              => "Provizyon Şifresi",
                    'description'       => "",
                    'type'              => "password",
                    'value'             => $this->config["settings"]["password"] ?? '',
                ],

                'store_key'          => [
                    'name'              => "Store Key",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["store_key"] ?? '',
                ],

                'terminal_id'          => [
                    'name'              => "Terminal ID",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["terminal_id"] ?? '',
                ],
                'installment'          => [
                    'name'              => "Taksit Seçeneği",
                    'description'       => "Seçerseniz ödeme sırasında taksit seçeneği sunulur.",
                    'type'              => "approval",
                    'value'             => 1,
                    'checked'           => (boolean) (int) ($this->config["settings"]["installment"] ?? 0),
                ],

                'installment_commission'          => [
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
                'sandbox'          => [
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
            if(!(stristr($card_bin["bank_name"],'GARANTI')))
                return [];


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
            $address = "https://sanalposprov.garanti.com.tr/servlet/gt3dengine";
            $mode = "PROD";

            if($this->config['settings']['sandbox'])
            {
                $mode       = "TEST";
                $address    = "https://sanalposprovtest.garanti.com.tr/VPServlet";
            }


            $ip             = UserManager::GetIP();

            $amount         = round($params["amount"],2) * 100;
            $installment    = $params['installment'] ?? 0;
            $currency       = $this->convert_curr_code($params["currency"]);
            $callback       = /*"https://garantibbvapos.com.tr/destek/postback.aspx";*/ $this->links["callback"];
            $tid            = $this->config['settings']['terminal_id'];
            $password       = $this->config['settings']['password'];
            $st_key         = $this->config['settings']['store_key'];

            if($installment < 1) $installment = '';

            $SecurityData   = strtoupper(sha1($password. str_pad($tid, 9, "0", STR_PAD_LEFT)));
            $HashData       = strtoupper(sha1($tid . $this->checkout_id . $amount . $callback . $callback . "sales" . $installment . $st_key . $SecurityData));


            $post_fields = [
                "secure3dsecuritylevel" => $this->config['settings']['type'],
                "cardnumber"            => $params['num'],
                "cardexpiredatemonth"   => $params['expiry_m'],
                "cardexpiredateyear"    => $params['expiry_y'],
                "cardcvv2"              => $params['cvc'],
                "mode"                  => $mode,
                "apiversion"            => "v0.01",
                "terminalprovuserid"    => "PROVAUT",
                "terminaluserid"        => $this->string_to_ascii($this->clientInfo->full_name),
                "terminalmerchantid"    => $this->config['settings']['merchant_id'],
                "txntype"               => "sales",
                "txnamount"             => $amount,
                "txncurrencycode"       => $this->convert_curr_code($currency),
                "txninstallmentcount"   => $installment,
                "orderid"               => $this->checkout_id,
                "terminalid"            => $tid,
                "successurl"            => $callback,
                "errorurl"              => $callback,
                "customeripaddress"     => $ip,
                "customeremailaddress" => $this->clientInfo->email,
                "secure3dhash"         => $HashData,
            ];

            $request            = Utility::HttpRequest($address,[
                'post' => urldecode(http_build_query($post_fields)),
                'timeout' => 20,
            ]);

            $post_fields["cardnumber"] = Filter::censored($params["num"]);
            Modules::save_log("Payment",__CLASS__,"capture",$post_fields,htmlentities($request),Utility::$error);

            if($request)
            {
                Session::set("Garanti3DRedirect",$request,true);

                /*
                $re = '/"\/ruxitagentjs(.*?)"/m';
                preg_match_all($re, $request, $matches, PREG_SET_ORDER, 0);
                $regres         = $matches[0][0] ?? '';

                if($regres) $request  = str_replace('src='.$regres,'',$request);
                $request  = str_replace('/oosweb/img/loading.gif','/resources/assets/images/loading.gif',$request);
                $request  = str_replace('/oosweb/img/preloader.gif','/resources/assets/images/loading.gif',$request);


                #$request  = '<textarea>'.$request.'</textarea>';

                if(stristr($request,'Please click here to continue'))
                {
                    $request .= '<script type="text/javascript">$(document).ready(function(){ $("input[name=submitBtn]").click(); });</script>';
                }
                $request  .= '<script type="text/javascript">$(document).ready(function(){ if(typeof autoSubmit !== "undefined"){ autoSubmit(); } });</script>';
                $request  .= '<script type="text/javascript">$(document).ready(function(){ if(typeof OnLoadEvent !== "undefined"){ OnLoadEvent(); } });</script>';
                */

            }

            if(Utility::$error)
                return [
                    'status'        => 'error',
                    'message'       => Utility::$error,
                ];
            else
                return [
                    'status' => "redirect",
                    'redirect' => $this->links["ThreeDRedirect"],
                ];
        }

        public function ThreeDRedirect()
        {
            $content = Session::get("Garanti3DRedirect",true);

            if($content)
                echo $content;
            else
                echo "3D yönlendirilemedi.";

            Session::delete("Garanti3DRedirect");
        }

        public function callback()
        {
            if(!$_POST) exit('POST değeri dönmedi');

            if (!$this->check_hash($this->config['settings']["store_key"], $_POST, $_POST["hashparams"], $_POST["hash"]))
            {
                Modules::save_log("Payment",__CLASS__,"callback-1",false,$_POST, "Güvenlik Hatası: İletilen veriler sorunlu olduğu için, işleme devam edilemiyor.");
                return  ['status' => "error"];
            }

            $invoiceId = $_POST["oid"];
            $transactionId = $_POST["hostrefnum"];

            $checkout       = $this->get_checkout($invoiceId);

            if(!$checkout)
            {
                $this->error = "Checkout is incorrect";
                Modules::save_log("Payment",__CLASS__,"callback-2",false,$_POST, "Checkout is incorrect");
                return false;
            }

            $this->set_checkout($checkout);


            $status = "Unsuccessful";
            if ($_POST["response"] == "Approved") $status = "Successful";

            if ($invoiceId) {
                if ($_POST["response"] == "Approved") {

                    if($transactionId)
                    {
                        $findID = Invoices::search_pmethod_msg($transactionId);
                        if($findID)
                        {
                            return [
                                'status' => 'error',
                                'message' => "Daha once bu islem nuamarasi ile siparis alinmistir",
                            ];
                        }
                    }

                    return [
                        'status' => 'successful',
                        'message' => [
                            'Transaction ID' => $transactionId,
                        ],
                    ];
                }
            }

            Modules::save_log("Payment",__CLASS__,"callback-3",false,$_POST, "Response not approved");

            return [
                'status' => "error",
            ];
        }

        public function convert_curr_code($currency='')
        {
            $find = ["AED", "AFN", "ALL", "AMD", "ANG", "AOA", "ARS", "AUD", "AWG", "AZN", "BAM", "BBD", "BDT", "BGN", "BHD", "BIF", "BMD", "BND", "BOB", "BOV", "BRL", "BSD", "BTN", "BWP", "BYR", "BZD", "CAD", "CDF", "CHE", "CHF", "CHW", "CLF", "CLP", "CNH", "CNY", "COP", "COU", "CRC", "CUC", "CUP", "CVE", "CZK", "DJF", "DKK", "DOP", "DZD", "EGP", "ERN", "ETB", "EUR", "FJD", "FKP", "GBP", "GEL", "GHS", "GIP", "GMD", "GNF", "GTQ", "GYD", "HKD", "HNL", "HRK", "HTG", "HUF", "IDR", "ILS", "INR", "IQD", "IRR", "ISK", "JMD", "JOD", "JPY", "KES", "KGS", "KHR", "KMF", "KPW", "KRW", "KWD", "KYD", "KZT", "LAK", "LBP", "LKR", "LRD", "LSL", "LTL", "LYD", "MAD", "MDL", "MGA", "MKD", "MMK", "MNT", "MOP", "MRO", "MUR", "MVR", "MWK", "MXN", "MXV", "MYR", "MZN", "NAD", "NGN", "NIO", "NOK", "NPR", "NZD", "OMR", "PAB", "PEN", "PGK", "PHP", "PKR", "PLN", "PYG", "QAR", "RON", "RSD", "RUB", "RWF", "SAR", "SBD", "SCR", "SDG", "SEK", "SGD", "SHP", "SLL", "SOS", "SRD", "SSP", "STD", "SYP", "SZL", "THB", "TJS", "TMT", "TND", "TOP", "TRY", "TTD", "TWD", "TZS", "UAH", "UGX", "USD", "USN", "USS", "UYI", "UYU", "UZS", "VEF", "VND", "VUV", "WST", "XAF", "XAG", "XAU", "XBA", "XBB", "XBC", "XBD", "XCD", "XDR", "XFU", "XOF", "XPD", "XPF", "XPT", "XSU", "XTS", "XUA", "XXX", "YER", "ZAR", "ZMW", "ZWD"];
            $replace = ["784", "971", "008", "051", "532", "973", "032", "036", "533", "944", "977", "052", "050", "975", "048", "108", "060", "096", "068", "984", "986", "044", "064", "072", "974", "084", "124", "976", "947", "756", "948", "990", "152", "156", "156", "170", "970", "188", "931", "192", "132", "203", "262", "208", "214", "012", "818", "232", "230", "978", "242", "238", "826", "981", "936", "292", "270", "324", "320", "328", "344", "340", "191", "332", "348", "360", "376", "356", "368", "364", "352", "388", "400", "392", "404", "417", "116", "174", "408", "410", "414", "136", "398", "418", "422", "144", "430", "426", "440", "434", "504", "498", "969", "807", "104", "496", "446", "478", "480", "462", "454", "484", "979", "458", "943", "516", "566", "558", "578", "524", "554", "512", "590", "604", "598", "608", "586", "985", "600", "634", "946", "941", "643", "646", "682", "090", "690", "938", "752", "702", "654", "694", "706", "968", "728", "678", "760", "748", "764", "972", "934", "788", "776", "949", "780", "901", "834", "980", "800", "840", "997", "998", "940", "858", "860", "937", "704", "548", "882", "950", "961", "959", "955", "956", "957", "958", "951", "960", "Nil", "952", "964", "953", "962", "994", "963", "965", "999", "886", "710", "967", "932"];
            return str_replace($find, $replace, $currency);
        }

        public function string_to_ascii($str='')
        {
            $from   = ["ş", "Ş", "ı", "ü", "Ü", "ö", "Ö", "ç", "Ç", "ş", "Ş", "ı", "ğ", "Ğ", "İ", "ö", "Ö", "Ç", "ç", "ü", "Ü"];
            $to     = ["s", "S", "i", "u", "U", "o", "O", "c", "C", "s", "S", "i", "g", "G", "I", "o", "O", "C", "c", "u", "U"];
            $str    = str_replace($from, $to, $str);
            return $str;
        }

        public function check_hash($storekey, $result, $responseHashparams, $responseHash)
        {
            $isValidHash = false;
            if ($responseHashparams !== NULL && $responseHashparams !== "") {
                $digestData = "";
                $paramList = explode(":", $responseHashparams);
                foreach ($paramList as $param) {
                    $value = $result[strtolower($param)];
                    if ($value == NULL) {
                        $value = "";
                    }
                    $digestData .= $value;
                }
                $digestData .= $storekey;
                $hashCalculated = base64_encode(pack("H*", sha1($digestData)));
                if ($responseHash == $hashCalculated) {
                    $isValidHash = true;
                }
            }
            return $isValidHash;
        }
    }