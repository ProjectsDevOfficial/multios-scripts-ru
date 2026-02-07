<?php
    class Akbank extends PaymentGatewayModule
    {
        private $pos_addresses      = [
            'test' => [
                '3D'                => "https://virtualpospaymentgatewaypre.akbank.com/securepay",
                '3D_PAY'            => "https://virtualpospaymentgatewaypre.akbank.com/securepay",
                'PAY_HOSTING'       => "https://virtualpospaymentgatewaypre.akbank.com/payhosting",
            ],
            'live'          => [
                '3D'                => "https://virtualpospaymentgateway.akbank.com/securepay",
                '3D_PAY'            => "https://virtualpospaymentgateway.akbank.com/securepay",
                'PAY_HOSTING'       => "https://virtualpospaymentgateway.akbank.com/payhosting",
            ],
        ];

        function __construct(){
            $this->name             = __CLASS__;

            $config     = include __DIR__.DS."config.php";

            $this->standard_card    = ($config["settings"]["type"] ?? '3D_PAY') != "PAY_HOSTING";

            $this->define_function("ThreeDRedirect","ThreeDRedirect");

            parent::__construct();
        }

        private function callRequest($url, $data, $method = 'POST')
        {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, $method == 'POST' ? 1 : 0);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            $response = curl_exec($ch);
            if(curl_errno($ch)){
                $this->error = curl_error($ch);
                return false;
            }
            return $response;
        }

        private function getRandomNumberBase16 ($n = 128)
        {
            $characters = '0123456789ABCDEF';
            $randomString = '';

            for ($i = 0; $i < $n; $i++) {
                $index = rand(0, strlen($characters) - 1);
                $randomString .= $characters[$index];
            }

            return strtoupper($randomString);
        }

        private function calculate_hash($data='', $secretKey=''){
            $hash = hash_hmac('sha512', $data, $secretKey, true);
            return base64_encode($hash);
        }

        private function checkResponseHash($requestMap, $secretKey){
            $params = explode("+", $requestMap["hashParams"]);
            $builder = "";
            foreach($params as $param){
                $builder .= $requestMap[$param];
            }
            $hash = $this->calculate_hash($builder, $secretKey);
            if($requestMap["hash"] !== $hash){
                return false;
            }
            return true;
        }

        public function convert_curr_code($currency='')
        {
            $find = ["AED", "AFN", "ALL", "AMD", "ANG", "AOA", "ARS", "AUD", "AWG", "AZN", "BAM", "BBD", "BDT", "BGN", "BHD", "BIF", "BMD", "BND", "BOB", "BOV", "BRL", "BSD", "BTN", "BWP", "BYR", "BZD", "CAD", "CDF", "CHE", "CHF", "CHW", "CLF", "CLP", "CNH", "CNY", "COP", "COU", "CRC", "CUC", "CUP", "CVE", "CZK", "DJF", "DKK", "DOP", "DZD", "EGP", "ERN", "ETB", "EUR", "FJD", "FKP", "GBP", "GEL", "GHS", "GIP", "GMD", "GNF", "GTQ", "GYD", "HKD", "HNL", "HRK", "HTG", "HUF", "IDR", "ILS", "INR", "IQD", "IRR", "ISK", "JMD", "JOD", "JPY", "KES", "KGS", "KHR", "KMF", "KPW", "KRW", "KWD", "KYD", "KZT", "LAK", "LBP", "LKR", "LRD", "LSL", "LTL", "LYD", "MAD", "MDL", "MGA", "MKD", "MMK", "MNT", "MOP", "MRO", "MUR", "MVR", "MWK", "MXN", "MXV", "MYR", "MZN", "NAD", "NGN", "NIO", "NOK", "NPR", "NZD", "OMR", "PAB", "PEN", "PGK", "PHP", "PKR", "PLN", "PYG", "QAR", "RON", "RSD", "RUB", "RWF", "SAR", "SBD", "SCR", "SDG", "SEK", "SGD", "SHP", "SLL", "SOS", "SRD", "SSP", "STD", "SYP", "SZL", "THB", "TJS", "TMT", "TND", "TOP", "TRY", "TTD", "TWD", "TZS", "UAH", "UGX", "USD", "USN", "USS", "UYI", "UYU", "UZS", "VEF", "VND", "VUV", "WST", "XAF", "XAG", "XAU", "XBA", "XBB", "XBC", "XBD", "XCD", "XDR", "XFU", "XOF", "XPD", "XPF", "XPT", "XSU", "XTS", "XUA", "XXX", "YER", "ZAR", "ZMW", "ZWD"];
            $replace = ["784", "971", "008", "051", "532", "973", "032", "036", "533", "944", "977", "052", "050", "975", "048", "108", "060", "096", "068", "984", "986", "044", "064", "072", "974", "084", "124", "976", "947", "756", "948", "990", "152", "156", "156", "170", "970", "188", "931", "192", "132", "203", "262", "208", "214", "012", "818", "232", "230", "978", "242", "238", "826", "981", "936", "292", "270", "324", "320", "328", "344", "340", "191", "332", "348", "360", "376", "356", "368", "364", "352", "388", "400", "392", "404", "417", "116", "174", "408", "410", "414", "136", "398", "418", "422", "144", "430", "426", "440", "434", "504", "498", "969", "807", "104", "496", "446", "478", "480", "462", "454", "484", "979", "458", "943", "516", "566", "558", "578", "524", "554", "512", "590", "604", "598", "608", "586", "985", "600", "634", "946", "941", "643", "646", "682", "090", "690", "938", "752", "702", "654", "694", "706", "968", "728", "678", "760", "748", "764", "972", "934", "788", "776", "949", "780", "901", "834", "980", "800", "840", "997", "998", "940", "858", "860", "937", "704", "548", "882", "950", "961", "959", "955", "956", "957", "958", "951", "960", "Nil", "952", "964", "953", "962", "994", "963", "965", "999", "886", "710", "967", "932"];
            return str_replace($find, $replace, $currency);
        }

        public function config_fields()
        {
            return [
                'type'                      => [
                    'name'              => "Pos Tipi",
                    'description'       => "Pos tipi belirleyiniz.",
                    'type'              => "dropdown",
                    'value'             => $this->config["settings"]["type"] ?? '3D_PAY',
                    'options'           => [
                        "3D_PAY"            => "3D PAY",
                        "3D"                => "3D MODEL",
                        "PAY_HOSTING"       => "PAY HOSTING",
                    ],
                ],
                'merchantSafeId'               => [
                    'name'              => "Güvenli Üye İş Yeri numarası",
                    'description'       => "Bu bilgiye portaldeki Yönetim -> Üye İş Yeri İşlemleri -> Üye İş Yeri Bilgileri -> Güvenli İş Yeri numarasından ulaşabilirsiniz.",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["merchantSafeId"] ?? '',
                ],
                'terminalSafeId'                  => [
                    'name'             => "Güvenli Terminal Numarası",
                    'description'      => "Bu bilgiye portaldeki Yönetim -> Terminal İşlemleri -> Terminal Safe ID’den ulaşabilirsiniz.",
                    'type'             => "text",
                    'value'            => $this->config["settings"]["terminalSafeId"] ?? '',
                ],
                'secretKey'                  => [
                    'name'             => "Secret Key",
                    'description'      => "Secret Key bilgisine, portaldeki Yönetim -> Güvenlik Anahtarları menüsünden ulaşabilirsiniz.",
                    'type'             => "password",
                    'value'            => $this->config["settings"]["secretKey"] ?? '',
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

            $merchantSafeId = $this->config["settings"]["merchantSafeId"] ?? '';
            $terminalSafeId = $this->config["settings"]["terminalSafeId"] ?? '';
            $secretKey      = $this->config["settings"]["secretKey"] ?? '';
            $type           = $this->config["settings"]["type"] ?? '3D_PAY';
            $sandbox        = $this->config["settings"]["sandbox"] ?? false;
            $address                = $this->pos_addresses[$sandbox ? "test" : "live"][$type];

            if(function_exists("number_format"))
                $amount = number_format($params["amount"],2,'.','');
            else
                $amount =  sprintf("%.2f", $params["amount"]);

            $order_id               = $this->checkout_id."-".time();
            $lang                   = Bootstrap::$lang->clang == "tr" ? "TR" : "EN";
            $currency               = $this->convert_curr_code($params["currency"]);
            $installmentCount       = $params['installment'] ?: 1;
            $okUrl                  = $this->links["callback"];
            $failUrl                = $this->links["callback"];
            $cardNum                = $params['num'];
            $cardExpiry             = $params['expiry_m'].$params['expiry_y'];
            $cardCvc                = $params['cvc'];
            $requestDateTime        = DateManager::Now("Y-m-d\TH:i:s.v");
            $randomNumber           = $this->getRandomNumberBase16();
            $txnCode                = '3000';
            $version                = "1.00";
            $email                  = $this->clientInfo->email;

            /*
            $request = [
                'txnCode'                   => $txnCode,
                'requestDateTime'           => $requestDateTime,
                'randomNumber'              => $randomNumber,
                'terminal'                  => [
                    'merchantSafeId'            => $merchantSafeId,
                    'terminalSafeId'            => $terminalSafeId,
                ],
                'card'                      => [
                    'cardNumber'                => $cardNum,
                    'expiredDate'               => $cardExpiry,
                    'cvv2'                       => $cardCvc,
                ],
                'order'                     => [
                    'orderId'                   => $order_id,
                ],
                'transaction'               => [
                    'amount'                    => $amount,
                    'currencyCode'              => $currency,
                    'installCount'              => $installmentCount,
                ],
                'customer'                  => [
                    'emailAddress'              => $email,
                ],
                'lang'                      => $lang,
                'okUrl'                     => $okUrl,
                'failUrl'                   => $failUrl,
                'paymentModel'              => $type,
            ];
            */

            $request = [
                'txnCode'                   => $txnCode,
                'requestDateTime'           => $requestDateTime,
                'randomNumber'              => $randomNumber,
                'merchantSafeId'            => $merchantSafeId,
                'terminalSafeId'            => $terminalSafeId,
                'creditCard'                => $cardNum,
                'expiredDate'               => $cardExpiry,
                'cvv'                       => $cardCvc,
                'orderId'                   => $order_id,
                'amount'                    => $amount,
                'currencyCode'              => $currency,
                'installCount'              => $installmentCount,
                'emailAddress'              => $email,
                'lang'                      => $lang,
                'okUrl'                     => $okUrl,
                'failUrl'                   => $failUrl,
                'paymentModel'              => $type,
            ];

            $hashStr                = $type.$txnCode.$merchantSafeId.$terminalSafeId.$order_id.$lang.$amount.$currency.$installmentCount.$okUrl.$failUrl.$email.$cardNum.$cardExpiry.$cardCvc.$randomNumber.$requestDateTime;
            $request['hash']         = $this->calculate_hash($hashStr,$secretKey);

            $response               = Utility::HttpRequest($address,[
                'post' => $request,
                'timeout' => 20,
            ]);

            $request["creditCard"] = Filter::censored($cardNum);

            Modules::save_log("Payment",__CLASS__,$address,$request,htmlentities($response),Utility::$error);

            if(Utility::$error)
                return [
                    'status'        => 'error',
                    'message'       => Utility::$error,
                ];
            else
            {
                preg_match('/<span[^>]*id="message"[^>]*>(.*?)<\/span>/', $response, $matches);
                preg_match('/<span.*?id="message".*?>(.*?)<\/span>/s', $response, $matches2);
                preg_match('/<span.*?id="messageDetail".*?>(.*?)<\/span>/s', $response, $matches3);
                preg_match('/<input[^>]+name="responseMessage"[^>]+value="([^"]*)"/', $response, $matches4);


                if (isset($matches[1]) && !empty($matches[1]))
                {
                    $this->error = $matches[1];
                    if(isset($matches3[1]) && $matches3[1]) $this->error .= " - ".$matches3[1];

                    return false;
                }
                if(isset($matches2[1]) && !empty($matches2[1]))
                {
                    $this->error = $matches2[1];
                    if(isset($matches3[1]) && $matches3[1]) $this->error .= " -  ".$matches3[1];
                    return false;
                }
                if(isset($matches4[1]) && !empty($matches4[1]))
                {
                    $this->error = $matches4[1];
                    return false;
                }

                Session::set("Akbank3DRedirect",$response,true);

                return [
                    'status' => "redirect",
                    'redirect' => $this->links["ThreeDRedirect"],
                ];
            }
        }

        public function area($params = [])
        {
            $merchantSafeId = $this->config["settings"]["merchantSafeId"] ?? '';
            $terminalSafeId = $this->config["settings"]["terminalSafeId"] ?? '';
            $secretKey      = $this->config["settings"]["secretKey"] ?? '';
            $type           = $this->config["settings"]["type"] ?? '3D_PAY';
            $sandbox        = $this->config["settings"]["sandbox"] ?? false;
            $address                = $this->pos_addresses[$sandbox ? "test" : "live"][$type];

            if(function_exists("number_format"))
                $amount = number_format($params["amount"],2,'.','');
            else
                $amount =  sprintf("%.2f", $params["amount"]);

            $order_id               = $this->checkout_id."-".time();
            $lang                   = Bootstrap::$lang->clang == "tr" ? "TR" : "EN";
            $currency               = $this->convert_curr_code($params["currency"]);
            $installmentCount       = $params['installment'] ?: 1;
            $okUrl                  = $this->links["callback"];
            $failUrl                = $this->links["callback"];
            $requestDateTime        = DateManager::Now("Y-m-d\TH:i:s.v");
            $randomNumber           = $this->getRandomNumberBase16();
            $txnCode                = '1000';
            $email                  = $this->clientInfo->email;

            $hashItems  = $type . $txnCode . $merchantSafeId . $terminalSafeId . $order_id . $lang . $amount  . $currency . $installmentCount . $okUrl . $failUrl . $email . $randomNumber . $requestDateTime;
            $hash       = $this->calculate_hash($hashItems, $secretKey);

            return (<<<HTML
<form action="{$address}" method="POST" id="payForm">	
  <input type="hidden" name="paymentModel" value="PAY_HOSTING">
  <input type="hidden" name="txnCode" value="{$txnCode}">	
  <input type="hidden" name="merchantSafeId" value="{$merchantSafeId}">
  <input type="hidden" name="terminalSafeId" value="{$terminalSafeId}">
  <input type="hidden" name="orderId" value="{$order_id}">
  <input type="hidden" name="lang" value="{$lang}">
  <input type="hidden" name="amount" value="{$amount}" >
  <input type="hidden" name="currencyCode" value="{$currency}">
  <input type="hidden" name="installCount" value="{$installmentCount}">
  <input type="hidden" name="okUrl" value="{$okUrl}">
  <input type="hidden" name="failUrl" value="{$failUrl}">
  <input type="hidden" name="emailAddress" value="{$email}">
  <input type="hidden" name="randomNumber" value="{$randomNumber}">
  <input type="hidden" name="requestDateTime" value="{$requestDateTime}">
  <input type="hidden" name="hash" value="{$hash}">
</form>
<script>
document.addEventListener("DOMContentLoaded", function(event) {
    document.getElementById("payForm").submit();
});
</script>
HTML);

        }

        public function callback()
        {
            $merchantSafeId = $this->config["settings"]["merchantSafeId"] ?? '';
            $terminalSafeId = $this->config["settings"]["terminalSafeId"] ?? '';
            $secretKey      = $this->config["settings"]["secretKey"] ?? '';
            $type           = $this->config["settings"]["type"] ?? '3D_PAY';
            $sandbox        = $this->config["settings"]["sandbox"] ?? false;

            $orderId        = Filter::init("POST/orderId","route");
            $rrn            = Filter::init("POST/rrn");
            $responseCode   = Filter::init("POST/responseCode");
            $responseMsg    = Filter::init("POST/responseMessage");
            $orderId_split  = $orderId ? explode("-",$orderId) : [$orderId];
            $orderId        = $orderId_split[0];

            $check_hash     = $this->checkResponseHash($_POST,$secretKey);

            if(!$check_hash)
            {
                $this->error = "Hash calculation failed";
                Modules::save_log("Payment",__CLASS__,"callback",$_POST,$this->error);
                return false;
            }

            if ($responseCode != "VPS-0000") {
                if(DEVELOPMENT) echo '<pre>'.json_encode($_POST).'</pre>'.EOL;
                $this->error = $responseCode." = ".($responseMsg ?: "Transaction failed");
                return false;
            }


            $checkout = $this->get_checkout($orderId);

            if(!$checkout)
            {
                $this->error = "Checkout is incorrect";
                return false;
            }

            $this->set_checkout($checkout);

            return [
                'status' => "successful",
                'message' => [
                    'Transaction ID' => $rrn,
                ],
            ];

        }

        public function ThreeDRedirect()
        {
            $content = Session::get("Akbank3DRedirect",true);

            if($content)
                echo ($content);
            else
                echo "3D yönlendirilemedi.";

            #Session::delete("Akbank3DRedirect");
        }
    }