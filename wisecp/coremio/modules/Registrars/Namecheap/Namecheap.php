<?php

    class Namecheap extends RegistrarModule
    {
        public $api = false;
        public $config = [];
        public $lang = [];
        public $error = null;
        public $whidden = [];
        public $order = [];
        public $docs = [];


        function __construct($external = [])
        {
            if (function_exists("ini_set")) ini_set("max_execution_time", 3600);

            $this->config = Modules::Config("Registrars", __CLASS__);
            $this->lang = Modules::Lang("Registrars", __CLASS__);
            if (is_array($external) && sizeof($external) > 0)
                $this->config = array_merge($this->config, $external);
            if (!isset($this->config["settings"]["api-key"])) {
                $this->error = $this->lang["error1"];
                return false;
            }

            if (!class_exists("Namecheap_Api")) include __DIR__ . DS . "api.php";
            if (isset($this->config["settings"]["whidden-amount"])) {
                $whidden_amount = $this->config["settings"]["whidden-amount"];
                $whidden_currency = $this->config["settings"]["whidden-currency"];
                $this->whidden["amount"] = $whidden_amount;
                $this->whidden["currency"] = $whidden_currency;
            }
            $test_mode = $this->config["settings"]["test-mode"];
            $username = $this->config["settings"]["username"];
            $akey = $this->config["settings"]["api-key"];
            $username_sandbox = $this->config["settings"]["username-sandbox"];
            $akey_sandbox = $this->config["settings"]["api-key-sandbox"];
            $akey = Crypt::decode($akey, Config::get("crypt/system"));
            $akey_sandbox = Crypt::decode($akey_sandbox, Config::get("crypt/system"));
            $this->api = new Namecheap_Api($username, $akey, $username_sandbox, $akey_sandbox, $test_mode);
        }

        private function setConfig($username = '', $akey = '', $username_sandbox = '', $akey_sandbox = '', $test_mode = false)
        {
            $this->config["settings"]["username"] = $username;
            $this->config["settings"]["api-key"] = $akey;
            $this->config["settings"]["username-sandbox"] = $username_sandbox;
            $this->config["settings"]["api-key-sandbox"] = $akey_sandbox;
            $this->config["settings"]["test-mode"] = $test_mode;
            $this->api = new Namecheap_Api($username, $akey, $username_sandbox, $akey_sandbox, $test_mode);
        }

        public function set_order($order = [])
        {
            $this->order = $order;
            return $this;
        }

        public function define_docs($docs = [])
        {
            $this->docs = $docs;
        }

        public function testConnection($config = [])
        {
            $username = $config["settings"]["username"];
            $akey = $config["settings"]["api-key"];
            $username_sandbox = $config["settings"]["username-sandbox"];
            $akey_sandbox = $config["settings"]["api-key-sandbox"];
            $test_mode = $config["settings"]["test-mode"];

            if ((!$username || !$akey) && !$test_mode) {
                $this->error = $this->lang["error6"];
                return false;
            }

            if ((!$username_sandbox || !$akey_sandbox) && $test_mode) {
                $this->error = $this->lang["error6"];
                return false;
            }

            $akey = Crypt::decode($akey, Config::get("crypt/system"));
            $akey_sandbox = Crypt::decode($akey_sandbox, Config::get("crypt/system"));
            $this->setConfig($username, $akey, $username_sandbox, $akey_sandbox, $test_mode);

            $check = $this->domains(true);

            if (!$check) return false;

            return true;
        }

        public function questioning($sld = null, $tlds = [])
        {
            if ($sld == '' || empty($tlds)) {
                $this->error = $this->lang["error2"];
                return false;
            }

            $result = [];
            $domains = [];
            if ($tlds) {
                foreach ($tlds as $tld) {
                    $result[$tld] = ['status' => "unknown"];
                    $domains[] = $sld . "." . $tld;
                }
            }
            $availability = $this->api->domains_check(['DomainList' => implode(",", $domains)]);
            if (!$availability && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }

            if (!isset($availability["DomainCheckResult"][0]))
                $availability["DomainCheckResult"] = [0 => $availability["DomainCheckResult"]];

            if ($availability["DomainCheckResult"]) {
                foreach ($availability["DomainCheckResult"] as $row) {
                    $row = $row["@attributes"];
                    $tld = str_replace($sld . ".", "", $row["Domain"]);
                    $result[$tld]["status"] = $row["Available"] == "true" ? "available" : "unavailable";
                    if (($row["IsPremiumName"] ?? "false") == "true") {
                        $result[$tld]["premium"] = true;
                        $result[$tld]["premium_price"] = [
                            'amount'   => $row["PremiumRegistrationPrice"] ?? 0,
                            'currency' => $this->config["settings"]["cost-currency"] ?? 4,
                        ];
                    }
                }
            }

            return $result;

        }

        public function register($domain = '', $sld = '', $tld = '', $year = 1, $dns = [], $whois = [], $wprivacy = false)
        {
            $detail = $this->api->domains_getInfo($domain);
            if ($detail) {
                if ($detail["DomainGetInfoResult"]["@attributes"]["Status"] != "Ok") return ['status' => "FAIL"];
                else return ['config' => ['id' => $detail["DomainGetInfoResult"]["@attributes"]["ID"]]];
            }

            if (!function_exists("idn_to_ascii")) {
                $this->error = "Intl -> IDN : idn_to_ascii function not found.";
                return false;
            }

            $params = [];

            $params["DomainName"] = $domain;
            $params["Years"] = $year;

            $wg_ex = ["bz", "ca", "cn", "co.uk", "de", "eu", "in", "me.uk", "mobi", "nu", "org.uk", "us", "ws"];

            if ($wprivacy && !in_array($tld, $wg_ex)) {
                $params["AddFreeWhoisguard"] = "yes";
                $params["WGEnabled"] = "yes";
            }

            $params["Nameservers"] = implode(",", $dns);

            if ($this->config["settings"]["coupon"])
                $params["PromotionCode"] = $this->config["settings"]["coupon"];

            $params["IdnCode"] = idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46);

            if ($this->docs) foreach ($this->docs as $k => $v) $params[$k] = $v;


            $contacts = $this->contactProcess($whois);
            $params = array_merge($params, $contacts);

            $register = $this->api->domains_create($params);

            if (!$register && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }

            $register = $register["DomainCreateResult"]["@attributes"];

            return [
                'status' => "SUCCESS",
                'config' => ['id' => $register["DomainID"], 'creation_info' => $register],
            ];
        }

        public function transfer($domain = '', $sld = '', $tld = '', $year = 1, $dns = [], $whois = [], $wprivacy = false, $tcode = '')
        {
            $detail = $this->api->domains_getInfo($domain);
            if ($detail) {
                if ($detail["DomainGetInfoResult"]["@attributes"]["Status"] == "Ok") {
                    $this->error = $domain . " already exists.";
                    return false;
                }
            }

            $params = [];

            $params["DomainName"] = $domain;
            $params["Years"] = $year;

            $wg_ex = ["bz", "ca", "cn", "co.uk", "de", "eu", "in", "me.uk", "mobi", "nu", "org.uk", "us", "ws"];

            if ($wprivacy && !in_array($tld, $wg_ex)) {
                $params["AddFreeWhoisguard"] = "yes";
                $params["WGEnabled"] = "yes";
            }

            if ($this->config["settings"]["coupon"])
                $params["PromotionCode"] = $this->config["settings"]["coupon"];

            $params["EPPCode"] = preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', $tcode) ? base64_encode($tcode) : $tcode;

            $transfer = $this->api->domains_transfer_create($params);

            if (!$transfer && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }

            $transfer = $transfer["DomainTransferCreateResult"]["@attributes"];


            $returnData = [
                'status'        => "SUCCESS",
                'message'       => null,
                'config'        => ['TransferID' => $transfer["TransferID"]],
                'creation_info' => $transfer,
            ];

            if ($wprivacy) $returnData["whois_privacy"] = ['status' => true, 'message' => null];

            return $returnData;
        }

        public function renewal($params = [], $domain = '', $sld = '', $tld = '', $year = 1, $oduedate = '', $nduedate = '')
        {
            $detail = $this->api->domains_getInfo($domain);
            if (!$detail) {
                $this->error = $this->api->error;
                return false;
            }

            $data = [
                'DomainName' => $domain,
                'Years'      => $year,
            ];

            if ($this->config["settings"]["coupon"])
                $data["PromotionCode"] = $this->config["settings"]["coupon"];


            $handle = $this->api->domains_renew($data);

            if (!$handle && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function contactProcess($data = [])
        {
            $key_replace = [
                'Registrant' => "registrant",
                'Tech'       => "technical",
                'Admin'      => "administrative",
                'AuxBilling' => "billing",
                'Billing'    => "billing",
            ];

            $result = [];

            foreach (array_keys($key_replace) as $type) {
                $a_data = $data[$key_replace[$type]] ?? $data;

                $result[$type . 'FirstName'] = $a_data["FirstName"];
                $result[$type . 'LastName'] = $a_data["LastName"];
                $result[$type . 'OrganizationName'] = $a_data["Company"];
                $result[$type . 'EmailAddress'] = $a_data["EMail"];
                $result[$type . 'Address1'] = $a_data["AddressLine1"];
                $result[$type . 'City'] = Utility::substr($a_data["City"], 0, 50);
                $result[$type . 'StateProvince'] = Utility::substr($a_data["State"], 0, 50);
                $result[$type . 'PostalCode'] = $a_data["ZipCode"];
                $result[$type . 'Country'] = $a_data["Country"];
                $result[$type . 'Phone'] = $a_data["Phone"] ? "+" . $a_data["PhoneCountryCode"] . "." . $a_data["Phone"] : '';
                $result[$type . 'Fax'] = $a_data["Fax"] ? "+" . $a_data["FaxCountryCode"] . "." . $a_data["Fax"] : '';
            }
            return $result;

        }

        public function getWhois($params = [])
        {
            $contact = $this->api->domains_getContacts($params["domain"]);

            if (!$contact && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }

            $key_replace = [
                'Registrant' => "registrant",
                'Tech'       => "technical",
                'Admin'      => "administrative",
                'AuxBilling' => "billing",
                'Billing'    => "billing",
            ];

            $whois = [];

            $contactList = $contact["DomainContactsResult"];


            foreach (array_keys($key_replace) as $ct) {
                $s_key = $key_replace[$ct];

                $contact = $contactList[$ct] ?? $contactList["Registrant"];


                $phone = $contact["Phone"] ? Filter::phone_smash(str_replace('.', '', $contact["Phone"])) : '';
                $fax = $contact["Fax"] ? Filter::phone_smash(str_replace('.', '', $contact["Fax"])) : '';
                $name = [$contact["FirstName"]];
                if ($contact["LastName"]) $name[] = $contact["LastName"];


                $address2 = is_array($contact["Address2"]) ? (empty($contact["Address2"]) ? '' : $contact["Address2"]) : $contact["Address2"];

                $whois[$s_key] = [
                    'Name'             => implode(" ", $name),
                    'FirstName'        => $contact["FirstName"],
                    'LastName'         => $contact["LastName"],
                    'Company'          => $contact["OrganizationName"],
                    'EMail'            => $contact["EmailAddress"],
                    'Address'          => $contact["Address1"] . ($address2 ? " " . $address2 : ''),
                    'City'             => $contact["City"],
                    'State'            => $contact["StateProvince"],
                    'ZipCode'          => $contact["PostalCode"],
                    'Country'          => $contact["Country"],
                    'PhoneCountryCode' => isset($phone["cc"]) ? $phone["cc"] : '',
                    'Phone'            => isset($phone["number"]) ? $phone["number"] : '',
                    'FaxCountryCode'   => isset($fax["cc"]) ? $fax["cc"] : '',
                    'Fax'              => isset($fax["number"]) ? $fax["number"] : '',
                ];
            }


            return $whois;
        }

        public function ModifyWhois($params = [], $whois = [])
        {
            $detail = $this->api->domains_getInfo($params["domain"]);
            if (!$detail) {
                $this->error = $this->api->error;
                return true;
            }

            $contacts = $this->contactProcess($whois);
            $contacts['DomainName'] = $params["domain"];

            $set = $this->api->domains_setContacts($contacts);

            if (!$set && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function NsDetails($params = [])
        {
            $NsDetails = $this->api->domains_dns_getList($params["name"], $params["tld"]);
            if (!$NsDetails) {
                $this->error = $this->api->error;
                return false;
            }

            $NsDetails = $NsDetails["DomainDNSGetListResult"];

            $returns = [];
            if (isset($NsDetails[0])) $returns["ns1"] = strtolower($NsDetails[0]);
            if (isset($NsDetails[1])) $returns["ns2"] = strtolower($NsDetails[1]);
            if (isset($NsDetails[2])) $returns["ns3"] = strtolower($NsDetails[2]);
            if (isset($NsDetails[3])) $returns["ns4"] = strtolower($NsDetails[3]);
            return $returns;
        }

        public function ModifyDns($params = [], $dns = [])
        {
            $data = [
                'SLD'         => $params["name"],
                'TLD'         => $params["tld"],
                'Nameservers' => implode(",", $dns),
            ];

            $modifyDns = $this->api->domains_dns_setCustom($data);

            if (!$modifyDns && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function CNSList($params = [])
        {
            return [];
        }

        public function addCNS($params = [], $ns = '', $ip = '')
        {

            $data = [
                'SLD'        => $params["name"],
                'TLD'        => $params["tld"],
                'Nameserver' => $ns,
                'IP'         => $ip,
            ];


            $addCNS = $this->api->domains_ns_create($data);
            if (!$addCNS) {
                $this->error = $this->api->error;
                return false;
            }

            return ['ns' => $ns, 'ip' => $ip];
        }

        public function ModifyCNS($params = [], $cns = [], $ns = '', $ip = '')
        {

            $data = [
                'SLD'        => $params["name"],
                'TLD'        => $params["tld"],
                'Nameserver' => $cns["ns"],
                'OldIP'      => $cns["ip"],
            ];

            if ($cns["ns"] !== $ns) {
                $delete = $this->DeleteCNS($params, $cns["ns"], $cns["ip"]);
                if (!$delete) return false;
                return $this->addCNS($params, $ns, $ip);
            }

            $data["IP"] = $ip;

            $modifyCNS = $this->api->domains_ns_update($data);
            if (!$modifyCNS && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function DeleteCNS($params = [], $cns = '', $ip = '')
        {
            $deleteCNS = $this->api->domains_ns_delete([
                'SLD'        => $params["name"],
                'TLD'        => $params["tld"],
                'Nameserver' => $cns,
            ]);

            if (!$deleteCNS && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function getWhoisPrivacy($params = [])
        {
            $detail = $this->api->domains_getInfo($params["domain"]);
            if (!$detail) {
                $this->error = $this->api->error;
                return false;
            }

            $result = [];

            if (isset($detail["DomainGetInfoResult"]["Whoisguard"]) && $detail["DomainGetInfoResult"]["Whoisguard"]) {
                $whoisGuard = $detail["DomainGetInfoResult"]["Whoisguard"];
                $attributes = $whoisGuard["@attributes"];

                $result["status"] = $attributes["Enabled"] == "True" ? "enable" : "disable";
                if (isset($whoisGuard["ExpiredDate"])) {
                    list($m, $d, $y) = explode("/", $whoisGuard["ExpiredDate"]);
                    $result["end_time"] = $y . "-" . $m . "-" . $d;
                }
            }
            return $result;
        }

        public function getTransferLock($params = [])
        {
            $detail = $this->api->domains_getRegistrarLock($params["domain"]);
            if (!$detail) {
                $this->error = $this->api->error;
                return false;
            }

            $detail = $detail["DomainGetRegistrarLockResult"]["@attributes"];

            return $detail["RegistrarLockStatus"] == "true";
        }

        public function isInactive($params = [])
        {
            $detail = $this->api->domains_getInfo($params["domain"]);
            if (!$detail) {
                $this->error = $this->api->error;
                return false;
            }

            $attributes = $detail["DomainGetInfoResult"]["@attributes"];

            return $attributes["Status"] != "Ok";
        }

        public function ModifyTransferLock($params = [], $type = '')
        {

            $data = [
                'DomainName' => $params["domain"],
                'LockAction' => $type == "enable" ? "lock" : "unlock",
            ];

            $modify = $this->api->domains_setRegistrarLock($data);

            if (!$modify && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function modifyPrivacyProtection($params = [], $staus = '')
        {
            $detail = $this->api->domains_getInfo($params["domain"]);
            if (!$detail) {
                $this->error = $this->api->error;
                return false;
            }

            if (isset($detail["DomainGetInfoResult"]["Whoisguard"]) && $detail["DomainGetInfoResult"]["Whoisguard"]) {
                $whoisGuard = $detail["DomainGetInfoResult"]["Whoisguard"];
                if ($staus == "enable")
                    $modify = $this->api->whoisguard_enable([
                        'WhoisguardID'     => $whoisGuard["ID"],
                        'ForwardedToEmail' => $params["whois"]["registrant"]["EMail"] ?? $params["whois"]["EMail"],
                    ]);
                else
                    $modify = $this->api->whoisguard_disable([
                        'WhoisguardID' => $whoisGuard["ID"],
                    ]);

                if (!$modify && $this->api->error) {
                    $this->error = $this->api->error;
                    return false;
                }

            }

            return true;
        }

        public function purchasePrivacyProtection($params = [])
        {
            $detail = $this->api->domains_getInfo($params["domain"]);
            if (!$detail) {
                $this->error = $this->api->error;
                return false;
            }

            if (isset($detail["DomainGetInfoResult"]["Whoisguard"]) && $detail["DomainGetInfoResult"]["Whoisguard"]) {
                $whoisGuard = $detail["DomainGetInfoResult"]["Whoisguard"];

                $renew = $this->api->whoisguard_renew([
                    'WhoisguardID'  => $whoisGuard["ID"],
                    'Years'         => 1,
                    'PromotionCode' => $this->config["settings"]["coupon"],
                ]);

                if (!$renew && $this->api->error) {
                    $this->error = $this->api->error;
                    return false;
                }

            }
            return true;
        }

        public function getAuthCode($params = [])
        {
            $this->error = "Unable to get transfer code.";
            return false;
        }

        public function sync($params = [])
        {
            $detail = $this->api->domains_getInfo($params["domain"]);
            if (!$detail) {
                $this->error = $this->api->error;
                return false;
            }

            if ($detail["DomainGetInfoResult"]["@attributes"]["Status"] !== "Ok") return false;

            $endtime = $detail["DomainGetInfoResult"]["DomainDetails"]["ExpiredDate"];
            list($m, $d, $y) = explode("/", $endtime);
            $endtime = $y . "-" . $m . "-" . $d;
            $currentstatus = $detail["DomainGetInfoResult"]["@attributes"]["Status"];


            if ($endtime) {
                $return_data = [
                    'endtime' => $endtime . " 00:00:00",
                ];

                if ($currentstatus == "Ok") {
                    $return_data["status"] = "active";
                } elseif ($currentstatus == "Expired")
                    $return_data["status"] = "expired";

                return $return_data;
            }
            $this->error = "No end date information was received.";
            return false;
        }

        public function transfer_sync($params = [])
        {
            $detail = $this->api->domains_getInfo($params["domain"]);
            if (!$detail) {
                return [
                    'status' => "pending",
                ];
            }

            if ($detail["DomainGetInfoResult"]["@attributes"]["Status"] !== "Ok") return false;

            $endtime = $detail["DomainGetInfoResult"]["DomainDetails"]["ExpiredDate"];
            list($m, $d, $y) = explode("/", $endtime);
            $endtime = $y . "-" . $m . "-" . $d;
            $currentstatus = $detail["DomainGetInfoResult"]["@attributes"]["Status"];


            if ($endtime) {
                $return_data = [
                    'endtime' => $endtime . " 00:00:00",
                ];

                if ($currentstatus == "Ok") {
                    $return_data["status"] = "active";
                } elseif ($currentstatus == "Expired")
                    $return_data["status"] = "expired";

                return $return_data;
            }
            $this->error = "No end date information was received.";
            return false;
        }

        public function get_info($params = [])
        {
            $detail = $this->api->domains_getInfo($params["domain"]);
            if (!$detail) {
                $this->error = $this->api->error;
                return false;
            }

            $result = [];

            $cdate = $detail["DomainGetInfoResult"]["DomainDetails"]["CreatedDate"];
            list($m, $d, $y) = explode("/", $cdate);
            $cdate = $y . "-" . $m . "-" . $d . " 00:00:00";

            $duedate = $detail["DomainGetInfoResult"]["DomainDetails"]["ExpiredDate"];
            list($m, $d, $y) = explode("/", $duedate);
            $duedate = $y . "-" . $m . "-" . $d . " 00:00:00";

            $wprivacy = $this->getWhoisPrivacy($params);

            $NsDetails = $this->NsDetails($params);

            $ns1 = isset($NsDetails["ns1"]) ? $NsDetails["ns1"] : false;
            $ns2 = isset($NsDetails["ns2"]) ? $NsDetails["ns2"] : false;
            $ns3 = isset($NsDetails["ns3"]) ? $NsDetails["ns3"] : false;
            $ns4 = isset($NsDetails["ns4"]) ? $NsDetails["ns4"] : false;
            $whois = $this->getWhois($params);

            if ($cdate) $result["creation_time"] = $cdate;
            if ($duedate) $result["end_time"] = $duedate;
            $result["whois_privacy"] = $wprivacy;
            if (isset($ns1) && $ns1) $result["ns1"] = $ns1;
            if (isset($ns2) && $ns2) $result["ns2"] = $ns2;
            if (isset($ns3) && $ns3) $result["ns3"] = $ns3;
            if (isset($ns4) && $ns4) $result["ns4"] = $ns4;
            if (isset($whois) && $whois) $result["whois"] = $whois;

            $cns_list = $this->CNSList($params);
            if ($cns_list) $result["cns"] = $cns_list;

            $result["transferlock"] = $this->getTransferLock($params);

            return $result;

        }


        public function domains($test = false)
        {

            $response = $this->api->domains_getList();
            if (!$response && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }

            if ($test) return true;

            Helper::Load(["User"]);

            $result = [];

            $rows = $response["DomainGetListResult"]["Domain"];

            $cTotalItems = isset($response["Paging"]["TotalItems"]) ? $response["Paging"]["TotalItems"] : 0;
            $cPage = 1;

            while ($cTotalItems >= 100) {
                $cPage += 1;
                $response = $this->api->domains_getList($cPage);
                $cTotalItems = isset($response["Paging"]["TotalItems"]) ? $response["Paging"]["TotalItems"] : 0;
                if ($response) foreach ($response["DomainGetListResult"]["Domain"] as $row) $rows[] = $row;
            }

            if (isset($rows["@attributes"])) $rows = [0 => $rows];

            if ($rows) {
                foreach ($rows as $res) {
                    $res = $res["@attributes"];
                    $domain = $res["Name"];
                    list($m, $d, $y) = explode("/", $res["Created"]);
                    $created = $y . "-" . $m . "-" . $d;
                    list($m, $d, $y) = explode("/", $res["Expires"]);
                    $expires = $y . "-" . $m . "-" . $d;

                    $cdate = $created;
                    $edate = $expires;
                    if ($domain) {
                        $order_id = 0;
                        $user_data = [];
                        $is_imported = Models::$init->db->select("id,owner_id AS user_id")->from("users_products");
                        $is_imported->where("type", '=', "domain", "&&");
                        $is_imported->where("name", '=', $domain);
                        $is_imported = $is_imported->build() ? $is_imported->getAssoc() : false;
                        if ($is_imported) {
                            $order_id = $is_imported["id"];
                            $user_data = User::getData($is_imported["user_id"], "id,full_name,company_name", "array");
                        }
                        $result[] = [
                            'domain'        => $domain,
                            'creation_date' => $cdate,
                            'end_date'      => $edate,
                            'order_id'      => $order_id,
                            'user_data'     => $user_data,
                        ];
                    }
                }
            }

            return $result;
        }

        public function import_domain($data = [])
        {
            $config = $this->config;

            $imports = [];

            Helper::Load(["Orders", "Products", "Money"]);

            foreach ($data as $domain => $datum) {
                $domain_parse = Utility::domain_parser("http://" . $domain);
                $sld = $domain_parse["host"];
                $tld = $domain_parse["tld"];
                $user_id = (int)$datum["user_id"];
                if (!$user_id) continue;
                $info = $this->get_info([
                    'domain' => $domain,
                    'name'   => $sld,
                    'tld'    => $tld,
                ]);
                if (!$info) continue;

                $user_data = User::getData($user_id, "id,lang", "array");
                $ulang = $user_data["lang"];
                $locallang = Config::get("general/local");
                $productID = Models::$init->db->select("id")->from("tldlist")->where("name", "=", $tld);
                $productID = $productID->build() ? $productID->getObject()->id : false;
                if (!$productID) continue;
                $productPrice = Products::get_price("register", "tld", $productID);
                $productPrice_amt = $productPrice["amount"];
                $productPrice_cid = $productPrice["cid"];
                $start_date = $info["creation_time"];
                $end_date = $info["end_time"];
                $year = 1;

                $options = [
                    "established"      => true,
                    "group_name"       => Bootstrap::$lang->get_cm("website/account_products/product-type-names/domain", false, $ulang),
                    "local_group_name" => Bootstrap::$lang->get_cm("website/account_products/product-type-names/domain", false, $locallang),
                    "category_id"      => 0,
                    "domain"           => $domain,
                    "name"             => $sld,
                    "tld"              => $tld,
                    "dns_manage"       => true,
                    "whois_manage"     => true,
                    "transferlock"     => $info["transferlock"],
                    "cns_list"         => isset($info["cns"]) ? $info["cns"] : [],
                    "whois"            => isset($info["whois"]) ? $info["whois"] : [],
                ];

                if (isset($info["whois_privacy"]) && $info["whois_privacy"]) {
                    $options["whois_privacy"] = $info["whois_privacy"]["status"] == "enable";
                    $wprivacy_endtime = DateManager::ata();
                    if (isset($info["whois_privacy"]["end_time"]) && $info["whois_privacy"]["end_time"]) {
                        $wprivacy_endtime = $info["whois_privacy"]["end_time"];
                        $options["whois_privacy_endtime"] = $wprivacy_endtime;
                    }
                }

                if (isset($info["ns1"]) && $info["ns1"]) $options["ns1"] = $info["ns1"];
                if (isset($info["ns2"]) && $info["ns2"]) $options["ns2"] = $info["ns2"];
                if (isset($info["ns3"]) && $info["ns3"]) $options["ns3"] = $info["ns3"];
                if (isset($info["ns4"]) && $info["ns4"]) $options["ns4"] = $info["ns4"];


                $order_data = [
                    "owner_id"     => (int)$user_id,
                    "type"         => "domain",
                    "product_id"   => (int)$productID,
                    "name"         => $domain,
                    "period"       => "year",
                    "period_time"  => (int)$year,
                    "amount"       => (float)$productPrice_amt,
                    "total_amount" => (float)$productPrice_amt,
                    "amount_cid"   => (int)$productPrice_cid,
                    "status"       => "active",
                    "cdate"        => $start_date,
                    "duedate"      => $end_date,
                    "renewaldate"  => DateManager::Now(),
                    "module"       => $config["meta"]["name"],
                    "options"      => Utility::jencode($options),
                    "unread"       => 1,
                ];

                $insert = Orders::insert($order_data);
                if (!$insert) continue;

                if (isset($options["whois_privacy"])) {
                    $amount = Money::exChange($this->whidden["amount"], $this->whidden["currency"], $productPrice_cid);
                    $start = DateManager::Now();
                    $end = isset($wprivacy_endtime) ? $wprivacy_endtime : DateManager::ata();
                    Orders::insert_addon([
                        'invoice_id'  => 0,
                        'owner_id'    => $insert,
                        "addon_key"   => "whois-privacy",
                        'addon_id'    => 0,
                        'addon_name'  => Bootstrap::$lang->get_cm("website/account_products/whois-privacy", false, $ulang),
                        'option_id'   => 0,
                        "option_name" => Bootstrap::$lang->get("needs/iwwant", $ulang),
                        'period'      => 1,
                        'period_time' => "year",
                        'status'      => "active",
                        'cdate'       => $start,
                        'renewaldate' => $start,
                        'duedate'     => $end,
                        'amount'      => $amount,
                        'cid'         => $productPrice_cid,
                        'unread'      => 1,
                    ]);
                }
                $imports[] = $order_data["name"] . " (#" . $insert . ")";
            }

            if ($imports) {
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "domain-imported", [
                    'module'   => $config["meta"]["name"],
                    'imported' => implode(", ", $imports),
                ]);
            }

            return $imports;
        }

        public function cost_prices($type = 'domain')
        {
            if (!isset($this->config["settings"]["adp"]) || !$this->config["settings"]["adp"]) return false;

            $response = $this->api->users_getPricing([
                'ProductType' => "DOMAIN",
                'ActionName'  => "REGISTER",
            ]);

            if (!$response && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }

            $result = [];
            $pricing = [];

            if ($response["UserGetPricingResult"]["ProductType"]["ProductCategory"]["Product"])
                foreach ($response["UserGetPricingResult"]["ProductType"]["ProductCategory"]["Product"] as $k => $v)
                    $pricing[$v["@attributes"]["Name"]]["register"] = $v["Price"][0]["@attributes"];


            $response = $this->api->users_getPricing([
                'ProductType' => "DOMAIN",
                'ActionName'  => "RENEW",
            ]);

            if (!$response && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }

            if ($response["UserGetPricingResult"]["ProductType"]["ProductCategory"]["Product"])
                foreach ($response["UserGetPricingResult"]["ProductType"]["ProductCategory"]["Product"] as $k => $v)
                    $pricing[$v["@attributes"]["Name"]]["renew"] = $v["Price"][0]["@attributes"];


            $response = $this->api->users_getPricing([
                'ProductType' => "DOMAIN",
                'ActionName'  => "TRANSFER",
            ]);

            if (!$response && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }

            if ($response["UserGetPricingResult"]["ProductType"]["ProductCategory"]["Product"])
                foreach ($response["UserGetPricingResult"]["ProductType"]["ProductCategory"]["Product"] as $k => $v)
                    $pricing[$v["@attributes"]["Name"]]["transfer"] = $v["Price"]["@attributes"];

            Helper::Load(["Money"]);
            $cost_cid = $this->config["settings"]["cost-currency"];

            if ($pricing) {
                foreach ($pricing as $tld => $val) {


                    $register_price = $val["register"]["YourPrice"];
                    $additional_cost = isset($val["register"]["YourAdditonalCost"]) ? $val["register"]["YourAdditonalCost"] : 0;
                    $register_price = Money::exChange($register_price, $val["register"]["Currency"], $cost_cid);
                    $additional_cost = Money::exChange($additional_cost, $val["register"]["Currency"], $cost_cid);
                    if ($additional_cost) $register_price += $additional_cost;

                    $renew_price = $val["renew"]["YourPrice"];
                    $additional_cost = isset($val["renew"]["YourAdditonalCost"]) ? $val["renew"]["YourAdditonalCost"] : 0;
                    $renew_price = Money::exChange($renew_price, $val["renew"]["Currency"], $cost_cid);
                    $additional_cost = Money::exChange($additional_cost, $val["renew"]["Currency"], $cost_cid);
                    if ($additional_cost) $renew_price += $additional_cost;


                    $transfer_price = $val["transfer"]["YourPrice"];
                    $additional_cost = isset($val["transfer"]["YourAdditonalCost"]) ? $val["transfer"]["YourAdditonalCost"] : 0;
                    $transfer_price = Money::exChange($transfer_price, $val["transfer"]["Currency"], $cost_cid);
                    $additional_cost = Money::exChange($additional_cost, $val["transfer"]["Currency"], $cost_cid);
                    if ($additional_cost) $transfer_price += $additional_cost;

                    $result[$tld] = [
                        'register' => round($register_price, 2),
                        'transfer' => round($transfer_price, 2),
                        'renewal'  => round($renew_price, 2),
                    ];
                }
            }

            return $result;
        }

        public function apply_import_tlds()
        {
            $response = $this->api->users_getPricing([
                'ProductType' => "DOMAIN",
                'ActionName'  => "REGISTER",
            ]);

            if (!$response && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }

            $result = [];
            $pricing = [];

            if ($response["UserGetPricingResult"]["ProductType"]["ProductCategory"]["Product"])
                foreach ($response["UserGetPricingResult"]["ProductType"]["ProductCategory"]["Product"] as $k => $v)
                    $pricing[$v["@attributes"]["Name"]]["register"] = $v["Price"][0]["@attributes"];


            $response = $this->api->users_getPricing([
                'ProductType' => "DOMAIN",
                'ActionName'  => "RENEW",
            ]);

            if (!$response && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }

            if ($response["UserGetPricingResult"]["ProductType"]["ProductCategory"]["Product"])
                foreach ($response["UserGetPricingResult"]["ProductType"]["ProductCategory"]["Product"] as $k => $v)
                    $pricing[$v["@attributes"]["Name"]]["renew"] = $v["Price"][0]["@attributes"];


            $response = $this->api->users_getPricing([
                'ProductType' => "DOMAIN",
                'ActionName'  => "TRANSFER",
            ]);

            if (!$response && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }

            if ($response["UserGetPricingResult"]["ProductType"]["ProductCategory"]["Product"])
                foreach ($response["UserGetPricingResult"]["ProductType"]["ProductCategory"]["Product"] as $k => $v)
                    $pricing[$v["@attributes"]["Name"]]["transfer"] = $v["Price"]["@attributes"];


            Helper::Load(["Products", "Money"]);

            $cost_cid = isset($this->config["settings"]["cost-currency"]) ? $this->config["settings"]["cost-currency"] : 4;
            $profit_rate = Config::get("options/domain-profit-rate");

            if ($pricing) {
                foreach ($pricing as $tld => $val) {


                    $register_price = $val["register"]["YourPrice"];
                    $additional_cost = isset($val["register"]["YourAdditonalCost"]) ? $val["register"]["YourAdditonalCost"] : 0;
                    $register_price = Money::exChange($register_price, $val["register"]["Currency"], $cost_cid);
                    $additional_cost = Money::exChange($additional_cost, $val["register"]["Currency"], $cost_cid);
                    if ($additional_cost) $register_price += $additional_cost;

                    $renew_price = $val["renew"]["YourPrice"];
                    $additional_cost = isset($val["renew"]["YourAdditonalCost"]) ? $val["renew"]["YourAdditonalCost"] : 0;
                    $renew_price = Money::exChange($renew_price, $val["renew"]["Currency"], $cost_cid);
                    $additional_cost = Money::exChange($additional_cost, $val["renew"]["Currency"], $cost_cid);
                    if ($additional_cost) $renew_price += $additional_cost;

                    $transfer_price = $val["transfer"]["YourPrice"];
                    $additional_cost = isset($val["transfer"]["YourAdditonalCost"]) ? $val["transfer"]["YourAdditonalCost"] : 0;
                    $transfer_price = Money::exChange($transfer_price, $val["transfer"]["Currency"], $cost_cid);
                    $additional_cost = Money::exChange($additional_cost, $val["transfer"]["Currency"], $cost_cid);
                    if ($additional_cost) $transfer_price += $additional_cost;

                    $api_cost_prices = [
                        'register' => $register_price,
                        'transfer' => $transfer_price,
                        'renewal'  => $renew_price,
                    ];

                    $name = $tld;
                    $paperwork = 0;
                    $epp_code = 1;
                    $dns_manage = 1;
                    $whois_privacy = 1;
                    $module = "Namecheap";

                    $check = Models::$init->db->select()->from("tldlist")->where("name", "=", $name);

                    if ($check->build()) {
                        $tld = $check->getAssoc();
                        $pid = $tld["id"];

                        $reg_price = Products::get_price("register", "tld", $pid);
                        $ren_price = Products::get_price("renewal", "tld", $pid);
                        $tra_price = Products::get_price("transfer", "tld", $pid);

                        $tld_cid = $reg_price["cid"];


                        $register_cost = Money::deformatter($api_cost_prices["register"]);
                        $renewal_cost = Money::deformatter($api_cost_prices["renewal"]);
                        $transfer_cost = Money::deformatter($api_cost_prices["transfer"]);

                        // ExChanges
                        $register_cost = Money::exChange($register_cost, $cost_cid, $tld_cid);
                        $renewal_cost = Money::exChange($renewal_cost, $cost_cid, $tld_cid);
                        $transfer_cost = Money::exChange($transfer_cost, $cost_cid, $tld_cid);


                        $reg_profit = Money::get_discount_amount($register_cost, $profit_rate);
                        $ren_profit = Money::get_discount_amount($renewal_cost, $profit_rate);
                        $tra_profit = Money::get_discount_amount($transfer_cost, $profit_rate);

                        $register_sale = $register_cost + $reg_profit;
                        $renewal_sale = $renewal_cost + $ren_profit;
                        $transfer_sale = $transfer_cost + $tra_profit;

                        Products::set("domain", $pid, [
                            'paperwork'     => $paperwork,
                            'epp_code'      => $epp_code,
                            'dns_manage'    => $dns_manage,
                            'whois_privacy' => $whois_privacy,
                            'register_cost' => $register_cost,
                            'renewal_cost'  => $renewal_cost,
                            'transfer_cost' => $transfer_cost,
                            'module'        => $module,
                        ]);

                        Models::$init->db->update("prices", [
                            'amount' => $register_sale,
                            'cid'    => $tld_cid,
                        ])->where("id", "=", $reg_price["id"])->save();


                        Models::$init->db->update("prices", [
                            'amount' => $renewal_sale,
                            'cid'    => $tld_cid,
                        ])->where("id", "=", $ren_price["id"])->save();


                        Models::$init->db->update("prices", [
                            'amount' => $transfer_sale,
                            'cid'    => $tld_cid,
                        ])->where("id", "=", $tra_price["id"])->save();

                    } else {

                        $tld_cid = $cost_cid;


                        $register_cost = Money::deformatter($api_cost_prices["register"]);
                        $renewal_cost = Money::deformatter($api_cost_prices["renewal"]);
                        $transfer_cost = Money::deformatter($api_cost_prices["transfer"]);


                        $reg_profit = Money::get_discount_amount($register_cost, $profit_rate);
                        $ren_profit = Money::get_discount_amount($renewal_cost, $profit_rate);
                        $tra_profit = Money::get_discount_amount($transfer_cost, $profit_rate);

                        $register_sale = $register_cost + $reg_profit;
                        $renewal_sale = $renewal_cost + $ren_profit;
                        $transfer_sale = $transfer_cost + $tra_profit;

                        $insert = Models::$init->db->insert("tldlist", [
                            'status'        => "inactive",
                            'cdate'         => DateManager::Now(),
                            'name'          => $name,
                            'paperwork'     => $paperwork,
                            'epp_code'      => $epp_code,
                            'dns_manage'    => $dns_manage,
                            'whois_privacy' => $whois_privacy,
                            'currency'      => $tld_cid,
                            'register_cost' => $register_cost,
                            'renewal_cost'  => $renewal_cost,
                            'transfer_cost' => $transfer_cost,
                            'module'        => $module,
                        ]);

                        if ($insert) {
                            $tld_id = Models::$init->db->lastID();

                            Models::$init->db->insert("prices", [
                                'owner'    => "tld",
                                'owner_id' => $tld_id,
                                'type'     => 'register',
                                'amount'   => $register_sale,
                                'cid'      => $tld_cid,
                            ]);


                            Models::$init->db->insert("prices", [
                                'owner'    => "tld",
                                'owner_id' => $tld_id,
                                'type'     => 'renewal',
                                'amount'   => $renewal_sale,
                                'cid'      => $tld_cid,
                            ]);


                            Models::$init->db->insert("prices", [
                                'owner'    => "tld",
                                'owner_id' => $tld_id,
                                'type'     => 'transfer',
                                'amount'   => $transfer_sale,
                                'cid'      => $tld_cid,
                            ]);
                        }

                    }

                }
            }

            return true;
        }


        public function getDnsRecords()
        {
            $result = [];

            $response = $this->api->domains_dns_getHosts($this->order["options"]["name"], $this->order["options"]["tld"]);
            if (!$response && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }


            if ($response) {
                $hosts = $response["DomainDNSGetHostsResult"]["host"];
                if (isset($hosts["@attributes"])) $hosts = [$hosts];

                foreach ($hosts as $r) {
                    $attr = $r["@attributes"] ?? [];


                    $result[] = [
                        'identity' => $attr["HostId"] ?? 0,
                        'type'     => $attr["Type"] ?? '',
                        'name'     => $attr["Name"] ?? '',
                        'value'    => $attr["Address"] ?? '',
                        'ttl'      => $attr["TTL"] ?? '',
                        'priority' => $attr["MXPref"],
                    ];
                }
            }


            return $result;

        }


        public function addDnsRecord($type, $name, $value, $ttl, $priority)
        {
            if (!$priority) $priority = 10;
            if (!$ttl) $ttl = 7207;

            $r_i = 0;

            $record_data = [];

            $records = $this->getDnsRecords();

            if ($records) {
                foreach ($records as $r) {
                    $r_i++;

                    $record_data[] = [
                        'Key'   => 'RecordType' . $r_i,
                        'Value' => $r["type"],
                    ];

                    $record_data[] = [
                        'Key'   => 'HostName' . $r_i,
                        'Value' => $r["name"],
                    ];

                    $record_data[] = [
                        'Key'   => 'Address' . $r_i,
                        'Value' => $r["value"],
                    ];

                    $record_data[] = [
                        'Key'   => 'TTL' . $r_i,
                        'Value' => $r["ttl"],
                    ];

                    if ($r["type"] == "MX")
                        $record_data[] = [
                            'Key'   => 'MXPref' . $r_i,
                            'Value' => $r["priority"],
                        ];
                }
            }

            $r_i++;

            $record_data[] = [
                'Key'   => 'RecordType' . $r_i,
                'Value' => $type,
            ];

            $record_data[] = [
                'Key'   => 'HostName' . $r_i,
                'Value' => $name,
            ];

            $record_data[] = [
                'Key'   => 'Address' . $r_i,
                'Value' => $value,
            ];

            $record_data[] = [
                'Key'   => 'TTL' . $r_i,
                'Value' => $ttl,
            ];

            if ($type == "MX")
                $record_data[] = [
                    'Key'   => 'MXPref' . $r_i,
                    'Value' => $priority,
                ];


            $setData = [
                'SLD'           => $this->order["options"]["name"],
                'TLD'           => $this->order["options"]["tld"],
                'RequestValues' => $record_data,
            ];


            $response = $this->api->domains_dns_setHosts($setData);

            if (!$response && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }


        public function updateDnsRecord($type = '', $name = '', $value = '', $identity = '', $ttl = '', $priority = '')
        {
            $list = $this->getDnsRecords();
            if (!$list) return false;
            $verified = false;
            foreach ($list as $l) if ($l["identity"] == $identity) $verified = true;
            if (!$verified) {
                $this->error = "Invalid identity ID";
                return false;
            }

            $r_i = 0;

            $record_data = [];

            $records = $list;

            if ($records) {
                foreach ($records as $r) {
                    $r_i++;

                    if ($r["identity"] == $identity) {
                        $record_data[] = [
                            'Key'   => 'RecordType' . $r_i,
                            'Value' => $type,
                        ];

                        $record_data[] = [
                            'Key'   => 'HostName' . $r_i,
                            'Value' => $name,
                        ];

                        $record_data[] = [
                            'Key'   => 'Address' . $r_i,
                            'Value' => $value,
                        ];

                        $record_data[] = [
                            'Key'   => 'TTL' . $r_i,
                            'Value' => $ttl,
                        ];

                        if ($type == "MX")
                            $record_data[] = [
                                'Key'   => 'MXPref' . $r_i,
                                'Value' => $priority,
                            ];
                    } else {
                        $record_data[] = [
                            'Key'   => 'RecordType' . $r_i,
                            'Value' => $r["type"],
                        ];

                        $record_data[] = [
                            'Key'   => 'HostName' . $r_i,
                            'Value' => $r["name"],
                        ];

                        $record_data[] = [
                            'Key'   => 'Address' . $r_i,
                            'Value' => $r["value"],
                        ];

                        $record_data[] = [
                            'Key'   => 'TTL' . $r_i,
                            'Value' => $r["ttl"],
                        ];

                        if ($r["type"] == "MX")
                            $record_data[] = [
                                'Key'   => 'MXPref' . $r_i,
                                'Value' => $r["priority"],
                            ];
                    }

                }
            }


            $setData = [
                'SLD'           => $this->order["options"]["name"],
                'TLD'           => $this->order["options"]["tld"],
                'RequestValues' => $record_data,
            ];


            $response = $this->api->domains_dns_setHosts($setData);

            if (!$response && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }


            return true;
        }


        public function deleteDnsRecord($type = '', $name = '', $value = '', $identity = '')
        {
            $list = $this->getDnsRecords();
            if (!$list) return false;
            $verified = false;

            foreach ($list as $l) if ($l["identity"] == $identity) $verified = true;

            if (!$verified) {
                $this->error = "Invalid identity ID";
                return false;
            }

            $r_i = 0;

            $record_data = [];

            $records = $list;

            if ($records) {
                foreach ($records as $r) {
                    if ($r["identity"] == $identity) continue;

                    $r_i++;

                    $record_data[] = [
                        'Key'   => 'RecordType' . $r_i,
                        'Value' => $r["type"],
                    ];

                    $record_data[] = [
                        'Key'   => 'HostName' . $r_i,
                        'Value' => $r["name"],
                    ];

                    $record_data[] = [
                        'Key'   => 'Address' . $r_i,
                        'Value' => $r["value"],
                    ];

                    $record_data[] = [
                        'Key'   => 'TTL' . $r_i,
                        'Value' => $r["ttl"],
                    ];

                    if ($r["type"] == "MX")
                        $record_data[] = [
                            'Key'   => 'MXPref' . $r_i,
                            'Value' => $r["priority"],
                        ];

                }
            }

            $setData = [
                'SLD'           => $this->order["options"]["name"],
                'TLD'           => $this->order["options"]["tld"],
                'RequestValues' => $record_data,
            ];


            $response = $this->api->domains_dns_setHosts($setData);

            if (!$response && $this->api->error) {
                $this->error = $this->api->error;
                return false;
            }


            return true;
        }


    }