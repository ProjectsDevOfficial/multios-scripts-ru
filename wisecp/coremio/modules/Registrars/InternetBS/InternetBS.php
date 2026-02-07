<?php

    class InternetBS {
        public $api                = false;
        public $config             = [];
        public $lang               = [];
        public  $error             = NULL;
        public  $whidden           = [];
        public $order              = [];

        function __construct($args=[]){

            $this->config   = Modules::Config("Registrars",__CLASS__);
            $this->lang     = Modules::Lang("Registrars",__CLASS__);

            if(!class_exists("InternetBS_API")){
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
            $api_key    = $this->config["settings"]["api-key"];
            $password   = $this->config["settings"]["password"];
            $password   = Crypt::decode($password,Config::get("crypt/system"));
            $test_mode  = $this->config["settings"]["test-mode"] ?? false;

            $this->api  =  new InternetBS_API();
            $this->api->set_credentials($api_key,$password,$test_mode);
        }

        public function set_order($order=[]){
            $this->order = $order;
            return $this;
        }

        private function setConfig($api_key,$password,$sandbox){
            $this->config["settings"]["api-key"]    = $api_key;
            $this->config["settings"]["password"]   = $password;
            $this->config["settings"]["test-mode"]  = $sandbox;
            $this->api = new InternetBS_API();
            $this->api->set_credentials($this->config["settings"]["api-key"],$this->config["settings"]["password"],$this->config["settings"]["test-mode"]);
        }

        public function suspend($params=[]){
            return true;
        }
        public function unsuspend($params=[]){
            return true;
        }
        public function terminate($params=[]){
            return true;
        }

        public function testConnection($config=[]){
            $api_key    = $config["settings"]["api-key"];
            $password   = $config["settings"]["password"];
            $sandbox    = $config["settings"]["test-mode"];

            if(!$api_key || !$password){
                $this->error = $this->lang["error6"];
                return false;
            }

            $password  = Crypt::decode($password,Config::get("crypt/system"));

            $this->setConfig($api_key,$password,$sandbox);

            $list = $this->api->call("Domain/List");

            if(!$list && $this->api->error){
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


            $result = [];

            foreach($tlds AS $tld)
            {
                $check      = $this->api->call("Domain/Check",['Domain' => $sld.".".$tld]);

                if(isset($check["status"]) && $check["status"])
                {
                    if($check["status"] == "FAILURE") $check["status"] = "AVAILABLE";

                    $premium = ($check["price"]["ispremium"] ?? 'NO') == "YES";
                    $result[$tld] = [
                        'status' => strtolower($check["status"]),
                        'premium' => $premium,
                        'premium_price' => $premium ? ['amount' => (float) $check['price']['registration'][1],'currency' => $check['price']['currency']] : [],
                    ];

                }
            }
            return $result;
        }

        public function whois_process($data=[])
        {
            $new_data = [];

            $key_replace = [
                'Registrant'    => "registrant",
                'Admin'         => "administrative",
                'Technical'     => "technical",
                'Billing'       => "billing",
            ];

            foreach(array_keys($key_replace) AS $t)
            {
                $a_data         = $data[$key_replace[$t]] ?? $data;

                $new_data[$t.'_FirstName'] = $a_data['FirstName'];
                $new_data[$t.'_LastName'] = $a_data['LastName'];
                $new_data[$t.'_Organization'] = $a_data['Company'];
                $new_data[$t.'_Email'] = $a_data['EMail'];
                $new_data[$t.'_PhoneNumber'] = "+".$a_data['PhoneCountryCode'].".".$a_data['Phone'];
                $new_data[$t.'_Fax'] = strlen($a_data['Fax']) > 0 ? "+".$a_data['FaxCountryCode'].".".$a_data['Fax'] : '';
                $new_data[$t.'_Street2'] = $a_data['AddressLine1'].(strlen($a_data['AddressLine2']) > 1 ? $a_data['AddressLine2'] : '');
                $new_data[$t.'_PostalCode'] = $a_data['ZipCode'];
                $new_data[$t.'_City'] = $a_data['City'];
                $new_data[$t.'_Street'] = $a_data['State'];
                $new_data[$t.'_CountryCode'] = $a_data['Country'];
            }

            return $new_data;
        }

        public function register($domain='',$sld='',$tld='',$year=1,$dns=[],$whois=[],$wprivacy=false,$epp_code=''){
            $domain   = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $sld      = idn_to_ascii($sld,0,INTL_IDNA_VARIANT_UTS46);

            $params     = [
                'Domain' => $domain,
                'Period' => $year."Y",
                'Ns_list' => implode(",",$dns),
                'registrarLock' => 'ENABLED',
                'privateWhois' => $wprivacy ? 'FULL' : 'DISABLE',
            ];

            if($epp_code) $params['transferAuthInfo'] = $epp_code;


            $params        = array_merge($params,$this->whois_process($whois));

            $process       = $this->api->call($epp_code ? "Domain/Transfer/Initiate" : "Domain/Create",$params);

            if(!$process)
            {
                $this->error = $this->api->error;
                return false;
            }


            $returnData = [
                'status' => "SUCCESS",
                'config' => [
                    'entityID' => $process['TRANSACTID'],
                ],
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

            $process        = $this->api->call("Domain/Renew",[
                'Domain'        => $domain,
                'Period'        => $year."Y",
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


            $new_params     = [
                'Domain' => $domain
            ];

            $new_params     = array_merge($new_params,$this->whois_process($whois));

            $process        = $this->api->call("Domain/Update",$new_params);

            if(!$process)
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function resend_verification_mail($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);


            $process    = $this->api->call("Domain/RegistrantVerification/Send",['Domain' => $domain]);

            if(!$process)
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function ModifyDns($params=[],$dns=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            if($dns) foreach($dns AS $i=>$dn) $dns[$i] = idn_to_ascii($dn,0,INTL_IDNA_VARIANT_UTS46);

            $process        = $this->api->call("Domain/Update",[
                'Domain' => $domain,
                'Ns_list' => implode(",",$dns),
            ]);

            if(!$process)
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function getTransferLock($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $get        = $this->api->call("Domain/RegistrarLock/Status",[
                'Domain'        => $domain,
            ]);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            return $get["registrar_lock_status"] == "LOCKED" ? "active" : "passive";
        }
        public function ModifyTransferLock($params=[],$status=''){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $process    = $this->api->call("Domain/RegistrarLock/".($status == "enable" ? "Enable" : "Disable"),[
                'Domain' => $domain,
            ]);

            if(!$process)
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function getWhoisPrivacy($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $get        = $this->api->call("Domain/PrivateWhois/Status",['Domain' => $domain]);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            return in_array($get["STATUS"],['FULL','PARTIAL']) ? "active" : "passive";
        }
        public function modifyPrivacyProtection($params=[],$status=''){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $process    = $this->api->call("Domain/PrivateWhois/".($status == "enable" ? "Enable" : "Disable"),[
                'Domain' => $domain
            ]);

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

            $get        = $this->api->call("Domain/Host/List",['Domain' => $domain,'CompactList' => "no"]);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            if(isset($get["total_hosts"]) && $get["total_hosts"] > 0)
            {
                foreach($get["host"] AS $r)
                {

                    $name       = $r["hostname"] ?? $r;
                    $ip         = $r["ip"] ?? false;
                    if($ip && is_array($ip)) $ip = current($ip);

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


            $process        = $this->api->call("Domain/Host/Create",[
                'Host'      => $ns,
                'IP_List'   => $ip,
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

            $process = $this->DeleteCNS($params,$old["ns"],$old["ip"]);
            if(!$process) return false;

            $process        = $this->addCNS($params,$new_ns,$new_ip);

            if(!$process) return false;

            return true;
        }

        public function DeleteCNS($params=[],$ns='',$ip=''){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);
            $ns         = idn_to_ascii($ns,0,INTL_IDNA_VARIANT_UTS46);

            $process        = $this->api->call("Domain/Host/Delete",['Host' => $ns]);

            if(!$process)
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;

        }


        public function getAuthCode($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $get        = $this->api->call("Domain/Info",[
                'Domain'        => $domain,
            ]);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            return $get["transferauthinfo"];
        }


        public function isInactive($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $get        = $this->api->call("Domain/Info",['Domain' => $domain]);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            return in_array($get["domainstatus"],['EXPIRED','UNKNOWN']);
        }

        public function sync($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $get        = $this->api->call("Domain/Info",['Domain' => $domain]);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            $start              = str_replace("/","-",$get["registrationdate"]);
            $end                = str_replace("/","-",$get["expirationdate"]);
            $status             = $get["domainstatus"];

            if(in_array($status,['EXPIRED','UNKNOWN']))
                $status_new = 'expired';
            else
                $status_new = 'active';



            $return_data    = [
                'creationtime'  => $start,
                'endtime'       => $end,
                'status'        => $status_new,
            ];

            return $return_data;
        }

        public function transfer_sync($params=[]){
            return $this->sync($params);
        }

        public function get_info($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $get        = $this->api->call("Domain/Info",['Domain' => $domain]);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }


            $result             = [];

            $cdate              = str_replace("/","-",$get["registrationdate"]);
            $duedate            = str_replace("/","-",$get["expirationdate"]);

            $wprivacy           = $get["privatewhois"] == "ENABLED";

            $ns1                = $get["nameserver"][0] ?? false;
            $ns2                = $get["nameserver"][1] ?? false;
            $ns3                = $get["nameserver"][2] ?? false;
            $ns4                = $get["nameserver"][3] ?? false;
            $whois_data         = $get["contacts"] ?? [];

            if($whois_data){
                $key_replace = [
                    'registrant'    => "registrant",
                    'admin'         => "administrative",
                    'technical'     => "technical",
                    'billing'       => "billing",
                ];

                $whois      = [];

                foreach(array_keys($key_replace) AS $ct)
                {
                    $s_key = $key_replace[$ct];

                    $w_data                 = $whois_data[$ct] ?? $whois_data;

                    $phone_smash            = explode(".",$w_data["phonenumber"] ?? '');
                    $fax_smash              = explode(".",$w_data["faxnumber"] ?? '');

                    $whois[$s_key] = [
                        'FirstName'         =>  $w_data["firstname"],
                        'LastName'          =>  $w_data["lastname"],
                        'Name'              =>  $w_data["firstname"]." ".$whois_data["lastname"],
                        'Company'           =>  $w_data["organization"],
                        'EMail'             =>  $w_data["email"],
                        'AddressLine1'      =>  $w_data["street"],
                        'AddressLine2'      =>  '',
                        'City'              =>  $w_data["city"],
                        'State'             =>  $w_data["state"],
                        'ZipCode'           =>  $w_data["postalcode"],
                        'Country'           =>  $w_data["countrycode"],
                        'PhoneCountryCode'  => substr(($phone_smash[0] ?? ''),1),
                        'Phone'             => $phone_smash[1] ?? '',
                        'FaxCountryCode'    => substr(($fax_smash[0] ?? ''),1),
                        'Fax'               => $fax_smash[1] ?? '',
                    ];
                }
            }

            $result["creation_time"]    = $cdate;
            $result["end_time"]         = $duedate;

            if(isset($wprivacy) && $wprivacy != "none"){
                $result["whois_privacy"] = ['status' => $wprivacy ? "enable" : "disable"];
                if(isset($wprivacy_endtime) && $wprivacy_endtime) $result["whois_privacy"]["end_time"] = $wprivacy_endtime;
            }

            if(isset($ns1) && $ns1) $result["ns1"] = $ns1;
            if(isset($ns2) && $ns2) $result["ns2"] = $ns2;
            if(isset($ns3) && $ns3) $result["ns3"] = $ns3;
            if(isset($ns4) && $ns4) $result["ns4"] = $ns4;
            if(isset($whois) && $whois) $result["whois"] = $whois;

            $result["transferlock"] = $get["registrarlock"] == "ENABLED";

            return $result;

        }

        public function domains(){
            Helper::Load(["User"]);

            $get        = $this->api->call("Domain/List",[
                'CompactList'    => "no",
                'ReturnFields'   => "RegistrationDate,Status,Expiration",
            ]);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            $result     = [];

            if(isset($get["domaincount"]) && $get["domaincount"] > 0){
                foreach($get["domain"] AS $res){
                    $cdate      = str_replace("/","-",$res["registrationdate"] ?? '');
                    $edate      = str_replace("/","-",$res['expiration'] ?? '');
                    $domain     = $res['name'];

                    if(in_array($res['status'],['Expired','Unknown'])) continue;

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
                            'end_date'          => $edate,
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

        public function cost_prices($type='domain'){
            $prices    = $this->api->call("Account/PriceList/Get");
            if(!$prices){
                $this->error = $this->api->error;
                return false;
            }

            $result = [];

            if($type == "domain"){
                foreach($prices['product'] AS $r){
                    $name       = $r["name"];
                    $name_p     = explode(" ",$name);
                    $tld        = array_shift($name_p);
                    $type       = implode(" ",$name_p);
                    if(!$tld) continue;
                    if($tld == ".0") continue;

                    if(substr($tld,0,1) == ".") $tld = substr($tld,1);

                    $price      = (float) $r["price"];
                    $curr       = $r["currency"];
                    $cost_curr  = $this->config["settings"]["cost-currency"] ?? 4;
                    $price      = Money::exChange($price,$curr,$cost_curr);
                    $price      = round($price,2);


                    if(!isset($result[$tld])) $result[$tld] = ['register' => 0,'transfer' => 0,'renewal' => 0];

                    if(stristr($type,'registration')) $result[$tld]['register'] = $price;
                    elseif(stristr($type,'renewal')) $result[$tld]['renewal'] = $price;
                    elseif(stristr($type,'transfer')) $result[$tld]['transfer'] = $price;
                }
            }
            return $result;
        }

        public function apply_import_tlds(){
            Helper::Load(["Products","Money"]);

            $cost_cid           = $this->config["settings"]["cost-currency"] ?? 4;

            $prices             = $this->cost_prices();
            if(!$prices) return false;

            $profit_rate        = Config::get("options/domain-profit-rate");

            foreach($prices AS $name=>$val){
                $api_cost_prices    = [
                    'register' => $val["register"],
                    'transfer' => $val["transfer"],
                    'renewal'  => $val["renewal"],
                ];

                $paperwork      = 0;
                $epp_code       = 1;
                $dns_manage     = 1;
                $whois_privacy  = 1;
                $module         = __CLASS__;

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
                else
                {

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

        public function getDnsRecords()
        {
            $result         = [];
            $request        = $this->api->call("Domain/DnsRecord/List",['Domain' => $this->order["options"]["domain"]]);

            if($request && isset($request["total_records"]) && $request["total_records"] > 0)
            {
                foreach($request["records"] AS $k => $r)
                {
                    $result[] = [
                        'identity'      => $k,
                        'type'          => $r["type"],
                        'name'          => $r["name"],
                        'value'         => $r["value"],
                        'ttl'           => $r["ttl"],
                        'priority'      => $r["distance"] ?? 7207,
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

            $params             = [
                'Type'                  => $type,
                'FullRecordName'        => $name,
                'Value'                 => $value,
                'Ttl'                   => $ttl,
            ];

            if($priority) $params['Priority'] = $priority;

            $apply              = $this->api->call("Domain/DnsRecord/Add",$params);


            if(!$apply && $this->api->error){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }
        public function updateDnsRecord($type='',$name='',$value='',$identity='',$ttl='',$priority='')
        {
            $records        = $this->getDnsRecords();
            $current        = [];
            if($records) foreach($records AS $k => $r) if($k == (int) $identity) $current = $r;

            $params = [
                'FullRecordName'        => $current["name"],
                'CurrentValue'          => $current['value'],
                'CurrentTtl'            => $current["ttl"],
                'CurrentPriority'       => $current["priority"],
                'Type'                  => $current["type"],
                'NewValue'              => $value,
                'NewTtl'                => $ttl,
                'NewPriority'           => $priority,

            ];

            $apply      =        $this->api->call("Domain/DnsRecord/Update",$params);

            if(!$apply){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }
        public function deleteDnsRecord($type='',$name='',$value='',$identity='')
        {
            $records        = $this->getDnsRecords();
            $current        = [];
            if($records) foreach($records AS $k => $r) if($k == (int) $identity) $current = $r;

            if(!$current)
            {
                $this->error = "Invalid ID";
                return false;
            }


            $apply      =        $this->api->call("Domain/DnsRecord/Remove",[
                'FullRecordName'    => $current['name'],
                'Type'              => $current['type'],
            ]);

            if(!$apply){
                $this->error = $this->api->error;
                return false;
            }
            return true;
        }

        public function getForwardingDomain()
        {
            $domain         = $this->order["options"]["domain"];
            $domain         = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);

            $get            = $this->api->call('Domain/UrlForward/List',[
                'Domain' => $domain,
            ]);

            if(!$get) return  ['status' => false];

            $status         = false;
            $method         = false;
            $protocol       = false;
            $destination    = false;


            if(isset($get["total_rules"]) && $get["total_rules"] > 0)
            {
                $rules = $get["rules"] ?? ($get["rule"] ?? []);

                if($rules)
                {
                    $rules          = $rules[0];
                    if($rules)
                    {
                        $source         = $rules['source'];

                        if(stristr($source,'https://'))
                            $protocol = "https";
                        else
                            $protocol = 'http';

                        if(isset($rules['redirect301']) && strtolower($rules['redirect301']) == 'yes')
                            $method = 301;
                        else
                            $method = 302;

                        $destination = $rules['destination'];

                    }
                }

            }

            return [
                'status'    => $status,
                'method'    => $method,
                'protocol'  => $protocol,
                'domain'    => $destination,
            ];
        }
        public function setForwardingDomain($protocol='',$method='',$domain='')
        {
            $apply      = $this->api->call('Domain/UrlForward/Add',[
                'Source'        => $protocol.'://'.$this->order["options"]["domain"],
                'Destination'   => $domain,
                'redirect301'   => $method == 301 ? 'YES' : 'NO',
            ]);

            if(!$apply){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }
        public function cancelForwardingDomain()
        {
            $this->api->call('Domain/UrlForward/Remove',[
                'Source' => "http://".$this->order["options"]["domain"]
            ]);

            $this->api->call('Domain/UrlForward/Remove',[
                'Source' => "https://".$this->order["options"]["domain"]
            ]);

            return true;
        }

        public function getEmailForwards()
        {
            $result = [];

            $request = $this->api->call('Domain/EmailForward/List',['Domain' => $this->order["options"]["domain"]]);

            if(!$request)
            {
                $this->error = $this->api->error;
                return false;
            }

            if(isset($request['total_rules']) && $request['total_rules'] > 0)
            {
                $rules      = $request['rules'] ?? ($request['rule'] ?? []);
                if($rules)
                {
                    foreach($rules AS $k => $r)
                    {
                        $r_split = explode("@",$r["source"]);
                        $result[$k] = [
                            'identity'      => $k,
                            'prefix'        => $r_split[0] ?? 'noname',
                            'target'        => $r["destination"],
                        ];
                    }
                }
            }

            return $result;
        }
        public function addForwardingEmail($prefix='',$target='')
        {
            $apply      = $this->api->call('Domain/EmailForward/Add',[
                'Source'        => $prefix."@".$this->order["options"]["domain"],
                'Destination'   => $target,
            ]);

            if(!$apply){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }
        public function updateForwardingEmail($prefix='',$target='',$target_new='',$identity='')
        {
            return $this->addForwardingEmail($prefix,$target_new);
        }
        public function deleteForwardingEmail($prefix='',$target='',$identity='')
        {
            $apply      = $this->api->call('Domain/EmailForward/Remove',[
                'Source'        => $prefix."@".$this->order["options"]["domain"],
            ]);

            if(!$apply){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

    }