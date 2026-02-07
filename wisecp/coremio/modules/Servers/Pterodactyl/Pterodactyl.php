<?php
    class Pterodactyl_Module extends ServerModule
    {
        private $api = NULL;
        static $addon_logs = [];
        function __construct($server,$options=[])
        {
            $this->_name = __CLASS__;
            parent::__construct($server,$options);
        }

        private function create_client_api_key($params=[])
        {
            $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.82 Safari/537.36';

            $ch = curl_init();

            $cookie_file    = ROOT_DIR."temp".DS.md5(time()).".txt";

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT,3);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION,true);
            curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
            curl_setopt($ch, CURLOPT_COOKIEFILE,$cookie_file);

            $website = $this->api->GetHostname();

            // Index

            curl_setopt($ch, CURLOPT_URL,$website.'/');
            curl_setopt($ch, CURLOPT_HEADER, 1);
            $result = curl_exec($ch);

            if(!$result && curl_errno($ch))
            {
                $this->error = curl_error($ch);
                FileManager::file_delete($cookie_file);
                curl_close($ch);
                return false;
            }




            preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches);

            if(isset($matches[1][1]) && stristr($matches[1][1],'XSRF-TOKEN='))
            {
                $token_split    = explode("XSRF-TOKEN=",$matches[1][1]);
                $token_1        = urldecode($token_split[1]);
            }
            else
                $token_1 = '';

            preg_match('/<meta name="csrf-token" content="(.*?)">/i',$result,$token);

            if(!isset($token[1]) || !$token[1])
            {
                FileManager::file_delete($cookie_file);
                $this->error = $this->lang["error6"];
                curl_close($ch);
                return false;
            }
            $token_2      = $token[1];


            $http_header = array(
                'Accept: application/json',
                'Content-Type: application/json',
                'User-Agent: '.$user_agent,
                'X-Requested-With: XMLHttpRequest',
                'X-XSRF-Token: '.$token_1,
                'X-CSRF-Token: '.$token_2,
            );



            // Login...

            curl_setopt($ch, CURLOPT_HEADER, 0);

            $json_data  = [
                'user' => $params['username'],
                'password' => $params['password'],
                'g-recaptcha-response' => '',
            ];

            curl_setopt($ch, CURLOPT_HTTPHEADER, $http_header);
            curl_setopt($ch, CURLOPT_URL,$website.'/auth/login');
            curl_setopt($ch, CURLOPT_POST,true);
            curl_setopt($ch, CURLOPT_POSTFIELDS,Utility::jencode($json_data));
            $result = curl_exec($ch);
            if(curl_errno($ch)){
                $this->error = curl_error($ch);
                curl_close($ch);
                FileManager::file_delete($cookie_file);
                return false;
            }
            $result     = Utility::jdecode($result,true);


            if(!$result || (isset($result["errors"]) && sizeof($result["errors"]) > 0))
            {
                $error = current($result["errors"]);
                $this->error = $error["detail"];
                FileManager::file_delete($cookie_file);
                curl_close($ch);
                return false;
            }


            curl_setopt($ch, CURLOPT_URL,$website);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_POST,false);
            $result = curl_exec($ch);


            if(!$result && curl_errno($ch))
            {
                $this->error = curl_error($ch);
                FileManager::file_delete($cookie_file);
                curl_close($ch);
                return false;
            }

            preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches);

            if(isset($matches[1][1]) && stristr($matches[1][1],'XSRF-TOKEN='))
            {
                $token_split    = explode("XSRF-TOKEN=",$matches[1][1]);
                $token_1        = urldecode($token_split[1]);
            }
            else
                $token_1 = '';

            preg_match('/<meta name="csrf-token" content="(.*?)">/i',$result,$token);

            if(!isset($token[1]) || !$token[1])
            {
                FileManager::file_delete($cookie_file);
                $this->error = $this->lang["error6"];
                curl_close($ch);
                return false;
            }
            $token_2      = $token[1];


            $http_header = array(
                'Accept: application/json',
                'Content-Type: application/json',
                'User-Agent: '.$user_agent,
                'X-Requested-With: XMLHttpRequest',
                'X-XSRF-Token: '.$token_1,
                'X-CSRF-Token: '.$token_2,
            );

            // Create API Credit

            $json_data = [
                'description' => "WISECP Client",
            ];

            curl_setopt($ch, CURLOPT_HTTPHEADER, $http_header);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_URL,$website.'/api/client/account/api-keys');
            curl_setopt($ch, CURLOPT_POST,true);
            curl_setopt($ch, CURLOPT_POSTFIELDS,Utility::jencode($json_data));

            $result = curl_exec($ch);
            if(curl_errno($ch)){
                $this->error = curl_error($ch);
                curl_close($ch);
                FileManager::file_delete($cookie_file);
                return false;
            }

            $result = Utility::jdecode($result,true);

            curl_close($ch);

            if(!$result || (isset($result["errors"]) && sizeof($result["errors"]) > 0))
            {
                $error = current($result["errors"]);
                $this->error = $error["detail"];
                FileManager::file_delete($cookie_file);
                return false;
            }

            FileManager::file_delete($cookie_file);

            if($result && isset($result["meta"]["secret_token"]))
                return $result["attributes"]["identifier"].$result["meta"]["secret_token"];
        }

        private function GetOption($id, $default = NULL,$noAdReq=false) {
            $creation_info  = $this->options["creation_info"];
            $requirements   = $this->val_of_requirements;
            $addons         = [];#$this->val_of_conf_opt;

            if(!$noAdReq && isset($requirements[$id]) && $requirements[$id] !== '')
                return $requirements[$id];
            elseif(!$noAdReq && isset($addons[$id]) && $addons[$id] !== '')
                return $addons[$id];
            elseif(isset($creation_info[$id]) && $creation_info[$id] !== '')
                return $creation_info[$id];

            return $default;
        }

        protected function define_server_info($server=[])
        {

            if(!class_exists("Pterodactyl_API")) include __DIR__.DS."api.php";
            $this->api = new Pterodactyl_API($server);
        }

        public function testConnect(){
            $response = $this->api->call('nodes');
            if(!$response)
            {
                $this->error = $this->lang["error1"].": ".$this->api->error;
                return false;
            }
            return true;
        }

        public function config_options($data=[],$selection_data=true)
        {
            $egg_list           = [];
            $location_list      = [];
            $nest_list          = [];

            if($selection_data)
            {
                $locations          = $this->api->call("locations",[],false,true);
                $nests              = $this->api->call("nests",[],false);

                if(isset($nests["data"]) && $nests["data"])
                {
                    foreach($nests["data"] AS $nest)
                    {
                        $nest_id            = $nest["attributes"]["id"];
                        $eggs               = $this->api->call("nests/".$nest_id."/eggs",[],false,true);

                        $nest_list[$nest["attributes"]["id"]] = $nest["attributes"]["id"]." - ".$nest["attributes"]["name"];

                        if(isset($eggs["data"]) && $eggs["data"])
                        {
                            foreach($eggs["data"] AS $egg)
                                $egg_list[$egg["attributes"]["id"]] = "Nest:".$nest_id." | ".$egg["attributes"]["name"];
                        }
                    }
                }
                if(isset($locations["data"]) && $locations["data"])
                {
                    foreach($locations["data"] AS $location)
                        $location_list[$location["attributes"]["id"]] = $location["attributes"]["id"]." - ".$location["attributes"]["long"];
                }

                if(!$nest_list && isset($data["nest_id"]) && $data["nest_id"]) $nest_list[$data["nest_id"]] = $data["nest_id"];
                if(!$egg_list && isset($data["egg_id"]) && $data["egg_id"]) $egg_list[$data["egg_id"]] = $data["egg_id"];
                if(!$location_list && isset($data["location_id"]) && $data["location_id"]) $location_list[$data["location_id"]] = $data["location_id"];

            }

            return [
                'cpu'          => [
                    'name'              => $this->lang["cpu"],
                    'description'       => $this->lang["cpu-desc"],
                    'type'              => "text",
                    'width'             => "50",
                    'value'             => isset($data["cpu"]) ? $data["cpu"] : "0",
                ],
                'disk'          => [
                    'name'              => $this->lang["disk"],
                    'description'       => $this->lang["disk-desc"],
                    'type'              => "text",
                    'width'             => "50",
                    'value'             => isset($data["disk"]) ? $data["disk"] : "0",
                ],
                'memory'          => [
                    'name'              => $this->lang["memory"],
                    'description'       => $this->lang["memory-desc"],
                    'type'              => "text",
                    'width'             => "50",
                    'value'             => isset($data["memory"]) ? $data["memory"] : "0",
                ],
                'swap'          => [
                    'name'              => $this->lang["swap"],
                    'description'       => $this->lang["swap-desc"],
                    'type'              => "text",
                    'width'             => "50",
                    'value'             => isset($data["swap"]) ? $data["swap"] : "0",
                ],
                'location_id'          => [
                    'name'              => $this->lang["location_id"],
                    'description'       => $this->lang["location_id-desc"],
                    'type'              => "dropdown",
                    'width'             => "50",
                    'options'           => $location_list,
                    'value'             => isset($data["location_id"]) ? $data["location_id"] : "",
                ],
                'nest_id'          => [
                    'name'              => $this->lang["nest_id"],
                    'description'       => $this->lang["nest_id-desc"],
                    'type'              => "dropdown",
                    'width'             => "50",
                    'options'           => $nest_list,
                    'value'             => isset($data["nest_id"]) ? $data["nest_id"] : "",
                ],
                'egg_id'          => [
                    'name'              => $this->lang["egg_id"],
                    'description'       => $this->lang["egg_id-desc"],
                    'type'              => "dropdown",
                    'width'             => "50",
                    'options'           => $egg_list,
                    'value'             => isset($data["egg_id"]) ? $data["egg_id"] : "",
                ],
                'io'          => [
                    'name'              => $this->lang["io"],
                    'description'       => $this->lang["io-desc"],
                    'type'              => "text",
                    'width'             => "50",
                    'value'             => isset($data["io"]) ? $data["io"] : "500",
                ],
                'pack_id'          => [
                    'name'              => $this->lang["pack_id"],
                    'description'       => $this->lang["pack_id-desc"],
                    'type'              => "text",
                    'width'             => "50",
                    'value'             => isset($data["pack_id"]) ? $data["pack_id"] : "",
                ],
                'port_range'          => [
                    'name'              => $this->lang["port_range"],
                    'description'       => $this->lang["port_range-desc"],
                    'type'              => "text",
                    'width'             => "50",
                    'value'             => isset($data["port_range"]) ? $data["port_range"] : "",
                ],
                'startup'          => [
                    'name'              => $this->lang["startup"],
                    'description'       => $this->lang["startup-desc"],
                    'type'              => "text",
                    'width'             => "50",
                    'value'             => isset($data["startup"]) ? $data["startup"] : "",
                ],
                'image'          => [
                    'name'              => $this->lang["image"],
                    'description'       => $this->lang["image-desc"],
                    'type'              => "text",
                    'width'             => "50",
                    'value'             => isset($data["image"]) ? $data["image"] : "",
                ],
                'databases'          => [
                    'name'              => $this->lang["databases"],
                    'description'       => $this->lang["databases-desc"],
                    'type'              => "text",
                    'width'             => "50",
                    'value'             => isset($data["databases"]) ? $data["databases"] : "",
                ],
                'server_name'          => [
                    'name'              => $this->lang["server_name"],
                    'description'       => $this->lang["server_name-desc"],
                    'type'              => "text",
                    'width'             => "50",
                    'value'             => isset($data["server_name"]) ? $data["server_name"] : "",
                ],
                'backups'          => [
                    'name'              => $this->lang["backups"],
                    'description'       => $this->lang["backups-desc"],
                    'type'              => "text",
                    'width'             => "50",
                    'value'             => isset($data["backups"]) ? $data["backups"] : "",
                ],
                'allocations'          => [
                    'name'              => $this->lang["allocations"],
                    'description'       => $this->lang["allocations-desc"],
                    'type'              => "text",
                    'width'             => "50",
                    'value'             => isset($data["allocations"]) ? $data["allocations"] : "",
                ],
                'dedicated_ip'          => [
                    'name'              => $this->lang["dedicated_ip"],
                    'description'       => $this->lang["dedicated_ip-desc"],
                    'type'              => "approval",
                    'width'             => "50",
                    'checked'             => isset($data["dedicated_ip"]) && $data["dedicated_ip"],
                ],
                'oom_disabled'          => [
                    'name'              => $this->lang["oom_disabled"],
                    'description'       => $this->lang["oom_disabled-desc"],
                    'type'              => "approval",
                    'width'             => "50",
                    'checked'             => isset($data["oom_disabled"]) && $data["oom_disabled"],
                ],
            ];
        }

        public function create(array $order_options=[])
        {
            $prefix         = $this->config["external_id_prefix"] ?? '';
            $user_password      = '';
            $client_secret_key  = '';
            $userResult = $this->api->call('users?filter[email]=' . urlencode($this->user['email']));
            if($userResult['meta']['pagination']['total'] === 0)
            {
                $user_password      = Utility::generate_hash(24,false,'lud');
                $userResult         = $this->api->call('users',
                    [
                        'username'      => $this->GetOption('username',$this->api->GenerateUsername()),
                        'email'         => $this->user['email'],
                        'password'      => $user_password,
                        'first_name'    => $this->user['name'],
                        'last_name'     => $this->user['surname'],
                        'external_id'   => $prefix.$this->user['id'],
                    ]
                    ,
                    'POST'
                );
                if(!$userResult && $this->api->error)
                {
                    $this->error = $this->api->error;
                    return false;
                }

                $client_secret_key = $this->create_client_api_key
                (
                    [
                        'username'              => $this->user["email"],
                        'password'              => $user_password,
                    ]
                );
                if(!$client_secret_key && $this->error) $this->error = NULL;

            }
            else
            {
                foreach($userResult['data'] as $key => $value) {
                    if($value['attributes']['email'] === $this->user['email']) {
                        $userResult = array_merge($userResult, $value);
                        break;
                    }
                }
                $userResult = array_merge($userResult, $userResult['data'][0]);

                $found_password     = WDB::select("options")->from("users_products");
                $found_password->where("owner_id","=",$this->user["id"],"&&");
                $found_password->where("id","!=",$this->order["id"],"&&");
                $found_password->where("type","=","server","&&");
                $found_password->where("module","=","Pterodactyl","&&");
                $found_password->where("options","NOT LIKE",'%"login":{"username":"","password":""}%',"&&");
                $found_password->where("(");
                $found_password->where("options","LIKE",'%"server_id":'.$this->server["id"].'%',"||");
                $found_password->where("options","LIKE",'%"server_id":"'.$this->server["id"].'"%');
                $found_password->where(")");
                $found_password->order_by("id DESC");
                $found_password->limit(1);
                if(WDB::build())
                {
                    $found_order        = WDB::getObject()->options;
                    $found_order        = Utility::jdecode($found_order,true);
                    if(isset($found_order["login"]["password"]) && $found_order["login"]["password"])
                    {
                        $get_password       = $this->decode_str($found_order["login"]["password"]);
                        if($get_password) $user_password = $get_password;
                        if(isset($found_order["config"]["client_secret_key"]) && $found_order["config"]["client_secret_key"])
                            $client_secret_key = $found_order["config"]["client_secret_key"];
                    }
                }

                $pass  = Session::get("Pterodactyl_pass",true);

                if($pass) $pass = Utility::jdecode($pass,true);
                else $pass = [];

                if(!$user_password)
                {
                    if($pass)
                    {
                        if(isset($pass[$this->server["id"].'-'.$this->user["id"]]))
                        {
                            $user_password          = $pass[$this->server["id"].'-'.$this->user["id"]]['password'];
                            $client_secret_key      = $pass[$this->server["id"].'-'.$this->user["id"]]['secret'];
                        }
                    }
                }

                if(!$user_password)
                {
                    $user_password      = Utility::generate_hash(24,false,'lud');

                    $updateResult       = $this->api->call('users/' . $userResult['attributes']['id'], [
                        'username' => $userResult['attributes']['username'],
                        'email' => $userResult['attributes']['email'],
                        'first_name' => $userResult['attributes']['first_name'],
                        'last_name' => $userResult['attributes']['last_name'],
                        'password' => $user_password,
                    ], 'PATCH');
                    if(!$updateResult)
                    {
                        $this->error = $this->lang["error1"].': '.$this->api->error;
                        return false;
                    }
                }

                $pass[$this->server["id"].'-'.$this->user["id"]] = [
                    'password'  => $user_password,
                    'secret'    => $client_secret_key,
                ];

                Session::set("Pterodactyl_pass",Utility::jencode($pass),true);

                if(!$client_secret_key)
                    $client_secret_key = $this->create_client_api_key
                    (
                        [
                            'username'              => $this->user["email"],
                            'password'              => $user_password,
                        ]
                    );
                if(!$client_secret_key && $this->error) $this->error = NULL;
            }

            if(!$userResult)
            {
                $this->error = $this->lang["error1"].': Failed to create user, received error code: ' . $this->api->status_code . '. Enable module debug log for more info.';
                return false;
            }
            $userId = $userResult['attributes']['id'];

            $nestId     = $this->GetOption('nest_id');
            $eggId      = $this->GetOption('egg_id');


            $eggData = $this->api->call('nests/' . $nestId . '/eggs/' . $eggId . '?include=variables');
            if($this->api->status_code !== 200)
            {
                $this->error = 'Failed to get egg data, received error code: ' . $this->api->status_code . '. Enable module debug log for more info.';
                return false;
            }
            $environment = [];


            foreach($eggData['attributes']['relationships']['variables']['data'] as $key => $val) {
                $attr = $val['attributes'];
                $var = $attr['env_variable'];
                $default = $attr['default_value'];
                $friendlyName = $this->GetOption($attr['name']);
                $envName = $this->GetOption($attr['env_variable']);

                if(isset($friendlyName)) $environment[$var] = $friendlyName;
                elseif(isset($envName)) $environment[$var] = $envName;
                else $environment[$var] = $default;
            }

            $name                      = $this->GetOption('server_name');
            if(!$name) $name           = $this->product["title"] . ' - ' . $this->order['id'];
            $memory         = $this->GetOption('memory');
            $swap           = $this->GetOption('swap');
            $io             = $this->GetOption('io');
            $cpu            = $this->GetOption('cpu');
            $disk           = $this->GetOption('disk');
            $location_id    = $this->GetOption('location_id');
            $dedicated_ip   = $this->GetOption('dedicated_ip') ? true : false;
            $port_range     = $this->GetOption('port_range');
            $port_range     = isset($port_range) ? explode(',', $port_range) : [];
            $image          = $this->GetOption('image', $eggData['attributes']['docker_image']);
            $startup        = $this->GetOption('startup', $eggData['attributes']['startup']);
            $databases      = $this->GetOption('databases');
            $allocations    = $this->GetOption('allocations');
            $backups        = $this->GetOption('backups');
            $oom_disabled   = $this->GetOption('oom_disabled') ? true : false;

            $serverData = [
                'name' => $name,
                'user' => (int) $userId,
                'nest' => (int) $nestId,
                'egg' => (int) $eggId,
                'docker_image' => $image,
                'startup' => $startup,
                'oom_disabled' => $oom_disabled,
                'limits' => [
                    'memory' => (int) $memory,
                    'swap' => (int) $swap,
                    'io' => (int) $io,
                    'cpu' => (int) $cpu,
                    'disk' => (int) $disk,
                ],
                'feature_limits' => [
                    'databases' => $databases ? (int) $databases : null,
                    'allocations' => (int) $allocations,
                    'backups' => (int) $backups,
                ],
                'deploy' => [
                    'locations' => [(int) $location_id],
                    'dedicated_ip' => $dedicated_ip,
                    'port_range' => $port_range,
                ],
                'environment' => $environment,
                'start_on_completion' => true,
                'external_id' => $prefix.$this->order["id"],
            ];

            $addon_values           = $this->id_of_conf_opt ?? [];
            $accepted_addons        = [];

            if($addon_values) {
                foreach ($addon_values as $ad_id => $ad_values) {
                    if($ad_values)
                    {
                        foreach($ad_values AS $k => $v)
                        {
                            $feature_limit  = isset($serverData["feature_limits"][$k]);
                            $is_limit       = isset($serverData["limits"][$k]);

                            if(stristr($k,'_id')) continue;
                            else
                            {
                                $currentValue = $serverData[$k] ?? 0;

                                if($feature_limit)
                                    $currentValue = $serverData["feature_limits"][$k] ?? 0;
                                elseif($is_limit)
                                    $currentValue = $serverData["limits"][$k] ?? 0;

                                $v = (int) $v;

                                $newValue = ((int) $currentValue) + $v;
                            }
                            if($feature_limit)
                                $serverData["feature_limits"][$k] = $newValue;
                            elseif($is_limit)
                                $serverData["limits"][$k] = $newValue;
                            else
                                $serverData[$k] = $newValue;
                        }
                        $accepted_addons[] = $ad_id;
                    }
                }
            }

            $server = $this->api->call('servers', $serverData, 'POST');

            if($this->api->status_code === 400)
            {
                $this->error = $this->lang["error1"].': Couldn\'t find any nodes satisfying the request.';
                return false;
            }
            elseif($this->api->status_code !== 201)
            {
                $this->error = $this->lang["error1"].': Failed to create the server, received the error code: ' . $this->api->status_code. '. Enable module debug log for more info.';
                return false;
            }

            $cf = [$this->entity_id_name => $server['attributes']['id']];
            if($client_secret_key) $cf["client_secret_key"] = $client_secret_key;

            $creation_info          = $order_options["creation_info"];
            if($this->val_of_requirements) $creation_info = array_merge($creation_info,$this->val_of_requirements);
            if($this->val_of_conf_opt) $creation_info    = array_merge($creation_info,$this->val_of_conf_opt);

            $this->api->client_secret_key = $client_secret_key;

            $svDetail   = $this->api->client_call('servers/'.$server['attributes']['id']);
            $ip         = $svDetail["attributes"]["relationships"]["allocations"]["data"][0]["attributes"]["ip"] ?? '';
            $port       = $svDetail["attributes"]["relationships"]["allocations"]["data"][0]["attributes"]["port"] ?? '';

            if($port) $ip .= ":".$port;

            if($accepted_addons)
                foreach($accepted_addons AS $ad_id) $GLOBALS["addon_accepted"][$ad_id] = true;

            return [
                'hostname'          => $name,
                'ip'                => $ip ? $ip : $this->server["ip"],
                'login' => [
                    'username' => $this->user["email"],
                    'password' => $user_password,
                ],
                'creation_info' => $creation_info,
                'config' => $cf,
            ];
        }

        public function suspend()
        {
            $serverId = $this->config[$this->entity_id_name];
            $suspendResult = $this->api->call('servers/' . $serverId . '/suspend', [], 'POST');
            if(!$suspendResult && $this->api->error)
            {
                $this->error = $this->lang["error1"].': '.$this->api->error;
                return false;
            }
            return true;
        }

        public function unsuspend()
        {
            $serverId = $this->config[$this->entity_id_name];
            $suspendResult = $this->api->call('servers/' . $serverId . '/unsuspend', [], 'POST');
            if(!$suspendResult && $this->api->error)
            {
                $this->error = $this->lang["error1"].': '.$this->api->error;
                return false;
            }
            return true;
        }

        public function terminate()
        {
            $serverId = $this->config[$this->entity_id_name];
            $terminateResult = $this->api->call('servers/' . $serverId, [], 'DELETE');
            if(!$terminateResult && $this->api->error)
            {
                $this->error = $this->lang["error1"].': '.$this->api->error;
                return false;
            }

            return true;
        }

        public function addon_create($addon=[], $args=[])
        {
            $entity_id  = $this->config[$this->entity_id_name] ?? 0;

            if(!$entity_id)
            {
                $this->error = "The connected service is not yet established.";
                return false;
            }

            if(isset($GLOBALS["addon_accepted"][$addon["id"]])) return true;

            $serverDetail = $this->api->GetServerDetail($entity_id);
            if(!$serverDetail)
            {
                $this->error = $this->api->error;
                return false;
            }

            $builds = [
                'allocation'        => $serverDetail["attributes"]["allocation"] ?? 1,
                'memory'            => $serverDetail["attributes"]["limits"]["memory"] ?? 0,
                'swap'              => $serverDetail["attributes"]["limits"]["swap"] ?? 0,
                'io'                => $serverDetail["attributes"]["limits"]["io"] ?? 0,
                'cpu'               => $serverDetail["attributes"]["limits"]["cpu"] ?? 0,
                'disk'              => $serverDetail["attributes"]["limits"]["disk"] ?? 0,
                'feature_limits'    => $serverDetail["attributes"]["feature_limits"],
            ];

            $values = $this->id_of_conf_opt[$addon['id']] ?? [];

            if($values)
            {
                foreach($values AS $k => $v)
                {
                    $feature_limit = $builds["feature_limits"][$k] ?? false;

                    if(stristr($k,'_id')) continue;
                    else
                    {
                        $currentValue = $builds[$k] ?? 0;
                        if($feature_limit)
                            $currentValue = $builds["feature_limits"][$k] ?? 0;
                        $currentValue = (int) $currentValue;
                        $v = (int) $v;

                        $newValue = $currentValue + $v;
                    }

                    if($feature_limit)
                        $builds["feature_limits"][$k] = $newValue;
                    else
                        $builds[$k] = $newValue;
                }
            }

            $run            = $this->api->call("servers/".$entity_id."/build",$builds,'PATCH');
            if(!$run && $this->api->error)
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }
        public function addon_suspend($addon=[],$args=[])
        {
            return $this->addon_cancelled($addon,$args);
        }
        public function addon_unsuspend($addon=[],$args=[])
        {
            return $this->addon_create($addon,$args);
        }
        public function addon_cancelled($addon=[],$args=[])
        {
            if($addon['status'] == 'suspended') return true;
            $entity_id  = $this->config[$this->entity_id_name] ?? 0;
            if(!$entity_id) return true;

            $serverDetail = $this->api->GetServerDetail($entity_id);
            if(!$serverDetail)
            {
                $this->error = $this->api->error;
                return false;
            }

            $builds = [
                'allocation'        => $serverDetail["attributes"]["allocation"] ?? 1,
                'memory'            => $serverDetail["attributes"]["limits"]["memory"] ?? 0,
                'swap'              => $serverDetail["attributes"]["limits"]["swap"] ?? 0,
                'io'                => $serverDetail["attributes"]["limits"]["io"] ?? 0,
                'cpu'               => $serverDetail["attributes"]["limits"]["cpu"] ?? 0,
                'disk'              => $serverDetail["attributes"]["limits"]["disk"] ?? 0,
                'feature_limits' => $serverDetail["attributes"]["feature_limits"],
            ];

            $values         = $this->id_of_conf_opt[$addon['id']] ?? [];

            if($values)
            {
                foreach($values AS $k => $v)
                {
                    $feature_limit = $builds["feature_limits"][$k] ?? false;

                    if(stristr($k,'_id')) continue;
                    else
                    {
                        $currentValue = $builds[$k];
                        if($feature_limit) $currentValue = $builds["feature_limits"][$k];
                        $currentValue = (int) $currentValue;
                        $v            = (int) $v;
                        $newValue = $currentValue - $v;
                        if($newValue < 1) $newValue = $this->order["options"]["creation_info"][$k] ?? ($this->product["module_data"][$k] ?? $currentValue);
                    }

                    if($feature_limit)
                        $builds["feature_limits"][$k] = $newValue;
                    else
                        $builds[$k] = $newValue;
                }
            }

            $run            = $this->api->call("servers/".$entity_id."/build",$builds,'PATCH');
            if(!$run && $this->api->error)
            {
                $this->error = $this->api->error;
                return false;
            }
            return true;
        }


        public function power_change($command='start')
        {
            if(!isset($this->config["client_secret_key"]) || !$this->config["client_secret_key"]) return false;
            $this->api->client_secret_key = $this->config["client_secret_key"];

            $serverId           = $this->config[$this->entity_id_name];
            $serverDetail       = $this->api->GetServerDetail($serverId);
            if(!$serverDetail)
            {
                echo Utility::jencode([
                    'status' => "error",
                    'message' => $this->api->error,
                ]);
                return false;
            }

            $identifier     = $serverDetail["attributes"]["identifier"];
            $run            = $this->api->client_call("servers/".$identifier."/power",[
                'signal' => $command,
            ],'POST');

            if(!$run)
            {
                echo Utility::jencode([
                    'status' => "error",
                    'message' => $this->api->error,
                ]);
                return false;
            }


            echo Utility::jencode([
                'status' => "successful",
                'message' => $this->lang["successful"],
                'timeRedirect' => [
                    'url' => $this->area_link,
                    'duration' => 1000,
                ],
            ]);

            return true;
        }
        public function reinstall()
        {
            if(!isset($this->config["client_secret_key"]) || !$this->config["client_secret_key"]) return false;
            $this->api->client_secret_key = $this->config["client_secret_key"];

            $serverId           = $this->config[$this->entity_id_name];
            $serverDetail       = $this->api->GetServerDetail($serverId);
            if(!$serverDetail)
            {
                echo Utility::jencode([
                    'status' => "error",
                    'message' => $this->api->error,
                ]);
                return false;
            }
            $run            = $this->api->call("servers/".$serverId."/reinstall",[],'POST');

            if(!$run && $this->api->error)
            {
                echo Utility::jencode([
                    'status' => "error",
                    'message' => $this->api->error,
                ]);
                return false;
            }

            echo Utility::jencode([
                'status' => "successful",
                'message' => $this->lang["successful"],
                'timeRedirect' => ['url' => $this->area_link, 'duration' => 1000],
            ]);

            return true;
        }


        public function apply_updowngrade($params=[])
        {
            $serverId       = $this->config[$this->entity_id_name];
            if(!$serverId)
            {
                $this->error = "VM ID Not found";
                return false;
            }
            $serverData     = $this->api->GetServerDetail($serverId);

            if(!$serverData)
            {
                $this->error = $this->lang["error1"].': '.$this->api->error;
                return false;
            }

            $memory = $this->GetOption('memory');
            $swap = $this->GetOption('swap');
            $io = $this->GetOption('io');
            $cpu = $this->GetOption('cpu');
            $disk = $this->GetOption('disk');
            $databases = $this->GetOption('databases');
            $allocations = $this->GetOption('allocations');
            $backups = $this->GetOption('backups');
            $oom_disabled = $this->GetOption('oom_disabled') ? true : false;

            $builds = [
                'allocation' => $serverData['attributes']['allocation'],
                'memory' => (int) $memory,
                'swap' => (int) $swap,
                'io' => (int) $io,
                'cpu' => (int) $cpu,
                'disk' => (int) $disk,
                'oom_disabled' => $oom_disabled,
                'feature_limits' => [
                    'databases' => (int) $databases,
                    'allocations' => (int) $allocations,
                    'backups' => (int) $backups,
                ],
            ];

            if($this->val_of_conf_opt)
            {
                foreach($this->val_of_conf_opt AS $k => $v)
                {
                    $feature_limit = $builds["feature_limits"][$k] ?? false;

                    if(stristr($k,'_id')) continue;
                    else
                    {
                        $currentValue = $builds[$k] ?? 0;
                        if($feature_limit)
                            $currentValue = $builds["feature_limits"][$k] ?? 0;
                        $currentValue = (int) $currentValue;
                        $v = (int) $v;

                        $newValue = $currentValue + $v;
                    }

                    if($feature_limit)
                        $builds["feature_limits"][$k] = $newValue;
                    else
                        $builds[$k] = $newValue;
                }
            }


            $updateResult = $this->api->call('servers/' . $serverId . '/build', $builds, 'PATCH');
            if(!$updateResult)
            {
                $this->error = $this->lang["error1"].': '.$this->api->error;
                return false;
            }

            $nestId     = $this->GetOption('nest_id');
            $eggId      = $this->GetOption('egg_id');


            $eggData = $this->api->call('nests/' . $nestId . '/eggs/' . $eggId . '?include=variables');
            if(!$eggData && $this->api->error)
            {
                $this->error = $this->lang["error1"].': '.$this->api->error;
                return false;
            }

            $environment = [];
            foreach($eggData['attributes']['relationships']['variables']['data'] as $key => $val) {
                $attr           = $val['attributes'];
                $var            = $attr['env_variable'];
                $envName        = $this->GetOption($attr['env_variable']);

                if(isset($envName)) $environment[$var] = $envName;
                elseif(isset($serverData['attributes']['container']['environment'][$var])) $environment[$var] = $serverData['attributes']['container']['environment'][$var];
                elseif(isset($attr['default_value'])) $environment[$var] = $attr['default_value'];
            }

            $image      = $this->GetOption('image', $serverData['attributes']['container']['image']);
            $startup    = $this->GetOption('startup', $serverData['attributes']['container']['startup_command']);
            $updateData = [
                'environment' => $environment,
                'startup' => $startup,
                'egg' => (int) $eggId,
                'image' => $image,
                'skip_scripts' => false,
            ];

            $updateResult = $this->api->call('servers/' . $serverId . '/startup', $updateData, 'PATCH');
            if(!$updateResult)
            {
                $this->error = $this->lang["error1"].': '.$this->api->error;
                return false;
            }

            return true;
        }

        public function get_status()
        {
            $serverId       = $this->config[$this->entity_id_name];
            $serverDetail   = $this->api->getServerDetail($serverId);
            if($serverDetail && isset($this->config["client_secret_key"]) && $this->config["client_secret_key"])
            {
                $this->api->client_secret_key = $this->config["client_secret_key"];
                $resources      = $this->api->client_call('servers/'.$serverDetail["attributes"]["identifier"].'/resources');
                if(!$resources)
                {
                    $this->error = $this->api->error;
                    return false;
                }
                $status = $resources["attributes"]["current_state"];

                return $status == "running" || $status == "starting";
            }
            else
            {
                $this->error = $this->lang["error3"];
                return false;
            }
        }

        public function list_vps()
        {

            $list = [];

            $responses      = [] ;

            $response = $this->api->call("servers");

            if(!$response)
            {
                $this->error = $this->lang["error1"].": ".$this->api->error;
                return false;
            }

            if(isset($response["meta"]["pagination"]["count"]) && $response["meta"]["pagination"]["count"] == 0)
            {
                $this->error = $this->lang["error2"];
                return false;
            }

            $responses[] = $response;
            if(isset($response["meta"]["pagination"]["total_pages"]) && $response["meta"]["pagination"]["total_pages"] > 1){
                for($i=2;$i<=$response["meta"]["pagination"]["total_pages"]; $i++)
                {
                    $response_new = $this->api->call("servers?page=".$i);
                    if($response_new) $responses[] = $response_new;
                }
            }


            if($responses)
            {
                foreach($responses AS $response)
                {
                    foreach($response["data"] AS $row)
                    {
                        $hostname       = $row["attributes"]["name"];
                        $primary_ip     = $this->server["ip"];
                        $created_at     = explode("T",$row["attributes"]["created_at"]);
                        $created_at     = $created_at[0];
                        $id             = $row["attributes"]["id"];

                        $data           = [
                            'cdate'             => $created_at,
                            'hostname'          => $hostname,
                            'ip'                => $primary_ip,
                            'sync_terms'        => [
                                [
                                    'column'    => "options",
                                    'mark'      => "LIKE",
                                    'value'     => '%"'.$this->entity_id_name.'":"'.$id.'"%',
                                    'logical'   => "||",
                                ],
                                [
                                    'column'    => "options",
                                    'mark'      => "LIKE",
                                    'value'     => '%"'.$this->entity_id_name.'":'.$id.'%',
                                    'logical'   => "",
                                ],
                            ],
                            'access_data'       => [
                                'config' => [ $this->entity_id_name => $id ],
                            ],
                        ];

                        $cOptions = array_keys($this->config_options([],false));

                        if($cOptions)
                        {
                            foreach($cOptions AS $cOption)
                            {
                                if(isset($row["attributes"]["limits"][$cOption]))
                                    $data["access_data"][$cOption] = $row["attributes"]["limits"][$cOption];
                                if(isset($row["attributes"]["feature_limits"][$cOption]))
                                    $data["access_data"][$cOption] = $row["attributes"]["feature_limits"][$cOption];
                                if(isset($row["attributes"][$cOption]))
                                    $data["access_data"][$cOption] = $row["attributes"][$cOption];
                            }
                        }
                        $list[$hostname."|".$primary_ip] = $data;
                    }
                }
            }

            return $list;
        }

        public function clientArea()
        {
            $content    = $this->clientArea_buttons_output();
            $_page      = $this->page;
            $_data      = [];

            if(!$_page) $_page = 'home';

            if($_page == 'home')
            {
                $serverDetail       = $this->api->GetServerDetail($this->config[$this->entity_id_name]);

                if($serverDetail && isset($this->config["client_secret_key"]) && $this->config["client_secret_key"])
                {
                    $identifier     = $serverDetail["attributes"]["identifier"];
                    $ram_limit      = $serverDetail["attributes"]["limits"]["memory"];
                    $cpu_limit      = $serverDetail["attributes"]["limits"]["cpu"];
                    $hdd_limit      = $serverDetail["attributes"]["limits"]["disk"];
                    $this->api->client_secret_key = $this->config["client_secret_key"];

                    $svDetail   = $this->api->client_call('servers/'.$identifier);
                    $ip         = $svDetail["attributes"]["relationships"]["allocations"]["data"][0]["attributes"]["ip"] ?? '';
                    $port       = $svDetail["attributes"]["relationships"]["allocations"]["data"][0]["attributes"]["port"] ?? '';

                    if($port) $ip .= ":".$port;

                    if($ip && $ip != $this->order["options"]["ip"])
                    {
                        $order_options = $this->order["options"];
                        $order_options["ip"] = $ip;
                        Orders::set($this->order["id"],['options' => Utility::jencode($order_options)]);
                        $content .= '<script type="text/javascript">$(document).ready(function(){ window.location.reload();});</script>';
                    }


                    $resources      = $this->api->client_call('servers/'.$identifier.'/resources');

                    if($resources)
                    {
                        $resources  = $resources["attributes"]["resources"];
                        $cpu_used   = $resources["cpu_absolute"];
                        $hdd_used   = substr(FileManager::formatByte($resources["disk_bytes"]),0,-3);
                        $ram_used   = substr(FileManager::formatByte($resources["memory_bytes"]),0,-3);

                        if($cpu_used == 'NAN' || $cpu_used == 'N') $cpu_used = 0;
                        if($hdd_used == 'NAN' || $hdd_used == 'N') $hdd_used = 0;
                        if($ram_used == 'NAN' || $ram_used == 'N') $ram_used = 0;


                        $_data['hdd_limit']         = $hdd_limit ? round($hdd_limit) : 0;
                        $_data['cpu_limit']         = $cpu_limit ? round($cpu_limit) : 0;
                        $_data['ram_limit']         = $ram_limit ? round($ram_limit) : 0;
                        $_data['hdd_used']          = $hdd_used ? round($hdd_used) : 0;
                        $_data['cpu_used']          = $cpu_used ? round($cpu_used) : 0;
                        $_data['ram_used']          = $ram_used ? round($ram_used) : 0;
                    }
                }
            }

            $content .= $this->get_page('clientArea-'.$_page,$_data);
            return  $content;
        }

        public function clientArea_buttons()
        {
            $buttons    = [];

            $defined_secret_key = isset($this->config["client_secret_key"]) && $this->config["client_secret_key"];

            if($this->page && $this->page != "home")
            {
                $buttons['home'] = [
                    'icon' => 'fa fa-chevron-circle-left',
                    'text' => $this->lang["turn-back"],
                    'type' => 'page-loader',
                ];
            }
            else
            {

                if($defined_secret_key)
                {
                    $buttons['restart']     = [
                        'attributes'=> [
                            'id' => "vpsrestart",
                            'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('restart',this);",
                        ],
                        'icon'  => 'fa fa-refresh',
                        'text'  => "Restart",
                        'type'  => 'transaction',
                    ];

                    $server_status = $this->get_status();

                    if(!$this->error)
                    {
                        if($server_status)
                            $buttons['stop']     = [
                                'attributes'=> [
                                    'id' => "vpsstop",
                                    'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('stop',this);",
                                ],
                                'icon'      => 'fa fa-pause-circle',
                                'text'      => "Stop",
                                'type'      => 'transaction',
                            ];
                        else
                            $buttons['start']     = [
                                'attributes' => [
                                    'id' => 'vpsstart',
                                    'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('start',this);",
                                ],
                                'icon'  => 'fa fa-play-circle',
                                'text'  => "Start",
                                'type'  => 'transaction',
                            ];
                    }
                }

                $buttons['change-password'] = [
                    'attributes' => [
                        'id' => 'vpscpassword',
                    ],
                    'icon'      => 'fa fa-shield',
                    'text'      => $this->lang["change-password"],
                    'type'      => 'page-loader',
                ];

                if($defined_secret_key)
                    $buttons['kill']     = [
                        'attributes'=> [
                            'id' => "vpskill",
                            'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('kill',this);",
                        ],
                        'icon'  => 'fa fa-ban',
                        'text'  => "Kill",
                        'type'  => 'transaction',
                    ];

                $buttons['reinstall']     = [
                    'attributes'=> [
                        'id' => "vpsreinstall",
                        'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('reinstall',this);",
                    ],
                    'icon'      => 'fa fa-wrench',
                    'text'  => "Reinstall",
                    'type'  => 'transaction',
                ];

            }

            return $buttons;
        }

        public function adminArea_buttons()
        {
            $buttons    = [];

            if(!isset($this->config[$this->entity_id_name]) || !$this->config[$this->entity_id_name]) return [];

            $defined_secret_key = isset($this->config["client_secret_key"]) && $this->config["client_secret_key"];

            if($defined_secret_key)
            {
                $buttons['restart']     = [
                    'attributes'=> [
                        'id' => "vpsrestart",
                        'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('restart',this);",
                    ],
                    'icon'  => 'fa fa-refresh',
                    'text'  => "Restart",
                    'type'  => 'transaction',
                ];

                $server_status = $this->get_status();

                if(!$this->error)
                {
                    if($server_status)
                        $buttons['stop']     = [
                            'attributes'=> [
                                'id' => "vpsstop",
                                'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('stop',this);",
                            ],
                            'icon'      => 'fa fa-pause-circle',
                            'text'      => "Stop",
                            'type'      => 'transaction',
                        ];
                    else
                        $buttons['start']     = [
                            'attributes' => [
                                'id' => 'vpsstart',
                                'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('start',this);",
                            ],
                            'icon'  => 'fa fa-play-circle',
                            'text'  => "Start",
                            'type'  => 'transaction',
                        ];
                }
            }

            if($defined_secret_key)
                $buttons['kill']     = [
                    'attributes'=> [
                        'id' => "vpskill",
                        'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('kill',this);",
                    ],
                    'icon'  => 'fa fa-ban',
                    'text'  => "Kill",
                    'type'  => 'transaction',
                ];

            $buttons['reinstall']     = [
                'attributes'=> [
                    'id' => "vpsreinstall",
                    'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('reinstall',this);",
                ],
                'icon'      => 'fa fa-wrench',
                'text'  => "Reinstall",
                'type'  => 'transaction',
            ];

            return $buttons;
        }

        public function use_clientArea_change_password()
        {
            if(!Filter::isPOST()) return false;
            $password       = Filter::init("POST/password","password");

            if(!$password){
                echo Utility::jencode([
                    'status' => "error",
                    'message' => $this->lang["error"],
                ]);
                return false;
            }

            $serverDetail   = $this->api->GetServerDetail($this->config[$this->entity_id_name]);
            $userId         = $serverDetail["attributes"]["user"];
            $userResult     = $this->api->call("users/".$userId);

            if(!$userResult)
            {
                $this->error = $this->api->error;
                return false;
            }

            $updateResult           = $this->api->call('users/' . $userId,
                [
                    'username'      => $userResult['attributes']['username'],
                    'email'         => $userResult['attributes']['email'],
                    'first_name'    => $userResult['attributes']['first_name'],
                    'last_name'     => $userResult['attributes']['last_name'],
                    'password'      => $password,
                ],
                'PATCH'
            );
            if(!$updateResult)
            {
                $this->error = $this->lang["error1"].': '.$this->api->error;
                return false;
            }

            $password_e         = $this->encode_str($password);

            $found_orders     = WDB::select("id,options")->from("users_products");
            $found_orders->where("owner_id","=",$this->user["id"],"&&");
            $found_orders->where("id","!=",$this->order["id"],"&&");
            $found_orders->where("type","=","server","&&");
            $found_orders->where("module","=","Pterodactyl","&&");
            $found_orders->where("options","NOT LIKE",'%"login":{"username":"","password":""}%',"&&");
            $found_orders->where("(");
            $found_orders->where("options","LIKE",'%"server_id":'.$this->server["id"].'%',"||");
            $found_orders->where("options","LIKE",'%"server_id":"'.$this->server["id"].'"%');
            $found_orders->where(")");
            $found_orders->order_by("id DESC");
            $found_orders->limit(1);
            if(WDB::build())
            {
                foreach(WDB::fetch_assoc() AS $found_order)
                {
                    $_options = Utility::jdecode($found_order["options"],true);
                    $_options["login"] = [
                        'username'      => $this->user["email"],
                        'password'      => $password_e,
                    ];
                    Orders::set($found_order["id"],['options' => Utility::jencode($_options)]);
                }
            }


            if(!isset($this->options["login"])) $this->options["login"] = [];

            $this->options["login"]["password"] = $password_e;

            # users_products.options save data
            Orders::set($this->order["id"],['options' => Utility::jencode($this->options)]);

            // Save Action Log
            $u_data     = UserManager::LoginData("member");
            $user_id    = $u_data["id"];
            User::addAction($user_id,'transaction','The server password for service #'.$this->order["id"].' has been changed.');
            Orders::add_history($user_id,$this->order["id"],'server-order-password-changed');

            echo Utility::jencode([
                'status'    => "successful",
                'message'   => $this->lang["successful"],
                'timeRedirect' => ['url' => $this->area_link, 'duration' => 3000],
            ]);

            return true;
        }

        public function use_clientArea_start()
        {
            if($this->power_change("start")){
                $u_data     = UserManager::LoginData('member');
                $user_id    = $u_data['id'];
                User::addAction($user_id,'transaction','The command "start" has been sent for service #'.$this->order["id"].' on the module.');
                Orders::add_history($user_id,$this->order["id"],'server-order-start');
                return true;
            }
            return false;
        }

        public function use_clientArea_stop()
        {
            if($this->power_change("stop")){
                $u_data     = UserManager::LoginData('member');
                $user_id    = $u_data['id'];
                User::addAction($user_id,'transaction','The command "stop" has been sent for service #'.$this->order["id"].' on the module.');
                Orders::add_history($user_id,$this->order["id"],'server-order-stop');
                return true;
            }
            return false;
        }

        public function use_clientArea_restart()
        {
            if($this->power_change("restart")){
                $u_data     = UserManager::LoginData('member');
                $user_id    = $u_data['id'];
                User::addAction($user_id,'transaction','The command "restart" has been sent for service #'.$this->order["id"].' on the module.');
                Orders::add_history($user_id,$this->order["id"],'server-order-restart');
                return true;
            }
            return false;
        }
        public function use_clientArea_kill()
        {
            if($this->power_change("kill")){
                $u_data     = UserManager::LoginData('member');
                $user_id    = $u_data['id'];
                User::addAction($user_id,'transaction','The command "kill" has been sent for service #'.$this->order["id"].' on the module.');
                Orders::add_history($user_id,$this->order["id"],'Kill command sent');
                return true;
            }
            return false;
        }
        public function use_clientArea_reinstall()
        {
            if($this->reinstall()){
                $u_data     = UserManager::LoginData('member');
                $user_id    = $u_data['id'];
                User::addAction($user_id,'transaction','The command "reinstall" has been sent for service #'.$this->order["id"].' on the module.');
                Orders::add_history($user_id,$this->order["id"],'Reinstall command sent');
                return true;
            }
            return false;
        }


        public function use_clientArea_SingleSignOn()
        {
            $hostname       = $this->api->GetHostname();
            $serverData     = $this->api->GetServerDetail($this->config[$this->entity_id_name],true);
            if($this->api->status_code === 404 || !isset($serverData['attributes']['id']))
                $url        = $hostname;
            else
                $url        = $hostname . '/server/' . $serverData['attributes']['identifier'];


            Utility::redirect($url);

            echo "Redirecting...";
        }

        public function use_adminArea_SingleSignOn()
        {
            $serverId = $this->config[$this->entity_id_name];

            if(!$serverId) return false;
            $hostname = $this->api->GetHostname();
            Utility::redirect($hostname.'/admin/servers/view/' . $serverId);

            echo 'Redirecting...';
        }

        public function use_adminArea_generate_client_secret_key()
        {
            if(!isset($this->options["login"]["username"]) || !$this->options["login"]["username"] || !isset($this->options["login"]["password"]) || !$this->options["login"]["password"])
            {
                echo Utility::jencode([
                    'status' => "error",
                    'message' => $this->lang["error4"],
                ]);
                return false;
            }

            if(!isset($this->config[$this->entity_id_name]) || !$this->config[$this->entity_id_name])
            {
                echo Utility::jencode([
                    'status' => "error",
                    'message' => $this->lang["error5"],
                ]);
                return false;
            }
            $login                  = $this->options["login"];
            $login["password"]      = $this->decode_str($login["password"]);

            $generate               = $this->create_client_api_key($login);

            if(!$generate)
            {
                echo Utility::jencode([
                    'status' => "error",
                    'message' => $this->error,
                ]);
                return false;
            }

            echo Utility::jencode([
                'status' => "generated",
                'javascript_code' => "$('input[name=\'config[client_secret_key]\']').val('".$generate."');",
            ]);
        }

        public function use_adminArea_start()
        {
            $this->area_link .= '?content=automation';
            if($this->power_change("start")){
                $u_data     = UserManager::LoginData('admin');
                $user_id    = $u_data['id'];
                User::addAction($user_id,'transaction','The command "start" has been sent for service #'.$this->order["id"].' on the module.');
                Orders::add_history($user_id,$this->order["id"],'server-order-start');
                return true;
            }
            return false;
        }
        public function use_adminArea_stop()
        {
            $this->area_link .= '?content=automation';
            if($this->power_change("stop"))
            {
                $u_data     = UserManager::LoginData('admin');
                $user_id    = $u_data['id'];
                User::addAction($user_id,'transaction','The command "stop" has been sent for service #'.$this->order["id"].' on the module.');
                Orders::add_history($user_id,$this->order["id"],'server-order-stop');
                return true;
            }
            return false;
        }
        public function use_adminArea_restart()
        {
            $this->area_link .= '?content=automation';
            if($this->power_change("restart")){
                $u_data     = UserManager::LoginData('admin');
                $user_id    = $u_data['id'];
                User::addAction($user_id,'transaction','The command "restart" has been sent for service #'.$this->order["id"].' on the module.');
                Orders::add_history($user_id,$this->order["id"],'server-order-restart');
                return true;
            }
            return false;
        }

        public function use_adminArea_kill()
        {
            $this->area_link .= '?content=automation';
            if($this->power_change("kill")){
                $u_data     = UserManager::LoginData('admin');
                $user_id    = $u_data['id'];
                User::addAction($user_id,'transaction','The command "kill" has been sent for service #'.$this->order["id"].' on the module.');
                Orders::add_history($user_id,$this->order["id"],'Kill command sent');
                return true;
            }
            return false;
        }
        public function use_adminArea_reinstall()
        {
            $this->area_link .= '?content=automation';
            if($this->reinstall()){
                $u_data     = UserManager::LoginData('admin');
                $user_id    = $u_data['id'];
                User::addAction($user_id,'transaction','The command "reinstall" has been sent for service #'.$this->order["id"].' on the module.');
                Orders::add_history($user_id,$this->order["id"],'Reinstall command sent');
                return true;
            }
            return false;
        }


        public function adminArea_service_fields(){
            return [];
        }

        public function adminArea_statistics()
        {
            $_data = [];
            $serverId           = $this->config[$this->entity_id_name];

            if(!$serverId) return false;
            $serverDetail       = $this->api->GetServerDetail($serverId);

            if($serverDetail && isset($this->config["client_secret_key"]) && $this->config["client_secret_key"])
            {
                $identifier     = $serverDetail["attributes"]["identifier"];
                $ram_limit      = $serverDetail["attributes"]["limits"]["memory"];
                $cpu_limit      = $serverDetail["attributes"]["limits"]["cpu"];
                $hdd_limit      = $serverDetail["attributes"]["limits"]["disk"];
                $this->api->client_secret_key = $this->config["client_secret_key"];
                $resources      = $this->api->client_call('servers/'.$identifier.'/resources');

                if($resources)
                {
                    $resources  = $resources["attributes"]["resources"];
                    $cpu_used   = $resources["cpu_absolute"];
                    $hdd_used   = substr(FileManager::formatByte($resources["disk_bytes"]),0,-3);
                    $ram_used   = substr(FileManager::formatByte($resources["memory_bytes"]),0,-3);

                    if($cpu_used == 'NAN' || $cpu_used == 'N') $cpu_used = 0;
                    if($hdd_used == 'NAN' || $hdd_used == 'N') $hdd_used = 0;
                    if($ram_used == 'NAN' || $ram_used == 'N') $ram_used = 0;


                    $_data['hdd_limit']         = $hdd_limit ? round($hdd_limit) : 0;
                    $_data['cpu_limit']         = $cpu_limit ? round($cpu_limit) : 0;
                    $_data['ram_limit']         = $ram_limit ? round($ram_limit) : 0;
                    $_data['hdd_used']          = $hdd_used ? round($hdd_used) : 0;
                    $_data['cpu_used']          = $cpu_used ? round($cpu_used) : 0;
                    $_data['ram_used']          = $ram_used ? round($ram_used) : 0;
                }
            }
            return $_data;
        }

        public function save_adminArea_service_fields($data=[]){
            $c_info         = $data['creation_info'];
            $config         = $data['config'];
            $serverId       = isset($this->config[$this->entity_id_name]) ? $this->config[$this->entity_id_name] : 0;
            $new_login      = false;


            if($serverId)
            {
                if(isset($c_info["new_password"]) && $c_info["new_password"])
                {
                    $serverDetail   = $this->api->GetServerDetail($this->config[$this->entity_id_name]);
                    $userId         = $serverDetail["attributes"]["user"];
                    $userResult     = $this->api->call("users/".$userId);
                    $new_password   = Filter::password($c_info["new_password"]);

                    if(!$userResult)
                    {
                        $this->error = $this->api->error;
                        return false;
                    }

                    $updateResult           = $this->api->call('users/' . $userId,
                        [
                            'username'      => $userResult['attributes']['username'],
                            'email'         => $userResult['attributes']['email'],
                            'first_name'    => $userResult['attributes']['first_name'],
                            'last_name'     => $userResult['attributes']['last_name'],
                            'password'      => $new_password,
                        ],
                        'PATCH'
                    );
                    if(!$updateResult)
                    {
                        $this->error = $this->lang["error1"].': '.$this->api->error;
                        return false;
                    }
                    unset($c_info["new_password"]);

                    $new_login = [
                        'username'      => $this->user["email"],
                        'password'      => $this->encode_str($new_password),
                    ];

                    $found_orders     = WDB::select("id,options")->from("users_products");
                    $found_orders->where("owner_id","=",$this->user["id"],"&&");
                    $found_orders->where("id","!=",$this->order["id"],"&&");
                    $found_orders->where("type","=","server","&&");
                    $found_orders->where("module","=","Pterodactyl","&&");
                    $found_orders->where("options","NOT LIKE",'%"login":{"username":"","password":""}%',"&&");
                    $found_orders->where("(");
                    $found_orders->where("options","LIKE",'%"server_id":'.$this->server["id"].'%',"||");
                    $found_orders->where("options","LIKE",'%"server_id":"'.$this->server["id"].'"%');
                    $found_orders->where(")");
                    $found_orders->order_by("id DESC");
                    $found_orders->limit(1);
                    if(WDB::build())
                    {
                        foreach(WDB::fetch_assoc() AS $found_order)
                        {
                            $_options = Utility::jdecode($found_order["options"],true);
                            $_options["login"] = $new_login;
                            Orders::set($found_order["id"],['options' => Utility::jencode($_options)]);
                        }
                    }

                }

                $changes    = [];

                foreach(['cpu','disk','memory','swap','io','databases','backups','allocations','oom_disabled'] AS $cr)
                {
                    $old        = isset($this->options["creation_info"][$cr]) ? $this->options["creation_info"][$cr] : '';
                    $new        = isset($c_info[$cr]) ? $c_info[$cr] : '';
                    if($old != $new) $changes[$cr] = $new;
                }

                if($changes)
                {
                    $serverDetail   = $this->api->GetServerDetail($serverId);
                    $memory         = $c_info["memory"];
                    $swap           = $c_info["swap"];
                    $io             = $c_info["io"];
                    $cpu            = $c_info["cpu"];
                    $disk           = $c_info["disk"];
                    $databases      = $c_info["databases"];
                    $allocations    = $c_info["allocations"];
                    $backups        = $c_info["backups"];
                    $oom_disabled   = $c_info["oom_disabled"] ? true : false;

                    $updateData = [
                        'allocation' => $serverDetail['attributes']['allocation'],
                        'memory' => (int) $memory,
                        'swap' => (int) $swap,
                        'io' => (int) $io,
                        'cpu' => (int) $cpu,
                        'disk' => (int) $disk,
                        'oom_disabled' => $oom_disabled,
                        'feature_limits' => [
                            'databases' => (int) $databases,
                            'allocations' => (int) $allocations,
                            'backups' => (int) $backups,
                        ],
                    ];

                    $updateResult = $this->api->call('servers/' . $serverId . '/build', $updateData, 'PATCH');
                    if(!$updateResult)
                    {
                        $this->error = $this->lang["error1"].': '.$this->api->error;
                        return false;
                    }
                }
            }

            $returnData =  [
                'creation_info'     => $c_info,
                'config'            => $config,
            ];

            if($new_login) $returnData["login"] = $new_login;

            return $returnData;
        }

    }