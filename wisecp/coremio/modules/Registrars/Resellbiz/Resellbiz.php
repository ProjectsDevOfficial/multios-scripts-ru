<?php
    class Resellbiz {
        public $api                 = false;
        public $config              = [];
        public $lang                = [];
        public  $error              = NULL;
        public  $whidden            = [];
        public $order               = [];
        public $docs                = [];

        function __construct($external=[]){

            $this->config   = Modules::Config("Registrars",__CLASS__);
            $this->lang     = Modules::Lang("Registrars",__CLASS__);
            if(is_array($external) && sizeof($external)>0)
                $this->config = array_merge($this->config,$external);
            if(!isset($this->config["settings"]["auth-userid"]) || !isset($this->config["settings"]["api-key"])){
                $this->error = $this->lang["error1"];
                return false;
            }

            if(!class_exists("Resellbiz_API")) include __DIR__.DS."api.php";
            if(isset($this->config["settings"]["whidden-amount"])){
                $whidden_amount   = $this->config["settings"]["whidden-amount"];
                $whidden_currency = $this->config["settings"]["whidden-currency"];
                $this->whidden["amount"] = $whidden_amount;
                $this->whidden["currency"] = $whidden_currency;
            }

            $uid   = $this->config["settings"]["auth-userid"];
            $akey  = $this->config["settings"]["api-key"];
            $akey  = Crypt::decode($akey,Config::get("crypt/system"));
            $tmode = (bool)$this->config["settings"]["test-mode"];
            $this->api =  new Resellbiz_API($uid,$akey,$tmode);
        }
        public function set_order($order=[]){
            $this->order = $order;
            return $this;
        }
        private function setConfig($uid,$akey,$tmode){
            $this->config["settings"]["auth-userid"] = $uid;
            $this->config["settings"]["api-key"] = $akey;
            $this->config["settings"]["test-mode"] = $tmode;
            $this->api = new Resellbiz_API($uid,$akey,$tmode);
        }
        public function define_docs($docs=[])
        {
            if($docs) $this->docs = $docs;
        }

        public function questioning($sld=NULL,$tlds=[]){
            if($sld == '' || empty($tlds)){
                $this->error = $this->lang["error2"];
                return false;
            }
            if(!is_array($tlds)) $tlds = [$tlds];
            $response = $this->api->available($sld,$tlds);
            if(!$response) return['status' => "error",'message' => $this->api->error];
            $result = [];
            foreach ($tlds AS $t){
                $domain = $sld.".".$t;
                $domain = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
                $status = isset($response[$domain]["status"]) ? $response[$domain]["status"] : false;
                if($status == "regthroughus" || $status == "regthroughothers" || !$status) $status = "unavailable";
                $result[$t]["status"] = $status;
            }
            return $result;
        }
        public function testConnection($config=[]){
            $uid   = $config["settings"]["auth-userid"];
            $akey  = $config["settings"]["api-key"];

            if(!$uid || !$akey){
                $this->error = $this->lang["error6"];
                return false;
            }

            $akey  = Crypt::decode($akey,Config::get("crypt/system"));
            $tmode = false;
            $this->setConfig($uid,$akey,$tmode);

            $check   = $this->domains(true);

            if(!$check) return false;

            return true;
        }
        public function register($domain='',$sld='',$tld='',$year=1,$dns=[],$whois=[],$wprivacy=false,$tcode=NULL){
            $domain   = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $order_id = $this->api->getOrderId($domain);
            if($order_id){
                return[
                    'order_id' => $order_id,
                ];
            }

            $whois_registrant       = $whois["registrant"] ?? $whois;
            $whois_technical        = $whois["technical"] ?? $whois;
            $whois_billing          = $whois["billing"] ?? $whois;
            $whois_administrative   = $whois["administrative"] ?? $whois;

            $contact_registrant     = $this->contactProcess($whois_registrant);
            $contact_technical      = $this->contactProcess($whois_technical);
            $contact_billing        = $this->contactProcess($whois_billing);
            $contact_administrative = $this->contactProcess($whois_administrative);

            $customer_id    = $this->getCustomerID($contact_registrant["email"]);
            if(!$customer_id) $customer_id = $this->addCustomer($contact_registrant);
            if(!$customer_id) return false;

            $reg_contact_id = $this->addContact($tld,['contact' => $contact_registrant,'customer_id' => $customer_id]);
            if(!$reg_contact_id){
                $this->error = $this->api->error;
                return false;
            }

            $admin_contact_id = $this->addContact($tld,['contact' => $contact_administrative,'customer_id' => $customer_id]);
            if(!$admin_contact_id){
                $this->error = $this->api->error;
                return false;
            }

            $bill_contact_id = $this->addContact($tld,['contact' => $contact_billing,'customer_id' => $customer_id]);
            if(!$bill_contact_id){
                $this->error = $this->api->error;
                return false;
            }

            $tech_contact_id = $this->addContact($tld,['contact' => $contact_technical,'customer_id' => $customer_id]);
            if(!$tech_contact_id){
                $this->error = $this->api->error;
                return false;
            }

            $params = [
                'domain-name' => $domain,
                'years' => $year,
                'dns'    => $dns,
                'customer-id' => $customer_id,
                'reg-contact-id' => $reg_contact_id,
                'admin-contact-id' => $admin_contact_id,
                'tech-contact-id' => $tech_contact_id,
                'billing-contact-id' => $bill_contact_id,
                'invoice-option' => "NoInvoice",
                'purchase-privacy' => $wprivacy ? "true" : "false",
                'protect-privacy' => $wprivacy ? "true" : "false",
            ];

            ## idnLanguageCode ##

            if($tld == "bharat") $this->docs["idnLanguageCode"] = "hin-deva";
            if($tld == "ca") $this->docs["idnLanguageCode"] = "fr";
            if($tld == "de") $this->docs["idnLanguageCode"] = "de";
            if($tld == "es") $this->docs["idnLanguageCode"] = "es";
            if($tld == "eu") $this->docs["idnLanguageCode"] = "latin";
            if($tld == "eu") $this->docs["idnLanguageCode"] = "latin";


            if($this->docs)
            {
                $attr_key = 0;
                foreach($this->docs AS $k => $v)
                {
                    $attr_key++;
                    $params["attr-name".$attr_key] = $k;
                    $params["attr-value".$attr_key] = $v;
                }
            }

            if($tcode != '') $params["auth-code"] = $tcode;

            $submit     = $tcode == '' ? $this->api->register($params) : $this->api->transfer($params);
            if(!$submit){
                $this->error = $this->api->error;
                return false;
            }

            if(isset($submit["status"]) && $submit["status"] == "Success"){
                $rdata = [
                    'status' => "SUCCESS",
                    'config' => [
                        'order_id' => $submit["entityid"],
                    ],
                ];

                if(!isset($submit["actionstatus"]) || $submit["actionstatus"] != "Success"){
                    if(isset($submit["actionstatusdesc"]))
                        $this->error = $submit["actionstatusdesc"];
                    else
                        $this->error = Utility::jencode($submit);
                    $rdata["status"] = "FAIL";
                }

                if($wprivacy) $rdata["whois_privacy"] = ['status' => true,'message' => NULL];

                return $rdata;
            }elseif(isset($submit["status"]) && $submit["status"] == "DomSecretProvided"){
                $this->error = Bootstrap::$lang->get("errors/domain4",Config::get("general/local"));
                return false;
            }else{
                $this->error = Utility::jencode($submit);
                return false;
            }
        }
        public function renewal($params=[],$domain='',$sld='',$tld='',$year=1,$oduedate='',$nduedate=''){
            $order_id = $this->getOrderID($params);
            if(!$order_id) return false;

            $OrderDetails    = $this->api->getDetails($order_id,"OrderDetails",false);
            if(!$OrderDetails){
                $this->error = $this->api->error;
                return false;
            }

            $paramsx = [
                'order-id' => $order_id,
                'years' => $year,
                'exp-date' => $OrderDetails["endtime"],
                'invoice-option' => "NoInvoice",
            ];

            $handle         = $this->api->renewal($paramsx);
            if(isset($handle["actionstatus"]) && $handle["actionstatus"] == "Success" && isset($handle["status"]) && $handle["status"] == "Success"){
                return true;
            }else{
                $this->error = $this->api->error." -> ".print_r($paramsx,true);
                return false;
            }
        }
        public function transfer($domain='',$sld='',$tld='',$year=1,$dns=[],$whois=[],$wprivacy=false,$tcode=''){
            return $this->register($domain,$sld,$tld,$year,$dns,$whois,$wprivacy,$tcode);
        }

        public function cost_prices($type='domain'){
            if(!$this->config["settings"]["adp"]) return false;

            $pkeys     = $this->product_keys($type);

            if(!$pkeys) return false;

            $prices    = $this->api->cost_prices();
            if(!$prices){
                $this->error = $this->api->error;
                return false;
            }

            $result = [];

            if($type == "domain"){
                foreach($pkeys AS $dotkey=>$dotval){
                    if(isset($prices[$dotkey]["addnewdomain"])){
                        $result[$dotval] = [
                            'register' => $prices[$dotkey]["addnewdomain"][1],
                            'transfer' => $prices[$dotkey]["addtransferdomain"][1],
                            'renewal'  => $prices[$dotkey]["renewdomain"][1],
                        ];
                    }
                }
            }


            return $result;
        }
        public function product_keys($type='domain'){
            $keys    = $this->api->product_keys();
            if(!$keys){
                $this->error = $this->api->error;
                return false;
            }
            $keys   = $keys["domorder"];
            $result = [];

            if($type == "domain")
                if($keys) foreach($keys AS $r=>$v) if(is_array($v)) foreach($v AS $ke=>$va) if(isset($va[0])) $result[$ke] = $va[0];

            return $result;
        }
        public function getOrderID($params=[]){
            $domain = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);
            if(isset($params["order_id"])) return $params["order_id"];
            $result = $this->api->getOrderId($domain);
            if(!$result){
                $this->error = $this->api->error;
                return false;
            }
            return $result;
        }
        public function getCustomerID($email=''){
            $result = $this->api->getCustomerDetail($email);
            if(!$result){
                $this->error = $this->api->error;
                return false;
            }
            return $result["customerid"];
        }
        public function addCustomer($data=[]){

            $params = $data;
            unset($params["email"]);
            $params["lang-pref"] = "en";
            $params["username"] = $data["email"];
            $params["passwd"] = Utility::generate_hash(12);

            $result = $this->api->addCustomer($params);
            if(!$result){
                $this->error = $this->api->error;
                return false;
            }

            if(!isset($result[0])){
                $this->error = "Customer creation failed. :: ".print_r($result,true);
                return false;
            }
            return $result[0];
        }
        public function NsDetails($params=[]){
            $order_id = $this->getOrderID($params);
            if(!$order_id) return false;

            $NsDetails      = $this->api->getDetails($order_id,"NsDetails",false);
            if(!$NsDetails){
                $this->error = $this->api->error;
                return false;
            }
            $returns = [];
            if(isset($NsDetails["ns1"])) $returns["ns1"] = $NsDetails["ns1"];
            if(isset($NsDetails["ns2"])) $returns["ns2"] = $NsDetails["ns2"];
            if(isset($NsDetails["ns3"])) $returns["ns3"] = $NsDetails["ns3"];
            if(isset($NsDetails["ns4"])) $returns["ns4"] = $NsDetails["ns4"];
            return $returns;
        }
        public function ModifyDns($params=[],$dns=[]){
            $order_id = $this->getOrderID($params);
            if(!$order_id) return false;

            if($dns) foreach($dns AS $i=>$dn) $dns[$i] = idn_to_ascii($dn,0,INTL_IDNA_VARIANT_UTS46);

            $modifyDns  = $this->api->ModifyDns($order_id,$dns);
            if(!$modifyDns){
                $this->error = $this->api->error;
                return false;
            }

            if(isset($modifyDns["status"]) && $modifyDns["status"] == "Success" && isset($modifyDns["actionstatus"]) && $modifyDns["actionstatus"] == "Success"){
                return true;
            }else{
                $this->error = isset($modifyDns["actiontypedesc"]) ? $modifyDns["actiontypedesc"] : "Error message empty";
                return false;
            }
        }
        public function CNSList($params=[]){
            $order_id = $this->getOrderID($params);
            if(!$order_id) return false;

            $CNSList    = $this->api->getDetails($order_id,"All",false);
            if(!$CNSList){
                $this->error = $this->api->error;
                return false;
            }
            if(isset($CNSList["cns"])){
                $CNSList = $CNSList["cns"];
                $result  = [];
                $i       = 0;
                foreach($CNSList AS $k=>$v){
                    $k  = idn_to_ascii($k,0,INTL_IDNA_VARIANT_UTS46);
                    $i+=1;
                    $result[$i] = ['ns' => $k,'ip' => $v[0]];
                }
                return $result;
            }else{
                $this->error = "Cns not failed data";
                return false;
            }
        }
        public function addCNS($params=[],$ns='',$ip=''){
            $order_id = $this->getOrderID($params);
            if(!$order_id) return false;

            $ns     = idn_to_ascii($ns,0,INTL_IDNA_VARIANT_UTS46);

            $addCNS = $this->api->addCNS($order_id,$ns,$ip);
            if(!$addCNS){
                $this->error = $this->api->error;
                return false;
            }

            if(isset($addCNS["status"]) && $addCNS["status"] == "Success" && isset($addCNS["actionstatus"]) && $addCNS["actionstatus"] == "Success")
                return ['ns' => $ns,'ip' => $ip];

            return false;
        }
        public function ModifyCNS($params=[],$cns=[],$ns='',$ip=''){
            $order_id = $this->getOrderID($params);
            if(!$order_id) return false;

            $cns_ns      = idn_to_ascii($cns["ns"],0,INTL_IDNA_VARIANT_UTS46);
            $ns          = idn_to_ascii($ns,0,INTL_IDNA_VARIANT_UTS46);

            if($cns_ns != $ns){
                $modify1     = $this->api->modifyCNsName($order_id,$cns_ns,$ns);
                if(!$modify1){
                    $this->error = $this->api->error;
                    return false;
                }
            }

            if($cns["ip"] != $ip){
                $modify2    = $this->api->modifyCNsIP($order_id,$cns_ns,$cns["ip"],$ip);
                if(!$modify2){
                    $this->error = $this->api->error;
                    return false;
                }
            }

            $total          = 0;

            if(isset($modify1["status"]) && $modify1["status"] == "Success" && isset($modify1["actionstatus"]) && $modify1["actionstatus"] == "Success") $total+=1;

            if(isset($modify2["status"]) && $modify2["status"] == "Success" && isset($modify2["actionstatus"]) && $modify2["actionstatus"] == "Success") $total+=1;

            return $total>0;
        }
        public function DeleteCNS($params=[],$cns='',$ip=''){
            $order_id = $this->getOrderID($params);
            if(!$order_id) return false;

            $cns        = idn_to_ascii($cns,0,INTL_IDNA_VARIANT_UTS46);

            $delete     = $this->api->deleteCNS($order_id,$cns,$ip);
            if(!$delete){
                $this->error = $this->api->error;
                return false;
            }

            return isset($delete["status"]) && $delete["status"] == "Success" && isset($delete["actionstatus"]) && $delete["actionstatus"] == "Success";

        }
        public function getWhoisPrivacy($params=[]){
            $order_id = $this->getOrderID($params);
            if(!$order_id) return false;

            $OrderDetails    = $this->api->getDetails($order_id,"OrderDetails",false);
            if(!$OrderDetails){
                $this->error = $this->api->error;
                return false;
            }

            if(!isset($OrderDetails["isprivacyprotected"])){
                $this->error = $this->lang["error4"];
                return false;
            }

            return $OrderDetails["isprivacyprotected"] == "true" ? "active" : "passive";
        }
        public function ModifyWhois($params=[],$whois=[]){
            $order_id = $this->getOrderID($params);
            if(!$order_id) return true;

            $OrderDetails    = $this->api->getDetails($order_id,"OrderDetails",false);
            if(!$OrderDetails){
                $this->error = $this->api->error;
                return true;
            }

            $customer_id                = $OrderDetails["customerid"];
            $contact_registrant         = $this->contactProcess($whois["registrant"] ?? $whois);
            $contact_administrative     = $this->contactProcess($whois["administrative"] ?? $whois);
            $contact_technical          = $this->contactProcess($whois["technical"] ?? $whois);
            $contact_billing            = $this->contactProcess($whois["billing"] ?? $whois);

            if(!$customer_id) $customer_id = $this->addCustomer($contact_registrant);
            if(!$customer_id) return false;


            $reg_contact_id = $this->addContact($params["tld"],['contact' => $contact_registrant,'customer_id' => $customer_id]);
            if(!$reg_contact_id){
                $this->error = $this->api->error;
                return false;
            }

            $admin_contact_id = $this->addContact($params["tld"],['contact' => $contact_administrative,'customer_id' => $customer_id]);
            if(!$admin_contact_id){
                $this->error = $this->api->error;
                return false;
            }

            $tech_contact_id = $this->addContact($params["tld"],['contact' => $contact_technical,'customer_id' => $customer_id]);
            if(!$tech_contact_id){
                $this->error = $this->api->error;
                return false;
            }

            $billing_contact_id = $this->addContact($params["tld"],['contact' => $contact_billing,'customer_id' => $customer_id]);
            if(!$billing_contact_id){
                $this->error = $this->api->error;
                return false;
            }


            $modifyContact = $this->api->modifyContact($order_id,$reg_contact_id,$admin_contact_id,$tech_contact_id,$billing_contact_id);
            if(!$modifyContact){
                $this->error = $this->api->error;
                return false;
            }
            return true;
        }
        public function contactProcess($data=[]){
            $fields     = [
                'name' =>  $data["Name"],
                'company' =>  $data["Company"] == '' ? "N/A" : $data["Company"],
                'email' =>  $data["EMail"],
                'address-line-1' =>  Utility::substr($data["AddressLine1"],0,50),
                'address-line-2' =>  Utility::substr($data["AddressLine2"],0,50),
                'city' =>  $data["City"],
                'state' =>  $data["State"],
                'zipcode' =>  $data["ZipCode"] == '' ? "0000" : $data["ZipCode"],
                'country' =>  $data["Country"],
                'phone-cc' => $data["PhoneCountryCode"],
                'phone' => $data["Phone"],
                'fax-cc' => $data["FaxCountryCode"],
                'fax' => $data["Fax"],
            ];

            return $fields;
        }
        public function addContact($tld='',$data=[]){
            $contact        = $data["contact"];
            $customer_id    = $data["customer_id"];
            return $this->api->addContact($customer_id,$contact,$tld);
        }
        public function getTransferLock($params=[]){
            $order_id = $this->getOrderID($params);
            if(!$order_id) return false;

            $OrderDetails    = $this->api->getDetails($order_id,"OrderDetails",false);
            if(!$OrderDetails){
                $this->error = $this->api->error;
                return false;
            }

            if(!isset($OrderDetails["orderstatus"])){
                $this->error = $this->lang["error5"];
                return false;
            }
            $status = $OrderDetails["orderstatus"];
            foreach($status AS $s) if($s == "transferlock") return true;
            return false;
        }
        public function isInactive($params=[]){
            $order_id = $this->getOrderID($params);
            if(!$order_id) return false;

            $OrderDetails    = $this->api->getDetails($order_id,"OrderDetails",false);
            if(!$OrderDetails){
                $this->error = $this->api->error;
                return false;
            }

            if(!isset($OrderDetails["orderstatus"])){
                $this->error = $this->lang["error5"];
                return false;
            }
            $status = $OrderDetails["orderstatus"];
            if(empty($status)) return false;
            foreach($status AS $s) if($s != "transferlock") return true;
        }
        public function ModifyTransferLock($params=[],$type=''){
            $order_id = $this->getOrderID($params);
            if(!$order_id) return false;

            $modify     = $this->api->modifyTransferLock($order_id,$type);
            if(!$modify){
                $this->error = $this->api->error;
                return false;
            }

            return isset($modify["status"]) && $modify["status"] == "Success" && isset($modify["actionstatus"]) && $modify["actionstatus"] == "Success";
        }
        public function modifyPrivacyProtection($params=[],$staus=''){
            $order_id = $this->getOrderID($params);
            if(!$order_id) return false;
            $nstatus  = $staus == "enable" ? "true" : "false";

            $modify = $this->api->modifyPrivacyProtection($order_id,$nstatus);
            if(!$modify){
                $this->error = $this->api->error;
                return false;
            }

            if(isset($modify["status"]) && $modify["status"] == "Success" && isset($modify["actionstatus"]) && $modify["actionstatus"] == "Success")
                return true;
            else{
                $this->error = $this->api->error;
                return false;
            }
        }
        public function purchasePrivacyProtection($params=[]){
            $order_id = $this->getOrderID($params);
            if(!$order_id) return false;

            $apply = $this->api->purchasePrivacy($order_id);
            if(!$apply){
                $this->error = $this->api->error;
                return false;
            }

            if(isset($apply["status"]) && $apply["status"] == "Success" && isset($apply["actionstatus"]) && $apply["actionstatus"] == "Success")
                return true;
            else{
                $this->error = $this->api->error;
                return false;
            }
        }
        public function getAuthCode($params=[]){
            $order_id = $this->getOrderID($params);
            if(!$order_id) return false;

            $authCode       = Utility::generate_hash(14);
            $modify         = $this->api->modifyAuthCode($order_id,$authCode);
            if(!$modify){
                $this->error = $this->api->error;
                return false;
            }

            if(isset($modify["status"]) && $modify["status"] == "Success" && isset($modify["actionstatus"]) && $modify["actionstatus"] == "Success")
                return $authCode;
            else{
                $this->error = $this->api->error;
                return false;
            }
        }
        public function modifyAuthCode($params=[],$code=''){
            $order_id = $this->getOrderID($params);
            if(!$order_id) return false;

            $authCode       = $code;
            $modify         = $this->api->modifyAuthCode($order_id,$authCode);
            if(!$modify){
                $this->error = $this->api->error;
                return false;
            }

            if(isset($modify["status"]) && $modify["status"] == "Success" && isset($modify["actionstatus"]) && $modify["actionstatus"] == "Success")
                return true;
            else{
                $this->error = $this->api->error;
                return false;
            }
        }
        public function sync($params=[]){

            $order_id = $this->getOrderID($params);
            if(!$order_id) return false;

            $OrderDetails    = $this->api->getDetails($order_id,"All",false);
            if(!$OrderDetails){
                $this->error = $this->api->error;
                return false;
            }

            $endtime            = DateManager::timetostr("Y-m-d H:i:s",$OrderDetails["endtime"]);
            $currentstatus      = $OrderDetails["currentstatus"];

            $return_data    = ['status'   => "waiting"];

            if($endtime)
                $return_data["endtime"] = $endtime;

            if($currentstatus == "Active")
                $return_data["status"] = "active";
            elseif($currentstatus == "Expired")
                $return_data["status"] = "expired";

            return $return_data;
        }
        public function transfer_sync($params=[]){

            $order_id = $this->getOrderID($params);
            if(!$order_id) return false;

            $OrderDetails    = $this->api->getDetails($order_id,"All",false);
            if(!$OrderDetails){
                $this->error = $this->api->error;
                return false;
            }

            $endtime            = DateManager::timetostr("Y-m-d H:i:s",$OrderDetails["endtime"]);
            $currentstatus      = $OrderDetails["currentstatus"];

            $return_data    = ['status'   => "waiting"];

            if($endtime)
                $return_data["endtime"] = $endtime;

            if($currentstatus == "Active")
                $return_data["status"] = "active";
            elseif($currentstatus == "Expired")
                $return_data["status"] = "expired";

            return $return_data;
        }
        public function get_info($params=[]){
            $order_id = $this->getOrderID($params);
            if(!$order_id) return false;

            $OrderDetails    = $this->api->getDetails($order_id,"All",false);
            if(!$OrderDetails){
                $this->error = $this->api->error;
                return false;
            }

            $result             = [];

            $cdate              = DateManager::timetostr("Y-m-d H:i:s",$OrderDetails["creationtime"]);
            $duedate            = DateManager::timetostr("Y-m-d H:i:s",$OrderDetails["endtime"]);
            $wprivacy           = isset($OrderDetails["isprivacyprotected"]) ? ($OrderDetails["isprivacyprotected"] == "true") : "none";
            if($wprivacy && $wprivacy != "none"){
                $wprivacy_endtime_i   = isset($OrderDetails["privacyprotectendtime"]) ? $OrderDetails["privacyprotectendtime"] : "none";
                if($wprivacy_endtime_i && $wprivacy_endtime_i != "none")
                    $wprivacy_endtime   = DateManager::timetostr("Y-m-d H:i:s",$OrderDetails["privacyprotectendtime"]);
            }

            $ns1                = isset($OrderDetails["ns1"]) ? $OrderDetails["ns1"] : false;
            $ns2                = isset($OrderDetails["ns2"]) ? $OrderDetails["ns2"] : false;
            $ns3                = isset($OrderDetails["ns3"]) ? $OrderDetails["ns3"] : false;
            $ns4                = isset($OrderDetails["ns4"]) ? $OrderDetails["ns4"] : false;

            $key_replace = [
                'RegistrantContactDetails'      => "registrant",
                'AdminContactDetails'           => "administrative",
                'TechContactDetails'            => "technical",
                'BillingContactDetails'         => "billing",
            ];

            $whois      = [];

            foreach(array_keys($key_replace) AS $ct)
            {
                $s_key              = $key_replace[$ct];
                $w_data             = $OrderDetails[$ct] ?? $OrderDetails["RegistrantContactDetails"];

                $whois[$s_key] = [
                    'Name'              =>  $w_data["name"],
                    'Company'           =>  $w_data["company"] == 'N/A' ? "" : $w_data["company"],
                    'EMail'             =>  $w_data["emailaddr"],
                    'AddressLine1'      =>  $w_data["address1"],
                    'AddressLine2'      =>  isset($w_data["address2"]) ? $w_data["address2"] : "",
                    'City'              =>  $w_data["city"],
                    'State'             =>  isset($w_data["state"]) ? $w_data["state"] : '',
                    'ZipCode'           =>  $w_data["zip"],
                    'Country'           =>  $w_data["country"],
                    'PhoneCountryCode'  => $w_data["telnocc"],
                    'Phone'             => $w_data["telno"],
                    'FaxCountryCode'    => isset($w_data["faxnocc"]) ? $w_data["faxnocc"] : "",
                    'Fax'               => isset($w_data["faxno"]) ? $w_data["faxno"] : "",
                ];
            }


            if($cdate) $result["creation_time"] = $cdate;
            if($duedate) $result["end_time"] = $duedate;
            if(isset($wprivacy) && $wprivacy != "none"){
                $result["whois_privacy"] = ['status' => $wprivacy ? "enable" : "disable"];
                if(isset($wprivacy_endtime) && $wprivacy_endtime) $result["whois_privacy"]["end_time"] = $wprivacy_endtime;
            }
            if(isset($ns1) && $ns1) $result["ns1"] = $ns1;
            if(isset($ns2) && $ns2) $result["ns2"] = $ns2;
            if(isset($ns3) && $ns3) $result["ns3"] = $ns3;
            if(isset($ns4) && $ns4) $result["ns4"] = $ns4;
            if(isset($whois) && $whois) $result["whois"] = $whois;

            $result["transferlock"] = isset($OrderDetails["orderstatus"][0]) && $OrderDetails["orderstatus"][0] == "transferlock";

            if(isset($OrderDetails["cns"])){
                $CNSList = $OrderDetails["cns"];
                $cnsx  = [];
                $i       = 0;
                foreach($CNSList AS $k=>$v){
                    $i+=1;
                    $cnsx[$i] = ['ns' => $k,'ip' => $v[0]];
                }
                $result["cns"] = $cnsx;
            }


            return $result;

        }
        public function domains($test=false){
            Helper::Load(["User"]);
            $params = [
                'order-by' => "orderid",
                'page-no'  => 1,
                'status'   => "Active",
            ];
            if($test){
                $params["no-of-records"] = 10;
            }else{
                $params["no-of-records"] = 500;
            }

            $response = $this->api->search($params);
            if(!$response && $this->api->error) $this->error = $this->api->error;
            if($test) return $response ? true : false;

            $result     = [];

            if($response){
                foreach($response AS $res){
                    $cdate = isset($res["orders.creationdt"]) ? DateManager::timetostr("Y-m-d H:i",$res["orders.creationdt"]) : '';
                    $edate = isset($res["orders.endtime"]) ? DateManager::timetostr("Y-m-d H:i",$res["orders.endtime"]) : '';
                    $domain = isset($res["entity.description"]) ? $res["entity.description"] : '';
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
            $pkeys     = $this->product_keys("domain");

            if(!$pkeys) return false;

            $prices    = $this->api->cost_prices();
            if(!$prices){
                $this->error = $this->api->error;
                return false;
            }

            Helper::Load(["Products","Money"]);

            $cost_cid           = isset($this->config["settings"]["cost-currency"]) ? $this->config["settings"]["cost-currency"] : 4;
            $profit_rate        = Config::get("options/domain-profit-rate");

            foreach($pkeys AS $dotkey=>$dotval){
                if(!isset($prices[$dotkey]["addnewdomain"])) continue;

                $name           = Utility::strtolower(trim($dotval));
                $api_cost_prices    = [
                    'register' => number_format($prices[$dotkey]["addnewdomain"][1],2,'.',''),
                    'transfer' => number_format($prices[$dotkey]["addtransferdomain"][1],2,'.',''),
                    'renewal'  => number_format($prices[$dotkey]["renewdomain"][1],2,'.',''),
                ];

                $paperwork      = 0;
                $epp_code       = 1;
                $dns_manage     = 1;
                $whois_privacy  = 1;
                $module         = "Resellbiz";

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



    }