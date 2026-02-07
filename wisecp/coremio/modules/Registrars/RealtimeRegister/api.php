<?php
    class RealtimeRegister_API {
        private $test_mode      = false;
        public  $error          = NULL;
        public $hostname_real   = 'https://api.yoursrs.com/v2/';
        public $hostname_test   = 'https://api.yoursrs-ote.com/v2/';
        public $api_key         = NULL;
        public $password        = NULL;
        public $header          = NULL;
        public $status_code     = 200;

        public function set_credentials($api_key,$password='',$test_mode=false)
        {
            $this->api_key          = $api_key;
            $this->password         = $password;
            $this->test_mode        = $test_mode;
        }

        public function call($endpoint='',$data = [],$method='GET',$dontLog=false)
        {

            if(!function_exists("Realtimeregister_header_read"))
            {
                function Realtimeregister_header_read($ch, $header)
                {
                    $GLOBALS["header"] = $header;

                    return strlen($header);
                }
            }


            if($this->test_mode)
                $url = $this->hostname_test;
            else
                $url = $this->hostname_real;


            if(isset($data["queries"]) && $data["queries"])
            {
                $endpoint .=  "?".http_build_query($data["queries"]);
                unset($data["queries"]);
            }


            if($data) $postData = Utility::jencode($data);


            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url.$endpoint);



            curl_setopt($ch, CURLOPT_HEADER, 0);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            curl_setopt($ch, CURLOPT_CUSTOMREQUEST,$method);

            curl_setopt($ch, CURLOPT_HEADERFUNCTION, 'RealtimeRegister_header_read');

            curl_setopt($ch, CURLOPT_HTTPHEADER,[
                'Content-Type: application/json',
                'Authorization: ApiKey '.$this->api_key,
            ]);

            if(isset($postData) && $postData)
            {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            }

            $result_x = curl_exec($ch);

            $this->status_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $this->header       = $GLOBALS["header"];


            $this->error = curl_error($ch);

            curl_close($ch);

            $result = Utility::jdecode($result_x,true);


            if(isset($result["errors"]) && $result["errors"])
            {
                $errors = [];
                foreach($result["errors"] AS $err)
                {
                    foreach($err AS $k => $v) $errors[] = "*".$k."*: ".implode(" ",$v);
                }

                $this->error = implode(", ",$errors);
                $result = false;
            }
            elseif(isset($result["violations"]) && $result["violations"])
            {
                $errors = [];
                foreach($result["violations"] AS $v)
                    $errors[] = "*".$v["field"]."* ".$v["message"];
                $this->error = implode(", ",$errors);
                $result = false;
            }
            elseif(isset($result['type']) && isset($result['message']) && $result['message'])
            {
                $this->error = $result['message'] ?? 'none';
                $result = false;
            }
            elseif($this->status_code == 401) $this->error = "Authorization Failed/ Incorrect Api Key";
            elseif(!in_array($this->status_code,[200,201,202])) $this->error = 'Status Code: '.$this->status_code;



            if(!$dontLog) Modules::save_log("Registrars","RealtimeRegister",$method." / ".$url.$endpoint,$data,$result_x ? $result_x : 'Status Code: '.$this->status_code,$result);




            return $result;
        }


    }