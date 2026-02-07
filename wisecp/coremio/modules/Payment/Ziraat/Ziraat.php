<?php
    class Ziraat extends PaymentGatewayModule
    {

        function __construct()
        {
            $this->name             = __CLASS__;
            $this->standard_card    = true;

            $this->define_function("ThreeDSecure","ThreeDSecure");

            parent::__construct();
        }

        public function config_fields()
        {
            return [
                'pox_request_url'       => [
                    'name'              => "POX Request URL",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["pox_request_url"] ?? 'https://sanalpos.ziraatbank.com.tr/v4/v3/Vposreq.aspx',
                ],

                '3d_url'                => [
                    'name'              => "3D Secure Enrollment URL",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["3d_url"] ?? 'https://mpi.ziraatbank.com.tr/Enrollment.aspx',
                ],

                'merchant_id'          => [
                    'name'              => "Merchant ID (Üye İşyeri Numarası)",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["merchant_id"] ?? '',
                ],

                'merchant_password'     => [
                    'name'              => "Merchant Password (Üye İşyeri Şifresi)",
                    'description'       => "",
                    'type'              => "password",
                    'value'             => $this->config["settings"]["merchant_password"] ?? '',
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
            $invoiceid          = $this->checkout_id;
            $amount             = $params['amount'];
            $currency           = $params['currency'];

            $cardtype           = $params['type'];
            $cardnumber         = $params['num'];
            $cccvv              = $params['cvc'];
            $son_kullanim_ay    = $params['expiry_m'];
            $son_kullanim_yil   = $params['expiry_y'];

            if (stristr($cardtype,'master'))
                $cardtype_number = '200';
            else
                $cardtype_number = '100';

            $amount                 = round($amount, 2);
            $amount                 = number_format($amount, 2, '.', '');
            $mpiServiceUrl          = $this->config['settings']['3d_url'];
            $krediKartiNumarasi     = $cardnumber;
            $sonKullanmaTarihi      = $son_kullanim_yil . $son_kullanim_ay;
            $kartTipi               = $cardtype_number;
            $tutar                  = $amount;
            $paraKodu               = $this->convert_curr_code($currency);
            $taksitSayisi           = $params['installment'];
            $islemNumarasi          = $invoiceid . '_' . DateManager::Now('YmdHis');
            $uyeIsyeriNumarasi      = $this->config['settings']['merchant_id'];
            $uyeIsYeriSifresi       = $this->config['settings']['merchant_password'];
            $SuccessURL             = $this->links["callback"]."?oid=".$invoiceid;
            $FailureURL             = $this->links["callback"]."?oid=".$invoiceid;
            $ekVeri                 = $krediKartiNumarasi . '--' . $cccvv . '--' . $amount;
            if($taksitSayisi < 1) $taksitSayisi = '';


            $postData               = "Pan=$krediKartiNumarasi&ExpiryDate=$sonKullanmaTarihi&PurchaseAmount=$tutar&Currency=$paraKodu&BrandName=$kartTipi&VerifyEnrollmentRequestId=$islemNumarasi&SessionInfo=$ekVeri&MerchantId=$uyeIsyeriNumarasi&MerchantPassword=$uyeIsYeriSifresi&SuccessUrl=$SuccessURL&FailureUrl=$FailureURL&InstallmentCount=$taksitSayisi";


            $ch                     = curl_init();
            curl_setopt($ch, CURLOPT_URL, $mpiServiceUrl);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type" => "application/x-www-form-urlencoded"));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

            $resultXml = curl_exec($ch);
            curl_close($ch);

            $resultXml = str_replace($krediKartiNumarasi, Filter::censored($krediKartiNumarasi), $resultXml);

            $sonuc_3d = $this->read_result($resultXml);
            Modules::save_log("Payment",__CLASS__,$mpiServiceUrl,$postData,htmlentities($resultXml),$sonuc_3d);

            if($sonuc_3d["Status"] == "Y")
            {
                $hidden_dizi = array('PaReq' => $sonuc_3d['PaReq'], 'TermUrl' => $sonuc_3d['TermUrl'], 'MD' => $sonuc_3d['MerchantData'],);
                $hidden_text = '';
                foreach ($hidden_dizi as $hidden_key => $hidden_value) $hidden_text .= '<input type="hidden" name="' . $hidden_key . '" value="' . $hidden_value . '" />';

                Session::set("Ziraat3D",'
			<form action="' . $sonuc_3d['ACSUrl'] . '" method="post" id="RedirectForm">
				' . $hidden_text . '
				<noscript>
					<div class="errorbox"><b>JavaScript is currently disabled or is not supported by your
						browser.</b><br />Please click the continue button to proceed with the processing of your
						transaction.
					</div>
					<p align="center"><input type="submit" value="' . $this->l_payNow . '" /></p>
				</noscript>
				<script type="text/javascript">
				    document.getElementById("RedirectForm").submit();
				</script>
			</form>
		',true);
                Session::set("ZiraatInfo",Utility::jencode($params),true);

                return [
                    "status"        => "redirect",
                    "redirect"      => $this->links["ThreeDSecure"],
                ];
            }


            return [
                "status"        => "error",
                "message"       => "Bir sorun oluştu",
            ];
        }

        public function ThreeDSecure()
        {
            $content = Session::get("Ziraat3D",true);

            if($content)
                echo $content;
            else
                echo "3D yönlendirilemedi.";

            Session::delete("Ziraat3D");
        }

        public function callback()
        {
            $ziraatInfo = Session::get("ZiraatInfo",true);
            $ziraatInfo = Utility::jdecode($ziraatInfo,true);
            Session::delete("ZiraatInfo");

            $oid    = $ziraatInfo["checkout_id"] ?? 0;

            $status = '0';
            if($oid)
            {
                $checkout           = $this->get_checkout($oid);

                if(!$checkout)
                {
                    $this->error = 'Checkout not found';
                    return false;
                }

                $currency       = $ziraatInfo['currency'];
                $fatura_amount  = round($ziraatInfo["amount"],2);
                $cvv            = $ziraatInfo["cvc"];
                $card_num       = $ziraatInfo["num"];

                $Status = $_POST['Status'];
                $VerifyEnrollmentRequestId = $_POST['VerifyEnrollmentRequestId'];
                $Xid = $_POST['Xid'];
                $Eci = $_POST['Eci'];
                $Cavv = $_POST['Cavv'];

                $Pan = $_POST['Pan'];
                $Expiry = $_POST['Expiry'];
                $son_kullanim_yil = substr($Expiry, 0, 2);
                $son_kullanim_ay = substr($Expiry, -2);
                $PurchAmount = $_POST['PurchAmount'];

                if ($Status == 'Y' && $VerifyEnrollmentRequestId != '' && $Xid != '')
                {
                    $PurchAmount /= 100;
                    if (round($PurchAmount,2) >= $fatura_amount && $card_num == $Pan)
                    {
                        //otorizasyon tamamlanıyor
                        $PostUrl        = $this->config['settings']["pox_request_url"];
                        $IsyeriNo       = $this->config['settings']["merchant_id"];
                        $IsyeriSifre    = $this->config['settings']["merchant_password"];
                        $KartNo         = $Pan;
                        $KartAy         = $son_kullanim_ay;
                        $KartYil        = '20'.$son_kullanim_yil;
                        $KartCvv        = $cvv;
                        $Tutar          = $PurchAmount;
                        $SiparID        = $VerifyEnrollmentRequestId;
                        $IslemTipi      = 'Sale';
                        $TutarKodu      = $this->convert_curr_code($currency);

                        $PosXML ='prmstr=<?xml version="1.0" encoding="utf-8"?>
				<VposRequest>
				  <MerchantId>'.$IsyeriNo.'</MerchantId>
				  <Password>'.$IsyeriSifre.'</Password>
				  <TransactionType>'.$IslemTipi.'</TransactionType>
				  <TransactionId>'.$SiparID.'</TransactionId>
				  <CurrencyAmount>'.$Tutar.'</CurrencyAmount>
				  <CurrencyCode>'.$TutarKodu.'</CurrencyCode>
				  <Pan>'.$KartNo.'</Pan>
				  <Cvv>'.$KartCvv.'</Cvv>
				  <Expiry>'.$KartYil.$KartAy.'</Expiry>
				  <ClientIp>'.UserManager::GetIP().'</ClientIp>
				  <ECI>'.$Eci.'</ECI>
				  <CAVV>'.$Cavv.'</CAVV>
				  <TransactionDeviceSource>0</TransactionDeviceSource>
				</VposRequest>';

                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL,$PostUrl);
                        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
                        curl_setopt($ch, CURLOPT_POST, 1);
                        curl_setopt($ch, CURLOPT_POSTFIELDS,$PosXML);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 5);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 59);
                        $vpos_sonuc_xml = curl_exec($ch);
                        curl_close($ch);
                        $vpos_sonuc = simplexml_load_string($vpos_sonuc_xml);

                        $PosXML = str_replace($KartNo, Filter::censored($KartNo), $PosXML);
                        Modules::save_log("Payment",__CLASS__,"Otorizasyon",htmlentities($PosXML),htmlentities($vpos_sonuc_xml),$vpos_sonuc);

                        if($vpos_sonuc->ResultCode == '0000' && $vpos_sonuc->AuthCode != '')
                        {
                            $status = '1';
                        }else{
                            $ResponseMessage = $vpos_sonuc->ResultDetail;
                            $status = '0';
                        }
                    }
                    else
                    {
                        $ResponseMessage = 'Ödeme tutarı hatalı :  '.round($PurchAmount,2).' - '.$fatura_amount;
                        $status = '0';
                    }
                }
                else
                {
                    $ResponseMessage = '3D Güvenliği Doğrulanamadı';
                    $status = '0';
                }
            }
            else
                $ResponseMessage = 'Checkout ID not found';


            if($status)
            {
                $this->set_checkout($checkout);

                return [
                    'status' => "successful"
                ];
            }

            Modules::save_log("Payment",__CLASS__,"Callback",false,htmlentities($vpos_sonuc_xml ?? ''),$ResponseMessage);

            return [
                'status' => "error",
                'message' => $ResponseMessage
            ];
        }

        public function convert_curr_code($currency='')
        {
            $find = ["AED", "AFN", "ALL", "AMD", "ANG", "AOA", "ARS", "AUD", "AWG", "AZN", "BAM", "BBD", "BDT", "BGN", "BHD", "BIF", "BMD", "BND", "BOB", "BOV", "BRL", "BSD", "BTN", "BWP", "BYR", "BZD", "CAD", "CDF", "CHE", "CHF", "CHW", "CLF", "CLP", "CNH", "CNY", "COP", "COU", "CRC", "CUC", "CUP", "CVE", "CZK", "DJF", "DKK", "DOP", "DZD", "EGP", "ERN", "ETB", "EUR", "FJD", "FKP", "GBP", "GEL", "GHS", "GIP", "GMD", "GNF", "GTQ", "GYD", "HKD", "HNL", "HRK", "HTG", "HUF", "IDR", "ILS", "INR", "IQD", "IRR", "ISK", "JMD", "JOD", "JPY", "KES", "KGS", "KHR", "KMF", "KPW", "KRW", "KWD", "KYD", "KZT", "LAK", "LBP", "LKR", "LRD", "LSL", "LTL", "LYD", "MAD", "MDL", "MGA", "MKD", "MMK", "MNT", "MOP", "MRO", "MUR", "MVR", "MWK", "MXN", "MXV", "MYR", "MZN", "NAD", "NGN", "NIO", "NOK", "NPR", "NZD", "OMR", "PAB", "PEN", "PGK", "PHP", "PKR", "PLN", "PYG", "QAR", "RON", "RSD", "RUB", "RWF", "SAR", "SBD", "SCR", "SDG", "SEK", "SGD", "SHP", "SLL", "SOS", "SRD", "SSP", "STD", "SYP", "SZL", "THB", "TJS", "TMT", "TND", "TOP", "TRY", "TTD", "TWD", "TZS", "UAH", "UGX", "USD", "USN", "USS", "UYI", "UYU", "UZS", "VEF", "VND", "VUV", "WST", "XAF", "XAG", "XAU", "XBA", "XBB", "XBC", "XBD", "XCD", "XDR", "XFU", "XOF", "XPD", "XPF", "XPT", "XSU", "XTS", "XUA", "XXX", "YER", "ZAR", "ZMW", "ZWD"];
            $replace = ["784", "971", "008", "051", "532", "973", "032", "036", "533", "944", "977", "052", "050", "975", "048", "108", "060", "096", "068", "984", "986", "044", "064", "072", "974", "084", "124", "976", "947", "756", "948", "990", "152", "156", "156", "170", "970", "188", "931", "192", "132", "203", "262", "208", "214", "012", "818", "232", "230", "978", "242", "238", "826", "981", "936", "292", "270", "324", "320", "328", "344", "340", "191", "332", "348", "360", "376", "356", "368", "364", "352", "388", "400", "392", "404", "417", "116", "174", "408", "410", "414", "136", "398", "418", "422", "144", "430", "426", "440", "434", "504", "498", "969", "807", "104", "496", "446", "478", "480", "462", "454", "484", "979", "458", "943", "516", "566", "558", "578", "524", "554", "512", "590", "604", "598", "608", "586", "985", "600", "634", "946", "941", "643", "646", "682", "090", "690", "938", "752", "702", "654", "694", "706", "968", "728", "678", "760", "748", "764", "972", "934", "788", "776", "949", "780", "901", "834", "980", "800", "840", "997", "998", "940", "858", "860", "937", "704", "548", "882", "950", "961", "959", "955", "956", "957", "958", "951", "960", "Nil", "952", "964", "953", "962", "994", "963", "965", "999", "886", "710", "967", "932"];
            return str_replace($find, $replace, $currency);
        }

        public function read_result($result)
        {
            $resultDocument = new DOMDocument();
            $resultDocument->loadXML($result);
            $statusNode = $resultDocument->getElementsByTagName("Status")->item(0);
            $status = "";
            if ($statusNode != null) $status = $statusNode->nodeValue;
            $PAReqNode = $resultDocument->getElementsByTagName("PaReq")->item(0);
            $PaReq = "";
            if ($PAReqNode != null) $PaReq = $PAReqNode->nodeValue;
            $ACSUrlNode = $resultDocument->getElementsByTagName("ACSUrl")->item(0);
            $ACSUrl = "";
            if ($ACSUrlNode != null) $ACSUrl = $ACSUrlNode->nodeValue;
            $TermUrlNode = $resultDocument->getElementsByTagName("TermUrl")->item(0);
            $TermUrl = "";
            if ($TermUrlNode != null) $TermUrl = $TermUrlNode->nodeValue;
            $MDNode = $resultDocument->getElementsByTagName("MD")->item(0);
            $MD = "";
            if ($MDNode != null) $MD = $MDNode->nodeValue;
            $messageErrorCodeNode = $resultDocument->getElementsByTagName("MessageErrorCode")->item(0);
            $messageErrorCode = "";
            if ($messageErrorCodeNode != null) $messageErrorCode = $messageErrorCodeNode->nodeValue;
            $result = array("Status" => $status, "PaReq" => $PaReq, "ACSUrl" => $ACSUrl, "TermUrl" => $TermUrl, "MerchantData" => $MD, "MessageErrorCode" => $messageErrorCode);
            return $result;
        }

    }