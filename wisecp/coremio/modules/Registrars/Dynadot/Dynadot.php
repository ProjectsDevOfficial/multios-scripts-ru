<?php

    /*
        * Modify transfer lock : not supported by API
        * DNS Sec Record list :  not supported by API
        * Email Forward list :  not supported by API
        */

    class Dynadot {
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

            if(!class_exists("Dynadot_API")){
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

            $key        = $this->config["settings"]["key"];

            $sandbox    = (bool)$this->config["settings"]["test-mode"];
            $this->api  =  new Dynadot_API($sandbox);

            $this->api->set_credentials($key);

        }

        public function set_order($order=[]){
            $this->order = $order;
            return $this;
        }

        public function define_docs($docs=[])
        {
            $this->docs = $docs;
        }
        

        private function setConfig($key,$sandbox){
            $this->config["settings"]["key"]        = $key;
            $this->config["settings"]["test-mode"]  = $sandbox;
            $this->api = new Dynadot_API($sandbox);

            $this->api->set_credentials($key);
        }

        public function testConnection($config=[]){
            $key        = $config["settings"]["key"];
            $sandbox    = $config["settings"]["test-mode"];

            if(!$key){
                $this->error = $this->lang["error6"];
                return false;
            }

            $this->setConfig($key,$sandbox);

            if(!$this->api->call("list_domain")){
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

            foreach ($tlds AS $t){
                $response       = $this->api->call("search",[
                    'domain0' => $sld.".".$t,
                    'show_price' => 0,
                ]);

                if($response)
                {
                    $wSstatus       = $response["SearchResponse"]["SearchResults"][0]["Available"] == "yes";

                    $result[$t] = ['status' => $wSstatus ? "available" : "unavailable"];
                }
            }

            return $result;
        }
        
        private function whois_process($whois=[])
        {
            return [
                'name'              => $whois["Name"] ?? '',
                'organization'      => $whois["Company"] ?? '',
                'email'             => $whois["EMail"] ?? '',
                'address1'          => $whois["AddressLine1"] ?? '',
                'address2'          => $whois["AddressLine2"] ?? '',
                'city'              => $whois["City"] ?? '',
                'state'             => $whois["State"] ?? '',
                'zip'               => $whois["ZipCode"] ?? '',
                'country'           => $whois["Country"] ?? '',
                'phonecc'           => $whois["PhoneCountryCode"] ?? '',
                'phonenum'          => $whois["Phone"] ?? '',
                'faxcc'             => $whois["FaxCountryCode"] ?? '',
                'faxnum'            => $whois["Fax"] ?? '',
            ];
        }

        public function register($domain='',$sld='',$tld='',$year=1,$dns=[],$whois=[],$wprivacy=false,$eppCode=''){
            $domain             = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $sld                = idn_to_ascii($sld,0,INTL_IDNA_VARIANT_UTS46);

            $api_params         = [
                'domain'            => $domain,
                'duration'          => $year,
            ];

            $convert_key = [
                'registrant'        => 'registrant',
                'administrative'    => 'admin',
                'technical'         => 'technical',
                'billing'           => 'billing',
            ];
            $contact_types          = array_keys($convert_key);

            foreach($contact_types AS $w_ct)
            {
                $ct = $convert_key[$w_ct];

                $contact_detail          = $this->whois_process($whois[$w_ct]);
                $create_contact          = $this->api->call("create_contact",$contact_detail);

                if(!$create_contact)
                {
                    $this->error = $this->api->error;
                    return false;
                }

                $responseCode   = $create_contact["CreateContactResponse"]["ResponseCode"] ?? "-1";
                if($responseCode != "0")
                {
                    $this->error = $create_contact["CreateContactResponse"]["Error"] ?? 'Unknown';
                    return false;
                }

                $contact_id         = $create_contact["CreateContactResponse"]["CreateContactContent"]["ContactId"] ?? 0;
                if($contact_id < 1)
                {
                    $this->error = "Contact id is 0";
                    return false;
                }

                $api_params[$ct."_contact"] = $contact_id;
            }

            if($eppCode) $api_params['EppCode'] = $eppCode;

            // If the tld contains a document, we process it.
            $require_docs       = $this->config["settings"]["doc-fields"][$tld] ?? [];
            if($require_docs)
            {
                // If there is a document defined in the tld and the user has not sent a document, we give a warning.
                if(!$this->docs)
                {
                    $this->error = "Required documents for domain name not defined";
                    return false;
                }

                // We prepare the obtained document entries to be sent to the domain name provider.
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


            $response       = $this->api->call($eppCode ? "transfer" : "register",$api_params);

            if(!$response)
            {
                $this->error = $this->api->error;
                return false;
            }

            $resType        = $eppCode ? "Transfer" : "Register";


            if($response[$resType."Response"]["Status"] != "success")
            {
                $this->error = $response[$resType."Response"]["Error"] ?? 'Unknown';
                return false;
            }

            $returnData = [
                'status' => "SUCCESS",
                'config' => [
                    'entityID' => 1,
                ],
            ];

            sleep(3);

            $ns_params      = ['domain' => $domain];
            foreach(array_values($dns) AS $nk => $ns) $ns_params["ns".$nk] = $ns;

            $this->api->call("set_ns",$ns_params);

            if($wprivacy){
                $wpp            = $this->api->call("set_privacy",[
                    'domain' => $domain,
                    'option' => "full",
                ]);
                $wpp_success = true;
                $wpp_error   = NULL;

                if(!$wpp)
                {
                    $wpp_success    = false;
                    $wpp_error      = $this->api->error;
                }
                elseif($wpp["SetPrivacyResponse"]["Status"] != "success")
                {
                    $wpp_success    = false;
                    $wpp_error      = $wpp["SetPrivacyResponse"]["Error"] ?? "Unknown";
                }

                $returnData["whois_privacy"] = ['status' => $wpp_success,'message' => $wpp_error];
            }

            return $returnData;
        }

        public function transfer($domain='',$sld='',$tld='',$year=1,$dns=[],$whois=[],$wprivacy=false,$eppCode=''){
            return $this->register($domain,$sld,$tld,$year,$dns,$whois,$wprivacy,$eppCode);
        }

        public function renewal($params=[],$domain='',$sld='',$tld='',$year=1,$oduedate='',$nduedate=''){
            $domain   = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $sld      = idn_to_ascii($sld,0,INTL_IDNA_VARIANT_UTS46);

            $request                = $this->api->call("renew",[
                'domain'            => $domain,
                'duration'          => $year,
            ]);

            if(!$request)
            {
                $this->error        = $this->api->error;
                return false;
            }
            return true;
        }

        public function ModifyDns($params=[],$dns=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            if($dns) foreach($dns AS $i=>$dn) $dns[$i] = idn_to_ascii($dn,0,INTL_IDNA_VARIANT_UTS46);

            $ns_params      = ['domain' => $domain];
            foreach(array_values($dns) AS $nk => $ns) $ns_params["ns".$nk] = $ns;

            $request = $this->api->call("set_ns",$ns_params);

            if(!$request)
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function ModifyWhois($params=[],$whois=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $domainInfo             = $this->api->call("domain_info",['domain' => $domain]);

            if(!$domainInfo)
            {
                $this->error = $this->api->error;
                return false;
            }

            if($domainInfo["DomainInfoResponse"]["ResponseCode"] != "0")
            {
                $this->error = $domainInfo["DomainInfoResponse"]["Error"];
                return false;
            }


            $convert_key = [
                'registrant'        => 'Registrant',
                'administrative'    => 'Admin',
                'technical'         => 'Technical',
                'billing'           => 'Billing',
            ];
            $contact_types          = array_keys($convert_key);

            foreach($contact_types AS $w_ct)
            {
                $ct = $convert_key[$w_ct];

                $contact_id         = $domainInfo["DomainInfoResponse"]["DomainInfo"]["Whois"][$ct]["ContactId"] ?? 0;


                $whois_data = $this->whois_process($whois[$w_ct]);

                $whois_data["contact_id"] = $contact_id;

                $modify = $this->api->call("edit_contact",$whois_data);
                if(!$modify){
                    $this->error = $this->api->error;
                    return false;
                }

                if($modify["EditContactResponse"]["ResponseCode"] != 0)
                {
                    $this->error = $modify["EditContactResponse"]["Error"];
                    return false;
                }
            }
            
            return true;
        }

        public function isInactive($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $domainInfo             = $this->api->call("domain_info",['domain' => $domain]);

            if(!$domainInfo)
            {
                $this->error = $this->api->error;
                return false;
            }

            if($domainInfo["DomainInfoResponse"]["ResponseCode"] != "0")
            {
                $this->error = $domainInfo["DomainInfoResponse"]["Error"];
                return false;
            }

            return $domainInfo["DomainInfoResponse"]["DomainInfo"]["Hold"] == "yes";
        }

        public function modifyPrivacyProtection($params=[],$status=''){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $wpp            = $this->api->call("set_privacy",[
                'domain' => $domain,
                'option' => $status == "enable" ? "full" : "off",
            ]);

            if(!$wpp)
            {
                $this->error = $this->api->error;
                return false;
            }

            if($wpp["SetPrivacyResponse"]["Status"] != "success")
            {
                $this->error = $wpp["SetPrivacyResponse"]["Error"] ?? "Unknown";
                return  false;
            }


            return true;
        }

        public function purchasePrivacyProtection($params=[]){
            return $this->modifyPrivacyProtection($params,'enable');
        }

        public function getAuthCode($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $details    = $this->api->call("get_transfer_auth_code",[
                'domain' => $domain,
            ]);
            if(!$details){
                $this->error = $this->api->error;
                return false;
            }

            if($details["GetTransferAuthCodeResponse"]["ResponseCode"] != 0)
            {
                $this->error = $details["GetTransferAuthCodeResponse"]["Error"];
                return false;
            }

            return $details["GetTransferAuthCodeResponse"]["AuthCode"];
        }


        public function sync($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $domainInfo             = $this->api->call("domain_info",['domain' => $domain]);

            if(!$domainInfo)
            {
                $this->error = $this->api->error;
                return false;
            }

            if($domainInfo["DomainInfoResponse"]["ResponseCode"] != "0")
            {
                $this->error = $domainInfo["DomainInfoResponse"]["Error"];
                return false;
            }


            $start              = DateManager::timetostr("Y-m-d H:i",str_replace("000","",$domainInfo["DomainInfoResponse"]["DomainInfo"]["Registration"] ?? ''));
            $end                = DateManager::timetostr("Y-m-d H:i",str_replace("000","",$domainInfo["DomainInfoResponse"]["DomainInfo"]["Expiration"] ?? ''));
            $status             = $domainInfo["DomainInfoResponse"]["DomainInfo"]["Hold"] == "yes" ? "expired" : "active";

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

            $domainInfo             = $this->api->call("domain_info",['domain' => $domain]);

            if(!$domainInfo)
            {
                $this->error = $this->api->error;
                return false;
            }

            if($domainInfo["DomainInfoResponse"]["ResponseCode"] != "0")
            {
                $this->error = $domainInfo["DomainInfoResponse"]["Error"];
                return false;
            }


            $details = $domainInfo["DomainInfoResponse"]["DomainInfo"] ?? [];


            $result             = [];

            $cdate              = DateManager::timetostr("Y-m-d H:i",str_replace("000","",$details["Registration"] ?? ''));
            $duedate            = DateManager::timetostr("Y-m-d H:i",str_replace("000","",$details["Expiration"] ?? ''));


            $wprivacy           = $details["Privacy"] != "none" ? ($details["Privacy"] != "off") : "none";

            $ns_info            = $this->api->call("get_ns",['domain' => $domain]);
            if($ns_info)
            {
                $ns_k = 0;
                if($ns_info["GetNsResponse"]["ResponseCode"] == 0)
                {
                    foreach($ns_info["GetNsResponse"]["NsContent"] AS $n)
                    {
                        if($n == "Name Servers") continue;
                        $ns_k++;
                        $result["ns".$ns_k] = $n;
                    }
                }

            }
            $whois              = [];

            $convert_key = [
                'registrant'        => 'Registrant',
                'administrative'    => 'Admin',
                'technical'         => 'Technical',
                'billing'           => 'Billing',
            ];
            $contact_types          = array_keys($convert_key);

            foreach($contact_types AS $w_ct)
            {
                $ct                     = $convert_key[$w_ct];

                $contact_id             = $details["Whois"][$ct]["ContactId"] ?? 0;

                $whois_data             = $this->api->call("get_contact",['contact_id' => $contact_id]);
                if($whois_data)
                {
                    if($whois_data["GetContactResponse"]["ResponseCode"] == 0)
                    {
                        $w_data = $whois_data["GetContactResponse"]["GetContact"];

                        $f_name = $w_data["Name"] ?? '';

                        $name_smash     = Filter::name_smash($f_name);

                        $whois[$w_ct]             = [
                            'FirstName'         => $name_smash["first"],
                            'LastName'          => $name_smash["last"],
                            'Name'              => $f_name,
                            'Company'           => $w_data["Organization"] ?? '',
                            'EMail'             => $w_data["Email"] ?? '',
                            'AddressLine1'      => $w_data["Address1"] ?? '',
                            'AddressLine2'      => $w_data["Address2"] ?? '',
                            'City'              => $w_data["City"] ?? '',
                            'State'             => $w_data["State"] ?? '',
                            'ZipCode'           => $w_data["ZipCode"] ?? '',
                            'Country'           => $w_data["Country"] ?? '',
                            'PhoneCountryCode'  => $w_data["PhoneCc"] ?? '',
                            'Phone'             => $w_data["PhoneNum"] ?? '',
                            'FaxCountryCode'    => $w_data["FaxCc"] ?? '',
                            'Fax'               => $w_data["FaxNum"] ?? '',
                        ];
                    }
                }
            }


            $result["creation_time"]    = $cdate;
            $result["end_time"]         = $duedate;

            if(isset($wprivacy) && !(is_string($wprivacy) && $wprivacy == "none")){
                $result["whois_privacy"] = ['status' => $wprivacy ? "enable" : "disable"];
                if(isset($wprivacy_endtime) && $wprivacy_endtime) $result["whois_privacy"]["end_time"] = $wprivacy_endtime;
            }


            if(isset($whois) && $whois) $result["whois"] = $whois;

            $result["transferlock"] = $details["Locked"] == "yes";

            return $result;

        }
        
        public function domains(){
            Helper::Load(["User"]);

            $data       = $this->api->call("list_domain");
            if(!$data){
                $this->error = $this->api->error;
                return false;
            }

            if($data["ListDomainInfoResponse"]["ResponseCode"] != 0)
            {
                $this->error = $data["ListDomainInfoResponse"]["Error"] ?? 'Unknown';
                return false;
            }


            $data =  $data["ListDomainInfoResponse"]["MainDomains"] ?? [];



            $result     = [];

            if($data && is_array($data)){
                foreach($data AS $res){
                    $cdate      = DateManager::timetostr("Y-m-d",str_replace("000","",$res["Registration"] ?? ''));
                    $edate      = DateManager::timetostr("Y-m-d",str_replace("000","",$res["Expiration"] ?? ''));
                    $domain     = $res["Name"] ?? '';

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
        
        public function cost_prices($type='domain'){

            $prices    = $this->api->call("tld_price");
            if(!$prices){
                $this->error = $this->api->error;
                return false;
            }

            if($prices["TldPriceResponse"]["ResponseCode"] != 0)
            {
                $this->error = $prices["TldPriceResponse"]["Error"] ?? 'Unknown';
                return false;
            }

            $prices = $prices["TldPriceResponse"]["TldPrice"] ?? [];

            $result = [];

            if($type == "domain"){
                foreach($prices AS $row){
                    $name           = substr($row["Tld"],1);

                    $result[$name] = [
                        'register' => $row["Price"]["Register"],
                        'transfer' => $row["Price"]["Transfer"],
                        'renewal'  => $row["Price"]["Renew"],
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

        public function apply_import_tlds(){

            $cost_cid           = $this->config["settings"]["cost-currency"]; // Currency ID

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
            $result = [];

            $domain     = idn_to_ascii($this->order["name"],0,INTL_IDNA_VARIANT_UTS46);

            $domainInfo             = $this->api->call("domain_info",['domain' => $domain]);

            if(!$domainInfo)
            {
                $this->error = $this->api->error;
                return false;
            }

            if($domainInfo["DomainInfoResponse"]["ResponseCode"] != "0")
            {
                $this->error = $domainInfo["DomainInfoResponse"]["Error"];
                return false;
            }


            $details    = $domainInfo["DomainInfoResponse"]["DomainInfo"] ?? [];
            $nss        = $details["NameServerSettings"] ?? [];
            $nss_type   = $nss["Type"] ?? '';

            if($nss_type == "Dynadot DNS")
            {
                if(isset($nss["MainDomains"]) && $nss["MainDomains"])
                {
                    foreach($nss["MainDomains"] as $k => $r)
                    {
                        $record_data =  [
                            'identity'      => $k,
                            'type'          => strtoupper($r["RecordType"]),
                            'name'          => '@',
                            'value'         => $r["Value"],
                            'ttl'           => 7207,
                            'priority'      => '',
                        ];

                        if($record_data["type"] == "MX")
                            $record_data["priority"] = $r["Value2"] ?? '';


                        $result[] = $record_data;
                    }
                }
            }


            return $result;

        }

        public function addDnsRecord($type,$name,$value,$ttl,$priority)
        {
            if(!$priority) $priority = 10;
            if(!$ttl) $ttl = 7207;

            $api_params = [
                'domain' => $this->order["name"],
            ];

            $current_records = $this->getDnsRecords();
            $total_record = -1;
            if($current_records)
            {
                foreach($current_records AS $k => $r)
                {
                    $total_record += 1;
                    $api_params["main_record_type".$k] = strtolower($r["type"]);
                    $api_params["main_record".$k] = $r["value"];
                    if($r["type"] == "MX") $api_params["main_recordx".$k] = $r["priority"];
                }
            }

            $total_record++;

            $api_params["main_record_type".$total_record]   = strtolower($type);
            $api_params["main_record".$total_record]        = $value;
            if($type == "MX") $api_params["main_recordx".$total_record] = $priority;



            $response       = $this->api->call("set_dns2",$api_params);

            if(!$response)
            {
                $this->error = $this->api->error;
                return false;
            }

            if($response["SetDnsResponse"]["Status"] != "success")
            {
                $this->error = $response["SetDnsResponse"]["Error"] ?? 'Unknown';
                return false;
            }

            return true;
        }

        public function updateDnsRecord($type='',$name='',$value='',$identity='',$ttl='',$priority='')
        {
            $list = $this->getDnsRecords();
            if(!$list) return false;
            $verified = false;
            foreach($list AS $l) if($l["identity"] == $identity) $verified = true;
            if(!$verified)
            {
                $this->error = "Invalid identity ID";
                return false;
            }

            $api_params = [
                'domain' => $this->order["name"],
            ];

            $current_records = $list;
            if($current_records)
            {
                foreach($current_records AS $k => $r)
                {
                    if($r["identity"] == $identity)
                    {
                        $api_params["main_record_type".$k] = strtolower($type);
                        $api_params["main_record".$k] = $value;
                        if($r["type"] == "MX") $api_params["main_recordx".$k] = $priority;
                    }
                    else
                    {
                        $api_params["main_record_type".$k] = strtolower($r["type"]);
                        $api_params["main_record".$k] = $r["value"];
                        if($r["type"] == "MX") $api_params["main_recordx".$k] = $r["priority"];
                    }
                }
            }

            $response       = $this->api->call("set_dns2",$api_params);

            if(!$response)
            {
                $this->error = $this->api->error;
                return false;
            }

            if($response["SetDnsResponse"]["Status"] != "success")
            {
                $this->error = $response["SetDnsResponse"]["Error"] ?? 'Unknown';
                return false;
            }

            return true;
        }

        public function deleteDnsRecord($type='',$name='',$value='',$identity='')
        {
            $list = $this->getDnsRecords();
            if(!$list) return false;
            $verified = false;
            foreach($list AS $l) if($l["identity"] == $identity) $verified = true;
            if(!$verified)
            {
                $this->error = "Invalid identity ID";
                return false;
            }

            $api_params = [
                'domain' => $this->order["name"],
            ];

            $current_records = $list;
            if($current_records)
            {
                foreach($current_records AS $k => $r)
                {
                    if($r["identity"] == $identity)
                        continue;
                    else
                    {
                        $api_params["main_record_type".$k] = strtolower($r["type"]);
                        $api_params["main_record".$k] = $r["value"];
                        if($r["type"] == "MX") $api_params["main_recordx".$k] = $r["priority"];
                    }
                }
            }

            $response       = $this->api->call("set_dns2",$api_params);

            if(!$response)
            {
                $this->error = $this->api->error;
                return false;
            }

            if($response["SetDnsResponse"]["Status"] != "success")
            {
                $this->error = $response["SetDnsResponse"]["Error"] ?? 'Unknown';
                return false;
            }

            return true;
        }

    }