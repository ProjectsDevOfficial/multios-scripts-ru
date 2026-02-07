<?php
    class WebMoney extends PaymentGatewayModule
    {
        function __construct()
        {
            $this->name             = __CLASS__;

            parent::__construct();
        }

        public function config_fields()
        {
            return [
                'purse'          => [
                    'name'              => "WMZ Purse",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["purse"] ?? '',
                ],
                'key'          => [
                    'name'              => "Secret Key",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["key"] ?? '',
                ],
                'rate'          => [
                    'name'              => "Rate",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["rate"] ?? '1.0',
                ],
            ];
        }

        public function area($params=[])
        {
            $amount = $params['amount'];
            $rate = $this->config['settings']['rate'];
            $itogo = round( sprintf( "%.2f", $amount ) * $rate, 2);

            $code = "<style type=\"text/css\" media=\"all\">\r\n\t\t\t.button {\r\n\t\t\tcolor:#000000;\r\n\t\t\tcursor: pointer;\r\n\t\t\tcursor: hand;\r\n\t\t\theight:20px;\r\n\t\t\tfont-weight:bold;\r\n\t\t\tbackground-color:#ffffff;\r\n\t\t\tborder:1px solid #8FBCE9;\r\n\t\t\tfilter:progid:DXImageTransform.Microsoft.Gradient(GradientType=0,StartColorStr='#ffffff',EndColorStr='#e5e5e5');}\r\n\t\t\t}\r\n\t\t\t</style>\r\n\t<br>\r\n\t<form action=\"https://merchant.webmoney.ru/lmi/payment.asp\" method=\"POST\">\r\n\t<input type=\"hidden\" name=\"LMI_PAYEE_PURSE\" value=\"".$this->config['settings']['purse']."\">\r\n    <input type=\"hidden\" name=\"LMI_PAYMENT_NO\" value=\"".$this->checkout_id."\">\r\n    <input type=\"hidden\" name=\"LMI_PAYMENT_AMOUNT\" value=\"".$itogo."\">\r\n    <input type=\"hidden\" name=\"LMI_PAYMENT_DESC\" value=\"Checkout # ".$this->checkout_id."\">\r\n    <input type=\"hidden\" name=\"LMI_RESULT_URL\" value=\"".$this->links['callback']."\">\r\n    <input type=\"hidden\" name=\"LMI_SUCCESS_URL\" value=\"".$this->links['successful']."\">\r\n    <input type=\"hidden\" name=\"LMI_SUCCESS_METHOD\" value=\"1\">\r\n    <input type=\"hidden\" name=\"LMI_FAIL_URL\" value=\"".$this->links['failed']."\">\r\n    <input type=\"hidden\" name=\"LMI_FAIL_METHOD\" value=\"1\">\r\n    <div align='center'><button type=\"submit\" value=\"".$this->l_payNow."\" class=\"lbtn green\"></button></div>\r\n</form>";
            return $code;
        }

        public function callback()
        {
            $silent             = "true";
            $debugreport        = "";
            $arr                = "";

            foreach ( $_REQUEST as $key => $value )
            {
                $arr .= $key."=>".$value."\n";
            }
            if ( !isset($_POST[LMI_PAYMENT_AMOUNT]) ||
                !isset($_POST[LMI_PAYMENT_NO]) 	||
                !isset($_POST[LMI_PAYEE_PURSE]) 	||
                !isset($_POST[LMI_SYS_INVS_NO]) 	||
                !isset($_POST[LMI_SYS_TRANS_NO])  	||
                !isset($_POST[LMI_PAYER_PURSE]) 	||
                !isset($_POST[LMI_SYS_TRANS_DATE]) ||
                !isset($_POST[LMI_HASH] )		||
                !is_numeric($_POST[LMI_PAYMENT_NO])  )

            {
                echo "NO";
                exit();
            }
            $debugreport .= $arr;

            $custom_id      = (int) Filter::init("POST/LMI_PAYMENT_NO","numbers");

            if(!$custom_id){
                $this->error = 'ERROR: Checkout id not found.';
                return false;
            }

            $checkout       = $this->get_checkout($custom_id);

            // Checkout invalid error
            if(!$checkout)
            {
                $this->error = 'Checkout ID unknown';
                return false;
            }

            // You introduce checkout to the system
            $this->set_checkout($checkout);


            $key        = $this->config['settings']['key'];

            $my_crc = strtoupper( hash('sha256', $_POST['LMI_PAYEE_PURSE'].$_POST['LMI_PAYMENT_AMOUNT'].$_POST['LMI_PAYMENT_NO'].$_POST['LMI_MODE'].$_POST['LMI_SYS_INVS_NO'].$_POST['LMI_SYS_TRANS_NO'].$_POST['LMI_SYS_TRANS_DATE'].$key.$_POST['LMI_PAYER_PURSE'].$_POST['LMI_PAYER_WM']) );
            if ( strtoupper( $my_crc ) != strtoupper( $_POST[LMI_HASH] ) )
            {
                Modules::save_log("Payment","WebMoney","callback",false,$debugreport,"Error");
                echo "NO";
                exit();
            }

            $total_invoice = round($this->checkout["data"]["total"],2);

            $rate = $this->config['settings']['rate'];

            $itogo = round( sprintf( "%.2f", $total_invoice ) * $rate[0], 2 );

            $p_total = round($_POST['LMI_PAYMENT_AMOUNT'],2);

            if($itogo != $p_total)
            {
                Modules::save_log("Payment","WebMoney","callback",['p_amount' => $p_total,'itogo' => $itogo],$debugreport,"The amount paid and the amount payable are not equivalent");
                echo "NO";
                exit();
            }

            return [
                'status'            => 'successful',
                'callback_message'        => "OK\n",
            ];
        }

    }