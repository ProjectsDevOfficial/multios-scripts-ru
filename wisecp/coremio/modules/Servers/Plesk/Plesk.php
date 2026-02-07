<?php

    class Plesk_Module {
        private $client,$caches,$server;
        private $storage=[];
        public $config=[],$options=[];
        public $lang,$error;
        public $area_link;
        public $user;
        public $order;
        public $force_setup = false;

        function __construct($server,$options=[]){
            if(!class_exists("PleskApi\Client")) include __DIR__.DS."init.php";

            $this->client       =  new \PleskApi\Client($server["ip"],$server["port"],$server["secure"] ? "https" : "http");
            $this->client->set_credentials($server["username"],$server["password"]);
            $this->server       = $server;

            $config             = Modules::Config("Servers","Plesk");
            $config["owner"]    = $server["username"];
            $config["ip"]       = $server["ip"];
            if(isset($options["config"])){
                $this->options = $options;
                $external_config = $options["config"];
            }else $external_config = $options;
            $this->config       = array_merge($config,$external_config);
            $this->lang         = Modules::Lang("Servers","Plesk");
        }

        public function randomPassword(){
            return Orders::generate_password(5).Orders::generate_password(3,false,'l').Orders::generate_password_force(3,false,'s').Orders::generate_password(2,false,'u').Orders::generate_password(3,false,'d');
        }

        public function object_to_array($node=''){
            return Utility::jdecode(Utility::jencode($node),true);
        }

        public function limit_converter($value=''){
            if($value == -1) return "unlimited";
            return $value;
        }

        public function limit_converter_reverse($value=''){
            if($value == "unlimited") return -1;
            return $value;
        }

        public function testConnect(){
            try{

                $this->client->operator("customer")->_getItems('gen_info');

                return true;

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            $this->error = "Connection Failed";
            return false;
        }

        public function activation_infos($type='html',$order=[],$lang=''){
            $this->lang     = Modules::Lang("Servers","Plesk",$lang);
            $options        = $order["options"];
            if(isset($options["config"]["password"]))
                $options["config"]["password"] = Crypt::decode($options["config"]["password"],Config::get("crypt/user"));
            if(isset($options["ftp_info"]["password"]))
                $options["ftp_info"]["password"] = Crypt::decode($options["ftp_info"]["password"],Config::get("crypt/user"));
            $data           = [
                'module'    => $this,
                'options'   => $options,
                'server'    => $this->server,
            ];
            return Modules::getPage("Servers","Plesk","activation-".$type,$data);
        }

        public function getPlans(){

            try{
                $getData  = $this->client->operator("service-plan")->_getItems();
                $getData  = $this->object_to_array($getData);
            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            if($getData){
                $plans = [];
                foreach($getData AS $plan){
                    $item = [
                        'name'      => $plan["name"],
                        'guid'      => $plan["guid"],
                    ];

                    if(isset($plan["limits"]["limit"])){
                        foreach($plan["limits"]["limit"] AS $limit){
                            if($limit["name"] == "max_site")
                                $item["maxaddon"] = $this->limit_converter($limit["value"]);
                            elseif($limit["name"] == "max_subdom")
                                $item["maxsubdomain"] = $this->limit_converter($limit["value"]);
                            elseif($limit["name"] == "max_subftp_users")
                                $item["maxftp"] = $this->limit_converter($limit["value"]);
                            elseif($limit["name"] == "max_db")
                                $item["maxdb"] = $this->limit_converter($limit["value"]);
                            elseif($limit["name"] == "max_box")
                                $item["maxpop"] = $this->limit_converter($limit["value"]);
                            elseif($limit["name"] == "disk_space" || $limit["name"] == "max_traffic"){
                                $value = $this->limit_converter($limit["value"]);
                                if($value != "unlimited") $value = FileManager::showMB($value);
                                $item[$limit["name"]] = $value;
                            }
                        }
                    }
                    $plans[] = $item;
                }

                return $plans;
            }else
                return [];
        }
        public function getResellerPlans(){
            try{
                $getData  = $this->client->operator("reseller-plan")->_getItems(
                    [
                        'limits'
                    ],
                    [
                        'all' => NULL
                    ]
                );
                $getData  = $this->object_to_array($getData);
            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            if($getData){
                $plans = [];
                foreach($getData AS $plan){
                    $item = [
                        'name'      => $plan["name"],
                        'guid'      => $plan["guid"],
                    ];
                    if(isset($plan["limits"]["limit"])){
                        foreach($plan["limits"]["limit"] AS $limit){
                            if($limit["name"] == "max_site")
                                $item["maxaddon"] = $this->limit_converter($limit["value"]);
                            elseif($limit["name"] == "max_subdom")
                                $item["maxsubdomain"] = $this->limit_converter($limit["value"]);
                            elseif($limit["name"] == "max_subftp_users")
                                $item["maxftp"] = $this->limit_converter($limit["value"]);
                            elseif($limit["name"] == "max_db")
                                $item["maxdb"] = $this->limit_converter($limit["value"]);
                            elseif($limit["name"] == "max_box")
                                $item["maxpop"] = $this->limit_converter($limit["value"]);
                            elseif($limit["name"] == "disk_space" || $limit["name"] == "max_traffic"){
                                $value = $this->limit_converter($limit["value"]);
                                if($value != "unlimited") $value = FileManager::showMB($value);
                                $item[$limit["name"]] = $value;
                            }else
                                $item[$limit["name"]] = $this->limit_converter($limit["value"]);
                        }
                    }
                    $plans[] = $item;
                }
                return $plans;
            }else
                return [];
        }

        public function set_order($order=[]){
            $this->order =  $order;
            Helper::Load(["Products","User","Orders"]);
            $this->product      = Products::get($order["type"],$order["product_id"]);
            $this->user    = User::getData($order["owner_id"],"id,name,surname,company_name,full_name,email,phone,lang,country","array");
            $this->user    = array_merge($this->user,User::getInfo($order["owner_id"],["gsm_cc","gsm_number"]));
            $this->user["address"] = AddressManager::getAddress(false,$order["owner_id"]);

            $configurable_options = [];
            if($addons = Orders::addons($this->order["id"])){
                $lang   = $this->user["lang"];
                foreach($addons AS $addon){
                    if($gAddon = Products::addon($addon["addon_id"],$lang)){
                        $addon["attributes"] = $gAddon;
                        $this->addons[$addon["id"]] = $addon;
                        if($gAddon["options"]){
                            if($gAddon["type"] == "quantity" || $addon["option_quantity"] > 0){
                                if($addon["option_quantity"] > 0)
                                    $addon_v = (int) $addon["option_quantity"];
                                else
                                {
                                    $addon_v    = $addon["option_name"];
                                    $addon_v    = explode("x",$addon_v);
                                    $addon_v    = (int) trim($addon_v[0]);
                                }
                            }
                            else
                                $addon_v        = '';
                            foreach($gAddon["options"] AS $option){
                                if($option["id"] == $addon["option_id"]){
                                    if(isset($option["module"]) && $option["module"]){
                                        if(isset($option["module"][$this->_name]))
                                        {
                                            $c_options = $option["module"][$this->_name]["configurable"];
                                            foreach($c_options AS $k=>$v)
                                            {
                                                $d_v = $v;
                                                if(strlen($addon_v) > 0)
                                                    $d_v = $addon_v;

                                                if(!in_array($addon['status'],["cancelled","waiting"]))
                                                {
                                                    if(isset($configurable_options[$k]) && strlen($addon_v)>0)
                                                        $configurable_options[$k] += $d_v;
                                                    else
                                                        $configurable_options[$k] = $d_v;
                                                }

                                                $this->id_of_conf_opt[$addon["id"]][$k] = $d_v;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $this->val_of_conf_opt = $configurable_options;

            $values_of_requirements = [];
            if($requirements = Orders::requirements($this->order["id"]))
            {
                $this->requirements = $requirements;
                foreach($requirements AS $req)
                {
                    if($req["module_co_names"])
                    {
                        $req["module_co_names"] = Utility::jdecode($req["module_co_names"],true);
                        if(isset($req["module_co_names"][$this->_name]))
                        {
                            $c_o_name = $req["module_co_names"][$this->_name];
                            if(in_array($req["response_type"],['input','textarea','file']))
                                $response = $req["response"];
                            else
                            {
                                $mkey     = $req["response_mkey"];
                                if($dc    = Utility::jdecode($mkey,true)) $mkey = $dc;
                                $response = is_array($mkey) && sizeof($mkey) < 2 ? current($mkey) : $mkey;
                            }
                            $values_of_requirements[$c_o_name] = $response;
                        }
                    }
                }
            }
            $this->val_of_requirements = $values_of_requirements;
            if(!$this->options) $this->options = $this->order["options"];
            $this->config = array_merge($this->config,isset($this->options["config"]) ? $this->options["config"] : []);
        }

        public function createAccount($domain,$options=[])
        {
            $domain         = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $username       = $this->UsernameGenerator($domain);
            if(isset($options["username"])) $username = $options["username"];


            if(isset($options["password"]) && $options["password"]) $password = $options["password"];
            else $password = $this->randomPassword();

            $disk_limit             = $options["disk_limit"] ?? 'unlimited';
            $bandwidth_limit        = $options["bandwidth_limit"] ?? 'unlimited';

            if($disk_limit == "unlimited") $disk_limit = 0;
            if($bandwidth_limit == "unlimited") $bandwidth_limit = 0;

            $limits = [
                'disk_space' => $this->limit_converter_reverse($disk_limit > 0 ? FileManager::converByte($disk_limit."MB") : 0),
                'max_traffic' => $this->limit_converter_reverse($bandwidth_limit > 0 ? FileManager::converByte($bandwidth_limit."MB") : 0),
                'max_subftp_users' => $this->limit_converter_reverse(isset($options["ftp_limit"]) ? $options["ftp_limit"] : 0),
                'max_subdom' => $this->limit_converter_reverse(isset($options["subdomain_limit"]) ? $options["subdomain_limit"] : 0),
                'max_db' => $this->limit_converter_reverse(isset($options["database_limit"]) ? $options["database_limit"] : 0),
                'max_box' => $this->limit_converter_reverse(isset($options["email_limit"]) ? $options["email_limit"] : 0),
                'max_site' => $this->limit_converter_reverse(isset($options["addons_limit"]) ? $options["addons_limit"] : 0),
            ];



            try{

                $creation_info  = isset($options["creation_info"]) ? $options["creation_info"] : [];
                $plan           = isset($creation_info["plan"]) ? $creation_info["plan"] : NULL;
                $rplan          = isset($creation_info["reseller_plan"]) ? $creation_info["reseller_plan"] : NULL;
                $reseller       = isset($creation_info["reseller"]) && $creation_info["reseller"];
                $clmt           = isset($creation_info["account_limit"]) ? $creation_info["account_limit"] : -1;
                $domlmt         = isset($creation_info["domain_limit"]) ? $creation_info["domain_limit"] : -1;

                if($clmt == '') $clmt = -1;
                if($domlmt == '') $domlmt = -1;


                $gen_info = [
                    'cname' => $this->user["company_name"],
                    'pname' => $this->user["full_name"],
                    'login' => $username,
                    'passwd' => $password,
                    'status' => 0,
                    'phone'  => $this->user["phone"],
                    'fax'    => '',
                    'email'  => $this->user["email"],
                    'address' => $this->user["address"]["address"],
                    'city'    => $this->user["address"]["counti"],
                    'state'   => $this->user["address"]["city"],
                    'country' => $this->user["address"]["country_code"],
                ];

                if($reseller)
                    $owner   = $this->client->operator("reseller")->create($gen_info,[
                        'plan' => $rplan,
                        'limits' => [
                            "max_cl"    => $clmt,
                            "max_dom"   => $domlmt,
                        ],
                    ]);
                else
                    $owner   = $this->client->operator("customer")->create($gen_info);

            }
            catch(Exception $e){
                $this->error = $e->getMessage();
                return false;
            }


            try
            {
                $this->client->operator("webspace")->create([
                    'ip_address'     => $this->config["ip"],
                    'gen_setup'      => [
                        'name'       => $domain,
                        'owner-id'   => $owner->id,
                        'htype'      => "vrt_hst",
                        'ip_address' => $this->config["ip"],
                        'status'     => 0,
                    ],
                    'limits'         => $limits,
                    'properties'     => [
                        'ftp_login'  => $username,
                        'ftp_password' => $password,
                    ],
                    'plan'          => $reseller ? '' : $plan,
                ]);

            }
            catch(Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return [
                'username' => $username,
                'password' => $password,
                'ftp_info' => [
                    'ip'   => $this->config["ip"],
                    'host' => "ftp.".$domain,
                    'username' => $username,
                    'password' => $password,
                    'port' => 21,
                ],
            ];

        }

        public function UsernameGenerator($domain='',$half_mixed=false){
            $exp            = explode(".",$domain);
            $domain         = Filter::transliterate($exp[0]);
            $username       = $domain;
            $fchar          = substr($username,0,1);
            $size           = strlen($username);
            if($fchar == "0" || (int)$fchar)
                $username   = Utility::generate_hash(1,false,"l").substr($username,1,$size-1);

            /*if($half_mixed){
                $username   = substr($username,0,10);
                if($size>=8){
                    $username   = substr($username,0,4);
                    $username .= Utility::generate_hash(4,false,"d");
                }elseif($size>4 && $size<8){
                    $username   = substr($username,0,5);
                    $username .= Utility::generate_hash(3,false,"d");
                }elseif($size>=1 && $size<5){
                    $how        = (8 - $size);
                    $username   = substr($username,0,$size);
                    $username .= Utility::generate_hash($how,false,"d");
                }
            }else*/if($size>=8){
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

        public function getBandwidth($user=false){
            if(!$user) $user = $this->config["user"];

            $getData = $this->getAcSummary(false,$user);
            if(!$getData) return false;

            $limit  = 0;
            $used   = 0;

            if(isset($getData["data"]["limits"]["limit"]))
                foreach($getData["data"]["limits"]["limit"] as $row)
                    if($row["name"] == "max_traffic")
                        $limit = (int) $row["value"];


            if(isset($getData["data"]["resource-usage"]["resource"]))
                foreach($getData["data"]["resource-usage"]["resource"] as $row)
                    if($row["name"] == "max_traffic")
                        $used = (int) $row["value"];

            if($limit == -1) $limit = 0;

            if($limit && $used) $percent = Utility::getPercent($used,$limit);
            else $percent = 0;
            if($percent>100) $percent = 100;

            return [
                'limit' => $limit>0 ? $limit : 0,
                'used'  => $used>0 ? $used : 0,
                'used-percent' => $percent,
                'format-limit' => $limit>0 ? FileManager::formatByte($limit) : "∞",
                'format-used' => $used>0 ? FileManager::formatByte($used) : "0 KB",
            ];

        }

        public function getDisk(){

            $getData = $this->getAcSummary(false);
            if(!$getData) return false;

            $limit  = 0;
            $used   = 0;

            if(isset($getData["data"]["limits"]["limit"]))
                foreach($getData["data"]["limits"]["limit"] as $row)
                    if($row["name"] == "disk_space")
                        $limit = (int) $row["value"];

            if(isset($getData["data"]["resource-usage"]["resource"]))
                foreach($getData["data"]["resource-usage"]["resource"] as $row)
                    if($row["name"] == "disk_space")
                        $used = (int) $row["value"];

            if($limit == -1) $limit = 0;

            if($limit && $used) $percent = Utility::getPercent($used,$limit);
            else $percent = 0;
            if($percent>100) $percent = 100;

            return [
                'limit' => $limit>0 ? $limit : 0,
                'used'  => $used>0 ? $used : 0,
                'used-percent' => $percent,
                'format-limit' => $limit > 0 ? FileManager::formatByte($limit) : "∞",
                'format-used' => $used > 0 ? FileManager::formatByte($used) : "0 KB",
            ];
        }

        public function getSummary(){
            $getData = $this->getAcSummary(false);
            if(!$getData) return false;
            $email_limit        = 0;
            $database_limit     = 0;
            $addons_limit       = 0;

            if(isset($getData["data"]["limits"]["limit"])){
                foreach($getData["data"]["limits"]["limit"] as $row){
                    if($row["name"] == "max_box")
                        $email_limit = $row["value"] == -1 ? "unlimited" : $row["value"];
                    elseif($row["name"] == "max_db")
                        $database_limit = $row["value"] == -1 ? "unlimited" : $row["value"];
                    elseif($row["name"] == "max_site")
                        $addons_limit = $row["value"] == -1 ? "unlimited" : $row["value"];
                }
            }

            return [
                'database_limit'    => $database_limit,
                'addons_limit'      => $addons_limit,
                'email_limit'       => $email_limit,
            ];
        }

        public function getAcSummary($reload=true,$username=''){
            $username = !$username ? $this->config["user"] : $username;
            if(!isset($this->config["user"])) return false;
            if($reload || (!$reload && !isset($this->storage["accountsummary"]))){

                try{
                    $getData = $this->client->operator("webspace")->_getItems(
                        [
                            'gen_info',
                            'limits',
                            'resource-usage',
                        ],
                        [
                            'owner-login' => $username,
                        ]
                    );
                    $getData = $this->object_to_array($getData)[0];
                }catch(PleskApi\Exception $e){
                    $this->error = $e->getMessage();
                    return false;
                }

                $this->storage["accountsummary"] = $getData;
                return $getData;
            }else
                return $this->storage["accountsummary"];
            return false;
        }

        public function getEmailList(){

            try{

                $domains    = $this->getDomains();
                if(!$domains) return false;

                $data         = [];

                foreach($domains AS $site_id => $domain){
                    $getData    = $this->client->operator("mail")->getItems([
                        'mailbox',
                        'mailbox-usage',
                        'forwarding',
                    ],['site-id' => $site_id]);
                    $getData      = $this->object_to_array($getData);

                    if($getData){
                        foreach($getData AS $row){
                            if(!isset($row["mailname"])) continue;
                            $row_data   = $row["mailname"];
                            $mailbox    = $row_data["mailbox"];

                            $quota_bytes    = $mailbox["quota"];
                            $quota_mb       = $quota_bytes > -1 ? FileManager::showMB($quota_bytes) : 0;
                            $quota_show     = FileManager::formatByte($quota_bytes > -1 ? $quota_bytes : 0);
                            $used_bytes     = $mailbox["usage"];
                            $used_mb        = $used_bytes > -1 ? FileManager::showMB($used_bytes) : 0;
                            $used_show      = FileManager::formatByte($used_bytes > -1 ? $used_bytes : 0);
                            $data[] = [
                                'username'  => $row_data["name"],
                                'domain'    => $domain,
                                'email'     => $row_data["name"]."@".$domain,
                                'used'      => $used_show,
                                'used_mb'   => $used_mb,
                                'limit'     => $quota_bytes == -1 ? "unlimited" : $quota_show,
                                'limit_mb'  => $quota_bytes == -1 ? "unlimited" : $quota_mb,
                            ];

                        }
                    }
                }

                Utility::sksort($data,"username",true);

                return $data;

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                echo $this->error;
                return false;
            }
        }

        public function getForwardsList(){

            try{

                $domains    = $this->getDomains();
                if(!$domains) return false;

                $data         = [];

                foreach($domains AS $site_id=>$domain){
                    $getData    = $this->client->operator("mail")->getItems([
                        'mailbox',
                        'mailbox-usage',
                        'forwarding',
                    ],['site-id' => $site_id]);
                    $getData      = $this->object_to_array($getData);

                    if($getData){
                        foreach($getData AS $row){
                            if(!isset($row["mailname"])) continue;
                            $row_data   = $row["mailname"];
                            $forwarding = $row_data["forwarding"];

                            if($forwarding["enabled"] == "true"){
                                $address    = $forwarding["address"];

                                if(is_string($address))
                                    $data[] = [
                                        'dest' => $row_data["name"]."@".$domain,
                                        'forward' => $address,
                                    ];
                                elseif(is_array($address)){
                                    foreach($address AS $adr){
                                        $data[] = [
                                            'dest' => $row_data["name"]."@".$domain,
                                            'forward' => $adr,
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }

                return $data;

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }
        }

        public function addNewEmail($username,$domain,$password,$quota,$unlimited){
            $quota  = $unlimited == 1 ? -1 : FileManager::converByte($quota."MB");

            try{

                $site       = $this->client->operator("site")->_get(false,['name' => $domain]);
                $site       = $this->object_to_array($site);
                $site       = $site[0];
                $site_id    = (int) $site["id"];

                $this->client->operator("mail")->create([
                    'site_id'   => $site_id,
                    'username'  => $username,
                    'domain'    => $domain,
                    'password'  => $password,
                    'quota'     => $quota,
                ]);

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }

        public function addNewEmailForward($domain,$dest,$forward){

            $split      = explode("@",$dest);
            $dest       = $split[0];

            try{

                $site       = $this->client->operator("site")->_get(false,['name' => $domain]);
                $site       = $this->object_to_array($site);
                $site       = $site[0];
                $site_id    = (int) $site["id"];

                $this->client->operator("mail")->edit_add($site_id,$dest,[
                    'forward' => $forward,
                ]);

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }

        public function setPassword($domain,$name,$password){
            try{
                $site       = $this->client->operator("site")->_get(false,['name' => $domain]);
                $site       = $this->object_to_array($site);
                $site       = $site[0];
                $site_id    = (int) $site["id"];

                $this->client->operator("mail")->edit_set($site_id,$name,['password' => $password]);

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }

        public function setQuota($domain,$name,$quota,$unlimited=0){
            try{
                $site       = $this->client->operator("site")->_get(false,['name' => $domain]);
                $site       = $this->object_to_array($site);
                $site       = $site[0];
                $site_id    = (int) $site["id"];

                $quota = $unlimited == 1 ? -1 : FileManager::converByte($quota."MB");

                $this->client->operator("mail")->edit_set($site_id,$name,['quota' => $quota]);

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }

        public function deleteEmail($domain,$name=''){
            try{
                $site       = $this->client->operator("site")->_get(false,['name' => $domain]);
                $site       = $this->object_to_array($site);
                $site       = $site[0];
                $site_id    = (int) $site["id"];

                $this->client->operator("mail")->delete($site_id,$name);

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }

        public function deleteEmailForward($dest,$forward){
            $split      = explode("@",$dest);
            $dest       = $split[0];
            $domain     = $split[1];

            try{

                $site       = $this->client->operator("site")->_get(false,['name' => $domain]);
                $site       = $this->object_to_array($site);
                $site       = $site[0];
                $site_id    = (int) $site["id"];

                $this->client->operator("mail")->edit_remove($site_id,$dest,[
                    'forward' => $forward,
                ]);

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }

        public function getPasswordStrength($password){
            return 100;
        }

        public function getMailDomains($cache=true){
            return array_values($this->getDomains($cache));
        }

        public function getDomains($cache=true){

            if($cache && isset($this->caches["domains"])) return $this->caches["domains"];

            try{
                $site       = $this->client->operator("site")->_get(false,['name' => $this->options["domain"]]);
                $site       = $this->object_to_array($site);
                $site       = $site[0];
                $site_id    = (int) $site["id"];

                $getData    = $this->client->operator("site")->_getItems('gen_info',['parent-id' => $site_id]);
                $getData    = $this->object_to_array($getData);

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            $data = [$site_id => $this->options["domain"]];
            if($getData) foreach($getData as $row) $data[$row["id"]] = $row["data"]["gen_info"]["name"];

            $this->caches["domains"] = $data;

            return $data;
        }

        public function changePassword($oldpw,$newpw){
            try{
                $summary    = $this->getAcSummary();
                if(!$summary) return false;

                $owner_id    = (int) $summary["data"]["gen_info"]["owner-id"];
                $htype       = $summary["data"]["gen_info"]["htype"];

                $cr_info    = isset($this->options["creation_info"]) ? $this->options["creation_info"] : [];

                if(isset($cr_info["reseller"]) && $cr_info["reseller"])
                    $this->client->operator("reseller")->edit($owner_id,[
                        'gen-info' => [
                            'passwd' => $newpw,
                        ],
                    ]);
                else
                    $this->client->operator("customer")->edit($owner_id,[
                        'gen_info' => [
                            'passwd' => $newpw,
                        ],
                    ]);

                $this->client->operator("webspace")->edit($this->config["user"],[
                    'hosting' => [
                        'htype' => $htype,
                        'properties' => [
                            'ftp_password' => $newpw,
                        ],
                    ],
                ]);

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                if(stristr($this->error,'IP Address is not specified')) return true;
                return false;
            }


            return true;
        }

        public function apply_options($old_options,$new_options=[]){
            $old_config     = isset($old_options["config"]) ? $old_options["config"] : [];
            $new_config     = isset($new_options["config"]) ? $new_options["config"] : [];

            $old_c_info     = isset($old_options["creation_info"]) ? $old_options["creation_info"] : [];
            $new_c_info     = isset($new_options["creation_info"]) ? $new_options["creation_info"] : [];

            $old_c_user     = isset($old_config["user"]) ? $old_config["user"] : '';
            $new_c_user     = isset($new_config["user"]) ? $new_config["user"] : '';

            $old_reseller   = isset($old_c_info["reseller"]) ? $old_c_info["reseller"] : false;
            $new_reseller   = isset($new_c_info["reseller"]) ? $new_c_info["reseller"] : false;

            $old_plan       = isset($old_c_info["plan"]) ? $old_c_info["plan"] : false;
            $new_plan       = isset($new_c_info["plan"]) ? $new_c_info["plan"] : false;

            if($old_c_user !== $new_c_user && $new_c_user){
                try{
                    if(!$old_c_user) $this->config["user"] = $new_c_user;
                    $summary    = $this->getAcSummary();
                    if(!$summary) return false;
                }catch(PleskApi\Exception $e){
                    $this->error = $e->getMessage();
                    return false;
                }
            }

            $old_password           = isset($old_config["password"]) ? $old_config["password"] : '';
            $password               = Filter::password($new_config["password"]);
            $c_password             = $password ? Crypt::encode($password,Config::get("crypt/user")) : '';
            $new_config["password"] = $c_password;

            if($new_c_user && $password && $c_password != $old_password){

                if(Utility::strlen($password) < 5){
                    $this->error = __("admin/orders/error8");
                    return false;
                }
                $strength = $this->getPasswordStrength($password);
                if(!$strength) return false;

                if($strength < 65){
                    $this->error = __("admin/orders/error9");
                    return false;
                }

                $changed    = $this->changePassword(false,$password);
                if(!$changed) return false;
            }


            if($old_options["domain"] != $new_options["domain"] && method_exists($this,'modifyDomain')){
                $changed    = $this->modifyDomain($new_options["domain"]);
                if(!$changed) return false;
            }

            if($new_c_user && $new_reseller && $old_c_info != $new_c_info){
                if($old_reseller)
                    $change  = $this->setReseller($new_config["user"],$new_c_info);
                else
                    $change  = $this->setupReseller($new_config["user"],$new_c_info);

                if(!$change) return false;
            }

            if($new_c_user && $old_reseller && !$new_reseller && method_exists($this,"unSetupReseller")){
                $change  = $this->unSetupReseller();
                if(!$change) return false;
            }

            $new_options["ftp_info"]["host"] = "ftp.".$new_options["domain"];
            $new_options["ftp_info"]["port"] = 21;
            $new_options["ftp_info"]["username"] = $new_config["user"];
            $new_options["ftp_info"]["password"] = $c_password;


            if($new_c_user){
                if($new_plan && !Validation::isEmpty($new_plan)){
                    if(!$old_plan || $new_plan != $old_plan){
                        $change  = $this->change_plan($new_plan);
                        if(!$change) return false;
                    }
                }
                else{
                    if($old_plan){
                        $change  = $this->change_plan('');
                        if(!$change) return false;
                    }

                    if($new_options["disk_limit"] != $old_options["disk_limit"]){
                        $change  = $this->change_quota($new_options["disk_limit"]);
                        if(!$change) return false;
                    }

                    if($new_options["bandwidth_limit"] != $old_options["bandwidth_limit"]){
                        $change  = $this->change_bandwidth($new_options["bandwidth_limit"]);
                        if(!$change) return false;
                    }

                    $setFeatures        = [];

                    if($new_options["email_limit"] != $old_options["email_limit"])
                        $setFeatures["email_limit"] = $new_options["email_limit"];

                    if($new_options["database_limit"] != $old_options["database_limit"])
                        $setFeatures["database_limit"] = $new_options["database_limit"];

                    if($new_options["addons_limit"] != $old_options["addons_limit"])
                        $setFeatures["addons_limit"] = $new_options["addons_limit"];

                    if($new_options["subdomain_limit"] != $old_options["subdomain_limit"])
                        $setFeatures["subdomain_limit"] = $new_options["subdomain_limit"];

                    if($new_options["ftp_limit"] != $old_options["ftp_limit"])
                        $setFeatures["ftp_limit"] = $new_options["ftp_limit"];

                    if($new_options["park_limit"] != $old_options["park_limit"])
                        $setFeatures["park_limit"] = $new_options["park_limit"];

                    if($new_options["max_email_per_hour"] != $old_options["park_limit"])
                        $setFeatures["max_email_per_hour"] = $new_options["max_email_per_hour"];

                    if($setFeatures){
                        $apply  = $this->modifyAccount($setFeatures);
                        if(!$apply) return false;
                    }
                }
            }

            $new_options["config"]          = $new_config;
            $new_options["creation_info"]   = $new_c_info;

            return $new_options;
        }

        public function apply_updowngrade($orderopt=[],$product=[]){
            $creation_info      = isset($product["module_data"]["create_account"]) ? $product["module_data"]["create_account"] : [];
            if(!$creation_info || isset($product["module_data"]["plan"])) $creation_info = $product["module_data"];

            $disk_limit         = $product["options"]["disk_limit"];
            $bandwidth_limit     = $product["options"]["bandwidth_limit"];
            $email_limit         = $product["options"]["email_limit"];
            $database_limit      = $product["options"]["database_limit"];
            $addons_limit        = $product["options"]["addons_limit"];
            $subdomain_limit     = $product["options"]["subdomain_limit"];
            $ftp_limit           = $product["options"]["ftp_limit"];
            $park_limit          = $product["options"]["park_limit"];
            $max_email_per_hour  = $product["options"]["max_email_per_hour"];

            $o_reseller         = isset($orderopt["creation_info"]["reseller"]) ? $orderopt["creation_info"]["reseller"] : false;
            $p_reseller         = isset($creation_info["reseller"]) ? $creation_info["reseller"] : false;

            $p_plan             = isset($creation_info["plan"]) ? $creation_info["plan"] : false;

            if($p_reseller){
                if($o_reseller) $change  = $this->setReseller(false,$creation_info);
                else $change  = $this->setupReseller(false,$creation_info);
                if(!$change) return false;
            }
            elseif($o_reseller && !$p_reseller){
                $this->error = "Product is not reseller";
                return false;
            }

            if($p_plan){
                $change  = $this->change_plan($p_plan);
                if(!$change) return false;
            }
            else{

                $change  = $this->change_quota($disk_limit);
                if(!$change) return false;

                $change  = $this->change_bandwidth($bandwidth_limit);
                if(!$change) return false;

                $setFeatures        = [
                    "email_limit" => $email_limit,
                    "database_limit" => $database_limit,
                    "addons_limit" => $addons_limit,
                    "subdomain_limit" => $subdomain_limit,
                    "ftp_limit" => $ftp_limit,
                    "park_limit" => $park_limit,
                    "max_email_per_hour" => $max_email_per_hour,
                ];

                $change  = $this->modifyAccount($setFeatures);
                if(!$change) return false;

            }

            return true;
        }

        public function modifyAccount($params=[]){
            $limits     = [];
            $plan       = NULL;

            foreach($params AS $key=>$val){
                $val = $this->limit_converter_reverse($val);
                if($key == "plan") $plan = $val;
                if($key == "disk_limit") $limits["disk_space"] = $val;
                if($key == "bandwidth_limit") $limits["max_traffic"] = $val;
                if($key == "email_limit") $limits["max_box"] = $val;
                if($key == "database_limit") $limits["max_db"] = $val;
                if($key == "subdomain_limit") $limits["max_subdom"] = $val;
                if($key == "addons_limit") $limits["max_site"] = $val;
                if($key == "ftp_limit") $limits["max_subftp_users"] = $val;
            }

            try{

                $this->client->operator("webspace")->edit($this->config["user"],[
                    'plan'      => $plan,
                    'limits'    => $limits,
                ]);

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }

        public function setReseller($user,$params=[]){
            if(!$user) $user = $this->config["user"];
            $curr_plan  = isset($this->options["creation_info"]["reseller_plan"]) ? $this->options["creation_info"]["reseller_plan"] : NULL;
            $plan       = isset($params["reseller_plan"]) ? $params["reseller_plan"] : NULL;
            $max_cl     = isset($params["account_limit"]) ? $params["account_limit"] : -1;
            if($max_cl == '') $max_cl = -1;
            $max_dom    = isset($params["domain_limit"]) ? $params["domain_limit"] : -1;
            if($max_dom == '') $max_dom = -1;

            $curr_max_cl     = isset($this->options["creation_info"]["account_limit"]) ? $this->options["creation_info"]["account_limit"] : -1;
            if($curr_max_cl == '') $curr_max_cl = -1;
            $curr_max_dom    = isset($this->options["creation_info"]["domain_limit"]) ? $this->options["creation_info"]["domain_limit"] : -1;
            if($curr_max_dom == '') $curr_max_dom = -1;

            $sync       = false;

            if(!isset($this->options["creation_info"]["account_limit"]) || $max_cl != $curr_max_cl) $sync = true;
            elseif(!isset($this->options["creation_info"]["domain_limit"]) || $max_dom != $curr_max_dom) $sync = true;

            try{

                if((!$plan && $curr_plan) || ($plan && $plan != $curr_plan)){
                    $plans = $this->getResellerPlans();
                    if($plans) foreach($plans AS $row) if($row["name"] == $plan) $plan = $row["guid"];
                    $this->client->operator("reseller")->change_plan($user,$plan);
                }

                if(!$plan && $sync){
                    $this->client->operator("reseller")->edit($user,[
                        'plan'   => NULL,
                        'limits' => [
                            'max_cl' => $max_cl,
                            'max_dom' => $max_dom,
                        ],
                    ]);
                }

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }
        public function setupReseller($user=false,$params=[]){
            if(!$user) $user = $this->config["user"];

            $plan       = isset($params["reseller_plan"]) ? $params["reseller_plan"] : NULL;
            $max_cl     = isset($params["account_limit"]) ? $params["account_limit"] : -1;
            if(Validation::isEmpty($max_cl) && $max_cl != 0) $max_cl = -1;
            $max_dom    = isset($params["domain_limit"]) ? $params["domain_limit"] : -1;
            if(Validation::isEmpty($max_dom) && $max_dom != 0) $max_dom = -1;

            try{
                $this->client->operator("customer")->convert_reseller($user,$plan);

                if(!$plan){
                    $this->client->operator("reseller")->edit($user,[
                        'limits' => [
                            'max_cl' => $max_cl,
                            'max_dom' => $max_dom,
                        ],
                    ]);
                }
            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }
        public function unSetupReseller($user=false){
            if(!$user) $user = $this->config["user"];
            try{
                $this->client->operator("reseller")->convert_customer($user);
            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }
            return true;
        }
        public function removeReseller($user=false){
            if(!$user) $user = $this->config["user"];

            try{
                $summary    = $this->getAcSummary(true,$user);
                if(!$summary) return false;

                $owner_id    = (int) $summary["data"]["gen_info"]["owner-id"];

                $this->client->operator("reseller")->_delete('del','id',$owner_id);

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }
        public function suspend_reseller(){
            try{

                $summary    = $this->getAcSummary();
                if(!$summary) return false;

                $owner_id    = (int) $summary["data"]["gen_info"]["owner-id"];

                $this->client->operator("reseller")->edit($owner_id,[
                    'gen-info' => [
                        'status' => 16,
                    ],
                ]);

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }
        public function unsuspend_reseller(){
            try{

                $summary    = $this->getAcSummary();
                if(!$summary) return false;

                $owner_id    = (int) $summary["data"]["gen_info"]["owner-id"];

                $this->client->operator("reseller")->edit($owner_id,[
                    'gen-info' => [
                        'status' => 0,
                    ],
                ]);

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }

        public function removeAccount($user=false){
            if(!$user) $user = $this->config["user"];

            try{
                $summary    = $this->getAcSummary(true,$user);
                if(!$summary) return false;

                $owner_id    = (int) $summary["data"]["gen_info"]["owner-id"];

                $this->client->operator("customer")->_delete('del','id',$owner_id);

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }
        public function suspend(){
            try{
                $status_code = 1;
                if(in_array($this->config["owner"],["root","administrator","admin"])) $status_code = 16;
                $this->client->operator("webspace")->edit($this->config["user"],[
                    'gen_setup' => [
                        'status' => $status_code,
                    ],
                ]);

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }
        public function unsuspend(){
            try{
                $this->client->operator("webspace")->edit($this->config["user"],[
                    'gen_setup' => [
                        'status' => 0,
                    ],
                ]);

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }

        public function change_plan($plan){
            try{

                if($plan){
                    $plans = $this->getPlans();
                    if($plans) foreach($plans AS $row) if((string) $row["name"] == (string) $plan) $plan = $row["guid"];
                }

                $this->client->operator("webspace")->change_plan($this->config["user"],$plan);

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }

        public function change_quota($quota){

            try{

                if(is_string($quota) && str_starts_with(strtolower($quota),'unlimited')) $quota = 0;
                else $quota = FileManager::converByte(((int) $quota)."MB");


                $this->client->operator("webspace")->edit($this->config["user"],[
                    'limits' => [
                        'disk_space' => $quota,
                    ],
                ]);

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }

        public function change_bandwidth($bwlimit){

            try{

                if(is_string($bwlimit) && str_starts_with(strtolower($bwlimit),'unlimited')) $bwlimit = 0;
                else $bwlimit = FileManager::converByte(((int) $bwlimit)."MB");

                $this->client->operator("webspace")->edit($this->config["user"],[
                    'limits' => [
                        'max_traffic' => $bwlimit,
                    ],
                ]);

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }

        public function listAccounts(){

            try{

                $plans      = [];
                $getPlans   = $this->client->operator("service-plan")->_getItems();
                $getPlans   = $this->object_to_array($getPlans);

                if($getPlans) foreach($getPlans AS $plan) $plans[$plan["guid"]] = $plan["name"];

                $getData    = $this->client->operator("webspace")->_getItems([
                    'gen_info',
                    'hosting',
                    'subscriptions',
                ]);

            }catch(PleskApi\Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            $data = [];

            if($getData){
                $getData   = $this->object_to_array($getData);

                foreach($getData AS $row){
                    $item   = [];
                    $item["cdate"]       = DateManager::format("Y-m-d H:i",$row["data"]["gen_info"]["cr_date"]);
                    $item["unix_cdate"]  = DateManager::strtotime($row["data"]["gen_info"]["cr_date"]);
                    $item["domain"]      = $row["data"]["gen_info"]["ascii-name"];
                    $status              = $row["data"]["gen_info"]["status"];
                    $item["suspend"]     = ($status == 8 || $status == 16 || $status == 32 || $status == 64);
                    $item["suspended_time"] = 0;


                    if(isset($row["data"]["hosting"]["vrt_hst"]["property"])){
                        foreach($row["data"]["hosting"]["vrt_hst"]["property"] AS $property){
                            if($property["name"] == "ftp_login") $item["username"] = $property["value"];
                            elseif($property["name"] == "ftp_password") $item["password"] = $property["value"];
                        }
                    }

                    if(isset($row["data"]["subscriptions"]["subscription"]["plan"]["plan-guid"])){
                        if(isset($plans[$row["data"]["subscriptions"]["subscription"]["plan"]["plan-guid"]]))
                            $item["plan"] = $plans[$row["data"]["subscriptions"]["subscription"]["plan"]["plan-guid"]];
                    }else $item["plan"] = NULL;

                    $data[] = $item;
                }
                Utility::sksort($data,"unix_cdate");
            }
            return $data;
        }


        public function use_method($param=''){
            $method_name        = '';
            $method_prefix_c    = 'use_clientArea_';
            $method_prefix_a    = 'use_adminArea_';
            $param              = str_replace("-","_",$param);

            if(defined("ADMINISTRATOR") && $param)
                $method_name = method_exists($this,$method_prefix_a.$param) ? $method_prefix_a.$param : '';
            elseif($param)
                $method_name = method_exists($this,$method_prefix_c.$param) ? $method_prefix_c.$param : '';

            if($method_name) return $this->{$method_name}();
        }

        public function panel_links_for_client(){
            $buttons                = [];

            if(method_exists($this,'use_clientArea_SingleSignOn'))
                $buttons["panel"] = [
                    'url'       => $this->area_link . "?inc=use_method&method=SingleSignOn",
                    'name'      => __("website/account_products/login-panel"),
                ];

            if(method_exists($this,'use_clientArea_webMail'))
                $buttons["mail"] = [
                    'url' => $this->area_link . "?inc=use_method&method=webMail",
                    'name' => __("website/account_products/login-webmail"),
                ];

            return $buttons;
        }
        public function panel_links_for_admin(){
            $buttons                = [];

            if(method_exists($this,'use_adminArea_root_SingleSignOn'))
                $buttons["root_panel"] = [
                    'url'       => $this->area_link . "?operation=hosting_use_method&use_method=root_SingleSignOn",
                    'name'      => __("admin/products/login-root-panel"),
                ];

            if(method_exists($this,'use_adminArea_SingleSignOn'))
                $buttons["panel"] = [
                    'url'       => $this->area_link . "?operation=hosting_use_method&use_method=SingleSignOn",
                    'name'      => __("website/account_products/login-panel"),
                ];

            if(method_exists($this,'use_adminArea_webMail'))
                $buttons["mail"] = [
                    'url' => $this->area_link . "?operation=hosting_use_method&use_method=webMail",
                    'name' => __("website/account_products/login-webmail"),
                ];

            return $buttons;
        }

        public function use_adminArea_SingleSignOn()
        {
            $options                = $this->options;
            $creation_info          = $options["creation_info"];
            $reseller               = isset($creation_info["reseller"]) && $creation_info["reseller"];
            $link                   = $this->panel_link($options);

            if($link)
            {
                Utility::redirect($link);
                return true;

            }
            else
            {
                echo  "Oops! There was a problem.";
            }
        }
        public function use_adminArea_root_SingleSignOn()
        {
            $options                = $this->options;
            $creation_info          = $options["creation_info"];
            $reseller               = isset($creation_info["reseller"]) && $creation_info["reseller"];
            $link                   = $this->root_panel_link();

            if($link)
            {
                Utility::redirect($link);
                return true;

            }
            else
            {
                echo  "Oops! There was a problem.";
            }
        }
        public function use_clientArea_SingleSignOn()
        {
            $options                = $this->options;
            $creation_info          = $options["creation_info"];
            $reseller               = isset($creation_info["reseller"]) && $creation_info["reseller"];
            $link                   = $this->panel_link($options);

            if($link)
            {
                Utility::redirect($link);
                return true;

            }
            else
            {
                echo  "Oops! There was a problem.";
            }
        }
        public function use_clientArea_webMail()
        {
            Utility::redirect($this->mail_link());
            return true;
        }



        private function panel_link($options=[]){
            $config        = isset($options["config"]) && $options["config"] ? $options["config"] : [];

            $link = '';

            if($this->server["secure"])
                $link .= "https";
            else
                $link .= "http";

            if(Validation::NSCheck($this->server["name"]))
                $host = $this->server["name"];
            else
                $host = $this->server["ip"];

            $link .= "://".$host.":";
            if($this->server["secure"])
                $link .= "8443";
            else
                $link .= "8880";

            if(isset($config["user"])){
                if($config["password"]){
                    $password_d = Crypt::decode($config["password"],Config::get("crypt/user"));
                    if($password_d) $config["password"] = $password_d;
                }
                $link .= "/login_up.php";
                $link .= "?login_name=".$config["user"];
                $link .= "&passwd=".urlencode($config["password"]);
            }
            return $link;
        }
        private function root_panel_link(){
            $link = '';

            if($this->server["secure"])
                $link .= "https";
            else
                $link .= "http";

            if(Validation::NSCheck($this->server["name"]))
                $host = $this->server["name"];
            else
                $host = $this->server["ip"];

            $link .= "://".$host.":";
            $link .= $this->server["port"];

            $link .= "/login_up.php";
            $link .= "?login_name=".$this->server["username"];
            $link .= "&passwd=".urlencode($this->server["password"]);

            return $link;
        }
        private function mail_link(){
            $link = '';

            if($this->server["secure"])
                $link .= "https";
            else
                $link .= "http";

            $link .= "://webmail.".$this->options["domain"];

            return $link;
        }



    }

    Hook::add("ClientAreaEndBody",1,function(){
        if(Controllers::$init->getData("module") != "Plesk") return false;
        return '
<script type="text/javascript">
$(document).ready(function(){
    $("#get_details_module_content").css("display","none");
});
</script>';
    });