<?php
    use Gregwar\Captcha\CaptchaBuilder;
    class DefaultCaptcha
    {
        public array $lang;
        public array $config;
        public array $properties;

        public function __construct()
        {
            $config = Modules::Config("Captcha",__CLASS__);

            $this->config = is_array($config) ? $config : [];
            $this->lang = Modules::Lang("Captcha",__CLASS__);
            $this->properties = [
                'background-color' => '',
                'text-color'       => '#132525',
                'width'            => 133,
                'height'           => 41,
            ];
        }

        private function rgb($hex='#FFF'){
            return sscanf($hex, "#%02x%02x%02x");
        }

        public function getInputName():string
        {
            return 'captcha_value';
        }

        public function getMarkup():string
        {
            $url = Utility::link_determiner("captcha");
            return '<img src="'.$url.'" width="'.$this->properties["width"].'" height="'.$this->properties["height"].'" class="captcha-img" data-href="'.$url.'">';
        }

        public function generateDisplay():string
        {
            if(!class_exists("Captcha\CaptchaBuilder")) require __DIR__.DS."vendor".DS."autoload.php";
            $builder = new CaptchaBuilder();

            $settings = $this->properties;

            $bg_color = $settings["background-color"];
            $tx_color = $settings["text-color"];

            if($bg_color != ''){
                list($r,$g,$b) = $this->rgb($bg_color);
                $builder->setBackgroundColor($r,$g,$b);
            }else{
                if(function_exists("finfo_open")) {
                    $builder->setIgnoreAllEffects(true);
                    $builder->setBackgroundImages(array(__DIR__.DS."vendor".DS."captcha-bg.png"));
                }
            }

            if($tx_color != ''){
                list($r,$g,$b) = $this->rgb($tx_color);
                $builder->setTextColor($r,$g,$b);
            }

            $builder->setMaxFrontLines(0);
            $builder->setMaxBehindLines(0);

            $builder->build($settings["width"],$settings["height"]);
            Session::set("GeneratingCaptchaCode",$builder->getPhrase());
            header('Content-type: image/jpeg');
            $builder->output(100);
            return '';
        }

        public function check():bool
        {
            $code1  = strtolower(Filter::init("POST/".$this->getInputName(),"letters_numbers"));
            $code2 = strtolower(Session::get("GeneratingCaptchaCode"));
            return ($code1 != '' && $code2 != '' && $code1==$code2);
        }

        public function refreshJS():string
        {
            $output = "$('.captcha-img').each(function(){ ";
            $output .= "var url = $(this).data('href') + '?time='+(new Date().getTime());";
            $output .= "$(this).attr('src',url); ";
            $output .= "$(this).parent().next('input').val(''); ";
            $output .= "});";

            return $output;
        }



    }