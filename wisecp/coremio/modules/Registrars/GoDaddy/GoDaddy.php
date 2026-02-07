<?php
    /*
     * Automatic pricing and tld import functions are not available because tld' pricing information is not obtained.
     * The advanced dns method is not supported because the method for listing DNS records could not be found.
    */
    class GoDaddy {
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

            if(!class_exists("GoDaddy_API")) include __DIR__.DS."api.php";

            if(isset($this->config["settings"]["whidden-amount"])){
                $whidden_amount   = $this->config["settings"]["whidden-amount"];
                $whidden_currency = $this->config["settings"]["whidden-currency"];
                $this->whidden["amount"] = $whidden_amount;
                $this->whidden["currency"] = $whidden_currency;
            }

            // Set API Credentials 

            $username   = $this->config["settings"]["username"];
            $password   = $this->config["settings"]["password"];
            $password   = Crypt::decode($password,Config::get("crypt/system"));

            $sandbox    = (bool)$this->config["settings"]["test-mode"];
            $this->api  =  new GoDaddy_API($sandbox);

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
            $this->config["settings"]["test-mode"]  = $sandbox;
            $this->api = new GoDaddy_API($sandbox);

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

            if(!$this->api->call("domains") && $this->api->error){
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

            $result         = [];
            $domains        = [];
            foreach ($tlds AS $t) $domains[] = $sld.".".$t;

            $domains["queries"] = ['checkType' => 'FAST'];

            $request        = $this->api->call("domains/available",$domains,'POST');


            if($request && isset($request["domains"]) && $request["domains"])
            {
                foreach($request["domains"] AS $r)
                {
                    $status          = 'unknown';
                    $dom             = $r["domain"];
                    $ext             = str_replace($sld.".","",$dom);

                    if($request) $status = $r['available'] ? 'available' : 'unavailable';

                    $result[$ext] = ['status' => $status];
                }
            }

            return $result;
        }

        private function whois_process($whois=[])
        {

            $fax                    = '';
            $get_fax_cc                = $whois["FaxCountryCode"] ?? '';
            $get_fax_num               = $whois["Fax"] ?? '';
            if(strlen($get_fax_cc) > 0) $fax .= "+".$get_fax_cc;
            if(strlen($get_fax_num) > 0) $fax .= ".".$get_fax_num;
            
            $phone                    = '';
            $get_phone_cc                = $whois["PhoneCountryCode"] ?? '';
            $get_phone_num               = $whois["Phone"] ?? '';
            if(strlen($get_phone_cc) > 0) $phone .= "+".$get_phone_cc;
            if(strlen($get_phone_num) > 0) $phone .= ".".$get_phone_num;
            

            return [
                'addressMailing'    => [
                    'address1'      => $whois["AddressLine1"] ?? '',
                    'address2'      => $whois["AddressLine2"] ?? '',
                    'city'          => $whois["City"] ?? '',
                    'state'         => $whois["State"] ?? '',
                    'postalCode'    => $whois["ZipCode"] ?? '',
                    'country'       => $whois["Country"] ?? '',
                ],
                'nameFirst'         => $whois["FirstName"] ?? '',
                'nameLast'          => $whois["LastName"] ?? '',
                'nameMiddle'        => '',
                'organization'      => $whois["Company"] ?? '',
                'jobTitle'          => strlen($whois["Company"] ?? '') > 0 ? 'Unknown' : '',
                'email'             => $whois["EMail"] ?? '',
                'phone'             => $phone,
                'fax'               => $fax,
            ];
        }

        public function register($domain='',$sld='',$tld='',$year=1,$dns=[],$whois=[],$wprivacy=false,$eppCode=''){
            $domain             = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $sld                = idn_to_ascii($sld,0,INTL_IDNA_VARIANT_UTS46);

            $agreements          = $this->api->call("domains/agreements",[
                'tlds'              => $tld,
                'privacy'           => $wprivacy,
                'forTransfer'       => strlen($eppCode) > 0,
            ]);

            $agreementKeys          = [];

            if($agreements) foreach($agreements AS $a) $agreementKeys[] = $a["agreementKey"];

            $api_params         = [
                'consent'        => [
                    'agreedAt' => date(DATE_ISO8601, DateManager::strtotime())."Z",
                    'agreedBy' => UserManager::GetIP(),
                    'agreementKeys' => $agreementKeys,
                ],
                'domain'         => $domain,
                'nameServers'    => array_values($dns),
                'period'         => (int) $year,
                'privacy'        => $wprivacy,
            ];

            $require_docs       = $this->config["settings"]["doc-fields"][$tld] ?? [];
            if($require_docs)
            {
                if(!$this->docs)
                {
                    $this->error = "Required documents for domain name not defined";
                    return false;
                }

                foreach($require_docs AS $doc_id => $doc)
                {
                    if(!isset($this->docs[$doc_id]) || strlen($this->docs[$doc_id]) < 1)
                    {
                        $this->error = 'The document "'.$doc["name"].'" is not specified!';
                        return false;
                    }

                    $doc_value = $this->docs[$doc_id];

                    if($doc["type"] == "file") $doc_value = base64_encode(file_get_contents($doc_value));

                    $api_params[$doc_id] = $doc_value;
                }
            }


            $convert_key = [
                'registrant'        => 'Registrant',
                'administrative'    => 'Admin',
                'technical'         => 'Tech',
                'billing'           => 'Billing',
            ];
            $contact_types          = array_keys($convert_key);

            foreach($contact_types AS $w_ct)
            {
                $ct = $convert_key[$w_ct];

                $api_params["contact".$ct] = $this->whois_process($whois[$w_ct]);
            }

            $endpoint   = "domains/purchase";

            if($eppCode)
            {
                $endpoint               = "domains/".$domain."/transfer";
                $api_params['authCode'] = $eppCode;
                unset($api_params["domain"]);
            }




            $response       = $this->api->call($endpoint,$api_params,'POST');

            if($response)
            {

                $returnData = [
                    'status' => "SUCCESS",
                    'config' => [
                        'entityID' => $response['orderId'],
                    ],
                ];

                if($wprivacy)
                    $returnData["whois_privacy"] = ['status' => true,'message' => NULL];

                return $returnData;
            }
            else
            {
                $this->error = $this->api->error;
                return false;
            }
        }

        public function transfer($domain='',$sld='',$tld='',$year=1,$dns=[],$whois=[],$wprivacy=false,$eppCode=''){
            return $this->register($domain,$sld,$tld,$year,$dns,$whois,$wprivacy,$eppCode);
        }

        public function renewal($params=[],$domain='',$sld='',$tld='',$year=1,$oduedate='',$nduedate=''){
            $domain   = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $sld      = idn_to_ascii($sld,0,INTL_IDNA_VARIANT_UTS46);


            $response       = $this->api->call("domains/".$domain."/renew",[
                'period' => $year
            ],'POST');

            if(!$response)
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function ModifyDns($params=[],$dns=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            if($dns) foreach($dns AS $i=>$dn) $dns[$i] = idn_to_ascii($dn,0,INTL_IDNA_VARIANT_UTS46);

            $modifyDns  = $this->api->call("domains/".$domain,[
                'nameServers' => array_values($dns)
            ],'PATCH');
            if(!$modifyDns && $this->api->error){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }


        public function ModifyWhois($params=[],$whois=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $api_params     = [];

            $convert_key = [
                'registrant'        => 'Registrant',
                'administrative'    => 'Admin',
                'technical'         => 'Tech',
                'billing'           => 'Billing',
            ];
            $contact_types          = array_keys($convert_key);

            foreach($contact_types AS $w_ct)
            {
                $ct = $convert_key[$w_ct];

                $api_params["contact".$ct] = $this->whois_process($whois[$w_ct]);
            }

            $modifyWhois  = $this->api->call("domains/".$domain."/contacts",$api_params,'PATCH');
            if(!$modifyWhois && $this->api->error){
                $this->error = $this->api->error;
                return false;
            }

            
            return true;
        }


        public function isInactive($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $details    = $this->api->call("domains/".$domain);
            if(!$details){
                $this->error = $this->api->error;
                return false;
            }
            return $details["status"] !== "ACTIVE" ? true : false;
        }

        public function ModifyTransferLock($params=[],$status=''){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $modify      = $this->api->call("domains/".$domain,[
                'locked' => $status == "enable"
            ],'PATCH');

            if(!$modify && $this->api->error){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function modifyPrivacyProtection($params=[],$status=''){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $modify     = $this->api->call("domains/".$domain,[
                'exposeWhois' => !($status == "enable")
            ],'PATCH');
            if(!$modify && $this->api->error){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function purchasePrivacyProtection($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);
            $tld        = idn_to_ascii($params["tld"],0,INTL_IDNA_VARIANT_UTS46);

            $agreements          = $this->api->call("domains/agreements",[
                'tlds'              => $tld,
                'privacy'           => true,
                'forTransfer'       => false,
            ]);

            $agreementKeys          = [];

            if($agreements) foreach($agreements AS $a) $agreementKeys[] = $a["agreementKey"];

            $apply = $this->api->call("domains/".$domain."/privacy/purchase",[
                'consent'        => [
                    'agreedAt' => date(DATE_ISO8601, DateManager::strtotime())."Z",
                    'agreedBy' => UserManager::GetIP(),
                    'agreementKeys' => $agreementKeys,
                ],
            ]);
            if(!$apply && $this->api->error){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }


        public function getAuthCode($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $details    = $this->api->call("domains/".$domain);
            if(!$details){
                $this->error = $this->api->error;
                return false;
            }

            $authCode   = $details["authCode"];

            return $authCode;
        }

        public function sync($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $details    = $this->api->call("domains/".$domain);
            if(!$details){
                $this->error = $this->api->error;
                return false;
            }


            $start              = DateManager::format("Y-m-d",$details["createdAt"] ?? '');
            $end                = DateManager::format("Y-m-d",$details["expires"] ?? '');
            $status             = $details["status"];

            $return_data    = [
                'creationtime'  => $start,
                'endtime'       => $end,
                'status'        => "unknown",
            ];

            var_dump($status);

            if($status == "ACTIVE"){
                $return_data["status"] = "active";
            }elseif($status == "EXPIRED")
                $return_data["status"] = "expired";

            return $return_data;

        }

        public function transfer_sync($params=[]){
            return $this->sync($params);
        }

        public function get_info($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $details    = $this->api->call("domains/".$domain);
            if(!$details){
                $this->error = $this->api->error;
                return false;
            }

            $result             = [];

            $cdate              = DateManager::format("Y-m-d",$details["createdAt"]);
            $duedate            = DateManager::format("Y-m-d",$details["expires"]);

            $wprivacy           = isset($details["exposeWhois"]) ? !$details["exposeWhois"] : "none";


            $ns1                = $details["nameServers"][0] ?? false;
            $ns2                = $details["nameServers"][1] ?? false;
            $ns3                = $details["nameServers"][2] ?? false;
            $ns4                = $details["nameServers"][3] ?? false;


            $whois              = [];

            $convert_key = [
                'registrant'        => 'Registrant',
                'administrative'    => 'Admin',
                'technical'         => 'Tech',
                'billing'           => 'Billing',
            ];
            $contact_types          = array_keys($convert_key);

            foreach($contact_types AS $w_ct)
            {
                $ct                     = $convert_key[$w_ct];

                $wd                     = $details["contact".$ct];

                $phoneExp               = explode(".",$wd["phone"] ?? '');
                $faxExp                 = explode(".",$wd["fax"] ?? '');

                $phoneCc                = Filter::numbers($phoneExp[0] ?? '');
                $phoneNm                = Filter::numbers($phoneExp[1] ?? '');

                $faxCc                  = Filter::numbers($faxExp[0] ?? '');
                $faxNm                  = Filter::numbers($faxExp[1] ?? '');

                $whois[$w_ct]           = [
                    'FirstName'         => $wd["nameFirst"] ?? '',
                    'LastName'          => $wd["nameLast"] ?? '',
                    'Name'              => ($wd["nameFirst"] ?? '')." ".($wd["nameLast"] ?? ''),
                    'Company'           => $wd["organization"] ?? '',
                    'EMail'             => $wd["email"] ?? '',
                    'AddressLine1'      => $wd["addressMailing"]["address1"] ?? '',
                    'AddressLine2'      => $wd["addressMailing"]["address2"] ?? '',
                    'City'              => $wd["addressMailing"]["city"] ?? '',
                    'State'             => $wd["addressMailing"]["state"] ?? '',
                    'ZipCode'           => $wd["addressMailing"]["postalCode"] ?? '',
                    'Country'           => $wd["addressMailing"]["country"] ?? '',
                    'PhoneCountryCode'  => $phoneCc,
                    'Phone'             => $phoneNm,
                    'FaxCountryCode'    => $faxCc,
                    'Fax'               => $faxNm,
                ];
            }

            $result["creation_time"]    = $cdate;
            $result["end_time"]         = $duedate;

            if(isset($wprivacy) && $wprivacy !== "none"){
                $result["whois_privacy"] = ['status' => $wprivacy ? "enable" : "disable"];
                if(isset($wprivacy_endtime) && $wprivacy_endtime) $result["whois_privacy"]["end_time"] = $wprivacy_endtime;
            }

            if(isset($ns1) && $ns1) $result["ns1"] = $ns1;
            if(isset($ns2) && $ns2) $result["ns2"] = $ns2;
            if(isset($ns3) && $ns3) $result["ns3"] = $ns3;
            if(isset($ns4) && $ns4) $result["ns4"] = $ns4;
            if(isset($whois) && $whois) $result["whois"] = $whois;

            $result["transferlock"] = $details["locked"];

            return $result;

        }
        
        public function domains(){
            Helper::Load(["User"]);

            $data       = $this->api->call("domains");
            if(!$data && $this->api->error){
                $this->error = $this->api->error;
                return false;
            }

            $result     = [];

            if($data && is_array($data)){
                foreach($data AS $res){
                    $cdate      = DateManager::format("Y-m-d",$res["createdAt"] ?? '');
                    $edate      = DateManager::format("Y-m-d",$res["expires"] ?? '');
                    $domain     = $res["domain"] ?? '';

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
                    "module"            => $config["meta"]["name"],
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

    }