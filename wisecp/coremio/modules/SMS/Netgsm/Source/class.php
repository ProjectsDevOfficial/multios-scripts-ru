<?php
    class NetgsmLibrary {
        public $username; // This username
        public $password; // This Password
        public $error;	// This error message output
        public $timeout = 5; // This timeout
        public $rid; 		// This Report ID
        private $url;		// This Data URL
        public $otp        = false;


        public function __construct($username, $password) {
            $this->username = $username;
            $this->password = $password;
            $this->url 	  = "https://api.netgsm.com.tr/";
        }

        /*
        * variable: site_url (string value)
        * variable: post_data: (array and string value)
        @return: (string value)
        */
        private function curl_use ($site_url,$post_data){
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL,$site_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS,$post_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            $result = curl_exec($ch);
            if(curl_errno($ch)) $this->error = curl_error($ch);
            curl_close($ch);
            return $result;
        }


        public function Submit($title = NULL,$message = NULL,$number = 0,$kara_liste = NULL){
            $this->timeout = 200;
            if(is_array($number)){
                $numbers = $number;
            }else{
                $numbers = array($number);
            }

            $xml_data  = '<?xml version="1.0" encoding="UTF-8"?>'.
                '<mainbody>'.
                '<header>'.
                ($this->otp ? '' : '<company>Netgsm</company>').
                '<usercode>'.$this->username.'</usercode>'.
                '<password>'.$this->password.'</password>'.
                '<startdate></startdate>'.
                '<stopdate></stopdate>'.
                '<type>1:n</type>'.
                '<msgheader>'.$title.'</msgheader>'.
                '</header>'.
                '<body><msg><![CDATA['.str_replace(PHP_EOL,"\\n",$message).']]></msg>';
            foreach($numbers AS $number){
                $xml_data .= '<no>'.$number.'</no>';
            }
            $xml_data .= '</body></mainbody>';

            $url = $this->url.($this->otp ? 'sms/send/otp' : 'sms/send/xml');
            $outcome = $this->curl_use($url,$xml_data);

            Modules::save_log("SMS","Netgsm",$url,htmlspecialchars($xml_data),htmlspecialchars($outcome),$this->error);

            if($outcome){

                if($this->otp)
                {
                    $outcome = str_replace('<main><xml>','</main></xml>', $outcome);
                    $response = Utility::xdecode($outcome);

                    $code = (string) ($response->main->code ?? 1);
                    $jobID = (string) ($response->main->jobID ?? 0);

                    if($code == "0")
                    {
                        $this->rid = $jobID;
                        return true;
                    }
                    else
                    {
                        $this->error = "Bir hata oluştu: ".$code;
                        return false;
                    }
                }
                else
                {
                    $exp    = explode(" ",$outcome);
                    if($exp[0] == "00"){
                        $this->rid = $exp[1];
                        return true;
                    }elseif($exp[0] == "30"){
                        $this->error = "Geçersiz kullanıcı adı , şifre veya kullanıcının API erişim izni yok.";
                        return false;
                    }elseif($exp[0] == "40"){
                        $this->error = "Mesaj başlığınızın (gönderici adınızın) sistemde tanımlı değildir. ::".$title.":: ";
                        return false;
                    }else{
                        $this->error = "Bilinmeyen hata : ".$exp[0];
                        return false;
                    }
                }
            }else{
                if(!$this->error) $this->error = "Boş cevap döndü.";
                return false;
            }

        }

        public function Balance(){

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.netgsm.com.tr/balance',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode(array(
                    'usercode' => $this->username,
                    'password' => $this->password,
                    'stip'     => 1
                )),
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
            ));

            $response = curl_exec($curl);
            $error    = curl_error($curl);
            curl_close($curl);

            if($error)
            {
                $this->error = $error;
                return false;
            }

            $response = json_decode($response, true);

            if($response){
                $code       = $response["code"];
                $balances   = $response["balance"];
                $balance    = 0;
                $error_messages = [
                    30 => "Geçersiz kullanıcı adı , şifre veya kullanıcı API erişim izini bulunmuyor.",
                    60 => "Hesabınızda tanımlı paket veya kampanyanız bulunmamaktadır.",
                    70 => "Hatalı sorgulama. Gönderdiğiniz parametrelerden birisi hatalı veya zorunlu alanlardan birisi eksik.",
                ];
                if(isset($error_messages[$code])){
                    $this->error = $error_messages[$code];
                    return false;
                }

                foreach($balances AS $item){
                    if($item["balance_name"] == "Adet SMS") $balance = str_replace(",","",$item["amount"] ?? 0);
                }

                return $balance;
            }
            else
            {
                if(!$this->error) $this->error = "Geçersiz yanıt döndü.";
                return false;
            }
        }

        public function ReportLook($rid){
            $data = [
                'usercode' => $this->username,
                'password' => $this->password,
                'bulkid'   => $rid,
                'type'     => 0,
                'version'  => 2,
            ];
            $outcome = $this->curl_use($this->url.'httpbulkrapor.asp?'.http_build_query($data),false);

            if($outcome){
                $list       = explode("<br>",$outcome);
                $iletilen   = [];
                $bekleyen   = [];
                $hatali     = [];

                foreach($list AS $row){
                    if($row){
                        $split  = explode(" ",$row);
                        $durum  = $split[1];
                        $numara = $split[0];
                        if($durum == 0)
                            $bekleyen[] = $numara;
                        elseif($durum == 1)
                            $iletilen[] = $numara;
                        elseif($durum != 100)
                            $hatali[] = $numara;
                    }
                }
                return [
                    'iletilen' => $iletilen,
                    'bekleyen' => $bekleyen,
                    'hatali'   => $hatali,
                ];
            }else{
                if(!$this->error) $this->error = "Sonuç boş döndü.";
                return false;
            }

            //return $outcome;
        }
    }