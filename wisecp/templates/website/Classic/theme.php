<?php
    //define("DISABLE_CSRF",true);
    Class Classic_Theme {
        public $config=[],$name = 'Classic',$error=NULL,$language,$languages;

        function __construct()
        {
            if(!$this->languages) $this->languages = View::$init->theme_language_loader($this->name);
            if(!$this->language) $this->language = View::$init->theme_lang(Bootstrap::$lang->clang,$this->languages);

            $this->config   = include __DIR__.DS."theme-config.php";
        }

        public function router($params=[]){
            $page       = Filter::folder(isset($params[0]) ? $params[0] : '');

            if(($params[0] ?? false) && ($params[1] ?? false) && file_exists(__DIR__.DS."pages".DS.Filter::folder($params[0] ?? false).DS.Filter::folder($params[1] ?? false).".php"))
                return ['include_file' => __DIR__.DS."pages".DS.Filter::folder($params[0] ?? false).DS.Filter::folder($params[1] ?? false).".php"];
            elseif($page && file_exists(__DIR__.DS."pages".DS.$page.".php"))
                return ['include_file' => __DIR__.DS."pages".DS.$page.".php"];
        }

        public function change_settings(){

            $settings           = isset($this->config["settings"]) ? $this->config["settings"] : [];

            $header_type        = (int) Filter::init("POST/header_type","numbers");
            $clientArea_type    = (int) Filter::init("POST/clientArea_type","numbers");
            $color1             = ltrim(Filter::init("POST/color1"),"#");
            $color2             = ltrim(Filter::init("POST/color2"),"#");
            $text_color         = ltrim(Filter::init("POST/text_color"),"#");

            if($header_type != $settings["header-type"]) $settings["header-type"] = $header_type;
            if($clientArea_type != $settings["clientArea-type"]) $settings["clientArea-type"] = $clientArea_type;

            if($color1 != $settings["color1"]){
                $settings["color1"]         = $color1;
                $settings["meta-color"]     = "#".$color1;
            }

            if($color2 != $settings["color2"]) $settings["color2"] = $color2;


            if($text_color != $settings["text-color"]) $settings["text-color"] = $text_color;


            $css_file       = __DIR__.DS."css".DS."wisecp.php";

            ob_start();
            include $css_file;
            $output = ob_get_clean();
            FileManager::file_write(str_replace(".php",".css",$css_file),$output);


            return $settings;
        }

        public function get_css_url()
        {
            return View::$init->get_template_url().$this->name."/css/wisecp.css";
        }

    }