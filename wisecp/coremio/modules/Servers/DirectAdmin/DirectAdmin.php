<?php

    class DirectAdmin_Module {
        private $api,$server;
        public $storage=[],$order=[],$product=[],$user=[];
        public $config=[],$options=[];
        public $lang,$error;
        public $area_link;
        public $page;
        public $force_setup = false;

        function __construct($server,$options=[]){
            if($server){
                $api_host = $server["ip"];

                if(Validation::NSCheck($server["name"])) $api_host = $server["name"];

                if(!class_exists("DirectAdmin_HTTPSocket")) include __DIR__.DS."api.php";
                $this->api = new DirectAdmin_HTTPSocket;
                $this->api->connect(($server["secure"] ? "ssl://" : '').$api_host,$server["port"]);
                $this->api->set_login($server["username"],$server["password"]);
            }

            $this->server       = $server;
            $config             = Modules::Config("Servers","DirectAdmin");

            if(isset($options["config"])){
                $this->options = $options;
                $external_config = $options["config"];
            }else $external_config = $options;
            if(!is_array($external_config)) $external_config = [];
            $this->config       = array_merge($config,$external_config);
            $this->config["owner"] = $this->server["username"];

            $this->lang         = Modules::Lang("Servers","DirectAdmin");
        }
        private function result(){
            $result = $this->api->fetch_parsed_body();
            if(isset($result["error"]) && isset($result["text"]) && $result["error"]){
                $this->error = $result["text"].(($result["details"] ?? '') ? ': '.$result["details"] : '');
                return false;
            }
            $error_msg   = is_array($this->api->error) ? implode(", ",$this->api->error) : $this->api->error;
            $get_html   = is_array($result) ? current(array_keys($result)) : $result;
            if(!$error_msg && stristr($get_html,"<head>")){
                $error_msg = "Invalid Username or Password";
                $result     = false;
            }
            if($error_msg) $this->error = "API: ".$error_msg;
            if($this->api->result_status_code!=200){
                if(!$this->error) $this->error = 'No results were obtained.';
                return false;
            }

            elseif(isset($result["error"]) && $result["error"]){
                $this->error = $result["error"];
                return false;
            }

            if(!$result && !is_array($result) && !$this->error) $this->error = 'No results were obtained.';
            $result_x = current($result);
            if(is_string($result_x) && stristr($result_x,'root-preloader'))
            {
                $this->error = "Invalid Username or Password";
                return false;
            }
            return $result;
        }

        public function set_order($order=[]){
            $this->order =  $order;
            Helper::Load(["Products","User"]);
            $this->product = Products::get($order["type"],$order["product_id"]);
            $this->user    = User::getData($order["owner_id"],"id,name,surname,full_name,email,lang","array");
        }

        public function activation_infos($type='html',$order=[],$lang=''){
            $this->lang     = Modules::Lang("Servers","DirectAdmin",$lang);
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
            return Modules::getPage("Servers","DirectAdmin","activation-".$type,$data);
        }
        public function testConnect(){
            $this->api->set_method('POST');

            $this->api->query('/CMD_API_SHOW_USERS');
            $result     = $this->result();
            if(!is_array($result) && !$result) return false;

            return true;
        }
        public function getPlans(){
            $this->api->set_method('GET');
            $this->api->query('/CMD_API_PACKAGES_USER');
            $plans  = $this->result();
            if($plans){
                $result = [];
                $plans  = isset($plans["list"]) ? $plans["list"] : [];
                foreach($plans AS $plan){
                    $this->api->query('/CMD_API_PACKAGES_USER',['package'=>$plan]);
                    $data   = $this->result();
                    $result[$plan] = $data;
                }
                return $result;
            }
            return false;
        }
        public function getResellerPlans(){
            $this->api->set_method('GET');
            $this->api->query('/CMD_API_PACKAGES_RESELLER');
            $plans  = $this->result();
            if($plans){
                $result = [];
                $plans  = isset($plans["list"]) ? $plans["list"] : [];
                foreach($plans AS $plan){
                    $this->api->query('/CMD_API_PACKAGES_RESELLER',['package'=>$plan]);
                    $data   = $this->result();
                    $result[$plan] = $data;
                }
                return $result;
            }
            return false;
        }
        public function createAccount($domain,$options=[]){
            $domain         = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $username       = $this->UsernameGenerator($domain);
            $password       = Utility::generate_hash(16,false,'lud');
            if(isset($options["username"])) $username = $options["username"];
            if(isset($options["password"])) $password = $options["password"];

            $username       = str_replace("-","",$username);

            $creation_info      = isset($options["creation_info"]) ? $options["creation_info"] : [];

            $package            = isset($creation_info["plan"]) ? $creation_info["plan"] : NULL;
            $package_r          = isset($creation_info["reseller_plan"]) ? $creation_info["reseller_plan"] : NULL;

            $reseller           = isset($creation_info["reseller"]) ? $creation_info["reseller"] : false;
            $reseller_ip        = isset($creation_info["reseller_ip"]) ? $creation_info["reseller_ip"] : 'shared';
            $suspend_at_limit   = isset($creation_info["reseller_ip"]) ? $creation_info["reseller_ip"] : false;
            $ip                 = $this->server["ip"];

            if($reseller) $ip   = $reseller_ip;

            $params             = [
                'action'            => "create",
                'add'               => "Submit",
                'ip'                => $ip,
                'username'          => $username,
                'email'             => $this->user["email"],
                'passwd'            => $password,
                'passwd2'           => $password,
                'domain'            => $domain,
                'notify'            => "no",
            ];

            if($reseller && $package_r) $params["package"] = $package_r;
            elseif($package) $params["package"] = $package;
            else{
                if(isset($options["disk_limit"]) && $options["disk_limit"] != "unlimited")
                    $params['quota'] = $options["disk_limit"];
                else
                    $params['uquota'] = "ON";

                if(isset($options["bandwidth_limit"]) && $options["bandwidth_limit"] != "unlimited")
                    $params['bandwidth'] = $options["bandwidth_limit"];
                else
                    $params['ubandwidth'] = "ON";

                if(isset($options["email_limit"]) && $options["email_limit"] != "unlimited")
                    $params['nemails'] = $options["email_limit"];
                else
                    $params['unemails'] = "ON";

                if(isset($options["database_limit"]) && $options["database_limit"] != "unlimited")
                    $params['mysql'] = $options["database_limit"];
                else
                    $params['umysql'] = "ON";

                if(isset($options["subdomain_limit"]) && $options["subdomain_limit"] != "unlimited")
                    $params['nsubdomains'] = $options["subdomain_limit"];
                else
                    $params['unsubdomains'] = "ON";

                if(isset($options["ftp_limit"]) && $options["ftp_limit"] != "unlimited")
                    $params['ftp'] = $options["ftp_limit"];
                else
                    $params['Uftp'] = "ON";

                if(isset($options["park_limit"]) && $options["park_limit"] != "unlimited")
                    $params['domainptr'] = $options["park_limit"];
                else
                    $params['udomainptr'] = "ON";

                if(isset($options["addons_limit"]) && $options["addons_limit"] != "unlimited")
                    $params['vdomains'] = $options["addons_limit"];
                else
                    $params['uvdomains'] = "ON";

                if($suspend_at_limit) $params['suspend_at_limit'] = "ON";

                $params['cgi'] = "ON";
                $params['php'] = "ON";
                $params['cron'] = "ON";
                $params['ssl']  = "ON";
            }

            $this->api->set_method('GET');
            $this->api->query($reseller ? '/CMD_API_ACCOUNT_RESELLER' : '/CMD_API_ACCOUNT_USER',$params);
            $result         = $this->result();

            if(!$result) return false;

            if($username == ''){
                $this->error = "Username is empty or invalid.";
                return false;
            }

            return [
                'username' => $username,
                'password' => $password,
                'ftp_info' => [
                    'ip'   => $this->server["ip"],
                    'host' => "ftp.".$domain,
                    'username' => $username,
                    'password' => $password,
                    'port' => 21,
                ],
            ];
        }

        public function modifyAccount($params=[]){
            $params['user']         = $this->config["user"];
            $params['action']       = "customize";

            $this->api->set_method('GET');
            $this->api->query("/CMD_API_MODIFY_USER",$params);
            $result = $this->result();
            if(!$result) return false;

            return true;
        }

        public function apply_options($old_options,$new_options=[]){
            $old_config     = isset($old_options["config"]) ? $old_options["config"] : [];
            $new_config     = isset($new_options["config"]) ? $new_options["config"] : [];

            $old_c_info     = isset($old_options["creation_info"]) ? $old_options["creation_info"] : [];
            $new_c_info     = isset($new_options["creation_info"]) ? $new_options["creation_info"] : [];

            $old_c_user     = isset($old_config["user"]) ? $old_config["user"] : '';
            $new_c_user     = isset($new_config["user"]) ? $new_config["user"] : '';


            $old_plan       = isset($old_c_info["plan"]) ? $old_c_info["plan"] : false;
            $old_plan_r     = isset($old_c_info["reseller_plan"]) ? $old_c_info["reseller_plan"] : false;

            $new_plan       = isset($new_c_info["plan"]) ? $new_c_info["plan"] : false;
            $new_plan_r     = isset($new_c_info["reseller_plan"]) ? $new_c_info["reseller_plan"] : false;

            $reseller       = isset($new_c_info["reseller"]) ? $new_c_info["reseller"] : false;

            if($old_c_user !== $new_c_user && $new_c_user){
                $this->api->query("/CMD_API_SHOW_USER_CONFIG",['user' => $new_c_user]);
                $check = $this->result();
                if(!$check) return false;
            }

            $old_password           = isset($old_config["password"]) ? $old_config["password"] : '';
            $password               = Filter::password($new_config["password"]);
            $c_password             = $password ? Crypt::encode($password,Config::get("crypt/user")) : '';
            if($password) $new_config["password"] = $c_password;

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

            $new_options["ftp_info"]["host"] = "ftp.".$new_options["domain"];
            $new_options["ftp_info"]["port"] = 21;
            $new_options["ftp_info"]["username"] = $new_c_user;
            $new_options["ftp_info"]["password"] = $c_password;

            $setFeatures        = [];

            if(!$reseller && $new_plan && $old_plan != $new_plan){
                $this->api->set_method('GET');
                $this->api->query('/CMD_API_MODIFY_USER',[
                    'action'  => "package",
                    'user'    => $new_c_user,
                    'package' => $new_plan
                ]);
                $result   = $this->api->fetch_parsed_body();
                if(!$result) return false;
            }
            elseif($reseller && $new_plan_r && $old_plan_r != $new_plan_r){
                $this->api->set_method('GET');
                $this->api->query('/CMD_API_MODIFY_RESELLER',[
                    'action'  => "package",
                    'user'    => $new_c_user,
                    'package' => $new_plan_r
                ]);
                $result   = $this->api->fetch_parsed_body();
                if(!$result) return false;
            }
            elseif((!$reseller && !$new_plan) || ($reseller && !$new_plan_r)){

                if($new_options["disk_limit"] !== $old_options["disk_limit"]){
                    if(isset($new_options["disk_limit"]) && $new_options["disk_limit"] != "unlimited")
                        $setFeatures['quota'] = $new_options["disk_limit"];
                    else
                        $setFeatures['uquota'] = "ON";
                }

                if($new_options["bandwidth_limit"] !== $old_options["bandwidth_limit"]){
                    if(isset($new_options["bandwidth_limit"]) && $new_options["bandwidth_limit"] != "unlimited")
                        $setFeatures['bandwidth'] = $new_options["bandwidth_limit"];
                    else
                        $setFeatures['ubandwidth'] = "ON";
                }

                if($new_options["email_limit"] !== $old_options["email_limit"]){
                    if(isset($new_options["email_limit"]) && $new_options["email_limit"] != "unlimited")
                        $setFeatures['nemails'] = $new_options["email_limit"];
                    else
                        $setFeatures['unemails'] = "ON";
                }

                if($new_options["database_limit"] !== $old_options["database_limit"]){
                    if(isset($new_options["database_limit"]) && $new_options["database_limit"] != "unlimited")
                        $setFeatures['mysql'] = $new_options["database_limit"];
                    else
                        $setFeatures['umysql'] = "ON";
                }

                if($new_options["subdomain_limit"] !== $old_options["subdomain_limit"]){
                    if(isset($new_options["subdomain_limit"]) && $new_options["subdomain_limit"] != "unlimited")
                        $setFeatures['nsubdomains'] = $new_options["subdomain_limit"];
                    else
                        $setFeatures['unsubdomains'] = "ON";
                }

                if($new_options["ftp_limit"] !== $old_options["ftp_limit"]){
                    if(isset($new_options["ftp_limit"]) && $new_options["ftp_limit"] != "unlimited")
                        $setFeatures['ftp'] = $new_options["ftp_limit"];
                    else
                        $setFeatures['Uftp'] = "ON";
                }

                if($new_options["park_limit"] !== $old_options["park_limit"]){
                    if(isset($new_options["park_limit"]) && $new_options["park_limit"] != "unlimited")
                        $setFeatures['domainptr'] = $new_options["park_limit"];
                    else
                        $setFeatures['udomainptr'] = "ON";
                }

                if($new_options["addons_limit"] !== $old_options["addons_limit"]){
                    if(isset($new_options["addons_limit"]) && $new_options["addons_limit"] != "unlimited")
                        $setFeatures['vdomains'] = $new_options["addons_limit"];
                    else
                        $setFeatures['uvdomains'] = "ON";
                }

                $setFeatures['cgi'] = "ON";
                $setFeatures['php'] = "ON";
                $setFeatures['cron'] = "ON";
                $setFeatures['ssl']  = "ON";

            }

            if($new_c_user && $setFeatures){
                $apply  = $this->modifyAccount($setFeatures);
                if(!$apply) return false;
            }

            $new_options["config"]          = $new_config;
            $new_options["creation_info"]   = $new_c_info;

            return $new_options;
        }

        public function apply_updowngrade($orderopt=[],$product=[]){
            $creation_info      = isset($product["module_data"]["create_account"]) ? $product["module_data"]["create_account"] : [];
            if(!$creation_info || isset($product["module_data"]["plan"])) $creation_info = $product["module_data"];

            $setFeatures        = [];

            if($creation_info["plan"] && $creation_info["plan"] != $orderopt["creation_info"]["plan"]){
                $this->api->set_method('GET');
                $this->api->query('/CMD_API_MODIFY_USER',[
                    'action'  => "package",
                    'user'    => $this->config["user"],
                    'package' => $creation_info["plan"],
                ]);
                $result   = $this->result();
                if(!$result) return false;
            }
            elseif($creation_info["reseller_plan"] && $creation_info["reseller_plan"] != $orderopt["creation_info"]["reseller_plan"]){
                $this->api->set_method('GET');
                $this->api->query('/CMD_API_MODIFY_RESELLER',[
                    'action'  => "package",
                    'user'    => $this->config["user"],
                    'package' => $creation_info["reseller_plan"],
                ]);
                $result   = $this->result();
                if(!$result) return false;
            }
            else{

                if($creation_info["disk_limit"] !== $orderopt["disk_limit"]){
                    if(isset($creation_info["disk_limit"]) && $creation_info["disk_limit"] != "unlimited")
                        $setFeatures['quota'] = $creation_info["disk_limit"];
                    else
                        $setFeatures['uquota'] = "ON";
                }

                if($creation_info["bandwidth_limit"] !== $orderopt["bandwidth_limit"]){
                    if(isset($creation_info["bandwidth_limit"]) && $creation_info["bandwidth_limit"] != "unlimited")
                        $setFeatures['bandwidth'] = $creation_info["bandwidth_limit"];
                    else
                        $setFeatures['ubandwidth'] = "ON";
                }

                if($creation_info["email_limit"] !== $orderopt["email_limit"]){
                    if(isset($creation_info["email_limit"]) && $creation_info["email_limit"] != "unlimited")
                        $setFeatures['nemails'] = $creation_info["email_limit"];
                    else
                        $setFeatures['unemails'] = "ON";
                }

                if($creation_info["database_limit"] !== $orderopt["database_limit"]){
                    if(isset($creation_info["database_limit"]) && $creation_info["database_limit"] != "unlimited")
                        $setFeatures['mysql'] = $creation_info["database_limit"];
                    else
                        $setFeatures['umysql'] = "ON";
                }

                if($creation_info["subdomain_limit"] !== $orderopt["subdomain_limit"]){
                    if(isset($creation_info["subdomain_limit"]) && $creation_info["subdomain_limit"] != "unlimited")
                        $setFeatures['nsubdomains'] = $creation_info["subdomain_limit"];
                    else
                        $setFeatures['unsubdomains'] = "ON";
                }

                if($creation_info["ftp_limit"] !== $orderopt["ftp_limit"]){
                    if(isset($creation_info["ftp_limit"]) && $creation_info["ftp_limit"] != "unlimited")
                        $setFeatures['ftp'] = $creation_info["ftp_limit"];
                    else
                        $setFeatures['Uftp'] = "ON";
                }

                if($creation_info["park_limit"] !== $orderopt["park_limit"]){
                    if(isset($creation_info["park_limit"]) && $creation_info["park_limit"] != "unlimited")
                        $setFeatures['domainptr'] = $creation_info["park_limit"];
                    else
                        $setFeatures['udomainptr'] = "ON";
                }

                if($creation_info["addons_limit"] !== $orderopt["addons_limit"]){
                    if(isset($creation_info["addons_limit"]) && $creation_info["addons_limit"] != "unlimited")
                        $setFeatures['vdomains'] = $creation_info["addons_limit"];
                    else
                        $setFeatures['uvdomains'] = "ON";
                }

            }


            if($setFeatures){
                $apply  = $this->modifyAccount($setFeatures);
                if(!$apply) return false;
            }

            return true;
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
            $details        = $this->getDetails($user);
            if(!$details) return false;

            $usages          = $details["usages"];
            $info            = $details["info"];

            if($info["bandwidth"] == "unlimited") $info["bandwidth"] = 0;

            $limit_byte      = $info["bandwidth"] ? FileManager::converByte($info["bandwidth"]."MB") : 0;
            $used_byte       = $usages["bandwidth"] ? FileManager::converByte($usages["bandwidth"]."MB") : 0;

            if($limit_byte && $used_byte) $percent = Utility::getPercent($used_byte,$limit_byte);
            else $percent = 0;
            if($percent>100) $percent = 100;

            return [
                'limit' => $limit_byte,
                'used'  => $used_byte,
                'used-percent' => $percent,
                'format-limit' => $limit_byte   ? FileManager::formatByte($limit_byte) : "∞",
                'format-used' => $used_byte     ? FileManager::formatByte($used_byte) : "0 KB",
            ];
        }
        public function getDisk($user=false){
            if(!$user) $user = $this->config["user"];
            $details        = $this->getDetails($user);
            if(!$details) return false;

            $usages          = $details["usages"];
            $info            = $details["info"];

            if($info["quota"] == "unlimited") $info["quota"] = 0;

            $limit_byte      = $info["quota"] ? FileManager::converByte($info["quota"]."MB") : 0;
            $used_byte       = $usages["quota"] ? FileManager::converByte($usages["quota"]."MB") : 0;

            if($limit_byte && $used_byte) $percent = Utility::getPercent($used_byte,$limit_byte);
            else $percent = 0;
            if($percent>100) $percent = 100;

            return [
                'limit' => $limit_byte,
                'used'  => $used_byte,
                'used-percent' => $percent,
                'format-limit' => $limit_byte   ? FileManager::formatByte($limit_byte) : "∞",
                'format-used' => $used_byte     ? FileManager::formatByte($used_byte) : "0 KB",
            ];
        }
        public function getSummary(){
            $details        = $this->getDetails($this->config["user"]);
            if(!$details) return false;

            $info           = $details["info"];

            return [
                'database_limit'    => $info["mysql"],
                'addons_limit'      => $info["vdomains"],
                'email_limit'       => $info["nemails"],
            ];
        }

        private function getDetails($user='',$enable_usages=true){
            if(isset($this->storage["details"][$user])) return $this->storage["details"][$user];

            $this->api->set_method('GET');
            $this->api->query("/CMD_API_SHOW_USER_CONFIG",['user' => $user]);
            $info = $this->result();
            if(!$info) return false;

            if($enable_usages){
                $this->api->set_method('GET');
                $this->api->query("/CMD_API_SHOW_USER_USAGE",['user' => $user]);
                $usages      = $this->result();
                if(!$usages) return false;
            }else $usages = [];

            $return = [
                'info'      => $info,
                'usages'     => $usages,
            ];

            $this->storage["details"][$user] = $return;

            return $return;
        }

        public function getEmailList(){
            $this->api->set_login($this->server["username"]."|".$this->options["config"]["user"],$this->server["password"]);
            $domains    = $this->getDomains();
            $results    = [];

            $this->api->set_method('GET');

            foreach($domains AS $domain){
                $this->api->query("/CMD_API_POP",[
                    'action' => "list",
                    'domain' => $domain,
                ]);
                $result     = $this->result();
                if(isset($result["list"]) && $result["list"]){
                    foreach($result["list"] AS $item){
                        $results[] = [
                            'username'  => $item,
                            'domain'    => $domain,
                            'email'     => $item."@".$domain,
                            'used'      => 'UNKNOWN',
                            'used_mb'   => 'UNKNOWN',
                            'limit'     => 'UNKNOWN',
                            'limit_mb'  => 'UNKNOWN',
                        ];
                    }
                }
            }

            return $results;
        }
        public function getForwardsList(){
            return false;
        }
        public function addNewEmail($username,$domain,$password,$quota,$unlimited){
            $this->api->set_login($this->server["username"]."|".$this->options["config"]["user"],$this->server["password"]);
            $params     = [
                'action'        => "create",
                'domain'        => $domain,
                'user'          => $username,
                'passwd'        => $password,
                'passwd2'       => $password,
                'quota'         => $unlimited ? 1 : $quota,
                'limit'         => $unlimited ? "0" : '',
            ];

            $this->api->set_method('GET');
            $this->api->query("/CMD_API_POP",$params);
            $result     = $this->result();
            if(!$result) return false;

            return true;
        }
        public function addNewEmailForward($domain,$dest,$forward){
            return true;
        }
        public function deleteEmail($domain,$email){
            $this->api->set_login($this->server["username"]."|".$this->options["config"]["user"],$this->server["password"]);
            $params     = [
                'action'        => "delete",
                'domain'        => $domain,
                'user'          => $email,
            ];

            $this->api->set_method('POST');
            $this->api->query("/CMD_API_POP",$params);
            $result     = $this->result();

            if(!$result) return false;


            return true;
        }
        public function deleteEmailForward($dest,$forward){
            return true;
        }
        public function getPasswordStrength($password){
            return 100;
        }
        public function getMailDomains(){
            return $this->getDomains();
        }
        public function getDomains(){
            $this->api->set_login($this->server["username"]."|".$this->options["config"]["user"],$this->server["password"]);

            $this->api->set_method('GET');
            $this->api->query("/CMD_API_SHOW_DOMAINS");
            $result     = $this->result();
            if(!$result){
                return [$this->options["domain"]];
            }
            if(isset($result["list"]) && $result["list"]) $result = $result["list"];

            $list       = [];
            if($result){
                foreach($result AS $k=>$v){
                    $list[] = $v;
                }
            }

            if(!$list) $list[] = $this->order["options"]["domain"];

            return $list;
        }

        public function changePassword($oldpw,$newpw){
            $user   = $this->config["user"];

            $this->api->set_method('POST');
            $this->api->query("/CMD_API_USER_PASSWD",[
                'username'  => $user,
                'passwd'    => $newpw,
                'passwd2'   => $newpw,
            ]);
            $result     = $this->result();
            if(!$result) return false;

            return true;
        }

        public function setReseller($user,$params=[]){
            return true;
        }

        public function removeAccount($user=false){
            if(!$user) $user = $this->config["user"];
            $this->api->set_method('POST');
            $this->api->query("/CMD_API_SELECT_USERS",[
                'confirmed' => "Confirm",
                'delete'    => "yes",
                'select0'   => $user,
            ]);
            $result         = $this->result();

            if(stristr($this->error,'ullanıcısı sunucu üzerinde buluna') || stristr($this->error,'not exist on the server'))
            {
                $this->error = NULL;
                return true;
            }

            FileManager::file_write(ROOT_DIR."test.txt",$this->error);

            if(!$result && $this->error) return false;

            return true;
        }

        public function removeReseller($user=false){
            return $this->removeAccount($user);
        }

        public function setupReseller($user=false,$params=[]){
            return true;
        }

        public function suspend($user=false){
            if(!$user) $user = $this->config["user"];
            $this->api->set_method('POST');
            $this->api->query("/CMD_API_SELECT_USERS",[
                'location'  => "CMD_SELECT_USERS",
                'suspend'   => "Suspend",
                'select0'   => $user,
            ]);
            $result         = $this->result();
            if(!$result) return false;

            return true;
        }

        public function suspend_reseller($user=false){
            return $this->suspend($user);
        }

        public function unsuspend($user=false){
            if(!$user) $user = $this->config["user"];
            $this->api->set_method('POST');
            $this->api->query("/CMD_API_SELECT_USERS",[
                'location'  => "CMD_SELECT_USERS",
                'suspend'   => "Unsuspend",
                'select0'   => $user,
            ]);
            $result         = $this->result();
            if(!$result) return false;

            return true;
        }

        public function unsuspend_reseller($user=false){
            return $this->unsuspend($user);
        }

        public function change_quota($quota){
            return true;
        }

        public function change_bandwidth($bwlimit){
            return true;
        }

        public function listAccounts(){
            $this->api->set_method('GET');
            $this->api->query("/CMD_API_SHOW_ALL_USERS");
            $result1     = $this->result();
            if(!$result1) return false;
            $result1     = $result1["list"] ?? [];

            $this->api->query("/CMD_API_SHOW_RESELLERS");
            $result2     = $this->result();
            $result2     = $result2["list"] ?? [];

            $result         = array_merge($result1,$result2);

            $data = [];

            $months     = [
                'Jan' => '01',
                'Feb' => '02',
                'Mar' => '03',
                'Apr' => '04',
                'May' => '05',
                'Jun' => '06',
                'Jul' => '07',
                'Aug' => '08',
                'Sep' => '09',
                'Oct' => '10',
                'Nov' => '11',
                'Dec' => '12',
            ];

            if($result){
                foreach($result AS $row){
                    $detail             = $this->getDetails($row,false);
                    if($detail){
                        $detail             = $detail["info"];
                        $date_created       = $detail["date_created"];
                        $date_created_p     = explode(" ",$date_created);
                        $month_str          = $date_created_p[1];
                        $day                = $date_created_p[2];
                        if($day == ''){
                            $day                = $date_created_p[3];
                            $time               = $date_created_p[4];
                            $year               = $date_created_p[5];
                        }else{
                            $time               = $date_created_p[3];
                            $year               = $date_created_p[4];
                        }

                        $date_created       = $year."-".$months[$month_str]."-".$day." ".$time;
                        $package            = $detail["package"];

                        if(isset($detail["original_package"]) && $detail["original_package"])
                            $package = $detail["original_package"];

                        $item   = [];
                        $item["cdate"]      = $date_created;
                        $item["domain"]     = $detail["domain"];
                        $item["username"]   = $row;
                        $item["plan"]       = $package;
                        $item["unix_cdate"] = DateManager::strtotime($date_created);
                        $data[] = $item;
                    }
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
            return $this->panel_login();
        }
        public function use_adminArea_root_SingleSignOn()
        {
            return $this->root_panel_login();
        }
        public function use_clientArea_SingleSignOn()
        {
            return $this->panel_login();
        }
        public function use_clientArea_webMail()
        {
            Utility::redirect($this->WebMail_link());
            return true;
        }

        private function WebMail_link(){
            $link = '';

            if($this->server["secure"])
                $link .= "https";
            else
                $link .= "http";

            $link .= "://";

            if(Validation::NSCheck($this->server["name"]))
                $link .= $this->server["name"];
            else
                $link .= $this->server["ip"];

            $link .= "/roundcube";

            return $link;
        }
        private function panel_login(){
            $this->api->set_login($this->server["username"]."|".$this->options["config"]["user"],$this->server["password"]);

            $this->api->query('/CMD_API_LOGIN_KEYS',
                    array (
                            'method'=>'GET',
                            'action' => 'create',
                            'type' => 'one_time_url',
                            'redirect-url' => 1,
                            'login_keys_notify_on_creation' => 0,
                            'expiry'=>'30m',
                            'user' => $this->server["username"]."|".$this->options["config"]["user"],
                            'passwd' => $this->server["password"],
                    ));
            $result = $this->result();
            if(!$result) return $this->panel_login_old();

            $details = $result["details"] ?? '';
            $first_array = current($result);
            if($first_array)
            {
                $first_result = [];
                parse_str($first_array, $first_result);

                if(isset($first_result["redirect-url"]))
                {
                    if(empty($first_result["redirect-url"] ?? ''))
                        return $this->panel_login_old();
                    else {
                        $url = $first_result["redirect-url"];
                        if(!str_starts_with($url,"http"))
                            $url = "http".($this->server["secure"] ? 's' : '')."://".$url.':'.$this->server["port"]."/CMD_LOGIN?key=".$first_result["key"];
                        $details = $url;
                    }

                }
            }
            if(!$details) return $this->panel_login_old();

            echo $details;
            Utility::redirect($details);

            return true;
        }
        private function root_panel_login(){
            $this->api->set_login($this->server["username"],$this->server["password"]);

            $this->api->query('/CMD_API_LOGIN_KEYS',
                    array (
                            'method'=>'GET',
                            'action' => 'create',
                            'type' => 'one_time_url',
                            'redirect-url' => 1,
                            'login_keys_notify_on_creation' => 0,
                            'expiry'=>'30m',
                            'user' => $this->server["username"],
                            'passwd' => $this->server["password"],
                    ));
            $result = $this->result();
            if(!$result) return $this->root_panel_login_old();

            $details = $result["details"] ?? '';
            $first_array = current($result);
            if($first_array)
            {
                $first_result = [];
                parse_str($first_array, $first_result);
                if(isset($first_result["redirect-url"]))
                {
                    if(empty($first_result["redirect-url"] ?? ''))
                        return $this->root_panel_login_old();
                    else {
                        $url = $first_result["redirect-url"];
                        if(!str_starts_with($url,"http"))
                            $url = "http".($this->server["secure"] ? 's' : '')."://".$url.':'.$this->server["port"]."/CMD_LOGIN?key=".$first_result["key"];
                        $details = $url;
                    }
                }
            }

            if(!$details) $this->root_panel_login_old();

            Utility::redirect($details);

            return true;
        }
        private function panel_login_old(){
            $link = '';

            if($this->server["secure"])
                $link .= "https";
            else
                $link .= "http";

            if(Validation::NSCheck($this->server["name"]))
                $host = $this->server["name"];
            else
                $host = $this->server["ip"];

            $link .= "://".$host.":".$this->server["port"]."/CMD_LOGIN";
            $username   = $this->config["user"];
            $password   = $this->config["password"];
            $password_d = Crypt::decode($password,Config::get("crypt/user"));
            if($password_d) $password = $password_d;
            ?>
            <script type="text/javascript">
                setTimeout(function(){
                    var x = document.getElementById("RedirectForm").submit();
                },1);
            </script>
            <form action="<?php echo $link; ?>" method="POST" id="RedirectForm">
                <input type="hidden" name="username" value="<?php echo $username; ?>">
                <input type="hidden" name="password" value="<?php echo $password; ?>">
            </form>
            <?php

            return true;
        }
        private function root_panel_login_old(){
            $link = '';

            if($this->server["secure"])
                $link .= "https";
            else
                $link .= "http";

            if(Validation::NSCheck($this->server["name"]))
                $host = $this->server["name"];
            else
                $host = $this->server["ip"];

            $link .= "://".$host.":".$this->server["port"]."/CMD_LOGIN";
            $username   = $this->server["username"];
            $password   = $this->server["password"];
            ?>
            <script type="text/javascript">
                setTimeout(function(){
                    var x = document.getElementById("RedirectForm").submit();
                },1);
            </script>
            <form action="<?php echo $link; ?>" method="POST" id="RedirectForm">
                <input type="hidden" name="username" value="<?php echo $username; ?>">
                <input type="hidden" name="password" value="<?php echo $password; ?>">
            </form>
            <?php

            return true;
        }

    }

    Hook::add("ClientAreaEndBody",1,function(){
        if(Controllers::$init->getData("module") != "DirectAdmin") return false;
        return '
<script type="text/javascript">
$(document).ready(function(){
    $("#get_details_module_content").css("display","none");
});
</script>';
    });