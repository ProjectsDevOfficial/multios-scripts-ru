<?php
    class Payssion_yamoney extends PaymentGatewayModule
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
            return Payssion::link($params,'yamoney');
        }

        public function refund($params=[])
        {
            return Payssion::refund($params);
        }

        public function callback()
        {
            return Payssion::callback();
        }

    }