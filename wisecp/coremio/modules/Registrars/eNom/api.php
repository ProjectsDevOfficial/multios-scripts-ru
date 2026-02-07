<?php
    class eNom_API {
        private $test_mode      = false;
        private $username       = NULL;
        private $password       = NULL;
        public  $error          = NULL;

        function __construct($test_mode=false){
            $this->test_mode    = $test_mode;
        }

        public function set_credentials($username='',$password=NULL){
            $this->username      = $username;
            $this->password      = $password;
        }

        public function call($params=[],$decode=true,$dontLog=false)
        {
            $params["UID"]      = $this->username;
            $params["PW"]       = $this->password;
            $params["responsetype"] = "XML";

            $this->error = NULL;

            $url        = "http://reseller".($this->test_mode ? 'test' : '').".enom.com/interface.asp?";

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url.http_build_query($params),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
            ));

            $response = curl_exec($curl);

            curl_close($curl);


            if(!$dontLog) Modules::save_log("Registrars","eNom",$params["command"] ?? ($params["Command"] ?? $url),$params,htmlentities($response));

            if($response && $decode)
            {
                $response         = Utility::xdecode($response);
                if($response->ErrCount > 0)
                {
                    $this->error = implode(", ",(array) $response->errors);

                    return false;
                }
            }



            return $response;
        }

    }