<?php
    class Tripay_maybank_va extends PaymentGatewayModule
    {
        function __construct()
        {
            $this->name             = __CLASS__;

            parent::__construct();
            include MODULE_DIR."Payment".DS."Tripay.php";
            \Tripay\Core::$gateway = $this;
        }

        public function config_fields()
        {
            return \Tripay\Core::config();
        }

        public function area($params=[])
        {
            try {
                $output = \Tripay\Core::link($params, "MYBVA");
            }
            catch (Exception $e)
            {
                return  'Error: '.$e->getMessage();
            }

            return $output;
        }

        public function callback()
        {
            return \Tripay\Core::callback();
        }

    }