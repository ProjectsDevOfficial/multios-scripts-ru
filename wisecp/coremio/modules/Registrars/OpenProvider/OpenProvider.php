<?php

    class OpenProvider {
        public $api                 = false;
        public $config              = [];
        public $lang                = [];
        public $error               = NULL;
        public $whidden             = [];
        public $order               = [];
        public $docs;
        private $zone               = [];

        function __construct($args=[]){

            $this->config   = Modules::Config("Registrars",__CLASS__);
            $this->lang     = Modules::Lang("Registrars",__CLASS__);

            if(!class_exists("OpenProvider_API")){
                // Calling API files
                include __DIR__.DS."api.php";
            }

            if(isset($this->config["settings"]["whidden-amount"])){
                $whidden_amount   = $this->config["settings"]["whidden-amount"];
                $whidden_currency = $this->config["settings"]["whidden-currency"];
                $this->whidden["amount"] = $whidden_amount;
                $this->whidden["currency"] = $whidden_currency;
            }

            // Set API Credentials
            $username    = $this->config["settings"]["username"];
            $password   = $this->config["settings"]["password"];
            $password   = Crypt::decode($password,Config::get("crypt/system"));
            $test_mode  = $this->config["settings"]["test-mode"] ?? false;

            $this->api  =  new OpenProvider_API();
            $this->api->set_credentials($username,$password,$test_mode);
        }

        public function set_order($order=[]){
            $this->order = $order;
            return $this;
        }

        private function setConfig($username,$password,$sandbox){
            $this->config["settings"]["username"]    = $username;
            $this->config["settings"]["password"]   = $password;
            $this->config["settings"]["test-mode"]  = $sandbox;
            $this->api = new OpenProvider_API();
            $this->api->set_credentials($this->config["settings"]["username"],$this->config["settings"]["password"],$this->config["settings"]["test-mode"]);
        }

        public function getDomainID($domain='')
        {
            $found      = $this->api->call("domains",[
                'queries' => [
                    'full_name' => $domain,
                ],
            ]);

            if(!$found)
            {
                $this->error = $this->api->error;
                return false;
            }

            $id         = 0;

            if($found['data']['total'] > 0) foreach($found['data']['results'] AS $r) $id = $r['id'];
            else
            {
                $this->error = "Not found domain: ".$domain;
                return false;
            }

            return $id;
        }

        public function testConnection($config=[]){

            if(file_exists(__DIR__.DS."TOKEN")) FileManager::file_delete(__DIR__.DS."TOKEN");

            $username    = $config["settings"]["username"];
            $password   = $config["settings"]["password"];
            $sandbox    = $config["settings"]["test-mode"];

            if(!$username || !$password){
                $this->error = $this->lang["error6"];
                return false;
            }

            $password  = Crypt::decode($password,Config::get("crypt/system"));

            $this->setConfig($username,$password,$sandbox);

            $list = $this->api->call("domains");

            if(!$list)
            {
                $this->error = $this->api->error;
                return false;
            }


            return true;
        }

        public function questioning($sld=NULL,$tlds=[]){
            if($sld == '' || empty($tlds)){
                $this->error = $this->lang["error2"];
                return false;
            }
            $sld = idn_to_ascii($sld,0,INTL_IDNA_VARIANT_UTS46);
            if(!is_array($tlds)) $tlds = [$tlds];


            $post_data = [
                "domains" => [],
            ];

            foreach($tlds AS $tld)
            {
                $post_data["domains"][] = [
                    'extension' => $tld,
                    'name' => $sld,
                ];
            }

            $result     = [];

            $check      = $this->api->call("domains/check",$post_data);

            if(isset($check["data"]["results"]) && $check["data"]["results"])
            {
                foreach($check["data"]["results"] AS $r)
                {
                    $pars   = explode($sld,$r["domain"]);
                    $tld    = substr($pars[1] ?? '.com',1);

                    $result[$tld] = [
                        'status' => ($r["status"] ?? "in use") == 'free' ? 'available' : 'unavailable',
                        'premium' => $r["is_premium"] ?? false,
                        'premium_price' => ($r["is_premium"] ?? false) ? ['amount' => (float) $r["premium"]["price"]["create"],'currency' => $this->config["settings"]["cost-currency"] ?? 4] : [],
                    ];


                }
            }
            return $result;
        }

        public function whois_process($data=[])
        {
            $replace_1 = [
                'Ç','Ü','Ö','İ','Ğ','Ş',
                'ç','ü','ö','ı','ğ','ş',
            ];
            $replace_2 = [
                'C','U','O','I','G','S',
                'c','u','o','i','g','s',
            ];

            foreach($data AS $k => $v) $data[$k] = str_replace($replace_1,$replace_2,html_entity_decode($v) ?? '');

            $new_data =  [
                'company_name'  => $data['Company'],
                'email'         => $data["EMail"],
                'name'          => [
                    'first_name'    => $data['FirstName'],
                    'full_name'     => $data['FirstName'].( strlen($data['LastName']) > 0 ? ' '.$data["LastName"] : ''),
                    'last_name'     => $data["LastName"],
                ],
                'address'       => [
                    'city'          => $data["City"],
                    'country'       => $data["Country"],
                    'state'         => $data["State"],
                    'street'        => $data["AddressLine1"],
                    'zipcode'       => $data["ZipCode"],
                ],
                'phone'         => [
                    'area_code'    => "0",
                    'country_code' => "+".$data["PhoneCountryCode"],
                    'subscriber_number' => $data["Phone"],
                ],
            ];

            if(strlen($data["Fax"]) > 3)
            {
                $new_data['fax'] = [
                    'area_code' => "0",
                    'country_code' => "+".$data["FaxCountryCode"],
                    'subscriber_number' => $data["Fax"],
                ];
            }

            return $new_data;
        }

        public function whois_process_reverse($data=[])
        {
            $new_data       = [
                'FirstName'         =>  $data["name"]["first_name"],
                'LastName'          =>  $data["name"]["last_name"],
                'Name'              =>  $data["name"]["full_name"],
                'Company'           =>  $data["company_name"],
                'EMail'             =>  $data["email"],
                'AddressLine1'      =>  $data["address"]["street"],
                'AddressLine2'      =>  '',
                'City'              =>  $data["address"]["city"],
                'State'             =>  $data["address"]["state"],
                'ZipCode'           =>  $data["address"]["zipcode"],
                'Country'           =>  $data["address"]["country"],
                'PhoneCountryCode'  => substr($data["phone"]["country_code"],1),
                'Phone'             => $data["phone"]["subscriber_number"],
                'FaxCountryCode'    => '',
                'Fax'               => '',
            ];

            if(isset($data["fax"]) && $data["fax"])
            {
                $new_data["FaxCountryCode"] = $data["fax"]["country_code"];
                $new_data["Fax"] = $data["fax"]["subscriber_number"];
            }


            return $new_data;
        }

        public function register($domain='',$sld='',$tld='',$year=1,$dns=[],$whois=[],$wprivacy=false,$epp_code=''){
            $domain   = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $sld      = idn_to_ascii($sld,0,INTL_IDNA_VARIANT_UTS46);

            $name_servers = [];

            if($dns) foreach($dns AS $d) $name_servers[] = ['name' => $d];

            $owner              = $this->api->call("customers",$this->whois_process($whois["registrant"]));
            if(!$owner)
            {
                $this->error = $this->api->error;
                return false;
            }
            $owner_handle       = $owner["data"]["handle"];

            $admin              = $this->api->call("customers",$this->whois_process($whois["administrative"]));
            if(!$admin)
            {
                $this->error = $this->api->error;
                return false;
            }
            $admin_handle       = $admin["data"]["handle"];

            $tech              = $this->api->call("customers",$this->whois_process($whois["technical"]));
            if(!$tech)
            {
                $this->error = $this->api->error;
                return false;
            }
            $tech_handle       = $tech["data"]["handle"];

            $billing              = $this->api->call("customers",$this->whois_process($whois["billing"]));
            if(!$billing)
            {
                $this->error = $this->api->error;
                return false;
            }
            $billing_handle       = $billing["data"]["handle"];



            $params     = [
                'domain' => [
                    'name' => $sld,
                    'extension' => $tld,
                ],
                'owner_handle'      => $owner_handle,
                'admin_handle'      => $admin_handle,
                'tech_handle'       => $tech_handle,
                'billing_handle'    => $billing_handle,
                'period'            => $year,
                'name_servers'      => $name_servers,
                'autorenew'         => 'default',
                'is_private_whois_enabled' => $wprivacy,
            ];

            if($epp_code) $params['auth_code'] = $epp_code;



            $process       = $this->api->call("domains/".($epp_code ? "transfer" : ""),$params);

            if(!$process)
            {
                $this->api->call("customers/".$owner_handle,false,false,'DELETE');
                $this->api->call("customers/".$admin_handle,false,false,'DELETE');
                $this->api->call("customers/".$tech_handle,false,false,'DELETE');
                $this->api->call("customers/".$billing_handle,false,false,'DELETE');

                $this->error = $this->api->error;
                return false;
            }

            $expDate    = $process['data']['renewal_date'] ?? ($process['data']['expiration_date'] ?? '');

            $returnData = [
                'status' => "SUCCESS",
                'config' => [
                    'entityID' => $process['data']['id'],
                ],
            ];

            if($expDate)
                $returnData['change'] = [
                    'duedate' => $expDate,
                ];

            if($wprivacy) $returnData["whois_privacy"] = ['status' => true,'message' => NULL];

            return $returnData;
        }

        public function transfer($domain='',$sld='',$tld='',$year=1,$dns=[],$whois=[],$wprivacy=false,$eppCode=''){
            return $this->register($domain,$sld,$tld,$year,$dns,$whois,$wprivacy,$eppCode);
        }

        public function renewal($params=[],$domain='',$sld='',$tld='',$year=1,$oduedate='',$nduedate=''){
            $domain   = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $sld      = idn_to_ascii($sld,0,INTL_IDNA_VARIANT_UTS46);

            $domain_id      = $this->getDomainID($domain);

            if(!$domain_id) return false;

            $process        = $this->api->call("domains/".$domain_id."/renew",[
                'domain' => [
                    'name'          => $sld,
                    'extension'     => $tld,
                ],
                'id'                => $domain_id,
                'period'            => $year
            ]);

            if(!$process)
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function ModifyWhois($params=[],$whois=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $domain_id  = $this->getDomainID($domain);

            if(!$domain_id) return false;

            $detail         = $this->api->call("domains/".$domain_id);

            if(!$detail)
            {
                $this->error = $this->api->error;
                return false;
            }

            $ct_types       = [
                'owner'     => 'registrant',
                'admin'     => 'administrative',
                'tech'      => "technical",
                'billing'   => 'billing',
            ];

            foreach($ct_types AS $ct1 => $ct2)
            {
                if(isset($detail['data'][$ct1."_handle"]) && $detail['data'][$ct1."_handle"])
                {
                    $contact_id         = $detail['data'][$ct1."_handle"];
                    $w_data             = $this->whois_process($whois[$ct2]);

                    $process            = $this->api->call("customers/".$contact_id,$w_data,false,'PUT');

                    if(!$process)
                    {
                        $this->error = $this->api->error;
                        return false;
                    }
                }
            }

            return true;
        }

        public function ModifyDns($params=[],$dns=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $domain_id  = $this->getDomainID($domain);

            if(!$domain_id) return false;


            $rows       = [];

            if($dns) foreach($dns AS $dn) $rows[] = ['name' => idn_to_ascii($dn,0,INTL_IDNA_VARIANT_UTS46)];

            $process        = $this->api->call("domains/".$domain_id,['name_servers' => $rows],false,'PUT');

            if(!$process)
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function getTransferLock($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $domain_id  = $this->getDomainID($domain);

            if(!$domain_id) return false;


            $get        = $this->api->call("domains/".$domain_id);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            return $get["data"]["is_locked"] ? "active" : "passive";
        }

        public function ModifyTransferLock($params=[],$status=''){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);
            $domain_id  = $this->getDomainID($domain);
            if(!$domain_id) return false;

            $process    = $this->api->call("domains/".$domain_id,[
                'is_locked' => $status == "enable",
            ],false,'PUT');

            if(!$process)
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function getWhoisPrivacy($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);
            $domain_id  = $this->getDomainID($domain);

            if(!$domain_id) return false;

            $get        = $this->api->call("domains/".$domain_id);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            return $get["data"]["is_private_whois_enabled"] ? "active" : "passive";
        }

        public function modifyPrivacyProtection($params=[],$status=''){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);
            $domain_id  = $this->getDomainID($domain);

            if(!$domain_id) return false;


            $process    = $this->api->call("domains/".$domain_id,[
                'is_private_whois_enabled' => $status == "enable"
            ],false,'PUT');

            if(!$process)
            {
                $this->error = $this->api->error;
                return false;
            }


            return true;
        }

        public function CNSList($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);
            $result     = [];

            $get        = $this->api->call("dns/nameservers",[
                'queries' => [
                    'pattern' => "*.".$domain,
                    'order_by' =>  "id",
                ],
            ]);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            if(isset($get["data"]["total"]) && $get["data"]["total"] > 0)
            {
                foreach($get["data"]["results"] AS $r)
                {
                    $name       = $r["name"] ?? 'none';
                    $ip         = $r["ip"] ?? false;

                    $result[] = [
                        'ns' => $name,
                        'ip' => $ip,
                    ];
                }
            }
            return $result;
        }

        public function addCNS($params=[],$ns='',$ip=''){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);
            $ns         = idn_to_ascii($ns,0,INTL_IDNA_VARIANT_UTS46);


            $process        = $this->api->call("dns/nameservers",[
                'name'      => $ns,
                'ip'        => $ip,
            ]);

            if(!$process)
            {
                $this->error = $this->api->error;
                return false;
            }

            return [
                'ns'        => $ns,
                'ip'        => $ip,
            ];
        }

        public function ModifyCNS($params=[],$old=[],$new_ns='',$new_ip=''){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $process        = $this->api->call("dns/nameservers/".$old['ns'],[
                'name'      => $new_ns,
                'ip'        => $new_ip,
            ],false,'PUT');

            if(!$process)
            {
                $this->error = $this->api->error;
                return false;
            }


            return true;
        }

        public function DeleteCNS($params=[],$ns='',$ip=''){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);
            $ns         = idn_to_ascii($ns,0,INTL_IDNA_VARIANT_UTS46);

            $process        = $this->api->call("dns/nameservers/".$ns,false,false,'DELETE');

            if(!$process)
            {
                $this->error = $this->api->error;
                return false;
            }

            if(!$process['data']['success'])
            {
                $this->error = 'action failed';
                return false;
            }


            return true;

        }


        public function getAuthCode($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);
            $domain_id  = $this->getDomainID($domain);

            if(!$domain_id) return false;

            $get        = $this->api->call("domains/".$domain_id."/authcode");

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            return $get["data"]["auth_code"] ?? 'unknown';
        }


        public function isInactive($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);
            $domain_id  = $this->getDomainID($domain);

            if(!$domain_id) return false;


            $get        = $this->api->call("domains/".$domain_id);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            return DateManager::strtotime() > DateManager::strtotime($get["data"]["expiration_date"]);
        }

        public function sync($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $domain_id          = $this->getDomainID($domain);

            if(!$domain_id) return false;


            $get        = $this->api->call("domains/".$domain_id);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }


            $expDate            = $get["data"]['renewal_date'] ?? ($get["data"]['expiration_date'] ?? '');
            $start              = DateManager::format("Y-m-d H:i:s",$get["data"]["creation_date"]);
            $end                = DateManager::format("Y-m-d H:i:s",$expDate);

            if(DateManager::strtotime() > DateManager::strtotime($get["data"]["expiration_date"]))
                $status = 'expired';
            else
                $status = 'active';



            $return_data    = [
                'creationtime'  => $start,
                'endtime'       => $end,
                'status'        => $status,
            ];

            return $return_data;
        }

        public function transfer_sync($params=[]){
            return $this->sync($params);
        }

        public function get_info($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $domain_id  = $this->getDomainID($domain);

            if(!$domain_id) return false;


            $get        = $this->api->call("domains/".$domain_id);


            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }


            $result             = [];

            $cdate              = DateManager::format("Y-m-d H:i:s",$get["data"]["creation_date"]);
            $duedate            = DateManager::format("Y-m-d H:i:s",$get["data"]["expiration_date"]);

            $wprivacy           = $get["data"]["is_private_whois_enabled"];

            $ns1                = false;
            $ns2                = false;
            $ns3                = false;
            $ns4                = false;


            if(isset($get['data']['name_servers']) && $get['data']['name_servers'])
                foreach($get['data']['name_servers'] AS $k => $ns) ${"ns".'ns'.($k+1)} = $ns["name"];


            $ct_types       = [
                'owner' => 'registrant',
                'admin' => 'administrative','tech',
                'billing' => 'billing',
            ];

            $whois      = [];

            foreach($ct_types AS $ct1 => $ct2)
            {
                if(isset($get['data'][$ct1."_handle"]) && $get['data'][$ct1."_handle"])
                {
                    $contact_id         = $get['data'][$ct1."_handle"];

                    $w_data             = $this->api->call("customers/".$contact_id);

                    if($w_data)
                    {
                        $w_data             = $this->whois_process_reverse($w_data["data"]);
                        if($w_data) $whois[$ct2] = $w_data;
                    }
                }
            }



            $result["creation_time"]    = $cdate;
            $result["end_time"]         = $duedate;

            if(isset($wprivacy) && $wprivacy !== "none"){
                $result["whois_privacy"] = ['status' => $wprivacy ? "enable" : "disable"];

                if($wprivacy && isset($get["data"]["whois_privacy_data"]["expiration_date"]))
                {
                    $result["whois_privacy"]["end_time"] = DateManager::format("Y-m-d H:i:s",$get["data"]["whois_privacy_data"]["expiration_date"]);
                }
            }

            if(isset($ns1) && $ns1) $result["ns1"] = $ns1;
            if(isset($ns2) && $ns2) $result["ns2"] = $ns2;
            if(isset($ns3) && $ns3) $result["ns3"] = $ns3;
            if(isset($ns4) && $ns4) $result["ns4"] = $ns4;
            if(isset($whois) && $whois) $result["whois"] = $whois;
            $result["transferlock"] = $get["data"]["is_locked"];

            return $result;

        }

        public function domains(){
            Helper::Load(["User"]);

            $get        = $this->api->call("domains",[
                'queries' => [
                    'limit' => 1000
                ]
            ]);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            $result     = [];

            if(isset($get["data"]["total"]) && $get["data"]["total"] > 0){
                foreach($get["data"]["results"] AS $res){

                    $expDate    = $res['renewal_date'] ?? ($res['expiration_date'] ?? '');
                    $cdate      = DateManager::format("Y-m-d H:i:s",$res["creation_date"]);
                    $domain     = $res['domain']["name"].".".$res["domain"]["extension"];

                    if($domain){
                        $domain      = idn_to_utf8($domain,0,INTL_IDNA_VARIANT_UTS46);
                        $order_id    = 0;
                        $user_data   = [];
                        $is_imported = Models::$init->db->select("id,owner_id AS user_id")->from("users_products");
                        $is_imported->where("type",'=',"domain","&&");
                        $is_imported->where("name",'=',$domain);
                        $is_imported = $is_imported->build() ? $is_imported->getAssoc() : false;
                        if($is_imported){
                            $order_id   = $is_imported["id"];
                            $user_data  =  User::getData($is_imported["user_id"],"id,full_name,company_name","array");
                        }

                        $result[] = [
                            'domain'            => $domain,
                            'creation_date'     => $cdate,
                            'end_date'          => $expDate,
                            'order_id'          => $order_id,
                            'user_data'        => $user_data,
                        ];
                    }
                }
            }

            return $result;
        }

        public function import_domain($data=[]){
            $config     = $this->config;

            $imports = [];

            Helper::Load(["Orders","Products","Money"]);

            foreach($data AS $domain=>$datum){
                $domain_parse   = Utility::domain_parser("http://".$domain);
                $sld            = $domain_parse["host"];
                $tld            = $domain_parse["tld"];
                $user_id        = (int) $datum["user_id"];
                if(!$user_id) continue;
                $info           = $this->get_info([
                    'domain'    => $domain,
                    'name'      => $sld,
                    'tld'       => $tld,
                ]);
                if(!$info) continue;

                $user_data          = User::getData($user_id,"id,lang","array");
                $ulang              = $user_data["lang"];
                $locallang          = Config::get("general/local");
                $productID          = Models::$init->db->select("id")->from("tldlist")->where("name","=",$tld);
                $productID          = $productID->build() ? $productID->getObject()->id : false;
                if(!$productID) continue;
                $productPrice       = Products::get_price("register","tld",$productID);
                $productPrice_amt   = $productPrice["amount"];
                $productPrice_cid   = $productPrice["cid"];
                $start_date         = $info["creation_time"];
                $end_date           = $info["end_time"];
                $year               = 1;

                $options            = [
                    "established"         => true,
                    "group_name"          => Bootstrap::$lang->get_cm("website/account_products/product-type-names/domain",false,$ulang),
                    "local_group_name"    => Bootstrap::$lang->get_cm("website/account_products/product-type-names/domain",false,$locallang),
                    "category_id"         => 0,
                    "domain"              => $domain,
                    "name"                => $sld,
                    "tld"                 => $tld,
                    "dns_manage"          => true,
                    "whois_manage"        => true,
                    "transferlock"        => $info["transferlock"],
                    "cns_list"            => isset($info["cns"]) ? $info["cns"] : [],
                    "whois"               => isset($info["whois"]) ? $info["whois"] : [],
                ];

                if(isset($info["whois_privacy"]) && $info["whois_privacy"]){
                    $options["whois_privacy"] = $info["whois_privacy"]["status"] == "enable";
                    $wprivacy_endtime   = DateManager::ata();
                    if(isset($info["whois_privacy"]["end_time"]) && $info["whois_privacy"]["end_time"]){
                        $wprivacy_endtime = $info["whois_privacy"]["end_time"];
                        $options["whois_privacy_endtime"] = $wprivacy_endtime;
                    }
                }

                if(isset($info["ns1"]) && $info["ns1"]) $options["ns1"] = $info["ns1"];
                if(isset($info["ns2"]) && $info["ns2"]) $options["ns2"] = $info["ns2"];
                if(isset($info["ns3"]) && $info["ns3"]) $options["ns3"] = $info["ns3"];
                if(isset($info["ns4"]) && $info["ns4"]) $options["ns4"] = $info["ns4"];



                $order_data             = [
                    "owner_id"          => (int) $user_id,
                    "type"              => "domain",
                    "product_id"        => (int) $productID,
                    "name"              => $domain,
                    "period"            => "year",
                    "period_time"       => (int) $year,
                    "amount"            => (float) $productPrice_amt,
                    "total_amount"      => (float) $productPrice_amt,
                    "amount_cid"        => (int) $productPrice_cid,
                    "status"            => "active",
                    "cdate"             => $start_date,
                    "duedate"           => $end_date,
                    "renewaldate"       => DateManager::Now(),
                    "module"            => __CLASS__,
                    "options"           => Utility::jencode($options),
                    "unread"            => 1,
                ];

                $insert                 = Orders::insert($order_data);
                if(!$insert) continue;

                if(isset($options["whois_privacy"])){
                    $amount = Money::exChange($this->whidden["amount"],$this->whidden["currency"],$productPrice_cid);
                    $start  = DateManager::Now();
                    $end    = isset($wprivacy_endtime) ? $wprivacy_endtime : DateManager::ata();
                    Orders::insert_addon([
                        'invoice_id' => 0,
                        'owner_id' => $insert,
                        "addon_key"     => "whois-privacy",
                        'addon_id' => 0,
                        'addon_name' => Bootstrap::$lang->get_cm("website/account_products/whois-privacy",false,$ulang),
                        'option_id'  => 0,
                        "option_name"   => Bootstrap::$lang->get("needs/iwwant",$ulang),
                        'period'       => 1,
                        'period_time'  => "year",
                        'status'       => "active",
                        'cdate'        => $start,
                        'renewaldate'  => $start,
                        'duedate'      => $end,
                        'amount'       => $amount,
                        'cid'          => $productPrice_cid,
                        'unread'       => 1,
                    ]);
                }
                $imports[] = $order_data["name"]." (#".$insert.")";
            }

            if($imports){
                $adata      = UserManager::LoginData("admin");
                User::addAction($adata["id"],"alteration","domain-imported",[
                    'module'   => $config["meta"]["name"],
                    'imported'  => implode(", ",$imports),
                ]);
            }

            return $imports;
        }

        private function ct_zone()
        {
            $domain     = idn_to_ascii($this->order["name"],0,INTL_IDNA_VARIANT_UTS46);
            $sld        = idn_to_ascii($this->order["options"]["name"] ?? $this->order["options"]["sld"],0,INTL_IDNA_VARIANT_UTS46);

            $ct_zone       = $this->api->call("dns/zones/".$domain,[
                'queries' => ['with_records' => true],
            ]);

            if(!$ct_zone)
            {
                $create     = $this->api->call("dns/zones",[
                    'domain' => [
                        'name' => $sld,
                        'extension' => $this->order["options"]["tld"],
                    ],
                    'records' => [],
                    'type' => "master",
                ],false,'POST');

                if(!$create)
                {
                    $this->error = $this->api->error;
                    return false;
                }
                $ct_zone       = $this->api->call("dns/zones/".$domain,[
                    'queries' => ['with_records' => true],
                ]);
            }

            if(!$ct_zone)
            {
                $this->error = $this->api->error;
                return false;
            }
            $this->zone  = $ct_zone;
            return $ct_zone;
        }

        public function getDnsRecords()
        {

            $result         = [];

            $ct_zone        = $this->ct_zone();

            if(!$ct_zone) return false;

            $records        = $ct_zone["data"]["records"] ?? [];

            if($records)
            {
                foreach($records AS $k => $r)
                {
                    $result[] = [
                        'identity'      => $k,
                        'type'          => $r["type"],
                        'name'          => $r["name"],
                        'value'         => $r["value"],
                        'ttl'           => $r["ttl"],
                        'priority'      => $r["prio"] ?? 7207,
                    ];
                }
            }

            return $result;

        }
        public function addDnsRecord($type,$name,$value,$ttl,$priority)
        {
            if(!$priority) $priority = 10;
            if(!$ttl) $ttl = 7207;

            if($ttl < 3600) $ttl = 7207;

            $ct_zone    = $this->ct_zone();

            $domain     = idn_to_ascii($this->order["name"],0,INTL_IDNA_VARIANT_UTS46);

            $params     = [
                'id'        => $ct_zone['data']['id'],
                'name'      => $domain,
                'records'   => [
                ],
            ];

            $item   = [
                'type'      => $type,
                'name'      => $name,
                'value'     => $value,
                'ttl'       => $ttl,
                'prio'      => $priority,
            ];

            if($type != "MX") unset($item["prio"]);
            if($name == "@" || $name == $domain) unset($item["name"]);

            $params["records"]["add"] = [$item];

            $apply      = $this->api->call("dns/zones/".$domain,$params,false,'PUT');


            if(!$apply)
            {
                $this->error = $this->api->error;
                return false;
            }


            return true;
        }
        public function updateDnsRecord($type='',$name='',$value='',$identity='',$ttl='',$priority='')
        {
            $records        = $this->getDnsRecords();

            $domain     = idn_to_ascii($this->order["options"]["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $original_records   = [];
            $updated_records    = [];

            if($records)
            {
                foreach($records AS $k => $r)
                {
                    if($k == (int) $identity)
                    {
                        $replace_item   = [
                            'name'  => $name,
                            'type'  => $type,
                            'value' => $value,
                            'ttl'   => $ttl,
                            'prio'  => $priority,
                        ];

                        $org_item   = [
                            'name'  => $r['name'],
                            'type'  => $r['type'],
                            'value' => $r['value'],
                            'ttl'   => $r['ttl'],
                            'prio'  => $r['priority'],
                        ];

                        if($org_item['type'] != "MX") unset($org_item["prio"]);
                        if($org_item['name'] == "@" || $org_item['name'] == $domain) unset($org_item["name"]);

                        if($replace_item['type'] != "MX") unset($replace_item["prio"]);
                        if($replace_item['name'] == "@" || $replace_item['name'] == $domain) unset($replace_item["name"]);

                        $original_records = $org_item;
                        $updated_records = $replace_item;
                    }
                }
            }

            $params     = [
                'id'        => $this->zone['data']['id'],
                'name'      => $domain,
                'records' => [
                    'update' => [
                        [
                            'original_record'   => $original_records,
                            'record'            => $updated_records,
                        ]
                    ],
                ],
            ];

            $apply      = $this->api->call("dns/zones/".$domain,$params,false,'PUT');

            if(!$apply)
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }
        public function deleteDnsRecord($type='',$name='',$value='',$identity='')
        {

            $domain     = idn_to_ascii($this->order["options"]["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $records        = $this->getDnsRecords();
            $current        = [];
            if($records) foreach($records AS $k => $r) if($k == (int) $identity) $current = $r;

            if(!$current)
            {
                $this->error = "Invalid ID";
                return false;
            }

            $remove = [];

            $item   = [
                'name'      => $current["name"],
                'prio'      => $current["priority"],
                'ttl'       => $current["ttl"],
                'type'      => $current["type"],
                'value'     => $current["value"],
            ];

            if($item['type'] != "MX") unset($item["prio"]);
            if($item['name'] == "@" || $item['name'] == $domain) unset($item["name"]);

            $remove[] = $item;


            $apply      = $this->api->call("dns/zones/".$domain,[
                'id'    => $this->zone['data']['id'],
                'name'  => $domain,
                'records' => [
                    'remove' => $remove,
                ],
            ],false,'PUT');

            if(!$apply)
            {
                $this->error = $this->api->error;
                return false;
            }


            return true;
        }

        public function cost_prices($type='domain'){

            $prices    = $this->api->call("tlds",['queries' => ['with_price' => true]]);
            if(!$prices){
                $this->error = $this->api->error;
                return false;
            }

            $result = [];

            $prices         = $prices['data']['results'];

            if($type == "domain"){
                foreach($prices AS $row){

                    $name       = $row["name"];


                    $result[$name] = [
                        'register' => $row["prices"]["create_price"]["reseller"]["price"],
                        'transfer' => $row["prices"]["transfer_price"]["reseller"]["price"],
                        'renewal'  => $row["prices"]["renew_price"]["reseller"]["price"],
                        'min_year' => $row["min_period"] ?? 1,
                        'max_year' => $row["max_period"] ?? 10,
                        'epp_code' => $row["is_transfer_auth_code_required"] ?? true,
                    ];
                }
            }

            return $result;
        }


        public function apply_import_tlds(){

            $cost_cid           = $this->config["settings"]["cost-currency"] ?? 4;

            $prices             = $this->cost_prices();
            if(!$prices) return false;

            Helper::Load(["Products","Money"]);

            $profit_rate        = Config::get("options/domain-profit-rate");

            foreach($prices AS $name=>$val){
                $api_cost_prices    = [
                    'register' => round($val["register"],2),
                    'transfer' => round($val["transfer"],2),
                    'renewal'  => round($val["renewal"],2),
                ];

                $paperwork      = 0;
                $epp_code       = $val["epp_code"] ? 1 : 0;
                $dns_manage     = 1;
                $whois_privacy  = 1;
                $module         = $this->config["meta"]["name"];

                $check          = Models::$init->db->select()->from("tldlist")->where("name","=",$name);

                if($check->build()){
                    $tld        = $check->getAssoc();
                    $pid        = $tld["id"];

                    $reg_price = Products::get_price("register","tld",$pid);
                    $ren_price = Products::get_price("renewal","tld",$pid);
                    $tra_price = Products::get_price("transfer","tld",$pid);

                    $tld_cid    = $reg_price["cid"];


                    $register_cost  = Money::deformatter($api_cost_prices["register"]);
                    $renewal_cost   = Money::deformatter($api_cost_prices["renewal"]);
                    $transfer_cost  = Money::deformatter($api_cost_prices["transfer"]);

                    // ExChanges
                    $register_cost  = Money::exChange($register_cost,$cost_cid,$tld_cid);
                    $renewal_cost   = Money::exChange($renewal_cost,$cost_cid,$tld_cid);
                    $transfer_cost  = Money::exChange($transfer_cost,$cost_cid,$tld_cid);


                    $reg_profit     = Money::get_discount_amount($register_cost,$profit_rate);
                    $ren_profit     = Money::get_discount_amount($renewal_cost,$profit_rate);
                    $tra_profit     = Money::get_discount_amount($transfer_cost,$profit_rate);

                    $register_sale  = $register_cost + $reg_profit;
                    $renewal_sale   = $renewal_cost + $ren_profit;
                    $transfer_sale  = $transfer_cost + $tra_profit;

                    Products::set("domain",$pid,[
                        'paperwork'         => $paperwork,
                        'epp_code'          => $epp_code,
                        'min_years'          => $val["min_year"],
                        'max_years'          => $val["max_year"],
                        'dns_manage'        => $dns_manage,
                        'whois_privacy'     => $whois_privacy,
                        'register_cost'     => $register_cost,
                        'renewal_cost'      => $renewal_cost,
                        'transfer_cost'     => $transfer_cost,
                        'module'            => $module,
                    ]);

                    Models::$init->db->update("prices",[
                        'amount' => $register_sale,
                        'cid'    => $tld_cid,
                    ])->where("id","=",$reg_price["id"])->save();


                    Models::$init->db->update("prices",[
                        'amount' => $renewal_sale,
                        'cid'    => $tld_cid,
                    ])->where("id","=",$ren_price["id"])->save();


                    Models::$init->db->update("prices",[
                        'amount' => $transfer_sale,
                        'cid'    => $tld_cid,
                    ])->where("id","=",$tra_price["id"])->save();

                }
                else{

                    $tld_cid    = $cost_cid;

                    $register_cost  = Money::deformatter($api_cost_prices["register"]);
                    $renewal_cost   = Money::deformatter($api_cost_prices["renewal"]);
                    $transfer_cost  = Money::deformatter($api_cost_prices["transfer"]);


                    $reg_profit     = Money::get_discount_amount($register_cost,$profit_rate);
                    $ren_profit     = Money::get_discount_amount($renewal_cost,$profit_rate);
                    $tra_profit     = Money::get_discount_amount($transfer_cost,$profit_rate);

                    $register_sale  = $register_cost + $reg_profit;
                    $renewal_sale   = $renewal_cost + $ren_profit;
                    $transfer_sale  = $transfer_cost + $tra_profit;

                    $insert                 = Models::$init->db->insert("tldlist",[
                        'status'            => "inactive",
                        'cdate'             => DateManager::Now(),
                        'name'              => $name,
                        'paperwork'         => $paperwork,
                        'epp_code'          => $epp_code,
                        'dns_manage'        => $dns_manage,
                        'min_years'          => $val["min_year"],
                        'max_years'          => $val["max_year"],
                        'whois_privacy'     => $whois_privacy,
                        'currency'          => $tld_cid,
                        'register_cost'     => $register_cost,
                        'renewal_cost'      => $renewal_cost,
                        'transfer_cost'     => $transfer_cost,
                        'module'            => $module,
                    ]);

                    if($insert){
                        $tld_id         = Models::$init->db->lastID();

                        Models::$init->db->insert("prices",[
                            'owner'     => "tld",
                            'owner_id'  => $tld_id,
                            'type'      => 'register',
                            'amount'    => $register_sale,
                            'cid'       => $tld_cid,
                        ]);


                        Models::$init->db->insert("prices",[
                            'owner'     => "tld",
                            'owner_id'  => $tld_id,
                            'type'      => 'renewal',
                            'amount'    => $renewal_sale,
                            'cid'       => $tld_cid,
                        ]);


                        Models::$init->db->insert("prices",[
                            'owner'     => "tld",
                            'owner_id' => $tld_id,
                            'type'      => 'transfer',
                            'amount'    => $transfer_sale,
                            'cid'       => $tld_cid,
                        ]);
                    }

                }
            }
            return true;
        }




    }