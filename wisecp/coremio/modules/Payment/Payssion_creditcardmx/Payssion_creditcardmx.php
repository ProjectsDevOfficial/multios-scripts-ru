<?php
    class Payssion_creditcardmx extends PaymentGatewayModule
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
            return Payssion::link($params,'creditcard_mx');
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