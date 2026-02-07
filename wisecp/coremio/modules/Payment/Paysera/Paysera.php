<?php
    if(!class_exists('WebToPay')) include __DIR__.DS."WebToPay.php";
    class Paysera extends PaymentGatewayModule
    {
        function __construct()
        {
            $this->name             = __CLASS__;

            parent::__construct();
        }

        public function config_fields()
        {
            return [
                'projectID'             => [
                    'name'              => "Project ID",
                    'description'       => "Enter unique Project ID",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["projectID"] ?? '',
                ],
                'projectPass'           => [
                    'name'              => "Project password",
                    'description'       => "Enter unique sign password",
                    'type'              => "password",
                    'value'             => $this->config["settings"]["projectPass"] ?? '',
                ],
                'testmode'              => [
                    'name'              => "Test Mode",
                    'description'       => "Tick this to enable test mode",
                    'type'              => "approval",
                    'value'             => 1,
                    'checked'           => (boolean) (int) ($this->config["settings"]["testmode"] ?? 0),
                ],
            ];
        }

        public function area($params=[])
        {
            try
            {
                $request = WebToPay::buildRequest(array(
                    'projectid'     => $this->config['settings']['projectID'],
                    'sign_password' => $this->config['settings']['projectPass'],

                    'orderid'       => $this->checkout_id,
                    'amount'        => intval(number_format($params['amount'], 2, '', '')),
                    'currency'      => $params['currency'],

                    'accepturl'     => $this->links['callback'].'?accepturl=1',
                    'cancelurl'     => $this->links['return'],
                    'callbackurl'   => $this->links['callback'],

                    'p_firstname'   => $this->clientInfo->name,
                    'p_lastname'    => $this->clientInfo->surname,
                    'p_email'       => $this->clientInfo->email,
                    'p_street'      => $this->clientInfo->address->address,
                    'p_city'        => $this->clientInfo->address->city,
                    'p_state'       => $this->clientInfo->address->counti,
                    'p_zip'         => $this->clientInfo->address->zipcode,
                    'p_countrycode' => $this->clientInfo->address->country_code,
                    'test'          => $this->config['settings']['testmode'] ? 1 : 0,
                ));

                $code = '<form method="post" action="'.WebToPay::PAY_URL.'" target="_blank">
		<input type=hidden name=action value="payment">
		<input type="hidden" name="data" value="'.$request['data'].'" />
		<input type="hidden" name="sign" value="'.$request['sign'].'" />
		<div align="center"><button class="lbtn green">'.$this->l_payNow.'</button></div>	
		</form>
	';
                return $code;
            }
            catch (WebToPayException $e)
            {
                echo get_class($e) . ': ' . $e->getMessage();
            }

        }

        public function callback()
        {

            try
            {
                $response = WebToPay::validateAndParseData($_REQUEST, $this->config['settings']['projectID'], $this->config['settings']['projectPass']);

                $orderId   = (int) Filter::rnumbers($response['orderid']);

                if(!$orderId){
                    $this->error = 'ERROR: order id not found.';
                    return false;
                }

                $checkout       = $this->get_checkout($orderId);

                // Checkout invalid error
                if(!$checkout)
                {
                    $this->error = 'Checkout ID unknown';
                    return false;
                }

                // You introduce checkout to the system
                $this->set_checkout($checkout);



                if (isset($_REQUEST['accepturl'])) {
                    Modules::save_log("Payment",__CLASS__,'callback',$_REQUEST,'Successful');


                    $redirectPage = $this->links['successful'];

                    header("Location: $redirectPage");

                    exit;
                }

                if ($response['status'] == 1) {
                    $orderData = array(
                        'amount' => (string) round($this->checkout["data"]["total"],2),
                        'currency' => $response['currency'],
                    );

                    $isPaymentCorrect = $this->checkPayment($orderData ,$response);

                    if (!$isPaymentCorrect)
                    {
                        Modules::save_log("Payment",__CLASS__,'callback',$_REQUEST,$response,'Unsuccessful');
                        exit("ERROR");
                    }
                }
            }
            catch (Exception $e) {
                Modules::save_log("Payment",__CLASS__,'callback',$_REQUEST,'Unsuccessful');
                $exceptionMessage = get_class($e) . ': ' . $e->getMessage();
                exit($exceptionMessage);
            }

            return [
                'status'            => 'successful',
                'message'           => [
                    'Request ID' => $response['requestid'],
                ],
                'callback_message'  => 'OK',
            ];
        }

         private function checkPayment($orderMoney, $response)
         {
             $orderAmount   = $orderMoney['amount'];
             $orderCurrency = $orderMoney['currency'];

             if ($response['amount'] !== $orderAmount
                 || $response['currency'] !== $orderCurrency) {
                 $checkConvert = array_key_exists('payamount', $response);
                 if (!$checkConvert) {
                     exit(sprintf(
                         'Wrong pay amount: ' . $response['amount'] / 100 . $response['currency']
                         . ', expected: ' . $orderAmount / 100 . $orderCurrency
                     ));
                 } elseif ($response['payamount'] !== $orderAmount
                     || $response['paycurrency'] !== $orderCurrency) {
                     exit(sprintf(
                         'Wrong pay amount: ' . $response['payamount'] / 100 . $response['paycurrency']
                         . ', expected: ' . $orderAmount / 100 . $orderCurrency
                     ));
                 }
             }

             return true;
         }

    }