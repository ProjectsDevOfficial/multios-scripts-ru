<?php
    class Payssion_dokuid extends PaymentGatewayModule
    {
        function __construct()
        {
            $this->name             = __CLASS__;
            if(!class_exists('Payssion')) include MODULE_DIR."Payment".DS."Payssion.php";
            parent::__construct();
            Payssion::set_gateway($this);
        }

        public function config_fields()
        {
            return Payssion::config();
        }

        public function area($params=[])
        {
            return Payssion::link($params,'doku_id');
        }

        public function callback()
        {
            return Payssion::callback();
        }

    }