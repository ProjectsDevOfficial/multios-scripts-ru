<?php
    namespace WISECP\Modules\Imports;
    class WISECP
    {
        public array $lang; // To be assigned by the system.
        public string $area_link; // To be assigned by the system.
        public string $name; // // To be assigned by the system.
        public object $controller; // // To be assigned by the system.

        // Variables that can be used
        public bool $test = false;
        public string $encryption_hash,$encryption_hash2,$file_hash;
        private $db;
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

        public function wisecp_decrypt($string,$k=NULL,$ekl=NULL,$ekr=NULL)
        {
            if(!$k) $k = $this->encryption_hash;
            if($ekl) $k =  $ekl.$k;
            if($ekr) $k .= $ekr;

            return \Crypt::decode($string,$k);
        }

        public function wisecp_encrypt($string,$k=NULL,$ekl=NULL,$ekr=NULL)
        {
            if(!$k) $k = \Config::get("crypt/user");
            if($ekl) $k =  $ekl.$k;
            if($ekr) $k .= $ekr;
            return \Crypt::encode($string,$k);
        }


        private function set_db_credentials():void
        {
            $db_host        = \Filter::init("POST/db_host","hclear");
            $db_username    = \Filter::init("POST/db_username","hclear");
            $db_password    = \Filter::init("POST/db_password","password");
            $db_name        = \Filter::init("POST/db_name","route");
            $db_charset     = \Filter::init("POST/db_charset","hclear");
            $encryption_key = \Filter::init("POST/encryption_key","hclear");
            $encryption_key2 = \Filter::init("POST/encryption_key2","hclear");


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

            if(\Validation::isEmpty($encryption_key))
                die(\Utility::jencode([
                    'status' => "error",
                    'for' => "input[name='encryption_key2']",
                    'message' => __("admin/tools/error16"),
                ]));

            $this->encryption_hash      = $encryption_key;
            $this->encryption_hash2     = $encryption_key2;

            if(\Config::get("database/host") == $db_host && \Config::get("database/name") == $db_name)
                die(\Utility::jencode([
                    'status' => "error",
                    'for' => "input[name='db_name']",
                    'message' => $this->lang["same-database-error"],
                ]));



            \MioException::$error_hide=true;
            try{
                $this->db     = new \Database("mysql",$db_host,3306,$db_username,$db_password,false,$db_name,$db_charset);
            }
            catch(\DatabaseException $e){
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
                        'products' => [],
                    ],
                    'others'        => [
                        'shared_servers' => [],
                        'events' => [],
                        'user_groups' => [],
                        'credit_logs' => [],
                        'affiliates'  => [],
                        'users_custom_fields'  => [],
                        'stored_cards'  => [],
                        'subscriptions' => [],
                        'whois_profiles'  => [],
                        'announcements' => [],
                    ],
                    'products'      => [],
                    'product_addons' => [],
                    'product_requirements' => [],
                    'users'         => [],
                    'orders'        => [],
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
                $this->pull_data_part1($result,$collation);
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
            //  Fetch TLDS
            $tlds           = \WDB::select()->from("tldlist");
            $tlds           = $tlds->build() ? $tlds->fetch_assoc() : [];
            if($tlds) foreach($tlds AS $row) $collation["tlds"][$row["name"]] = $row["id"];

            //  Fetch Categories
            $product_groups     = $this->db->select()->from("categories c");
            $product_groups->where("type","=","requirement","||");
            $product_groups->where("type","=","addon","||");
            $product_groups->where("type","=","products","||");
            $product_groups->where("type","=","software");
            $product_groups->order_by("id ASC");
            $product_groups     = $product_groups->build() ? $product_groups->fetch_assoc() : [];
            if($product_groups){
                foreach($product_groups AS $row)
                {
                    $id                 = $row["id"];
                    $row["options"]     = \Utility::jdecode($row["options"],true);

                    unset($row["id"]);

                    $item               = [
                        'data'          => $row,
                        'lang' => [],
                    ];

                    $default_lang = $this->db->select()->from("categories_lang");
                    $default_lang->where("owner_id","=",$id);
                    $default_lang->order_by("id ASC");
                    $default_lang = $default_lang->build() ? $default_lang->getAssoc() : [];

                    if(!$default_lang) continue;

                    foreach($this->lang_list AS $l)
                    {
                        $lk = $l["key"];
                        $exists_lang = $this->db->select()->from("categories_lang");
                        $exists_lang->where("owner_id","=",$id,"&&");
                        $exists_lang->where("lang","=",$lk);
                        $exists_lang = $exists_lang->build() ? $exists_lang->getAssoc() : [];
                        if(!$exists_lang) $exists_lang = $default_lang;

                        unset($exists_lang["id"]);
                        unset($exists_lang["owner_id"]);

                        $item['lang'][$lk] = $exists_lang;
                    }

                    $result["count"]["product_categories"] +=1;
                    $result["categories"]["products"][$id] = $item;
                }
            }

            //  Fetch Shared Servers
            $shared_servers     = $this->db->select()->from("servers");
            $shared_servers     = $shared_servers->build() ? $shared_servers->fetch_assoc() : [];
            if($shared_servers){
                foreach($shared_servers AS $row){
                    $id = $row["id"];
                    unset($row["id"]);
                    $row["password"] = $this->wisecp_encrypt($this->wisecp_decrypt($row["password"]));
                    $result["others"]["shared_servers"][$id] = $row;
                }
            }

            //  Fetch Shared Server Groups
            $server_groups     = $this->db->select()->from("servers_groups");
            $server_groups     = $server_groups->build() ? $server_groups->fetch_assoc() : [];
            if($server_groups)
            {
                foreach($server_groups AS $row){
                    $id                 = $row["id"];
                    $server_ids         = explode(",",$row["servers"]);

                    unset($row["id"]);
                    $row["servers"] = $server_ids;

                    $collation["server_groups"][$id] = $row;

                    $result["others"]["shared_server_groups"][$id] = $collation["server_groups"][$id];
                }
            }


            // Fetch Products
            $products   = $this->db->select()->from("products");
            $products->order_by("id ASC");
            $products   = $products->build() ? $products->fetch_assoc() : [];
            if($products){
                foreach($products AS $row){
                    $id                        = $row["id"];
                    unset($row["id"]);

                    $row["categories"]    = $row["categories"] ? explode(",",$row["categories"]) : [];
                    $row["notes"]           .= " Created with the WISECP import module";
                    $row["options"]         = \Utility::jdecode($row["options"],true);
                    $row["module_data"]     = \Utility::jdecode($row["module_data"],true);
                    $row["addons"]          = $row["addons"] ? explode(",",$row["addons"]) : [];
                    $row["requirements"]    = $row["requirements"] ? explode(",",$row["requirements"]) : [];
                    $row["upgradeable_products"]    = $row["upgradeable_products"] ? explode(",",$row["upgradeable_products"]) : [];

                    $item = [
                        'data'          => $row,
                        'lang'          => [],
                        'prices'        => [],
                    ];

                    $default_lang = $this->db->select()->from("products_lang");
                    $default_lang->where("owner_id","=",$id);
                    $default_lang->order_by("id ASC");
                    $default_lang = $default_lang->build() ? $default_lang->getAssoc() : [];

                    if(!$default_lang) continue;

                    foreach($this->lang_list AS $l)
                    {
                        $lk = $l["key"];
                        $exists_lang = $this->db->select()->from("products_lang");
                        $exists_lang->where("owner_id","=",$id,"&&");
                        $exists_lang->where("lang","=",$lk);
                        $exists_lang = $exists_lang->build() ? $exists_lang->getAssoc() : [];
                        if(!$exists_lang) $exists_lang = $default_lang;

                        unset($exists_lang["id"]);
                        unset($exists_lang["owner_id"]);

                        $item['lang'][$lk] = $exists_lang;
                    }

                    $prices = $this->db->select()->from("prices");
                    $prices->where("owner_id","=",$id,"&&");
                    $prices->where("owner","=","products");
                    $prices->order_by("id ASC");
                    $prices = $prices->build() ? $prices->fetch_assoc() : [];
                    if($prices)
                    {
                        foreach($prices AS $prow)
                        {
                            $pid = $prow["id"];
                            unset($prow["id"]);
                            unset($prow["owner_id"]);
                            $item["prices"][] = $prow;
                        }
                    }


                    $result["products"][$id] = $item;
                    $result["count"]["products"] +=1;
                }
            }


            // Fetch Software Products
            $products   = $this->db->select()->from("pages");
            $products->where("type","=","software");
            $products->order_by("id ASC");
            $products   = $products->build() ? $products->fetch_assoc() : [];
            if($products)
            {
                foreach($products AS $row)
                {
                    $id = $row["id"];
                    unset($row["id"]);

                    $row["categories"]    = $row["categories"] ? explode(",",$row["categories"]) : [];
                    $row["notes"]           .= " Created with the WISECP import module";
                    $row["options"]         = \Utility::jdecode($row["options"],true);
                    $row["module_data"]     = \Utility::jdecode($row["module_data"],true);
                    $row["addons"]          = $row["addons"] ? explode(",",$row["addons"]) : [];
                    $row["requirements"]    = $row["requirements"] ? explode(",",$row["requirements"]) : [];
                    if(isset($row["upgradeable_products"]))
                        $row["upgradeable_products"]    = $row["upgradeable_products"] ? explode(",",$row["upgradeable_products"]) : [];


                    $item = [
                        'data'          => $row,
                        'lang'          => [],
                        'prices'        => [],
                    ];

                    $default_lang = $this->db->select()->from("pages_lang");
                    $default_lang->where("owner_id","=",$id);
                    $default_lang->order_by("id ASC");
                    $default_lang = $default_lang->build() ? $default_lang->getAssoc() : [];

                    if(!$default_lang) continue;

                    foreach($this->lang_list AS $l)
                    {
                        $lk = $l["key"];
                        $exists_lang = $this->db->select()->from("pages_lang");
                        $exists_lang->where("owner_id","=",$id,"&&");
                        $exists_lang->where("lang","=",$lk);
                        $exists_lang = $exists_lang->build() ? $exists_lang->getAssoc() : [];
                        if(!$exists_lang) $exists_lang = $default_lang;

                        unset($exists_lang["id"]);
                        unset($exists_lang["owner_id"]);

                        $item['lang'][$lk] = $exists_lang;
                    }

                    $prices = $this->db->select()->from("prices");
                    $prices->where("owner_id","=",$id,"&&");
                    $prices->where("owner","=","softwares");
                    $prices->order_by("id ASC");
                    $prices = $prices->build() ? $prices->fetch_assoc() : [];
                    if($prices)
                    {
                        foreach($prices AS $prow)
                        {
                            $pid = $prow["id"];
                            unset($prow["id"]);
                            unset($prow["owner_id"]);
                            $item["prices"][] = $prow;
                        }
                    }

                    $result["products"]["software|".$id] = $item;
                    $result["count"]["products"] +=1;

                }
            }

            // Fetch Requirements
            $requirements = $this->db->select()->from("products_requirements");
            $requirements = $requirements->build() ? $requirements->fetch_assoc() : [];
            if($requirements)
            {
                foreach($requirements AS $row)
                {
                    $id = $row["id"];
                    unset($row["id"]);

                    $item = [
                        'data'          => $row,
                        'lang'          => [],
                    ];

                    $default_lang = $this->db->select()->from("products_requirements_lang");
                    $default_lang->where("owner_id","=",$id);
                    $default_lang->order_by("id ASC");
                    $default_lang = $default_lang->build() ? $default_lang->getAssoc() : [];

                    if(!$default_lang) continue;

                    foreach($this->lang_list AS $l)
                    {
                        $lk = $l["key"];
                        $exists_lang = $this->db->select()->from("products_requirements_lang");
                        $exists_lang->where("owner_id","=",$id,"&&");
                        $exists_lang->where("lang","=",$lk);
                        $exists_lang = $exists_lang->build() ? $exists_lang->getAssoc() : [];
                        if(!$exists_lang) $exists_lang = $default_lang;

                        unset($exists_lang["id"]);
                        unset($exists_lang["owner_id"]);
                        $exists_lang["properties"]  = \Utility::jdecode($exists_lang["properties"],true);
                        $exists_lang["options"]     = \Utility::jdecode($exists_lang["options"],true);

                        $item['lang'][$lk] = $exists_lang;
                    }

                    $result["product_requirements"][$id] = $item;
                }
            }

            // Fetch Products Addons
            $addons = $this->db->select()->from("products_addons");
            $addons = $addons->build() ? $addons->fetch_assoc() : [];
            if($addons)
            {
                foreach($addons AS $row)
                {
                    $id = $row["id"];
                    unset($row["id"]);

                    $item = [
                        'data'          => $row,
                        'lang'          => [],
                    ];

                    $default_lang = $this->db->select()->from("products_addons_lang");
                    $default_lang->where("owner_id","=",$id);
                    $default_lang->order_by("id ASC");
                    $default_lang = $default_lang->build() ? $default_lang->getAssoc() : [];

                    if(!$default_lang) continue;

                    foreach($this->lang_list AS $l)
                    {
                        $lk = $l["key"];
                        $exists_lang = $this->db->select()->from("products_addons_lang");
                        $exists_lang->where("owner_id","=",$id,"&&");
                        $exists_lang->where("lang","=",$lk);
                        $exists_lang = $exists_lang->build() ? $exists_lang->getAssoc() : [];
                        if(!$exists_lang) $exists_lang = $default_lang;

                        unset($exists_lang["id"]);
                        unset($exists_lang["owner_id"]);
                        $exists_lang["properties"]  = \Utility::jdecode($exists_lang["properties"],true);
                        $exists_lang["options"]     = \Utility::jdecode($exists_lang["options"],true);

                        $item['lang'][$lk] = $exists_lang;
                    }

                    $result["product_addons"][$id] = $item;
                }
            }


            $announcements     = $this->db->select()->from("pages");
            $announcements->where("type","=","news","&&");
            $announcements->where("visible_to_user","=","1");
            $announcements     = $announcements->build() ? $announcements->fetch_assoc() : [];
            if($announcements)
            {
                foreach($announcements AS $row)
                {

                    $id = $row["id"];
                    unset($row["id"]);

                    $row["category"]        = 0;
                    $row["categories"]      = '';
                    $row["notes"]           .= " Created with the WISECP import module";


                    $item = [
                        'data'          => $row,
                        'lang'          => [],
                    ];

                    $default_lang = $this->db->select()->from("pages_lang");
                    $default_lang->where("owner_id","=",$id);
                    $default_lang->order_by("id ASC");
                    $default_lang = $default_lang->build() ? $default_lang->getAssoc() : [];

                    if(!$default_lang) continue;

                    foreach($this->lang_list AS $l)
                    {
                        $lk = $l["key"];
                        $exists_lang = $this->db->select()->from("pages_lang");
                        $exists_lang->where("owner_id","=",$id,"&&");
                        $exists_lang->where("lang","=",$lk);
                        $exists_lang = $exists_lang->build() ? $exists_lang->getAssoc() : [];
                        if(!$exists_lang) $exists_lang = $default_lang;

                        unset($exists_lang["id"]);
                        unset($exists_lang["owner_id"]);

                        $item['lang'][$lk] = $exists_lang;
                    }
                    $result["others"]["announcements"][$id] = $item;
                }
            }
        }
        private function pull_data_part2(&$result,&$collation):void
        {
            // Fetch User Groups
            $user_groups   = $this->db->select()->from("users_groups");
            $user_groups   = $user_groups->build() ? $user_groups->fetch_assoc() : [];
            if($user_groups){
                foreach($user_groups AS $row){
                    $id = $row["id"];
                    unset($row["id"]);
                    $result["others"]["user_groups"][$id] = $row;
                }
            }


            // Fetch Users
            $users   = $this->db->select()->from("users");
            $users->where("type","=","member");
            $users->order_by("id ASC");
            $users   = $users->build() ? $users->fetch_assoc() : [];
            if($users){
                foreach($users AS $row){
                    $id                 = $row["id"];
                    unset($row["id"]);


                    $ekl                = 'member-SECURITY|';
                    $ekr                = "|member-SECURITY";
                    $password           = $this->wisecp_decrypt($row["password"],NULL,$ekl,$ekr);
                    $row["password"]    = $this->wisecp_encrypt($password,\Config::get("crypt/user"),$ekl,$ekr);
                    $row["login_token"] = NULL;
                    $row["secure_hash"] = NULL;

                    if(!\Bootstrap::$lang->LangExists($row["lang"])) $row["lang"] = $this->local_lang;

                    $infos   = [];
                    $getInfos       = $this->db->select()->from("users_informations");
                    $getInfos->where("owner_id","=",$id);
                    $getInfos->order_by("id ASC");
                    $getInfos       = $getInfos->build() ? $getInfos->fetch_assoc() : [];

                    if($getInfos)
                    {
                        foreach($getInfos AS $irow)
                        {
                            unset($irow["id"]);
                            unset($irow["owner_id"]);
                            if(in_array($irow["name"],["authentication","security_question","security_question_answer","GoCardless_mandate_id"]))
                                $irow["content"] = $this->wisecp_encrypt($this->wisecp_decrypt($irow["content"]));
                            $infos[$irow["name"]] = $irow["content"];
                        }
                    }

                    $addresses = [];
                    $getAddresses = $this->db->select()->from("users_addresses");
                    $getAddresses->where("status","!=","delete","&&");
                    $getAddresses->where("owner_id","=",$id);
                    $getAddresses->order_by("id ASC");
                    $getAddresses = $getAddresses->build() ? $getAddresses->fetch_assoc() : [];
                    if($getAddresses)
                    {
                        foreach($getAddresses AS $arow)
                        {
                            $a_id = $arow["id"];
                            unset($arow["id"]);
                            $addresses[$a_id] = $arow;
                        }
                    }


                    $item                   = [
                        'data'                      => $row,
                        'info'                      => $infos,
                        'addresses'                 => $addresses,
                    ];


                    $result["users"][$id] = $item;
                    $result["count"]["users"] +=1;
                }
            }

            // Fetch Credit Logs
            $credit_logs   = $this->db->select()->from("users_credit_logs");
            $credit_logs   = $credit_logs->build() ? $credit_logs->fetch_assoc() : [];
            if($credit_logs)
            {
                foreach($credit_logs AS $row)
                {
                    if(isset($result["users"][$row["user_id"]]))
                    {
                        unset($row["id"]);
                        $result["others"]["credit_logs"][] = $row;
                    }
                }
            }

            // Fetch Affiliates
            $affiliates     = $this->db->select()->from("users_affiliates");
            $affiliates     = $affiliates->build() ? $affiliates->fetch_assoc() : [];
            if($affiliates)
            {
                foreach($affiliates AS $row)
                {
                    $id = $row["id"];
                    unset($row["id"]);
                    if(!isset($result["users"][$row["owner_id"]])) continue;

                    $_referrers     = [];
                    $_hits          = [];
                    $_transactions  = [];
                    $_withdrawals   = [];
                    $_members       = [];

                    $referrers      = $this->db->select()->from("users_affiliate_referrers");
                    $referrers->where("affiliate_id","=",$id);
                    $referrers     = $referrers->build() ? $referrers->fetch_assoc() : [];

                    if($referrers)
                    {
                        foreach($referrers AS $rowx)
                        {
                            $rowx_id = $rowx["id"];
                            unset($rowx["id"]);
                            $_referrers[$rowx_id] = $rowx;
                        }
                    }

                    $hits      = $this->db->select()->from("users_affiliate_hits");
                    $hits->where("affiliate_id","=",$id);
                    $hits     = $hits->build() ? $hits->fetch_assoc() : [];

                    if($hits)
                    {
                        foreach($hits AS $rowx)
                        {
                            $rowx_id = $rowx["id"];
                            unset($rowx["id"]);
                            $_hits[$rowx_id] = $rowx;
                        }
                    }

                    $withdrawals      = $this->db->select()->from("users_affiliate_withdrawals");
                    $withdrawals->where("affiliate_id","=",$id);
                    $withdrawals     = $withdrawals->build() ? $withdrawals->fetch_assoc() : [];

                    if($withdrawals)
                    {
                        foreach($withdrawals AS $rowx)
                        {
                            $rowx_id = $rowx["id"];
                            unset($rowx["id"]);
                            $_withdrawals[$rowx_id] = $rowx;
                        }
                    }


                    $transactions      = $this->db->select()->from("users_affiliate_transactions");
                    $transactions->where("affiliate_id","=",$id);
                    $transactions     = $transactions->build() ? $transactions->fetch_assoc() : [];
                    if($transactions)
                    {
                        foreach($transactions AS $rowx)
                        {
                            $rowx_id = $rowx["id"];
                            unset($rowx["id"]);
                            $_transactions[$rowx_id] = $rowx;
                        }
                    }

                    $members      = $this->db->select("id")->from("users");
                    $members->where("aff_id","=",$id);
                    $members     = $members->build() ? $members->fetch_assoc() : [];
                    if($members) foreach($members AS $rowx) $_members[] = $rowx["id"];

                    $result["others"]["affiliates"][$id] = [
                        'data' => $row,
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

            // Fetch User Custom Fields
            $fields         = $this->db->select()->from("users_custom_fields");
            $fields->order_by("id ASC");
            $fields         = $fields->build() ? $fields->fetch_assoc() : [];
            if($fields)
            {
                foreach($fields AS $row)
                {
                    $id = $row["id"];
                    unset($row["id"]);
                    $result["others"]["users_custom_fields"][$id] = $row;
                }
            }

            // Fetch User Stored Cards
            $stored_cards = $this->db->select()->from("users_stored_cards");
            $stored_cards->order_by("id ASC");
            $stored_cards = $stored_cards->build() ? $stored_cards->fetch_assoc() : [];
            if($stored_cards)
            {
                foreach($stored_cards AS $row)
                {
                    $id = $row["id"];
                    unset($row["id"]);

                    $ekr = "**STORED_CARD**";
                    $row["cvc"] = $this->wisecp_encrypt($this->wisecp_decrypt($row["cvc"],false,false,$ekr),false,false,$ekr);
                    $row["token"] = $this->wisecp_encrypt($this->wisecp_decrypt($row["token"],false,false,$ekr),false,false,$ekr);

                    $result["others"]["stored_cards"][$id] = $row;
                }
            }

            // Fetch User Whois Profiles
            $whois_profiles = $this->db->select()->from("users_whois_profiles");
            $whois_profiles->order_by("id ASC");
            $whois_profiles = $whois_profiles->build() ? $whois_profiles->fetch_assoc() : [];
            if($whois_profiles)
            {
                foreach($whois_profiles AS $row)
                {
                    $id = $row["id"];
                    unset($row["id"]);

                    $result["others"]["whois_profiles"][$id] = $row;
                }
            }
        }
        private function pull_data_part3(&$result,&$collation):void
        {
            // Fetch Order Subscriptions
            $subscriptions = $this->db->select()->from("users_products_subscriptions");
            $subscriptions->order_by("id ASC");
            $subscriptions = $subscriptions->build() ? $subscriptions->fetch_assoc() : [];
            if($subscriptions)
            {
                foreach($subscriptions AS $row)
                {
                    $id = $row["id"];
                    unset($row["id"]);
                    $row["items"] = \Utility::jdecode($row["items"],true);
                    $result["others"]["subscriptions"][$id] = $row;
                }
            }

            // Fetch All Orders
            $orders   = $this->db->select()->from("users_products");
            $orders   = $orders->build() ? $orders->fetch_assoc() : [];
            if($orders)
            {
                foreach($orders AS $row)
                {
                    $id = $row["id"];
                    unset($row["id"]);

                    $prefix         = '';

                    if($row["type"] == "software") $prefix = $row["type"]."|";
                    $user_id        = $row["owner_id"];
                    if(!isset($result["users"][$user_id])) continue;

                    $options        = \Utility::jdecode($row["options"],true);

                    if($options["login"]["password"] ?? false)
                        $options["login"]["password"] = $this->wisecp_encrypt($this->wisecp_decrypt($options["login"]["password"]));

                    if($options["config"]["password"] ?? false)
                        $options["config"]["password"] = $this->wisecp_encrypt($this->wisecp_decrypt($options["config"]["password"]));

                    $row["options"] = $options;

                    $requirements       = [];
                    $getRequirements    = $this->db->select()->from("users_products_requirements");
                    $getRequirements->where("owner_id","=",$id);
                    $getRequirements = $getRequirements->build() ? $getRequirements->fetch_assoc() : [];
                    if($getRequirements)
                    {
                        foreach($getRequirements AS $grow)
                        {
                            $grow_id = $grow["id"];
                            unset($grow["id"]);

                            $requirements[$grow_id] = $grow;
                        }
                    }

                    $addons       = [];
                    $getAddons    = $this->db->select()->from("users_products_addons");
                    $getAddons->where("owner_id","=",$id);
                    $getAddons = $getAddons->build() ? $getAddons->fetch_assoc() : [];
                    if($getAddons)
                    {
                        foreach($getAddons AS $grow)
                        {
                            $grow_id = $grow["id"];
                            unset($grow["id"]);

                            $addons[$grow_id] = $grow;
                        }
                    }

                    $updown        = [];
                    $getUpDown     = $this->db->select()->from("users_products_updowngrades");
                    $getUpDown->where("owner_id","=",$id);
                    $getUpDown = $getUpDown->build() ? $getUpDown->fetch_assoc() : [];
                    if($getUpDown)
                    {
                        foreach($getUpDown AS $ggrow)
                        {
                            $ggrow_id = $ggrow["id"];
                            unset($ggrow["id"]);
                            $updown[$ggrow_id] = $ggrow;
                        }
                    }

                    $docs          = [];
                    $getDocs       = $this->db->select()->from("users_products_docs");
                    $getDocs->where("owner_id","=",$id);
                    $getDocs = $getDocs->build() ? $getDocs->fetch_assoc() : [];
                    if($getDocs)
                    {
                        foreach($getDocs AS $grow)
                        {
                            $grow_id = $grow["id"];
                            unset($grow["id"]);

                            $docs[$grow_id] = $grow;
                        }
                    }

                    $result["orders"][$id] = [
                        'data' => $row,
                        'relationships' => [
                            'requirements'  => $requirements,
                            'addons'        => $addons,
                            'updown'        => $updown,
                            'docs'          => $docs,
                        ],
                    ];
                    $result["count"]["orders"] +=1;

                }
            }
        }
        private function pull_data_part4(&$result,&$collation)
        {
            $taxation_type = 'exclusive';

            $get_taxation = $this->db->select("taxation_type")->from("invoices");
            $get_taxation->order_by("id DESC");
            $get_taxation->limit(1);
            if($get_taxation->build()) $taxation_type = $get_taxation->getObject()->taxation_type;

            $invoices   = $this->db->select()->from("invoices")->order_by("id ASC")->build() ? $this->db->fetch_assoc() : false;
            if($invoices)
            {
                foreach($invoices AS $row)
                {
                    $id             = $row["id"];
                    $items          = [];
                    $row["user_data"] = \Utility::jdecode($row["user_data"],true);

                    $getItems       = $this->db->select()->from("invoices_items");
                    $getItems->where("owner_id","=",$id);
                    $getItems->order_by("id ASC");
                    $getItems = $getItems->build() ? $getItems->fetch_assoc() : [];
                    if($getItems)
                    {
                        foreach($getItems AS $grow)
                        {
                            $grow_id = $grow["id"];
                            unset($grow["id"]);
                            $items[$grow_id] = $grow;
                        }
                    }

                    unset($row["id"]);

                    $result["invoices"][$id] = [
                        'data' =>  $row,
                        'items'             => $items,
                    ];
                    $result["count"]["invoices"] +=1;
                }
            }




        }
        private function pull_data_part5(&$result,&$collation)
        {
            // Fetch Knowledgebase && Predefined Reply Categories
            $categories     = $this->db->select()->from("categories c");
            $categories->where("type","=","predefined_replies","||");
            $categories->where("type","=","knowledgebase");
            $categories->order_by("id ASC");
            $categories     = $categories->build() ? $categories->fetch_assoc() : [];
            if($categories){
                foreach($categories AS $row)
                {
                    $id                 = $row["id"];
                    $row["options"]     = \Utility::jdecode($row["options"],true);

                    unset($row["id"]);

                    $item               = [
                        'data'          => $row,
                        'lang' => [],
                    ];

                    $default_lang = $this->db->select()->from("categories_lang");
                    $default_lang->where("owner_id","=",$id);
                    $default_lang->order_by("id ASC");
                    $default_lang = $default_lang->build() ? $default_lang->getAssoc() : [];

                    if(!$default_lang) continue;

                    foreach($this->lang_list AS $l)
                    {
                        $lk = $l["key"];
                        $exists_lang = $this->db->select()->from("categories_lang");
                        $exists_lang->where("owner_id","=",$id,"&&");
                        $exists_lang->where("lang","=",$lk);
                        $exists_lang = $exists_lang->build() ? $exists_lang->getAssoc() : [];
                        if(!$exists_lang) $exists_lang = $default_lang;

                        unset($exists_lang["id"]);
                        unset($exists_lang["owner_id"]);

                        $item['lang'][$lk] = $exists_lang;
                    }

                    $result["categories"][$row["type"]][$row["id"]] = $item;
                }
            }

            $kbase_articles    = $this->db->select()->from("knowledgebase");
            $kbase_articles->order_by("id ASC");
            $kbase_articles     = $kbase_articles->build() ? $this->db->fetch_assoc() : [];
            if($kbase_articles)
            {
                foreach($kbase_articles AS $row)
                {
                    $id = $row["id"];
                    unset($row["id"]);

                    $item = [
                        'data'          => $row,
                        'lang'          => [],
                    ];

                    $default_lang = $this->db->select()->from("knowledgebase_lang");
                    $default_lang->where("owner_id","=",$id);
                    $default_lang->order_by("id ASC");
                    $default_lang = $default_lang->build() ? $default_lang->getAssoc() : [];

                    if(!$default_lang) continue;

                    foreach($this->lang_list AS $l)
                    {
                        $lk = $l["key"];
                        $exists_lang = $this->db->select()->from("knowledgebase_lang");
                        $exists_lang->where("owner_id","=",$id,"&&");
                        $exists_lang->where("lang","=",$lk);
                        $exists_lang = $exists_lang->build() ? $exists_lang->getAssoc() : [];
                        if(!$exists_lang) $exists_lang = $default_lang;

                        unset($exists_lang["id"]);
                        unset($exists_lang["owner_id"]);

                        $item['lang'][$lk] = $exists_lang;
                    }

                    $result["others"]["knowledgebase"][$id] = $item;
                }
            }


            // Fetch Tickets Departments
            $departments    = $this->db->select()->from("tickets_departments");
            $departments->order_by("id ASC");
            $departments     = $departments->build() ? $this->db->fetch_assoc() : [];
            if($departments)
            {
                foreach($departments AS $row)
                {
                    $id = $row["id"];
                    unset($row["id"]);
                    $row["appointees"] = '';

                    $item = [
                        'data' => $row,
                        'lang' => [],
                    ];

                    $default_lang = $this->db->select()->from("tickets_departments_lang");
                    $default_lang->where("owner_id","=",$id);
                    $default_lang->order_by("id ASC");
                    $default_lang = $default_lang->build() ? $default_lang->getAssoc() : [];

                    if(!$default_lang) continue;

                    foreach($this->lang_list AS $l)
                    {
                        $lk = $l["key"];
                        $exists_lang = $this->db->select()->from("tickets_departments_lang");
                        $exists_lang->where("owner_id","=",$id,"&&");
                        $exists_lang->where("lang","=",$lk);
                        $exists_lang = $exists_lang->build() ? $exists_lang->getAssoc() : [];
                        if(!$exists_lang) $exists_lang = $default_lang;

                        unset($exists_lang["id"]);
                        unset($exists_lang["owner_id"]);

                        $item['lang'][$lk] = $exists_lang;
                    }

                    $cfields = $this->db->select()->from("tickets_custom_fields");
                    $cfields->where("did","=",$id);
                    $cfields->order_by("id ASC");
                    $cfields = $cfields->build() ? $this->db->fetch_assoc() : [];
                    if($cfields)
                    {
                        foreach($cfields AS $cfield)
                        {
                            $cfield_id = $cfield["id"];
                            unset($cfield["id"]);

                            $cfield_item = [
                                'data' => $cfield,
                                'lang' => [],
                            ];

                            $default_lang = $this->db->select()->from("tickets_custom_fields_lang");
                            $default_lang->where("owner_id","=",$cfield_id);
                            $default_lang->order_by("id ASC");
                            $default_lang = $default_lang->build() ? $default_lang->getAssoc() : [];

                            if(!$default_lang) continue;

                            foreach($this->lang_list AS $l)
                            {
                                $lk = $l["key"];
                                $exists_lang = $this->db->select()->from("tickets_custom_fields_lang");
                                $exists_lang->where("owner_id","=",$cfield_id,"&&");
                                $exists_lang->where("lang","=",$lk);
                                $exists_lang = $exists_lang->build() ? $exists_lang->getAssoc() : [];
                                if(!$exists_lang) $exists_lang = $default_lang;

                                unset($exists_lang["id"]);
                                unset($exists_lang["owner_id"]);

                                $cfield_item['lang'][$lk] = $exists_lang;
                            }

                            $result["tickets"]["cfields"][$cfield_id] = $cfield_item;
                        }
                    }

                    $result["tickets"]["departments"][$id] = $item;
                }
            }

            // Fetch Predefined Replies
            $predefined_replies    = $this->db->select()->from("tickets_predefined_replies")->build() ? $this->db->fetch_assoc() : [];

            if($predefined_replies)
            {
                foreach($predefined_replies AS $row)
                {
                    $id = $row["id"];
                    unset($row["id"]);

                    $item = [
                        'data' => $row,
                        'lang' => [],
                    ];


                    $default_lang = $this->db->select()->from("tickets_predefined_replies_lang");
                    $default_lang->where("owner_id","=",$id);
                    $default_lang->order_by("id ASC");
                    $default_lang = $default_lang->build() ? $default_lang->getAssoc() : [];
                    if(!$default_lang) continue;

                    foreach($this->lang_list AS $l)
                    {
                        $lk = $l["key"];
                        $exists_lang = $this->db->select()->from("tickets_predefined_replies_lang");
                        $exists_lang->where("owner_id","=",$id,"&&");
                        $exists_lang->where("lang","=",$lk);
                        $exists_lang = $exists_lang->build() ? $exists_lang->getAssoc() : [];
                        if(!$exists_lang) $exists_lang = $default_lang;

                        unset($exists_lang["id"]);
                        unset($exists_lang["owner_id"]);
                        $item['lang'][$lk] = $exists_lang;
                    }

                    $result["tickets"]["predefined_replies"][$id] = $item;
                }
            }

            // Fetch Tickets
            $tickets    = $this->db->select()->from("tickets")->build() ? $this->db->fetch_assoc() : [];
            if($tickets)
            {
                foreach($tickets AS $row)
                {
                    $id             = $row["id"];
                    unset($row["id"]);
                    $row["assigned"] = 0;
                    if(isset($row["WChat"])) unset($row["WChat"]);
                    if(isset($row["WChat_domain"])) unset($row["WChat_domain"]);

                    $row["custom_fields"] = \Utility::jdecode($this->wisecp_decrypt($row["custom_fields"],$this->encryption_hash2,NULL,"_CUSTOM_FIELDS"),true);

                    $replies = [];

                    $getReplies     = $this->db->select()->from("tickets_replies")->where("owner_id","=",$id)->order_by("id ASC")->build() ? $this->db->fetch_assoc() : [];

                    if($getReplies)
                    {
                        foreach($getReplies AS $reply)
                        {
                            $reply_id = $reply["id"];
                            unset($reply["id"]);
                            $attachments = [];
                            $getAttachments =  $this->db->select()->from("tickets_attachments");
                            $getAttachments->where("ticket_id","=",$id,"&&");
                            $getAttachments->where("reply_id","=",$reply_id);
                            $getAttachments->order_by("id ASC");
                            $getAttachments = $getAttachments->build() ? $this->db->fetch_assoc() : [];
                            if($getAttachments)
                            {
                                foreach($getAttachments AS $attachment)
                                {
                                    $attachment_id = $attachment["id"];
                                    unset($attachment["id"]);
                                    $attachments[$attachment_id] = $attachment;
                                }
                            }

                            $reply["attachments"] = $attachments;

                            if($reply["encrypted"])
                                $reply["message"] = $this->wisecp_encrypt($this->wisecp_decrypt($reply["message"],$this->encryption_hash2,NULL,"_MSG"),\Config::get("crypt/system"),NULL,"_MSG");


                            $replies[] = $reply;
                        }
                    }

                    $item           = [
                        'data'      => $row,
                        'replies'   => $replies,
                    ];


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
            \User::addAction($admin_data["id"],"alteration","imported-from-another-software",['name' => "WISECP"]);
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
                $upgradeable_products   = [];
                $addon_product_links    = [];

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
                            $collation["others"]["announcements"][$old_id] = $page;
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
                    foreach($categories AS $old_id=>$row)
                    {
                        $data       = $row["data"] ?? false;
                        $languages  = $row["lang"] ?? false;

                        if(!$data) continue;

                        if(isset($data["parent"]) && $data["parent"])
                            if(isset($collation["categories"]["products"][$data["parent"]]))
                                $data["parent"] = $collation["categories"]["products"][$data["parent"]];

                        if(isset($data["kind_id"]) && $data["kind_id"])
                            if(isset($collation["categories"]["products"][$data["kind_id"]]))
                                $data["kind_id"] = $collation["categories"]["products"][$data["kind_id"]];

                        if(is_array($data["options"])) $data["options"] = \Utility::jencode($data["options"]);

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
                if($this->cx["tickets"] && isset($result["categories"]["knowledgebase"]) && $result["categories"]["knowledgebase"])
                {
                    $categories = $result["categories"]["knowledgebase"];
                    foreach($categories AS $old_id=>$row){
                        $data       = $row["data"] ?? false;
                        $languages  = $row["lang"] ?? false;

                        if(!$data) continue;

                        if(isset($data["parent"]) && $data["parent"])
                            if(isset($collation["categories"]["knowledgebase"][$data["parent"]]))
                                $data["parent"] = $collation["categories"]["knowledgebase"][$data["parent"]];

                        if(is_array($data["options"])) $data["options"] = \Utility::jencode($data["options"]);

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
                    foreach($categories AS $old_id=>$row)
                    {
                        $data       = $row["data"] ?? false;
                        $languages  = $row["lang"] ?? false;

                        if(!$data) continue;

                        if(isset($data["parent"]) && $data["parent"])
                            if(isset($collation["categories"]["predefined_replies"][$data["parent"]]))
                                $data["parent"] = $collation["categories"]["predefined_replies"][$data["parent"]];

                        if(is_array($data["options"])) $data["options"] = \Utility::jencode($data["options"]);

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

                // Shared Servers Process
                if($this->cx["products"] && isset($result["others"]["shared_servers"]) && $result["others"]["shared_servers"])
                {
                    $servers = $result["others"]["shared_servers"];
                    foreach($servers AS $old_id=>$row)
                    {
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
                if($this->cx["products"] && isset($result["product_requirements"]) && $result["product_requirements"])
                {
                    $rows   = $result["product_requirements"];
                    foreach($rows AS $old_id=>$row){

                        $data       = $row["data"] ?? false;
                        $languages  = $row["lang"] ?? false;

                        if(!$data) continue;

                        if(str_starts_with("special_",$data["mcategory"]))
                        {
                            $cat_id = (int) str_replace("special_","",$data["mcategory"]);
                            $new_cat_id = $collation["categories"]["products"][$cat_id] ?? 0;
                            $data["mcategory"] = "special_".$new_cat_id;
                        }

                        if(isset($collation["categories"]["products"][$data["category"]]))
                            $data["category"] = $collation["categories"]["products"][$data["category"]];

                        $requirement = \WDB::insert("products_requirements",$data);
                        if($requirement){
                            $requirement = \WDB::lastID();
                            $collation["product_requirements"][$old_id] = $requirement;
                            if($languages){
                                foreach($languages AS $key=>$lang){
                                    $lang["owner_id"] = $requirement;
                                    $lang["lang"] = $key;
                                    $lang["properties"] = \Utility::jencode($lang["properties"]);
                                    $lang["options"] = \Utility::jencode($lang["options"]);
                                    \WDB::insert("products_requirements_lang",$lang);
                                }
                            }
                        }
                    }
                }

                // Product Addons Process
                if($this->cx["products"] && isset($result["product_addons"]) && $result["product_addons"])
                {
                    $rows   = $result["product_addons"];
                    foreach($rows AS $old_id=>$row)
                    {

                        $data       = $row["data"] ?? false;
                        $languages  = $row["lang"] ?? false;

                        if(!$data) continue;

                        if(str_starts_with("special_",$data["mcategory"]))
                        {
                            $cat_id = (int) str_replace("special_","",$data["mcategory"]);
                            $new_cat_id = $collation["categories"]["products"][$cat_id] ?? 0;
                            $data["mcategory"] = "special_".$new_cat_id;
                        }

                        if(isset($collation["categories"]["products"][$data["category"]]))
                            $data["category"] = $collation["categories"]["products"][$data["category"]];

                        if($data["requirements"])
                        {
                            $new_ids = [];
                            $parse_requirements = explode(",",$data["requirements"]);
                            if($parse_requirements)
                            {
                                foreach($parse_requirements AS $prs)
                                {
                                    if($collation["product_requirements"][$prs] ?? 0)
                                        $new_ids[] = $collation["product_requirements"][$prs];
                                }
                            }
                            if($new_ids)
                                $data["requirements"] = implode(",",$new_ids);
                            else
                                $data["requirements"] = "";
                        }

                        if($data["product_type_link"] && $data["product_id_link"])
                            $addon_product_links[$old_id] = [
                                'type'  => $data["product_type_link"],
                                'id'    => $data["product_id_link"],
                            ];
                        else
                        {
                            $data["product_type_link"] = "";
                            $data["product_id_link"] = "";
                        }

                        $addon = \WDB::insert("products_addons",$data);
                        if($addon)
                        {
                            $addon = \WDB::lastID();
                            $collation["product_addons"][$old_id] = $addon;
                            if($languages)
                            {
                                foreach($languages AS $key=>$lang)
                                {
                                    $lang["owner_id"] = $addon;
                                    $lang["lang"] = $key;
                                    $lang["properties"] = \Utility::jencode($lang["properties"]);
                                    $lang["options"] = \Utility::jencode($lang["options"]);
                                    \WDB::insert("products_addons_lang",$lang);
                                }
                            }
                        }
                    }
                }

                // Products Process
                if($this->cx["products"] && isset($result["products"]) && $products = $result["products"])
                {
                    foreach($products AS $old_id=>$row)
                    {
                        $data           = $row["data"];
                        $languages      = $row["lang"];
                        $prices         = $row["prices"] ?? [];

                        if(isset($data["requirements"]) && $data["requirements"])
                        {
                            $requirements   = [];
                            foreach($data["requirements"] AS $requirement)
                                if(isset($collation["product_requirements"][$requirement]))
                                    $requirements[] = $collation["product_requirements"][$requirement];
                            $data["requirements"] = $requirements ? implode(",",$requirements) : '';
                        }
                        else
                            $data["requirements"] = "";

                        if(isset($data["addons"]) && $data["addons"])
                        {
                            $addons         = [];
                            foreach($data["addons"] AS $addon)
                                if(isset($collation["product_addons"][$addon]))
                                    $addons[] = $collation["product_addons"][$addon];
                            $data["addons"] = $addons ? implode(",",$addons) : '';
                        }
                        else
                            $data["addons"] = "";

                        if($data["type_id"] && isset($collation["categories"]["products"][$data["type_id"]]))
                            $data["type_id"] = $collation["categories"]["products"][$data["type_id"]];

                        if($data["category"] && isset($collation["categories"]["products"][$data["category"]]))
                            $data["category"] = $collation["categories"]["products"][$data["category"]];
                        else $data["category"] = 0;

                        if($data["categories"])
                        {
                            $ids = [];
                            foreach($data["categories"] AS $c)
                                if(isset($collation["categories"]["products"][$c]))
                                    $ids[] = $collation["categories"]["products"][$c];

                            if($ids)
                                $data["categories"] = implode(",",$ids);
                            else
                                $data["categories"] = "";
                        }
                        else
                            $data["categories"] = "";


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

                        $data["options"]        = $data["options"] ? \Utility::jencode($data["options"]) : '';
                        $data["module_data"]    = $data["module_data"] ? \Utility::jencode($data["module_data"]) : '';
                        if(isset($data["upgradeable_products"]))
                        {
                            if($data["upgradeable_products"])
                                $upgradeable_products[$old_id] = $data["upgradeable_products"];
                            $data["upgradeable_products"] = '';
                        }

                        if($data["type"] == "software")
                            $product                = \WDB::insert("pages",$data);
                        else
                            $product                = \WDB::insert("products",$data);


                        if($product){
                            $product            = \WDB::lastID();
                            $collation["products"][$old_id] = $product;
                            if($languages){
                                foreach($languages AS $key=>$lang){
                                    $lang["owner_id"] = $product;
                                    $lang["lang"] = $key;

                                    if($data["type"] == "software")
                                        \WDB::insert("pages_lang",$lang);
                                    else
                                        \WDB::insert("products_lang",$lang);
                                }
                            }

                            if($prices)
                            {
                                foreach($prices AS $price)
                                {
                                    $price["owner_id"] = $product;
                                    \WDB::insert("prices",$price);
                                }
                            }

                        }
                    }
                }

                // Upgrade Product Process
                if($upgradeable_products)
                {
                    foreach($upgradeable_products AS $old_id => $product_ids)
                    {
                        if(isset($collation["products"][$old_id]))
                        {
                            $pids = [];
                            foreach($product_ids AS $pid)
                            {
                                if(isset($collation["products"][$pid]))
                                    $pids[] = $collation["products"][$pid];
                            }
                            if($pids)
                                \WDB::update("products",['upgradeable_products' => implode(',',$pids)])->where("id","=",$collation["products"][$old_id])->save();
                        }
                    }
                }

                // Addon Product Link Process
                if($addon_product_links)
                {
                    foreach($addon_product_links AS $old_id => $product_link)
                    {
                        if(isset($collation["product_addons"][$old_id]))
                        {
                            $product_type       = $product_link["type"];
                            $product_id         = 0;
                            if($product_link["type"] == "software" && isset($collation["products"]["software".$product_link["id"]]))
                                $product_id = $collation["products"]["software".$product_link["id"]];
                            elseif(isset($collation["products"][$product_link["id"]]))
                                $product_id = $collation["products"][$product_link["id"]];
                            else
                                $product_type = '';

                                \WDB::update("products_addons",[
                                    'product_type_link'     => $product_type,
                                    'product_type_id'       => $product_id,
                                ])->where("id","=",$collation["product_addons"][$old_id])->save();
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

                // User Custom Fields Process
                if($this->cx["users"] && ($result["others"]["users_custom_fields"] ?? []) && !isset($collation["others"]["users_custom_fields"]))
                {
                    foreach($result["others"]["users_custom_fields"] AS $old_id=>$row)
                    {
                        $insert = \WDB::insert("users_custom_fields",$row);
                        if($insert)
                        {
                            $insert = \WDB::lastID();
                            $collation["others"]["users_custom_fields"][$old_id] = $insert;
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
                                if($info)
                                {
                                    foreach($info AS $k => $v)
                                    {
                                        if(str_starts_with($k,"field_"))
                                        {
                                            $field_id = str_replace("field_","",$k);
                                            if(isset($collation["others"]["users_custom_fields"][$field_id]))
                                            {
                                                unset($k);
                                                $k = "field_".$collation["others"]["users_custom_fields"][$field_id];
                                                $info[$k] = $v;
                                            }
                                            else
                                                unset($info[$k]);
                                        }
                                    }
                                }
                                \User::setData($user,['secure_hash' => \User::secure_hash($user)]);
                                \User::setInfo($user,$info);
                                if($addresses)
                                {
                                    foreach($addresses AS $addr_old_id => $addr)
                                    {
                                        $addr["owner_id"] = $user;
                                        if(\WDB::insert("users_addresses",$addr))
                                        {
                                            $addr_id = \WDB::lastID();
                                            $collation["others"]["user_addresses"][$old_id] = $addr_id;
                                            if($addr["detouse"])
                                                \User::setInfo($user,['default_address' => $addr_id]);
                                        }
                                    }
                                }

                            }
                        }
                    }
                }

                if($return === true)
                {
                    // User Stored Cards Process
                    if(!isset($collation["others"]["stored_cards"]) && $result["others"]["stored_cards"] ?? [])
                    {
                        foreach($result["others"]["stored_cards"] AS $old_id => $row)
                        {
                            if(isset($collation["users"][$row["user_id"]])) $row["user_id"] = $collation["users"][$row["user_id"]];
                            else continue;

                            $insert = \WDB::insert("users_stored_cards",$row);
                            if($insert)
                            {
                                $insert = \WDB::lastID();
                                $collation["others"]["stored_cards"][$old_id] = $insert;
                            }
                        }

                    }

                    // User Whois Profiles Process
                    if(!isset($collation["others"]["whois_profiles"]) && ($result["others"]["whois_profiles"] ?? []))
                    {
                        foreach($result["others"]["whois_profiles"] AS $old_id => $row)
                        {
                            if(isset($collation["users"][$row["owner_id"]])) $row["owner_id"] = $collation["users"][$row["owner_id"]];
                            else continue;

                            $insert = \WDB::insert("users_whois_profiles",$row);
                            if($insert)
                            {
                                $insert = \WDB::lastID();
                                $collation["others"]["whois_profiles"][$old_id] = $insert;
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

                // Orders Subscriptions Process
                if($this->cx["orders"] && !isset($collation["others"]["subscriptions"]) && $result["others"]["subscriptions"] ?? [])
                {
                    foreach($result["others"]["subscriptions"] AS $old_id => $row)
                    {
                        if(!isset($collation["users"][$row["user_id"]])) continue;
                        $row["user_id"] = $collation["users"][$row["user_id"]];
                        if($row["items"])
                        {
                            $new_items = [];
                            foreach($row["items"] AS $item)
                            {
                                $item_type  = $item["product_type"];
                                $item_id    = $item["product_id"];
                                if($item_type == "software")
                                    $item_id = "software|".$item_id;
                                if(!isset($collation["products"][$item_id])) continue;
                                $item["product_id"] = $collation["products"][$item_id];
                                $new_items[] = $item;
                            }
                            $row["items"] = $new_items;
                        }

                        $row["items"] = \Utility::jencode($row["items"]);

                        \WDB::insert("users_products_subscriptions",$row);
                        $collation["others"]["subscriptions"][$old_id] = \WDB::lastID();
                    }
                }

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
                    foreach($orders AS $old_id=>$row)
                    {
                        $data = $row["data"];

                        if(!isset($collation["users"][$data["owner_id"]])) continue;
                        $data["owner_id"] = $collation["users"][$data["owner_id"]];

                        if(isset($data["options"]["category_id"]) && $data["options"]["category_id"])
                        {
                            if(isset($collation["categories"]["products"][$data["options"]["category_id"]]))
                                $data["options"]["category_id"] = $collation["categories"]["products"][$data["options"]["category_id"]];
                            else
                                $data["options"]["category_id"] = 0;
                        }

                        $data["product_id"] = ($collation["products"][($data["type"] == "software" ? "software|" : '').$data["product_id"]]) ?: 0;

                        if($data["type"] == "domain" && $data["product_id"] == 0 && ($data["options"]["tld"] ?? false))
                        {
                            $find_domain = \WDB::select("id")->from("tldlist");
                            $find_domain->where("name","=",$data["options"]["tld"]);
                            $find_domain = $find_domain->build() ? $find_domain->getObject()->id : 0;;
                            if($find_domain) $data["product_id"] = $find_domain;
                        }



                        if(isset($data["options"]["server_id"]) && $data["options"]["server_id"])
                        {
                            $server_id = $data["options"]["server_id"];
                            if(isset($collation["others"]["shared_servers"][$server_id]))
                                $data["options"]["server_id"] = $collation["others"]["shared_servers"][$server_id];
                            else unset($data["options"]["server_id"]);
                        }

                        if(isset($data["type_id"]) && $data["type_id"])
                        {
                            if(isset($collation["categories"]["products"][$data["type_id"]]))
                                $data["type_id"] = $collation["categories"]["products"][$data["type_id"]];
                            else
                                $data["type_id"] = 0;
                        }

                        if($data["type"] == "domain" && ($data["options"]["whois"] ?? []))
                        {
                            $whois = $data["options"]["whois"] ?? [];
                            if($whois["registrant"]["profile_id"] ?? 0)
                            {
                                if(isset($collation["others"]["whois_profiles"][$whois["registrant"]["profile_id"]]))
                                    $data["options"]["whois"]["registrant"]["profile_id"] = $collation["others"]["whois_profiles"][$whois["registrant"]["profile_id"]];
                            }

                            if($whois["administrative"]["profile_id"] ?? 0)
                            {
                                if(isset($collation["others"]["whois_profiles"][$whois["administrative"]["profile_id"]]))
                                    $data["options"]["whois"]["administrative"]["profile_id"] = $collation["others"]["whois_profiles"][$whois["administrative"]["profile_id"]];
                            }

                            if($whois["technical"]["profile_id"] ?? 0)
                            {
                                if(isset($collation["others"]["whois_profiles"][$whois["technical"]["profile_id"]]))
                                    $data["options"]["whois"]["technical"]["profile_id"] = $collation["others"]["whois_profiles"][$whois["technical"]["profile_id"]];
                            }

                            if($whois["billing"]["profile_id"] ?? 0)
                            {
                                if(isset($collation["others"]["whois_profiles"][$whois["billing"]["profile_id"]]))
                                    $data["options"]["whois"]["billing"]["profile_id"] = $collation["others"]["whois_profiles"][$whois["billing"]["profile_id"]];
                            }

                            if($whois["profile_id"] ?? 0)
                            {
                                if(isset($collation["others"]["whois_profiles"][$whois["profile_id"]]))
                                    $data["options"]["whois"]["profile_id"] = $collation["others"]["whois_profiles"][$whois["profile_id"]];
                            }
                        }

                        $data["options"] = \Utility::jencode($data["options"]);

                        if($data["subscription_id"] > 0 && isset($collation["others"]["subscriptions"][$data["subscription_id"]]))
                            $data["subscription_id"] = $collation["others"]["subscriptions"][$data["subscription_id"]];
                        else
                            $data["subscription_id"] = 0;

                        $data["invoice_id"] = 0;


                        $order  = \WDB::insert("users_products",$data);

                        if($order)
                        {
                            $order  = \WDB::lastID();
                            $collation["orders"][$old_id] = $order;



                            if($row["relationships"]["requirements"] ?? [])
                            {
                                foreach($row["relationships"]["requirements"] AS $requirement)
                                {
                                    $requirement["owner_id"] = $order;
                                    if(isset($collation["product_requirements"][$requirement["requirement_id"]]))
                                        $requirement["requirement_id"] = $collation["product_requirements"][$requirement["requirement_id"]];
                                    else
                                        $requirement["requirement_id"] = 0;
                                    \WDB::insert("users_products_requirements",$requirement);
                                }
                            }

                            if($row["relationships"]["addons"] ?? [])
                            {
                                foreach($row["relationships"]["addons"] AS $addon)
                                {
                                    $addon["owner_id"] = $order;
                                    $addon["invoice_id"] = 0;

                                    if($collation["product_addons"][$addon["addon_id"]] ?? 0)
                                        $addon["addon_id"] = $collation["product_addons"][$addon["addon_id"]];
                                    else
                                        $addon["addon_id"] = 0;

                                    if($addon["addon_plink_relid"] > 0)
                                    {
                                        if($collation["products"][$addon["addon_plink_relid"]] ?? false)
                                            $addon["addon_plink_relid"] = $collation["products"][$addon["addon_plink_relid"]];
                                        elseif($collation["products"]["software|".$addon["addon_plink_relid"]] ?? false)
                                            $addon["addon_plink_relid"] = $collation["products"]["software|".$addon["addon_plink_relid"]];
                                        else
                                            $addon["addon_plink_relid"] = 0;
                                    }

                                    if($addon["subscription_id"] > 0 && isset($collation["others"]["subscriptions"][$addon["subscription_id"]]))
                                        $addon["subscription_id"] = $collation["others"]["subscriptions"][$addon["subscription_id"]];
                                    else
                                        $addon["subscription_id"] = 0;

                                    \WDB::insert("users_products_addons",$addon);
                                }
                            }

                            if($row["relationships"]["updown"] ?? [])
                            {
                                foreach($row["relationships"]["updown"] AS $updown)
                                {
                                    $updown["user_id"] = $data["owner_id"];
                                    $updown["owner_id"] = $order;
                                    $updown["invoice_id"] = 0;

                                    if($collation["products"][$updown["old_pid"]] ?? 0)
                                        $updown["old_pid"] = $collation["products"][$updown["old_pid"]];
                                    elseif($collation["products"]["software|".$updown["old_pid"]] ?? 0)
                                        $updown["old_pid"] = $collation["products"]["software|".$updown["old_pid"]];

                                    if($collation["products"][$updown["new_pid"]] ?? 0)
                                        $updown["new_pid"] = $collation["products"][$updown["new_pid"]];
                                    elseif($collation["products"]["software|".$updown["new_pid"]] ?? 0)
                                        $updown["new_pid"] = $collation["products"]["software|".$updown["new_pid"]];

                                    \WDB::insert("users_products_updowngrades",$updown);
                                }
                            }

                            if($row["relationships"]["docs"] ?? [])
                            {
                                foreach($row["relationships"]["docs"] AS $doc)
                                {
                                    $doc["owner_id"]    = $order;
                                    $doc["module_data"] = $this->wisecp_encrypt($this->wisecp_decrypt($doc["module_data"]));
                                    $doc["value"]       = $this->wisecp_encrypt($this->wisecp_decrypt($doc["value"]));
                                    $doc["file"]        = $this->wisecp_encrypt($this->wisecp_decrypt($doc["file"]));

                                    \WDB::insert("users_products_docs",$doc);
                                }
                            }
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

                            \WDB::insert("users_affiliates",$data);

                            $affiliate = \WDB::lastID();

                            $collation["others"]["affiliates"][$old_id] = $affiliate;


                            if(isset($rels["referrers"]) && $rels["referrers"])
                            {
                                foreach($rels["referrers"] AS $rf_id => $rf)
                                {
                                    $rf["affiliate_id"] = $affiliate;
                                    \WDB::insert("users_affiliate_referrers",$rf);
                                    $collation["others"]["affiliates_referrers"][$rf_id] = \WDB::lastID();
                                }
                            }

                            if(isset($rels["hits"]) && $rels["hits"])
                            {
                                foreach($rels["hits"] AS $hit_id => $hit)
                                {
                                    $hit["affiliate_id"]    = $affiliate;
                                    $hit["referrer_id"]     = $collation["others"]["affiliates_referrers"][$hit["referrer_id"]] ?? 0;
                                    \WDB::insert("users_affiliate_hits",$hit);
                                }
                            }

                            if(isset($rels["withdrawals"]) && $rels["withdrawals"])
                            {
                                foreach($rels["withdrawals"] AS $r_id => $w)
                                {
                                    $w["affiliate_id"] = $affiliate;
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
                                            'aff_id' => $affiliate
                                        ])->where("id","=",$new_mid)->save();
                                }
                            }

                            if(isset($rels["transactions"]) && $rels["transactions"] && is_array($rels["transactions"]))
                            {
                                foreach($rels["transactions"] AS $t_id => $t)
                                {
                                    if(isset($collation["orders"][$t["order_id"]]))
                                        $t["order_id"] = $collation["orders"][$t["order_id"]] ?? 0;
                                    $t["affiliate_id"] = $affiliate;
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

                                    $user_pid = $item["user_pid"];
                                    if(isset($collation["orders"][$user_pid]))
                                        $item["user_pid"] = $collation["orders"][$user_pid];
                                    else
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
                            preg_match("/Fatura #([0-9])/",$desc,$match2);
                            if(isset($match[1]) && isset($collation["invoices"][$match[1]]))
                                $desc = str_replace("#".$match[1],"#".$collation["invoices"][$match[1]],$desc);
                            if(isset($match2[1]) && isset($collation["invoices"][$match2[1]]))
                                $desc = str_replace("#".$match2[1],"#".$collation["invoices"][$match2[1]],$desc);

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
                if(!isset($collation["tickets"]["departments"]) && isset($result["tickets"]["departments"]) && $departments = $result["tickets"]["departments"])
                {
                    foreach($departments AS $old_id=>$row)
                    {
                        $data       = $row["data"];
                        $languages  = $row["lang"];
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
                        }
                    }
                }

                if(isset($result["tickets"]["cfields"]) && $cfields = $result["tickets"]["cfields"])
                {
                    foreach($cfields AS $f_old_id => $f_row)
                    {
                        $f_data         = $f_row["data"];
                        $f_languages    = $f_row["lang"];
                        $f_data["did"] = $collation["tickets"]["departments"][$f_data["did"]] ?? 0;
                        $i_field = \WDB::insert("tickets_custom_fields",$f_data);
                        if($i_field){
                            $i_field = \WDB::lastID();
                            if($f_languages){
                                foreach($f_languages AS $l_key=>$language){
                                    $language["owner_id"]   = $i_field;
                                    $language["lang"]       = $l_key;
                                    \WDB::insert("tickets_custom_fields_lang",$language);
                                }
                            }
                            $collation["tickets"]["cfields"][$f_old_id] = $i_field;
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

                        if($fields)
                        {
                            $new_fields = [];
                            foreach($fields AS $f_i=>$f_v){
                                if(isset($collation["tickets"]["cfields"][$f_i])){
                                    $new_fields[$collation["tickets"]["cfields"][$f_i]] = $f_v;
                                }
                            }
                            $data["custom_fields"] = $new_fields;
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