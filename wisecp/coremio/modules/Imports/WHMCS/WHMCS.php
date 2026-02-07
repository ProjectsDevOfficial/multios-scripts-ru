<?php
    namespace WISECP\Modules\Imports;
    class WHMCS
    {
        public array $lang; // To be assigned by the system.
        public string $area_link; // To be assigned by the system.
        public string $name; // // To be assigned by the system.
        public object $controller; // // To be assigned by the system.

        // Variables that can be used
        public bool $test = false;
        public string $cc_encryption_hash,$file_hash;
        private \Database $db;
        private array $module_list;
        private array $cx;
        private string|int $local_lang,$local_curr,$local_country_id;
        private array $lang_list;

        function __construct()
        {
            @set_time_limit(0);
        }

        // Required function
        public function area():void
        {
            $page       = \Filter::route($_GET["page"] ?? "index");

            echo \Modules::getPage("Imports",$this->name,$page,[
                'module' => $this,
            ]);
        }

        public function exists_module($module_type='',$module_name='')
        {
            $module_name = strtolower($module_name);

            if(isset($this->module_list[$module_type]))
                $data = $this->module_list[$module_type];
            else
            {
                $list   = \Modules::Load($module_type,'All',true);

                if($list)
                {
                    $keys = array_keys($list);
                    foreach($keys AS $k) $this->module_list[$module_type][strtolower($k)] = $k;
                }
                $data   = $this->module_list[$module_type] ?? [];
            }

            return $data[$module_name] ?? false;
        }
        public function whmcs_decrypt($string)
        {
            $cc_encryption_hash = $this->cc_encryption_hash;

            $key = md5(md5($cc_encryption_hash)) . md5($cc_encryption_hash);
            $hash_key = $this->whmcs_hash($key);
            $hash_length = strlen($hash_key);
            $string = base64_decode($string);
            $tmp_iv = substr($string, 0, $hash_length);
            $string = substr($string, $hash_length, strlen($string) - $hash_length);
            $iv = '';
            $out = '';
            $c = 0;

            while ($c < $hash_length) {
                $iv .= chr(ord($tmp_iv[$c]) ^ ord($hash_key[$c]));
                ++$c;
            }

            $key = $iv;
            $c = 0;

            while ($c < strlen($string)) {
                if (($c != 0) && (($c % $hash_length) == 0)) {
                    $key = $this->whmcs_hash($key . substr($out, $c - $hash_length, $hash_length));
                }


                $out .= chr(ord($key[$c % $hash_length]) ^ ord($string[$c]));
                ++$c;
            }

            return $out;
        }
        public function whmcs_hash($string)
        {
            if (function_exists('sha1')) {
                $hash = sha1($string);
            }
            else {
                $hash = md5($string);
            }

            $out = '';
            $c = 0;

            while ($c < strlen($hash)) {
                $out .= chr(hexdec($hash[$c] . $hash[$c + 1]));
                $c += 2;
            }

            return $out;
        }
        private function find_module_name($type,$name):string
        {
            $list = [
                'Servers' => [
                    'cpanel'        => "cPanel",
                    'plesk'         => "Plesk",
                    'directadmin'   => "directadmin",
                    'cwp7'          => "CentOSWebPanel",
                    'cyberpanel'    => "CyberPanel",
                    'vesta'         => "VestaCP",
                    'maestropanel'  => "MaestroPanel",
                    'vmpanel'       => "CyberVM",
                    'solusvm2vps'   => "SolusVM2",
                    'solusvmpro'    => "SolusVM",
                    'solusvm'       => "SolusVM",
                    'autovm'        => "AutoVM",
                    'virtualizor'   => "Virtualizor",
                    'virtualizor_cloud' => "Virtualizor",
                ],
                'Product' => [
                    'SSLCENTERWHMCS'    => "GogetSSL",
                    'namecheapssl'      => "NamecheapSSL",
                    'resellerclubssl'   => "ResellerClubSSL",
                    'onlinenic_ssl'     => "OnlineNICSSL",
                ],
                'Payment' => [
                    'banktransfer'      => "BankTransfer",
                    'mailin'            => "BankTransfer",
                    'stripe'            => "Stripe",
                    'tco'               => "TCO",
                    'paypal'            => "PayPal",
                    'paypalcheckout'    => "PayPal",
                    'PayTR_BurtiNET'    => "PayTR",
                    'iyzico_BurtiNET'   => "Iyzico",
                    'ravepay'           => "RavePay",
                    'perfectmoney'      => "PerfectMoney",
                    'shopier'           => "Shopier",
                    'skrill'            => "Skrill",
                    'coinpayments'      => "CoinPayments",
                    'paywant'           => "CoinPayments",
                ],
                'Registrars'  => [
                    'domainnameapi' => "DomainNameAPI",
                    'resellerclub'  => "ResellerClub",
                    'onlinenic'     => "OnlineNIC",
                ],
            ];

            if($type == "special") $type = "Product";
            if($type == "hosting" || $type == "server") $type = "Servers";
            if($type == "domain") $type = "Registrars";


            if(isset($list[$type][$name])) $name = $list[$type][$name];
            elseif($m_name = $this->exists_module($type,$name)) $name = $m_name;

            return $name;
        }

        private function set_db_credentials():void
        {
            $db_host        = \Filter::init("POST/db_host","hclear");
            $db_username    = \Filter::init("POST/db_username","hclear");
            $db_password    = \Filter::init("POST/db_password","password");
            $db_name        = \Filter::init("POST/db_name","route");
            $db_charset     = \Filter::init("POST/db_charset","hclear");
            $encryption_key = \Filter::init("POST/encryption_key","hclear");


            if(!$db_charset) $db_charset = "utf8";

            if(\Validation::isEmpty($db_host)) $db_host = "localhost";
            if(\Validation::isEmpty($db_username))
                die(\Utility::jencode([
                    'status' => "error",
                    'for' => "input[name='db_username']",
                    'message' => __("admin/tools/error6"),
                ]));

            if(\Validation::isEmpty($db_password))
                die(\Utility::jencode([
                    'status' => "error",
                    'for' => "input[name='db_password']",
                    'message' => __("admin/tools/error7"),
                ]));

            if(\Validation::isEmpty($db_name))
                die(\Utility::jencode([
                    'status' => "error",
                    'for' => "input[name='db_name']",
                    'message' => __("admin/tools/error8"),
                ]));

            if(\Validation::isEmpty($encryption_key))
                die(\Utility::jencode([
                    'status' => "error",
                    'for' => "input[name='encryption_key']",
                    'message' => __("admin/tools/error16"),
                ]));

            $this->cc_encryption_hash   = $encryption_key;

            \MioException::$error_hide=true;
            try{
                $this->db     = new \Database("mysql",$db_host,3306,$db_username,$db_password,false,$db_name,$db_charset);
                $this->db->query("SET GLOBAL innodb_buffer_pool_size = 4G;SET GLOBAL max_allowed_packet = 128M;");
            }catch(\DatabaseException $e){
                throw new \Exception(__("admin/tools/error9",['{message}' => $e->getMessage()]));
            }
            \MioException::$error_hide=false;
            
            $this->file_hash = md5($db_host."+++".$db_username."+++".$db_password."+++".$db_name);
            
        }

        // Pulling data from a remote database
        public function call_pull_data()
        {
            if(DEMO_MODE)
            {
                echo \Utility::jencode([
                    'status' => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]);
                return true;
            }

            try {
                $this->set_db_credentials();
            }
            catch (\Exception $e)
            {
                echo \Utility::jencode([
                    'status' => "error",
                    'message' => $e->getMessage(),
                ]);
                return true;
            }

            \Helper::Load(["Money","Products","Orders","Invoices","Tickets"]);


            $part           = (int) \Filter::init("POST/part","numbers");
            
            $this->local_lang           = \Config::get("general/local");
            $this->local_curr           = \Config::get("general/currency");
            $this->local_country_id     = \AddressManager::LocalCountryID();
            $this->lang_list            = \Bootstrap::$lang->rank_list("all");

            $result_file        = ROOT_DIR."temp".DS.$this->file_hash."-data.php";
            $collation_file     = ROOT_DIR."temp".DS.$this->file_hash."-result-collation.php";

            if(file_exists($result_file) && $part > 1)
                $result     = include $result_file;
            else
                $result     = [
                    'categories'    => [
                        'products' => [
                            "special-group" => [
                                'data'          => [
                                    'type'      => "products",
                                    'kind'      => "special",
                                    'rank'      => 1,
                                    'ctime'     => \DateManager::Now(),
                                    'options'   => '',
                                ],
                                'lang' => [
                                ],
                            ],
                        ],
                    ],
                    'others'        => [
                        'shared_servers' => [],
                        'events' => [],
                        'user_groups' => [],
                        'credit_logs' => [],
                        'affiliates'  => [],
                        'announcements' => [],
                    ],
                    'products'      => [],
                    'product_addons' => [],
                    'product_requirements' => [],
                    'users'         => [],
                    'orders'        => [],
                    'order_requirements' => [],
                    'invoices'      => [],
                    'tickets'       => [
                        'requests'  => [],
                        'departments' => [],
                    ],
                    'count'         => [
                        'product_categories' => 0,
                        'products' => 0,
                        'users' => 0,
                        'tickets' => 0,
                        'orders' => 0,
                        'invoices' => 0,
                    ],
                ];

            if(file_exists($collation_file) && $part > 1)
                $collation = include $collation_file;
            else
                $collation          = [];


            if($part == 0) {}
            elseif($part == 1)
            {
                foreach($this->lang_list AS $l)
                {
                    $lk = $l["key"];
                    $result['categories']['products']['special-group']['lang'][$lk] = [
                        'title' => __("admin/tools/import-product-group-other",false,$lk),
                        'sub_title' => '',
                        'content'   => '',
                        'route'     => \Filter::permalink(__("admin/tools/import-product-group-other",false,$lk)),
                        'seo_title' => '',
                        'seo_keywords' => '',
                        'seo_description' => '',
                    ];
                }

                $this->pull_data_part1($result,$collation);
            }
            elseif ($part == 2)
                $this->pull_data_part2($result,$collation);
            elseif ($part == 3)
                $this->pull_data_part3($result,$collation);
            elseif ($part == 4)
                $this->pull_data_part4($result,$collation);
            elseif ($part == 5)
                $this->pull_data_part5($result,$collation);
            else
                $part = "done";

            \FileManager::file_write($result_file,\Utility::array_export($result,['pwith' => true]));

            if($part == "done")
            {
                \FileManager::file_delete($collation_file);

                echo \Utility::jencode([
                    'status' => "successful",
                    'count' => $result["count"],
                ]);

                return false;
            }

            $part = $part+1;

            \FileManager::file_write($collation_file,\Utility::array_export($collation,['pwith' => true]));

            echo \Utility::jencode([
                'status' => "next-part",
                'part' => $part,
            ]);
            return true;
        }
        private function pull_data_part1(&$result,&$collation):void
        {

            //  Fetch Currencies
            $currencies     = $this->db->select()->from("tblcurrencies");
            $currencies     = $currencies->build() ? $currencies->fetch_assoc() : [];
            if($currencies){
                foreach($currencies AS $row){
                    if($row["default"]==1){
                        $collation["local_currency"] = [
                            'id'    => $row["id"],
                            'code' => $row["code"],
                        ];
                    }
                    $collation["currencies"][$row["id"]] = $row["code"];
                }
            }

            //  Fetch TLDS
            $tlds           = \WDB::select()->from("tldlist");
            $tlds           = $tlds->build() ? $tlds->fetch_assoc() : [];
            if($tlds) foreach($tlds AS $row) $collation["tlds"][$row["name"]] = $row["id"];

            //  Fetch Configuration
            $get_ns1        = $this->db->select()->from("tblconfiguration")->where("setting","=","DefaultNameserver1");
            $get_ns1        = $get_ns1->build() ? $get_ns1->getObject()->value : '';
            $get_ns2        = $this->db->select()->from("tblconfiguration")->where("setting","=","DefaultNameserver2");
            $get_ns2        = $get_ns2->build() ? $get_ns2->getObject()->value : '';
            $get_ns3        = $this->db->select()->from("tblconfiguration")->where("setting","=","DefaultNameserver3");
            $get_ns3        = $get_ns3->build() ? $get_ns3->getObject()->value : '';
            $get_ns4        = $this->db->select()->from("tblconfiguration")->where("setting","=","DefaultNameserver4");
            $get_ns4        = $get_ns4->build() ? $get_ns4->getObject()->value : '';

            $collation["ns1"] = $get_ns1;
            $collation["ns2"] = $get_ns2;
            $collation["ns3"] = $get_ns3;
            $collation["ns4"] = $get_ns4;

            //  Fetch Categories
            $product_groups     = $this->db->select()->from("tblproductgroups")->order_by("id ASC");
            $product_groups     = $product_groups->build() ? $product_groups->fetch_assoc() : [];
            if($product_groups){
                foreach($product_groups AS $row){

                    $id         = $row["id"];
                    $type       = "products";
                    $kind       = NULL;

                    $title              = \Filter::html_clear($row["name"]);
                    $sub_title          = \Filter::html_clear($row["headline"]);
                    $content            = '';
                    $route              = \Filter::permalink($title);

                    if(isset($collation["categories_routes"]))
                        if(in_array($route,$collation["categories_routes"])) $route .= "-".$id;


                    $rank               = \Filter::rnumbers($row["order"]);
                    $seo_title          = $title;
                    $seo_keywords       = '';
                    $seo_description    = '';
                    $ctime              = \Utility::substr($row["created_at"],0,4) == "0000" ? \DateManager::Now() : $row["created_at"];
                    $options            = [];

                    $options            = \Utility::jencode($options);

                    $item               = [
                        'data'          => [
                            'type'      => $type,
                            'kind'      => $kind,
                            'rank'      => $rank,
                            'ctime'     => $ctime,
                            'options'   => $options,
                        ],
                        'lang' => [],
                    ];

                    foreach($this->lang_list AS $l)
                    {
                        $lk = $l["key"];
                        $item['lang'][$lk] = [
                            'title' => $title,
                            'sub_title' => $sub_title,
                            'content'   => $content,
                            'route'     => $route,
                            'seo_title' => $seo_title,
                            'seo_keywords' => $seo_keywords,
                            'seo_description' => $seo_description,
                        ];
                    }

                    $collation["categories_routes"][] = $route;

                    $result["count"]["product_categories"] +=1;
                    $result["categories"]["products"][$id] = $item;
                }
            }

            //  Fetch Shared Servers
            $shared_servers     = $this->db->select()->from("tblservers");
            $shared_servers     = $shared_servers->build() ? $shared_servers->fetch_assoc() : [];
            if($shared_servers){
                foreach($shared_servers AS $row){
                    $port   = $row["port"];
                    $type   = $row["type"];
                    $type_f = $this->find_module_name("Servers",$type);

                    if($type != $type_f){
                        $status = "active";

                        if($row["active"] == 1 && $row["disabled"] == 1) $status = "inactive";
                        elseif($row["disabled"] == 1) $status = "inactive";

                        $result["others"]["shared_servers"][$row["id"]] = [
                            'name'          => $row["name"],
                            'ip'            => $row["ipaddress"],
                            'ns1'           => $row["nameserver1"],
                            'ns2'           => $row["nameserver2"],
                            'ns3'           => $row["nameserver3"],
                            'ns4'           => $row["nameserver4"],
                            'maxaccounts'   => $row["maxaccounts"],
                            'type'          => $type,
                            'username'      => $row["username"],
                            'password'      => $this->whmcs_decrypt($row["password"]),
                            'secure'        => $row["secure"] ? 1 : 0,
                            'port'          => $port ?: '0',
                            'status'        => $status,
                        ];
                    }
                }
            }


            //  Fetch Shared Server Groups
            $server_groups     = $this->db->select()->from("tblservergroups");
            $server_groups     = $server_groups->build() ? $server_groups->fetch_assoc() : [];
            if($server_groups){
                foreach($server_groups AS $row){
                    $server_ids         = [];
                    $m_type             = '';

                    $get_server_ids     = $this->db->select()->from("tblservergroupsrel");
                    $get_server_ids->where("groupid","=",$row["id"]);
                    $get_server_ids     = $get_server_ids->build() ? $get_server_ids->fetch_assoc() : [];

                    if($get_server_ids)
                    {
                        foreach($get_server_ids AS $srow)
                        {
                            $server_ids[] = $srow["serverid"];
                            if(!$m_type)
                                $m_type = $result["others"]["shared_servers"][$srow["serverid"]]["type"];
                        }
                    }

                    $collation["server_groups"][$row["id"]] = [
                        'type'      => $m_type,
                        'name'      => $row["name"],
                        'fill_type' => $row["filltype"],
                        'servers'   => $server_ids,
                    ];

                    $result["others"]["shared_server_groups"][$row["id"]] = $collation["server_groups"][$row["id"]];
                }
            }

            // Fetch Configuration Options
            $config_options = $this->db->select()->from("tblproductconfigoptions");
            $config_options->order_by("'order' ASC");
            $config_options = $config_options->build() ? $config_options->fetch_assoc() : [];
            if($config_options){
                foreach($config_options AS $k=>$v){
                    $collation["config_options"][$v["id"]] = $v;
                }
            }

            // Fetch Configuration Options Sub
            $config_options_sub = $this->db->select()->from("tblproductconfigoptionssub");
            $config_options_sub->order_by("sortorder ASC");
            $config_options_sub = $config_options_sub->build() ? $config_options_sub->fetch_assoc() : [];
            if($config_options_sub){
                foreach($config_options_sub AS $v){
                    $collation["config_options_sub"][$v["id"]] = $v;
                }
            }

            $config_option_prices                     = $this->db->select()->from("tblpricing");
            $config_option_prices->where("type","=","configoptions","&&");
            $config_option_prices->where("currency","=",$collation["local_currency"]["id"]);
            $config_option_prices = $config_option_prices->build() ? $config_option_prices->fetch_assoc() : false;
            if($config_option_prices){
                foreach($config_option_prices AS $k=>$v){
                    $collation["config_options_sub_price"][$v["relid"]] = $v;
                }
            }


            // Fetch Products
            $products   = $this->db->select()->from("tblproducts");
            $products->order_by("id ASC");
            $products   = $products->build() ? $products->fetch_assoc() : [];
            if($products){
                foreach($products AS $row){
                    $id                        = $row["id"];
                    $type                      = "special";
                    $type_id                   = 0;
                    $title                     = $row["name"];

                    if($row["type"] == "hostingaccount") $type = "hosting";
                    elseif($row["type"] == "reselleraccount") $type = "hosting";
                    elseif($row["type"] == "server") $type = "server";
                    else $type_id = "special-group";

                    $category                  = $row["gid"];
                    $visibility                = $row["hidden"]==0 ? "visible" : "invisible";
                    $ctime                     = \Utility::substr($row["created_at"],0,4) == "0000" ? \DateManager::Now() : $row["created_at"];
                    $rank                      = \Filter::rnumbers($row["order"]);
                    $options                   = [];

                    $module                     = $row["servertype"];
                    $module                     = $this->find_module_name($type,$module);


                    if($type == 'special'){
                        if(stristr(\Utility::strtolower($title),"hosting")) $type = 'hosting';
                        elseif(in_array($module,[
                            'cPanel',
                            'Plesk',
                            'DirectAdmin',
                            'CentOSWebPanel',
                            'CyberPanel',
                            'VestaCP',
                            'MaestroPanel',
                            'enhance',
                            'HestiaCP',
                            'HestiaCP',
                            'ISPmanager',
                            'KeyHelp',
                            'SonicPanel',
                            'Webuzo',
                            'ApisCP',
                        ]))
                            $type = "hosting";
                        elseif(stristr(\Utility::strtolower($title),"dedicated") || stristr(\Utility::strtolower($title),"vps"))
                            $type = 'server';
                        elseif(in_array($module,[
                            'HetznerCloud',
                            'CyberVM',
                            'SolusVM',
                            'SolusVM2',
                            'AutoVM',
                            'Virtualizor',
                            'Pterodactyl',
                        ]))
                            $type = "server";
                        if($type != 'special') $type_id = 0;
                    }

                    $options["panel_type"]          = $type == "hosting" && $module != 'none' ? $module : "";

                    $options["popular"]             = $row["is_featured"] ? true : false;

                    if($type == "special") $options["show_domain"] = $row["showdomainoptions"];
                    elseif($type == "hosting" && !$row["showdomainoptions"])
                        $options["hide_domain"] = 1;


                    if($type == "hosting"){
                        $options["disk_limit"]          = "unlimited";
                        $options["bandwidth_limit"]     = "unlimited";
                        $options["email_limit"]         = "unlimited";
                        $options["database_limit"]      = "unlimited";
                        $options["addons_limit"]        = "unlimited";
                        $options["subdomain_limit"]     = "unlimited";
                        $options["ftp_limit"]           = "unlimited";
                        $options["park_limit"]          = "unlimited";
                        $options["max_email_per_hour"]  = "unlimited";

                        $options["cpu_limit"]           = NULL;
                        $options["server_features"]     = [];
                        $options["dns"]                 = [];
                    }

                    $module_data                    = [];

                    $server_id                      = 0;
                    $server_group_id                = 0;


                    if($row["servergroup"] && isset($collation["server_groups"][$row["servergroup"]]))
                    {
                        $server_id      = current($collation["server_groups"][$row["servergroup"]]['servers']);
                        $server_group_id = $row["servergroup"];

                    }


                    if($type == "hosting")
                    {
                        $module_data["server_id"] = $server_id;
                        $module_data["server_group_id"] = $server_group_id;
                    }
                    elseif($type == "server")
                    {
                        $options["server_id"] = $server_id;
                        $options["server_group_id"] = $server_group_id;
                    }

                    if($module == "cPanel"){
                        if(\Filter::rnumbers($row["configoption3"])>0)
                            $options["disk_limit"] = \Filter::rnumbers($row["configoption3"]);
                        if(\Filter::rnumbers($row["configoption5"])>0)
                            $options["bandwidth_limit"] = \Filter::rnumbers($row["configoption5"]);
                        if(\Filter::rnumbers($row["configoption4"])>0)
                            $options["email_limit"] = \Filter::rnumbers($row["configoption4"]);
                        if(\Filter::rnumbers($row["configoption8"])>0)
                            $options["database_limit"] = \Filter::rnumbers($row["configoption8"]);
                        if(\Filter::numbers($row["configoption14"]) || $row["configoption12"] == "0")
                            $options["addons_limit"] = \Filter::rnumbers($row["configoption14"]);
                        if(\Filter::rnumbers($row["configoption10"])>0)
                            $options["subdomain_limit"] = \Filter::rnumbers($row["configoption10"]);
                        if(\Filter::rnumbers($row["configoption2"])>0)
                            $options["ftp_limit"] = \Filter::rnumbers($row["configoption2"]);
                        if(\Filter::numbers($row["configoption12"]) || $row["configoption12"] == "0")
                            $options["park_limit"] = \Filter::rnumbers($row["configoption12"]);
                        $module_data["plan"] = $row["configoption1"];
                        $account_limit  = $row["configoption15"];
                        $enableaclmt    = $row["configoption16"];
                        $reseller_ds    = $row["configoption17"];
                        $reseller_bw    = $row["configoption18"];
                        $acl_list       = $row["configoption21"];
                        $reseller       = $row["configoption24"];
                        if($reseller){
                            $module_data["reseller"]                  = 1;
                            $module_data["account_limit"]             = $account_limit;
                            $module_data["enable_resource_limits"]    = $enableaclmt;
                            $module_data["disk_limit"]                = $reseller_ds;
                            $module_data["bandwidth_limit"]           = $reseller_bw;
                            $module_data["acllist"]                   = $acl_list;
                        }
                    }
                    elseif($module == "Plesk"){
                        $module_data["plan"] = $row["configoption1"];
                        if($row["configoption2"]){
                            $module_data["reseller"] = 1;
                            $module_data["reseller_plan"] = $row["configoption2"];
                        }
                    }
                    elseif($module == "DirectAdmin"){
                        $plan = $row["configoption1"] == 'Custom' ? '' : $row["configoption1"];
                        if($row["type"] == "reselleraccount"){
                            $module_data["reseller"]      = 1;
                            $module_data["plan"]          = '';
                            $module_data["reseller_plan"] = $plan;
                            $module_data["reseller_ip"]   = $row["configoption2"];
                        }else{
                            $module_data["plan"]          = $plan;
                        }
                        $module_data["suspend_at_limit"]   = $row["configoption4"] ? 1 : 0;
                    }
                    elseif($module == "CentOSWebPanel"){
                        $module_data["plan"]          = $row["configoption1"];
                        $module_data["inode"]         = $row["configoption2"];
                        $module_data["limit_nofile"]  = $row["configoption3"];
                        $module_data["limit_nproc"]   = $row["configoption4"];
                    }
                    elseif($module == "CyberPanel"){
                        $module_data["plan"] = $row["configoption1"];
                    }
                    elseif($module == "VestaCP"){
                        $module_data["plan"] = $row["configoption1"];
                        $module_data["ssh_access"] = $row["configoption2"] ? 1 : 0;
                        $module_data["ip_address"] = $row["configoption3"];
                    }
                    elseif($module == "MaestroPanel"){
                        $module_data["plan"] = $row["configoption2"];
                        if($row["type"] == "reselleraccount") $module_data["reseller"] = 1;
                    }
                    elseif($module == "CyberVM"){
                        $module_data["server"]      = $row["configoption1"];
                        $module_data["ram"]         = $row["configoption2"];
                        $module_data["space"]       = $row["configoption3"];
                        $module_data["cpu"]         = $row["configoption4"];
                        $module_data["bandwidth"]   = $row["configoption5"];
                        $module_data["os"]          = $row["configoption6"];
                        $module_data["iso"]         = $row["configoption7"];
                        $module_data["vnc"]         = $row["configoption8"] ? 1 : 0;
                        $module_data["datastore"]   = $row["configoption9"];
                        $module_data["core"]        = $row["configoption10"];
                        $module_data["prefix"]      = $row["configoption13"];
                    }
                    elseif($module == "SolusVM"){
                        $module_data["virtualization_type"] = $row["configoption5"];
                        $module_data["node"]                = $row["configoption2"];
                        $module_data["plan"]                = $row["configoption4"];
                        $module_data["template"]            = $row["configoption6"];
                        $module_data["extra_ip"]            = $row["configoption8"];
                    }
                    elseif($module == "AutoVM"){
                        $module_data["server"]              = $row["configoption1"];
                        $module_data["datastore"]           = $row["configoption2"];
                        $module_data["plan"]                = $row["configoption3"];
                        $module_data["ram"]                 = $row["configoption4"];
                        $module_data["cpu"]                 = $row["configoption5"];
                        $module_data["core"]                = $row["configoption6"];
                        $module_data["hard"]                = $row["configoption7"];
                        $module_data["bandwidth"]           = $row["configoption8"];
                    }
                    elseif($module == "Virtualizor"){
                        $configopt1_parse = explode(" - ",$row["configoption1"]);
                        if(isset($configopt1_parse[1])){
                            $options["server_id"] = $configopt1_parse[0];

                            $plan_parse     = explode(" - ",$row["configoption3"]);
                            $server_parse   = explode(" - ",$row["configoption4"]);

                            if($row["configoption4"] == 'Auto Select Server')
                                $slave_server = 'auto';
                            elseif(stristr($server_parse[1],'[G]'))
                                $slave_server = 'G|'.$server_parse[0];
                            else
                                $slave_server = $server_parse[0];

                            $module_data["type"]            = $row["configoption2"];
                            $module_data["plan"]            = $plan_parse[0];
                            $module_data["slave_server"]    = $slave_server;
                        }
                    }
                    elseif($module == "GogetSSL"){
                        $module_data["product-id"] = $row["configoption1"];
                    }
                    elseif($module == "NamecheapSSL"){
                        $module_data["product-name"] = $row["configoption5"];
                    }
                    elseif($module == "ResellerClubSSL"){
                        $parse      = explode("|",$row["configoption3"]);
                        $module_data["plan-id"] = $parse[0];
                    }

                    $options["auto_install"] = $row["autosetup"];

                    if(!isset($module_data["reseller"]) && $row["type"] == "reselleraccount") $module_data["reseller"] = 1;

                    if($type == "hosting")
                        $options["reseller"] = $row["type"] == "reselleraccount" ? 1 : 0;


                    $stock                          = '';
                    if($row["stockcontrol"]) $stock = \Filter::rnumbers($row["qty"]);

                    $features                      = str_replace('<br />',"\n",$row["description"]);


                    $requirements                   = [];

                    $custom_fields                 = $this->db->select()->from("tblcustomfields");
                    $custom_fields->where("type","=","product","&&");
                    $custom_fields->where("relid","=",$row["id"],"&&");
                    $custom_fields->where("showorder","=","on");
                    $custom_fields->order_by("sortorder ASC");
                    $custom_fields                  = $custom_fields->build() ? $custom_fields->fetch_assoc() : [];
                    if($custom_fields){

                        if(!isset($result["categories"]["requirement"][$type])){
                            $requirement_group_name = "none";
                            if($type == "hosting") $requirement_group_name = "Hosting";
                            elseif($type == "server") $requirement_group_name = "Server";
                            elseif($type == "special") $requirement_group_name = "Other Product/Services";

                            $result["categories"]["requirement"][$type] = [
                                'data' => [
                                    'type' => "requirement",
                                    'ctime' => \DateManager::Now(),
                                ],
                                'lang' => [],
                            ];

                            foreach(\Bootstrap::$lang->rank_list("all") AS $l_row)
                                $result["categories"]["requirement"][$type]["lang"][$l_row["key"]] = [
                                    'title' => $requirement_group_name,
                                ];
                        }

                        foreach($custom_fields AS $c_row){
                            $similarity_hash = md5($c_row["fieldname"].$c_row["fieldtype"].$c_row["fieldoptions"]);

                            if(isset($collation["requirements_similarity"][$type][$similarity_hash])){
                                if(!in_array($collation["requirements_similarity"][$type][$similarity_hash],$requirements))
                                    $requirements[$c_row["id"]] = $collation["requirements_similarity"][$type][$similarity_hash];
                            }else{
                                if($type == "special") $mcategory = "special";
                                else $mcategory = $type;

                                $result["product_requirements"][$c_row["id"]] = [
                                    'data' => [
                                        'mcategory' => $mcategory,
                                        'category'  => $type,
                                        'rank'      => $c_row["sortorder"],
                                    ],
                                ];

                                $c_type             = '';
                                $c_properties       = [];
                                $c_options          = [];
                                $c_lid              = 0;

                                if($c_row["fieldtype"] == "text") $c_type = "input";
                                elseif($c_row["fieldtype"] == "link") $c_type = "input";
                                elseif($c_row["fieldtype"] == "password") $c_type = "input";
                                elseif($c_row["fieldtype"] == "dropdown") $c_type = "select";
                                elseif($c_row["fieldtype"] == "tickbox") $c_type = "checkbox";
                                elseif($c_row["fieldtype"] == "textarea") $c_type = "textarea";


                                if($c_row["required"] == "on") $c_properties["compulsory"] = 1;

                                if($c_row["fieldoptions"]){
                                    $c_get_options = explode(",",$c_row["fieldoptions"]);
                                    if($c_get_options){
                                        foreach($c_get_options AS $c_opt){
                                            $c_lid++;
                                            $c_options[] = [
                                                'id' => $c_lid,
                                                'name' => $c_opt,
                                            ];
                                        }
                                    }
                                }

                                foreach(\Bootstrap::$lang->rank_list("all") AS $l_row){
                                    $result["product_requirements"][$c_row["id"]]["lang"][$l_row["key"]] = [
                                        'name'          => $c_row["fieldname"],
                                        'description'   => $c_row["description"],
                                        'type'          => $c_type,
                                        'properties'    => $c_properties ? \Utility::jencode($c_properties) : '',
                                        'options'       => $c_options ? \Utility::jencode($c_options) : '',
                                        'lid'           => $c_lid,
                                    ];
                                }

                                $collation["requirements_similarity"][$type][$similarity_hash] = $c_row["id"];
                                if(!in_array($c_row["id"],$requirements)) $requirements[$c_row["id"]] = $c_row["id"];
                            }
                        }
                    }

                    $subdomains = '';
                    $g_subdomains =  $row["subdomain"];
                    if($g_subdomains)
                    {
                        $g_subdomains = explode(",",$g_subdomains);
                        if($g_subdomains)
                            foreach($g_subdomains AS $sd)
                                $subdomains .= ltrim($sd,'.')."\n";
                        $subdomains = trim($subdomains);
                    }

                    $affiliate_disable  = $row["affiliatepaytype"] == "none";
                    $affiliate_rate     = 0;

                    if($row["affiliatepaytype"] == "percentage")
                        $affiliate_rate = $row["affiliatepayamount"];

                    $item       = [
                        'data' => [
                            'type'          => $type,
                            'type_id'       => $type_id,
                            'category'      => $category,
                            'visibility'    => $visibility,
                            'ctime'         => $ctime,
                            'rank'          => $rank,
                            'stock'         => $stock,
                            'options'       => $options,
                            'module'        => $module ?: 'none',
                            'module_data'   => $module_data,
                            'requirements'  => $requirements ? $requirements : '',
                            'addons'        => '',
                            'subdomains'    => $subdomains,
                            'notes'         => "Transferred from ".$this->name,
                            'taxexempt'     => (int) $row["tax"],
                            'affiliate_disable' => (int) $affiliate_disable,
                            'affiliate_rate'    => $affiliate_rate,
                        ],
                        'lang' => [],
                        'prices' => [],
                    ];

                    foreach($this->lang_list AS $l)
                    {
                        $lk = $l["key"];
                        $item['lang'][$lk] = [
                            'title' => $title,
                            'features' => $features,
                        ];

                    }

                    $price                     = $this->db->select()->from("tblpricing");
                    $price->where("type","=","product","&&");
                    $price->where("relid","=",$id,"&&");
                    $price->where("currency","=",$collation["local_currency"]["id"]);
                    $price                     = $price->build() ? $price->getAssoc() : false;
                    if($price && isset($collation["currencies"][$price["currency"]])){
                        $currency               = \Money::Currency($collation["currencies"][$price["currency"]]);
                        $currency_id            = $currency["id"];

                        if($row["paytype"] == "onetime"){
                            if((float) $price["monthly"] > 0 || (float) $price["msetupfee"] > 0)
                                $item["prices"][] = [
                                    'owner' => "products",
                                    'type'  => "periodicals",
                                    'period' => "none",
                                    'time'   => 1,
                                    'setup' => (float) $price["msetupfee"],
                                    'amount' => (float) $price["monthly"],
                                    'cid'    => $currency_id,
                                ];
                        }
                        elseif($row["paytype"] == "recurring"){
                            if((float) $price["monthly"] > 0)
                                $item["prices"][] = [
                                    'owner' => "products",
                                    'type'  => "periodicals",
                                    'period' => "month",
                                    'time'   => 1,
                                    'setup' => (float) $price["msetupfee"],
                                    'amount' => (float) $price["monthly"],
                                    'cid'    => $currency_id,
                                ];

                            if((float) $price["quarterly"] > 0)
                                $item["prices"][] = [
                                    'owner' => "products",
                                    'type'  => "periodicals",
                                    'period' => "month",
                                    'time'   => 3,
                                    'setup' => (float) $price["qsetupfee"],
                                    'amount' => (float) $price["quarterly"],
                                    'cid'    => $currency_id,
                                ];

                            if((float) $price["semiannually"] > 0)
                                $item["prices"][] = [
                                    'owner' => "products",
                                    'type'  => "periodicals",
                                    'period' => "month",
                                    'time'   => 6,
                                    'setup' => (float) $price["ssetupfee"],
                                    'amount' => (float) $price["semiannually"],
                                    'cid'    => $currency_id,
                                ];

                            if((float) $price["annually"] > 0)
                                $item["prices"][] = [
                                    'owner' => "products",
                                    'type'  => "periodicals",
                                    'period' => "year",
                                    'time'   => 1,
                                    'setup' => (float) $price["asetupfee"],
                                    'amount' => (float) $price["annually"],
                                    'cid'    => $currency_id,
                                ];

                            if((float) $price["biennially"] > 0)
                                $item["prices"][] = [
                                    'owner' => "products",
                                    'type'  => "periodicals",
                                    'period' => "year",
                                    'time'   => 2,
                                    'setup' => (float) $price["bsetupfee"],
                                    'amount' => (float) $price["biennially"],
                                    'cid'    => $currency_id,
                                ];

                            if((float) $price["triennially"] > 0)
                                $item["prices"][] = [
                                    'owner' => "products",
                                    'type'  => "periodicals",
                                    'period' => "year",
                                    'time'   => 3,
                                    'setup' => (float) $price["tsetupfee"],
                                    'amount' => (float) $price["triennially"],
                                    'cid'    => $currency_id,
                                ];
                        }
                    }

                    if(!$item["prices"]){
                        $currency               = \Money::Currency($collation["local_currency"]["code"]);
                        $currency_id            = $currency["id"];
                        $item["prices"][] = [
                            'owner' => "products",
                            'type'  => "periodicals",
                            'period' => "none",
                            'time'   => 1,
                            'amount' => 0,
                            'cid'    => $currency_id,
                        ];
                    }

                    if($category && isset($result["categories"]["products"][$category]["data"])){
                        if(!$result["categories"]["products"][$category]["data"]["kind"]){
                            $result["categories"]["products"][$category]["data"]["kind"] = $type;
                            if($type == "special"){
                                $result["categories"]["products"][$category]["data"]["kind_id"] = "special-group";
                                $result["categories"]["products"][$category]["data"]["parent"] = "special-group";
                            }
                            if(isset($result["count"][$type."_categories"])) $result["count"][$type."_categories"] += 1;
                            else $result["count"][$type."_categories"] = 1;
                        }
                    }

                    $result["products"][$id] = $item;
                    $result["count"]["products"] +=1;
                }
            }


            // Fetch Products Addons
            $addons     = $this->db->select()->from("tbladdons");
            $addons     = $addons->build() ? $addons->fetch_assoc() : [];
            if($addons){
                foreach($addons AS $row){
                    if(!$row["packages"]) continue;

                    $mcategory      = '';
                    $category       = '';

                    $packages   = explode(",",$row["packages"]);
                    foreach($packages AS $p_k => $package){
                        if(!$mcategory && isset($result["products"][$package])){
                            $package_type = $result["products"][$package]["data"]["type"];
                            $mcategory   = $package_type;
                            $category    = $package_type;
                            if(!isset($result["categories"]["addon"][$mcategory])){
                                $result["categories"]["addon"][$mcategory] = [
                                    'data' => [
                                        'type'      => "addon",
                                        'ctime'     => \DateManager::Now(),
                                    ],
                                    'lang'          => [],
                                ];
                                $mcategory_name     = 'None';
                                if($package_type == "special") $mcategory_name = "Other Product/Service";
                                elseif($package_type == "hosting") $mcategory_name = "Hosting";
                                elseif($package_type == "server") $mcategory_name = "Server";
                                foreach(\Bootstrap::$lang->rank_list("all") AS $l_row){
                                    $result["categories"]["addon"][$mcategory]["lang"][$l_row["key"]] = [
                                        'title' => $mcategory_name,
                                    ];
                                }
                            }
                        }

                        if(!is_array($result["products"][$package]["data"]["addons"]))
                            $result["products"][$package]["data"]["addons"] = [];
                        $result["products"][$package]["data"]["addons"][] = $row["id"];
                    }

                    $result["product_addons"][$row["id"]] = [
                        'data' => [
                            'mcategory' => $mcategory,
                            'category'  => $category,
                        ],
                    ];

                    $ad_options         = [];
                    $lid                = 0;

                    $price                     = $this->db->select()->from("tblpricing");
                    $price->where("type","=","addon","&&");
                    $price->where("relid","=",$row["id"],"&&");
                    $price->where("currency","=",$collation["local_currency"]["id"]);
                    $price                     = $price->build() ? $price->getAssoc() : false;
                    if($price && isset($collation["currencies"][$price["currency"]])){
                        $currency               = \Money::Currency($collation["currencies"][$price["currency"]]);
                        $currency_id            = $currency["id"];

                        if($row["billingcycle"] == "onetime" || $row["billingcycle"] == "One Time"){
                            if((float) $price["monthly"] > 0 || (float) $price["msetupfee"] > 0)
                                $ad_options[] = [
                                    'id'            => $lid,
                                    'name'          => \Bootstrap::$lang->get("needs/iwant",$this->local_lang),
                                    'period' => "none",
                                    'period_time'   => 1,
                                    'amount' => ((float) $price["monthly"] + (float) $price["msetupfee"]),
                                    'cid'    => $currency_id,
                                ];
                        }else{
                            if((float) $price["monthly"] > 0 || (float) $price["msetupfee"] > 0){
                                $lid++;
                                $ad_options[] = [
                                    'id'            => $lid,
                                    'name'          => \Bootstrap::$lang->get("needs/iwant",$this->local_lang),
                                    'period'        => "month",
                                    'period_time'   => 1,
                                    'amount'        => ((float) $price["monthly"] + (float) $price["msetupfee"]),
                                    'cid'           => $currency_id,
                                ];
                            }

                            if((float) $price["quarterly"] > 0 || (float) $price["qsetupfee"] > 0){
                                $lid++;
                                $ad_options[] = [
                                    'id'            => $lid,
                                    'name'          => \Bootstrap::$lang->get("needs/iwant",$this->local_lang),
                                    'period' => "month",
                                    'period_time'   => 3,
                                    'amount' => ((float) $price["quarterly"] + (float) $price["qsetupfee"]),
                                    'cid'    => $currency_id,
                                ];
                            }

                            if((float) $price["semiannually"] > 0 || (float) $price["ssetupfee"] > 0){
                                $lid++;
                                $ad_options[] = [
                                    'id'            => $lid,
                                    'name'          => \Bootstrap::$lang->get("needs/iwant",$this->local_lang),
                                    'period' => "month",
                                    'period_time'   => 6,
                                    'amount' => ((float) $price["semiannually"] + (float) $price["ssetupfee"]),
                                    'cid'    => $currency_id,
                                ];
                            }


                            if((float) $price["annually"] > 0 || (float) $price["asetupfee"] > 0){
                                $lid++;
                                $ad_options[] = [
                                    'id'            => $lid,
                                    'name'          => \Bootstrap::$lang->get("needs/iwant",$this->local_lang),
                                    'period' => "year",
                                    'period_time'   => 1,
                                    'amount' => ((float) $price["annually"] + (float) $price["asetupfee"]),
                                    'cid'    => $currency_id,
                                ];
                            }

                            if((float) $price["biennially"] > 0 || (float) $price["bsetupfee"] > 0) {
                                $lid++;
                                $ad_options[] = [
                                    'id'          => $lid,
                                    'name'        => \Bootstrap::$lang->get("needs/iwant",$this->local_lang),
                                    'period'      => "year",
                                    'period_time' => 2,
                                    'amount'      => ((float)$price["biennially"] + (float)$price["bsetupfee"]),
                                    'cid'         => $currency_id,
                                ];
                            }

                            if((float) $price["triennially"] > 0 || (float) $price["tsetupfee"] > 0){
                                $lid++;
                                $ad_options[] = [
                                    'id'            => $lid,
                                    'name'          => \Bootstrap::$lang->get("needs/iwant",$this->local_lang),
                                    'period' => "year",
                                    'period_time'   => 3,
                                    'amount' => ((float) $price["triennially"] + (float) $price["tsetupfee"]),
                                    'cid'    => $currency_id,
                                ];
                            }
                        }
                    }

                    if(!$ad_options){
                        $currency               = \Money::Currency($collation["local_currency"]["code"]);
                        $currency_id            = $currency["id"];
                        $ad_options[] = [
                            'id'            => $lid,
                            'name'          => \Bootstrap::$lang->get("needs/iwant",$this->local_lang),
                            'period' => "none",
                            'period_time'   => 1,
                            'amount' => 0,
                            'cid'    => $currency_id,
                        ];
                    }

                    foreach($this->lang_list AS $l)
                    {
                        $lk = $l["key"];
                        $result["product_addons"][$row["id"]]["lang"][$lk] = [
                            'name'          => $row["name"],
                            'description'   => $row["description"],
                            'type'          => 'checkbox',
                            'options'       => \Utility::jencode($ad_options),
                            'lid'           => $lid,
                        ];
                    }

                }
            }

            $announcements     = $this->db->select()->from("tblannouncements");
            $announcements     = $announcements->build() ? $announcements->fetch_assoc() : [];
            if($announcements)
            {
                $whmcs_lang =  array_flip(\Bootstrap::$lang->languages);

                foreach($announcements AS $row)
                {
                    if($row["parentid"] == 0)
                    {
                        $result["others"]["announcements"][$row["id"]] = [
                            'data'          => [
                                'type'      => "news",
                                'category'  => 0,
                                'status'    => $row["published"] ? "active" : 'inactive',
                                'ctime'     => $row["date"],
                            ],
                            'lang'     => [
                                \Bootstrap::$lang->clang => [
                                    'owner_id'  => $row["id"],
                                    'lang'      => \Bootstrap::$lang->clang,
                                    'title'     => $row["title"],
                                    'content'   => $row["announcement"],
                                    'route'     => \Filter::permalink($row["title"]),
                                ],
                            ],
                        ];
                    }
                    elseif(isset($result["others"]["announcements"][$row["parentid"]]))
                    {
                        $l_code =  $whmcs_lang[$row["language"]] ?? "en";
                        $result["others"]["announcements"][$row["parentid"]]["lang"][$l_code] = [
                            'title'     => $row["title"],
                            'content'   => $row["content"],
                            'route'     => \Filter::permalink($row["title"]),
                        ];
                    }
                }
            }
        }
        private function pull_data_part2(&$result,&$collation):void
        {
            // Fetch User Groups
            $user_groups   = $this->db->select()->from("tblclientgroups");
            $user_groups   = $user_groups->build() ? $user_groups->fetch_assoc() : [];
            if($user_groups){
                foreach($user_groups AS $row){
                    $result["others"]["user_groups"][$row["id"]] = [
                        'name' => $row["groupname"],
                    ];
                }
            }

            // Fetch Users
            $users   = $this->db->select()->from("tblclients");
            $users   = $users->build() ? $users->fetch_assoc() : [];
            if($users){
                foreach($users AS $row){
                    $name               = trim($row["firstname"]);
                    $surname            = trim($row["lastname"]);
                    $full_name          = $name;
                    if(!\Validation::isEmpty($surname)){
                        if(\Validation::isEmpty($name)){
                            $name       = $surname;
                            $surname    = NULL;
                            $full_name  = $name;
                        }else $full_name .= " ".$surname;
                    }

                    $full_name          = \Filter::html_clear($full_name);
                    $full_name          = \Utility::substr($full_name,0,255);
                    $full_name          = \Utility::ucfirst_space($full_name);
                    $smash              = \Filter::name_smash($full_name);
                    $name               = $smash["first"];
                    $surname            = $smash["last"];
                    $email              = $row["email"];
                    $password           = $row["password"];
                    $status             = "active";
                    $key                = '-O@cb6Dxvarhc+Ghp}1eW~Y0yYfrfI#RBWdl';

                    if($password) $password = \User::_crypt("member",$password,'encryptH',$key);
                    else
                        $password = \User::_crypt("member",\Utility::generate_hash(16),'encrypt',$key);


                    if($row["status"] == "Active") $status = "active";
                    elseif($row["status"] == "Inactive") $status = "active";
                    elseif($row["status"] == "Closed") $status = "blocked";

                    $ctime              = $row["datecreated"]." 00:00:00";
                    $ctime              = substr($ctime,0,4) == "0000" ? \DateManager::Now() : $ctime;
                    $last_login_time    = $row["lastlogin"];
                    $last_login_time    = substr($last_login_time,0,4) == "0000" ? $ctime : $last_login_time;
                    $ip                 = $row["ip"];
                    $phone              = \Filter::numbers($row["phonenumber"]);
                    $landline_phone     = '';
                    $company_name       = trim($row["companyname"]);
                    $company_tax_number = trim($row["tax_id"] ?? '');
                    $company_tax_office = '';
                    $address            = $row["address1"];
                    if($row["address2"]) $address .= " ".$row["address2"];
                    $identity           = '';
                    $sms_notification   = 1;
                    $mail_notification  = 1;
                    $country            = \Utility::strtolower($row["country"]);

                    if(\Validation::isEmpty($full_name) || \Validation::isEmpty($email)) continue;

                    try
                    {
                        if($phone && $country == "tr"){
                            $length         = strlen($phone);
                            if($length==11) $phone = "9".$phone;
                            elseif($length==10) $phone = "90".$phone;
                            else $phone      = NULL;
                        }
                        if($phone && strlen($phone) > 8 && \Validation::isPhone($phone)){
                            $smash           = \Filter::phone_smash($phone);
                            $gsm_cc          = $smash["cc"];
                            $gsm             = $smash["number"];
                        }
                        else
                        {
                            $gsm_cc          = false;
                            $gsm             = false;
                        }
                    }
                    catch(\Exception $e)
                    {
                        $gsm = false;
                        $gsm_cc = false;
                    }

                    $company_name       = \Filter::html_clear($company_name);
                    $company_name       = \Filter::html_clear($company_name);
                    $company_tax_number = \Filter::html_clear($company_tax_number);
                    $company_tax_office = \Filter::html_clear($company_tax_office);
                    $address            = \Filter::html_clear($address);
                    $identity           = \Filter::identity($identity);
                    $kind               = $company_name ? "corporate" : "individual";
                    $language           = \Utility::ucfirst($row["language"]);
                    $lang               = $this->local_lang;

                    $languages          = \Bootstrap::$lang->languages;
                    $languages          = array_flip($languages);

                    if($language && isset($languages[$language])){
                        $check1         = $languages[$language]."-".$country;
                        $check2         = $languages[$language];
                        if(\Bootstrap::$lang->LangExists($check1)) $lang = $check1;
                        elseif(\Bootstrap::$lang->LangExists($check2)) $lang = $check2;
                        elseif(\Bootstrap::$lang->LangExists("en")) $lang = "en";
                    }

                    $country_id         = \AddressManager::LocalCountryID();
                    $currency           = \Config::get("general/currency");

                    if($row["currency"] && isset($collation["currencies"][$row["currency"]])){
                        $get_curr       = \Money::Currency($collation["currencies"][$row["currency"]]);
                        if($get_curr) $currency = $get_curr["id"];
                    }

                    if($country){
                        $get_country_id = \AddressManager::get_id_with_cc($country);
                        if($get_country_id) $country_id = $get_country_id;
                    }


                    $item                   = [
                        'data'              => [
                            'group_id'          => $row["groupid"],
                            'type'              => "member",
                            'status'            => $status,
                            'name'              => $name,
                            'surname'           => $surname,
                            'full_name'         => $full_name,
                            'email'             => $email,
                            'phone'             => $phone,
                            'password'          => $password,
                            'ip'                => $ip,
                            'lang'              => $lang,
                            'country'           => $country_id,
                            'currency'          => $currency,
                            'balance'           => $row["credit"],
                            'balance_currency'  => $currency,
                            'creation_time'     => $ctime,
                            'last_login_time'   => $last_login_time,
                        ],
                        'info'                      => [
                            'kind'                  => $kind,
                            'email_notifications'   => $mail_notification,
                            'sms_notifications'     => $sms_notification,
                            'verified-email'        => $email,
                        ],
                        'addresses'                 => [],
                    ];

                    if($row["notes"]) $item["info"]["notes"] = $row["notes"];
                    if($row["taxexempt"]) $item["info"]["taxation"] = 0;

                    if($gsm_cc && $gsm){
                        $item["data"]["phone"]          = $phone;
                        $item["info"]["phone"]          = $phone;
                        $item["info"]["gsm_cc"]         = $gsm_cc;
                        $item["info"]["gsm"]            = $gsm;
                        $item["info"]["verified-gsm"]   = $phone;
                    }

                    if($kind == "corporate"){
                        if($company_name) $item["info"]["company_name"] = $company_name;
                        if($company_tax_number) $item["info"]["company_tax_number"] = $company_tax_number;
                        if($company_tax_office) $item["info"]["company_tax_office"] = $company_tax_office;
                        if($landline_phone) $item["info"]["landline_phone"]         = $landline_phone;
                    }

                    if($address)
                        $item["addresses"][] = [
                            'name'       => $name,
                            'surname'           => $surname,
                            'full_name'         => $full_name,
                            'email'             => $email,
                            'kind'              => $kind,
                            'company_name'      => $company_name,
                            'company_tax_number' => $company_tax_number,
                            'company_tax_office' => $company_tax_office,
                            'phone'              => $phone,
                            'country_id'         => $country_id,
                            'counti'             => $row["city"],
                            'city'               => $row["state"],
                            'zipcode'            => $row["postcode"],
                            'address'            => $address,
                            'detouse'            => 1,
                        ];

                    $result["users"][$row["id"]] = $item;
                    $result["count"]["users"] +=1;

                }
            }

            // Fetch Credit Logs
            $credit_logs   = $this->db->select()->from("tblcredit");
            $credit_logs   = $credit_logs->build() ? $credit_logs->fetch_assoc() : [];
            if($credit_logs){
                foreach($credit_logs AS $row){
                    if(isset($result["users"][$row["clientid"]])){
                        $cid        = $result["users"][$row["clientid"]]["data"]["currency"];
                        $amount     = $row["amount"];
                        $type       = "up";
                        if($amount < 0){
                            $type = "down";
                            $amount = substr($amount,1);
                        }

                        $result["others"]["credit_logs"][] = [
                            'user_id'       => $row["clientid"],
                            'type'          => $type,
                            'cid'           => $cid,
                            'amount'        => $amount,
                            'description'   => $row["description"],
                            'cdate'         => $row["date"]." 00:00:00",
                        ];
                    }
                }
            }

            // Fetch Affiliates

            $affiliates     = $this->db->select()->from("tblaffiliates");
            $affiliates     = $affiliates->build() ? $affiliates->fetch_assoc() : [];
            if($affiliates)
            {
                foreach($affiliates AS $row)
                {
                    if(isset($result["users"][$row["clientid"]]))
                    {
                        $cid            = $result["users"][$row["clientid"]]["data"]["currency"];
                        $com_per        = '';
                        $com_val        = '0.0000';

                        if($row["onetime"] == 1) $com_per = "onetime";
                        if($row["paytype"] && $row["paytype"] == "percentage" && $row["payamount"] > 0.00)
                            $com_val = $row["payamount"];


                        $_referrers     = [];
                        $_hits          = [];
                        $_transactions  = [];
                        $_withdrawals   = [];
                        $_members       = [];

                        $referrers      = $this->db->select()->from("tblaffiliates_referrers");
                        $referrers->where("affiliate_id","=",$row["id"]);
                        $referrers     = $referrers->build() ? $referrers->fetch_assoc() : [];

                        if($referrers)
                        {
                            foreach($referrers AS $rowx)
                            {
                                $_referrers[$rowx["id"]] = [
                                    'affiliate_id'  => $rowx["affiliate_id"],
                                    'referrer'      => $rowx["referrer"],
                                    'ctime'         => $rowx["created_at"],
                                ];
                            }
                        }

                        $hits      = $this->db->select()->from("tblaffiliates_hits");
                        $hits->where("affiliate_id","=",$row["id"]);
                        $hits     = $hits->build() ? $hits->fetch_assoc() : [];

                        if($hits)
                        {
                            foreach($hits AS $rowx)
                            {
                                $_hits[$rowx["id"]] = [
                                    'affiliate_id'  => $rowx["affiliate_id"],
                                    'referrer_id'   => $rowx["referrer_id"],
                                    'ctime'         => $rowx["created_at"],
                                ];
                            }
                        }

                        $withdrawals      = $this->db->select()->from("tblaffiliateswithdrawals");
                        $withdrawals->where("affiliateid","=",$row["id"]);
                        $withdrawals     = $withdrawals->build() ? $withdrawals->fetch_assoc() : [];

                        if($withdrawals)
                        {
                            foreach($withdrawals AS $rowx)
                            {
                                $_withdrawals[$rowx["id"]] = [
                                    'affiliate_id'          => $rowx["affiliateid"],
                                    'ctime'                 => $rowx["date"],
                                    'completed_time'        => $rowx["date"],
                                    'gateway'               => 1,
                                    'amount'                => $rowx["amount"],
                                    'currency'              => $cid,
                                    'status'                => "completed",
                                ];
                            }
                        }


                        $histories      = $this->db->select()->from("tblaffiliateshistory");
                        $histories->where("affiliateid","=",$row["id"]);
                        $histories     = $histories->build() ? $histories->fetch_assoc() : [];
                        if($histories)
                        {
                            foreach($histories AS $rowx)
                            {
                                $_transactions[] = [
                                    'affiliate_id'      => $rowx["affiliateid"],
                                    'order_id'          => 0,
                                    'clicked_ctime'     => $rowx["date"],
                                    'ctime'             => $rowx["date"],
                                    'clearing_date'     => $rowx["date"],
                                    'completed_time'    => $rowx["date"],
                                    'amount'            => 0,
                                    'currency'          => $cid,
                                    'rate'              => 0,
                                    'commission'        => $rowx["amount"],
                                    'exchange'          => 0,
                                    'status'            => 'approved',
                                ];
                            }
                        }

                        $members      = $this->db->select()->from("tblaffiliatesaccounts");
                        $members->where("affiliateid","=",$row["id"]);
                        $members     = $members->build() ? $members->fetch_assoc() : [];

                        if($members) foreach($members AS $rowx) $_members[] = $rowx["relid"];

                        $result["others"]["affiliates"][$row["id"]] = [
                            'data' => [
                                'owner_id'          => $row["clientid"],
                                'disabled'          => 0,
                                'date'              => $row["date"],
                                'commission_period' => $com_per,
                                'commission_value'  => $com_val,
                                'balance'           => $row["balance"],
                                'currency'          => $cid,
                            ],
                            'relationships' => [
                                'transactions'      => $_transactions,
                                'hits'              => $_hits,
                                'referrers'         => $_referrers,
                                'withdrawals'       => $_withdrawals,
                                'members'           => $_members,
                            ],
                        ];


                    }
                }
            }
        }
        private function pull_data_part3(&$result,&$collation):void
        {
            $local_taxation_type    = \Invoices::getTaxationType();
            $local_tax_rate         = \Invoices::getTaxRate();

            $taxation_type = 'exclusive';
            $get_taxation = $this->db->select("value")->from("tblconfiguration")->where("setting","=","TaxType");
            $get_taxation = $get_taxation->build() ? $get_taxation->getObject()->value : false;

            if($get_taxation == "Inclusive") $taxation_type = "inclusive";


            // Fetch Domain Orders
            $orders   = $this->db->select()->from("tbldomains");
            $orders   = $orders->build() ? $orders->fetch_assoc() : [];
            if($orders){
                foreach($orders AS $row){
                    $order_group_id     = $row["orderid"] ?? 0;
                    $invoice_id         = 0;
                    $user_id            = $row["userid"];
                    if(!isset($result["users"][$user_id])) continue;
                    $user           = $result["users"][$user_id];
                    $id             = $row["id"];
                    $type           = "domain";
                    $product_id     = 0;
                    $domain         = $row["domain"];
                    $name           = $domain;
                    $parse          = \Utility::domain_parser($domain);
                    if(isset($parse["tld"]) && $parse["tld"]){
                        $sld        = $parse["host"];
                        $tld        = $parse["tld"];
                        if(isset($collation["tlds"][$tld])) $product_id = $collation["tlds"][$tld];
                    }


                    if($order_group_id)
                    {
                        $order_group = $this->db->select()->from("tblorders")->where("id","=",$order_group_id);
                        if($order_group->build()) $invoice_id = $order_group->getObject()->invoiceid ?? 0;
                    }


                    $rel                           = "Domain".$row["type"]."|".$id;
                    $period                        = "year";
                    $sub_id                        = $row["subscriptionid"];
                    $period_time                   = $row["registrationperiod"];
                    $amount                        = $row["recurringamount"];
                    if ($taxation_type == 'inclusive') $amount -= \Money::get_exclusive_tax_amount($amount,$local_tax_rate);
                    $total_amount                  = $amount;
                    $amount_cid                    = $user["data"]["currency"];
                    $status                        = "waiting";
                    $pmethod                       = $this->find_module_name("Payment",$row["paymentmethod"]);
                    if($pmethod == "Stripe" && $sub_id) $pmethod = "StripeCheckout";

                    if($row["status"] == "Fraud" || $row["status"] == "Cancelled") $status = "cancelled";
                    elseif($row["status"] == "Expired") $status = "cancelled";
                    elseif($row["status"] == "Grace") $status = "cancelled";
                    elseif($row["status"] == "Redemption") $status = "cancelled";
                    elseif($row["status"] == "Pending Transfer") $status = "inprocess";
                    elseif($row["status"] == "Transferred Away") $status = "active";
                    elseif($row["status"] == "Active") $status = "active";
                    $cdate                          = $row["registrationdate"]." 00:00:00";
                    if(substr($cdate,0,4) == "0000") $cdate = \DateManager::ata();
                    $renewaldate                    = $cdate;
                    $duedate                        = $row["nextduedate"]." 00:00:00";
                    if(substr($duedate,0,4) == "0000") $duedate = \DateManager::ata();

                    $module                         = $this->find_module_name("Registrars",$row["registrar"]);

                    $gname  = __("website/account_products/product-type-names/".$type,false,$this->local_lang);

                    $options                        = [];

                    $options["whois_manage"]        = true;
                    $options["dns_manage"]          = true;
                    $options["whois"]               = [];
                    if($row["status"] == "Transferred Away") $options["transferlock"] = false;
                    $options["group_name"]          = $gname;
                    $options["local_group_name"]    = $gname;
                    $options["category_id"]         = 0;
                    $options["domain"]              = $domain;
                    $options["ns1"]                 = $collation["ns1"];
                    $options["ns2"]                 = $collation["ns2"];
                    $options["name"]                = $sld;
                    $options["tld"]                 = $tld;

                    if($status == "active"){
                        $options["established"] = true;
                        $options["config"]      = ['ID' => 0];
                    }
                    if($row["idprotection"]) $options["whois_privacy"] = true;

                    if($row["status"] == "Pending Transfer"){
                        $result["others"]["events"][] = [
                            'user_id'   => $user_id,
                            'type'      => "operation",
                            'owner'     => "order",
                            'owner_id'  => $rel,
                            'name'      => $module != "none" ? "transfer-request-to-us-with-api" : "transfer-request-to-us-with-manuel",
                            'data'      => \Utility::jencode(['domain' => $domain]),
                            'cdate'     => \DateManager::Now(),
                        ];
                    }

                    $item                   = [
                        'owner_id'          => $user_id,
                        'subscription_id'   => $sub_id,
                        'invoice_id'        => $invoice_id,
                        'type'              => $type,
                        'product_id'        => $product_id,
                        'name'              => $name,
                        'period'            => $period,
                        'period_time'       => $period_time,
                        'total_amount'      => $total_amount,
                        'amount'            => $amount,
                        'amount_cid'        => $amount_cid,
                        'status'            => $status,
                        'pmethod'           => $pmethod,
                        'cdate'             => $cdate,
                        'renewaldate'       => $renewaldate,
                        'duedate'           => $duedate,
                        'module'            => $module ?: 'none',
                        'options'           => $options,
                        'unread'            => 1,
                    ];

                    $result["orders"][$rel] = $item;
                    $result["count"]["orders"] +=1;
                }
            }

            // Fetch Hosting & Sever && Special Orders
            $orders   = $this->db->select()->from("tblhosting");
            $orders   = $orders->build() ? $orders->fetch_assoc() : [];
            if($orders){
                foreach($orders AS $row)
                {
                    $user_id        = $row["userid"];
                    if(!isset($result["users"][$user_id])) continue;
                    if(!isset($result["products"][$row["packageid"]])) continue;

                    $order_group_id     = $row["orderid"] ?? 0;


                    $user           = $result["users"][$user_id];
                    $sub_id         = $row["subscriptionid"];
                    $id             = $row["id"];
                    $product_id     = $row["packageid"];
                    $domain         = $row["domain"];
                    $invoice_id     = 0;

                    if($order_group_id)
                    {

                        $order_group = $this->db->select()->from("tblorders")->where("id","=",$order_group_id);
                        if($order_group->build()) $invoice_id = $order_group->getObject()->invoiceid ?? 0;
                    }

                    $product        = $result["products"][$row["packageid"]];

                    $type           = $product["data"]["type"];
                    $name           = $product["lang"][$this->local_lang]["title"];

                    $period         = "none";
                    $period_time    = 0;

                    if($row["billingcycle"] == "Monthly"){
                        $period                        = "month";
                        $period_time                   = 1;
                    }
                    elseif($row["billingcycle"] == "Quarterly"){
                        $period                        = "month";
                        $period_time                   = 3;
                    }
                    elseif($row["billingcycle"] == "Semi-Annually"){
                        $period                        = "month";
                        $period_time                   = 6;
                    }
                    elseif($row["billingcycle"] == "Annually"){
                        $period                        = "year";
                        $period_time                   = 1;
                    }
                    elseif($row["billingcycle"] == "Biennially"){
                        $period                        = "year";
                        $period_time                   = 2;
                    }
                    elseif($row["billingcycle"] == "Triennially"){
                        $period                        = "year";
                        $period_time                   = 3;
                    }

                    $amount                        = $row["amount"];
                    if ($taxation_type == 'inclusive') $amount -= \Money::get_exclusive_tax_amount($amount,$local_tax_rate);
                    $total_amount                  = $amount;
                    $amount_cid                    = $user["data"]["currency"];
                    $terminated                    = ($row["domainstatus"] == "Terminated") ? 1 : 0;
                    $status                        = "waiting";
                    $pmethod                       = $this->find_module_name("Payment",$row["paymentmethod"]);
                    if($pmethod == "Stripe" && $sub_id) $pmethod = "StripeCheckout";
                    if($row["domainstatus"] == "Fraud" || $row["domainstatus"] == "Cancelled") $status = "cancelled";
                    elseif($row["domainstatus"] == "Terminated") $status = "cancelled";
                    elseif($row["domainstatus"] == "Suspended") $status = "suspended";
                    elseif($row["domainstatus"] == "Completed") $status = "active";
                    elseif($row["domainstatus"] == "Active") $status = "active";

                    $cdate                          = $row["regdate"]." 00:00:00";
                    if(substr($cdate,0,4) == "0000") $cdate = \DateManager::ata();
                    $renewaldate                    = $cdate;
                    $duedate                        = $row["nextduedate"]." 00:00:00";
                    $terminated_date                 = $row["termination_date"]." 00:00:00";
                    if(substr($duedate,0,4) == "0000") $duedate = \DateManager::ata();
                    if(substr($terminated_date,0,4) == "0000") $terminated_date = \DateManager::ata();

                    $module                         = "none";
                    $type_id                        = 0;

                    if($type == "special"){
                        $type_id = "special-group";
                        $gname  = $result["categories"]["products"][$type_id]["lang"][$this->local_lang]["title"];
                    }
                    else
                        $gname  = __("website/account_products/product-type-names/".$type,false,$this->local_lang);


                    $options                        = [];

                    $options["group_name"]          = $gname;
                    $options["local_group_name"]    = $gname;

                    $category       = isset($result["categories"]["products"][$product["data"]["category"]]) ? $result["categories"]["products"][$product["data"]["category"]] : [];
                    if($category){
                        $options["category_name"]       = $category["lang"][$this->local_lang]["title"];
                        $options["local_category_name"] = $category["lang"][$this->local_lang]["title"];
                        $options["category_id"]         = $product["data"]["category"];
                    }
                    else{
                        $options["category_name"]       = NULL;
                        $options["local_category_name"] = NULL;
                        $options["category_id"]         = 0;
                    }

                    if($row["password"]){
                        $row["password"]        = $this->whmcs_decrypt($row["password"]);
                        $row["password"]        = \Crypt::encode($row["password"],\Config::get("crypt/user"));
                    }

                    if($type == "hosting"){
                        $options["domain"]                  = $domain;
                        $options["panel_type"]              = $product["data"]["options"]["panel_type"];
                        $options["email_limit"]             = $product["data"]["options"]["email_limit"];
                        $options["database_limit"]          = $product["data"]["options"]["database_limit"];
                        $options["addons_limit"]            = $product["data"]["options"]["addons_limit"];
                        $options["subdomain_limit"]         = $product["data"]["options"]["subdomain_limit"];
                        $options["ftp_limit"]               = $product["data"]["options"]["ftp_limit"];
                        $options["park_limit"]              = $product["data"]["options"]["park_limit"];
                        $options["max_email_per_hour"]      = $product["data"]["options"]["max_email_per_hour"];
                        $options["cpu_limit"]               = $product["data"]["options"]["cpu_limit"];
                        $options["server_features"]         = $product["data"]["options"]["server_features"];
                        if($row["ns1"])
                            $options["dns"]                 = [
                                'ns1'                           => $row["ns1"],
                                'ns2'                           => $row["ns2"],
                            ];
                        else $options["dns"]                = $product["data"]["options"]["dns"];
                        if(isset($product["data"]["module_data"]["create_account"]))
                            $options["creation_info"]       = $product["data"]["module_data"]["create_account"];
                        else
                            $options["creation_info"]       = $product["data"]["module_data"] ?? [];
                    }
                    elseif($type == "server"){
                        $options["hostname"]                = $domain;
                        $options["login"]["username"]       = $row["username"];
                        $options["login"]["password"]       = \Crypt::encode($row["password"],\Config::get("crypt/user"));
                        if($row["ns1"]) $options["ns1"]     = $row["ns1"];
                        if($row["ns2"]) $options["ns2"]     = $row["ns2"];
                        $options["creation_info"]           = $product["data"]["module_data"];
                    }


                    if($row["dedicatedip"]) $options["ip"] = $row["dedicatedip"];
                    if($domain) $options["domain"] = $domain;
                    if($row["assignedips"]) $options["assigned_ips"] = $row["assignedips"];


                    if($row["server"] && isset($result["others"]["shared_servers"][$row["server"]])){
                        $server                     = $result["others"]["shared_servers"][$row["server"]];
                        $module                     = $server["type"];
                        $options["server_id"]       = $row["server"];

                        if($type == "hosting"){

                            if($module == "cPanel" || $module == "Plesk") $options["server_features"][] = $module;

                            $options["config"]          = [
                                'user'                  => $row["username"],
                                'password'              => $row["password"],
                            ];
                        }
                        elseif($type == "server"){
                            if($module == "CyberVM"){

                                $get_vmid = $this->db->select("vmid")->from("mod_vmpanel")->where("serviceid","=",$row["id"]);
                                $get_vmid = $get_vmid->build() ? $get_vmid->getObject()->vmid : false;
                                if($get_vmid) $options["config"] = ['vpsid' => $get_vmid];

                            }
                            elseif($module == "SolusVM"){
                                $vserverid_field    = $this->db->select("id")->from("tblcustomfields");
                                $vserverid_field->where("type","=","product","&&");
                                $vserverid_field->where("relid","=",$product_id,"&&");
                                $vserverid_field->where("fieldname","=","vserverid");
                                $vserverid_field = $vserverid_field->build() ? $vserverid_field->getObject()->id : false;
                                if($vserverid_field){
                                    $vserverid_value    = $this->db->select("value")->from("tblcustomfieldsvalues");
                                    $vserverid_value->where("fieldid","=",$vserverid_field,"&&");
                                    $vserverid_value->where("relid","=",$id);
                                    $vserverid_value = $vserverid_value->build() ? $vserverid_value->getObject()->value : false;
                                    if($vserverid_value) $options["config"] = ['vpsid' => $vserverid_value];
                                }

                            }
                            elseif($module == "AutoVM"){
                                $vserverid_field    = $this->db->select("id")->from("tblcustomfields");
                                $vserverid_field->where("type","=","product","&&");
                                $vserverid_field->where("relid","=",$product_id,"&&");
                                $vserverid_field->where("fieldname","=","vpsid");
                                $vserverid_field = $vserverid_field->build() ? $vserverid_field->getObject()->id : false;
                                if($vserverid_field){
                                    $vserverid_value    = $this->db->select("value")->from("tblcustomfieldsvalues");
                                    $vserverid_value->where("fieldid","=",$vserverid_field,"&&");
                                    $vserverid_value->where("relid","=",$id);
                                    $vserverid_value = $vserverid_value->build() ? $vserverid_value->getObject()->value : false;
                                    if($vserverid_value) $options["config"] = ['vpsid' => $vserverid_value];
                                }
                            }
                            elseif($module == "Virtualizor"){

                                $vserverid_field    = $this->db->select("id")->from("tblcustomfields");
                                $vserverid_field->where("type","=","product","&&");
                                $vserverid_field->where("relid","=",$product_id,"&&");
                                $vserverid_field->where("fieldname","=","vpsid");
                                $vserverid_field = $vserverid_field->build() ? $vserverid_field->getObject()->id : false;
                                if($vserverid_field){
                                    $vserverid_value    = $this->db->select("value")->from("tblcustomfieldsvalues");
                                    $vserverid_value->where("fieldid","=",$vserverid_field,"&&");
                                    $vserverid_value->where("relid","=",$id);
                                    $vserverid_value = $vserverid_value->build() ? $vserverid_value->getObject()->value : false;
                                    if($vserverid_value) $options["config"] = ['vpsid' => $vserverid_value];
                                }
                            }
                        }
                    }
                    else{

                        $ssl_order      = $this->db->select()->from("tblsslorders");
                        $ssl_order->where("serviceid","=",$id);
                        $ssl_order      = $ssl_order->build() ? $ssl_order->getAssoc() : [];
                        if($ssl_order){
                            if($ssl_order["remoteid"]) $options["config"]["order_id"] = $ssl_order["remoteid"];
                            $config_data    = $ssl_order["configdata"];
                            $config_data    = $config_data ? \Utility::jdecode($config_data,true) : [];
                            if(isset($config_data["csr"])) $options["csr-code"] = $config_data["csr"];
                            if(isset($config_data["approveremail"]) && !is_array($config_data["approveremail"])){
                                $parse_apemail = explode("@",$config_data["approveremail"]);
                                $options["verification-email"] = $parse_apemail[0];
                            }
                        }

                        if($product["data"]["module"] != 'none'){
                            $module = $product["data"]["module"];
                            if($module == "NamecheapSSL" || $module == "GogetSSL" || $module == "ResellerClubSSL" || $module == "OnlineNICSSL"){
                                if($ssl_order) $options["checking-ssl-enroll"] = true;
                            }
                        }
                    }

                    if(isset($options["config"]["password"]))
                    {
                        $options["config"]["password"] = \Crypt::encode($options["config"]["password"],\Config::get("crypt/user"));
                    }

                    if($status == "active" && isset($options["config"]) && $options["config"])
                        $options["established"] = true;


                    if($terminated && isset($options['config'])) unset($options["config"]);


                    // Fetch Order Requirements
                    $p_requirements   = $product["data"]["requirements"];
                    if($p_requirements){
                        foreach($p_requirements AS $rq_id=>$p_requirement){
                            $field_value = $this->db->select()->from("tblcustomfieldsvalues");
                            $field_value->where("fieldid","=",$rq_id,"&&");
                            $field_value->where("relid","=",$id);
                            $field_value = $field_value->build() ? $field_value->getAssoc() : '';
                            if($field_value){
                                $requirement = $result["product_requirements"][$p_requirement];
                                $result["order_requirements"][] = [
                                    'owner_id'          => $id,
                                    'requirement_id'    => $p_requirement,
                                    'requirement_name'  => $requirement["lang"][$user["data"]["lang"]]["name"],
                                    'response_type'     => $requirement["lang"][$user["data"]["lang"]]["type"],
                                    'response'          => $field_value ? $field_value["value"] : '',
                                ];
                            }
                        }
                    }

                    $item                   = [
                        'owner_id'          => $user_id,
                        'invoice_id'        => $invoice_id,
                        'subscription_id'   => $sub_id,
                        'type'              => $type,
                        'type_id'           => $type_id,
                        'product_id'        => $product_id,
                        'name'              => $name,
                        'period'            => $period,
                        'period_time'       => $period_time,
                        'total_amount'      => $total_amount,
                        'amount'            => $amount,
                        'amount_cid'        => $amount_cid,
                        'status'            => $status,
                        'pmethod'           => $pmethod,
                        'cdate'             => $cdate,
                        'renewaldate'       => $renewaldate,
                        'duedate'           => $duedate,
                        'module'            => $module ?: 'none',
                        'options'           => $options,
                        'unread'            => 1,
                        'notes'             => $row["notes"] ?: 'Imported from WHMCS',
                        'server_terminated' => $terminated,
                        'terminated_date'   => $terminated_date,
                    ];

                    $rel                    = "Hosting|".$id;

                    $result["orders"][$rel] = $item;
                    $result["count"]["orders"] +=1;

                    $g_config_opts = $this->db->select()->from("tblhostingconfigoptions");
                    $g_config_opts->where("relid","=",$id);
                    $g_config_opts->order_by("id ASC");
                    $g_config_opts = $g_config_opts->build() ? $g_config_opts->fetch_assoc() : [];
                    if($g_config_opts){
                        foreach($g_config_opts AS $opt_v){
                            if(isset($collation["config_options"][$opt_v["configid"]])){
                                $conopt      = $collation["config_options"][$opt_v["configid"]];
                                $conopt_sub  = $collation["config_options_sub"][$opt_v["optionid"]];
                                $sub_price   = $collation["config_options_sub_price"][$opt_v["optionid"]];
                                $conopt_qty  = '';
                                if($opt_v["qty"] > 1) $conopt_qty .= " (".$opt_v["qty"].")";
                                $conopt_amount          = 0;

                                if($row["billingcycle"] == "Monthly")
                                    $conopt_amount = (float) $sub_price["monthly"];
                                elseif($row["billingcycle"] == "Quarterly")
                                    $conopt_amount = (float) $sub_price["quarterly"];
                                elseif($row["billingcycle"] == "Semi-Annually")
                                    $conopt_amount = $sub_price["semiannually"];
                                elseif($row["billingcycle"] == "Annually")
                                    $conopt_amount = $sub_price["annually"];
                                elseif($row["billingcycle"] == "Biennially")
                                    $conopt_amount = $sub_price["biennially"];
                                elseif($row["billingcycle"] == "Triennially")
                                    $conopt_amount = $sub_price["triennially"];

                                $result["orders"]["ConfigOption|".$opt_v["id"]] = [
                                    'owner_id'          => $id,
                                    'subscription_id'   => $sub_id,
                                    'addon_name'        => $conopt["optionname"],
                                    'option_name'       => $conopt_sub["optionname"].$conopt_qty,
                                    'period'            => $period,
                                    'period_time'       => $period_time,
                                    'amount'            => $conopt_amount,
                                    'cid'               => $amount_cid,
                                    'status'            => $status,
                                    'cdate'             => $cdate,
                                    'renewaldate'       => $renewaldate,
                                    'duedate'           => $duedate,
                                    'unread'            => 1,
                                ];
                            }
                        }
                    }
                }
            }

            // Fetch Addon Orders
            $addon_orders   = $this->db->select()->from("tblhostingaddons");
            $addon_orders   = $addon_orders->build() ? $addon_orders->fetch_assoc() : [];
            if($addon_orders){
                foreach($addon_orders AS $row){
                    $order_group_id     = $row["orderid"] ?? 0;
                    $user_id            = $row["userid"];
                    if(!isset($result["users"][$user_id])) continue;
                    if(!isset($result["orders"]["Hosting|".$row["hostingid"]])) continue;
                    $user           = $result["users"][$user_id];
                    $id             = $row["id"];
                    $addon_id       = $row["addonid"];
                    $sub_id         = $row["subscriptionid"];
                    $invoice_id     = 0;

                    if($order_group_id)
                    {
                        $order_group = $this->db->select()->from("tblorders")->where("id","=",$order_group_id);
                        if($order_group->build()) $invoice_id = $order_group->getObject()->invoiceid ?? 0;
                    }

                    if(!isset($result["product_addons"][$addon_id])) continue;
                    $addon          = $result["product_addons"][$addon_id];


                    $period         = "none";
                    $period_time    = 0;

                    if($row["billingcycle"] == "Monthly"){
                        $period                        = "month";
                        $period_time                   = 1;
                    }elseif($row["billingcycle"] == "Quarterly"){
                        $period                        = "month";
                        $period_time                   = 3;
                    }elseif($row["billingcycle"] == "Semi-Annually"){
                        $period                        = "month";
                        $period_time                   = 6;
                    }elseif($row["billingcycle"] == "Annually"){
                        $period                        = "year";
                        $period_time                   = 1;
                    }elseif($row["billingcycle"] == "Biennially"){
                        $period                        = "year";
                        $period_time                   = 2;
                    }elseif($row["billingcycle"] == "Triennially"){
                        $period                        = "year";
                        $period_time                   = 3;
                    }

                    $amount                        = $row["recurring"];
                    if ($taxation_type == 'inclusive') $amount -= \Money::get_exclusive_tax_amount($amount,$local_tax_rate);
                    $amount_cid                    = $user["data"]["currency"];
                    $status                        = "cancelled";

                    if($row["status"] == "Fraud" || $row["status"] == "Cancelled") $status = "cancelled";
                    elseif($row["status"] == "Terminated") $status = "cancelled";
                    elseif($row["status"] == "Suspended") $status = "suspended";
                    elseif($row["status"] == "Completed") $status = "active";
                    elseif($row["status"] == "Active") $status = "active";

                    $cdate                          = $row["regdate"]." 00:00:00";
                    if(substr($cdate,0,4) == "0000") $cdate = \DateManager::ata();
                    $renewaldate                    = $cdate;
                    $duedate                        = $row["nextduedate"]." 00:00:00";
                    if(substr($duedate,0,4) == "0000") $duedate = \DateManager::ata();

                    $pmethod                       = $this->find_module_name("Payment",$row["paymentmethod"]);
                    if($pmethod == "Stripe" && $sub_id) $pmethod = "StripeCheckout";


                    $item                   = [
                        'owner_id'          => $row["hostingid"],
                        'invoice_id'        => $invoice_id,
                        'subscription_id'   => $sub_id,
                        'addon_id'          => $addon_id,
                        'addon_name'        => $addon["lang"][$user["data"]["lang"]]["name"],
                        'pmethod'           => $pmethod,
                        'period'            => $period,
                        'period_time'       => $period_time,
                        'amount'            => $amount,
                        'cid'               => $amount_cid,
                        'status'            => $status,
                        'cdate'             => $cdate,
                        'renewaldate'       => $renewaldate,
                        'duedate'           => $duedate,
                        'unread'            => 1,
                    ];
                    $rel                    = "Addon|".$id;
                    $result["orders"][$rel] = $item;
                }
            }
        }
        private function pull_data_part4(&$result,&$collation):void
        {
            // Fetch Invoices

            $taxation_type = 'exclusive';

            $get_taxation = $this->db->select("value")->from("tblconfiguration")->where("setting","=","TaxType");
            $get_taxation = $get_taxation->build() ? $get_taxation->getObject()->value : false;

            if($get_taxation == "Inclusive") $taxation_type = "inclusive";

            $invoices   = $this->db->select()->from("tblinvoices")->order_by("id ASC")->build() ? $this->db->fetch_assoc() : false;
            if($invoices){
                foreach($invoices AS $row){
                    $id             = $row["id"];
                    $number         = $row["invoicenum"];

                    if(!$number) $number = $id;
                    $user_id        = $row["userid"];
                    if(!isset($result["users"][$user_id])) continue;
                    $cdate          = $row["date"]." 00:00:00";
                    $duedate        = $row["duedate"]." 00:00:00";
                    $duedate        = substr($duedate,0,4) == "0000" ? \DateManager::ata() : $duedate;
                    $datepaid       = substr($row["datepaid"],0,4) != "0000" ? $row["datepaid"] : \DateManager::ata();
                    $refund_date    = substr($row["date_refunded"],0,4) != "0000" ? $row["date_refunded"] : \DateManager::ata();
                    if(\Validation::isEmpty($refund_date)) $refund_date = \DateManager::ata();

                    $status         = $row["status"];
                    if($status == "Cancelled") $status = "cancelled";
                    elseif($status == "Draft") continue;
                    elseif($status == "Paid") $status = "paid";
                    elseif($status == "Payment Pending") $status = "waiting";
                    elseif($status == "Refunded") $status = "refund";
                    elseif($status == "Unpaid") $status = "unpaid";
                    else $status = "cancelled";
                    $pmethod        = $this->find_module_name("Payment",$row["paymentmethod"]);
                    $tax_rate               = $row["taxrate"] ?? 0;
                    $tax_rate2              = $row["taxrate2"] ?? 0;
                    if($tax_rate2 > 0.00) $tax_rate += $tax_rate2;

                    $subtotal       = 0;
                    $tax            = $row["tax"];
                    $tax2           = $row["tax2"] ?? 0;
                    if($tax2 > 0.00) $tax += $tax2;

                    $user                   = $result["users"][$user_id];
                    $country_cc             = \AddressManager::get_cc_with_id($user["data"]["country"]);

                    $user_data              = [
                        'id'                => $user_id,
                        'email'             => $user["data"]["email"],
                        'currency'          => $user["data"]["currency"],
                        'name'              => $user["data"]["name"],
                        'surname'           => $user["data"]["surname"],
                        'full_name'         => $user["data"]["full_name"],
                        'lang'              => $user["data"]["lang"],
                        'taxation'          => NULL,
                        'gsm_cc'            => isset($user["info"]["gsm_cc"]) ? $user["info"]["gsm_cc"] : NULL,
                        'gsm'               => isset($user["info"]["gsm"]) ? $user["info"]["gsm"] : NULL,
                        'landline_phone'    => isset($user["info"]["landline_phone"]) ? $user["info"]["landline_phone"] : NULL,
                        'identity'          => isset($user["info"]["identity"]) ? $user["info"]["identity"] : NULL,
                        'kind'              => isset($user["info"]["kind"]) ? $user["info"]["kind"] : NULL,
                        'company_name'      => isset($user["info"]["company_name"]) ? $user["info"]["company_name"] : NULL,
                        'company_tax_number' => isset($user["info"]["company_tax_number"]) ? $user["info"]["company_tax_number"] : NULL,
                        'company_tax_office' => isset($user["info"]["company_tax_office"]) ? $user["info"]["company_tax_office"] : NULL,
                        'address'            => [
                            'id'             => 0,
                            'country_id'     => $user["data"]["country"],
                            'city'           => isset($user["addresses"][0]) ? $user["addresses"][0]["city"] : '',
                            'counti'         => isset($user["addresses"][0]) ? $user["addresses"][0]["counti"] : '',
                            'address'        => isset($user["addresses"][0]) ? $user["addresses"][0]["address"] : ___("needs/unknown"),
                            'zipcode'       => isset($user["addresses"][0]) ? $user["addresses"][0]["zipcode"] : '',
                            'detouse'       => 1,
                            'country_code'  => strtoupper($country_cc),
                            'country_name'  => \AddressManager::get_country_name($country_cc,$user["data"]["lang"]),
                        ],
                    ];

                    $discount_amount         = 0;
                    $discounts               = [];
                    $items                   = [];
                    $taxed                   = $status == "unpaid" ? 0 : 1;


                    $get_items               = $this->db->select()->from("tblinvoiceitems")->where("invoiceid","=",$id)->order_by("id ASC");
                    $get_items              = $get_items->build() ? $get_items->fetch_assoc() : [];
                    if($get_items){
                        foreach($get_items AS $item){
                            $item_type      = $item["type"];
                            $rel_id         = $item["relid"];
                            $item_options   = [];

                            if($item_type == "Domain"){

                                if(isset($result["orders"]["DomainRegister|".$rel_id]))
                                    $order = $result["orders"]["DomainRegister|".$rel_id];
                                elseif(isset($result["orders"]["DomainTransfer|".$rel_id]))
                                    $order = $result["orders"]["DomainTransfer|".$rel_id];

                                $item_options['event']  = "RenewalDomain";
                                $item_options['event_data']['year']  = $order["period_time"];

                            }
                            elseif($item_type == "Hosting"){

                                if(isset($collation["order_invoices"]["Hosting"][$rel_id]))
                                    if(sizeof($collation["order_invoices"]["Hosting"][$rel_id])>=1)
                                        $item_options['event']  = "ExtendOrderPeriod";

                                if(!(isset($collation["order_invoices"]["Hosting"][$rel_id]) && in_array($id,$collation["order_invoices"]["Hosting"][$rel_id]))) $collation["order_invoices"]["Hosting"][$rel_id][] = $id;

                            }
                            elseif($item_type == "Addon"){

                                if(isset($collation["order_invoices"]["Addon"][$rel_id])){
                                    if(sizeof($collation["order_invoices"]["Addon"][$rel_id])>=1){
                                        $item_options['event']  = "ExtendAddonPeriod";
                                        $item_options['event_data']['addon_id']  = $rel_id;
                                    }
                                }

                                if(!(isset($collation["order_invoices"]["Addon"][$rel_id]) && in_array($id,$collation["order_invoices"]["Addon"][$rel_id]))) $collation["order_invoices"]["Addon"][$rel_id][] = $id;

                            }

                            $discount           = false;

                            if($item["amount"] < 0.00)
                            {
                                $discount = true;
                                $item["amount"] = substr($item["amount"],1);
                            }

                            if($taxation_type == "inclusive")
                            {
                                $item_tax_inclusive = \Money::get_inclusive_tax_amount($item["amount"], $tax_rate);
                                $item["amount"] -= $item_tax_inclusive;
                            }
                            else
                                $item_tax_inclusive = 0;



                            $format_amount  = \Money::formatter_symbol($item["amount"],$user["data"]["currency"]);

                            if($discount || $item_type == "PromoDomain" || $item_type == "PromoHosting")
                            {
                                $discount_amount += $item["amount"];
                                $discounts["items"]["coupon"][] = [
                                    'id'        => 0,
                                    'name'      => $item["description"],
                                    'rate'      => 0,
                                    'dvalue'    => $format_amount,
                                    'amount'    => $format_amount,
                                    'amountd'   => $item["amount"],
                                ];
                                continue;
                            }

                            if($item_type == "Domain"){
                                if(isset($result["orders"]["DomainRegister|".$rel_id]["invoice_id"]))
                                    $result["orders"]["DomainRegister|".$rel_id]["invoice_id"] = $id;
                                elseif(isset($result["orders"]["DomainTransfer|".$rel_id]["invoice_id"]))
                                    $result["orders"]["DomainTransfer|".$rel_id]["invoice_id"] = $id;
                            }
                            else
                            {
                                if(isset($result["orders"][$item_type."|".$rel_id]["invoice_id"]))
                                    $result["orders"][$item_type."|".$rel_id]["invoice_id"] = $id;
                            }

                            if(!$item["taxed"]) $taxed = 0;

                            $items[] = [
                                'owner_id'      => $id,
                                'user_id'       => $user_id,
                                'user_pid'      => $item_type."|".$rel_id,
                                'options'       => $item_options,
                                'oduedate'      => $duedate,
                                'description'   => $item["description"],
                                'quantity'      => 1,
                                'amount'        => $item["amount"],
                                'total_amount'  => $item["amount"],
                                'currency'      => $user["data"]["currency"],
                            ];
                            $subtotal += $item["amount"] ?? 0;
                        }
                    }

                    $legal                  = $tax > 0 ? 1 : 0;


                    $total                  = (float) $subtotal + (float) $tax;
                    if($discount_amount > 0.00) $total -= $discount_amount;

                    $result["invoices"][$id] = [
                        'data' => [
                            'id'            => $id,
                            'number'        => $number,
                            'user_id'       => $user_id,
                            'user_data'     => $user_data,
                            'cdate'         => $cdate,
                            'duedate'       => $duedate,
                            'datepaid'      => $datepaid,
                            'refunddate'    => $refund_date,
                            'local'         => $this->local_country_id == $user["data"]["country"] ? 1 : 0,
                            'legal'         => $legal,
                            'taxed'         => $taxed,
                            'status'        => $status,
                            'currency'      => $user["data"]["currency"],
                            'taxrate'       => $tax_rate,
                            'tax'           => $tax,
                            'subtotal'      => $subtotal,
                            'total'         => (string) $total,
                            'pmethod'       => $pmethod,
                            'unread'        => 1,
                            'discounts'     => \Utility::jencode($discounts),
                            'taxation_type' => $taxation_type,
                        ],
                        'items'             => $items,
                    ];
                    $result["count"]["invoices"] +=1;
                }
            }
            
        }
        private function pull_data_part5(&$result,&$collation):void
        {
            // Fetch Knowledgebase Categories
            $kbase_categories    = $this->db->select()->from("tblknowledgebasecats");
            $kbase_categories->where("language","=",'');
            $kbase_categories->order_by("id ASC");
            $kbase_categories   = $kbase_categories->build() ? $this->db->fetch_assoc() : [];
            if($kbase_categories){
                foreach($kbase_categories AS $row){
                    $route              = \Filter::permalink($row["name"]);
                    if(isset($collation["categories_routes"]))
                        if(in_array($route,$collation["categories_routes"])) $route .= "-k".$row["id"];

                    $result["categories"]["knowledgebase"][$row["id"]] = [
                        'data' => [
                            'parent' => $row["parentid"],
                            'type' => "knowledgebase",
                            'ctime' => \DateManager::Now(),
                            'visibility' => $row["hidden"]=="on" ? "invisible" : "visible",
                        ],
                        'lang' => [],
                    ];

                    foreach($this->lang_list AS $l)
                    {
                        $lk = $l["key"];
                        $result["categories"]["knowledgebase"][$row["id"]]['lang'][$lk] = [
                            'owner_id' => $row["id"],
                            'title' => $row["name"],
                            'route' => $route,
                            'content' => $row["description"],
                        ];
                    }

                    $collation["categories_routes"][] = $route;
                }
            }


            // Fetch Knowledgebase Articles
            $kbase_articles    = $this->db->select()->from("tblknowledgebase");
            $kbase_articles->where("language","=",'');
            $kbase_articles->order_by("id ASC");
            $kbase_articles     = $kbase_articles->build() ? $this->db->fetch_assoc() : [];
            if($kbase_articles){
                foreach($kbase_articles AS $row){
                    $catid          = $this->db->select("categoryid")->from("tblknowledgebaselinks")->where("articleid","=",$row["id"]);
                    $catid          = $catid->build() ? $catid->getObject()->categoryid : 0;

                    $tags           = $this->db->select("tag")->from("tblknowledgebasetags")->where("articleid","=",$row["id"]);
                    $tags->order_by("id ASC");
                    $tags           = $tags->build() ? $tags->fetch_assoc() : false;
                    $tag            = [];
                    if($tags) foreach($tags AS $tagrw) $tag[] = $tagrw["tag"];
                    $tag            = $tag ? implode(",",$tag) : '';
                    $result["others"]["knowledgebase"][$row["id"]] = [
                        'data' => [
                            'category'      => $catid,
                            'visit_count'   => $row["views"],
                            'useful'        => $row["useful"],
                            'useless'       => $row["votes"] > 0 ? ($row["votes"] - $row["useful"]) : 0,
                            'private'       => $row["private"] == "on" ? 1 : 0,
                            'rank'          => $row["order"],
                            'ctime'         => \DateManager::Now(),
                        ],
                        'lang' => [],
                    ];

                    foreach($this->lang_list AS $l)
                    {
                        $lk = $l["key"];
                        $result["others"]["knowledgebase"][$row["id"]]["lang"][$lk] = [
                            'owner_id'      => $row["id"],
                            'title'         => $row["title"],
                            'route'         => \Filter::permalink($row["title"]),
                            'content'       => $row["article"],
                            'tags'          => $tag,
                        ];
                    }

                }
            }


            // Fetch Tickets Departments
            $departments    = $this->db->select()->from("tblticketdepartments")->build() ? $this->db->fetch_assoc() : [];
            if($departments){
                foreach($departments AS $row){
                    $result["tickets"]["departments"][$row["id"]] = [
                        'data' => [
                            'rank' => $row["order"],
                        ],
                        'lang' => [],
                        'fields' => [],
                    ];

                    foreach($this->lang_list AS $l)
                    {
                        $lk = $l["key"];
                        $result["tickets"]["departments"][$row["id"]]["lang"][$lk] = [
                            'owner_id' => 0,
                            'name' => $row["name"],
                            'description' => $row["description"],
                        ];
                    }


                    $custom_fields                 = $this->db->select()->from("tblcustomfields");
                    $custom_fields->where("type","=","support","&&");
                    $custom_fields->where("relid","=",$row["id"]);
                    $custom_fields->order_by("sortorder ASC");
                    $custom_fields                  = $custom_fields->build() ? $custom_fields->fetch_assoc() : [];
                    if($custom_fields){
                        foreach($custom_fields AS $c_row){
                            $result["tickets"]["departments"][$row["id"]]["fields"][$c_row["id"]] = [
                                'data' => [
                                    'did'       => $row["id"],
                                    'rank'      => $c_row["sortorder"],
                                ],
                            ];

                            $c_type             = '';
                            $c_properties       = [];
                            $c_options          = [];
                            $c_lid              = 0;

                            if($c_row["fieldtype"] == "text") $c_type = "input";
                            elseif($c_row["fieldtype"] == "link") $c_type = "input";
                            elseif($c_row["fieldtype"] == "password") $c_type = "input";
                            elseif($c_row["fieldtype"] == "dropdown") $c_type = "select";
                            elseif($c_row["fieldtype"] == "tickbox") $c_type = "checkbox";
                            elseif($c_row["fieldtype"] == "textarea") $c_type = "textarea";

                            if($c_row["required"] == "on") $c_properties["compulsory"] = 1;
                            if($c_row["fieldoptions"]){
                                $c_get_options = explode(",",$c_row["fieldoptions"]);
                                if($c_get_options){
                                    foreach($c_get_options AS $c_opt){
                                        $c_lid++;
                                        $c_options[] = [
                                            'id' => $c_lid,
                                            'name' => $c_opt,
                                        ];
                                    }
                                }
                            }
                            foreach(\Bootstrap::$lang->rank_list("all") AS $l_row){
                                $result["tickets"]["departments"][$row["id"]]["fields"][$c_row["id"]]["lang"][$l_row["key"]] = [
                                    'name'          => $c_row["fieldname"],
                                    'description'   => $c_row["description"],
                                    'type'          => $c_type,
                                    'properties'    => $c_properties ? \Utility::jencode($c_properties) : '',
                                    'options'       => $c_options ? \Utility::jencode($c_options) : '',
                                    'lid'           => $c_lid,
                                ];
                            }
                        }
                    }
                }
            }

            // Fetch Tickets Predefined Replies Categories
            $predefined_categories    = $this->db->select()->from("tblticketpredefinedcats")->build() ? $this->db->fetch_assoc() : [];
            if($predefined_categories){
                foreach($predefined_categories AS $row){
                    $result["categories"]["predefined_replies"][$row["id"]] = [
                        'data' => [
                            'parent' => $row["parentid"],
                            'type' => "predefined_replies",
                            'ctime' => \DateManager::Now(),
                        ],
                        'lang' => [],
                    ];
                    foreach($this->lang_list AS $l)
                    {
                        $lk = $l["key"];
                        $result["categories"]["predefined_replies"][$row["id"]]["lang"][$lk] = [
                            'owner_id' => $row["id"],
                            'title' => $row["name"],
                        ];
                    }
                }
            }

            // Fetch Predefined Replies
            $predefined_replies    = $this->db->select()->from("tblticketpredefinedreplies")->build() ? $this->db->fetch_assoc() : [];
            if($predefined_replies){
                foreach($predefined_replies AS $row){
                    $result["tickets"]["predefined_replies"][$row["id"]] = [
                        'data' => [
                            'category' => $row["catid"],
                        ],
                        'lang' => [],
                    ];

                    foreach($this->lang_list AS $l)
                    {
                        $lk = $l["key"];
                        $result["tickets"]["predefined_replies"][$row["id"]]['lang'][$lk] = [
                            'owner_id' => $row["id"],
                            'name' => $row["name"],
                            'message' => nl2br(\Utility::text_replace($row["reply"],[
                                '[NAME]' => "{FULL_NAME}",
                                '[FIRSTNAME]' => "{NAME}",
                                '[EMAIL]' => "{EMAIL}",
                            ])),
                        ];
                    }
                }
            }

            // Fetch Tickets
            $tickets    = $this->db->select()->from("tbltickets")->build() ? $this->db->fetch_assoc() : [];
            if($tickets){
                foreach($tickets AS $row){
                    $id             = $row["id"];
                    $user_id        = $row["userid"];
                    if(!isset($result["users"][$user_id])) continue;
                    if(isset($row["merged_ticket_id"]) && $row["merged_ticket_id"]) continue;
                    $user           = $result["users"][$user_id];
                    $ctime          = $row["date"];
                    $lastreply      = $row["lastreply"];
                    $userunread     = $row["clientunread"];
                    $adminunread    = 1;
                    $title          = $row["title"];
                    $did            = $row["did"];
                    $status         = "waiting";
                    $priority       = 1;

                    if($row["urgency"] == "Medium") $priority = 2;
                    elseif($row["urgency"] == "High") $priority = 3;

                    if($row["status"] == "Open") $status = "waiting";
                    elseif($row["status"] == "Customer-Reply") $status = "waiting";
                    elseif($row["status"] == "On Hold") $status = "inprocess";
                    elseif($row["status"] == "Answered") $status = "replied";
                    if($row["status"] == "Closed") $status = "solved";


                    $replies        = [];

                    $getReplies     = $this->db->select()->from("tblticketreplies")->where("tid","=",$id)->order_by("id ASC")->build() ? $this->db->fetch_assoc() : [];

                    array_unshift($getReplies,[
                        'attachment'    => $row["attachment"],
                        'userid'        => $row["userid"],
                        'name'          => $row["name"],
                        'admin'         => $row["admin"],
                        'message'       => $row["message"],
                        'date'          => $row["date"],
                    ]);

                    if($getReplies){
                        foreach($getReplies AS $reply){
                            if($reply["attachment"]){
                                $split = explode("|",$reply["attachment"]);
                                if($split){
                                    foreach($split AS $sp){
                                        $attachments =  [
                                            [
                                                'ticket_id' => $id,
                                                'reply_id' => 0,
                                                'name' => $sp,
                                                'file_name' => $sp,
                                                'file_path' => $sp,
                                            ],
                                        ];
                                    }
                                }
                            }else $attachments = [];

                            $replies[] = [
                                'user_id'       => $reply["userid"] ? $reply["userid"] : $user_id,
                                'owner_id'      => $id,
                                'admin'         => $reply["userid"] ? 0 : 1,
                                'name'          => $reply["userid"] ? $user["data"]["full_name"] : $reply["admin"],
                                'message'       => $reply["message"],
                                'ctime'         => $reply["date"],
                                'ip'            => "0.0.0.0",
                                'attachments'   => $attachments,
                            ];

                        }
                    }

                    $item           = [
                        'data'      => [
                            'did'       => $did,
                            'user_id'   => $user_id,
                            'status'    => $status,
                            'priority'  => $priority,
                            'title'     => $title,
                            'ctime'     => $ctime,
                            'lastreply' => $lastreply,
                            'userunread' => $userunread,
                            'adminunread' => $adminunread,
                        ],
                        'replies' => $replies,
                        'custom_fields' => [],
                    ];

                    $fields = [];
                    if(isset($result["tickets"]["departments"][$did]["fields"]))
                        $fields = $result["tickets"]["departments"][$did]["fields"];

                    if($fields){
                        foreach($fields AS $f_id=>$f_row){
                            $field_values = $this->db->select()->from("tblcustomfieldsvalues");
                            $field_values->where("fieldid","=",$f_id,"&&");
                            $field_values->where("relid","=",$id);
                            $field_values = $field_values->build() ? $field_values->fetch_assoc() : '';
                            if($field_values)
                                foreach($field_values AS $fv_row)
                                    $item["custom_fields"][$f_id] = $fv_row["value"];
                        }
                    }


                    $result["tickets"]["requests"][$id] = $item;


                    $result["count"]["tickets"] +=1;

                }
            }
        }

        // Import Processes
        public function call_start_import()
        {
            if(DEMO_MODE)
                die(\Utility::jencode([
                    'status' => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            try {
                $this->set_db_credentials();
            }
            catch (\Exception $e)
            {
                die(\Utility::jencode([
                    'status' => "error",
                    'message' => $e->getMessage(),
                ]));
            }

            \Helper::Load(["Money","Products","Orders","Invoices","Tickets"]);


            $part           = (int) \Filter::init("POST/part","numbers");

            $collation_file = ROOT_DIR."temp".DS.$this->file_hash."-collation.php";
            $result_file    = ROOT_DIR."temp".DS.$this->file_hash."-data.php";


            if(!file_exists($result_file))
                die(\Utility::jencode([
                    'status' => "error",
                    'message' => $result_file." file not found",
                ]));

            $result     = include $result_file;

            $collation = [];
            if(file_exists($collation_file)) $collation = include $collation_file;


            $product_categories_cx      = (int) \Filter::init("POST/product_categories","rnumbers");
            $products_cx                = (int) \Filter::init("POST/products","rnumbers");
            $users_cx                   = (int) \Filter::init("POST/users","rnumbers");
            $orders_cx                  = (int) \Filter::init("POST/orders","rnumbers");
            $invoices_cx                = (int) \Filter::init("POST/invoices","rnumbers");
            $tickets_cx                 = (int) \Filter::init("POST/tickets","rnumbers");
            $backup_taken               = (int) \Filter::init("POST/backup_taken","rnumbers");

            if($invoices_cx) $users_cx = 1;
            if($tickets_cx) $users_cx = 1;
            if($products_cx) $product_categories_cx = 1;
            if($orders_cx)
            {
                $products_cx = 1;
                $product_categories_cx = 1;
                $users_cx = 1;
            }

            $this->cx["product_categories"]     = $product_categories_cx;
            $this->cx["products"]               = $products_cx;
            $this->cx["users"]                  = $users_cx;
            $this->cx["orders"]                 = $orders_cx;
            $this->cx["invoices"]               = $invoices_cx;
            $this->cx["tickets"]                = $tickets_cx;
            $this->cx["backup_taken"]           = $backup_taken;


            if($part == 0) $part = 1;
            elseif($part == 1)  $apply = $this->start_import_part1();
            elseif($part == 2)  $apply = $this->start_import_part2($result,$collation);
            elseif($part == 3)  $apply = $this->start_import_part3($result,$collation);
            elseif($part == 4)  $apply = $this->start_import_part4($result,$collation);
            elseif($part == 5)  $apply = $this->start_import_part5($result,$collation);
            elseif($part == 6)  $apply = $this->start_import_part6($result,$collation);
            else $part = "done";

            if($part == "done")
            {

                \FileManager::file_delete(ROOT_DIR."temp".DS.$this->file_hash."-data.php");
                \FileManager::file_delete(ROOT_DIR."temp".DS.$this->file_hash."-collation.php");
            }
            else
            {
                $part = $part+1;

                if(!is_bool($apply) && is_int($apply))
                    $part = $apply;
                elseif(!is_bool($apply))
                    die(\Utility::jencode([
                        'status' => "error",
                        'message' => $apply,
                    ]));



                \FileManager::file_write($result_file,\Utility::array_export($result,['pwith' => true]));
                \FileManager::file_write($collation_file,\Utility::array_export($collation,['pwith' => true]));

                echo \Utility::jencode([
                    'status' => "next-part",
                    'part' => $part,
                ]);

                return true;
            }

            $admin_data  = \UserManager::LoginData("admin");
            \User::addAction($admin_data["id"],"alteration","imported-from-another-software",['name' => "WHMCS"]);
            echo \Utility::jencode(['status' => "successful"]);
            return true;
        }
        private function start_import_part1()
        {
            if(!$this->cx["backup_taken"])
            {
                $backup = $this->controller->backup_database("import");
                if($backup["status"] == "error") return $backup["message"];
            }
            return true;
        }
        private function start_import_part2(&$result,&$collation)
        {
            if(!$this->test)
            {

                // Announcements data

                if($this->cx["users"] && isset($result["others"]["announcements"]) && $result["others"]["announcements"])
                {
                    foreach($result["others"]["announcements"] AS $old_id => $row)
                    {
                        $data       = $row["data"];
                        $languages  = $row["lang"];

                        $page = \WDB::insert("pages",$data);
                        if($page){
                            $page = \WDB::lastID();
                            $collation["others"]["announcements"][] = $page;
                            if($languages){
                                foreach($languages AS $l_key=>$language){
                                    $language["owner_id"]   = $page;
                                    $language["lang"]       = $l_key;
                                    \WDB::insert("pages_lang",$language);
                                }
                            }
                        }
                    }
                }

                // Products Categories Process
                if($this->cx["product_categories"] && isset($result["categories"]["products"]) && $result["categories"]["products"])
                {
                    $categories = $result["categories"]["products"];
                    $spgroup    = $categories["special-group"];
                    unset($categories["special-group"]);
                    array_unshift($categories,$spgroup);
                    foreach($categories AS $old_id=>$row){
                        if($old_id == 0) $old_id = "special-group";

                        $data       = $row["data"] ?? false;
                        $languages  = $row["lang"] ?? false;

                        if(!$data) continue;

                        if(isset($data["parent"]) && $data["parent"])
                            if(isset($collation["categories"]["products"][$data["parent"]]))
                                $data["parent"] = $collation["categories"]["products"][$data["parent"]];

                        if(isset($data["kind_id"]) && $data["kind_id"])
                            if(isset($collation["categories"]["products"][$data["kind_id"]]))
                                $data["kind_id"] = $collation["categories"]["products"][$data["kind_id"]];


                        $category   = \WDB::insert("categories",$data);
                        if($category){
                            $category   = \WDB::lastID();
                            $collation["categories"]["products"][$old_id] = $category;
                            foreach($languages AS $key=>$lang){
                                if(!\Filter::permalink_check($lang["route"])) $lang["route"] = $category;
                                $lang["owner_id"] = $category;
                                $lang["lang"] = $key;
                                \WDB::insert("categories_lang",$lang);
                            }
                        }
                    }
                }

                // Knowledgebase Categories Process
                if($this->cx["tickets"] && isset($result["categories"]["knowledgebase"]) && $result["categories"]["knowledgebase"]){
                    $categories = $result["categories"]["knowledgebase"];
                    foreach($categories AS $old_id=>$row){
                        $data       = $row["data"] ?? false;
                        $languages  = $row["lang"] ?? false;

                        if(!$data) continue;

                        if(isset($data["parent"]) && $data["parent"])
                            if(isset($collation["categories"]["knowledgebase"][$data["parent"]]))
                                $data["parent"] = $collation["categories"]["knowledgebase"][$data["parent"]];

                        $category   = \WDB::insert("categories",$data);
                        if($category){
                            $category   = \WDB::lastID();
                            $collation["categories"]["knowledgebase"][$old_id] = $category;
                            foreach($languages AS $key=>$lang){
                                if(!\Filter::permalink_check($lang["route"])) $lang["route"] = $category;
                                $lang["owner_id"] = $category;
                                $lang["lang"] = $key;
                                \WDB::insert("categories_lang",$lang);
                            }
                        }
                    }
                }

                // Predefined Replies Categories Process
                if($this->cx["tickets"] && isset($result["categories"]["predefined_replies"]) && $result["categories"]["predefined_replies"])
                {
                    $categories = $result["categories"]["predefined_replies"];
                    foreach($categories AS $old_id=>$row){
                        $data       = $row["data"] ?? false;
                        $languages  = $row["lang"] ?? false;

                        if(!$data) continue;

                        if(isset($data["parent"]) && $data["parent"])
                            if(isset($collation["categories"]["predefined_replies"][$data["parent"]]))
                                $data["parent"] = $collation["categories"]["predefined_replies"][$data["parent"]];

                        $category   = \WDB::insert("categories",$data);
                        if($category){
                            $category   = \WDB::lastID();
                            $collation["categories"]["predefined_replies"][$old_id] = $category;
                            foreach($languages AS $key=>$lang){
                                $lang["owner_id"] = $category;
                                $lang["lang"] = $key;
                                \WDB::insert("categories_lang",$lang);
                            }
                        }
                    }
                }

                // Requirement Categories Process
                if($this->cx["products"] && isset($result["categories"]["requirement"]) && $result["categories"]["requirement"]){
                    $categories = $result["categories"]["requirement"];
                    foreach($categories AS $old_id=>$row){
                        $data       = $row["data"] ?? false;
                        $languages  = $row["lang"] ?? false;

                        if(!$data) continue;

                        if(isset($data["parent"]) && $data["parent"])
                            if(isset($collation["categories"]["requirement"][$data["parent"]]))
                                $data["parent"] = $collation["categories"]["requirement"][$data["parent"]];

                        $category   = \WDB::insert("categories",$data);
                        if($category){
                            $category   = \WDB::lastID();
                            $collation["categories"]["requirement"][$old_id] = $category;
                            foreach($languages AS $key=>$lang){
                                $lang["owner_id"] = $category;
                                $lang["lang"] = $key;
                                \WDB::insert("categories_lang",$lang);
                            }
                        }
                    }
                }

                // Addon Categories Process
                if($this->cx["products"] && isset($result["categories"]["addon"]) && $result["categories"]["addon"]){
                    $categories = $result["categories"]["addon"];
                    foreach($categories AS $old_id=>$row){
                        $data       = $row["data"] ?? false;
                        $languages  = $row["lang"] ?? false;

                        if(!$data) continue;

                        if(isset($data["parent"]) && $data["parent"])
                            if(isset($collation["categories"]["addon"][$data["parent"]]))
                                $data["parent"] = $collation["categories"]["addon"][$data["parent"]];

                        $category   = \WDB::insert("categories",$data);
                        if($category){
                            $category   = \WDB::lastID();
                            $collation["categories"]["addon"][$old_id] = $category;
                            foreach($languages AS $key=>$lang){
                                $lang["owner_id"] = $category;
                                $lang["lang"] = $key;
                                \WDB::insert("categories_lang",$lang);
                            }
                        }
                    }
                }

                // Shared Servers Process
                if($this->cx["products"] && isset($result["others"]["shared_servers"]) && $result["others"]["shared_servers"]){
                    $servers = $result["others"]["shared_servers"];
                    foreach($servers AS $old_id=>$row){
                        $server = \WDB::insert("servers",$row);
                        if($server){
                            $server = \WDB::lastID();
                            $collation["others"]["shared_servers"][$old_id] = $server;
                        }
                    }
                }

                // Shared Server Groups Process
                if($this->cx["products"] && isset($result["others"]["shared_server_groups"]) && $result["others"]["shared_server_groups"])
                {
                    $server_groups = $result["others"]["shared_server_groups"];
                    foreach($server_groups AS $old_id=>$row){
                        if($row['servers'])
                        {
                            $new_ids = [];
                            foreach($row["servers"] AS $sr)
                            {
                                if(isset($collation["others"]["shared_servers"][$sr]))
                                    $new_ids[] = $collation["others"]["shared_servers"][$sr];
                            }
                            $row['servers'] = $new_ids ? implode(',',$new_ids) : '';
                        }
                        else
                            $row['servers'] = '';

                        $group = \WDB::insert("servers_groups",$row);
                        if($group){
                            $group = \WDB::lastID();
                            $collation["others"]["shared_server_groups"][$old_id] = $group;
                        }
                    }
                }

                // Product Requirements Process
                if($this->cx["products"] && isset($result["product_requirements"]) && $result["product_requirements"]){
                    $rows   = $result["product_requirements"];
                    foreach($rows AS $old_id=>$row){

                        $data       = $row["data"] ?? false;
                        $languages  = $row["lang"] ?? false;

                        if(!$data) continue;

                        if($data["mcategory"] == "special" && isset($collation["categories"]["products"]["special-group"]))
                            $data["mcategory"] = "special_".$collation["categories"]["products"]["special-group"];

                        if(isset($collation["categories"]["requirement"][$data["category"]]))
                            $data["category"] = $collation["categories"]["requirement"][$data["category"]];

                        $requirement = \WDB::insert("products_requirements",$data);
                        if($requirement){
                            $requirement = \WDB::lastID();
                            $collation["product_requirements"][$old_id] = $requirement;
                            if($languages){
                                foreach($languages AS $key=>$lang){
                                    $lang["owner_id"] = $requirement;
                                    $lang["lang"] = $key;
                                    \WDB::insert("products_requirements_lang",$lang);
                                }
                            }
                        }
                    }
                }

                // Product Addons Process
                if($this->cx["products"] && isset($result["product_addons"]) && $result["product_addons"]){
                    $rows   = $result["product_addons"];
                    foreach($rows AS $old_id=>$row){

                        $data       = $row["data"] ?? false;
                        $languages  = $row["lang"] ?? false;

                        if(!$data) continue;

                        if($data["mcategory"] == "special" && isset($collation["categories"]["products"]["special-group"]))
                            $data["mcategory"] = "special_".$collation["categories"]["products"]["special-group"];

                        if(isset($collation["categories"]["addon"][$data["category"]]))
                            $data["category"] = $collation["categories"]["addon"][$data["category"]];

                        $addon = \WDB::insert("products_addons",$data);
                        if($addon){
                            $addon = \WDB::lastID();
                            $collation["product_addons"][$old_id] = $addon;
                            if($languages){
                                foreach($languages AS $key=>$lang){
                                    $lang["owner_id"] = $addon;
                                    $lang["lang"] = $key;
                                    \WDB::insert("products_addons_lang",$lang);
                                }
                            }
                        }
                    }
                }

                // Products Process
                if($this->cx["products"] && isset($result["products"]) && $products = $result["products"]){
                    foreach($products AS $old_id=>$row){
                        $data           = $row["data"];
                        $languages      = $row["lang"];
                        $prices         = isset($row["prices"]) ? $row["prices"] : [];

                        $requirements   = [];
                        $addons         = [];

                        if(isset($data["requirements"]) && $data["requirements"]){
                            foreach($data["requirements"] AS $requirement)
                                if(isset($collation["product_requirements"][$requirement]))
                                    $requirements[] = $collation["product_requirements"][$requirement];
                            $data["requirements"] = $requirements ? implode(",",$requirements) : '';
                        }

                        if(isset($data["addons"]) && $data["addons"]){
                            foreach($data["addons"] AS $addon)
                                if(isset($collation["product_addons"][$addon]))
                                    $addons[] = $collation["product_addons"][$addon];
                            $data["addons"] = $addons ? implode(",",$addons) : '';
                        }

                        if($data["type_id"] && isset($collation["categories"]["products"][$data["type_id"]]))
                            $data["type_id"] = $collation["categories"]["products"][$data["type_id"]];

                        if($data["category"] && isset($collation["categories"]["products"][$data["category"]]))
                            $data["category"] = $collation["categories"]["products"][$data["category"]];
                        else $data["category"] = 0;


                        if(isset($data["module_data"]["server_id"]) || isset($data["module_data"]["server_group_id"]))
                        {
                            $server_id = $data["module_data"]["server_id"];
                            $server_group_id = $data["module_data"]["server_group_id"];


                            if(isset($collation["others"]["shared_servers"][$server_id]))
                                $data["module_data"]["server_id"] = $collation["others"]["shared_servers"][$server_id];
                            else
                                unset($data["module_data"]["server_id"]);

                            if(isset($collation["others"]["shared_server_groups"][$server_group_id]))
                                $data["module_data"]["server_id"] = $collation["others"]["shared_server_groups"][$server_group_id];
                            else
                                unset($data["module_data"]["server_group_id"]);

                        }

                        if(isset($data["options"]["server_id"]) || isset($data["options"]["server_group_id"]))
                        {
                            $server_id          = $data["options"]["server_id"];
                            $server_group_id    = $data["options"]["server_group_id"];

                            if(isset($collation["others"]["shared_servers"][$server_id]))
                                $data["module_data"]["server_id"] = $collation["others"]["shared_servers"][$server_id];
                            else
                                unset($data["options"]["server_id"]);

                            if(isset($collation["others"]["shared_server_groups"][$server_group_id]))
                                $data["module_data"]["server_group_id"] = $collation["others"]["shared_server_groups"][$server_group_id];
                            else
                                unset($data["options"]["server_group_id"]);

                        }

                        $data["options"]        = \Utility::jencode($data["options"]);
                        $data["module_data"]    = \Utility::jencode($data["module_data"]);

                        $product                = \WDB::insert("products",$data);

                        if($product){
                            $product            = \WDB::lastID();
                            $collation["products"][$old_id] = $product;
                            if($languages){
                                foreach($languages AS $key=>$lang){
                                    $lang["owner_id"] = $product;
                                    $lang["lang"] = $key;
                                    \WDB::insert("products_lang",$lang);
                                }
                            }

                            if($prices){
                                foreach($prices AS $price){
                                    $price["owner_id"] = $product;
                                    \WDB::insert("prices",$price);
                                }
                            }

                        }
                    }
                }
            }
            else sleep(3);
            
            return true;
        }
        private function start_import_part3(&$result,&$collation)
        {
            $return                     = true;

            if(!$this->test)
            {
                // User Groups Process
                if($this->cx["users"] && !isset($collation["others"]["user_groups"]))
                {
                    if(isset($result["others"]["user_groups"]) && $user_groups = $result["others"]["user_groups"])
                    {
                        foreach($user_groups AS $old_id=>$row)
                        {
                            $group = \WDB::insert("users_groups",$row);
                            if($group)
                            {
                                $group = \WDB::lastID();
                                $collation["others"]["user_groups"][$old_id] = $group;
                            }
                        }
                    }
                }

                // Users Process
                if($this->cx["users"] && isset($result["users"]) && $users = $result["users"])
                {
                    if($result["count"]["users"] > 999){
                        if(sizeof($result["users"]) > 999){
                            $result["users"] = array_chunk($users,1000,true);
                            $users           = $result["users"];
                        }
                        $before_part         = -1;
                        if(isset($collation["parts"]["users"]))
                            $before_part    = $collation["parts"]["users"];
                        $keys               = array_keys($users);
                        $end_part           = end($keys);
                        $next_part          = $before_part+1;
                        if($next_part != $end_part) $return = 3;
                        $users              = $result["users"][$next_part];
                        $collation["parts"]["users"] = $next_part;
                    }
                    foreach($users AS $old_id=>$row){
                        $data       = $row["data"];
                        $info       = $row["info"];
                        $addresses  = $row["addresses"] ?? [];

                        if(isset($collation["users"][$old_id])) continue;

                        if($data["group_id"] ?? 0)
                        {
                            if(isset($collation["others"]["user_groups"][$data["group_id"]]))
                                $data["group_id"] = $collation["others"]["user_groups"][$data["group_id"]];
                            else
                                $data["group_id"] = 0;
                        }

                        $check_user = \WDB::select("id")->from("users")
                            ->where("type","=","member","&&")
                            ->where("email","=",$data["email"]);
                        $check_user = $check_user->build() ? $check_user->getObject()->id : false;

                        if($check_user) $user = $check_user;
                        else
                        {
                            if(isset($data["id"])) unset($data["id"]);
                            if(\WDB::insert("users",$data))
                                $user   = \WDB::lastID();
                            else
                                $user   = false;
                        }
                        if($user){
                            $collation["users"][$old_id] = $user;
                            if(!$check_user){
                                \User::setData($user,['secure_hash' => \User::secure_hash($user)]);
                                \User::setInfo($user,$info);
                                if($addresses)
                                {
                                    foreach($addresses AS $addr)
                                    {
                                        $addr["owner_id"] = $user;
                                        if(\WDB::insert("users_addresses",$addr))
                                            \User::setInfo($user,['default_address' => \WDB::lastID()]);
                                    }
                                }
                            }
                        }
                    }
                }
            }
            else sleep(3);
            return $return;
        }
        private function start_import_part4(&$result,&$collation)
        {
            $return = true;
            if(!$this->test)
            {
                // Orders Process
                if($this->cx["orders"] && isset($result["orders"]) && $orders = $result["orders"]){
                    if($result["count"]["orders"] > 999){
                        if(sizeof($result["orders"]) > 999){
                            $result["orders"] = array_chunk($orders,1000,true);
                            $orders           = $result["orders"];
                        }
                        $before_part         = -1;
                        if(isset($collation["parts"]["orders"]))
                            $before_part    = $collation["parts"]["orders"];
                        $keys               = array_keys($orders);
                        $end_part           = end($keys);
                        $next_part          = $before_part+1;
                        if($next_part != $end_part) $return = 4;
                        $orders             = $result["orders"][$next_part];
                        $collation["parts"]["orders"] = $next_part;
                    }
                    foreach($orders AS $old_id=>$row){
                        $old_parse  = explode("|",$old_id);
                        $old_type   = $old_parse[0];

                        if($old_type == "Hosting" || $old_type == "DomainRegister" || $old_type == "DomainTransfer"){
                            if(isset($collation["users"][$row["owner_id"]])) $row["owner_id"] = $collation["users"][$row["owner_id"]];

                            if(isset($row["options"]["category_id"]) && $row["options"]["category_id"]){
                                if(isset($collation["categories"]["products"][$row["options"]["category_id"]]))
                                    $row["options"]["category_id"] = $collation["categories"]["products"][$row["options"]["category_id"]];
                                else
                                    $row["options"]["category_id"] = 0;

                            }

                            if(($row["type"] == "hosting" || $row["type"] == "server" || $row["type"] == "special") && isset($collation["products"][$row["product_id"]]))
                                $row["product_id"] = $collation["products"][$row["product_id"]];

                            if(isset($row["options"]["server_id"])){
                                $server_id = $row["options"]["server_id"];
                                if(isset($collation["others"]["shared_servers"][$server_id]))
                                    $row["options"]["server_id"] = $collation["others"]["shared_servers"][$server_id];
                                else unset($row["options"]["server_id"]);
                            }

                            if(isset($row["type_id"]) && $row["type_id"])
                            {
                                if(isset($collation["categories"]["products"][$row["type_id"]]))
                                    $row["type_id"] = $collation["categories"]["products"][$row["type_id"]];
                                else
                                    $row["type_id"] = 0;
                            }


                            $row["options"] = \Utility::jencode($row["options"]);

                            if(strlen($row["subscription_id"]) > 0)
                            {
                                $check_sub  = \Orders::get_subscription(0,$row["subscription_id"]);
                                if($check_sub)
                                    $row["subscription_id"] = $check_sub["id"];
                                else
                                    $row["subscription_id"] = \Orders::create_subscription([
                                        'user_id'           => $row["owner_id"],
                                        'module'            => $row["pmethod"] ?: 'none',
                                        'status'            => 'active',
                                        'first_paid_fee'    => $row["total_amount"],
                                        'last_paid_fee'     => $row["total_amount"],
                                        'next_payable_fee' => $row["total_amount"],
                                        'identifier'        => $row["subscription_id"],
                                        'currency'          => $row["amount_cid"],
                                        'last_paid_date'    => $row["renewaldate"],
                                        'next_payable_date' => $row["duedate"],
                                        'created_at'        => $row["renewaldate"],
                                        'updated_at'        => \DateManager::Now(),
                                    ]);
                            }
                            else
                                $row["subscription_id"] = 0;

                            $order  = \WDB::insert("users_products",$row);
                        }
                        elseif($old_type == "Addon"){
                            if(isset($collation["orders"]["Hosting|".$row["owner_id"]])) $row["owner_id"] = $collation["orders"]["Hosting|".$row["owner_id"]];

                            if(isset($row["addon_id"]) && $row["addon_id"] && isset($collation["product_addons"][$row["addon_id"]])) $row["addon_id"] = $collation["product_addons"][$row["addon_id"]];


                            if(strlen($row["subscription_id"]) > 0)
                            {
                                $check_sub  = \Orders::get_subscription(0,$row["subscription_id"]);
                                if($check_sub)
                                    $row["subscription_id"] = $check_sub["id"];
                                else
                                    $row["subscription_id"] = \Orders::create_subscription([
                                        'user_id'           => $row["owner_id"],
                                        'module'            => $row["pmethod"] ?: 'none',
                                        'status'            => 'active',
                                        'identifier'        => $row["subscription_id"],
                                        'first_paid_fee'    => $row["total_amount"],
                                        'last_paid_fee'     => $row["total_amount"],
                                        'next_payable_fee'  => $row["total_amount"],
                                        'currency'          => $row["cid"],
                                        'last_paid_date'    => $row["renewaldate"],
                                        'next_payable_date' => $row["duedate"],
                                        'created_at'        => $row["renewaldate"],
                                        'updated_at'        => \DateManager::Now(),
                                    ]);
                            }
                            else
                                $row["subscription_id"] = 0;

                            $order  = \WDB::insert("users_products_addons",$row);
                        }
                        elseif($old_type == "ConfigOption"){
                            if(isset($collation["orders"]["Hosting|".$row["owner_id"]])) $row["owner_id"] = $collation["orders"]["Hosting|".$row["owner_id"]];

                            if(strlen($row["subscription_id"]) > 0)
                            {
                                $check_sub  = \Orders::get_subscription(0,$row["subscription_id"]);
                                if($check_sub)
                                    $row["subscription_id"] = $check_sub["id"];
                                else
                                    $row["subscription_id"] = \Orders::create_subscription([
                                        'user_id'           => $row["owner_id"],
                                        'module'            => $row["pmethod"] ?: 'none',
                                        'status'            => 'active',
                                        'identifier'        => $row["subscription_id"],
                                        'first_paid_fee'    => $row["total_amount"],
                                        'last_paid_fee'     => $row["total_amount"],
                                        'next_payable_fee'  => $row["total_amount"],
                                        'currency'          => $row["cid"],
                                        'last_paid_date'    => $row["renewaldate"],
                                        'next_payable_date' => $row["duedate"],
                                        'created_at'        => $row["renewaldate"],
                                        'updated_at'        => \DateManager::Now(),
                                    ]);
                            }
                            else
                                $row["subscription_id"] = 0;

                            $order  = \WDB::insert("users_products_addons",$row);
                        }
                        else $order = false;
                        if($order){
                            $order  = \WDB::lastID();
                            $collation["orders"][$old_id] = $order;
                        }
                    }
                }

                // Requirements Process
                if($this->cx["orders"] && !isset($collation["order_requirements"]) && $return === true){
                    if(isset($result["order_requirements"]) && $requirements = $result["order_requirements"]){
                        foreach($requirements AS $row){
                            if(!isset($collation["orders"]["Hosting|".$row["owner_id"]])) continue;
                            $row["owner_id"] = $collation["orders"]["Hosting|".$row["owner_id"]];
                            $row["requirement_id"] = $collation["product_requirements"][$row["requirement_id"]];
                            \WDB::insert("users_products_requirements",$row);
                            $collation["order_requirements"][] = \WDB::lastID();
                        }
                    }
                }

                // Events Process
                if($this->cx["orders"] && !isset($collation["others"]["events"]) && $return === true){
                    if(isset($result["others"]["events"]) && $events = $result["others"]["events"]){
                        foreach($events AS $row){
                            $split_rel      = explode("|",$row["owner_id"]);
                            $rel_type       = $split_rel[0];
                            $rel_id         = $split_rel[1];

                            if($rel_type == "Domain" || $rel_type == "DomainTransfer" || $rel_type == "DomainRegister"){
                                if(isset($collation["orders"]["DomainRegister|".$rel_id]))
                                    $row["owner_id"] = $collation["orders"]["DomainRegister|".$rel_id];
                                elseif(isset($collation["orders"]["DomainTransfer|".$rel_id]))
                                    $row["owner_id"] = $collation["orders"]["DomainTransfer|".$rel_id];
                            }elseif($rel_type == "Hosting" && isset($collation["orders"]["Hosting|".$rel_id]))
                                $row["owner_id"] = $collation["orders"]["Hosting|".$rel_id];
                            elseif($rel_type == "Addon" && isset($collation["orders"]["Addon|".$rel_id]))
                                $row["owner_id"] = $collation["orders"]["Addon|".$rel_id];
                            else
                                $row["owner_id"] = 0;
                            \WDB::insert("events",$row);
                            $collation["others"]["events"][] = \WDB::lastID();
                        }
                    }
                }

                // Affiliate Process

                if($this->cx["orders"] && !isset($collation["others"]["affiliates"])  && $return === true)
                {
                    if(isset($result["others"]["affiliates"]) && $affiliates = $result["others"]["affiliates"])
                    {
                        foreach($affiliates AS $old_id => $row)
                        {
                            $data       = $row["data"];
                            $rels       = $row["relationships"];
                            $user_id    = $data["owner_id"];
                            $user_id    = $collation["users"][$user_id] ?? 0;
                            $data["owner_id"] = $user_id;

                            $data["id"] = $old_id;

                            \WDB::insert("users_affiliates",$data);
                            $collation["others"]["affiliates"][] = \WDB::lastID();


                            if(isset($rels["referrers"]) && $rels["referrers"])
                            {
                                foreach($rels["referrers"] AS $rf_id => $rf)
                                {
                                    $rf["id"] = $rf_id;
                                    \WDB::insert("users_affiliate_referrers",$rf);
                                }
                            }

                            if(isset($rels["hits"]) && $rels["hits"])
                            {
                                foreach($rels["hits"] AS $hit_id => $hit)
                                {
                                    $hit["id"] = $hit_id;
                                    \WDB::insert("users_affiliate_hits",$hit);
                                }
                            }

                            if(isset($rels["withdrawals"]) && $rels["withdrawals"])
                            {
                                foreach($rels["withdrawals"] AS $r_id => $w)
                                {
                                    $w["id"] = $r_id;
                                    \WDB::insert("users_affiliate_withdrawals",$w);
                                }
                            }

                            if(isset($rels["members"]) && $rels["members"])
                            {
                                foreach($rels["members"] AS $mid)
                                {
                                    $new_mid    = $collation["users"][$mid] ?? 0;
                                    if($new_mid)
                                        \WDB::update("users",[
                                            'aff_id' => $old_id
                                        ])->where("id","=",$new_mid)->save();
                                }
                            }

                            if(isset($rels["transactions"]) && $rels["transactions"] && is_array($rels["transactions"]))
                            {
                                foreach($rels["transactions"] AS $t_id => $t)
                                {
                                    $ord_id     = false;
                                    if(isset($collation["orders"]["Hosting|".$t["order_id"]]))
                                        $ord_id = $collation["orders"]["Hosting|".$t["order_id"]];
                                    elseif(isset($collation["orders"]["Domain|".$t["order_id"]]))
                                        $ord_id = $collation["orders"]["Domain|".$t["order_id"]];

                                    if($ord_id)
                                    {
                                        $t["order_id"] = $ord_id;
                                    }

                                    \WDB::insert("users_affiliate_transactions",$t);
                                }
                            }
                        }
                    }
                }

            }
            else sleep(3);
            return $return;
        }
        private function start_import_part5(&$result,&$collation)
        {
            $return                     = true;
            
            if(!$this->test)
            {
                \Helper::Load("Notification");
                // Invoices Process
                if($this->cx["invoices"] && isset($result["invoices"]) && $invoices = $result["invoices"]){
                    if($result["count"]["invoices"] > 999){
                        if(sizeof($result["invoices"]) > 999){
                            $result["invoices"] = array_chunk($invoices,1000,true);
                            $invoices           = $result["invoices"];
                        }
                        $before_part         = -1;
                        if(isset($collation["parts"]["invoices"]))
                            $before_part    = $collation["parts"]["invoices"];
                        $keys               = array_keys($invoices);
                        $end_part           = end($keys);
                        $next_part          = $before_part+1;
                        if($next_part != $end_part) $return = 5;
                        $invoices             = $result["invoices"][$next_part];
                        $collation["parts"]["invoices"] = $next_part;
                    }
                    foreach($invoices AS $id=>$row){
                        $data   = $row["data"];
                        $items  = $row["items"];

                        if(isset($collation["users"][$data["user_id"]]))
                            $data["user_id"] = $collation["users"][$data["user_id"]];

                        $data["user_data"]["id"] = $data["user_id"];
                        $data["user_data"] = \Utility::jencode($data["user_data"]);

                        $invoice    = \WDB::insert("invoices",$data);
                        if($invoice){
                            $invoice    = \WDB::lastID();

                            if($invoice && $items){

                                $collation["invoices"][$id] = $invoice;

                                foreach($items AS $item){
                                    $item["owner_id"] = $invoice;
                                    $item["user_id"] = $data["user_id"];

                                    $split_rel  = explode("|",$item["user_pid"]);
                                    $rel_type   = $split_rel[0];
                                    $rel_id     = $split_rel[1];
                                    if($rel_type == "Domain" || $rel_type == "DomainRegister" || $rel_type == "DomainTransfer")
                                    {
                                        if(isset($collation["orders"]["DomainRegister|".$rel_id]))
                                            $item["user_pid"] = $collation["orders"]["DomainRegister|".$rel_id];
                                        elseif(isset($collation["orders"]["DomainTransfer|".$rel_id]))
                                            $item["user_pid"] = $collation["orders"]["DomainTransfer|".$rel_id];
                                        else
                                            $item["user_pid"] = 0;
                                    }
                                    elseif($rel_type == "Hosting" && isset($collation["orders"]["Hosting|".$rel_id]))
                                        $item["user_pid"] = $collation["orders"]["Hosting|".$rel_id];
                                    elseif($rel_type == "Addon" && isset($collation["orders"]["Addon|".$rel_id])){
                                        $item["user_pid"] = $collation["orders"]["Addon|".$rel_id];
                                        if(isset($item["options"]["event_data"]["addon_id"]))
                                            $item["options"]["event_data"]["addon_id"] = $item["user_pid"];
                                        \WDB::update("users_products_addons",['invoice_id' => $invoice])
                                            ->where("id","=",$item["user_pid"])
                                            ->save();
                                    }else
                                        $item["user_pid"] = 0;

                                    $item["options"] = \Utility::jencode($item["options"]);

                                    \WDB::insert("invoices_items",$item);

                                }

                                \Notification::user_notification([
                                    'user_id' => $data["user_id"],
                                    'type' => "notification",
                                    'owner' => "invoice",
                                    'owner_id' => $invoice,
                                    'name' => "invoice-created",
                                    'unread' => 1,
                                ]);
                            }

                        }

                    }
                }

                // User Credits Process
                if($this->cx["users"] && !isset($collation["others"]["credit_logs"]) && $return === true){
                    if(isset($result["others"]["credit_logs"]) && $credit_logs = $result["others"]["credit_logs"]){
                        foreach($credit_logs AS $row){
                            $desc   = $row["description"];
                            preg_match("/Invoice #([0-9])/",$desc,$match);
                            if(isset($match[1]))
                                if(isset($collation["invoices"][$match[1]]))
                                    $desc = str_replace("#".$match[1],"#".$collation["invoices"][$match[1]],$desc);
                            $row["description"] = $desc;
                            if(!isset($collation["users"][$row["user_id"]])) continue;
                            $row["user_id"] = $collation["users"][$row["user_id"]];
                            \WDB::insert("users_credit_logs",$row);
                            $collation["others"]["credit_logs"][] = \WDB::lastID();
                        }
                    }
                }
            }
            else sleep(3);
            
            return $return;
        }
        private function start_import_part6(&$result,&$collation)
        {
            $return                     = true;
            $tickets_cx                 = $this->cx["tickets"];


            if(!$this->test){
                // Ticket Departments Process
                if(!isset($collation["tickets"]["departments"])){
                    if(isset($result["tickets"]["departments"]) && $departments = $result["tickets"]["departments"]){
                        foreach($departments AS $old_id=>$row){
                            $data       = $row["data"];
                            $languages  = $row["lang"];
                            $fields     = $row["fields"];
                            $department = \WDB::insert("tickets_departments",$data);
                            if($department){
                                $department = \WDB::lastID();
                                $collation["tickets"]["departments"][$old_id] = $department;
                                if($languages){
                                    foreach($languages AS $l_key=>$language){
                                        $language["owner_id"]   = $department;
                                        $language["lang"]       = $l_key;
                                        \WDB::insert("tickets_departments_lang",$language);
                                    }
                                }
                                if($fields){
                                    foreach($fields AS $f_old_id => $f_row)
                                    {
                                        $f_data         = $f_row["data"];
                                        $f_languages    = $f_row["lang"];

                                        $f_data["did"] = $department;
                                        $i_filed = \WDB::insert("tickets_custom_fields",$f_data);
                                        if($i_filed){
                                            $i_filed = \WDB::lastID();
                                            if($f_languages){
                                                foreach($f_languages AS $l_key=>$language){
                                                    $language["owner_id"]   = $i_filed;
                                                    $language["lang"]       = $l_key;
                                                    \WDB::insert("tickets_custom_fields_lang",$language);
                                                }
                                            }
                                            $collation["tickets"]["fields"][$old_id] = $i_filed;
                                        }

                                    }

                                }
                            }
                        }
                    }
                }

                $field_key          = \Config::get("crypt/system")."_CUSTOM_FIELDS";

                // Ticket Predefined Replies Process
                if($this->cx["tickets"] && !isset($collation["tickets"]["predefined_replies"])){
                    if(isset($result["tickets"]["predefined_replies"]) && $pdrpls = $result["tickets"]["predefined_replies"]){
                        foreach($pdrpls AS $row)
                        {
                            $data       = $row["data"];
                            $languages  = $row["lang"];

                            if(isset($data["category"]) && isset($collation["categories"]["predefined_replies"][$data["category"]]))
                                $data["category"] = $collation["categories"]["predefined_replies"][$data["category"]];
                            else
                                $data["category"] = 0;

                            $predefined = \WDB::insert("tickets_predefined_replies",$data);
                            if($predefined){
                                $predefined = \WDB::lastID();
                                $collation["tickets"]["predefined_replies"][] = $predefined;
                                if($languages){
                                    foreach($languages AS $l_key=>$language){
                                        $language["owner_id"]   = $predefined;
                                        $language["lang"]       = $l_key;
                                        \WDB::insert("tickets_predefined_replies_lang",$language);
                                    }
                                }
                            }
                        }
                    }
                }

                // Knowledgebase Process
                if($this->cx["tickets"] && !isset($collation["others"]["knowledgebase"]))
                {
                    if(isset($result["others"]["knowledgebase"]) && $kbases = $result["others"]["knowledgebase"]){
                        foreach($kbases AS $row){
                            $data       = $row["data"];
                            $languages  = $row["lang"];

                            if(isset($data["category"]) && isset($collation["categories"]["knowledgebase"][$data["category"]]))
                                $data["category"] = $collation["categories"]["knowledgebase"][$data["category"]];
                            else
                                $data["category"] = 0;

                            $kbase = \WDB::insert("knowledgebase",$data);
                            if($kbase){
                                $kbase = \WDB::lastID();
                                $collation["others"]["knowledgebase"][] = $kbase;
                                if($languages){
                                    foreach($languages AS $l_key=>$language){
                                        $language["owner_id"]   = $kbase;
                                        $language["lang"]       = $l_key;
                                        \WDB::insert("knowledgebase_lang",$language);
                                    }
                                }
                            }
                        }
                    }
                }

                // Ticket Requests Process
                if($tickets_cx && isset($result["tickets"]["requests"]) && $requests = $result["tickets"]["requests"]){
                    if($result["count"]["tickets"] > 999){
                        if(sizeof($result["tickets"]["requests"]) > 999){
                            $result["tickets"]["requests"] = array_chunk($requests,1000,true);
                            $requests                      = $result["tickets"]["requests"];
                        }
                        $before_part         = -1;
                        if(isset($collation["parts"]["tickets"]))
                            $before_part    = $collation["parts"]["tickets"];
                        $keys               = array_keys($requests);
                        $end_part           = end($keys);
                        $next_part          = $before_part+1;
                        if($next_part != $end_part) $return = 6;
                        $requests           = $result["tickets"]["requests"][$next_part];
                        $collation["parts"]["tickets"] = $next_part;
                    }
                    foreach($requests AS $old_id=>$row){
                        $data       = $row["data"];
                        $replies    = $row["replies"];
                        $fields     = $row["fields"];

                        if($data["did"] && isset($collation["tickets"]["departments"][$data["did"]]))
                            $data["did"] = $collation["tickets"]["departments"][$data["did"]];
                        else
                            $data["did"] = 0;

                        if($fields){
                            foreach($fields AS $f_i=>$f_v){
                                if(isset($collation["tickets"]["fields"][$f_i])){
                                    $data["custom_fields"][$collation["tickets"]["fields"][$f_i]] = $f_v;
                                }
                            }
                        }

                        if(isset($data["custom_fields"]) && $data["custom_fields"]) $data["custom_fields"] = \Crypt::encode(\Utility::jencode($data["custom_fields"]),$field_key);


                        if(isset($collation["users"][$data["user_id"]]))
                            $data["user_id"] = $collation["users"][$data["user_id"]];

                        $request    = \WDB::insert("tickets",$data);

                        if($request){
                            $request    = \WDB::lastID();

                            if($replies){
                                foreach($replies AS $reply){
                                    $reply["owner_id"] = $request;
                                    if(isset($collation["users"][$reply["user_id"]]))
                                        $reply["user_id"] = $collation["users"][$reply["user_id"]];

                                    $attachments = $reply["attachments"];
                                    unset($reply["attachments"]);

                                    $replied    = \WDB::insert("tickets_replies",$reply);
                                    if($replied && $attachments){
                                        $replied    = \WDB::lastID();

                                        foreach($attachments AS $attachment){
                                            $attachment["ticket_id"] = $request;
                                            $attachment["reply_id"]  = $replied;
                                            \WDB::insert("tickets_attachments",$attachment);
                                        }
                                    }
                                }
                            }

                        }

                    }
                }
            }
            else sleep(3);
            return $return;
        }

    }