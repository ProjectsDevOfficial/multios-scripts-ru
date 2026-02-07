<?php

    class Virtualizor_Module extends ServerModule
    {
        public $storage=[],$api,$api_u,$vlang;

        function __construct($server,$options=[]){
            $this->_name = __CLASS__;
            parent::__construct($server,$options);
        }

        public function define_server_info($server=[])
        {
            if(!class_exists("Virtualizor_Admin_API")) include __DIR__ . DS . "sdk" . DS . "admin.php";
            if(!class_exists("Virtualizor_Enduser_API"))  include __DIR__ . DS . "sdk" . DS . "enduser.php";

            $this->entity_id_name = "vpsid";

            $ip             = $server["ip"];
            $username       = $server["username"];
            $password       = $server["password"];
            $port           = $server["port"];
            $this->api      = new Virtualizor_Admin_API($ip,$username,$password,$port);
            $this->api_u    = new Virtualizor_Enduser_API($ip,$username,$password);
        }

        public function addon_create($addon=[],$params=[])
        {
            return true;
        }
        public function addon_suspend($addon=[],$params=[])
        {
            return true;
        }
        public function addon_unsuspend($addon=[],$params=[])
        {
            return true;
        }
        public function addon_cancelled($addon=[],$params=[])
        {
            return [];
        }
        public function addon_change($addon=[],$params=[]){
            return true;
        }

        public function testConnect(){
            $connect        = $this->api->addvs();

            if($connect["title"] == NULL){
                $this->error = 'FAILED: Could not connect to Virtualizor. Please make sure that all Ports from 4081 to 4085 are open on your Master Server or please check the server details entered are as displayed on Admin Panel >> Configuration >> Server Info.';
                return false;
            }
            return true;
        }

        public function server_list(){
            if(isset($this->storage["server_list"])) return $this->storage["server_list"];
            $rows       = $this->api->listservers(1,100);

            if(isset($rows["error"]) && $rows["error"]){
                $this->error = is_array($rows["error"]) ? implode(" , ",$rows["error"]) : $rows["error"];
                return false;
            }
            elseif(isset($rows["servs"])){
                $list = [];
                if($rows["servs"])
                {
                    foreach($rows["servs"] AS $serv){
                        $k      = $serv["virt"];
                        if($k == "kvm lxc") $k = "kvm";
                        $k_s    = explode(" ",$k);

                        if(sizeof($k_s) > 1)
                        {
                            foreach($k_s AS $v)
                            {
                                $list[$v][] = $serv;
                            }

                        }
                        else
                            $list[$k][] = $serv;
                    }
                }

                $this->storage["server_list"] = $list;
                return $list;
            }

            if($this->api->error){
                $this->error = is_array($this->api->error) ? implode(", ",$this->api->error) : $this->api->error;
                return false;
            }
            else $this->error = "failed to fetch the server list";
            return false;
        }
        public function server_groups(){
            if(isset($this->storage["server_groups"])) return $this->storage["server_groups"];
            $rows       = $this->api->servergroups();

            if(isset($rows["error"]) && $rows["error"]){
                $this->error = is_array($rows["error"]) ? implode(" , ",$rows["error"]) : $rows["error"];
                return false;
            }
            elseif(isset($rows["servergroups"])){
                $list = $rows["servergroups"];
                $this->storage["server_groups"] = $list;
                return $list;
            }

            if($this->api->error){
                $this->error = is_array($this->api->error) ? implode(", ",$this->api->error) : $this->api->error;
                return false;
            }
            else $this->error = "failed to fetch the server groups";
            return false;
        }
        public function get_plans(){
            if(isset($this->storage["get_plans"])) return $this->storage["get_plans"];
            $rows       = $this->api->plans(1,100);

            if(isset($rows["error"]) && $rows["error"]){
                $this->error = is_array($rows["error"]) ? implode(" , ",$rows["error"]) : $rows["error"];
                return false;
            }
            elseif(isset($rows["plans"])){
                $list = [];
                foreach ($rows["plans"] as $p_id=>$plan) $list[$plan["virt"]][$p_id] = $plan;

                $this->storage["get_plans"] = $list;
                return $list;
            }

            if($this->api->error){
                $this->error = is_array($this->api->error) ? implode(", ",$this->api->error) : $this->api->error;
                return false;
            }
            else $this->error = "failed to fetch the plans";
            return false;
        }
        public function os_templates(){
            if(isset($this->storage["os_templates"])) return $this->storage["os_templates"];
            $rows       = $this->api->ostemplates(1,500);

            if(isset($rows["error"]) && $rows["error"]){
                $this->error = is_array($rows["error"]) ? implode(" , ",$rows["error"]) : $rows["error"];
                return false;
            }
            elseif(isset($rows["ostemplates"])){
                $list = [];
                foreach ($rows["ostemplates"] as $os_id=>$os)
                {
                    $os["osid"] = $os_id;
                    $list[$os["type"]][$os_id] = $os;
                }

                $this->storage["os_templates"] = $list;
                return $list;
            }

            if($this->api->error){
                $this->error = is_array($this->api->error) ? implode(", ",$this->api->error) : $this->api->error;
                return false;
            }
            else $this->error = "failed to fetch the os templates";
            return false;
        }
        public function media_groups(){
            if(isset($this->storage["media_groups"])) return $this->storage["media_groups"];
            $rows       = $this->api->mediagroups(1,500);

            if(isset($rows["error"]) && $rows["error"]){
                $this->error = is_array($rows["error"]) ? implode(" , ",$rows["error"]) : $rows["error"];
                return false;
            }
            elseif(isset($rows["mediagroups"])){
                $list = [];
                foreach ($rows["mediagroups"] as $mg_id=>$mg) $list[$mg["mg_type"]][$mg_id] = $mg;

                $this->storage["media_groups"] = $list;
                return $list;
            }

            if($this->api->error){
                $this->error = is_array($this->api->error) ? implode(", ",$this->api->error) : $this->api->error;
                return false;
            }
            else $this->error = "failed to fetch the media groups";
            return false;
        }
        public function dns_plans(){
            if(isset($this->storage["dns_plans"])) return $this->storage["dns_plans"];
            $rows       = $this->api->listdnsplans(1,500);

            if(isset($rows["error"]) && $rows["error"]){
                $this->error = is_array($rows["error"]) ? implode(" , ",$rows["error"]) : $rows["error"];
                return false;
            }
            elseif(isset($rows["dnsplans"])){
                $list = $rows["dnsplans"];

                $this->storage["dns_plans"] = $list;
                return $list;
            }

            if($this->api->error){
                $this->error = is_array($this->api->error) ? implode(", ",$this->api->error) : $this->api->error;
                return false;
            }
            else $this->error = "failed to fetch the media groups";
            return false;
        }
        public function get_info($id=0){
            if(!$id) $id = isset($this->config["vpsid"]) ? $this->config["vpsid"] : 0;

            if(isset($this->storage["get_info"][$id])) return $this->storage["get_info"][$id];
            $rows       = $this->api->status([$id]);


            if(isset($rows["error"]) && $rows["error"]){
                $this->error = is_array($rows["error"]) ? implode(" , ",$rows["error"]) : $rows["error"];
                return false;
            }
            elseif(isset($rows[$id])){
                $list = $rows[$id];

                $this->storage["get_info"][$id] = $list;
                return $list;
            }


            if($this->api->error){
                $this->error = is_array($this->api->error) ? implode(", ",$this->api->error) : $this->api->error;
                return false;
            }
            else $this->error = "Not found vps info";
            return false;
        }
        public function create($params=[]){

            $config               = Modules::Config("Servers","Virtualizor");



            $temp_data            = User::getInfo($this->user["id"],['VirtualizorPW']);
            $virtPW               = $temp_data["VirtualizorPW"];

            if($virtPW) $virtPW = Crypt::decode($virtPW,Config::get("crypt/user"));


            $post               = [];
            $c_info             = isset($params["creation_info"]) ? $params["creation_info"] : [];
            $first_name         = Filter::transliterate($this->user["name"]);
            $last_name          = Filter::transliterate($this->user["surname"]);
            $email              = $this->user["email"];
            $password           = $virtPW ? $virtPW : Utility::generate_hash(20,false,'lud');
            $hostname           = isset($params["hostname"]) ? $params["hostname"] : '';
            $type               = 'OpenVZ';
            $slave_server       = 'auto';
            $server_group       = '';
            if(!$virtPW) User::setInfo($this->user["id"],['VirtualizorPW'=> Crypt::encode($password,Config::get("crypt/user"))]);

            // Params
            $plan               = isset($c_info["plan"]) ? $c_info["plan"] : 0;
            $os_id              = isset($c_info["osid"]) ? $c_info["osid"] : 0;
            $disk_space         = isset($c_info["disk_space"]) ? $c_info["disk_space"] : 0;
            $ram                = isset($c_info["ram"]) ? $c_info["ram"] : 0;
            $ips                = isset($c_info["ips"]) ? $c_info["ips"] : 0;
            $bandwidth          = isset($c_info["bandwidth"]) ? $c_info["bandwidth"] : 0;
            $cpu_cores          = isset($c_info["cpu_cores"]) ? $c_info["cpu_cores"] : 0;
            $network_speed      = isset($c_info["network_speed"]) ? $c_info["network_speed"] : 0;
            $mgs                = isset($c_info["mgs"]) ? $c_info["mgs"] : [];
            $priority           = isset($c_info["priority"]) ? $c_info["priority"] : 0;
            $cpu_units          = isset($c_info["cpu_units"]) ? $c_info["cpu_units"] : 0;
            $cpu_percent        = isset($c_info["cpu_percent"]) ? $c_info["cpu_percent"] : 0;
            $burst_ram          = isset($c_info["burst_ram"]) ? $c_info["burst_ram"] : 0;
            $swap_ram           = isset($c_info["swap_ram"]) ? $c_info["swap_ram"] : 0;
            $vnc                = isset($c_info["vnc"]) ? $c_info["vnc"] : 0;
            $ips6               = isset($c_info["ips6"]) ? $c_info["ips6"] : 0;
            $ips6_subnet        = isset($c_info["ips6_subnet"]) ? $c_info["ips6_subnet"] : 0;
            $dns_plan           = isset($c_info["dns_plan"]) ? $c_info["dns_plan"] : 0;
            $band_suspend       = isset($c_info["band_suspend"]) ? $c_info["band_suspend"] : 0;
            $tuntap             = isset($c_info["tuntap"]) ? $c_info["tuntap"] : 0;
            $ips_int            = isset($c_info["ips_int"]) ? $c_info["ips_int"] : 0;
            $virtio             = isset($c_info["virtio"]) ? $c_info["virtio"] : 0;
            $upload_speed       = isset($c_info["upload_speed"]) ? $c_info["upload_speed"] : 0;
            $dns                = isset($c_info["dns"]) ? $c_info["dns"] : [];
            $osreinstall_limit  = isset($c_info["osreinstall_limit"]) ? $c_info["osreinstall_limit"] : '';
            $admin_managed      = isset($c_info["admin_managed"]) ? $c_info["admin_managed"] : '';


            $types              = $config["types"];

            if(isset($c_info["type"])) $type = $c_info["type"];

            $type_k           = $types[$type];

            if(isset($params["login"]["password"]) && $params["login"]["password"]){
                $password = $params["login"]["password"];
                if($password_d = Crypt::decode($password,Config::get("crypt/user")))
                    $password = $password_d;
            }

            if(isset($this->val_of_requirements["hostname"]) && $this->val_of_requirements["hostname"])
                $hostname = Filter::html_clear($this->val_of_requirements["hostname"]);


            if(!$hostname)
                $hostname = $first_name."_".$last_name."_".$this->order["id"]."_".DateManager::Now("Y_m_d_H_i");

            $hostname = Filter::html_clear(str_replace(" ",'',$hostname));


            if(isset($c_info["slave_server"]))
                $slave_server = $c_info["slave_server"];

            if(substr($slave_server,0,1) == "G"){
                $server_group = substr($slave_server,2);
                $slave_server = '';
            }

            if(isset($this->val_of_requirements["sgid"]) && $this->val_of_requirements["sgid"])
            {
                $server_group = $this->val_of_requirements["sgid"];
                $slave_server = '';
            }

            if(isset($this->val_of_conf_opt["sgid"]) && $this->val_of_conf_opt["sgid"])
            {
                $server_group = $this->val_of_conf_opt["sgid"];
                $slave_server = '';
            }




            $post['user_email']         = $email;
            $post['user_pass']          = $password;
            $post['hostname']           = $hostname;
            $post['rootpass']           = $password;
            $post['fname']              = $first_name;
            $post['lname']              = $last_name;

            $get_servers        = $this->server_list();
            if(!$get_servers && $this->error) return false;

            if(!in_array($type_k,array_keys($get_servers))){
                $this->error = "Could not find server suitable for Specified type: ".$type_k;
                return false;
            }


            $get_servers    = $get_servers[$type_k];


            $hvm            = preg_match('/hvm/is',$type) ? 1 : 0;

            $server_id      = NULL;

            if($slave_server == 'auto'){
                foreach($get_servers AS $s){
                    if($server_id === NULL){
                        if($server_group !== '' && $s["sgid"] != $server_group) continue;

                        $s["_ram"] = $s["overcommit"] ? ($s['overcommit'] - $s['alloc_ram']) : $s['ram'];

                        // Xen HVM additional check
                        if($hvm && !$s["hvm"]) continue;

                        // Do you have enough space
                        if($s['space'] < $disk_space) continue;
                        if($s["locked"]) continue;
                        $ser_setting = unserialize($s['settings']);

                        // Reached the limit of vps creation ?
                        if($ser_setting['vpslimit'] && $s['numvps'] >= $ser_setting['vpslimit']) continue;

                        if($s['_ram'] < $ram) continue;

                        $server_id = $s["serid"];
                    }
                }
            }
            elseif($server_group !== ''){
                $post["server_group"] = $server_group;
            }
            else $server_id = $slave_server;



            $post['slave_server'] = $server_id;

            if($plan){

                $get_plans              = $this->get_plans();


                $plan_data          = [];
                if($get_plans[$type_k])
                {
                    foreach($get_plans[$type_k] AS $p) if($p["plid"] == $plan) $plan_data = $p;
                }

                if(!$plan_data){
                    $this->error = 'Could not found data for Specified plan';
                    return false;
                }
            }

            $post['osid']            = $os_id;
            if($plan) $post['plid'] = $plan;

            $post['num_ips']         = $ips ? $ips : 1;
            $post['num_ips6']        = $ips6;
            $post['num_ips6_subnet'] = $ips6_subnet;
            $post['num_ips_int']     = $ips_int;

            if(!$plan){
                $post['space']           = $disk_space;
                $post['ram']             = $ram;
                $post['bandwidth']       = $bandwidth;
                $post['cores']           = $cpu_cores;
                $post['network_speed']   = $network_speed;
                $post['mgs']             = $mgs ? (is_array($mgs) ? implode(',',$mgs) : $mgs) : '';
                $post['priority']        = $priority;
                $post['cpu']             = $cpu_units;
                $post['burst']           = $burst_ram;
                $post['swapram']         = $swap_ram;
                $post['cpu_percent']     = $cpu_percent;
                $post['vnc']             = $vnc;
                $post['vnc']             = $vnc ? 1 : 0;
                $post['dnsplid']         = $dns_plan;
                $post['band_suspend']    = $band_suspend;
                $post['tuntap']          = $tuntap;
                $post['virtio']          = $virtio;
                $post['upload_speed']    = $upload_speed;
                $post['dns']             = $dns ? $dns : '';
                $post['osreinstall_limit'] = $osreinstall_limit;
                $post['admin_managed']   = $admin_managed;
            }

            $post['vncpass']         = $vnc ? Utility::generate_hash(7,false,'lud') : '';
            $post['noemail']         = 1;

            $post["virt"]            = $type_k;



            if(($type_k == 'xen' || $type_k == 'xcp') && $hvm){
                $post['shadow']      = 8;
                $post['hvm']         = 1;
            }

            $configurable_options = $this->val_of_requirements;
            if($this->val_of_conf_opt) $configurable_options = array_merge($configurable_options,$this->val_of_conf_opt);

            $cfs_value_keys     = [
                'Number of IPs'                 => 'num_ips',
                'Number of IPv6 Address'        => 'num_ips6',
                'Number of IPv6 Subnet'         => 'num_ips6_subnet',
                'Number of Internal IP Address' => 'num_ips_int',
                'Space'                         => 'space',
                'RAM'                           => 'ram',
                'Bandwidth'                     => 'bandwidth',
                'CPU Cores'                     => 'cores',
                'Network Speed'                 => 'network_speed',
                'CPU Percent'                   => 'cpu_percent',
                'Operating System'              => 'osid',
                'Total I/Os per sec'            => 'total_iops_sec',
                'Read Mega Bytes/s'             => 'read_bytes_sec',
                'Write Mega Bytes/s'            => 'write_bytes_sec',
            ];

            $cfs_value_keys_flip                = [
                'num_ips'                       => 'ips',
                'num_ips6'                      => 'ips6',
                'num_ips6_subnet'               => 'ips6_subnet',
                'num_ips_int'                   => 'ips_int',
                'space'                         => 'disk_space',
                'ram'                           => 'ram',
                'bandwidth'                     => 'bandwidth',
                'cores'                         => 'cpu_cores',
                'swapram'                       => 'swap_ram',
                'dnsplid'                       => 'dns_plan',
            ];

            $os_templates       = $this->os_templates();


            if($configurable_options){
                foreach($configurable_options AS $k=>$v){
                    $cf_key = isset($cfs_value_keys[$k]) ? $cfs_value_keys[$k] : $k;
                    if($k == 'Operating System'){
                        if(Validation::isInt($v))
                            $post[$cf_key] = $v;
                        elseif($os_templates && isset($os_templates[$type_k])){
                            foreach($os_templates[$type_k] AS $os_template){
                                $os_found = 0;
                                if($v == $os_template["name"])
                                {
                                    $post[$cf_key] = $os_template["osid"];
                                    $os_found = $os_template["osid"];
                                }
                                if(!$os_found)
                                {
                                    $this->error = " ".$v." : Cannot find results for the specified type and operating system name.";
                                    return false;
                                }
                            }
                        }
                    }
                    else $post[$cf_key] = $v;
                    if($cf_key == 'mgs') $v = implode(",",$v);
                    $cf_key2 = isset($cfs_value_keys_flip[$cf_key]) ? $cfs_value_keys_flip[$cf_key] : $cf_key;
                    $params["creation_info"][$cf_key2] = $v;
                }
            }

            if(isset($os_templates[$type_k]))
            {
                foreach($os_templates[$type_k] AS $os_template){
                    if($post["osid"] == $os_template["osid"] && $os_template["type"] == $type_k){
                        unset($post["osid"]);
                        $post["os_name"] = $os_template["name"];
                    }
                }
            }

            if(isset($post["hostname"]) && $post["hostname"]) $post["hostname"] = $hostname;

            /*
            $_ips           = [];
            $_ips6          = [];
            $_ips6_subnet   = [];
            $_ips_int       = [];

            if(isset($post["num_ips"]) && $post["num_ips"] > 0 && isset($ret['ips'])){
                if(is_array($ret["ips"])){
                    foreach($ret["ips"] AS $k=>$v){
                        $_ips[] = $v['ip'];
                        if($post["num_ips"] == sizeof($_ips)) break;
                    }
                }
                if(!$_ips || sizeof($_ips) < $post["num_ips"]){
                    $this->error = 'There are insufficient IPs on the server';
                    return false;
                }
            }

            if(isset($post["num_ips_int"]) && $post["num_ips_int"] > 0 && isset($ret['ips_int'])){
                if(is_array($ret["ips_int"])){
                    foreach($ret["ips_int"] AS $k=>$v){
                        $_ips_int[] = $v['ip'];
                        if($post["num_ips_int"] == sizeof($_ips_int)) break;
                    }
                }
                if(!$_ips_int || sizeof($_ips_int) < $post["num_ips_int"]){
                    $this->error = 'There are insufficient Internal IPs on the server';
                    return false;
                }
            }

            if(isset($post["num_ips6"]) && $post["num_ips6"] > 0 && isset($ret['ips6'])){
                if(is_array($ret["ips6"])){
                    foreach($ret["ips6"] AS $k=>$v){
                        $_ips6[] = $v['ip'];
                        if($post["num_ips6"] == sizeof($_ips6)) break;
                    }
                }
                if(!$_ips6 || sizeof($_ips6) < $post["num_ips6"]){
                    $this->error = 'There are insufficient IPv6 Addresses on the server';
                    return false;
                }
            }

            if(isset($post["num_ips6_subnet"]) && $post["num_ips6_subnet"] > 0 && isset($ret['ips6_subnet'])){
                if(is_array($ret["ips6_subnet"])){
                    foreach($ret["ips6_subnet"] AS $k=>$v){
                        $_ips6_subnet[] = $v['ip'];
                        if($post["num_ips6_subnet"] == sizeof($_ips6_subnet)) break;
                    }
                }
                if(!$_ips6_subnet || sizeof($_ips6_subnet) < $post["num_ips6_subnet"]){
                    $this->error = 'There are insufficient IPv6 Subnets on the server';
                    return false;
                }
            }

            if($_ips) $post['ips'] = $_ips;
            if($_ips_int) $post['ips_int'] = $_ips_int;
            if($_ips6) $post['ipv6'] = $_ips6;
            if($_ips6_subnet) $post['ipv6_subnet'] = $_ips6_subnet;
            */

            $post['node_select']     = 1;
            $post['addvps']          = 1;

            $create     = $this->api->addvs_v2($post);

            Modules::save_log("Servers","Virtualizor",'addvs',$post,$create);

            if(isset($create["error"]) && $create["error"]){
                $this->error = is_array($create["error"]) ? implode(" , ",$create["error"]) : $create["error"];
                return false;
            }
            if($this->api->error){
                $this->error = is_array($this->api->error) ? implode(", ",$this->api->error) : $this->api->error;
                return false;
            }

            $info       = $create['vs_info'];

            $_ips           = [];
            $_ips6          = [];
            $_ips6_subnet   = [];

            if(isset($info['ips']) && $info["ips"])
                $_ips       = $info['ips'];
            if(isset($info['ipv6']) && $info["ipv6"])
                $_ips6     = $info['ipv6'];
            if(isset($info['ipv6_subnet']) && $info["ipv6_subnet"])
                $_ips6_subnet     = $info['ipv6_subnet'];


            $os_username = (stristr(($post["os_name"] ?? ''),"windows") ? "Administrator" : "root");


            $returnData = [
                'ip'         => isset($_ips[0]) ? $_ips[0] : $_ips6[0],
                'panel_type' => "other",
                'panel_link' => '',
                'login' => [
                    'username'      => $email,
                    'password'      => Crypt::encode($password,Config::get("crypt/user"))
                ],
                'os_username'  => $os_username,
                'hostname'      => $hostname,
                'creation_info' => $params["creation_info"],
                'config' => ['vpsid' => $info["vpsid"]],
            ];

            $tmp_ips = empty($_ips) ? array() : $_ips;

            if(!empty($_ips6_subnet)){
                foreach($_ips6_subnet as $k => $v){
                    $tmp_ips[] = $v;
                }
            }

            if(!empty($_ips6)){
                foreach($_ips6 as $k => $v){
                    $tmp_ips[] = $v;
                }
            }

            // Extra IPs
            if(count($tmp_ips) > 1){
                unset($tmp_ips[0]);
                $returnData["assigned_ips"] = implode(EOL,$tmp_ips);
            }

            return $returnData;
        }
        public function apply_updowngrade($params=[]){
            $this->config       = isset($params["config"]) ? $params["config"] : [];
            $this->options      = $params;

            $updowngrade_remove = $this->server["updowngrade_remove_server"];

            if($updowngrade_remove == "now"){

                $this->api->poweroff($this->config["vpsid"]);

                if($this->terminate()) return $this->create($params);
                return false;
            }
            elseif(substr($updowngrade_remove,0,4) == "then")
                Events::add_scheduled_operation([
                    'owner'     => "order",
                    'owner_id'  => $this->order["id"],
                    'name'      => "remove-server-for-updowngrade",
                    'period'    => "day",
                    'time'      => substr($updowngrade_remove,5,4),
                    'module'    => "Virtualizor",
                    'command'   => "terminate",
                    'needs'     => ['options' => $this->options],
                ]);
            return $this->create($params);
        }
        public function terminate(){
            if(!isset($this->config["vpsid"])) return true;
            $apply =  $this->api->delete_vs($this->config["vpsid"]);
            if(isset($apply["error"]) && $apply["error"]){
                $this->error = is_array($apply["error"]) ? implode(" , ",$apply["error"]) : $apply["error"];
                return false;
            }
            if($this->api->error){
                $this->error = is_array($this->api->error) ? implode(", ",$this->api->error) : $this->api->error;
                return false;
            }
            if(isset($apply['done']) && !$apply['done'] && isset($apply['done_msg'])){
                $this->error = $apply['done_msg'];
                return false;
            }
            return true;
        }
        public function suspend(){
            if(!isset($this->config["vpsid"])) return true;
            $apply =  $this->api->suspend($this->config["vpsid"]);
            if(isset($apply["error"]) && $apply["error"]){
                $this->error = is_array($apply["error"]) ? implode(" , ",$apply["error"]) : $apply["error"];
                return false;
            }
            if($this->api->error){
                $this->error = is_array($this->api->error) ? implode(", ",$this->api->error) : $this->api->error;
                return false;
            }
            if(isset($apply['done']) && !$apply['done'] && isset($apply['done_msg'])){
                $this->error = $apply['done_msg'];
                return false;
            }

            return true;
        }
        public function unsuspend(){
            if(!isset($this->config["vpsid"])) return true;
            $apply =  $this->api->unsuspend($this->config["vpsid"]);
            if(isset($apply["error"]) && $apply["error"]){
                $this->error = is_array($apply["error"]) ? implode(" , ",$apply["error"]) : $apply["error"];
                return false;
            }
            if($this->api->error){
                $this->error = is_array($this->api->error) ? implode(", ",$this->api->error) : $this->api->error;
                return false;
            }
            if(isset($apply['done']) && !$apply['done'] && isset($apply['done_msg'])){
                $this->error = $apply['done_msg'];
                return false;
            }

            return true;
        }
        public function start(){return $this->power_on();}
        public function stop(){return $this->power_off();}
        public function power_on(){
            if(!isset($this->config["vpsid"])) return true;
            $apply =  $this->api->start($this->config["vpsid"]);
            if(isset($apply["error"]) && $apply["error"]){
                $this->error = is_array($apply["error"]) ? implode(" , ",$apply["error"]) : $apply["error"];
                return false;
            }
            if($this->api->error){
                $this->error = is_array($this->api->error) ? implode(", ",$this->api->error) : $this->api->error;
                return false;
            }
            if(isset($apply['done']) && !$apply['done'] && isset($apply['done_msg'])){
                $this->error = $apply['done_msg'];
                return false;
            }

            echo Utility::jencode([
                'status' => "successful",
                'message' => $this->lang["successful"],
                'timeRedirect' => [
                    'url' => $this->area_link,
                    'duration' => 1000
                ],
            ]);

            return true;
        }
        public function power_off(){
            if(!isset($this->config["vpsid"])) return true;
            $apply =  $this->api->poweroff($this->config["vpsid"]);
            if(isset($apply["error"]) && $apply["error"]){
                $this->error = is_array($apply["error"]) ? implode(" , ",$apply["error"]) : $apply["error"];
                return false;
            }
            if($this->api->error){
                $this->error = is_array($this->api->error) ? implode(", ",$this->api->error) : $this->api->error;
                return false;
            }
            if(isset($apply['done']) && !$apply['done'] && isset($apply['done_msg'])){
                $this->error = $apply['done_msg'];
                return false;
            }

            echo Utility::jencode([
                'status' => "successful",
                'message' => $this->lang["successful"],
                'timeRedirect' => [
                    'url' => $this->area_link,
                    'duration' => 1000
                ],
            ]);

            return true;
        }
        public function shutdown(){
            if(!isset($this->config["vpsid"])) return true;
            $apply =  $this->api->stop($this->config["vpsid"]);
            if(isset($apply["error"]) && $apply["error"]){
                $this->error = is_array($apply["error"]) ? implode(" , ",$apply["error"]) : $apply["error"];
                return false;
            }
            if($this->api->error){
                $this->error = is_array($this->api->error) ? implode(", ",$this->api->error) : $this->api->error;
                return false;
            }
            if(isset($apply['done']) && !$apply['done'] && isset($apply['done_msg'])){
                $this->error = $apply['done_msg'];
                return false;
            }

            echo Utility::jencode([
                'status' => "successful",
                'message' => $this->lang["successful"],
                'timeRedirect' => [
                    'url' => $this->area_link,
                    'duration' => 1000
                ],
            ]);

            return true;
        }
        public function restart(){
            if(!isset($this->config["vpsid"])) return true;
            $apply =  $this->api->restart($this->config["vpsid"]);
            if(isset($apply["error"]) && $apply["error"]){
                $this->error = is_array($apply["error"]) ? implode(" , ",$apply["error"]) : $apply["error"];
                return false;
            }
            if($this->api->error){
                $this->error = is_array($this->api->error) ? implode(", ",$this->api->error) : $this->api->error;
                return false;
            }
            if(isset($apply['done']) && !$apply['done'] && isset($apply['done_msg'])){
                $this->error = $apply['done_msg'];
                return false;
            }

            echo Utility::jencode([
                'status' => "successful",
                'message' => $this->lang["successful"],
                'timeRedirect' => [
                    'url' => $this->area_link,
                    'duration' => 1000
                ],
            ]);

            return true;
        }
        public function rebuild($os=0){
            if(!isset($this->config["vpsid"])) return true;
            $password = Utility::generate_hash(10,false,'lud');
            if(isset($this->order["options"]["login"]["password"]))
                $password = $this->order["options"]["login"]["password"];

            $apply =  $this->api->rebuild([
                'vpsid'         => $this->config["vpsid"],
                'osid'          => $os,
                'newpass'       => $password,
                'conf'          => $password,
            ]);
            if(isset($apply["error"]) && $apply["error"]){
                $this->error = is_array($apply["error"]) ? implode(" , ",$apply["error"]) : $apply["error"];
                return false;
            }
            if($this->api->error){
                $this->error = is_array($this->api->error) ? implode(", ",$this->api->error) : $this->api->error;
                return false;
            }
            if(isset($apply['done']) && !$apply['done'] && isset($apply['done_msg'])){
                $this->error = $apply['done_msg'];
                return false;
            }
            return true;
        }
        public function get_status($id=0){
            if(!$id && isset($this->config[$this->entity_id_name]))
                $id = $this->config[$this->entity_id_name];
            $info       = $this->get_info($id);
            if(!$info) return false;
            return $info["status"];
        }
        public function list_vps(){
            $getData    = $this->api->vs(1,5000);

            if(isset($getData["error"]) && $getData["error"]){
                $this->error = is_array($getData["error"]) ? implode(" , ",$getData["error"]) : $getData["error"];
                return false;
            }

            if($this->api->error){
                $this->error = is_array($this->api->error) ? implode(", ",$this->api->error) : $this->api->error;
                return false;
            }

            $result     = [];

            if(isset($getData["vs"]) && $getData["vs"]){
                $getData        = $getData["vs"];

                $types          = $this->config["types"];
                $types_reverse  = array_flip($types);

                foreach($getData as $item){
                    $ip             = current($item["ips"]);
                    $hostname       = str_replace("www.","",$item["hostname"]);
                    $access_data    = [
                        'config' => ["vpsid" => $item["vpsid"]],
                    ];
                    $c_info                         = [];
                    $c_info['slave_server']         = $item['serid'];
                    $c_info['plan']                 = $item['plid'];
                    $c_info['osid']                 = $item['osid'];
                    $c_info['disk_space']           = $item['space'];
                    $c_info['ram']                  = $item['ram'];
                    $c_info['burst_ram']            = $item['burst'];
                    $c_info['swap_ram']             = $item['swap'];
                    $c_info['bandwidth']            = $item['bandwidth'];
                    $c_info['network_speed']        = $item['network_speed'];
                    $c_info['upload_speed']         = $item['upload_speed'];
                    $c_info['cpu_units']            = $item['cpu'];
                    $c_info['cpu_cores']            = $item['cores'];
                    $c_info['cpu_percent']          = $item['cpu_percent'];
                    $c_info['priority']             = 0;
                    $c_info['ips']                  = sizeof($item["ips"]);
                    $c_info['ips6_subnet']          = 0;
                    $c_info['ips6']                 = 0;
                    $c_info['ips_int']              = 0;
                    $c_info['virtio']               = $item['virtio'];
                    $c_info['vnc']                  = $item['vnc'];
                    $c_info['vnc']                  = $item['vnc'];
                    $c_info['mgs']                  = $item['mg'] ? [$item['mg']] : '';
                    $c_info['band_suspend']         = $item['band_suspend'];
                    $c_info['dns']                  = unserialize($item['dns_nameserver']);
                    $c_info['dns_plan']             = 0;
                    $c_info['tuntap']               = $item['tuntap'];
                    $c_info['osreinstall_limit']    = $item['osreinstall_limit'];
                    $c_info['admin_managed']        = $item['admin_managed'];
                    $access_data['creation_info']   = $c_info;

                    $result[$hostname."|".$ip] = [
                        'hostname' => $hostname,
                        'ip'       => $ip,
                        'sync_terms' => [
                            [
                                'column'    => "options",
                                'mark'      => "LIKE",
                                'value'     => '%"ip":"'.$ip.'"%',
                                'logical'   => "&&",
                            ],
                            [
                                'column'    => "options",
                                'mark'      => "LIKE",
                                'value'     => '%"hostname":"'.$hostname.'"%',
                                'logical'   => "",
                            ],
                        ],
                        'access_data' => $access_data,
                    ];
                }
            }
            return $result;
        }

        public function clientArea()
        {
            $username   = 'root';
            $password   = $this->decode_str($this->order["options"]["login"]["password"]);

            if(isset($this->order["options"]["os_username"]))
                $username = $this->order["options"]["os_username"];

            $port           = $this->config["client-not-secure-port"] ?? 4082;
            if($this->server["secure"]) $port = $this->config["client-secure-port"] ?? 4083;
            $hostname       = Validation::NSCheck($this->server["name"]) ? $this->server["name"] : $this->server["ip"];
            $url            = 'http'.($this->server["secure"]  ? 's' : '').'://'.$hostname.":".$port."/";





            $content = $this->clientArea_buttons_output();

            $content .= '<div class="clear"></div><h4 style="margin-top:25px;">Operating System Information</h4>';
            $content .= '<div style="display:block;">';

            $content .= '<div style="display:block;">';
            $content .= '<span style="width:100px;">IP:</span> <strong>'.$this->order["options"]["ip"].'</strong>';
            $content .= '</div>';

            $content .= '<div style="display:block;">';
            $content .= '<span style="width:100px;">Username:</span> <strong>'.$username.'</strong>';
            $content .= '</div>';

            $content .= '<div style="display:block;">';
            $content .= '<span style="width:100px;">Password:</span> <strong>'.$password.'</strong>';
            $content .= '</div>';


            $content .= '</div>';

            $content .=  '<script type="text/javascript">';
            $content .= '$(document).ready(function(){';
            $content .= '$("#login_informations tbody tr:eq(1) td:eq(0)").html("<strong>Panel URL</strong>");';
            $content .= '$("#login_informations tbody tr:eq(1) td:eq(1)").html("'.$url.'");';
            $content .= '});';
            $content .=  '</script>';




            return  $content;
        }

        public function clientArea_buttons()
        {
            $buttons    = [];

            if($this->page && $this->page != "home")
            {
                $buttons['home'] = [
                    'text' => $this->lang["turn-back"],
                    'type' => 'page-loader',
                ];
            }
            else
            {
                $status     = $this->get_status();

                if($status)
                {
                    $buttons['restart']     = [
                        'text'  => $this->lang["restart"],
                        'icon'  => 'fa fa-refresh',
                        'attributes'=> [
                            'id' => "vpsrestart",
                            'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('restart',this);",
                        ],
                        'type'  => 'transaction',
                    ];
                    $buttons['stop']      = [
                        'text'  => $this->lang["stop"],
                        'icon'      => 'fa fa-pause-circle',
                        'attributes'=> [
                            'id' => "vpsstop",
                            'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('stop',this);",
                        ],
                        'type'  => 'transaction',
                    ];
                    $buttons['shutdown']      = [
                        'attributes'=> [
                            'id' => "vpskill",
                            'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('shutdown',this);",
                        ],
                        'icon'  => 'fa fa-ban',
                        'text'  => 'Shutdown',
                        'type'  => 'transaction',
                    ];
                }
                else
                {
                    $buttons['start']      = [
                        'text'  => $this->lang["start"],
                        'icon'  => 'fa fa-play-circle',
                        'attributes' => [
                            'id' => 'vpsstart',
                            'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('start',this);",
                        ],
                        'type'  => 'transaction',
                    ];
                }
            }

            return $buttons;
        }

        public function adminArea_buttons()
        {
            $buttons = [];

            $status     = $this->get_status();

            if($status)
            {
                $buttons['restart']     = [
                    'text'  => $this->lang["restart"],
                    'type'  => 'transaction',
                    'icon'  => 'fa fa-refresh',
                    'attributes'=> [
                        'id' => "vpsrestart",
                        'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('restart',this);",
                    ],
                ];
                $buttons['stop']      = [
                    'text'  => $this->lang["stop"],
                    'icon'      => 'fa fa-pause-circle',
                    'attributes'=> [
                        'id' => "vpsstop",
                        'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('stop',this);",
                    ],
                    'type'  => 'transaction',
                ];
                $buttons['shutdown']      = [
                    'attributes'=> [
                        'id' => "vpskill",
                        'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('shutdown',this);",
                    ],
                    'icon'  => 'fa fa-ban',
                    'text'  => 'Shutdown',
                    'type'  => 'transaction',
                ];
            }
            else
            {
                $buttons['start']      = [
                    'text'  => $this->lang["start"],
                    'icon'  => 'fa fa-play-circle',
                    'attributes' => [
                        'id' => 'vpsstart',
                        'onclick' => "if(confirm('".addslashes(___("needs/apply-are-you-sure"))."')) run_transaction('start',this);",
                    ],
                    'type'  => 'transaction',
                ];
            }

            return $buttons;
        }

        public function use_clientArea_SingleSignOn()
        {
            $port           = $this->config["client-not-secure-port"] ?? 4082;
            if($this->server["secure"]) $port = $this->config["client-secure-port"] ?? 4083;


            $hostname       = Validation::NSCheck($this->server["name"]) ? $this->server["name"] : $this->server["ip"];
            $url            = 'http'.($this->server["secure"]  ? 's' : '').'://'.$hostname.":".$port."/";

            Utility::redirect($url);

            echo "Redirecting...";
        }
        public function use_adminArea_SingleSignOn()
        {
            $port           = $this->config["client-not-secure-port"] ?? 4082;
            if($this->server["secure"]) $port = $this->config["client-secure-port"] ?? 4083;



            $hostname       = Validation::NSCheck($this->server["name"]) ? $this->server["name"] : $this->server["ip"];
            $url            = 'http'.($this->server["secure"]  ? 's' : '').'://'.$hostname.":".$port."/";

            Utility::redirect($url);

            echo "Redirecting...";
        }


        public function use_clientArea_start()
        {
            if($this->start()){
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
            if($this->stop())
            {
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
            if($this->restart())
            {
                $u_data     = UserManager::LoginData('member');
                $user_id    = $u_data['id'];
                User::addAction($user_id,'transaction','The command "restart" has been sent for service #'.$this->order["id"].' on the module.');
                Orders::add_history($user_id,$this->order["id"],'server-order-restart');
                return true;
            }
            return false;
        }
        public function use_clientArea_shutdown()
        {
            if($this->shutdown()){
                $u_data     = UserManager::LoginData();
                $user_id    = $u_data['id'];
                User::addAction($user_id,'transaction','The command "shutdown" has been sent for service #'.$this->order["id"].' on the module.');
                Orders::add_history($user_id,$this->order["id"],'server-order-shutdown');
                return true;
            }
            return false;
        }


        public function use_adminArea_start()
        {
            $this->area_link .= '?content=automation';
            if($this->start()){
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
            if($this->stop()){
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
            if($this->restart()){
                $u_data     = UserManager::LoginData('admin');
                $user_id    = $u_data['id'];
                User::addAction($user_id,'transaction','The command "restart" has been sent for service #'.$this->order["id"].' on the module.');
                Orders::add_history($user_id,$this->order["id"],'server-order-restart');
                return true;
            }
            return false;
        }
        public function use_adminArea_shutdown()
        {
            $this->area_link .= '?content=automation';
            if($this->shutdown()){
                $u_data     = UserManager::LoginData('admin');
                $user_id    = $u_data['id'];
                User::addAction($user_id,'transaction','The command "shutdown" has been sent for service #'.$this->order["id"].' on the module.');
                Orders::add_history($user_id,$this->order["id"],'server-order-shutdown');
                return true;
            }
            return false;
        }


    }