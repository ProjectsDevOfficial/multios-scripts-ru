<?php
    Class ip_api {
        public $error;
        public $config=[];

        function __construct()
        {
            $this->config = Modules::Config("IP",__CLASS__);

        }

        public function info($ip=''){

            /* Return Json to Array Example
            {
            "as": "AS60781 LeaseWeb Netherlands B.V.",
            "city": "Amsterdam",
            "country": "Netherlands",
            "countryCode": "NL",
            "isp": "LeaseWeb Netherlands B.V.",
            "lat": 52.3738,
            "lon": 4.89093,
            "org": "LeaseWeb",
            "query": "95.211.101.229",
            "region": "NH",
            "regionName": "North Holland",
            "status": "success",
            "timezone": "Europe/Amsterdam",
            "zip": "1012"
            }
           * */

            $ch = curl_init();

            if($this->config["key"])
                curl_setopt($ch,CURLOPT_URL,"http://pro.ip-api.com/json/".$ip."?key=".$this->config["key"]);
            else
                curl_setopt($ch,CURLOPT_URL,"http://ip-api.com/json/".$ip);

            curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
            curl_setopt($ch,CURLOPT_HEADER, false);
            curl_setopt($ch,CURLOPT_TIMEOUT, 2);

            $result = curl_exec($ch);
            if(curl_errno($ch)){
                $this->error = curl_error($ch);
                $result = false;
            }
            curl_close($ch);

            if($result){
                $result = Utility::jdecode($result,true);
                if($result["status"] == "success"){
                    $result["countryCode"] = strtolower($result["countryCode"]);
                    $result["city"] = $result["regionName"];
                }else{
                    $this->error = $result["message"];
                    return false;
                }
            }
            return $result;
        }
    }