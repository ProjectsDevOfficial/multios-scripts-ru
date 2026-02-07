<?php
    class PayU extends PaymentGatewayModule
    {

        function __construct(){
            $this->name             = __CLASS__;
            $this->standard_card    = true;

            parent::__construct();
        }

        public function config_fields()
        {
            return [
                'merchant_id'          => [
                    'name'              => "Merchant ID",
                    'description'       => "Bu bilgiyi (Hesap Yönetimi > Hesap Ayarları > Anında Ödeme Bildirimi) sayfasından elde edebilirsiniz.",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["merchant_id"] ?? '',
                ],

                'secret_key'          => [
                    'name'              => "Secret Key",
                    'description'       => "Bu bilgiyi (Hesap Yönetimi > Hesap Ayarları > Anında Ödeme Bildirimi) sayfasından elde edebilirsiniz.",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["secret_key"] ?? '',
                ],

                'installment'          => [
                    'name'              => "Taksit Seçeneği",
                    'description'       => "Seçerseniz ödeme sırasında taksit seçeneği sunulur.",
                    'type'              => "approval",
                    'value'             => 1,
                    'checked'           => (boolean) (int) ($this->config["settings"]["installment"] ?? 0),
                ],
                'installment_commission'          => [
                    'name'              => "Taksit Komisyonu",
                    'description'       => "Komisyon oranı aşağıdaki gibi yazılmalıdır.<br>2 : 2.50<br>3 : 4.50<br>4 : 5.20",
                    'type'              => "textarea",
                    'value'           => $this->config["settings"]["installment_commission"] ?? '',
                ],
                'max_installment'          => [
                    'name'              => "Taksit Sınırı",
                    'description'       => "En fazla kaç taksit olacağını belirleyiniz.",
                    'type'              => "text",
                    'value'           => $this->config["settings"]["max_installment"] ?? '12',
                ],
            ];
        }

        public function installment_rates($card_bin = [])
        {
            if(!$this->config["settings"]["installment"]) return false;
            $rates      = $this->config['settings']['installment_commission'] ?? '';
            if(!$rates) return false;
            $lines      = explode("\n",$rates);
            $new_rate   = [];

            if($lines)
            {
                foreach($lines AS $line)
                {
                    $column = explode(" : ",$line);
                    $new_rate[$column[0]] = $column[1];
                }
            }

            return $new_rate;
        }

        public function capture($params=[])
        {
            $paymentMethod      = __CLASS__;
            $invoiceId          = $this->checkout_id;
            $amount             = $params["amount"];
            $currency           = $params["currency"];
            $callback           = $this->links["callback"];

            $configuration = new Payu\Configuration();
            $configuration->setMerchantId($this->config['settings']['merchant_id'])->setSecretKey($this->config['settings']['secret_key'])->setPaymentEndpointUrl("https://secure.payu.com.tr/order/alu/v3")->setPaymentReturnPointUrl($callback);
            $client = new Payu\Client($configuration);
            $installment = $params['installment'] ?? 1;
            $month = $params['expiry_m'];
            $year = "20" .$params['expiry_y'];
            $request = $client->createPaymentRequestBuilder()->buildCard($params["num"], $params["cvc"], $month, $year)->buildOrder($invoiceId, UserManager::GetIP(), $installment, $currency)->buildBilling($this->clientInfo->name, $this->clientInfo->surname, $this->clientInfo->email, $this->clientInfo->phone)->buildAndAddProduct("WISECP", "INVOICE-" . $invoiceId, 1, NULL, $amount)->build();
            $response = $client->makePayment($request);
            if ($response->getStatus() == Payu\Response\ResponseAbstract::STATUS_UNAUTHORIZED) {
                return [
                    'status' => '3d',
                    'redirect' => $response->getUrl3DS(),
                ];
            }
            else {
                if ($response->getStatus() == Payu\Response\ResponseAbstract::STATUS_APPROVED) {
                    Modules::save_log("Payment",__CLASS__,"capture",false,$response,$response->getStatus());
                    logTransaction($paymentMethod, $response, $response->getStatus());
                    return  [
                        'status' => "successful",
                        'message' => [
                            'Transaction ID' => $response->getTransactionId(),
                        ],
                    ];
                } else {
                    Modules::save_log("Payment",__CLASS__,"capture",false,["Code" => $response->getCode(), "Message" => $response->getMessage()],"Unsuccessful");
                    return [
                        'status' => "error",
                        'message' => $response->getMessage(),
                    ];
                }
            }
        }

        public function callback()
        {
            if(!Filter::isPOST()){
                $this->error = "POST failed";
                return false;
            }

            $response = (new Payu\Parser\PaymentResponseParser())->parse($_POST);
            $invoiceId = $_POST["ORDER_REF"];

            $checkout           = $this->get_checkout($invoiceId);

            if(!$checkout)
            {
                $this->error = "Checkout not found";
                return false;
            }

            $this->set_checkout($checkout);

            Modules::save_log("Payment",__CLASS__,"callback",false,$_POST,$response->getStatus());

            if ($response->getStatus() == Payu\Response\ResponseAbstract::STATUS_APPROVED) {
                $transactionId = $response->getTransactionId();

                return  [
                    'status'        => "successful",
                    'message'       => [
                        'Transaction ID' => $transactionId,
                    ],
                ];

            }
        }
    }