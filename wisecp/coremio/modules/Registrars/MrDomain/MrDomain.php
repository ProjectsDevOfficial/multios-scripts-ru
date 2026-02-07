<?php

    class MrDomain {
        public $api                = false;
        public $config             = [];
        public $lang               = [];
        public  $error             = NULL;
        public  $whidden           = [];
        public $order              = [];
        public $docs               = [];

        function __construct($args=[]){

            $this->config   = Modules::Config("Registrars",__CLASS__);
            $this->lang     = Modules::Lang("Registrars",__CLASS__);

            if(!class_exists("MrDomain_API")){
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

            $hostname   = $this->config["settings"]["hostname"];
            $username   = $this->config["settings"]["username"];
            $password   = $this->config["settings"]["password"];
            $password   = Crypt::decode($password,Config::get("crypt/system"));

            $this->api  =  new MrDomain_API($hostname);

            $this->api->set_credentials($username,$password);

        }

        public function set_order($order=[]){
            $this->order = $order;
            return $this;
        }

        public function define_docs($docs=[])
        {
            $this->docs = $docs;
        }

        private function setConfig($username,$password,$sandbox){
            $this->config["settings"]["username"]   = $username;
            $this->config["settings"]["password"]   = $password;
            $this->api = new MrDomain_API($this->config["settings"]["hostname"]);
            $this->api->set_credentials($username,$password);
        }

        public function testConnection($config=[]){
            $username   = $config["settings"]["username"];
            $password   = $config["settings"]["password"];
            $sandbox    = $config["settings"]["test-mode"];

            if(!$username || !$password){
                $this->error = $this->lang["error6"];
                return false;
            }

            $password  = Crypt::decode($password,Config::get("crypt/system"));

            $this->setConfig($username,$password,$sandbox);

            if(!$this->api->check_credentials()){
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
                $check      = $this->api->call("/domain/check/",['domain' => $sld.".".$tld]);
                if(isset($check["success"]) && $check["success"])
                {
                    $res = $check["responseData"]["domains"][0] ?? false;
                    if($res)
                    {
                        $result[$tld] = [
                            'status' => $res["available"] ? "available" : "unavailable",
                            'premium' => $res["premium"],
                            'premium_price' => $res['premium'] ? ['amount' => $res['price'],'currency' => $res["currency"]] : [],
                        ];
                    }
                    else
                        $result[$tld] = ['status' => "unknown"];

                }
            }
            return $result;
        }

        public function whois_process($tld,$data=[])
        {
            $new_data = [];
            
            $key_replace = [
                'owner'     => "registrant",
                'admin'     => "administrative",
                'tech'      => "technical",
                'billing'   => "billing",
            ];

            $user_info = User::getInfo($this->order["owner_id"],["kind","company_tax_number"]);
            $user_account_type          = $user_info["kind"];
            $user_company_tax_number    = $user_info["company_tax_number"];

            foreach(array_keys($key_replace) AS $t)
            {
                $a_data         = $data[$key_replace[$t]] ?? $data;
                if($tld == "es") $a_data["Company"] = '';
                
                $new_data[$t.'ContactType'] = strlen($a_data['Company']) > 1 ? 'organization' : 'individual';
                $new_data[$t.'ContactFirstName'] = $a_data['FirstName'];
                $new_data[$t.'ContactLastName'] = $a_data['LastName'];
                $new_data[$t.'ContactOrgName'] = $a_data['Company'];


                $new_data[$t.'ContactEmail'] = $a_data['EMail'];
                $new_data[$t.'ContactPhone'] = "+".$a_data['PhoneCountryCode'].".".$a_data['Phone'];
                $new_data[$t.'ContactFax'] = strlen($a_data['Fax']) > 2 ? "+".$a_data['FaxCountryCode'].".".$a_data['Fax'] : '';
                $new_data[$t.'ContactAddress'] = $a_data['AddressLine1'].(strlen($a_data['AddressLine2']) > 1 ? $a_data['AddressLine2'] : '');
                $new_data[$t.'ContactPostalCode'] = $a_data['ZipCode'];
                $new_data[$t.'ContactCity'] = $a_data['City'];
                $new_data[$t.'ContactState'] = $a_data['State'];
                $new_data[$t.'ContactCountry'] = $a_data['Country'];



                if(strlen($a_data['Company']) > 1) $new_data[$t.'ContactIdentNumber'] = $user_company_tax_number;
                else
                    $new_data[$t.'ContactIdentNumber'] = Utility::generate_hash(2,false,'u').Utility::generate_hash(5,false,'d');

                if($this->docs) foreach($this->docs AS $k => $v) $new_data[$t.'Contact'.$k] = $v;

            }

            return $new_data;
        }

        public function register($domain='',$sld='',$tld='',$year=1,$dns=[],$whois=[],$wprivacy=false){
            $domain   = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $sld      = idn_to_ascii($sld,0,INTL_IDNA_VARIANT_UTS46);

            $params     = [
                'domain' => $domain,
                'period' => $year,
                'premium' => false,
                'nameservers' => implode(",",$dns),
            ];
            $params        = array_merge($params,$this->whois_process($tld,$whois));

            $process       = $this->api->call("/domain/create/",$params);

            if(!isset($process['success']) || !$process['success'])
            {
                $this->error = $this->api->error;
                return false;
            }

            $res        = $process['responseData']['domains'][0] ?? false;

            if(!$res)
            {
                $this->error = "The domain name was not found in the result";
                return false;
            }



            $returnData = [
                'status' => "SUCCESS",
                'config' => [
                    'entityID' => $res['domainID'],
                ],
            ];



            if($wprivacy)
            {
                $w_privacy        = $this->purchasePrivacyProtection(['domain' => $domain]);
                if(!$w_privacy) $this->error = NULL;
                $returnData["whois_privacy"] = ['status' => $w_privacy,'message' => NULL];
            }

            return $returnData;
        }

        public function transfer($domain='',$sld='',$tld='',$year=1,$dns=[],$whois=[],$wprivacy=false,$eppCode=''){
            $domain   = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $sld      = idn_to_ascii($sld,0,INTL_IDNA_VARIANT_UTS46);


            $params     = [
                'domain'        => $domain,
                'period'        => $year,
                'premium'       => false,
                'nameservers'   => implode(",",$dns),
                'authcode'      => $eppCode,
            ];
            $params        = array_merge($params,$this->whois_process($tld,$whois));

            $process       = $this->api->call("/domain/transfer/",$params);

            if(!isset($process['success']) || !$process['success'])
            {
                $this->error = $this->api->error;
                return false;
            }

            $res        = $process['responseData']['domains'][0] ?? false;

            if(!$res)
            {
                $this->error = "The domain name was not found in the result";
                return false;
            }



            $returnData = [
                'status' => "SUCCESS",
                'config' => [
                    'entityID' => $res['domainID'],
                ],
            ];



            if($wprivacy)
            {
                $w_privacy        = $this->purchasePrivacyProtection(['domain' => $domain]);
                if(!$w_privacy) $this->error = NULL;
                $returnData["whois_privacy"] = ['status' => $w_privacy,'message' => NULL];
            }

            return $returnData;
        }

        public function renewal($params=[],$domain='',$sld='',$tld='',$year=1,$oduedate='',$nduedate=''){
            $domain   = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $sld      = idn_to_ascii($sld,0,INTL_IDNA_VARIANT_UTS46);

            $process        = $this->api->call("/domain/renew/",[
                'domain'        => $domain,
                'curExpDate'    => DateManager::format("Y-m-d",$oduedate),
                'period'        => $year,
            ]);

            if(!isset($process['success']) || !$process['success'])
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function NsDetails($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $process        = $this->api->call("/domain/getnameservers/",['domain' => $domain]);

            if(!isset($process['success']) || !$process['success'])
            {
                $this->error = $this->api->error;
                return false;
            }

            $returns = [];

            foreach($process['responseData']['nameservers'] AS $ns) $returns['ns'.$ns['order']] = $ns['name'];

            return $returns;
        }

        public function ModifyDns($params=[],$dns=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            if($dns) foreach($dns AS $i=>$dn) $dns[$i] = idn_to_ascii($dn,0,INTL_IDNA_VARIANT_UTS46);

            $process        = $this->api->call("/domain/updatenameservers/",[
                'domain' => $domain,
                'nameservers' => implode(",",$dns)
            ]);

            if(!isset($process['success']) || !$process['success'])
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }


        public function ModifyWhois($params=[],$whois=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);


            $new_params     = [
                'domain' => $domain
            ];

            $new_params     = array_merge($new_params,$this->whois_process($params["tld"],$whois));

            $process        = $this->api->call("/domain/updatecontacts/",$new_params);

            if(!isset($process['success']) || !$process['success'])
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function getWhoisPrivacy($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $get        = $this->api->call("/domain/getinfo/",[
                'domain'        => $domain,
                'infoType'      => 'status'
            ]);

            if(!isset($get["success"]) || !$get["success"])
            {
                $this->error = $this->api->error;
                return false;
            }

            return $get["responseData"]["whoisPrivacy"] ? "active" : "passive";
        }

        public function getTransferLock($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $get        = $this->api->call("/domain/getinfo/",[
                'domain'        => $domain,
                'infoType'      => 'status'
            ]);

            if(!isset($get["success"]) || !$get["success"])
            {
                $this->error = $this->api->error;
                return false;
            }

            return $get["responseData"]["transferBlock"] ? "active" : "passive";

            return $details["transfer_lock"] == "on" ? true : false;
        }

        public function isInactive($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $get        = $this->api->call("/domain/getinfo/",[
                'domain'        => $domain,
                'infoType'      => 'status'
            ]);

            if(!isset($get["success"]) || !$get["success"])
            {
                $this->error = $this->api->error;
                return false;
            }

            return $get["responseData"]["status"] != "active";
        }

        public function ModifyTransferLock($params=[],$status=''){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $process    = $this->api->call("/domain/update/",[
                'domain' => $domain,
                'updateType' => 'transferBlock',
                'transferBlock' => $status == "enable" ? "true" : "false",
            ]);

            if(!isset($process['success']) || !$process['success'])
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function modifyPrivacyProtection($params=[],$status=''){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $process    = $this->api->call("/domain/update/",[
                'domain' => $domain,
                'updateType' => 'whoisPrivacy',
                'whoisPrivacy' => $status == "enable" ? "true" : "false",
            ]);

            if(!isset($process['success']) || !$process['success'])
            {
                $this->error = $this->api->error;
                return false;
            }


            return true;
        }

        public function purchasePrivacyProtection($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);


            $process    = $this->api->call("/domain/update/",[
                'domain' => $domain,
                'updateType' => 'whoisPrivacy',
                'whoisPrivacy' => "true",
            ]);

            if(!isset($process['success']) || !$process['success'])
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }


        public function resend_verification_mail($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);


            $process    = $this->api->call("/domain/resendverificationmail/",[
                'domain' => $domain,
            ]);

            if(!isset($process['success']) || !$process['success'])
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
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

        public function getAuthCode($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $get        = $this->api->call("/domain/getauthcode/",[
                'domain'        => $domain,
            ]);

            if(!isset($get["success"]) || !$get["success"])
            {
                $this->error = $this->api->error;
                return false;
            }

            return $get["responseData"]["authcode"];
        }

        public function sync($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $get        = $this->api->call("/domain/getinfo/",[
                'domain'        => $domain,
                'infoType'      => "status",
            ]);

            if(!isset($get["success"]) || !$get["success"])
            {
                $this->error = $this->api->error;
                return false;
            }

            $start              = $get["responseData"]["tsCreate"] ?? '0000-00-00';
            $end                = $get["responseData"]["tsExpir"] ?? '0000-00-00';
            $status             = $get["responseData"]["status"];
            $status_new         = 'active';

            if($status == "transfer-pending") $status_new = "pending";

            $return_data    = [
                'creationtime'  => $start,
                'endtime'       => $end,
                'status'        => $status_new,
            ];

            if($status == "active"){
                $return_data["status"] = "active";
            }elseif($status == "expired")
                $return_data["status"] = "expired";

            return $return_data;
        }

        public function transfer_sync($params=[]){
            return $this->sync($params);
        }

        public function get_info($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $get        = $this->api->call("/domain/getinfo/",[
                'domain'        => $domain,
                'infoType'      => "status",
            ]);

            if(!isset($get["success"]) || !$get["success"])
            {
                $this->error = $this->api->error;
                return false;
            }
            $status_get         = $get;

            $get        = $this->api->call("/domain/getinfo/",[
                'domain'        => $domain,
                'infoType'      => "contact",
            ]);

            if(!isset($get["success"]) || !$get["success"])
            {
                $this->error = $this->api->error;
                return false;
            }
            $contact_get        = $get;

            $get        = $this->api->call("/domain/getinfo/",[
                'domain'        => $domain,
                'infoType'      => "nameservers",
            ]);

            if(!isset($get["success"]) || !$get["success"])
            {
                $this->error = $this->api->error;
                return false;
            }
            $dns_get        = $get;

            $get        = $this->api->call("/domain/getinfo/",[
                'domain'        => $domain,
                'infoType'      => "nameservers",
            ]);

            if(!isset($get["success"]) || !$get["success"])
            {
                $this->error = $this->api->error;
                return false;
            }


            $result             = [];

            $cdate              = $status_get["responseData"]["tsCreate"];
            $duedate            = $status_get["responseData"]["tsExpir"];

            $wprivacy           = $status_get["responseData"]["whoisPrivacy"];

            $ns1                = $dns_get["responseData"]["nameservers"][0]["name"] ?? false;
            $ns2                = $dns_get["responseData"]["nameservers"][1]["name"] ?? false;
            $ns3                = $dns_get["responseData"]["nameservers"][2]["name"] ?? false;
            $ns4                = $dns_get["responseData"]["nameservers"][3]["name"] ?? false;
            $whois_data         = $contact_get["responseData"];

            if($whois_data){

                $key_replace = [
                    'contactOwner'     => "registrant",
                    'contactAdmin'     => "administrative",
                    'contactTech'      => "technical",
                    'contactBilling'   => "billing",
                ];
                
                $whois      = [];
                
                foreach(array_keys($key_replace) AS $ct)
                {
                    $s_key = $key_replace[$ct];
                    
                    $w_data                 = $whois_data[$ct] ?? $whois_data["contactOwner"];

                    $phone_smash            = explode(".",$w_data["phone"]);
                    $fax_smash              = explode(".",$w_data["fax"]);

                    $whois[$s_key]          = [
                        'FirstName'         =>  $w_data["firstName"],
                        'LastName'          =>  $w_data["lastName"],
                        'Name'              =>  $w_data["firstName"]." ".$w_data["lastName"],
                        'Company'           =>  $w_data["orgName"],
                        'EMail'             =>  $w_data["email"],
                        'AddressLine1'      =>  $w_data["address"],
                        'AddressLine2'      =>  '',
                        'City'              =>  $w_data["city"],
                        'State'             =>  $w_data["state"],
                        'ZipCode'           =>  $w_data["postalCode"],
                        'Country'           =>  $w_data["country"],
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

            $result["transferlock"] = $status_get["responseData"]["transferBlock"];

            return $result;

        }

        public function domains(){
            Helper::Load(["User"]);

            $get        = $this->api->call("/domain/list/",[
                'pageLength'    => 1000,
                'infoType'      => "status",
            ]);

            if(!isset($get["success"]) || !$get["success"])
            {
                $this->error = $this->api->error;
                return false;
            }

            $result     = [];

            if($get && is_array($get)){
                foreach($get["responseData"]["domains"] AS $res){
                    $cdate      = $res["tsCreate"] ?? '';
                    $edate      = $res['tsExpir'] ?? '';
                    $domain     = $res['name'];

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
            $prices    = $this->api->call("/account/zones/",['pageLength' => 1000],'POST',true);
            if(!isset($prices["success"]) || !$prices["success"]){
                $this->error = $this->api->error;
                return false;
            }

            $result = [];

            if($type == "domain"){
                foreach($prices['responseData']['zones'] AS $r){
                    $result[$r["tld"]] = [
                        'register' => $r["create"]["price"],
                        'transfer' => $r["transfer"]["price"],
                        'renewal'  => $r["renew"]["price"],
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

    }