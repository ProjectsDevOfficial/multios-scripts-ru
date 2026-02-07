<?php
    class HestiaCP_Module extends ServerModule
    {
        private $storage=[];
        public $api;

        function __construct($server,$options=[]){
            $this->force_setup = false;
            $this->_name = __CLASS__;
            parent::__construct($server,$options);
        }

        protected function define_server_info($server=[])
        {
            if(!class_exists("HestiaApi")) include __DIR__.DS."api.php";
            $this->api = new HestiaApi($server);
        }

        public function testConnect(){
            $test       = $this->api->call('v-list-user',[
                'arg1' => $this->server["username"],
                'arg2' => 'json',
            ],'json');

            if(!$test){
                $this->error = $this->api->error;
                return false;
            }
            return true;
        }
        public function getPlans(){
            return false;
        }
        public function createAccount($domain,$options=[]){
            $domain         = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $username       = $this->UsernameGenerator($domain);
            $password       = Utility::generate_hash(12,false,"lud");
            if(isset($options["username"])) $username = $options["username"];
            if(isset($options["password"])) $password = $options["password"];

            $username       = str_replace("-","",$username);

            $creation_info  = isset($options["creation_info"]) ? $options["creation_info"] : [];

            $package        = isset($creation_info["plan"]) ? $creation_info["plan"] : 'default';
            $ssh_access     = isset($creation_info["ssh_access"]) ? $creation_info["ssh_access"] : false;
            $ip_address     = isset($creation_info["ip_address"]) ? $creation_info["ip_address"] : false;


            if(!$package) $package = "default";


            $create_user     = $this->api->call('v-add-user',[
                'arg1' => $username,
                'arg2' => $password,
                'arg3' => $this->user["email"],
                'arg4' => $package,
                'arg5' => $this->user["name"],
                'arg6' => $this->user["surname"],
            ]);

            if(!$create_user && $this->api->code != 204 && $this->api->error)
            {
                $this->error = $this->api->error;
                return false;
            }

            if($ssh_access){
                $apply_ssh_access = $this->api->call('v-change-user-shell',[
                    'arg1' => $username,
                    'arg2' => 'bash',
                ]);

                if(!$apply_ssh_access && $this->api->error && $this->api->code != 204){
                    $this->error = "SSH Access permission could not be granted. ".$this->api->error;
                    return false;
                }
            }

            $create_account     = $this->api->call('v-add-domain',[
                'arg1'          => $username,
                'arg2'          => $domain,
                'arg3'          => $ip_address,
            ]);

            if(!$create_account && $this->api->error && $this->api->code != 204){
                $this->error = "Could not create account: ".$this->api->error;
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
            return false;
        }

        public function apply_options($old_options,$new_options=[]){
            $old_config     = isset($old_options["config"]) ? $old_options["config"] : [];
            $new_config     = isset($new_options["config"]) ? $new_options["config"] : [];

            $old_c_info     = isset($old_options["creation_info"]) ? $old_options["creation_info"] : [];
            $new_c_info     = isset($new_options["creation_info"]) ? $new_options["creation_info"] : [];

            $old_c_user     = isset($old_config["user"]) ? $old_config["user"] : '';
            $new_c_user     = isset($new_config["user"]) ? $new_config["user"] : '';


            $old_plan       = isset($old_c_info["plan"]) ? $old_c_info["plan"] : false;
            $new_plan       = isset($new_c_info["plan"]) ? $new_c_info["plan"] : false;

            if($old_c_user !== $new_c_user && $new_c_user){
                if(!$this->detail($new_c_user)) return false;
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
            }


            $new_options["config"]          = $new_config;
            $new_options["creation_info"]   = $new_c_info;

            return $new_options;
        }

        public function apply_updowngrade($orderopt=[],$product=[]){
            $creation_info      = isset($product["module_data"]["create_account"]) ? $product["module_data"]["create_account"] : [];
            if(!$creation_info || isset($product["module_data"]["plan"])) $creation_info = $product["module_data"];

            $p_plan             = isset($creation_info["plan"]) ? $creation_info["plan"] : false;

            if($p_plan){
                $change  = $this->change_plan($p_plan);
                if(!$change) return false;
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
            $details        = $this->detail($user);
            if(!$details) return false;

            $info       = $details[$user];


            if($info["BANDWIDTH"] == "unlimited") $info["BANDWIDTH"] = 0;

            $limit_byte      = $info["BANDWIDTH"] ? FileManager::converByte($info["BANDWIDTH"]."MB") : 0;
            $used_byte       = $info["U_BANDWIDTH"] ? FileManager::converByte($info["U_BANDWIDTH"]."MB") : 0;

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
            $details        = $this->detail($user);
            if(!$details) return false;

            $info       = $details[$user];

            if($info["DISK_QUOTA"] == "unlimited") $info["DISK_QUOTA"] = 0;

            $limit_byte      = $info["DISK_QUOTA"] ? FileManager::converByte($info["DISK_QUOTA"]."MB") : 0;
            $used_byte       = $info["U_DISK"] ? FileManager::converByte($info["U_DISK"]."MB") : 0;

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

        private function detail($user=false){
            if(isset($this->storage["details"][$user])) return $this->storage["details"][$user];

            $result = $this->api->call('v-list-user',[
                'arg1' => $user,
                'arg2' => "json",
            ],'json');

            if(!$result){
                $this->error = "Could not get data";
                return false;
            }

            $this->storage["details"][$user] = $result;

            return $result;

        }
        public function getSummary(){
            if(!isset($this->config["user"]) || !$this->config["user"]) return false;
            return [
                'database_limit'    => $this->options["database_limit"],
                'addons_limit'      => $this->options["addons_limit"],
                'email_limit'       => $this->options["email_limit"],
            ];
        }

        public function getEmailList(){
            return false;
        }
        public function getForwardsList(){
            return false;
        }
        public function addNewEmail($username,$domain,$password,$quota,$unlimited){
            return true;
        }
        public function addNewEmailForward($domain,$dest,$forward){
            return true;
        }
        public function setPassword($domain,$email,$password){
            return true;
        }
        public function setQuota($domain,$email,$quota,$unlimited){
            return true;
        }
        public function deleteEmail($domain,$email){
            return true;
        }
        public function deleteEmailForward($dest,$forward){
            return true;
        }
        public function getPasswordStrength($password){
            return 100;
        }
        public function getMailDomains(){
            return false;
        }
        public function getDomains(){
            return false;
        }

        public function changePassword($oldpw,$newpw){
            $user   = $this->config["user"];
            $apply  = $this->api->call('v-change-user-password',[
                'arg1' => $user,
                'arg2' => $newpw,
            ]);

            if(!$apply){
                $this->error = $this->api->error;
                return false;
            }

            if($apply != 'OK'){
                $this->error = 'Could not change password: '.$apply;
                return false;
            }

            return true;
        }

        public function setReseller($user,$params=[]){
            return true;
        }

        public function removeAccount($user=false){
            if(!$user) $user = $this->config["user"];
            $apply      = $this->api->call('v-delete-user',[
                'arg1' => $user,
            ]);
            if(!$apply && $this->api->error && $this->api->code != 204){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function removeReseller($user=false){
            return $this->removeAccount($user);
        }

        public function setupReseller($user=false,$params=[]){
            return true;
        }

        public function suspend(){
            $apply      = $this->api->call('v-suspend-user',[
                'arg1' => $this->config["user"],
            ]);
            if(!$apply && $this->api->error && $this->api->code != 204){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function suspend_reseller(){
            return $this->suspend();
        }

        public function unsuspend(){
            $apply      = $this->api->call('v-unsuspend-user',[
                'arg1' => $this->config["user"],
            ]);
            if(!$apply && $this->api->error && $this->api->code != 204){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function unsuspend_reseller(){
            return $this->unsuspend();
        }

        public function change_plan($plan){
            if($plan == '') return true;

            $apply      = $this->api->call('v-change-user-package',[
                'arg1'  => $this->config["user"],
                'arg2'  => $plan,
            ]);
            if(!$apply && $this->api->error && $this->api->code != 204){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function change_quota($quota){
            return true;
        }

        public function change_bandwidth($bwlimit){
            return true;
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

        private function panel_login(){
            $link       = $this->server["secure"] ? 'https://' : 'http://';
            $link       .= $this->server["ip"].":".$this->server["port"]."/login/";
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
                <input type="hidden" name="user" value="<?php echo $username; ?>">
                <input type="hidden" name="password" value="<?php echo $password; ?>">
            </form>
            <?php

            return true;
        }
        private function root_panel_login(){

            $link       = $this->server["secure"] ? 'https://' : 'http://';
            $link       .= $this->server["ip"].":".$this->server["port"]."/login/";
            $username   = $this->server["username"];
            $password   = $this->server["password"];

            ?>
            <script type="text/javascript">
                setTimeout(function(){
                    var x = document.getElementById("RedirectForm").submit();
                },1);
            </script>
            <form action="<?php echo $link; ?>" method="POST" id="RedirectForm">
                <input type="hidden" name="user" value="<?php echo $username; ?>">
                <input type="hidden" name="password" value="<?php echo $password; ?>">
            </form>
            <?php
            return true;
        }
        private function WebMail_link(){
            $link = '';

            if($this->server["secure"])
                $link .= "https";
            else
                $link .= "http";

            $link .= "://".$this->server["ip"];

            $link .= "/webmail";

            return $link;
        }

        public function clientArea()
        {
            $content    = '';
            $_page      = $this->page;
            $_data      = [
                'LANG' => $this->lang,
            ];

            if(!$_page) $_page = 'home';

            if($_page == 'home')
            {
                $_data["username"] = $this->config["user"];
                $_data["password"] = $this->config["password"];
            }

            $content .= $this->get_page('clientArea-'.$_page,$_data);
            return  $content;
        }

    }