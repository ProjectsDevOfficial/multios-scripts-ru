<?php
    class OpenProvider_API {
        private $test_mode      = false;
        public  $error          = NULL;
        public $hostname        = 'https://api.openprovider.eu/v1beta/';
        public $username        = NULL;
        public $password        = NULL;
        public $token_data      = NULL;
        public $token           = NULL;


        public function set_credentials($username,$password='',$test_mode=false)
        {
            $this->username         = $username;
            $this->password         = $password;
            $this->test_mode        = $test_mode;
        }

        public function call($endpoint='',$data = [],$dontLog=false,$method=false)
        {
            $queries        = [];

            if(isset($data['queries']) && $data["queries"])
            {
                $queries = $data['queries'];
                unset($data["queries"]);
            }

            $url = $this->hostname.$endpoint;
            if($queries)
            {
                $encode_queries = http_build_query(array_map(function ($value){
                    if ($value === true) return 'true';
                    if ($value === false) return 'false';
                    return $value;
                }, $queries));
                $url .= "?".$encode_queries;
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL,$url);

            if($endpoint != 'auth/login')
            {
                if(!$this->token_data) $this->token_data = FileManager::file_read(__DIR__.DS."TOKEN");
                if(!$this->token_data) $this->token_creator();

                if($this->token_data)
                {
                    if(!is_array($this->token_data))
                        $this->token_data = Utility::jdecode(Crypt::decode($this->token_data,Config::get("crypt/system")),true);

                    if($this->token_data)
                    {
                        if($this->token_data['expires'] < DateManager::strtotime()) $this->token_creator();
                    }
                    else
                        $this->token_creator();

                    if($this->token_data)
                    {
                        if(!is_array($this->token_data)) $this->token_data = Utility::jdecode(Crypt::decode($this->token_data,Config::get("crypt/system")),true);

                        if(isset($this->token_data["token"]) && $this->token_data["token"])
                            $this->token = $this->token_data["token"];
                    }
                }


                if($this->error && !$this->token) return false;

                curl_setopt($ch, CURLOPT_HTTPHEADER,[
                    'Content-Type: application/json',
                    'Authorization: Bearer '.$this->token,
                ]);
            }


            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            if($data)
            {
                if(!$method) curl_setopt($ch, CURLOPT_CUSTOMREQUEST,"POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, Utility::jencode($data));
            }

            if($method) curl_setopt($ch, CURLOPT_CUSTOMREQUEST,$method);

            $result = curl_exec($ch);

            $this->error = curl_error($ch);

            curl_close($ch);

            $result         = Utility::jdecode($result,true);

            if($queries) $data["queries"] = $queries;

            if(!$dontLog) Modules::save_log("Registrars","OpenProvider",($method ? $method : ($data ? 'POST' : 'GET')).' / '.$endpoint,$data,$result);

            if(isset($result['desc']) && $result['desc'])
            {
                $this->error = $result["desc"];
                return false;
            }

            return $result;
        }

        private function token_creator()
        {
            $result         = $this->call('auth/login',[
                'username'  => $this->username,
                'password'  => $this->password,
                'ip'        => $_SERVER["SERVER_ADDR"],
            ],false,'POST');

            if($result && isset($result['data']) && $result['data'])
            {
                $data = $result['data'];
                $data['expires'] = DateManager::strtotime(DateManager::next_date(['hour' => 3]));

                $this->token_data   = Crypt::encode(Utility::jencode($data),Config::get("crypt/system"));

                return FileManager::file_write(__DIR__.DS."TOKEN",$this->token_data);
            }
            else
            {
                $this->error = $result["desc"] ?? ($this->error ? $this->error : 'Authorization Failed');
                return false;
            }
        }

    }