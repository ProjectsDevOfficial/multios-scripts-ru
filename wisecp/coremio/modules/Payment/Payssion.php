<?php
    class Payssion
    {
        public static $_api_key = '';
        public static $_secret_key = '';
        public static $gateway;

        public static function set_gateway($gateway)
        {
            self::$gateway              = $gateway;
            self::$_api_key             = self::$gateway->config['settings']['api_key'] ?? '';
            self::$_secret_key          = self::$gateway->config['settings']['secret_key'] ?? '';
        }

        public static function config() {
            return [
                'api_key'          => [
                    'name'              => "Payssion - API Key",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => self::$gateway->config["settings"]["api_key"] ?? '',
                ],
                'secret_key'          => [
                    'name'              => "Payssion - Secret Key",
                    'description'       => "",
                    'type'              => "text",
                    'value'             => self::$gateway->config["settings"]["secret_key"] ?? '',
                ],
                'testmode'          => [
                    'name'              => "Test Mode",
                    'description'       => "",
                    'type'              => "approval",
                    'value'             => 1,
                    'checked'           => (boolean) (int) (self::$gateway->config["settings"]["testmode"] ?? 0),
                ]
            ];
        }

        public static function link(&$params, $pm_id) {
            $gateway = self::$gateway;
            $url = '';

            if ($gateway->config['settings']['testmode']) {
                $pm_id = 'payssion_test';
                $url    = 'http://sandbox.payssion.com/payment/create.html';
            } else {
                $url    = 'https://www.payssion.com/payment/create.html';
            }

            $api_key = self::$_api_key;
            $secret_key = self::$_secret_key;
            $source = 'wisecp';

            // Invoice Parameters
            $track_id = $gateway->checkout_id;
            $description = 'Invoice Payment';
            $amount = $params['amount'];
            $currency = $params['currency'];

            // Client Parameters
            $payer_name = $gateway->clientInfo->name . ' ' . $gateway->clientInfo->surname;
            $payer_email = $gateway->clientInfo->email;

            $system_url = APP_URI;
            $notify_url = $gateway->links["callback"];

            $arr = array($api_key, $pm_id, $amount, $currency,
                $track_id, '', $secret_key);
            $api_sig = md5(implode('|', $arr));

            $code = '<form method="post" action="' . $url . '">
		<input type="hidden" name="source" value="'.$source.'" />
		<input type="hidden" name="api_key" value="'.$api_key.'" />
		<input type="hidden" name="api_sig" value="'.$api_sig.'" />
		<input type="hidden" name="pm_id" value="'.$pm_id.'" />
		<input type="hidden" name="payer_name" value="'.$payer_name.'" />
		<input type="hidden" name="payer_email" value="'.$payer_email.'" />
		<input type="hidden" name="track_id" value="'.$track_id.'" />
		<input type="hidden" name="description" value="'.$description.'" />
		<input type="hidden" name="amount" value="'.$amount.'" />
		<input type="hidden" name="currency" value="'.$currency.'" />
		<input type="hidden" name="notify_url" value="'.$notify_url.'" />
		<input type="hidden" name="success_url" value="'.$gateway->links["successful"].'" />
		<input type="hidden" name="redirect_url" value="'.$gateway->links['failed'].'" />
		<div align="center"><div class="yuzde30"><button class="lbtn green" type="submit">Pay Now</button></div></div>
		</form>';
            return $code;
        }

        public static function refund($params) {
            $gateway = self::$gateway;
            $url = '';
            if ($gateway->config['settings']['testmode']) {
                $url = 'http://sandbox.payssion.com/api/v1/refunds';
            } else {
                $url = 'https://www.payssion.com/api/v1/refunds';
            }

            $transaction_id = $params['id'];
            $amount = $params['total'];
            $currency = $gateway->currency($params['currency']);
            $request_data = array(
                'api_key' => self::$_api_key,
                'transaction_id' => $transaction_id,
                'amount' => $amount,
                'currency' => $currency,
            );
            $request_data['api_sig'] = md5(self::$_api_key . "|$transaction_id|$amount|$currency|" . self::$_secret_key);

            $ch = curl_init($url);
            curl_setopt($ch,CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);

            // Check HTTP status code

            $status = 'error';

            if (false === $response) {
                $errno = curl_errno($ch);
                $raw_data = "Failed to send request: $errno " . curl_error($ch);
            } else {
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                switch ($http_code) {
                    case 200:  # OK
                        $response = json_decode($response, true);
                        $raw_data = $response;
                        if (200 == $response['result_code']) {
                            $status = 'success';
                            $trans_id = $response['refund']['transaction_id'];
                        }
                        break;
                    default:
                        $raw_data = "Unexpected HTTP code: $http_code, response:$response";
                }
            }

            curl_close($ch);

            if($status != 'success')
            {
                $gateway->error = $raw_data;
                return false;
            }

            return true;
        }

        public static function callback()
        {
            $gateway        = self::$gateway;
            // Assign payment notification values to local variables
            $pm_id          = $_POST['pm_id'];
            $pm_name        = str_replace('_', '', $pm_id);
            $gatewaymodule  = "payssion" . $pm_name;
            $amount         = $_POST['amount'];
            $paid           = $_POST['paid'];
            $currency       = $_POST['currency'];
            $track_id       = $_POST['track_id'];
            $sub_track_id   = $_POST['sub_track_id'];
            $state          = $_POST['state'];
            $transid        = $_POST['transaction_id'];
            $fee            = $_POST['fee'];

            Modules::save_log("Payment",'Payssion','callback',false,['GET' => $_GET,'POST' => $_POST]);

            if(!Filter::isPOST())
            {
                exit('Incorrect API Request');
            }

            $status_msg     = '';

            $check_array = array(
                self::$_api_key,
                $pm_id,
                $amount,
                $currency,
                $track_id,
                $sub_track_id,
                $state,
                self::$_secret_key
            );
            $check_msg = implode('|', $check_array);
            $check_sig = md5($check_msg);
            $notify_sig = $_POST['notify_sig'];
            if ($notify_sig == $check_sig) {

            } else {
                Modules::save_log("Payment","Payssion","transaction",$track_id,'failed to validate IPN');
                header('HTTP/1.0 406 Not Acceptable');
                echo "check_msg=$check_msg, check_sig=$check_sig";
                exit();
            }

            if ($paid > 0) {
                $status_msg .= "$state:" . $gatewaymodule . $track_id;
                $invoiceid = $track_id; # Checks invoice ID is a valid invoice number or ends processing
                $status_msg .= "invoiceid=$invoiceid:";
                $checkout   = $gateway->get_checkout($invoiceid);

                if(!$checkout) exit('Checkout not found');

                $status_msg .= "checkCbTransID";

                if ('completed' == $state) {
                    if (array_key_exists('currency_settle', $_POST)) {
                        $paid = $_POST['amount_local'];
                        $currency = $_POST['currency_local'];
                        $fee = $fee / $amount * $_POST['amount_local'];;
                    }
                } else if ('paid_partial' == $state || 'paid_more' == $state) {
                    if (array_key_exists('currency_settle', $_POST)) {
                        $paid = $paid / $amount * $_POST['amount_local'];
                        $currency = $_POST['currency_local'];
                        $fee = $fee / $amount * $_POST['amount_local'];
                    }
                } else {
                    echo "$state not correct";
                    exit();
                }

                // Formats amount in cent units to string with dot separator
                $paid = number_format($paid, 2, '.', '');
                $status_msg .= "success:$state:$paid";
                Modules::save_log("Payment","Payssion","transaction",$_POST,"Successful $state:$paid");

                return [
                    'status' => 'successful',
                    'callback_message' => $status_msg,

                ];

            }
            else {
                echo 'not paid';
                exit;
            }
        }
    }