<?php

class BPC_Wh { 

   function __construct() {
    
}

public function BPC_getBitPayToken($endpoint,$gateway)
    {
        //dev or prod token
        switch (strtolower($endpoint)) {
            case 'test':
            default:
                return $gateway->config['settings']['bitpay_checkout_token_dev'];
                break;
            case 'production':
                return $gateway->config['settings']['bitpay_checkout_token_prod'];
                break;
        }

    }

}

?>
