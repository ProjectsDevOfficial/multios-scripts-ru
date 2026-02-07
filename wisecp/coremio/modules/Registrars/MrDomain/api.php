<?php
    class MrDomain_API {
        private $test_mode      = false;
        private $username       = NULL;
        private $password       = NULL;
        public  $error          = NULL;
        public $hostname        = 'https://simple-api.dondominio.net';

        function __construct($hostname=NULL){
            $this->hostname    = $hostname;
        }

        public function set_credentials($username='',$password=NULL){
            $this->username      = $username;
            $this->password      = $password;
        }
        public function check_credentials()
        {
            $call = $this->call("/tool/hello");

            if(!isset($call['success']) || !$call['success']) return false;

            return true;
        }

        public function getHostname()
        {
            $url    = $this->hostname.":443";
            return $url;
        }

        public function call($endpoint='',$data = [],$method='POST',$dontLog=false)
        {

            if(!function_exists("MrDomain_header_read"))
            {
                function MrDomain_header_read($ch, $header)
                {
                    $GLOBALS["header"] = $header;

                    return strlen($header);
                }
            }

            $url        = $this->getHostname();
            $curl       = curl_init();

            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($curl,CURLOPT_TIMEOUT,20);


            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

            $data["apiuser"]        = $this->username;
            $data["apipasswd"]      = $this->password;
            $data["output-format"]  = "json";
            $data["output-pretty"]  = "true";

            if($method != "GET" && $data)
            {
                #$data           = Utility::jencode($data);
                $data           = http_build_query($data);
                curl_setopt($curl, CURLOPT_POSTFIELDS,$data);
            }


            // OPTIONS:
            curl_setopt($curl, CURLOPT_URL, $url.$endpoint.($method == "GET" ? "?".http_build_query($data) : ''));
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/x-www-form-urlencoded',
            ));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($curl, CURLOPT_HEADERFUNCTION, 'MrDomain_header_read');



            // EXECUTE:

            $result             = curl_exec($curl);
            $error              = curl_errno($curl) ? curl_error($curl) : '';
            $this->status_code  = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            /// Parse Result
            if($dc = Utility::jdecode($result,true)) $result = $dc;

            $this->header       = $GLOBALS["header"];
            if($error) $this->error = 'Unable to connect to the server:' . $this->hostname . ' - ' . $error;

            if(isset($result["errorCodeMsg"]) && $result["errorCodeMsg"])
            {
                if(isset($result['messages']) && $result['messages'])
                    $this->error = implode(" ,",$result['messages']);
                else
                    $this->error = $result["errorCodeMsg"];
            }



            if($this->error)
            {
                if(!$dontLog) Modules::save_log("Registrars","MrDomain",$method." / ".$url.$endpoint,$data,$result ? $result : $this->error,$result ? $this->error : '');
                return false;
            }

            if(!$dontLog) Modules::save_log("Registrars","MrDomain",$method." / ".$url.$endpoint,$data,$result);

            return $result;
        }

    }