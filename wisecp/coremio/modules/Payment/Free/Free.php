<?php
    class Free {
        public $checkout_id,$checkout;
        public $config = [],$name,$commission=false,$lang=[];
        public $page_type="in-page",$payform=__DIR__.DS."pages".DS."payform",$callback_type="client-sided";
        public $error;

        function __construct(){

            $this->config     = Modules::Config("Payment",__CLASS__);
            $this->lang       = Modules::Lang("Payment",__CLASS__);
            $this->name       = __CLASS__;
        }

        public function get_auth_token(){
            $syskey = Config::get("crypt/system");
            $token  = md5(Crypt::encode("Free-Auth-Token=".$syskey,$syskey));
            return $token;
        }

        public function set_checkout($checkout){
            $this->checkout_id = $checkout["id"];
            $this->checkout    = $checkout;
            Session::set("free_ctid",$this->checkout_id);
        }

        public function payment_result(){
            $ctid           = Session::get("free_ctid");
            if(!$ctid) return false;
            $checkout       = Basket::get_checkout($ctid);
            if(!$checkout)
                return [
                    'status' => "ERROR",
                    'status_msg' => Bootstrap::$lang->get("errors/error6",Config::get("general/local")),
                ];

            $this->set_checkout($checkout);

            $c_amount = round($checkout["data"]["total"],2);

            if($c_amount > 0.00)
                return [
                    'checkout'  => $checkout,
                    'status'    => "ERROR",
                    'status_msg' => Bootstrap::$lang->get("errors/error10"),
                ];

            Basket::set_checkout($checkout["id"],[
                'status' => "paid",
            ]);

            Session::delete("free_ctid");

            return [
                'status'        => "SUCCESS",
                'checkout'      => $checkout,
            ];
        }

    }