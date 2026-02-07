<?php
    class eNom {
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

            if(!class_exists("eNom_API")){
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

            $username   = $this->config["settings"]["username"];
            $password   = $this->config["settings"]["password"];
            $password   = Crypt::decode($password,Config::get("crypt/system"));

            $sandbox    = (bool)$this->config["settings"]["test-mode"];
            $this->api  =  new eNom_API($sandbox);

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
            $this->api = new eNom_API($sandbox);

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

            $test       = $this->api->call([
                'COMMAND' => "check",
                'SLD'     => "google",
                'TLD'     => "COM",
            ]);

            if(!$test)
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

            $result = [];
            foreach ($tlds AS $t){
                $request       = $this->api->call([
                    'COMMAND' => "check",
                    'SLD'     => $sld,
                    'TLD'     => strtoupper($t),
                ]);

                $status         = "unknown";

                if(!$this->api->error) $status = (int) $request->RRPCode == 210 ? "available" : "unavailable";

                $result[$t] = ['status' => $status];

            }
            return $result;
        }

        public function whois_process($whois=[],$modify=false)
        {
            $api_params         = [];
            $convert_key        = [
                'registrant'        => 'Registrant',
                'administrative'    => 'Admin',
                'technical'         => 'Tech',
                'billing'           => 'AuxBilling',
            ];

            $contact_types          = array_keys($convert_key);

            $result                 = [];

            foreach($contact_types AS $w_ct)
            {
                $ct = $convert_key[$w_ct];

                if($modify) $api_params = [];

                $api_params[$ct."FirstName"]            = $whois[$w_ct]["FirstName"] ?? '';
                $api_params[$ct."LastName"]             = $whois[$w_ct]["LastName"] ?? '';
                $api_params[$ct."OrganizationName"]     = $whois[$w_ct]["Company"] ?? '';
                $api_params[$ct."Address1"]             = $whois[$w_ct]["AddressLine1"] ?? '';
                $api_params[$ct."Address2"]             = $whois[$w_ct]["AddressLine2"] ?? '';
                $api_params[$ct."City"]                 = $whois[$w_ct]["City"] ?? '';
                $api_params[$ct."StateProvinceChoice"]  = 'state';
                $api_params[$ct."StateProvince"]        = $whois[$w_ct]["State"] ?? '';
                $api_params[$ct."PostalCode"]           = $whois[$w_ct]["ZipCode"] ?? '';
                $api_params[$ct."Country"]              = $whois[$w_ct]["Country"] ?? '';
                $api_params[$ct."EmailAddress"]         = $whois[$w_ct]["EMail"] ?? '';
                $api_params[$ct."Phone"]                = "+".($whois[$w_ct]["PhoneCountryCode"] ?? '').".".($whois[$w_ct]["Phone"] ?? '');
                $api_params[$ct."Fax"]                = "+".($whois[$w_ct]["FaxCountryCode"] ?? '').".".($whois[$w_ct]["Fax"] ?? '');

                if(strlen($api_params[$ct."OrganizationName"]) > 1) $api_params[$ct."JobTitle"] = "Other";
                if($modify) $result[$w_ct] = $api_params;
            }

            return $modify ? $result : $api_params;
        }

        public function register($domain='',$sld='',$tld='',$year=1,$dns=[],$whois=[],$wprivacy=false){
            $domain             = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $sld                = idn_to_ascii($sld,0,INTL_IDNA_VARIANT_UTS46);


            $api_params         = [
                'Command'        => "Purchase",
                'SLD'            => $sld,
                'TLD'            => $tld,
                'RegPeriod'      => $year,
                'IgnoreNSFail'   => "Yes",
                'EndUserIP'      => UserManager::GetIP(),

            ];


            $ns_i = 0;
            foreach($dns AS $n)
            {
                $ns_i++;
                $api_params["NS".$ns_i] = $n;
            }

            $whois_process      = $this->whois_process($whois);
            $api_params         = array_merge($api_params,$whois_process);

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

            $response       = $this->api->call($api_params);

            if($response)
            {

                $returnData = [
                    'status' => "SUCCESS",
                    'config' => [
                        'entityID' => $response->OrderID,
                    ],
                ];

                if($wprivacy)
                {
                    $id_protect = $this->api->call([
                        'command'       => 'PurchaseServices',
                        'Service'       => 'ID Protect',
                        'SLD'           => $sld,
                        'TLD'           => $tld,
                    ]);

                    $returnData["whois_privacy"] = ['status' => $id_protect,'message' => $this->api->error];
                }

                return $returnData;
            }
            else
            {
                $this->error = $this->api->error;
                return false;
            }
        }

        public function transfer($domain='',$sld='',$tld='',$year=1,$dns=[],$whois=[],$wprivacy=false,$eppCode=''){
            $domain             = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $sld                = idn_to_ascii($sld,0,INTL_IDNA_VARIANT_UTS46);


            $api_params         = [
                'Command'        => "TP_CreateOrder",
                'PreConfig'      => 1,
                'OrderType'      => "Autoverification",
                'DomainCount'    => 1,
                'SLD1'           => $sld,
                'TLD1'           => $tld,
                'AuthInfo1'      => $eppCode,
                'RegPeriod'      => $year,
                'EndUserIP'      => UserManager::GetIP(),
            ];

            if($wprivacy)
            {
                $api_params["IncludeIDP"] = 1;
                $api_params["IDPPrice"] = 1;
            }

            $whois_process      = $this->whois_process($whois);
            $api_params         = array_merge($api_params,$whois_process);

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

            $response       = $this->api->call($api_params);

            if($response)
            {

                $returnData = [
                    'status' => "SUCCESS",
                    'config' => [
                        'transferorderid' => (int) $response->transferorder->transferorderid,
                    ],
                ];

                return $returnData;
            }
            else
            {
                $this->error = $this->api->error;
                return false;
            }
        }

        public function renewal($params=[],$domain='',$sld='',$tld='',$year=1,$oduedate='',$nduedate=''){
            $domain   = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $sld      = idn_to_ascii($sld,0,INTL_IDNA_VARIANT_UTS46);

            $request        = $this->api->call(
                [
                    'command'           => "Extend",
                    'SLD'               => $sld,
                    'TLD'               => $tld,
                    'NumYears'          => $year,
                ]
            );

            if($this->api->error)
            {
                $this->error = $this->api->error;
                return false;
            }
            return true;
        }

        public function ModifyDns($params=[],$dns=[]){
            $name               = idn_to_ascii($params["name"],0,INTL_IDNA_VARIANT_UTS46);
            if($dns) foreach($dns AS $i=>$dn) $dns[$i] = idn_to_ascii($dn,0,INTL_IDNA_VARIANT_UTS46);

            $api_params     = [
                'command'       => "ModifyNS",
                'SLD'           => $name,
                'TLD'           => $params["tld"],
                'usedns'        => "Default",
            ];
            $ns_key = 0;
            foreach($dns AS $dn)
            {
                $ns_key++;
                $api_params["NS".$ns_key] = $dn;
            }

            $request        = $this->api->call($api_params);


            if(!$request){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function ModifyWhois($params=[],$whois=[]){
            $name               = idn_to_ascii($params["name"],0,INTL_IDNA_VARIANT_UTS46);

            $convert_key = [
                'registrant'        => 'REGISTRANT',
                'administrative'    => 'ADMIN',
                'technical'         => 'TECH',
                'billing'           => 'AUXBILLING',
            ];
            $contact_types          = array_keys($convert_key);

            $whois_process          = $this->whois_process($whois,true);

            foreach($contact_types AS $w_ct)
            {
                $ct = $convert_key[$w_ct];

                $whois_data                     = $whois_process[$w_ct];
                $whois_data["command"]          = "Contacts";
                $whois_data["SLD"]              = $name;
                $whois_data["TLD"]              = $params["tld"];
                $whois_data["ContactType"]      = $ct;

                $modify = $this->api->call($whois_data);
                if(!$modify){
                    $this->error = $this->api->error;
                    return false;
                }
                
            }
            
            return true;
        }

        public function isInactive($params=[]){
            $name       = idn_to_ascii($params["name"],0,INTL_IDNA_VARIANT_UTS46);

            $details    = $this->api->call([
                'command' => "GetDomainInfo",
                'SLD'   => $name,
                'TLD'   => $params["tld"],
            ]);
            if(!$details){
                $this->error = $this->api->error;
                return false;
            }

            return $details->GetDomainInfo->status->registrationstatus != "Registered";
        }

        public function ModifyTransferLock($params=[],$status=''){
            $name     = idn_to_ascii($params["name"],0,INTL_IDNA_VARIANT_UTS46);

            $modify     = $this->api->call([
                'command' => "SetRegLock",
                'SLD'   => $name,
                'TLD'   => $params['tld'],
                'UnlockRegistrar' => $status == "enable" ? 0 : 1,
            ]);
            if(!$modify){
                $this->error = $this->api->error;
                return false;
            }
            return true;
        }

        public function modifyPrivacyProtection($params=[],$status=''){
            $name     = idn_to_ascii($params["name"],0,INTL_IDNA_VARIANT_UTS46);

            $modify = $this->api->call([
                'SLD'       => $name,
                'TLD'       => $params["tld"],
                'command'   => $status == "enable" ? "ENABLESERVICES" : "DISABLESERVICES",
                'service'   => "ID Protect",
            ]);
            if(!$modify){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function purchasePrivacyProtection($params=[]){
            $name     = idn_to_ascii($params["name"],0,INTL_IDNA_VARIANT_UTS46);

            $id_protect = $this->api->call([
                'command'       => 'PurchaseServices',
                'Service'       => 'ID Protect',
                'SLD'           => $name,
                'TLD'           => $params["tld"],
            ]);

            if(!$id_protect){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function sync($params=[]){
            $name     = idn_to_ascii($params["name"],0,INTL_IDNA_VARIANT_UTS46);

            $details    = $this->api->call([
                'command' => "GetDomainInfo",
                'SLD'   => $name,
                'TLD'   => $params["tld"],
            ]);

            if(!$details){
                $this->error = $this->api->error;
                return false;
            }

            $exp                = (string) $details->GetDomainInfo->status->expiration;
            if(strlen($exp) > 1)
            {
                $exp_e               = explode(" ",$exp);
                $exp_s               = explode("/",$exp_e[0]);
                $exp_m               = $exp_s[0];
                $exp_d               = $exp_s[1];
                $exp_y               = $exp_s[2];
                if(strlen($exp_m) < 2) $exp_m = "0".$exp_m;
                if(strlen($exp_d) < 2) $exp_d = "0".$exp_d;
                $exp                 = $exp_y."-".$exp_m."-".$exp_d;
            }



            $start              = DateManager::format("Y-m-d");
            $end                = DateManager::format("Y-m-d",$exp);
            $status             = $details->GetDomainInfo->status->registrationstatus ?? "Unknown";



            $return_data    = [
                'creationtime'  => $start,
                'endtime'       => $end,
                'status'        => "unknown",
            ];

            if($status == "Registered"){
                $return_data["status"] = "active";
            }
            elseif($status == "Expired")
                $return_data["status"] = "expired";

            return $return_data;

        }

        public function transfer_sync($params=[]){
            $name     = idn_to_ascii($params["name"],0,INTL_IDNA_VARIANT_UTS46);

            $details    = $this->api->call([
                'command' => "TP_GetDetailsByDomain",
                'SLD'   => $name,
                'TLD'   => $params["tld"],
            ]);

            if(!$details){
                $this->error = $this->api->error;
                return false;
            }

            $return_data    = [
                'status'        => "awaiting",
            ];


            $status_id      = (int) $details->TransferOrder->statusid;

            if($status_id == 5)
                return $this->sync($params);
            elseif(in_array($status_id,[10,15,17,18,19,20,21,22,23,24,25,26,27,30,101]))
            {
                $this->error = $details->TransferOrder->statusdesc;
                if(str_contains($this->error,"Canceled by customer")) return $this->sync($params);
                return false;
            }

            return $return_data;
        }

        public function get_info($params=[]){
            $name     = idn_to_ascii($params["name"] ?? $params["sld"],0,INTL_IDNA_VARIANT_UTS46);

            $details    = $this->api->call([
                'command' => "GetDomainInfo",
                'SLD'   => $name,
                'TLD'   => $params["tld"],
            ]);
            if(!$details){
                $this->error = $this->api->error;
                return false;
            }

            $result             = [];

            $cdate              = DateManager::format("Y-m-d");

            $exp                = (string) $details->GetDomainInfo->status->expiration;
            if(strlen($exp) > 1)
            {
                $exp_e               = explode(" ",$exp);
                $exp_s               = explode("/",$exp_e[0]);
                $exp_m               = $exp_s[0];
                $exp_d               = $exp_s[1];
                $exp_y               = $exp_s[2];
                if(strlen($exp_m) < 2) $exp_m = "0".$exp_m;
                if(strlen($exp_d) < 2) $exp_d = "0".$exp_d;
                $exp                 = $exp_y."-".$exp_m."-".$exp_d;
            }
            $duedate            = DateManager::format("Y-m-d",$exp);

            $wpp           = $this->api->call([
                'command'       => "GetWPPSInfo",
                'SLD'           => $name,
                'TLD'           => $params['tld'],
            ]);

            $contacts           = $this->api->call([
                'command'       => "GetContacts",
                'SLD'           => $name,
                'TLD'           => $params['tld'],
            ]);

            $wprivacy           = $wpp->GetWPPSInfo->WPPSExists ? ($wpp->GetWPPSInfo->WPPSEnabled == 1) : "none";

            if($wprivacy && $wprivacy !== "none"){
                $wprivacy_endtime_i_exists   = $wpp->GetWPPSInfo->WPPSExpDate ?? "";
                $wprivacy_endtime_i          = $wprivacy_endtime_i_exists ? $wprivacy_endtime_i_exists : "none";
                if($wprivacy_endtime_i && $wprivacy_endtime_i != "none")
                    $wprivacy_endtime   = DateManager::format("Y-m-d",$wprivacy_endtime_i);
            }

            $result["transferlock"] = true;


            if(($services = $details->GetDomainInfo->services ?? false))
            {
                foreach($services->entry AS $serv)
                {
                    $dns_list           = ($serv->configuration->dns ?? []);

                    if($dns_list)
                    {
                        $ns_k = 0;
                        foreach($dns_list AS $dn)
                        {
                            $dn         = (string) $dn;
                            if(strlen($dn) < 1) continue;
                            $ns_k++;
                            $result["ns".$ns_k] = $dn;
                        }
                    }
                }
            }


            $whois_data         = $contacts->GetContacts;
            $whois              = [];

            if($whois_data){
                $convert_key = [
                    'registrant'        => 'Registrant',
                    'administrative'    => 'Admin',
                    'technical'         => 'Tech',
                    'billing'           => 'AuxBilling',
                ];
                $contact_types          = array_keys($convert_key);
                
                foreach($contact_types AS $w_ct)
                {
                    $ct                     = $convert_key[$w_ct];

                    $contact_data           = $whois_data->{$ct} ?? false;

                    $phone_cc               = '';
                    $phone                  = '';
                    $fax_cc                 = '';
                    $fax                    = '';

                    $phone_split            = explode(".",(string) $contact_data->{$ct."Phone"} ?? '');
                    if(sizeof($phone_split) > 0)
                    {
                        $phone_cc           = substr($phone_split[0],1);
                        $phone              = $phone_split[1];
                    }

                    $fax_split            = explode(".",(string) $contact_data->{$ct."Fax"} ?? '');
                    if(sizeof($fax_split) > 0)
                    {
                        $fax_cc           = substr($fax_split[0],1);
                        $fax              = $fax_split[1];
                    }


                    $whois[$w_ct]             = [
                        'FirstName'         => (string) $contact_data->{$ct."FirstName"} ?? '',
                        'LastName'          => (string) $contact_data->{$ct."LastName"} ?? '',
                        'Name'              => ((string) $contact_data->{$ct."FirstName"} ?? '').' '.((string) $contact_data->{$ct."LastName"} ?? ''),
                        'Company'           => (string) $contact_data->{$ct."OrganizationName"} ?? '',
                        'EMail'             => (string) $contact_data->{$ct."EmailAddress"} ?? '',
                        'AddressLine1'      => (string) $contact_data->{$ct."Address1"} ?? '',
                        'AddressLine2'      => (string) $contact_data->{$ct."Address2"} ?? '',
                        'City'              => (string) $contact_data->{$ct."City"} ?? '',
                        'State'             => (string) $contact_data->{$ct."StateProvince"} ?? '',
                        'ZipCode'           => (string) $contact_data->{$ct."PostalCode"} ?? '',
                        'Country'           => (string) $contact_data->{$ct."Country"} ?? '',
                        'PhoneCountryCode'  => $phone_cc,
                        'Phone'             => $phone,
                        'FaxCountryCode'    => $fax_cc,
                        'Fax'               => $fax,
                    ];
                }
                
            }

            $result["creation_time"]    = $cdate;
            $result["end_time"]         = $duedate;

            if(isset($wprivacy) && $wprivacy != "none"){
                $result["whois_privacy"] = ['status' => $wprivacy ? "enable" : "disable"];
                if(isset($wprivacy_endtime) && $wprivacy_endtime) $result["whois_privacy"]["end_time"] = $wprivacy_endtime;
            }

            if(isset($whois) && $whois) $result["whois"] = $whois;


            $getRegLock         = $this->api->call([
                'command'       => "GetRegLock",
                'SLD'           => $name,
                'TLD'           => $params["tld"],
            ]);

            if($getRegLock) $result["transferlock"] = $getRegLock->{"reg-lock"} == 1;

            return $result;

        }
        
        public function domains(){
            Helper::Load(["User"]);


            $data       = $this->api->call([
                'COMMAND' => "AdvancedDomainSearch",
                'SearchCriteria' => "Start",
                'StartPosition' => 0,
                'RecordsToReturn' => 1000,
            ],true,true);

            if($this->api->error){
                $this->error = $this->api->error;
                return false;
            }
            $data         = $data->DomainSearch->Domains->Domain;

            $result     = [];

            if($data){

                foreach($data AS $res){
                    $expiry     = explode("/",(string) $res->ExpDate);
                    $expiry_m   = $expiry[0];
                    $expiry_d   = $expiry[1];
                    $expiry_y   = $expiry[2];
                    if(strlen($expiry_d) < 2) $expiry_d = "0".$expiry_d;
                    if(strlen($expiry_m) < 2) $expiry_m = "0".$expiry_m;
                    $expiry     = $expiry_y."-".$expiry_m."-".$expiry_d;
                    $cdate      = DateManager::old_date([$expiry,'year' => 1],'Y-m-d');


                    $domain      = idn_to_utf8((string) $res->SLD.'.'.(string) $res->TLD,0,INTL_IDNA_VARIANT_UTS46);


                    if($domain){
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
                            'end_date'          => $expiry,
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

        public function getDnsRecords()
        {
            $result = [];

            $request = $this->api->call([
                'command'   => "GetHosts",
                'SLD'       => $this->order["options"]["name"],
                'TLD'       => $this->order["options"]["tld"],

            ]);

            if($request)
            {
                $records        = $request->HostRecordCount == 1 ? [$request->host] : $request->host;
                foreach($records AS $r)
                {
                    $result[] = [
                        'identity'      => (string) $r->hostid ?? '',
                        'type'          => (string) $r->type ?? 'A',
                        'name'          => (string) $r->name ?? '',
                        'value'         => (string) $r->address ?? '',
                        'ttl'           => (string) $r->ttl ?? '',
                        'priority'      => (string) $r->mxpref ?? '',
                    ];
                }
            }


            return $result;

        }

        public function addDnsRecord($type,$name,$value,$ttl,$priority)
        {
            if(!$priority) $priority = 10;
            if(!$ttl) $ttl = 7207;

            $records            = $this->getDnsRecords();
            $record_k           = 0;

            $params             = [
                'command'       => "SetHosts",
                'SLD'           => $this->order["options"]["name"],
                'TLD'           => $this->order["options"]["tld"],
            ];

            if($records)
            {
                foreach($records AS $r)
                {
                    $record_k++;
                    $params["RecordType".$record_k]     = $r["type"];
                    $params["HostName".$record_k]       = $r["name"];
                    $params["Address".$record_k]        = $r["value"];
                    if($r["type"] == "MX") $params["MXPref".$record_k] = $r["priority"];
                }
            }

            $record_k++;

            $params["RecordType".$record_k]     = $type;
            $params["HostName".$record_k]       = $name;
            $params["Address".$record_k]        = $value;
            if($type == "MX") $params["MXPref".$record_k] = $priority;


            $apply              = $this->api->call($params);

            if(!$apply && $this->api->error){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function updateDnsRecord($type='',$name='',$value='',$identity='',$ttl='',$priority='')
        {
            if(!$priority) $priority = 10;
            if(!$ttl) $ttl = 7207;

            $records            = $this->getDnsRecords();
            $record_k           = 0;

            $params             = [
                'command'       => "SetHosts",
                'SLD'           => $this->order["options"]["name"],
                'TLD'           => $this->order["options"]["tld"],
            ];

            if($records)
            {
                foreach($records AS $r)
                {
                    if($r["identity"] == $identity)
                    {
                        $r["name"]      = $name;
                        $r["value"]     = $value;
                        $r["priority"]  = $priority;
                    }
                    $record_k++;
                    $params["RecordType".$record_k]     = $r["type"];
                    $params["HostName".$record_k]       = $r["name"];
                    $params["Address".$record_k]        = $r["value"];
                    if($r["type"] == "MX") $params["MXPref".$record_k] = $r["priority"];
                }
            }

            $apply              = $this->api->call($params);

            if(!$apply && $this->api->error){
                $this->error = $this->api->error;
                return false;
            }


            return true;
        }

        public function deleteDnsRecord($type='',$name='',$value='',$identity='')
        {
            $records            = $this->getDnsRecords();
            $record_k           = 0;

            $params             = [
                'command'       => "SetHosts",
                'SLD'           => $this->order["options"]["name"],
                'TLD'           => $this->order["options"]["tld"],
            ];

            if($records)
            {
                foreach($records AS $r)
                {
                    if($r["identity"] == $identity) continue;
                    $record_k++;
                    $params["RecordType".$record_k]     = $r["type"];
                    $params["HostName".$record_k]       = $r["name"];
                    $params["Address".$record_k]        = $r["value"];
                    if($r["type"] == "MX") $params["MXPref".$record_k] = $r["priority"];
                }
            }

            $apply              = $this->api->call($params);

            if(!$apply && $this->api->error){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }


        public function getEmailForwards()
        {
            $result = [];

            $request = $this->api->call([
                'command'   => "GetForwarding",
                'SLD'       => $this->order["options"]["name"],
                'TLD'       => $this->order["options"]["tld"],
            ]);

            if(!$request)
            {
                $this->error = $this->api->error;
                return false;
            }

            $records        = $request->EmailCount == 1 ? [$request->eforward] : $request->eforward;

            if($records)
            {
                foreach($records AS $r)
                {
                    $result[] = [
                        'identity'      => (string) $r->mailid ?? '',
                        'prefix'        => ((string) $r->alias ?? '')."@".$this->order["name"],
                        'target'        => (string) $r->{"forward-to"} ?? '',
                    ];
                }
            }


            return $result;
        }

        public function addForwardingEmail($prefix='',$target='')
        {
            $params     = [
                'command'    => "Forwarding",
                'SLD'        => $this->order["options"]["name"],
                'TLD'        => $this->order["options"]["tld"],
            ];

            $MailCount      = 0;

            $gets           = $this->getEmailForwards();
            if($gets)
            {
                foreach($gets AS $g)
                {
                    $MailCount++;
                    $pfx    = explode("@",$g["prefix"]);
                    $pfx    = $pfx[0];
                    $params["Address".$MailCount] = $pfx;
                    $params["ForwardTo".$MailCount] = $g["target"];
                }
            }

            $MailCount++;

            $params["Address".$MailCount] = $prefix;
            $params["ForwardTo".$MailCount] = $target;


            $apply      = $this->api->call($params);

            if(!$apply)
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function updateForwardingEmail($prefix='',$target='',$target_new='',$identity='')
        {
            $params     = [
                'command'    => "Forwarding",
                'SLD'        => $this->order["options"]["name"],
                'TLD'        => $this->order["options"]["tld"],
            ];

            $MailCount      = 0;

            $gets           = $this->getEmailForwards();
            if($gets)
            {
                foreach($gets AS $g)
                {
                    $MailCount++;
                    $pfx    = explode("@",$g["prefix"]);
                    $pfx    = $pfx[0];

                    if($g["prefix"] == $prefix) $g["target"] = $target_new;

                    $params["Address".$MailCount] = $pfx;
                    $params["ForwardTo".$MailCount] = $g["target"];
                }
            }


            $apply      = $this->api->call($params);

            if(!$apply)
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function deleteForwardingEmail($prefix='',$target='',$identity='')
        {
            $params     = [
                'command'    => "Forwarding",
                'SLD'        => $this->order["options"]["name"],
                'TLD'        => $this->order["options"]["tld"],
            ];

            $MailCount      = 0;

            $gets           = $this->getEmailForwards();
            if($gets)
            {
                foreach($gets AS $g)
                {
                    $pfx    = explode("@",$g["prefix"]);
                    $pfx    = $pfx[0];

                    if($pfx == $prefix) continue;

                    $MailCount++;

                    $params["Address".$MailCount] = $pfx;
                    $params["ForwardTo".$MailCount] = $g["target"];
                }
            }

            $apply      = $this->api->call($params);

            if(!$apply)
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        /*
         * There is no registration/renewal/transfer fee information in the tld detail.
         * No method related to DNS Sec found on API
         * No results were found on the API regarding the registration date of the domain name.

        public function cost_prices($type='domain'){
            $tldList    = $this->api->call(['command' => "gettldlist"]);


            if(!$tldList){
                $this->error = $this->api->error;
                return false;
            }

            $result = [];


            if($type == "domain"){
                foreach($tldList->tldlist->tld AS $tld){
                    $tld        = $tld;
                    $tld        = (string) $tld->tld;

                    if(empty($tld)) continue;

                    $details        = $this->api->call(
                        [
                            'command'   => "TLD_Overview",
                            'TLD'       => $tld,
                        ],
                        true,true
                    );

                    $result[$tld] = [
                        'register' => (string) $details->RegistrationFee ?? false,
                        'transfer' => (string) $details->RegistrationFee ?? false,
                        'renewal'  => (string) $details->RegistrationFee ?? false,
                    ];

                }
            }

            return $result;
        }

        public function apply_import_tlds(){

            $cost_cid           = $this->config["settings"]["cost-currency"]; // Currency ID

            $prices             = $this->cost_prices();
            if(!$prices) return false;

            print_r($prices);
            exit();

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
        */

    }
