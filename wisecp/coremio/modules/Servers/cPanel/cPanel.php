<?php
    class cPanel_Module {
        private $app,$server;
        private $storage=[];
        public $config=[],$options=[],$user=[],$order=[],$product=[],$val_of_conf_opt=[];
        public $lang,$error;
        private $default_acllist=[];
        public $area_link;

        function __construct($server,$options=[]){
            if($server){
                if(!class_exists("WHMApi")) include __DIR__.DS."WHMApi.php";
                if(isset($server["access_hash"]) && strlen($server["access_hash"]) > 0) $server["password"] = '|TOKEN|'.$server["access_hash"];

                $this->app = new WHMApi($server["ip"],$server["port"],$server["username"],$server["password"],$server["secure"]);
            }
            $this->server       = $server;
            $config             = Modules::Config("Servers","cPanel");
            $config["owner"]    = $server["username"];
            $config["ip"]       = $server["ip"];
            if(isset($options["config"])){
                $this->options = $options;
                $external_config = $options["config"];
            }else $external_config = $options;
            $this->config       = array_merge($config,$external_config);
            $this->lang         = Modules::Lang("Servers","cPanel");
            $this->default_acllist = $config["default-aclist"];
        }

        public function set_order($order=[]){
            $this->order =  $order;
            Helper::Load(["Products","User","Orders"]);
            $this->product      = Products::get($order["type"],$order["product_id"]);
            $this->user    = User::getData($order["owner_id"],"id,name,surname,full_name,email,phone,lang,country","array");
            $this->user    = array_merge($this->user,User::getInfo($order["owner_id"],["gsm_cc","gsm_number"]));
            $this->user["address"] = AddressManager::getAddress(false,$order["owner_id"]);

            $configurable_options = [];
            if($addons = Orders::addons($this->order["id"])){
                $lang   = $this->user["lang"];
                foreach($addons AS $addon){
                    if($gAddon = Products::addon($addon["addon_id"],$lang)){
                        if($gAddon["options"]){
                            if($gAddon["type"] == "quantity"){
                                $addon_v    = $addon["option_name"];
                                $addon_v    = explode("x",$addon_v);
                                $addon_v    = (int) trim($addon_v[0]);
                            }else
                                $addon_v        = 0;
                            foreach($gAddon["options"] AS $option){
                                if($option["id"] == $addon["option_id"]){
                                    if(isset($option["module"]) && $option["module"]){
                                        if(isset($option["module"][$this->_name])){
                                            $c_options = $option["module"][$this->_name]["configurable"];
                                            foreach($c_options AS $k=>$v) if($addon_v) $c_options[$k] = $addon_v;
                                            $configurable_options = array_replace_recursive($configurable_options,$c_options);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $this->val_of_conf_opt = $configurable_options;
        }

        public function activation_infos($type='html',$order=[],$lang=''){
            $this->lang     = Modules::Lang("Servers","cPanel",$lang);
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
            return Modules::getPage("Servers","cPanel","activation-".$type,$data);
        }

        public function testConnect(){
            $request    = $this->app->listaccts(false,false,"domain");
            if(!$request){
                $this->error = $this->app->error;
                return false;
            }
            return true;
        }

        public function getPlans(){
            $data = $this->app->listpkgs("viewable");
            if(!$data){
                $this->error = $this->app->error;
                return false;
            }
            return $data["pkg"];
        }

        public function getListAcls(){
            $data = $this->app->listacls();
            if(!$data){
                $this->error = $this->app->error;
                return false;
            }
            return $data["acl"];
        }

        public function createAccount($domain,$options=[]){
            $domain         = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $username       = $this->UsernameGenerator($domain);
            $password       = Utility::generate_hash(12);
            if(isset($options["username"])) $username = $options["username"];
            if(isset($options["password"])) $password = $options["password"];

            $username       = str_replace("-","",$username);

            //if(!$this->CheckUsername($username)) $username = $this->UsernameGenerator($domain,true);

            $creation_info  = isset($options["creation_info"]) ? $options["creation_info"] : [];

            $plan           = isset($creation_info["plan"]) ? $creation_info["plan"] : NULL;

            $params         = [
                'owner'         => $this->config["owner"],
                'language'      => isset($options["lang"]) ? $options["lang"] : "en",
                'username'      => $username,
                'password'      => $password,
                'domain'        => $domain,
                'contactemail'  => $this->user["email"],
            ];

            if($plan) $params["plan"] = $plan;
            else{
                $params["quota"] = isset($options["disk_limit"]) && $options["disk_limit"] != "unlimited" ? $options["disk_limit"] : 0;
                $params["maxftp"] = isset($options["ftp_limit"]) ? $options["ftp_limit"] : 0;
                $params["maxsql"] = isset($options["database_limit"]) ? $options["database_limit"] : 0;
                $params["maxpop"] = isset($options["email_limit"]) ? $options["email_limit"] : 0;
                $params["maxlst"] = 0;
                $params["maxsub"] = isset($options["subdomain_limit"]) ? $options["subdomain_limit"] : "unlimited";
                $params["maxpark"] = isset($options["park_limit"]) ? $options["park_limit"] :  "unlimited";
                $params["maxaddon"] = isset($options["addons_limit"]) ? $options["addons_limit"] :  "unlimited";
                $params["bwlimit"]  = isset($options["bandwidth_limit"]) ? $options["bandwidth_limit"] : 0;
                $params["max_email_per_hour"] = isset($options["max_email_per_hour"]) ? $options["max_email_per_hour"] : 0;
            }
            if(isset($creation_info["reseller"]) && $creation_info["reseller"]){
                $params["owner"] = $username;
                $params["reseller"] = 1;
            }

            $create     = $this->app->createacct($params);
            if(!$create){
                $this->error = $this->app->error;
                if(stristr($this->error,"for the “language” setting"))
                {
                    $options["lang"] = "en";
                    return $this->createAccount($domain,$options);
                }
                Modules::save_log("Servers","cPanel","createAccount",$params,$this->error);
            }

            if(isset($creation_info["reseller"]) && $creation_info["reseller"]){
                sleep(3);
                if(!$this->setReseller($username,$creation_info)){
                    $this->removeAccount($username);
                    Modules::save_log("Servers","cPanel","createAccount",$params,$this->error);
                }
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

        public function CheckUsername($username=''){
            $getData = $this->app->verify_new_username($username);
            if(!$getData){
                $this->error = $this->app->error;
                return false;
            }
            return $getData;
        }

        public function getBandwidth($user=false){
            if(!$user) $user = $this->config["user"];

            if($user){
                $getData = $this->app->showbw($user,"user");
                if(!$getData){
                    $this->error = $this->app->error;
                    return false;
                }

                $limit      = 0;
                $used       = 0;
                if(!isset($getData["acct"])) return false;
                foreach($getData["acct"] AS $act){
                    if($act["user"] == $user){
                        $limit = $act["limit"];
                        $used  = $act["totalbytes"];
                    }
                }

                if($limit == "unlimited") $limit = 0;



                $percent = 0;

                if($limit && $used) $percent = Utility::getPercent($used,$limit);

                if($percent>100) $percent = 100;

                return [
                    'limit' => $limit,
                    'used'  => $used,
                    'used-percent' => $percent,
                    'format-limit' => $limit ? FileManager::formatByte($limit) : "∞",
                    'format-used' => $used ? FileManager::formatByte($used) : "0 MB",
                ];
            }else
                return false;
        }

        public function getDisk(){
            $getData = $this->getAcSummary(false);
            if(!$getData){
                $this->error = $this->app->error;
                return false;
            }

            if(!isset($getData["acct"][0]["disklimit"])) return false;

            $limit  = $getData["acct"][0]["disklimit"];
            $used   = $getData["acct"][0]["diskused"];
            if($limit == "unlimited") $limit = 0;

            if($limit) $limit = FileManager::converByte($limit."B");
            if($used) $used = FileManager::converByte($used."B");

            if($limit && $used) $percent = Utility::getPercent($used,$limit);
            else $percent = 0;
            if($percent>100) $percent = 100;


            return [
                'limit' => $limit ? $limit : 0,
                'used'  => $used ? $used : 0,
                'used-percent' => $percent,
                'format-limit' => $limit ? FileManager::formatByte($limit) : "∞",
                'format-used' => $used ? FileManager::formatByte($used) : 0,
            ];
        }

        public function getSummary(){
            $getData = $this->getAcSummary(false);
            if(!$getData){
                $this->error = $this->app->error;
                return false;
            }
            $email_limit        = $getData["acct"][0]["maxpop"];
            $database_limit     = $getData["acct"][0]["maxsql"];
            $addons_limit       = $getData["acct"][0]["maxaddons"];

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
                $getData = $this->app->accountsummary($username);
                if(!$getData){
                    $this->error = $this->app->error;
                    return false;
                }
                $this->storage["accountsummary"] = $getData;
                return $getData;
            }else
                return $this->storage["accountsummary"];
            return false;
        }

        public function getEmailList(){
            if(isset($this->config["user"])){
                $getData = $this->app->Email_listpopswithdisk($this->config["user"]);
                if(!$getData){
                    $this->error = $this->app->error;
                    return false;
                }
                $data   = [];
                foreach($getData AS $var){
                    $quota_bytes    = $var["_diskquota"];
                    $quota_mb       = FileManager::showMB($quota_bytes);
                    $quota_show     = FileManager::formatByte($quota_bytes);
                    $used_bytes     = $var["_diskused"];
                    $used_mb        = FileManager::showMB($used_bytes);
                    $used_show      = FileManager::formatByte($used_bytes);
                    $data[] = [
                        'username'  => $var["user"],
                        'domain'    => $var["domain"],
                        'email'     => $var["email"],
                        'used'      => $var["_diskused"] == "NAN" ? 0 : $used_show,
                        'used_mb'   => $var["_diskused"] == "NAN" ? 0 : $used_mb,
                        'limit'     => $var["_diskquota"] == 0 ? "unlimited" : $quota_show,
                        'limit_mb'  => $var["_diskquota"] == 0 ? "unlimited" : $quota_mb,
                    ];
                }
                Utility::sksort($data,"username",true);
                return $data;
            }else
                return false;
        }

        public function getForwardsList(){
            $getData = $this->app->Email_listforwards($this->config["user"]);
            if(!$getData){
                $this->error = $this->app->error;
                return false;
            }
            if($getData){
                $forwards = [];
                foreach($getData AS $row){
                    $forwards[] = [
                        'dest' => $row["dest"],
                        'forward' => $row["forward"],
                    ];
                }
            }
            return $forwards;
        }

        public function addNewEmail($username,$domain,$password,$quota,$unlimited){
            $quota  = $unlimited == 1 ? 0 : $quota;
            $add = $this->app->Email_addpop($this->config["user"],$domain,$username,$password,$quota);
            if(!$add){
                $this->error = $this->app->error;
                return false;
            }

            if(isset($add[0]["result"]) && $add[0]["result"] != 1){
                $this->error = $this->app->error;
                return false;
            }
            return true;
        }

        public function addNewEmailForward($domain,$dest,$forward){
            $add = $this->app->Email_addforward($this->config["user"],$domain,$dest,$forward);
            if(!$add){
                $this->error = $this->app->error;
                return false;
            }
            if(!isset($add[0]["email"]) || !isset($add[0]["domain"]) || !isset($add[0]["forward"])){
                $this->error = $this->app->error;
                return false;
            }
            return true;
        }

        public function setPassword($domain,$email,$password){
            $set = $this->app->Email_passwdpop($this->config["user"],$domain,$email,$password);
            if(!$set){
                $this->error = $this->app->error;
                return false;
            }
            if(isset($set[0]["result"]) && $set[0]["result"] != 1){
                $this->error = $this->app->error;
                return false;
            }
            return true;
        }

        public function setQuota($domain,$email,$quota,$unlimited){
            $quota = $unlimited == 1 ? 0 : $quota;
            $set = $this->app->Email_editquota($this->config["user"],$domain,$email,$quota);
            if(!$set){
                $this->error = $this->app->error;
                return false;
            }
            if(isset($set[0]["result"]) && $set[0]["result"] != 1){
                $this->error = $this->app->error;
                return false;
            }
            return true;
        }

        public function deleteEmail($domain,$email){
            $getData = $this->app->Email_delpop($this->config["user"],$domain,$email);
            if(!$getData){
                $this->error = $this->app->error;
                return false;
            }

            if(isset($getData[0]["result"]) && $getData[0]["result"] != 1){
                $this->error = $this->app->error;
                return false;
            }
            return true;
        }

        public function deleteEmailForward($dest,$forward){
            $getData = $this->app->Email_delforward($this->config["user"],$dest,$forward);
            if(!$getData){
                $this->error = $this->app->error;
                return false;
            }

            if(isset($getData[0]["status"]) && $getData[0]["status"] != 1){
                $this->error = $this->app->error;
                return false;
            }
            return true;
        }

        public function getPasswordStrength($password){
            $getData = $this->app->PasswdStrength_get_password_strength($this->config["user"],$password);
            if(!$getData){
                $this->error = $this->app->error;
                return false;
            }
            if(!isset($getData[0]["strength"]))
                return false;
            return $getData[0]["strength"];
        }


        public function getMailDomains(){
            if(isset($this->config["user"])){
                $getData = $this->app->Email_listmaildomains($this->config["user"]);
                if(!$getData && !is_array($getData)){
                    $this->error = $this->app->error;
                    return false;
                }
                $domains    = [];
                foreach($getData AS $domain){
                    $domains[] = $domain["domain"];
                }
                return $domains;
            }else
                return false;
        }

        public function getDomains(){
            if(isset($this->config["user"])){
                $getData = $this->app->AddonDomain_listaddondomains($this->config["user"]);
                if(!$getData && !is_array($getData)){
                    $this->error = $this->app->error;
                    return false;
                }
                $domains    = [$this->config["domain"]];
                foreach($getData AS $domain){
                    $domains[] = $domain["domain"];
                }
                return $domains;
            }else
                return false;
        }

        public function changePassword($oldpw,$newpw){
            if(!$oldpw) $getData = $this->app->passwd($this->config["user"],$newpw);
            else $getData = $this->app->Passwd_change_password($this->config["user"],$oldpw,$newpw);
            if(!$getData && !is_array($getData)){
                $this->error = $this->app->error;
                return false;
            }

            if(!$oldpw) if(isset($getData[0]["status"]) && $getData[0]["status"] != 1) return false;
            else{
                if(!$getData){
                    $this->error = $this->app->error;
                    return false;
                }
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
                if(!$this->getAcSummary(false,$new_c_user)) return false;
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

            if($new_c_user){
                if($new_reseller && $old_c_info != $new_c_info){
                    if($old_reseller)
                        $change  = $this->setReseller($new_config["user"],$new_c_info);
                    else
                        $change  = $this->setupReseller($new_config["user"],$new_c_info);

                    if(!$change) return false;
                }

                if($old_reseller && !$new_reseller && method_exists($this,"unSetupReseller")){
                    $change  = $this->unSetupReseller();
                    if(!$change) return false;
                }
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
            $nparams = [];

            foreach($params AS $key=>$val){
                if($key == "plan") $nparams["PLAN"] = $val;
                if($key == "disk_limit") $nparams["QUOTA"] = $val;
                if($key == "bandwidth_limit") $nparams["BWLIMIT"] = $val;
                if($key == "email_limit") $nparams["MAXPOP"] = $val;
                if($key == "database_limit") $nparams["MAXSQL"] = $val;
                if($key == "subdomain_limit") $nparams["MAXSUB"] = $val;
                if($key == "addons_limit") $nparams["MAXADDON"] = $val;
                if($key == "ftp_limit") $nparams["MAXFTP"] = $val;
                if($key == "park_limit") $nparams["MAXPARK"] = $val;
                if($key == "max_email_per_hour") $nparams["MAX_EMAIL_PER_HOUR"] = $val;
            }

            $getData = $this->app->modifyacct($this->config["user"],$nparams);
            if(!$getData && !is_array($getData)){
                $this->error = $this->app->error;
                return false;
            }

            if(!$getData){
                $this->error = $this->app->error;
                return false;
            }
            return true;
        }

        public function setReseller($user,$params=[]){
            $nparams = [];
            if(!$user) $user = $this->config["user"];

            if(isset($params["enable_account_limit"])){
                if($params["enable_account_limit"]){
                    $nparams["enable_account_limit"] = 1;
                    $nparams["account_limit"] = $params["account_limit"];
                }else{
                    $nparams["enable_account_limit"] = 0;
                    $nparams["account_limit"] = 0;
                }
            }else{
                $nparams["enable_account_limit"] = 0;
                $nparams["account_limit"] = 0;
            }

            if(isset($params["enable_resource_limits"])){
                if($params["enable_resource_limits"]){
                    $nparams["enable_resource_limits"] = 1;
                    $nparams["bandwidth_limit"] = $params["bandwidth_limit"] ? $params["bandwidth_limit"] : 1023998976;
                    $nparams["diskspace_limit"] = $params["disk_limit"] ? $params["disk_limit"] : 1023998976;
                }else{
                    $nparams["enable_resource_limits"] = 0;
                    $nparams["bandwidth_limit"] = 0;
                    $nparams["diskspace_limit"] = 0;
                }
            }else{
                $nparams["enable_resource_limits"] = 0;
                $nparams["bandwidth_limit"] = 0;
                $nparams["diskspace_limit"] = 0;
            }

            $getData = $this->app->setresellerlimits($user,$nparams);
            if(!$getData && !is_array($getData)){
                $this->error = $this->app->error;
                return false;
            }

            if(!$getData){
                $this->error = $this->app->error;
                return false;
            }

            if(isset($params["acllist"])){
                if($params["acllist"]){
                    $nparams = ['acllist' => $params["acllist"]];
                }else{
                    $nparams = $this->default_acllist;
                }

                $getData = $this->app->setacls($user,$nparams);
                if(!$getData && !is_array($getData)){
                    $this->error = $this->app->error;
                    return false;
                }

                if(!$getData){
                    $this->error = $this->app->error;
                    return false;
                }

            }

            return true;
        }

        public function removeAccount($user=false){
            if(!$user) $user = $this->config["user"];
            $getData = $this->app->removeacct($user);

            if($this->app->error)
                Modules::save_log("Servers","cPanel","removeacct","Order ID:#".$this->order["id"]." - remove",$this->app->error);

            if(stristr($this->app->error,"You do not have access to an account named")) return true;
            elseif(stristr($this->app->error,"timed out")) return true;
            elseif(stristr($this->app->error,"You do not have a user named")) return true;
            elseif(stristr($this->app->error,"Owner does not exist")) return true;
            elseif(stristr($this->app->error,"Not a reseller")) return true;

            if(!$getData && !is_array($getData)){
                $this->error = $this->app->error;
                return false;
            }

            if(!$getData){
                $this->error = $this->app->error;
                return false;
            }

            return true;
        }

        public function removeReseller($user=false){
            if(!$user) $user = $this->config["user"];
            $getData = $this->app->terminatereseller($user);

            if($this->app->error)
                Modules::save_log("Servers","cPanel","removeacct","Order ID:#".$this->order["id"]." - remove",$this->app->error);

            if(stristr($this->app->error,"You do not have access to an account named")) return true;
            elseif(stristr($this->app->error,"timed out")) return true;
            elseif(stristr($this->app->error,"You do not have a user named")) return true;
            elseif(stristr($this->app->error,"Owner does not exist")) return true;
            elseif(stristr($this->app->error,"Not a reseller")) return $this->removeAccount($user);
            if(!$getData && !is_array($getData)){
                $this->error = $this->app->error;
                return false;
            }

            if(!$getData){
                $this->error = $this->app->error;
                return false;
            }

            return true;
        }

        public function setupReseller($user=false,$params=[]){
            if(!$user) $user = $this->config["user"];

            $getData = $this->app->setupreseller($user);
            if(stristr($this->app->error,'tried to make a reseller out of a reseller')) return true;
            if(!$getData && !is_array($getData)){
                $this->error = $this->app->error;
                return false;
            }

            if(!$getData){
                $this->error = $this->app->error;
                return false;
            }

            sleep(3);
            if(!$this->setReseller($user,$params)) return false;

            return true;
        }

        public function suspend(){
            $getData = $this->app->suspendacct($this->config["user"]);

            if(!$getData && $this->app->error)
            {
                Modules::save_log("Servers","cPanel","suspend","Order ID:#".$this->order["id"]." - suspend",$this->app->error);
                if(stristr($this->app->error,"timed out")) return true;
            }

            if(!$getData){
                $this->error = $this->app->error;
                return false;
            }


            return true;
        }

        public function suspend_reseller(){
            $getData = $this->app->suspendreseller($this->config["user"]);

            if(!$getData && $this->app->error)
            {
                Modules::save_log("Servers","cPanel","suspend_reseller","Order ID:#".$this->order["id"]." - suspend reseller",$this->app->error);
                if(stristr($this->app->error,"timed out")) return true;
            }

            if(!$getData){
                $this->error = $this->app->error;
                return false;
            }

            return true;
        }

        public function unsuspend(){
            $getData = $this->app->unsuspendacct($this->config["user"]);

            if(!$getData && $this->app->error)
            {
                Modules::save_log("Servers","cPanel","unsuspend","Order ID:#".$this->order["id"]." - unsuspend",$this->app->error);
                if(stristr($this->app->error,"timed out")) return true;
            }


            if(!$getData){
                $this->error = $this->app->error;
                return false;
            }

            return true;
        }

        public function unsuspend_reseller(){
            $getData = $this->app->unsuspendreseller($this->config["user"]);

            if(!$getData && $this->app->error)
            {
                Modules::save_log("Servers","cPanel","unsuspendreseller","Order ID:#".$this->order["id"]." - unsuspend",$this->app->error);
                if(stristr($this->app->error,"timed out")) return true;
            }


            if(!$getData && !is_array($getData)){
                $this->error = $this->app->error;
                return false;
            }

            if(!$getData){
                $this->error = $this->app->error;
                return false;
            }

            return true;
        }

        public function change_plan($plan){
            if($plan == '') return true;

            $getData = $this->app->changepackage($this->config["user"],$plan);
            if(!$getData && !is_array($getData)){
                $this->error = $this->app->error;
                return false;
            }

            if(!$getData){
                $this->error = $this->app->error;
                return false;
            }

            return true;
        }

        public function change_quota($quota){
            $getData = $this->app->editquota($this->config["user"],$quota);
            if(!$getData && !is_array($getData)){
                $this->error = $this->app->error;
                return false;
            }

            if(!$getData){
                $this->error = $this->app->error;
                return false;
            }

            return true;
        }

        public function change_bandwidth($bwlimit){
            $getData = $this->app->limitbw($this->config["user"],$bwlimit);
            if(!$getData && !is_array($getData)){
                $this->error = $this->app->error;
                return false;
            }

            if(!$getData){
                $this->error = $this->app->error;
                return false;
            }

            return true;
        }

        public function listAccounts(){

            $getData = $this->app->listaccts(false,false,"domain,user,unix_startdate,diskused,disklimit,plan,suspended,suspendtime");
            if(!$getData && !is_array($getData)){
                $this->error = $this->app->error;
                return false;
            }

            $data = [];

            if($getData && is_array($getData) && isset($getData["acct"])){
                foreach($getData["acct"] AS $row){
                    $item   = [];
                    $unix_start_date = $row["unix_startdate"] ?? '*unknown*';

                    if($unix_start_date == "*unknown*" || gettype($unix_start_date) == "double") $unix_start_date = false;

                    $item["cdate"]  = $unix_start_date ? DateManager::timetostr("Y-m-d H:i",$row["unix_startdate"]) : '';
                    $item["domain"] = $row["domain"];
                    $item["username"] = $row["user"];
                    $item["plan"] = $row["plan"];
                    $item["suspended"] = $row["suspended"] ? true : false;
                    $item["suspended_time"] = $row["suspendtime"];
                    $disk_usage        = FileManager::converByte($row["diskused"]."B");
                    $disk_limit        = FileManager::converByte($row["disklimit"]."B");
                    $item["disk_usage"] = $disk_usage;
                    $item["disk_limit"] = $disk_limit;
                    $item["unix_cdate"] = $row["unix_startdate"];
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

            $options                = $this->options;
            $creation_info          = isset($options["creation_info"]) ? $options["creation_info"] : [];
            $reseller               = isset($creation_info["reseller"]) && $creation_info["reseller"];

            if(method_exists($this,'use_clientArea_SingleSignOn'))
                $buttons["panel"] = [
                    'url'       => $this->area_link . "?inc=use_method&method=SingleSignOn",
                    'name'      => $reseller ? __("website/account_products/login-whm") : __("website/account_products/login-cpanel"),
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

            $options                = $this->options;
            $creation_info          = isset($options["creation_info"]) ? $options["creation_info"] : [];
            $reseller               = isset($creation_info["reseller"]) && $creation_info["reseller"];

            if( Admin::isPrivilege(["MODULES_ROOT_LOGIN_BUTTON"]) && method_exists($this,'use_adminArea_root_SingleSignOn'))
                $buttons["root_panel"] = [
                    'url'       => $this->area_link . "?operation=hosting_use_method&use_method=root_SingleSignOn",
                    'name'      => __("admin/products/login-root-panel"),
                ];


            if(method_exists($this,'use_adminArea_SingleSignOn'))
                $buttons["panel"] = [
                    'url'       => $this->area_link . "?operation=hosting_use_method&use_method=SingleSignOn",
                    'name'      => $reseller ? __("website/account_products/login-whm") : __("website/account_products/login-cpanel"),
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
            $link                   = $reseller ? $this->WHM_link() : $this->cPanel_link();

            if($link)
            {
                Utility::redirect($link);
                return true;

            }
            else
            {
                echo  "Error :: ".$this->error;
            }
        }
        public function use_adminArea_root_SingleSignOn()
        {
            if(!Admin::isPrivilege(["MODULES_ROOT_LOGIN_BUTTON"])) exit();
            $link = $this->Root_link();

            if($link)
            {
                Utility::redirect($link);
                return true;
            }
            else
            {
                echo  "Error :: ".$this->error;
            }

        }
        public function use_clientArea_SingleSignOn()
        {
            $options                = $this->options;
            $creation_info          = isset($options["creation_info"]) ? $options["creation_info"] : [];
            $reseller               = isset($creation_info["reseller"]) && $creation_info["reseller"];
            $link                   = $reseller ? $this->WHM_link() : $this->cPanel_link();

            if($link)
            {
                Utility::redirect($link);
                return true;

            }
            else
            {
                echo  "Error :: ".$this->error;
            }
        }
        public function use_clientArea_webMail()
        {
            Utility::redirect($this->WebMail_link());
            return true;
        }

        private function cPanel_link(){
            $params                 = [
                'user'              => isset($this->config["user"]) ? $this->config["user"] : '',
                'service'           => "cpaneld",
            ];

            $get_session            = $this->app->create_user_session($params);
            if(!$get_session)
            {
                $this->error = $this->app->error;
                return false;
            }

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
                $link .= "2083";
            else
                $link .= "2082";

            $link .= $get_session["cp_security_token"];

            $link .= "/login/?session=".$get_session["session"];

            return $link;
        }
        private function WebMail_link(){
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
                $link .= "2096";
            else
                $link .= "2095";

            $link .= "/login";

            return $link;
        }
        private function WHM_link(){
            $params                 = [
                'user'              => $this->config["user"],
                'service'           => "whostmgrd",
            ];

            $get_session            = $this->app->create_user_session($params);
            if(!$get_session)
            {
                $this->error = $this->app->error;
                return false;
            }

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

            $link .= $get_session["cp_security_token"];

            $link .= "/login/?session=".$get_session["session"];

            return $link;
        }
        private function Root_link(){
            $params                 = [
                'user'              => $this->server["username"],
                'service'           => "whostmgrd",
            ];

            $get_session            = $this->app->create_user_session($params);
            if(!$get_session)
            {
                $this->error = $this->app->error;
                return false;
            }

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

            $link .= $get_session["cp_security_token"];

            $link .= "/login/?session=".$get_session["session"];

            return $link;
        }
    }

    Hook::add("ClientAreaEndBody",1,function(){
        if(Controllers::$init->getData("module") != "cPanel") return false;
        return '
<script type="text/javascript">
$(document).ready(function(){
    $("#get_details_module_content").css("display","none");
});
</script>';
    });