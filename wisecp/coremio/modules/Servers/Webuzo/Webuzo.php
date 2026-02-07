<?php
    class Webuzo_Module extends ServerModule
    {
        private WebuzoSDK $api;
        private $details = [];

        function __construct($server,$options=[])
        {
            $this->_name = __CLASS__;
            $this->force_setup  = false;
            parent::__construct($server,$options);
        }

        protected function define_server_info($server=[])
        {

            if(!class_exists("WebuzoSDK")) include __DIR__.DS."sdk.php";
            $this->api = new WebuzoSDK($server);
        }

        public function config_options($data=[])
        {
            return [
                'plan'          => [
                    'wrap_width'        => 100,
                    'name'              => "Plan",
                    'type'              => "text",
                    'value'             => $data["plan"] ?? "",
                    'placeholder'       => "",
                ],
            ];
        }

        public function testConnect(){

            try
            {
                $servers    = $this->api->call("users");

                if(!$servers && $this->api->error) throw new Exception($this->api->error);

            }
            catch(Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }
        
        public function generate_username($domain=''){
            $exp            = explode(".",$domain);
            $domain         = Filter::transliterate($exp[0]);
            $username       = $domain;
            $fchar          = substr($username,0,1);
            $size           = strlen($username);
            if($fchar == "0" || (int)$fchar)
                $username   = Utility::generate_hash(1,false,"l").substr($username,1,$size-1);

            if($size>=8){
                $username   = substr($username,0,5);
                $username .= Utility::generate_hash(3,false,"l");
            }elseif($size>4 && $size<9){
                $username   = substr($username,0,5);
                $username .= Utility::generate_hash(3,false,"l");
            }elseif($size>=1 && $size<5){
                $how        = (8 - $size);
                $username   = substr($username,0,$size);
                $username .= Utility::generate_hash($how,false,"l");
            }

            return $username;
        }

        public function create($domain = '',array $order_options=[])
        {
            $username       = $this->generate_username($domain);
            $password       = Utility::generate_hash(12);


            if(isset($order_options["username"]) && $order_options["username"]) $username = $order_options["username"];
            if(isset($order_options["password"]) && $order_options["password"]) $password = $order_options["password"];

            $username       = str_replace("-","",$username);
            $creation_info  = isset($order_options["creation_info"]) ? $order_options["creation_info"] : [];

            try
            {
                $apply      = $this->api->call("add_user",[
                    'domain'           => $domain,
                    'create_user'      => 1,
                    'user'             => $username,
                    'user_passwd'      => $password,
                    'cnf_user_passwd'  => $password,
                    'email'            => $this->user["email"],
                    'plan'             => $creation_info["plan"] ?? '',
                    'billing_prefill'  => 1,
                ]);

                if(!$apply) throw new Exception($this->api->error);


                return [
                    'username' => $username,
                    'password' => $password,
                ];
            }
            catch (Exception $e){
                $this->error = $e->getMessage();
                return false;
            }


        }

        public function suspend()
        {
            try
            {
                $apply = $this->api->call("users",[
                    'suspend' => $this->order["options"]["config"]["user"] ?? $this->order["options"]["domain"],
                ]);

                if(!$apply) throw new Exception($this->api->error);
            }
            catch (Exception $e){
                $this->error = $e->getMessage();
                return false;
            }
        }

        public function unsuspend()
        {
            try
            {
                $apply = $this->api->call("users",[
                    'unsuspend' => $this->order["options"]["config"]["user"] ?? $this->order["options"]["domain"],
                ]);

                if(!$apply) throw new Exception($this->api->error);
            }
            catch (Exception $e){
                $this->error = $e->getMessage();
                return false;
            }
        }

        public function terminate()
        {
            try
            {
                $apply = $this->api->call("users",[
                    'delete_user' => $this->order["options"]["config"]["user"] ?? $this->order["options"]["domain"],
                ]);

                if(!$apply) throw new Exception($this->api->error);
            }
            catch (Exception $e){
                $this->error = $e->getMessage();
                return false;
            }
        }


        public function change_password($password=''){
            try
            {
                $user   = $this->order["options"]["config"]["user"] ?? $this->order["options"]["domain"];
                $apply  = $this->api->call("add_user",[
                    'domain'            => $this->options["domain"] ?? $this->order["options"]["domain"],
                    'edit_user'         => 1,
                    'email'             => $this->user["email"],
                    'plan'              => $this->options["creation_info"]["plan"] ?? "",
                    'user'              => $user,
                    'user_name'         => $user,
                    'user_passwd'       => $password,
                    'cnf_user_passwd'   => $password,
                ]);

                if(!$apply) throw new Exception($this->api->error);
            }
            catch (Exception $e){
                $this->error = $e->getMessage();
                return false;
            }
        }

        public function apply_updowngrade($orderopt=[],$product=[]){
            $o_creation_info        = $orderopt["creation_info"];
            $p_creation_info        = $product["module_data"];
            $plan                   = $p_creation_info["plan"] ?? 'none';

            try
            {
                $user   = $this->order["options"]["config"]["user"] ?? $this->order["options"]["domain"];
                $apply  = $this->api->call("add_user",[
                    'edit_user'     => 1,
                    'user'          => $user,
                    'user_name'     => $user,
                    'plan'          => $plan,
                ]);
                if(!$apply) throw new Exception($this->api->error);
            }
            catch (Exception $e){
                $this->error = $e->getMessage();
                return false;
            }
        }


        public function listAccounts($page=1){

            try
            {
                $users      = $this->api->call("users",[
                    'page'      => $page,
                    'reslen'    => 50,
                ]);

                if(!$users && $this->api->error) throw new Exception($this->api->error);

                if($users) $users = $users["users"];

                if($users && sizeof($users) == 50)
                {
                    $get_next_users = $this->listAccounts(($page+1));
                    if($get_next_users) $users = array_merge($users,$get_next_users);
                }

                $accounts = [];

                if($users)
                {
                    foreach($users AS $u)
                    {
                        $accounts[] = [
                            'cdate'     => '',
                            'domain'    => $u["domain"],
                            'username'  => $u["user"],
                            'plan'      => $u["plan"],
                            'suspended' => false,
                        ];
                    }
                }

                return $accounts;
            }
            catch (Exception $e){
                $this->error = $e->getMessage();
                return false;
            }
        }

        public function use_clientArea_SingleSignOn()
        {
            $config = include __DIR__.DS."config.php";

            if($this->server["secure"])
                $port = $config["client-secure-port"];
            else
                $port = $config["client-not-secure-port"];

            $this->api->server["port"]  = $port;

            $user       = $this->order["options"]["config"]["user"] ?? $this->order["options"]["domain"];
            $sso        = $this->api->call("sso",['loginAs' => $user,'noip' => 1]);

            if(!$sso)
            {
                echo 'Error : '.($this->api->error ?: 'Unknown error');
                return false;
            }


            $url        = $sso["done"]["url"] ?? '';

            if(!$url)
            {
                echo 'Error : '.($this->api->error ?: 'Unknown error');
                return false;
            }

            Utility::redirect($url);

            echo "Redirecting...";
        }

        public function use_adminArea_SingleSignOn()
        {
            return $this->use_clientArea_SingleSignOn();
        }

        public function use_adminArea_root_SingleSignOn()
        {
            $sso            = $this->api->call("sso",['noip' => 1]);

            if(!$sso)
            {
                echo 'Error : '.($this->api->error ?: 'Unknown error');
                return false;
            }


            $url        = $sso["done"]["url"] ?? '';

            if(!$url)
            {
                echo 'Error : '.($this->api->error ?: 'Unknown error');
                return false;
            }

            Utility::redirect($url);

            echo "Redirecting...";
        }

        public function getDisk(){
            try
            {
                $user = $this->order["options"]["config"]["user"] ?? $this->order["options"]["domain"];

                if(!$this->details)
                {
                    $request = $this->api->call("users",[
                        'search' => $user,
                    ]);

                    if(!$request) throw new Exception($this->api->error);
                    if(!isset($request["users"][$user])) throw new Exception("User not found");

                    $this->details = $request["users"][$user];
                }
            }
            catch (Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            $result             = $this->details;

            $limit              = $result["resource"]["disk"]["limit_bytes"];
            $used               = $result["resource"]["disk"]["used_bytes"];
            $format_limit       = $result["resource"]["disk"]["limit"];
            $format_used        = $result["resource"]["disk"]["used"];
            $percent            = $result["resource"]["disk"]["percent"];

            if($limit == "unlimited") $limit = 0;


            return [
                'limit' => $limit,
                'used'  => $used,
                'used-percent' => $percent,
                'format-limit' => $limit ? $format_limit : "∞",
                'format-used' => $used ? $format_used : "0MB",
            ];
        }

        public function getBandwidth(){
            try
            {
                $user = $this->order["options"]["config"]["user"] ?? $this->order["options"]["domain"];

                if(!$this->details)
                {
                    $request = $this->api->call("users",[
                        'search' => $user,
                    ]);

                    if(!$request) throw new Exception($this->api->error);
                    if(!isset($request["users"][$user])) throw new Exception("User not found");

                    $this->details = $request["users"][$user];
                }
            }
            catch (Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            $result             = $this->details;

            $limit              = $result["resource"]["bandwidth"]["limit_bytes"];
            $used               = $result["resource"]["bandwidth"]["used_bytes"];
            $format_limit       = $result["resource"]["bandwidth"]["limit"];
            $format_used        = $result["resource"]["bandwidth"]["used"];
            $percent            = $result["resource"]["bandwidth"]["percent"];

            if($limit == "unlimited") $limit = 0;

            return [
                'limit' => $limit,
                'used'  => $used,
                'used-percent' => $percent,
                'format-limit' => $limit ? $format_limit : "∞",
                'format-used' => $used ? $format_used : "0MB",
            ];
        }


    }