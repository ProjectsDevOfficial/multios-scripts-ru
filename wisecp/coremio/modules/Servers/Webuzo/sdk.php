<?php
    class WebuzoSDK
    {
        public $server      = [];
        public $error       = NULL;

        public function __construct($server=[])
        {
            $this->server = $server;
        }

        public function call($act='',$post=[])
        {
            $user       = $this->server["username"];
            $pass       = $this->server["password"];
            $api_key    = $this->server["access_hash"];
            $ip         = $this->server["ip"];
            $port       = $this->server["port"];
            $secure     = $this->server["secure"];

            $url        = $secure ? "https://" : "http://";

            if($api_key)
                $url        .= $ip;
            else
                $url        .= rawurlencode($user).':'.rawurlencode($pass).'@'.$ip;

            $url .=  ":".$port;

            $url .= '/index.php';

           $url .= '?api=json&act='.$act;


            if($api_key)
            {
                $post["apiuser"] = $user;
                $post["apikey"] = $api_key;
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            if(!empty($post)){
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
            }
            $resp   = curl_exec($ch);
            $err    = curl_error($ch);

            Modules::save_log("Servers","Webuzo",$act,[
                'url'       => str_replace([$pass,$api_key],'***HIDDEN***',$url),
                'post'      => $post,
            ],$resp,$err);

            if($err)
            {
                $this->error = $err;
                return false;
            }

            $res = json_decode($resp, true);
            if(!$res && $resp)
            {
                $this->error = "The error returned from the API service could not be resolved.";
                return false;
            }

            if($res["error"] ?? [])
            {
                $this->error = strip_tags(implode(", ",$res["error"]));
                return false;
            }

            return $res;
        }



    }