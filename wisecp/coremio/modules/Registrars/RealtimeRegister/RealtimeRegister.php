<?php
    class RealtimeRegister {
        public $api                 = false;
        public $config              = [];
        public $lang                = [];
        public $error               = NULL;
        public $whidden             = [];
        public $order               = [];
        public $allow_prio          = ['MX','SRV'];
        public $docs                = [];

        function __construct($args=[]){

            $this->config   = Modules::Config("Registrars",__CLASS__);
            $this->lang     = Modules::Lang("Registrars",__CLASS__);

            if(!class_exists("RealtimeRegister_API")){
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
            $password   = $this->config["settings"]["password"] ?? '';
            $password   = Crypt::decode($password,Config::get("crypt/system"));
            $test_mode  = $this->config["settings"]["test-mode"] ?? false;

            $this->api  =  new RealtimeRegister_API();
            $this->api->set_credentials($api_key,$password,$test_mode);
        }

        public function set_order($order=[]){
            $this->order = $order;
            return $this;
        }

        public function define_docs($docs=[])
        {
            $this->docs = $docs;
        }

        private function setConfig($api_key,$password,$sandbox){
            $this->config["settings"]["api-key"]    = $api_key;
            $this->config["settings"]["password"]   = $password;
            $this->config["settings"]["test-mode"]  = $sandbox;
            $this->api = new RealtimeRegister_API();
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
            $password   = $config["settings"]["password"] ?? '';
            $sandbox    = $config["settings"]["test-mode"];

            if(!$api_key){
                $this->error = $this->lang["error6"];
                return false;
            }

            $password  = Crypt::decode($password,Config::get("crypt/system"));

            $this->setConfig($api_key,$password,$sandbox);

            $list = $this->api->call("domains");

            if(!$list){
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
                $check      = $this->api->call("domains/".$sld.".".$tld.'/check');

                if($check)
                {
                    $result[$tld] = [
                        'status' => ($check["available"] ?? false) ? 'available' : 'unavailable',
                        'premium' => $check["premium"] ?? false,
                        'premium_price' => ($check["premium"] ?? false) ? ['amount' => (float) $check["price"],'currency' => $check["currency"]] : [],
                    ];
                }
            }
            return $result;
        }

        public function whois_process($data=[])
        {
            $data = [
                'name'              => $data["FirstName"].(strlen($data["LastName"]) > 0 ? ' '.$data["LastName"] : ''),
                'email'             => $data["EMail"],
                'voice'             => "+".$data['PhoneCountryCode'].".".$data['Phone'],
                'addressLine'       => [$data['AddressLine1'].(strlen($data['AddressLine2']) > 1 ? $data['AddressLine2'] : '')],
                'postalCode'        => $data['ZipCode'],
                'city'              => $data['City'],
                'state'             => $data['State'],
                'country'           => $data['Country'],
            ];
            if(strlen($data["Company"]) > 1) $data['organization'] = $data["Company"];

            if(strlen($data['Fax'])) $data['fax'] = "+".$data['FaxCountryCode'].".".$data['Fax'];

            return $data;
        }

        public function register($domain='',$sld='',$tld='',$year=1,$dns=[],$whois=[],$wprivacy=false,$epp_code=''){
            $config = include __DIR__.DS."config.php";
            $domain   = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $sld      = idn_to_ascii($sld,0,INTL_IDNA_VARIANT_UTS46);
            $customer = $config["settings"]["customer"] ?? 'x';


            $contacts                   = [];

            $c_types                  = [
                'registrant'        => "REGISTRANT",
                'administrative'    => "ADMIN",
                'technical'         => "TECH",
                'billing'           => "BILLING",
            ];

            $tld_x              = explode(".",$tld);
            $tld_x              = end($tld_x);

            $tld_info           = $this->api->call('tlds/'.str_replace(".","",$tld_x).'/info');


            if($this->api->status_code != 200)
            {
                $this->error = $this->api->error;
                return false;
            }

            $provider       = $tld_info["provider"] ?? "None";


            foreach($c_types AS $ct => $ct_v)
            {
                $contact_rand           = time().rand(1000,9999);
                $contact_id              = substr($ct,0,2).$contact_rand;
                $this->api->call('customers/'.$customer."/contacts/".$contact_id,$this->whois_process($whois[$ct]),'POST');

                if($this->api->status_code != 201)
                {
                    $this->error = $this->api->error;
                    return false;
                }

                if($this->docs && isset($config["settings"]["doc-fields"][$tld]))
                {
                    $this->api->call('customers/'.$customer."/contacts/".$contact_id."/".$provider,[
                        'properties'        => $this->docs,
                        'intendedUsage'     => $ct_v,
                    ],'POST');
                    if($this->api->status_code != 200)
                    {
                        $this->error = $this->api->error;
                        return false;
                    }
                }
                $contacts[$ct] = $contact_id;
            }


            $params     = [
                'customer'          => $customer,
                'period'            => $year * 12,
                'ns'                => array_values($dns),
                'privacyProtect'    => $wprivacy == true,
                'autoRenew'         => false,
                'registrant'        => $contacts["registrant"],
                'contacts'          => [
                    [
                        'role' => "ADMIN",
                        'handle' => $contacts["administrative"],
                    ],
                    [
                        'role' => "TECH",
                        'handle' => $contacts["technical"],
                    ],
                    [
                        'role' => "BILLING",
                        'handle' => $contacts["billing"],
                    ]
                ],
            ];

            if($epp_code) unset($params['period']);

            $dns = array_values($dns);
            if(in_array('ns1.yoursrs.com',$dns))
            {
                $t_name                  = $domain;#"tp".time();
                $t_records              = [];

                $t_data                  = [
                    'hostMaster'            => "admin@".$domain,
                    'refresh'               => 3600,
                    'retry'                 => 3600,
                    'expire'                => 86400,
                    'ttl'                   => 3600,
                    'records'               => [
                        [
                            'name'      => '##DOMAIN##',
                            'type'      => "A",
                            'content'   => "127.0.0.1",
                            'ttl'       => 3600,

                        ],
                        [
                            'name'      => '##DOMAIN##',
                            'type'      => "MX",
                            'content'   => "mail.".$domain,
                            'ttl'       => 3600,
                            'prio'      => 10,
                        ],
                        [
                            'name'      => 'ftp.##DOMAIN##',
                            'type'      => "CNAME",
                            'content'   => "files.".$domain,
                            'ttl'       => 3600,
                        ],
                        [
                            'name'      => 'spf.##DOMAIN##',
                            'type'      => "TXT",
                            'content'   => "v=spf1 ip4=127.0.0.1 include:examplesender.email -all",
                            'ttl'       => 3600,
                        ],
                    ],
                ];

                $this->api->call("customers/".$customer."/dnstemplates/".$t_name,$t_data,'POST');
                if($this->api->status_code != 201 && !stristr($this->api->error,'already exists'))
                {
                    $this->error = $this->api->error;
                    return false;
                }
                $params['zone'] = ['template' => $t_name];
                unset($params['ns']);
            }

            if($epp_code && $epp_code != "N/A") $params['authcode'] = $epp_code;


            $process       = $this->api->call("domains/".$domain.($epp_code ? '/transfer' : ''),$params,'POST');

            if(!$process)
            {
                $this->error = $this->api->error;
                return false;
            }


            $returnData = [
                'status' => "SUCCESS",
                'config' => [
                    'entityID' => 1,
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

            $process        = $this->api->call("domains/".$domain."/renew",[
                'period' => $year * 12,
            ],"POST");

            if(!$process)
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function ModifyWhois($params=[],$whois=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $details        = $this->api->call("domains/".$domain);

            if(!$details)
            {
                $this->error = $this->api->error;
                return false;
            }

            $contacts       = [
                'registrant' => $details["registrant"],
            ];

            if(isset($details["contacts"]) && $details["contacts"])
            {
                foreach($details["contacts"] AS $r)
                {
                    if($r["role"] == "ADMIN") $contacts["administrative"] = $r["handle"];
                    elseif($r["role"] == "BILLING") $contacts["billing"] = $r["handle"];
                    elseif($r["role"] == "TECH") $contacts["technical"] = $r["handle"];
                }
            }

            $customer       = $this->config["settings"]["customer"] ?? 'x';

            if($contacts)
            {
                foreach($contacts AS $ct => $ct_id)
                {
                    $wd         = $this->whois_process($whois[$ct]);
                    $this->api->call("customers/".$customer."/contacts/".$ct_id."/update",$wd,'POST');
                    if(!in_array($this->api->status_code,[200,202]))
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

            if($dns) foreach($dns AS $i=>$dn) $dns[$i] = idn_to_ascii($dn,0,INTL_IDNA_VARIANT_UTS46);

            $customer       = $this->config["settings"]["customer"] ?? 'x';

            $detail         = $this->api->call("domains/".$domain);


            $changes        = [];


            if(in_array('ns1.yoursrs.com',$dns))
            {
                $z_t            = $detail["zone"]["template"] ?? '';
                if($z_t)
                {
                    $request        = $this->api->call("customers/".$customer."/dnstemplates/".$z_t);

                    $records        = $request["records"] ?? [];

                    if($records)
                    {
                        foreach ($records AS $k => $r)
                        {
                            if($r["type"] == "NS")
                            {
                                unset($records[$k]);
                                continue;
                            }

                            $name           = str_replace(["@",$domain],".##DOMAIN##",$r["name"]);
                            if(!stristr($name,'##DOMAIN##')) $name .= ".##DOMAIN##";
                            if(stristr($name,'..')) $name = str_replace("..",".",$name);
                            if($name == ".##DOMAIN##") $name = '##DOMAIN##';
                            $records[$k]['name'] = $name;
                        }
                    }

                    foreach($dns AS $k => $v)
                    {

                        $r_data         = [
                            'name'      => "ns".($k+1).".##DOMAIN##",
                            'type'      => "NS",
                            'content'   => $v,
                            'ttl'       => 7207,
                        ];
                        $records[] = $r_data;
                    }

                    $this->api->call("customers/".$customer."/dnstemplates/".$z_t."/update",[
                        'records'       => $records,
                    ],'POST');

                }
                else
                {
                    $t_name                  = $domain;
                    $t_data                  = [
                        'hostMaster'            => "admin@".$domain,
                        'refresh'               => 3600,
                        'retry'                 => 3600,
                        'expire'                => 86400,
                        'ttl'                   => 3600,
                        'records'               => [
                            [
                                'name'      => '##DOMAIN##',
                                'type'      => "A",
                                'content'   => "127.0.0.1",
                                'ttl'       => 3600,

                            ],
                            [
                                'name'      => '##DOMAIN##',
                                'type'      => "MX",
                                'content'   => "mail.".$domain,
                                'ttl'       => 3600,
                                'prio'      => 10,
                            ],
                            [
                                'name'      => 'ftp.##DOMAIN##',
                                'type'      => "CNAME",
                                'content'   => "files.".$domain,
                                'ttl'       => 3600,
                            ],
                            [
                                'name'      => 'spf.##DOMAIN##',
                                'type'      => "TXT",
                                'content'   => "v=spf1 ip4=127.0.0.1 include:examplesender.email -all",
                                'ttl'       => 3600,
                            ],
                        ],
                    ];

                    $this->api->call("customers/".$customer."/dnstemplates/".$t_name,$t_data,'POST');
                    if($this->api->status_code != 201 && !stristr($this->api->error,'already exists'))
                    {
                        $this->error = $this->api->error;
                        return false;
                    }
                    $changes['zone'] = ['template' => $t_name];
                }
            }
            else
                $changes["ns"] = array_values($dns);


            if($changes) $this->api->call("domains/".$domain."/update",$changes,'POST');


            if(!in_array($this->api->status_code,[200,202]))
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function getTransferLock($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $get        = $this->api->call("domains/".$domain);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            return in_array('CLIENT_TRANSFER_PROHIBITED',$get["status"]) ? "active" : "passive";
        }

        public function ModifyTransferLock($params=[],$newStatus=''){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $get        = $this->api->call("domains/".$domain);

            if(!$get)
            {
                $this->error = $this->api->error;
                if(str_contains($this->error,"404")) {
                    $this->error = '';
                    return true;
                }
                return false;
            }

            //CLIENT_TRANSFER_PROHIBITED

            $status     = $get['status'];

            if($newStatus == "enable")
            {
                if(!in_array('CLIENT_TRANSFER_PROHIBITED',$status))
                    $status[] = 'CLIENT_TRANSFER_PROHIBITED';
            }
            else
            {
                $index = array_search('CLIENT_TRANSFER_PROHIBITED',$status);
                if($index !== false) unset($status[$index]);
            }


            $process    = $this->api->call("domains/".$domain."/update",[
                'status' => array_values($status),
            ],'POST');

            if(!$process)
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function getWhoisPrivacy($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $get        = $this->api->call("domains/".$domain);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            return $get["privacyProtect"] ? "active" : "passive";
        }
        public function modifyPrivacyProtection($params=[],$status=''){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $this->api->call("domains/".$domain."/update",[
                'privacyProtect' => $status == "enable"
            ],"POST");

            if(!in_array($this->api->status_code,[200,202]))
            {
                $this->error = $this->api->error;
                return false;
            }


            return true;
        }

        public function CNSList($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);
            $result     = [];

            $get        = $this->api->call("hosts",[
                'queries'   => [
                    'limit'     => 250,
                    'domain:like' => $domain,
                ],
            ]);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            if(isset($get["pagination"]["total"]) && $get["pagination"]["total"] > 0)
            {
                foreach($get["entities"] AS $r)
                {
                    $name       = $r["hostName"] ?? 'none';
                    $ips        = $r["addresses"] ?? false;
                    $ip         = false;

                    if($ips)
                    {
                        foreach($ips AS $i) if(!$ip && $i["ipVersion"] == "V4") $ip = $i["address"];
                    }

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

            $process        = $this->api->call("hosts/".$ns,[
                'addresses' => [
                    [
                        'ipVersion' => "V4",
                        'address' => $ip,
                    ]
                ],
            ],'POST');

            if($this->api->status_code != 201)
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

            $this->api->call("hosts/".$ns,false,'DELETE');

            if($this->api->status_code !== 200)
            {
                $this->error = $this->api->error;
                return false;
            }

            return true;

        }


        public function getAuthCode($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $get        = $this->api->call("domains/".$domain);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            return $get["authcode"];
        }


        public function isInactive($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $get        = $this->api->call("domains/".$domain);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            return in_array($get["status"],['EXPIRED','UNKNOWN']);
        }

        public function sync($params=[]){
            $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

            $get        = $this->api->call("domains/".$domain);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            $start              = DateManager::format("Y-m-d H:i:s",$get["createdDate"]);
            $end                = DateManager::format("Y-m-d H:i:s",$get["expiryDate"]);
            $status             = $get["status"];

            if(in_array($status,['PENDING_TRANSFER','EXPIRED','UNKNOWN']))
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

            $get        = $this->api->call("domains/".$domain);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }


            $result             = [];

            $cdate              = DateManager::format("Y-m-d H:i:s",$get["createdDate"]);
            $duedate            = DateManager::format("Y-m-d H:i:s",$get["expiryDate"]);

            $wprivacy           = $get["privacyProtect"];

            $ns1                = $get["ns"][0] ?? false;
            $ns2                = $get["ns"][1] ?? false;
            $ns3                = $get["ns"][2] ?? false;
            $ns4                = $get["ns"][3] ?? false;
            $contacts           = $get["contacts"] ?? [];

            if($contacts){

                $id_contacts       = ['registrant' => $get["registrant"]];

                foreach($contacts AS $r)
                {
                    if($r["role"] == "ADMIN") $id_contacts["administrative"] = $r["handle"];
                    elseif($r["role"] == "BILLING") $id_contacts["billing"] = $r["handle"];
                    elseif($r["role"] == "TECH") $id_contacts["technical"] = $r["handle"];
                }


                $whois      = [];

                $customer       = $this->config["settings"]["customer"] ?? 'x';

                foreach($id_contacts AS $ct => $c_id)
                {
                    $w_data            = $this->api->call("customers/".$customer."/contacts/".$c_id);

                    if($w_data)
                    {
                        $phone_smash            = explode(".",$w_data["voice"] ?? '');
                        $fax_smash              = explode(".",$w_data["fax"] ?? '');
                        $name_smash             = Filter::name_smash($w_data["name"]);

                        $whois[$ct] = [
                            'FirstName'         => $name_smash["first"],
                            'LastName'          => $name_smash["last"],
                            'Name'              => $w_data["name"],
                            'Company'           => $w_data["organization"],
                            'EMail'             => $w_data["email"],
                            'AddressLine1'      => is_array($w_data["addressLine"]) ? $w_data["addressLine"][0] : $w_data["addressLine"],
                            'City'              => $w_data["city"],
                            'State'             => $w_data["state"],
                            'ZipCode'           => $w_data["postalCode"],
                            'Country'           => $w_data["country	"],
                            'PhoneCountryCode'  => substr(($phone_smash[0] ?? ''),1),
                            'Phone'             => $phone_smash[1] ?? '',
                            'FaxCountryCode'    => substr(($fax_smash[0] ?? ''),1),
                            'Fax'               => $fax_smash[1] ?? '',
                        ];
                    }
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

            $result["transferlock"] = in_array('CLIENT_TRANSFER_PROHIBITED',$get["status"]);

            return $result;

        }

        public function domains(){
            Helper::Load(["User"]);

            $get        = $this->api->call("domains",[
                'queries'   => [
                    'limit'     => 250,
                ],
            ]);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            $result     = [];

            if(isset($get["entities"]) && sizeof($get["entities"]) > 0){
                foreach($get["entities"] AS $res){
                    $cdate      = DateManager::format('Y-m-d H:i:s',$res["createdDate"] ?? '');
                    $edate      = DateManager::format('Y-m-d H:i:s',$res['expiryDate'] ?? '');
                    $domain     = $res['domainName'];

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


        public function getDnsRecords()
        {

            $domain         = $this->order["options"]["domain"];
            $domain         = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $customer       = $this->config["settings"]["customer"] ?? 'x';

            $detail         = $this->api->call("domains/".$domain);
            $z_t            = $detail["zone"]["template"] ?? '';



            $result         = [];
            $request        = $z_t ? $this->api->call("customers/".$customer."/dnstemplates/".$z_t) : [];

            if($request && isset($request["records"]) && sizeof($request["records"]) > 0)
            {
                foreach($request["records"] AS $k => $r)
                {

                    $name           = str_replace("##DOMAIN##",$domain,$r["name"]);

                    $r_data         = [
                        'identity'      => $k,
                        'type'          => $r["type"],
                        'name'          => $name,
                        'value'         => $r["content"],
                        'ttl'           => $r["ttl"],
                        'priority'      => $r["prio"],
                    ];

                    $result[] = $r_data;
                }
            }


            return $result;

        }
        public function addDnsRecord($type,$name,$value,$ttl,$priority)
        {
            if(!$priority) $priority = 10;
            if(!$ttl) $ttl = 7207;

            if($ttl < 3600) $ttl = 7207;

            $domain         = $this->order["options"]["domain"];
            $domain         = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);

            $customer       = $this->config["settings"]["customer"] ?? 'x';

            $detail         = $this->api->call("domains/".$domain);
            $z_t            = $detail["zone"]["template"] ?? '';


            if(!$z_t)
            {
                $this->error =  $this->lang["ns-error"] ?? 'For this process, please set your nameserver information to ns1.yoursrs.com and ns2.yoursrs.com';
                return false;
            }


            $request        = $this->api->call("customers/".$customer."/dnstemplates/".$z_t);

            $records        = $request["records"] ?? [];

            if($records)
            {
                foreach ($records AS $k => $r)
                {
                    $r_name           = str_replace(["@",$domain],".##DOMAIN##",$r["name"]);
                    if(!stristr($r_name,'##DOMAIN##')) $r_name .= ".##DOMAIN##";
                    if(stristr($r_name,'..')) $r_name = str_replace("..",".",$r_name);
                    if($r_name == ".##DOMAIN##") $r_name = '##DOMAIN##';
                    $records[$k]['name'] = $r_name;
                }
            }

            $name           = str_replace(["@",$domain],".##DOMAIN##",$name);


            if(!stristr($name,'##DOMAIN##')) $name .= ".##DOMAIN##";
            if(stristr($name,'..')) $name = str_replace("..",".",$name);
            if($name == ".##DOMAIN##") $name = '##DOMAIN##';

            $r_data         = [
                'name'      => $name,
                'type'      => $type,
                'content'   => $value,
                'ttl'       => $ttl,
                'prio'      => $priority,
            ];

            if(!in_array($type,$this->allow_prio)) unset($r_data["prio"]);

            $records[] = $r_data;

            $apply              = $this->api->call("customers/".$customer."/dnstemplates/".$z_t."/update",[
                'records'       => $records,
            ],'POST');


            if($this->api->status_code != 200){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }
        public function updateDnsRecord($type='',$name='',$value='',$identity='',$ttl='',$priority='')
        {

            $domain         = $this->order["options"]["domain"];
            $domain         = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);

            $customer       = $this->config["settings"]["customer"] ?? 'x';

            $detail         = $this->api->call("domains/".$domain);
            $z_t            = $detail["zone"]["template"] ?? '';


            if(!$z_t)
            {
                $this->error =  'Dns template not found in domain';
                return false;
            }


            $new_records    = [];


            $records        = $this->getDnsRecords();
            if($records)
            {
                foreach($records AS $k => $r)
                {
                    if($k == (int) $identity)
                    {
                        $r['name']      = str_replace("@","##DOMAIN##",$name);
                        $r['value']     = $value;
                        $r['ttl']       = $ttl;
                        $r['priority']  = $priority;
                    }

                    $name           = str_replace(["@",$domain],".##DOMAIN##",$r["name"]);
                    if(!stristr($name,'##DOMAIN##')) $name .= ".##DOMAIN##";
                    if(stristr($name,'..')) $name = str_replace("..",".",$name);
                    if($name == ".##DOMAIN##") $name = '##DOMAIN##';

                    $r_data = [
                        'name'      => $name,
                        'type'      => $r["type"],
                        'content'   => $r["value"],
                        'ttl'       => $r["ttl"],
                        'prio'      => $r["priority"],
                    ];

                    if(!in_array($r["type"],$this->allow_prio)) unset($r_data["prio"]);

                    $new_records[] = $r_data;
                }
            }




            $apply              = $this->api->call("customers/".$customer."/dnstemplates/".$z_t."/update",[
                'records'       => $new_records,
            ],'POST');


            if($this->api->status_code != 200){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }
        public function deleteDnsRecord($type='',$name='',$value='',$identity='')
        {

            $domain         = $this->order["options"]["domain"];
            $domain         = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);

            $customer       = $this->config["settings"]["customer"] ?? 'x';

            $detail         = $this->api->call("domains/".$domain);
            $z_t            = $detail["zone"]["template"] ?? '';


            if(!$z_t)
            {
                $this->error =  'Dns template not found in domain';
                return false;
            }


            $new_records    = [];


            $records        = $this->getDnsRecords();
            if($records)
            {
                foreach($records AS $k => $r)
                {
                    if($k == (int) $identity) continue;

                    $name           = str_replace(["@",$domain],".##DOMAIN##",$r["name"]);

                    if(!stristr($name,'##DOMAIN##')) $name .= ".##DOMAIN##";
                    if(stristr($name,'..')) $name = str_replace("..",".",$name);
                    if($name == ".##DOMAIN##") $name = '##DOMAIN##';


                    $r_data = [
                        'name'      => $name,
                        'type'      => $r["type"],
                        'content'   => $r["value"],
                        'ttl'       => $r["ttl"],
                        'prio'      => $r["priority"],
                    ];

                    if(!in_array($r["type"],$this->allow_prio)) unset($r_data["prio"]);

                    $new_records[] = $r_data;
                }
            }




            $apply              = $this->api->call("customers/".$customer."/dnstemplates/".$z_t."/update",[
                'records'       => $new_records,
            ],'POST');


            if($this->api->status_code != 200){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function cost_prices($type='domain'){

            $get        = $this->api->call("customers/".$this->config["settings"]["customer"]."/pricelist",[

            ]);

            if(!$get)
            {
                $this->error = $this->api->error;
                return false;
            }

            $result = [];

            if($type == "domain"){
                $prices         = $get["prices"] ?? [];
                $changes        = $get["priceChanges"] ?? [];
                $promos         = $get["promos"] ?? [];

                if($prices) $prices = $this->price_list_cleaning($prices);
                if($changes) $changes = $this->price_list_cleaning($changes);
                if($promos) $promos = $this->price_list_cleaning($promos);

                $result = array_replace_recursive($prices,$changes,$promos);
            }


            return $result;
        }
        public function apply_import_tlds(){

            $prices    = $this->cost_prices();
            if(!$prices){
                $this->error = $this->api->error;
                return false;
            }

            Helper::Load(["Products","Money"]);

            $cost_cid           = isset($this->config["settings"]["cost-currency"]) ? $this->config["settings"]["cost-currency"] : 4;
            $profit_rate        = Config::get("options/domain-profit-rate");

            foreach($prices AS $dot=>$api_cost_prices){
                $name           = Utility::strtolower(trim($dot));

                $paperwork      = 0;
                $epp_code       = 1;
                $dns_manage     = 1;
                $whois_privacy  = 1;
                $module         = "RealtimeRegister";

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
                    $transfer_cost  = Money::deformatter($api_cost_prices["transfer"] ?? 0);


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

        private function price_list_cleaning($rows = [])
        {
            Helper::Load("Money");
            $result = [];
            if($rows)
            {
                foreach($rows AS $row)
                {
                    $parse_product      = explode("_",$row["product"]);
                    $p_type             = $parse_product[0] ?? 'domain';
                    $p_name             = $parse_product[1] ?? '';
                    $p_a                = $parse_product[2] ?? '';
                    $action             = $row["action"] ?? 'CREATE';
                    $price              = (($row["price"] ?? 0) / 100);
                    $fromDate           = $row["fromDate"] ?? '';
                    $endDate            = $row["endDate"] ?? '';
                    $isActive           = $row["active"] ?? true;
                    $currency           = $row["currency"] ?? 'USD';
                    $action_re          = "register";
                    $price              = Money::exChange($price,$currency,$this->config["settings"]["cost-currency"] ?? 4);
                    $price              = round($price,2);

                    if($action == "RENEW") $action_re = "renewal";
                    if($action == "TRANSFER") $action_re = "transfer";

                    if($endDate) $endDate           = str_replace("T00:00:00Z","",$endDate);
                    if($fromDate) $fromDate        = str_replace("T00:00:00Z","",$fromDate);

                    if($fromDate && DateManager::strtotime($fromDate) > DateManager::strtotime()) continue;
                    if($endDate && DateManager::strtotime($endDate) < DateManager::strtotime()) continue;
                    if(!$isActive) continue;


                    if($p_type == "domain" && $p_name && !$p_a)
                    {
                        if($action == "CREATE" || $action == "RENEW" || $action == "TRANSFER")
                        {
                            if(!isset($result[$p_name])) $result[$p_name] = [];
                            $result[$p_name][$action_re] = $price;
                        }
                    }
                }
            }
            return $result;
        }

    }