<?php
    class GoDaddy_API {
        private $test_mode      = false;
        private $username       = NULL;
        private $password       = NULL;
        public  $error          = NULL;
        public $version         = 'v1';

        function __construct($test_mode=false){
            $this->test_mode    = $test_mode;
        }

        public function set_credentials($username='',$password=NULL){
            $this->username      = $username;
            $this->password      = $password;
        }

        public function call($endpoint = '',$params=[],$method='GET')
        {
            if($method == 'GET')
            {
                $queries    = $params;
                $params     = [];
            }
            elseif(isset($params['queries']))
            {
                $queries = $params['queries'];
                unset($params['queries']);
            }
            else
                $queries = [];

            if($queries)
                $queries = http_build_query(array_map(function ($value){
                    if ($value === true) return 'true';
                    if ($value === false) return 'false';
                    return $value;
                }, $queries));


            $curl = curl_init();

            $url            = 'https://api.ote-godaddy.com/';

            if($this->test_mode)
                $url       = 'https://api.ote-godaddy.com/';
            else
                $url = 'https://api.godaddy.com/';



            $url .= $this->version.'/';

            $url .= $endpoint;

            if($queries) $url .= "?".$queries;

            $authorization  = ['Authorization: sso-key '.$this->username.':'.$this->password];
            $post_fields    = '';

            if($method != "GET" && $params)
            {
                $authorization[] = 'Content-Type: application/json';
                $post_fields = Utility::jencode($params);
            }


            curl_setopt_array($curl,[
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $authorization,
                CURLOPT_POSTFIELDS => $post_fields,
            ]);

            $response   = curl_exec($curl);
            $error      = curl_error($curl);
            curl_close($curl);

            Modules::save_log("Registrars","GoDaddy",$method.' / '.$endpoint,[
                'queries'   => $queries,
                'data'      => $params,
            ],$response);


            if($error)
            {
                $this->error = $error;
                return false;
            }

            $haveResponse = ($method != "PATCH");

            if(!$response && $haveResponse)
            {
                $this->error = "Couldn't get a response from the API Provider";
                return false;
            }

            $response_x         = trim($response);
            $response           = json_decode($response_x,true);

            if(!$response && $response_x != "[]" && $haveResponse)
            {
                $this->error = "The response from the API could not be resolved.";
                return false;
            }

            if(isset($response['code']) && isset($response['message']))
            {
                if($response['code'] && $response['message'])
                {
                    $this->error = $response["code"].' : '.$response['message'];
                    return false;
                }
            }

            return $response;
        }

    }