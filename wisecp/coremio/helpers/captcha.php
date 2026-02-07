<?php
    defined('CORE_FOLDER') OR exit('You can not get in here!');
    class Captcha {
        public $module = false;
        private string $type;
        public bool $input = false;
        public string $input_name='captcha_answer';

        function __construct($options=[]){
            if(isset($options["type"]))
                $this->type = substr(str_replace(["-","."],"",$options["type"]),0,255);
            else
                $this->type = Config::get("options/captcha/type");

            if(Modules::Load("Captcha",$this->type)) if(class_exists($this->type)) $this->module = new $this->type();
            if(!$this->module)
            {
                $this->type = "DefaultCaptcha";
                Modules::Load("Captcha",$this->type);
                $this->module = new $this->type();
            }

            if(method_exists($this->module,'getInputName'))
            {
                $this->input = true;
                $this->input_name = $this->module->getInputName();
            }

            return $this;
        }

        public function check():bool
        {
            return $this->module->check();
        }

        public function submit_after_js():string
        {
            return method_exists($this->module,'refreshJS') ? $this->module->refreshJS() : '';
        }

        public function getOutput($name=''):string
        {
            return method_exists($this->module,'getMarkup') ? $this->module->getMarkup() : '';
        }

        public function generate():void
        {
            echo method_exists($this->module,'generateDisplay') ? $this->module->generateDisplay() : '';
        }

        public function refresh():void
        {

        }


    }