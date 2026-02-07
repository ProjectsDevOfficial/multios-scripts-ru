<?php
    class InternetX {
        public $api                = false;
        public $config             = [];
        public $lang               = [];
        public  $error             = NULL;
        public  $whidden           = [];
        private $order             = [];
        private $zone              = NULL;

        function __construct($external=[]){

            $this->config   = Modules::Config("Registrars",__CLASS__);
            $this->lang     = Modules::Lang("Registrars",__CLASS__);
            if(is_array($external) && sizeof($external)>0)
                $this->config = array_merge($this->config,$external);

            if(!class_exists("InternetX_API")){
                include_once __DIR__ . DS . 'classes' . DS . 'class.InternetX_API.php';
                if (!class_exists("idna_convert"))
                    require_once  __DIR__ . DS . 'classes' . DS . 'class.idna_convert.php';
                include_once  __DIR__ . DS . 'classes' . DS . 'class.InternetX_Extension.php';
            }

            if(isset($this->config["settings"]["whidden-amount"])){
                $whidden_amount   = $this->config["settings"]["whidden-amount"];
                $whidden_currency = $this->config["settings"]["whidden-currency"];
                $this->whidden["amount"] = $whidden_amount;
                $this->whidden["currency"] = $whidden_currency;
            }

            $serverHost         = $this->config["settings"]["serverHost"];
            $serverUsername     = $this->config["settings"]["serverUsername"];
            $serverPassword     = $this->config["settings"]["serverPassword"];
            $serverPassword    = Crypt::decode($serverPassword,Config::get("crypt/system"));
            $serverContext      = $this->config["settings"]["serverContext"];

            $tmode              = (bool)$this->config["settings"]["test-mode"];
            $this->api          = new InternetX_API($serverHost, $serverUsername, $serverPassword, $serverContext, $serverUsername, $serverPassword, $serverContext,true,$tmode);
        }
        public function set_order($order=[]){
            $this->order = $order;
            return $this;
        }

        private function setConfig($serverHost,$serverUsername,$serverPassword,$serverContext,$tmode){
            $this->api          = new InternetX_API($serverHost, $serverUsername, $serverPassword, $serverContext, $serverUsername, $serverPassword, $serverContext,true,$tmode);
        }

        private function FormatPhone($number){
            $phone = explode(".",$number);
            $ccPart = $phone[0];
            $phonePart = $phone[1];

            $start = substr($phonePart, 0, 3); //we just assume the first 3 digits of the number as dialling code
            $end = substr($phonePart, 3);

            return $ccPart."-".$start."-".$end;
        }

        public function domainRobotApi($type='GET',$route='domainstudio',$body=[])
        {
            $us         = $this->config["settings"]["check_username"] ?? $this->config['settings']['serverUsername'];
            $pw         = $this->config["settings"]["check_password"] ?? $this->config['settings']['serverPassword'];
            $context    = $this->config['settings']['serverContext'] ?? '0';

            $url        = $this->config['settings']['check_url'] ?? 'https://api.autodns.com/v1/';

            if(stristr($url,'domainstudio')) $url = str_replace("/domainstudio","/",$url);

            $url .= $route;

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL,$url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            if($type != "GET")
            {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
                curl_setopt($ch, CURLOPT_POSTFIELDS,Utility::jencode($body));
            }
            curl_setopt($ch, CURLOPT_USERPWD, $us.':'.Crypt::decode($pw,Config::get("crypt/system")));

            $headers = array();
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'X-Domainrobot-Context: '.$context;
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $response     = curl_exec($ch);
            $errno      = curl_errno($ch);
            $errmsg     = curl_error($ch);

            curl_close($ch);

            if($errno)
            {
                $this->error =  $errmsg;
                Modules::save_log("Registrars",__CLASS__,$url,$body,$response,$this->error);
                return false;
            }

            $response =  Utility::jdecode($response,true);

            if(($response["status"]["type"] ?? '') == "ERROR")
            {
                $this->error = "[".$response["messages"][0]["code"]."]: ".$response["messages"][0]["text"];
                Modules::save_log("Registrars",__CLASS__,$url,$body,$response,$this->error);
                return false;
            }

            Modules::save_log("Registrars",__CLASS__,$url,$body,$response,$this->error);

            return $response;
        }

        public function questioning($sld=NULL,$tlds=[]){
            if($sld == '' || empty($tlds)){
                $this->error = $this->lang["error2"];
                return false;
            }
            $sld = idn_to_ascii($sld,0,INTL_IDNA_VARIANT_UTS46);
            if(!is_array($tlds)) $tlds = [$tlds];

            $result     = [];

            $queries        = array (
                'searchToken' => $sld,
                'sources' => array('initial' => array ('services' => array ('WHOIS'), 'tlds' => $tlds)),
            );

            $response       = $this->domainRobotApi('POST','domainstudio',$queries);

            if(!isset($response["data"]) || !$response["data"])
            {
                $this->error = "Check failed";
                return false;
            }

            foreach($response["data"] AS $d){
                $catch_extension = str_replace($sld.".","",$d['idn']);
                $status         = "unavailable";
                $whois_status   = $d["services"]["whois"]["data"]["status"] ?? 'NA';

                if($whois_status == "FREE" || $whois_status == "PREMIUM") $status = "available";

                $result[$catch_extension] = [
                    'status' => $status,
                    'premium' => $whois_status == "PREMIUM",
                ];

            }
            return $result;
        }
        public function testConnection($config=[]){
            $serverHost         = $config["settings"]["serverHost"];
            $serverUsername     = $config["settings"]["serverUsername"];
            $serverPassword     = $config["settings"]["serverPassword"];
            $serverContext      = $config["settings"]["serverContext"];

            if(!$serverHost || !$serverUsername || !$serverPassword){
                $this->error = $this->lang["error6"];
                return false;
            }

            $serverPassword  = Crypt::decode($serverPassword,Config::get("crypt/system"));
            $tmode = false;
            $this->setConfig($serverHost,$serverUsername,$serverPassword,$serverContext,$tmode);

            $check   = $this->api->domainInfo("test.com");
            if(!$check){
                $this->error = $this->api->getErrors();
                if(!stristr($this->error,"Domain could not be found")) return false;
            }

            return true;
        }
        public function register($domain='',$sld='',$tld='',$year=1,$dns=[],$whois=[],$wprivacy=false,$tcode=NULL){
            $domain   = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);

            $info = $this->api->domainInfo($domain);

            if($this->api->hasError())
                $this->api->clearErrors();
            else
            {
                $rdata = [
                    'status' => "SUCCESS",
                    'config' => [
                        'order_id' => 1,
                    ],
                ];
                if($wprivacy) $rdata["whois_privacy"] = ['status' => true,'message' => NULL];

                return $rdata;
            }


            $contacts = new stdClass();
            $contacts->ownerc = "";
            $contacts->adminc = "";
            $contacts->techc = "";
            $contacts->zonec = "";

            $extensions = InternetX_Extension::assing($tld,false);

            $contact_types = [
                'ownerc'             => "registrant",
                'adminc'             => "administrative",
                'techc'              => "technical",
                'zonec'              => "billing",
            ];

            $whois_temp             = $whois;

            foreach($contact_types as $ix_key => $ct)
            {
                $whois  = $whois_temp[$ct];
                $address = Utility::substr($whois["AddressLine1"].($whois["AddressLine2"] ? ' '.$whois["AddressLine2"] : ''));

                $address    = html_entity_decode($address);
                if(!$address) $address = "n/a";


                $user = array(
                    'type' => $whois["Company"] ? "ORG" : "PERSON",
                    'firstName' => $whois["FirstName"],
                    'lastName' => $whois["LastName"],
                    'organization' => $whois["Company"] == '' ? "" : $whois["Company"],
                    'address' => $address,
                    'city' => $whois["City"],
                    'state' => $whois["State"],
                    'postCode' => $whois["ZipCode"] == '' ? "0000" : $whois["ZipCode"],
                    'country' => $whois["Country"],
                    'countryName' => $whois["Country"],
                    'phone' => $this->FormatPhone($whois["PhoneCountryCode"].".".$whois["Phone"]),
                    'fax'   => $this->FormatPhone($whois["PhoneCountryCode"].".".$whois["Phone"]),
                    'email' => $whois["EMail"],
                );

                if($ix_key != "ownerc" && ($tld == "de" || $tld == "es")) $user['type'] = "PERSON";

                $contact_res = $this->api->contactCreate($user, $extensions);
                if ($this->api->hasError()){
                    $err = $this->api->getErrors();
                    if($err)
                    {
                        $this->error = "Contact create error: {$err}";
                        return false;
                    }
                }
                else
                    $contacts->$ix_key = (string) $contact_res->result->status->object->value;
            }

            $zoneNs         = explode("\n", trim($this->config["settings"]['nameServers']));
            $ns = [];
            for($i = 1; $i < 6; $i++){
                if(!isset($dns['ns' . $i]) || $dns['ns' . $i] == '') continue;
                $nameServer = explode(" ", preg_replace('/\s+/', ' ', $dns['ns' . $i]));
                $ns[] = [
                    'name' => $nameServer[0],
                    'ip' => isset($nameServer[1]) ? $nameServer[1] : '',
                    'ip6' => isset($nameServer[2]) ? $nameServer[2] : '',
                ];
            }

            $domainIP = $this->config["settings"]['domainIP'];
            $domainMX = $this->config["settings"]['domainMX'];


            if($tcode)
                $register = $this->api->transferIn($domain,  (array) $contacts, $ns, htmlspecialchars($tcode), $wprivacy ? 1 : 0, $domainIP, $domainMX);
            else
                $register = $this->api->domainCreate($domain, $year, (array) $contacts, $ns, $wprivacy ? 1 : 0, $domainIP, $domainMX);



            if(!stristr((string) $register->result->status->text,'successfully'))
            {
                $err = $this->api->getErrors();
                $this->error = "Domain Create Error: ".$err;
                return false;
            }



            $rdata = [
                'status' => "SUCCESS",
                'config' => [
                    'order_id' => 1,
                ],
            ];

            if($wprivacy) $rdata["whois_privacy"] = ['status' => true,'message' => NULL];

            return $rdata;

        }
        public function renewal($params=[],$domain='',$sld='',$tld='',$year=1,$oduedate='',$nduedate=''){
            try{
                $domain = idn_to_ascii($domain,INTL_IDNA_VARIANT_UTS46);
                $info = $this->api->domainInfo($domain);

                if(!$info){
                    $this->error = $this->api->hasError() ? $this->api->getErrors() : "Unable to get domain info";
                    return false;
                }

                $exp_date = (string) $info->result->data->domain->payable;
                $exp_date = date("Y-m-d", strtotime($exp_date));
                $this->api->domainRenew($domain, $exp_date, $year);

                if($this->api->hasError()){
                    $this->error = $this->api->getErrors();
                    return false;
                }
            }catch(Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }
        public function transfer($domain='',$sld='',$tld='',$year=1,$dns=[],$whois=[],$wprivacy=false,$tcode=''){
            return $this->register($domain,$sld,$tld,$year,$dns,$whois,$wprivacy,$tcode);
        }

        public function NsDetails($params=[]){
            try {
                $domain = idn_to_ascii($params["domain"],INTL_IDNA_VARIANT_UTS46);

                $info = $this->api->domainInfo($domain);
                if(!$info){
                    $this->error = $this->api->hasError() ? $this->api->getErrors() : "Unable  to get Nameservers";
                    return false;
                }
                $dns = $info->result->data->domain->nserver;
                $id = 1;
                $values = array();
                foreach ($dns as $ns) {
                    $values['ns' . (string) $id++] = (string) $ns->name;
                }
                return $values;
            } catch (Exception $ex) {
                return array("error" => $ex->getMessage());
            }
        }
        public function ModifyDns($params=[],$dns=[]){

            try {
                $domain = idn_to_ascii($params["domain"],INTL_IDNA_VARIANT_UTS46);

                $info = $this->api->domainInfo($domain);
                if(!$info){
                    $this->error = $this->api->hasError() ? $this->api->getErrors() : "Unable  to get Nameservers";
                    return false;
                }

                $ns = array();
                for ($i = 1; $i < 6; $i++) {
                    $i_ = $i-1;
                    if (!isset($dns[$i_]) || $dns[$i_] == '') continue;
                    $nameServer = $dns[$i_];
                    $ns[] = array('name' => $nameServer);
                }

                $domianInfo = (array) $info->result->data->domain;
                $infoNameservers = $domianInfo['nserver'];
                foreach ($infoNameservers as $key => $value) {
                    $testTable[] = (array) $value;
                }
                $result = $testTable == $ns;

                if (count($ns) && !$result){
                    $this->api->domainUpdate($domain, array(), $ns, $params["whois"]['EMail']);
                    if($this->api->hasError()){
                        $this->error = $this->api->getErrors();
                        return false;
                    }
                }
            } catch (Exception $ex) {
                return array("error" => $ex->getMessage());
            }

            return true;
        }


        public function getWhoisPrivacy($params=[]){
            try{

                $domain     = idn_to_ascii($params["domain"],INTL_IDNA_VARIANT_UTS46);

                $info       = $this->api->domainInfo($domain);

                if(!$info){
                    $this->error = $this->api->hasError() ? $this->api->getErrors() : 'Unable  to get domain info';
                    return false;
                }

                return $info->result->data->domain->use_privacy ? "active" : "passive";

            }catch(Exception $e){
                $this->error = $e->getMessage();
                return false;
            }
        }
        public function ModifyWhois($params=[],$whois=[]){
        try{
            $domain = idn_to_ascii($params["domain"],INTL_IDNA_VARIANT_UTS46);

            $info       = $this->api->domainInfo($domain);

            if(!$info){
                $this->error = $this->api->hasError() ? $this->api->getErrors() : 'Unable  to get domain info';
                return false;
            }

            $contactsIds = array(
                'Registrant' => (string) $info->result->data->domain->ownerc,
                'Admin' => (string) $info->result->data->domain->adminc,
                'Tech' => (string) $info->result->data->domain->techc,
                'Zone' => (string) $info->result->data->domain->zonec,
            );

            $contactsIds = array_unique($contactsIds);


            $contact_types = [
                'Registrant'             => "registrant",
                'Admin'             => "administrative",
                'Tech'              => "technical",
                'Zone'              => "billing",
            ];

            foreach ($contactsIds as $key => $id) {
                $whois      = $whois[$contact_types[$key]];

                $address = Utility::substr($whois["AddressLine1"].($whois["AddressLine2"] ? ' '.$whois["AddressLine2"] : ''));
                $address = html_entity_decode($address);

                if(!$address) $address = "n/a";

                $this->api->contactUpdate([
                    'id'            => $id,
                    'firstName'     => $whois["FirstName"],
                    'lastName'      => $whois["LastName"],
                    'organization'  => $whois["Company"] == '' ? "" : $whois["Company"],
                    'address'       => $address,
                    'city'          => $whois["City"],
                    'state'         => $whois["State"],
                    'postCode'      => $whois["ZipCode"] == '' ? "0000" : $whois["ZipCode"],
                    'country'       => $whois["Country"],
                    'countryName'   => $whois["Country"],
                    'phone'         => $this->FormatPhone($whois["PhoneCountryCode"].".".$whois["Phone"]),
                    'email'         => $whois["EMail"],
                ]);

                if($this->api->hasError()){
                    $this->error = $this->api->getErrors();
                    return false;
                }
            }
        }catch(Exception $e){
            $this->error = $e->getMessage();
            return false;
        }

        return true;
    }

        public function getTransferLock($params=[]){
            try{

                $domain     = idn_to_ascii($params["domain"],INTL_IDNA_VARIANT_UTS46);

                $info       = $this->api->domainInfo($domain);

                if(!$info){
                    $this->error = $this->api->hasError() ? $this->api->getErrors() : 'Unable  to get domain info';
                    return false;
                }

                $status = $info->result->data->domain->registry_status;
            }catch(Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return $status != "ACTIVE" ? true : false;
        }

        public function isInactive($params=[]){
            try{

                $domain     = idn_to_ascii($params["domain"],INTL_IDNA_VARIANT_UTS46);

                $info       = $this->api->domainInfo($domain);

                if(!$info){
                    $this->error = $this->api->hasError() ? $this->api->getErrors() : 'Unable  to get domain info';
                    return false;
                }

                $status = $info->result->data->domain->registrar_status;

            }catch(Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            return $status != "ACTIVE" ? true : false;
        }
        public function ModifyTransferLock($params=[],$type=''){

            try {
                $domain = idn_to_ascii($params["domain"],INTL_IDNA_VARIANT_UTS46);

                if($type == "enable")
                    $this->api->domainUpdateStatus($domain,'LOCK');
                else
                    $this->api->domainUpdateStatus($domain,'ACTIVE');


                if($this->api->hasError()){
                    $this->error = $this->api->getErrors();
                    return false;
                }

            } catch (Exception $ex) {
                return array("error" => $ex->getMessage());
            }

            return true;
        }
        public function modifyPrivacyProtection($params=[],$staus=''){
            try {
                $domain = idn_to_ascii($params["domain"],INTL_IDNA_VARIANT_UTS46);

                if($staus == "enable")
                    $this->api->domainUpdateIDProtection($domain, "true", $params["whois"]['EMail']);
                else
                    $this->api->domainUpdateIDProtection($domain, "false",$params["whois"]['EMail']);

                if($this->api->hasError()){
                    $this->error = $this->api->getErrors();
                    return false;
                }
            } catch (Exception $ex) {
                $this->error = $ex->getMessage();
                return false;
            }

            return true;
        }
        public function purchasePrivacyProtection($params=[]){
            return $this->modifyPrivacyProtection($params,"enable");
        }
        public function getAuthCode($params=[]){
            try {
                $domain = idn_to_ascii($params["domain"],INTL_IDNA_VARIANT_UTS46);

                if (strpos($params['tld'], 'de') !== false || strpos($params['tld'], 'eu') !== false){
                    $this->api->authinfo1Create($domain);
                    if($this->api->hasError()){
                        $this->error = $this->api->getErrors();
                        return false;
                    }
                }

                $info = $this->api->domainInfo($domain);
                if(!$info){
                    $this->error = $this->api->hasError() ? $this->api->getErrors() : "Unable  to get domain info";
                    return false;
                }
            } catch (Exception $ex) {
                $this->error = $ex->getMessage();
                return false;
            }

            return $info->result->data->domain->authinfo;
        }
        public function sync($params=[]){
            try {
                $domain     = idn_to_ascii($params["domain"],INTL_IDNA_VARIANT_UTS46);

                $info = $this->api->domainInfoSubUser($domain);

                if ($this->api->hasError()){
                    $this->error = $this->api->getErrors();
                    if(stristr($this->error,'E0105 Domain data could not be inquired'))
                        $this->error = '';
                    return false;
                }

                $values = array();
                if ($info){
                    $status = strtolower(strtolower($info->result->data->domain->status));
                    if ((string) $info->result->data->domain->payable == "0000-00-00 00:00:00" || (string) $info->result->data->domain->payable == "0000-00-00" || !isset($info->result->data->domain->payable)){
                        $this->error = "No end date information was received.";
                        return false;
                    }
                    $expiry_date = date('Y-m-d', strtotime((string) $info->result->data->domain->payable));

                    $values['endtime'] = $expiry_date;

                    if(strtotime($expiry_date) < strtotime(date('Y-m-d'))) $values["status"] = "expired";
                    elseif($status == "success") $values["status"] = "active";
                }
            } catch (Exception $ex) {
                return array("error" => $ex->getMessage());
            }

            return $values;
        }
        public function transfer_sync($params=[]){
            try {
                $domain     = idn_to_ascii($params["domain"],INTL_IDNA_VARIANT_UTS46);

                $info = $this->api->domainInfoSubUser($domain);

                if ($this->api->hasError()){
                    $this->error = $this->api->getErrors();
                    if(stristr($this->error,'E0105 Domain data could not be inquired'))
                        $this->error = '';
                    return false;
                }

                $values = array();
                if ($info){
                    $status = strtolower(strtolower($info->result->data->domain->status));
                    if ((string) $info->result->data->domain->payable == "0000-00-00 00:00:00" || (string) $info->result->data->domain->payable == "0000-00-00" || !isset($info->result->data->domain->payable)){
                        $this->error = "No end date information was received.";
                        return false;
                    }
                    $expiry_date = date('Y-m-d', strtotime((string) $info->result->data->domain->payable));

                    $values['endtime'] = $expiry_date;

                    if(strtotime($expiry_date) < strtotime(date('Y-m-d'))) $values["status"] = "expired";
                    elseif($status == "success") $values["status"] = "active";
                }
            } catch (Exception $ex) {
                return array("error" => $ex->getMessage());
            }

            return $values;
        }
        public function get_info($params=[]){
            $result             = [];

            try{

                $domain     = idn_to_ascii($params["domain"],INTL_IDNA_VARIANT_UTS46);

                $info       = $this->api->domainInfo($domain);

                if(!$info){
                    $this->error = $this->api->hasError() ? $this->api->getErrors() : 'Unable  to get domain info';
                    return false;
                }
            }catch(Exception $e){
                $this->error = $e->getMessage();
                return false;
            }

            $cdate              = (string) $info->result->data->domain->created;
            $duedate            = (string) $info->result->data->domain->payable;
            $wprivacy           = ((string) $info->result->data->domain->use_privacy) == "true";

            $ns1                = isset($info->result->data->domain->nserver[0]) ? (string) $info->result->data->domain->nserver[0]->name : false;
            $ns2                = isset($info->result->data->domain->nserver[1]) ? (string) $info->result->data->domain->nserver[1]->name : false;
            $ns3                = isset($info->result->data->domain->nserver[2]) ? (string) $info->result->data->domain->nserver[2]->name : false;
            $ns4                = isset($info->result->data->domain->nserver[3]) ? (string) $info->result->data->domain->nserver[3]->name : false;
            $contact_o            = $this->api->contactInfo((string) $info->result->data->domain->ownerc);
            $contact_a            = $this->api->contactInfo((string) $info->result->data->domain->adminc);
            $contact_t            = $this->api->contactInfo((string) $info->result->data->domain->techc);
            $contact_z            = $this->api->contactInfo((string) $info->result->data->domain->zonec);

            if($this->api->hasError()){
                $this->error = $this->api->getErrors();
                return false;
            }

            $key_replace = [
                'o'             => "registrant",
                'a'             => "administrative",
                't'             => "technical",
                'z'             => "billing",
            ];

            $whois      = [];

            foreach(array_keys($key_replace) AS $ct)
            {
                $s_key = $key_replace[$ct];

                $contact                = ${"contact_".$ct}->result->data->handle;

                $phone_split            = explode("-",$contact->phone);
                $phone_cc               = Filter::numbers($phone_split[0]);
                array_shift($phone_split);
                $phone_number           = implode("",$phone_split);


                $whois[$s_key] = [
                    'Name'              =>  (string) $contact->alias,
                    'FirstName'         =>  (string) $contact->fname,
                    'LastName'          =>  (string) $contact->lname,
                    'Company'           =>  (string) $contact->organization,
                    'EMail'             =>  (string) $contact->email,
                    'AddressLine1'      =>  Utility::substr((string) $contact->address,0,64),
                    'AddressLine2'      =>  Utility::substr((string) $contact->address,64,64),
                    'City'              =>  (string) $contact->city,
                    'State'             =>  (string) $contact->state,
                    'ZipCode'           =>  (string) $contact->pcode,
                    'Country'           =>  (string) $contact->country,
                    'PhoneCountryCode'  =>  (string) $phone_cc,
                    'Phone'             =>  (string) $phone_number,
                    'FaxCountryCode'    =>  (string) $phone_cc,
                    'Fax'               =>  (string) $phone_number,
                ];
            }


            if($cdate) $result["creation_time"] = $cdate;
            if($duedate) $result["end_time"] = $duedate;

            if(isset($ns1) && $ns1) $result["ns1"] = $ns1;
            if(isset($ns2) && $ns2) $result["ns2"] = $ns2;
            if(isset($ns3) && $ns3) $result["ns3"] = $ns3;
            if(isset($ns4) && $ns4) $result["ns4"] = $ns4;
            if(isset($whois) && $whois) $result["whois"] = $whois;

            $result["whois_privacy"] = ['status' => $wprivacy ? "enable" : "disable"];

            $result["transferlock"] = $info->result->data->domain->registry_status != "ACTIVE" ? true : false;

            return $result;
        }
        public function domains(){
            Helper::Load(["User"]);

            $result     = [];

            $info       = $this->api->domainList();

            if(!$info){
                $this->error = $this->api->hasError() ? $this->api->getErrors() : "Unable to get domain list";
                return false;
            }

            if($info){
                $list = $info->result->data->domain;
                if($list)
                {
                    foreach($list AS $res){
                        $cdate = (string) $res->created;
                        $edate = (string) $res->payable;
                        if(stristr($edate,'0000-00')) $edate = '';
                        $domain = (string) $res->name;

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

            $domain     = idn_to_ascii($this->order["name"],0,INTL_IDNA_VARIANT_UTS46);

            $domainInfo             = $this->domainRobotApi('GET','domain/'.$domain);

            if(!$domainInfo || !($domainInfo["data"][0] ?? []))
            {
                if(!$this->error) $this->error = "Domain info failed";
                return false;
            }
            $nameserver = $domainInfo["data"][0]['nameServers'][0]['name'];

            $zone       = $this->domainRobotApi('GET','zone/'.$domain.'/'.$nameserver);

            $this->zone = $zone;

            if(!$zone) return [];

            $hostRecords = $zone["data"][0]["resourceRecords"] ?? [];

            if($hostRecords)
            {
                foreach($hostRecords AS $k => $r)
                {
                    $record_data =  [
                        'identity'      => $k,
                        'type'          => strtoupper($r["type"]),
                        'name'          => $r["name"] == "" ? "@" : $r["name"],
                        'value'         => $r["value"],
                        'ttl'           => 7207,
                        'priority'      => '',
                    ];
                    if($record_data["type"] == "MX") $record_data["priority"] = $r["pref"] ?? '';

                    $result[] = $record_data;
                }
            }

            return $result;

        }

        public function addDnsRecord($type,$name,$value,$ttl,$priority)
        {
            $domain     = idn_to_ascii($this->order["name"],0,INTL_IDNA_VARIANT_UTS46);

            if(!$ttl) $ttl = 7207;

            $records = $this->getDnsRecords();

            $newRecords = [];

            foreach($records AS $r)
            {
                $record_data = [
                    'name' => str_replace("@","",$r["name"]),
                    'type' => $r["type"],
                    'value' => $r["value"],
                    'pref'  => $r["priority"],
                ];
                $newRecords[] = $record_data;
            }

            $newRecords[] = [
                'type' => $type,
                'name' => str_replace("@","",$name),
                'value' => $value,
                'priority' => (string) $priority,
            ];

            $data = $this->zone["data"][0] ?? [];

            $data["resourceRecords"] = $newRecords;

            unset($data["created"]);
            unset($data["updated"]);
            unset($data["action"]);
            unset($data["roid"]);

            $apply      = $this->domainRobotApi("PUT","zone/".$domain."/".$data["virtualNameServer"],$data);
            if(!$apply) return false;

            return true;
        }

        public function updateDnsRecord($type='',$name='',$value='',$identity='',$ttl='',$priority='')
        {
            $domain     = idn_to_ascii($this->order["name"],0,INTL_IDNA_VARIANT_UTS46);

            if(!$ttl) $ttl = 7207;

            $records = $this->getDnsRecords();

            $newRecords = [];

            foreach($records AS $k => $r)
            {

                if($k == $identity)
                {
                    $r = [
                        'type' => $type,
                        'name' => str_replace("@","",$name),
                        'value' => $value,
                        'priority' => (string) $priority,
                    ];
                }

                $record_data = [
                    'name' => str_replace("@","",$r["name"]),
                    'type' => $r["type"],
                    'value' => $r["value"],
                    'pref'  => (string) $r["priority"],
                ];
                $newRecords[] = $record_data;
            }


            $data = $this->zone["data"][0] ?? [];

            $data["resourceRecords"] = $newRecords;

            unset($data["created"]);
            unset($data["updated"]);
            unset($data["action"]);
            unset($data["roid"]);

            $apply      = $this->domainRobotApi("PUT","zone/".$domain."/".$data["virtualNameServer"],$data);
            if(!$apply) return false;

            return true;
        }

        public function deleteDnsRecord($type='',$name='',$value='',$identity='')
        {
            $domain     = idn_to_ascii($this->order["name"],0,INTL_IDNA_VARIANT_UTS46);

            $records = $this->getDnsRecords();

            $newRecords = [];

            foreach($records AS $k => $r)
            {
                if($k == $identity) continue;

                $record_data = [
                    'name' => str_replace("@","",$r["name"]),
                    'type' => $r["type"],
                    'value' => $r["value"],
                    'pref'  => (string) $r["priority"],
                ];
                $newRecords[] = $record_data;
            }


            $data = $this->zone["data"][0] ?? [];

            $data["resourceRecords"] = $newRecords;

            unset($data["created"]);
            unset($data["updated"]);
            unset($data["action"]);
            unset($data["roid"]);

            $apply      = $this->domainRobotApi("PUT","zone/".$domain."/".$data["virtualNameServer"],$data);
            if(!$apply) return false;

            return true;
        }

    }