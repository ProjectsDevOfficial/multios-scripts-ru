<?php
    class reCaptcha
    {
        public array $lang;
        public array $config;

        public function __construct()
        {
            $config = Modules::Config("Captcha",__CLASS__);
            $this->config = is_array($config) ? $config : [];
            $this->lang = Modules::Lang("Captcha",__CLASS__);
        }

        public function config_fields():array
        {
            $settings = $this->config;
            return [
                'site-key'          => [
                    'wrap_width'        => 100,
                    'name'              => "Site Key",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => $settings["site-key"] ?? "",
                ],
                'secret-key'          => [
                    'wrap_width'        => 100,
                    'name'              => "Secret Key",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => $settings["secret-key"] ?? "",
                ],
            ];
        }

        public function save_fields($fields=[]):array|bool
        {
            if(!isset($fields['site-key']) || !$fields['secret-key']){
                $this->error = $this->lang["error1"];
                return false;
            }
            return $this->config ? array_replace_recursive($this->config,$fields) : $fields;
        }

        public function getMarkup():string
        {
            if(isset($GLOBALS["grecaptchaInput"]))
                $GLOBALS["grecaptchaInput"]++;
            else
                $GLOBALS["grecaptchaInput"] = 1;

            $site_key = $this->config["site-key"];
            $input_id = "grecaptcha-hidden-input-".$GLOBALS["grecaptchaInput"];


            $return =  '<script type="text/javascript">';
            $return .= 'grecaptcha.ready(function() {';
            $return .= "grecaptcha.execute('".$site_key."', {action: 'submit'}).then(function(token) {";
            $return .= '$("#'.$input_id.'").val(token);';
            $return .= '});';
            $return .= '});';
            $return .= ' </script>';
            $return .= '<input type="hidden" name="g-recaptcha-response" id="'.$input_id.'">';

            return $return;
        }

        public function refreshJS():string
        {
            $site_key       = $this->config["site-key"] ?? 'na';
            $output         = '';
            $id_prefix      = "grecaptcha-hidden-input-";
            $lastIDnum      = $GLOBALS["grecaptchaInput"] ?? 1;

            for (;$lastIDnum >= 1; $lastIDnum--)
            {
                $output .= "grecaptcha.ready(function() {";
                $output .= "grecaptcha.execute('".$site_key."', {action: 'submit'}).then(function(token) {";
                $output .= '$("#'.$id_prefix.$lastIDnum.'").val(token);';
                $output .= "});";
                $output .= "});";
            }
            return $output;
        }

        public function check():bool
        {
            $token          = $_POST['g-recaptcha-response'];
            $secretKey      = $this->config["secret-key"];
            $url = "https://www.google.com/recaptcha/api/siteverify";
            $data = [
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => UserManager::GetIP(),
            ];

            $curlConfig = curl_init();
            curl_setopt($curlConfig, CURLOPT_URL, $url);
            curl_setopt($curlConfig, CURLOPT_POST, true);
            curl_setopt($curlConfig, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlConfig, CURLOPT_POSTFIELDS, http_build_query($data));
            $response = curl_exec($curlConfig);
            curl_close($curlConfig);

            $responseKeys = json_decode($response, true);

            return $responseKeys["success"] ?? false;
        }


    }

    Hook::add("ClientAreaHeadJS",1,function(){
        $config = include __DIR__.DS."config.php";
        $site_key = $config["site-key"];
        return EOL."<script src='https://www.google.com/recaptcha/api.js?render=".$site_key."'></script>";
    });