<?php
    defined('CORE_FOLDER') OR exit('You can not get in here!');

    class Registrar {
        private static $whois_servers=[];
        private static $modules=[];


        static function whois_server($tld=''){
            if(empty(self::$whois_servers)) self::$whois_servers = include STORAGE_DIR."whois-servers.php";

            if(!is_array($tld)) $tld = explode(",",$tld);

            $founds     = [];
            $servers    = [];
            foreach(self::$whois_servers AS $k=>$v){
                $k_split = explode(",",$k);
                foreach($k_split AS $k_row){
                    $servers[$k_row] = $v;
                }
            }

            foreach($tld AS $t) if(isset($servers[$t])) $founds[$t] = $servers[$t];
            return $founds;
        }


        static function questioning_module($module='',$sld='',$tlds=[]){
            if(!isset(self::$modules[$module])){
                self::$modules[$module] = Modules::Load("Registrars",$module);
                self::$modules[$module]["class"] = new $module();
            }
            $module = self::$modules[$module]["class"];
            return $module->questioning($sld,$tlds);
        }


        static function questioning($sld=false,$tld=false,$server=false,$port=43,$available=false){
            if(!$tld) return false;
            $full_domain = $sld.".".$tld;

            if(substr($server,0,7) == 'http://' || substr($server,0,8) == 'https://'){
                $server = str_replace("{domain}",$full_domain,$server);
                $data   = Utility::HttpRequest($server,['timeout' => 5]);

                if(!$data)
                    return [
                        'server' => $server,
                        'status' => "unknown",
                        'whois'  => "",
                    ];

            }
            else{
                MioException::$error_hide=true;
                $query = @fsockopen ($server, $port,$num,$message,5);
                MioException::$error_hide=false;

                if(!$query)
                    return [
                        'server' => $server,
                        'status' => "unknown",
                        'whois'  => "",
                    ];

                $data    = "";
                @fputs($query, $full_domain."\r\n");
                @socket_set_timeout($query,5);
                while(!@feof($query)){
                    $data .= @fread($query, 4096);
                }
                @fclose($query);

            }


            if(Validation::isEmpty($data))
                return [
                    'status' => "error",
                    'message' => "answer is empty",
                ];
            $available = trim($available);
            $response = ['whois' => $data];
            $response['status'] = stristr($data,$available) ? "available" : "unavailable";
            return $response;
        }


        static function get_whois($sld,$tld){
            $servers            = self::whois_server([$tld]);
            $result             = [];
            if(isset($servers[$tld])){
                $output         = self::questioning($sld,$tld,$servers[$tld]["host"],43,$servers[$tld]["available_pattern"]);
                if($output){
                    $result["raw"] = $output["whois"];
                    $result["status"] = $output["status"];
                }
            }
            return $result;
        }


        static function check($sld,$tlds=[]){
            if(!is_array($tlds)) $tlds = [$tlds];
            $servers            = self::whois_server($tlds);
            $result             = [];
            $interrogations     = [];
            $M_interrogations   = [];

            foreach ($tlds AS $t){
                $tldInfo    = self::get_tld($t,"module");
                if($tldInfo && $tldInfo["module"] != "none")
                    $M_interrogations[$tldInfo["module"]][] = $t;
                elseif(isset($servers[$t]["host"]) && isset($servers[$t]["available_pattern"]))
                {
                    $sld_idn = idn_to_ascii($sld,0,INTL_IDNA_VARIANT_UTS46);
                    $res = false;
                    if($h_questioning = Hook::run("DomainQuestioning",['sld' => $sld_idn,'tld' => $t,'module' => '']))
                        foreach($h_questioning AS $row)
                            if(is_array($row) && $row)
                                $res = $row;
                    if(!$res) $res = self::questioning($sld_idn, $t, $servers[$t]["host"], 43, $servers[$t]["available_pattern"]);
                    $interrogations[$t] = $res;
                }
                else
                    $interrogations[$t] = false;
            }

            if($M_interrogations){
                foreach($M_interrogations AS $m=>$t){
                    $res = false;
                    foreach($t AS $_k => $_t){
                        if($h_questioning = Hook::run("DomainQuestioning",['sld' => $sld,'tld' => $_t,'module' => $m]))
                        {
                            foreach($h_questioning AS $row)
                            {
                                if(is_array($row) && $row){
                                    $interrogations[$_t] = $row;
                                    unset($t[$_k]);
                                }
                            }
                        }
                    }
                    if($t) $res = self::questioning_module($m,$sld,$t);
                    if($res) $interrogations = array_merge($interrogations,$res);
                }
            }

            foreach($tlds AS $t){
                $questioning = isset($interrogations[$t]) ? $interrogations[$t] : false;

                $domain = $sld.".".$t;
                $result[$domain]["sld"] = $sld;
                $result[$domain]["tld"] = $t;

                if(!$questioning){
                    $result[$domain]["status"] = "unknown";
                    $result[$domain]["message"] = "Inquiry failed.";
                }elseif($questioning["status"] == "error"){
                    $result[$domain]["status"]  = "unknown";
                    $result[$domain]["message"] = $questioning["message"];
                }else{
                    $result[$domain]["status"] = $questioning["status"];
                    if(isset($questioning["premium"]) && $questioning["premium"]){
                        $result[$domain]["premium"] = true;
                        $result[$domain]["premium_price"] = $questioning["premium_price"];
                    }
                }
            }
            return $result;
        }


        static function get_tld($definition='com',$select=''){
            $get    = Models::$init->db->select($select)->from("tldlist");
            if(Validation::isInt($definition)) $get->where("id","=",$definition);
            else $get->where("name","=",$definition);
            return $get->build() ? $get->getAssoc() : false;
        }

    }