<?php
    class TCO extends PaymentGatewayModule
    {

        function __construct()
        {
            $this->name             = __CLASS__;

            parent::__construct();
        }

        public function config_fields()
        {
            return [
                'merchant_code' => [
                    'name'        => "Merchant Code",
                    'description' => "Your merchant code from 2Checkout account",
                    'type'        => "text",
                    'value'       => $this->config["settings"]["merchant_code"] ?? '',
                ],
                'secret_key' => [
                    'name'        => "Secret Key",
                    'description' => "Your secret key from 2Checkout account",
                    'type'        => "text",
                    'value'       => $this->config["settings"]["secret_key"] ?? '',
                ],
                'secret_word' => [
                    'name'        => "Secret Word",
                    'description' => "Your secret word from 2Checkout account",
                    'type'        => "text",
                    'value'       => $this->config["settings"]["secret_word"] ?? '',
                ],
                'publishable_key' => [
                    'name'        => "Publishable Key",
                    'description' => "Your api publishable key from 2Checkout account",
                    'type'        => "text",
                    'value'       => $this->config["settings"]["publishable_key"] ?? '',
                ],
                'private_key' => [
                    'name'        => "Private Key",
                    'description' => "Your api private key from 2Checkout account",
                    'type'        => "text",
                    'value'       => $this->config["settings"]["publishable_key"] ?? '',
                ],
                'test_mode' => [
                    'name'        => "Test Mode",
                    'description' => "Enable test mode for development",
                    'type'        => "approval",
                    'value'       => 1,
                    'checked'     => (int) ($this->config["settings"]["test_mode"] ?? 0),
                ]
            ];
        }

        public function area($params=[])
        {
            return $this->hosted_checkout($params);
        }

        private function hosted_checkout($params)
        {
            $merchant_code  = $this->config["settings"]["merchant_code"];
            $test_mode      = (int) ($this->config["settings"]["test_mode"] ?? 0);
            $demo           = $test_mode ? 'Y' : 'N';

            Session::set("twoCheckout_id", $this->checkout_id);

            $currency_code  = $this->currency($params["currency"] ?? '') ?: 'USD';
            $total          = number_format($params["amount"] ?? 0, 2, '.', '');

            $items          = '';
            $li             = -1;
            foreach($this->getItems() as $item) {
                $li++;
                $amount = number_format($item["total_amount"],2,'.','');
                $items .= <<<HTML
<input type="hidden" name="li_{$li}_name" value="{$item["name"]}" />
<input type="hidden" name="li_{$li}_quantity" value="{$item["quantity"]}" />
<input type="hidden" name="li_{$li}_price" value="{$amount}" />
HTML;
            }


            return <<<HTML
<form method="post" action="https://secure.2checkout.com/checkout/purchase" id="2co-form">
  <input type="hidden" name="sid" value="{$merchant_code}">
  <input type="hidden" name="mode" value="2CO">
  <input type="hidden" name="demo" value="{$demo}">
  <input type="hidden" name="merchant_order_id" value="{$this->checkout_id}"> 
  <input type="hidden" name="card_holder_name" value="{$this->clientInfo->full_name}">
  <input type="hidden" name="email" value="{$this->clientInfo->email}">
  <input type="hidden" name="street_address" value="{$this->clientInfo->address->address}">
  <input type="hidden" name="city" value="{$this->clientInfo->address->counti}">
  <input type="hidden" name="state" value="{$this->clientInfo->address->city}">
  <input type="hidden" name="zip" value="{$this->clientInfo->address->zipcode}">
  <input type="hidden" name="country" value="{$this->clientInfo->address->country_code}">
  <input type="hidden" name="currency_code" value="{$currency_code}">
  <input type="hidden" name="total" value="{$total}">
  <input type="hidden" name="x_receipt_link_url" value="{$this->links["successful"]}">
     
  {$items}
  
  <button type="submit" style="display: none;">Payment</button>
</form>
<script>
    setTimeout(() => {
        document.getElementById("2co-form").submit();
    },500);
</script>

<div id="two-checkout-spinner">
    <div class="spinner"></div>
    <p>You are being redirected for payment, please wait...</p>
</div>

<style>
    #two-checkout-spinner {
        display: flex; 
        flex-direction: column; 
        align-items: center; 
        justify-content: center;
        margin-left: auto; 
        margin-right: auto;
        padding: 20px; 
    }
    .spinner {
        border: 5px solid #e0e0e0; 
        border-top: 5px solid rgba(18,108,211,0.45); 
        background-color: rgba(25,122,195,0);
        border-radius: 50%; 
        width: 40px; 
        height: 40px; 
        animation: spin 1.2s ease-in-out infinite;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>
HTML;

        }

        public function callback()
        {
            $saveLog = fn($name = '',$response = '') => Modules::save_log("Payment",__CLASS__,"callback",$name,$response ?: ['get' => $_GET, 'post' => $_POST],$this->error);

            if (isset($_POST['vendor_order_id'], $_POST['invoice_id'], $_POST['hash'])) {
                $saveLog("initialize");

                $details        = $_POST;
                $secretKey      = $this->config["settings"]["secret_key"];
                $secretWord     = $this->config["settings"]["secret_word"];
                $TCOVendorId    = $this->config["settings"]["merchant_code"];
                $invoiceStatus  = $details['invoice_status'] ?? 'unknown';

                if($details['message_type'] != "INVOICE_STATUS_CHANGED")
                    return ['status' => 'error','message' => "Incorrect message type"];

                if(!in_array($invoiceStatus,['approved','deposited']))
                    return ['status' => 'error','message' => "Invoice not approved"];


                $hashParts = explode(':', $details['hash']);
                if (count($hashParts) !== 2) {
                    $this->error = "Invalid hash format.";
                    $saveLog("Hash parts");
                    return false;
                }

                $hashAlgorithm = $hashParts[0];
                $receivedHash = $hashParts[1];

                $parameters = [$details['sale_id'] ?? '', $TCOVendorId, $details['invoice_id'] ?? '', $secretWord];

                $calculatedHash = strtoupper(hash_hmac($hashAlgorithm, implode('', $parameters), $secretKey));

                if ($calculatedHash != $receivedHash) {
                    $this->error = "Hash verification failed. Calculated: {$calculatedHash}, Received: {$receivedHash}";
                    $saveLog("Hash calculation");
                    return false;
                }

                $order_id       = $details['vendor_order_id'] ?? '';
                $checkout       = $order_id ? $this->get_checkout($order_id) : false;

                if(!$checkout) {
                    $this->error = "Checkout not found";
                    $saveLog("WISECP checkout not found : ".$order_id);
                    return false;
                }

                $this->set_checkout($checkout);

                return [
                    'status' => "successful",
                    'message' => [
                        'tco_sale_id' => $details['sale_id'],
                        'tco_invoice_id' => $details['invoice_id'],
                        'vendor_order_id' => $order_id,
                    ],
                    'callback_message' => "Payment successful"
                ];
            }
            else
            {
                $ses_checkout_id    = Session::get("twoCheckout_id");
                $redirect           = false;

                if($ses_checkout_id) {
                    $checkout = $this->get_checkout($ses_checkout_id);
                    if(!$checkout) {
                        $this->error = "Checkout not found";
                        return false;
                    }
                    $redirect = $checkout["data"]["redirect"];
                }

                $paid           = false;

                if($_REQUEST["order_id"] ?? false){
                    $order_id = (int) Filter::init("REQUEST/order_id","numbers");

                    $curl = curl_init();

                    $date = gmdate("Y-m-d H:i:s");
                    $vendorCode = $this->config["settings"]["merchant_code"];
                    $secretKey = $this->config["settings"]["secret_key"];
                    $hashString = strlen($vendorCode) . $vendorCode . strlen($date) . $date;
                    $hash = hash_hmac('sha256', $hashString, $secretKey);

                    $authHeader = sprintf('code="%s" date="%s" hash="%s" algo="sha256"', $vendorCode, $date, $hash);

                    $url = "https://api.2checkout.com/rest/6.0/orders/{$order_id}/";

                    curl_setopt_array($curl, [
                        CURLOPT_URL            => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER     => [
                            "Content-Type: application/json",
                            "Accept: application/json",
                            "X-Avangate-Authentication: {$authHeader}",
                        ],
                    ]);

                    $response = curl_exec($curl);
                    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                    curl_close($curl);

                    $saveLog("Order Detail", $response);

                    if ($httpCode === 200 && $response) {
                        $decodedResponse = json_decode($response, true);
                        if (($decodedResponse["ApproveStatus"] ?? 'OK') == "" && ($decodedResponse['Status'] ?? '') === 'COMPLETE')
                            $paid = true;
                    }
                }

                if($paid) {
                    if($redirect) Utility::redirect($redirect["failed"]);
                    return true;
                }
                else
                {
                    if($redirect) Utility::redirect($redirect["failed"]);
                    $this->error = "Failed payment";
                    return false;
                }
            }
        }

    }