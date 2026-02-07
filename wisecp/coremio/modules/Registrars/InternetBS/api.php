<?php
    class InternetBS_API {
        private $test_mode      = false;
        public  $error          = NULL;
        public $hostname_real   = 'https://api.internet.bs/';
        public $hostname_test   = 'https://77.247.183.107/';
        public $api_key         = NULL;
        public $password        = NULL;


        public function set_credentials($api_key,$password='',$test_mode=false)
        {
            $this->api_key          = $api_key;
            $this->password         = $password;
            $this->test_mode        = $test_mode;
        }

        public function call($endpoint='',$data = [],$format='JSON',$dontLog=false)
        {
            if($this->test_mode)
                $url = $this->hostname_test;
            else
                $url = $this->hostname_real;

            if(!isset($data['apikey']))
            {
                $data['apikey']     = $this->api_key;
                $data['password']   = $this->password;
            }
            $data["ResponseFormat"] = $format;


            $postData = http_build_query($data);


            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url.$endpoint);

            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_USERAGENT, "IBS WISECP module V1.0");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            $result = curl_exec($ch);

            $this->error = curl_error($ch);

            curl_close($ch);

            if($format == "JSON")
                $result = Utility::jdecode($result,true);
            elseif($format == "XML")
                $result = Utility::xdecode($result,true);
            else
                $result = (($result === false) ? false : $this->parseResult($result));

            if(isset($data["apikey"])) unset($data["apikey"]);
            if(isset($data["password"])) unset($data["password"]);

            if(!$dontLog) Modules::save_log("Registrars","InternetBS",$endpoint,$data,$result);

            if(isset($result["status"]) && $result["status"] == "FAILURE" && $endpoint != "Domain/Check")
            {
                $this->error = $result["message"] ?? 'Error Problem';
                return false;
            }

            return $result;
        }

        private function parseResult($data) {
            $result = array();
            $arr = explode("\n", $data);
            foreach ($arr as $str) {
                list ($varName, $value) = explode("=", $str, 2);
                $varName = trim($varName);
                $value = trim($value);
                $result [$varName] = $value;
            }
            return $result;
        }

    }