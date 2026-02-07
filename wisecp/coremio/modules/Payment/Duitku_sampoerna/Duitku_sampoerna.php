<?php
    class Duitku_sampoerna extends PaymentGatewayModule
    {
        public $duitku_p_code = 'S1';
        function __construct()
        {
            $this->name             = __CLASS__;

            parent::__construct();
            if(!class_exists("Duitku")) include MODULE_DIR."Payment".DS."Duitku.php";
            Duitku::init($this);
        }

        public function config_fields()
        {
            return Duitku::config();
        }

        public function area($params=[])
        {
            return Duitku::link($params);
        }

        public function callback()
        {
            return Duitku::callback();
        }
    }