<?php
    class HestiaApi
    {
        private $server;
        public $error;
        public $code;

        public function __construct($server)
        {
            $this->server = $server;
        }


        public function call($command='',$post = [],$return_format='text')
        {
            $post['user']       = $this->server["username"];
            if($this->server["access_hash"])
                $post['hash']   = $this->server["access_hash"];
            else
                $post['password']   = $this->server["password"];

            $post['cmd']        = $command;
            if(!isset($post['returncode'])) $post['returncode'] = "no";


            $url  = (($this->server["secure"]) ? "https" : "http"). "://".$this->server["ip"].":".$this->server["port"]."/api/";
            $call = curl_init();
            curl_setopt($call, CURLOPT_URL, $url);
            curl_setopt($call, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($call, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($call, CURLOPT_ENCODING, '');
            curl_setopt($call, CURLOPT_MAXREDIRS, 10);
            curl_setopt($call, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($call, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($call, CURLOPT_POSTFIELDS, http_build_query($post));
            curl_setopt($call,CURLOPT_TIMEOUT,30);

            // Fire api
            $result         = curl_exec($call);
            $status_code    = curl_getinfo($call, CURLINFO_HTTP_CODE);
            $this->code     = (int) $status_code;
            $error          = curl_errno($call) ? curl_error($call) : NULL;
            curl_close($call);

            Modules::save_log("Servers","HestiaCP",$command,$post,$result,$error ?: "Status code: ".$status_code);

            if($error){
                $this->error = $error;
                return false;
            }


            if(stristr($result,'Error:')){
                $this->error = $result;
                return false;
            }

            if($return_format == 'json'){
                $resultJson = json_decode($result,true);

                if(!$resultJson && $result)
                {
                    $this->error = $result;
                    return false;
                }

                if($resultJson) $result = $resultJson;


                if(!$resultJson){
                    $this->error = 'Unable to parse the response returned from the API.';
                    return false;
                }
            }

            if(!$result && $status_code != 200){
                $this->error = "Connection Failed status code: ".$status_code;
                $result = false;
            }

            return $result;
        }


    }