<?php
    class Dynadot_API {
        private $test_mode      = false;
        private $key            = NULL;
        public  $error          = NULL;

        function __construct($test_mode=false){
            $this->test_mode    = $test_mode;
        }

        public function set_credentials($key=''){
            $this->key      = $key;
        }

        public function call($command,$params=[])
        {

            $params['command']          = $command;
            $params['key']              = $this->key;

            $queries = http_build_query(array_map(function ($value){
                if ($value === true) return 'true';
                if ($value === false) return 'false';
                return $value;
            }, $params));


            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.dynadot.com/api3.json?'.$queries,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
            ));

            $response       = curl_exec($curl);
            $error          = curl_error($curl);

            curl_close($curl);

            if($error)
            {
                $this->error = $error;
                return false;
            }

            if(!$response)
            {
                $this->error = "Couldn't get a response from the API";
                return false;
            }

            $response           = json_decode($response,true);

            Modules::save_log("Registrars","Dynadot",$command,$params,$response);

            if(!$response)
            {
                $this->error = "The response from the API could not be resolved.";
                return false;
            }

            $cres   = ucfirst($command).'Response';
            $xres   = 'Response';

            foreach([$cres,$xres] AS $res)
            {
                if(isset($response[$res]['ResponseCode']))
                {
                    if($response[$res]['ResponseCode'] != "0")
                    {
                        $this->error = $response[$res]['Error'];
                        return false;
                    }
                }
            }

            return $response;

        }

    }