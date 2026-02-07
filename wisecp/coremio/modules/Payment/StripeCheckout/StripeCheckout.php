<?php
    class StripeCheckout extends PaymentGatewayModule
    {
        function __construct()
        {
            $this->name             = __CLASS__;

            parent::__construct();
        }

        public function config_fields()
        {
            return [
                'api_key'          => [
                    'name'              => "API Key",
                    'description'       => "You can find API information <a target='_blank' href='https://dashboard.stripe.com/account/apikeys'>here</a>.",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["api_key"] ?? '',
                    'placeholder'       => "pk_live_**********",
                ],
                'secret_key'       => [
                    'name'              => "Secret Key",
                    'description'       => "You can find API information <a target='_blank' href='https://dashboard.stripe.com/account/apikeys'>here</a>.",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["secret_key"] ?? '',
                    'placeholder'       => "sk_live_**********",
                ],
                'wh_secret_key'    => [
                    'name'              => "Signing Secret",
                    'description'       => "You can find Webhook information <a target='_blank' href='https://dashboard.stripe.com/test/webhooks'>here</a>.",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["wh_secret_key"] ?? '',
                    'placeholder'       => "whsec_**********",
                ],
                'methods'          => [
                    'type'              => "text",
                    'name'              => "Payment Methods for One Time Payment",
                    'description'       => "Identify acceptable payment methods, you can separate them with commas. (e.g: card,ideal,p24) ",
                    'value'             => $this->config["settings"]["methods"] ?? 'card',
                ],
                'methods_subscription' => [
                    'type'              => "text",
                    'name'              => "Payment Methods for Subscriptions",
                    'description'       => "Identify acceptable payment methods, you can separate them with commas. (e.g: card,ideal,p24) ",
                    'value'             => $this->config["settings"]["methods_subscription"] ?? '',
                ],
                'disable_subscription' => [
                    'type'              => "approval",
                    'name'              => "Disable Subscription Feature",
                    'description'       => "Tick to prevent initiating payment by subscription.",
                    'value'             => 1,
                    'checked'           => (boolean) (int) ($this->config["settings"]["disable_subscription"] ?? 0),

                ],
                'methods_info'          => [
                    'type'              => "info",
                    'name'              => "Payment Method Info",
                    'description'       => "",
                    'value'             =>
                        '<strong class="selectalltext" style="margin:5px; width: 20%;text-align:center;display: inline-block;border:dotted 1px #33CCCC; padding: 5px;">card</strong>'.
                        '<strong class="selectalltext" style="margin:5px; width: 20%;text-align:center;display: inline-block;border:dotted 1px #33CCCC; padding: 5px;">ideal</strong>'.
                        '<strong class="selectalltext" style="margin:5px; width: 20%;text-align:center;display: inline-block;border:dotted 1px #33CCCC; padding: 5px;">p24</strong>'.
                        '<strong class="selectalltext" style="margin:5px; width: 20%;text-align:center;display: inline-block;border:dotted 1px #33CCCC; padding: 5px;">bancontact</strong>'.
                        '<strong class="selectalltext" style="margin:5px; width: 20%;text-align:center;display: inline-block;border:dotted 1px #33CCCC; padding: 5px;">sepa_debit</strong>'.
                        '<strong class="selectalltext" style="margin:5px; width: 20%;text-align:center;display: inline-block;border:dotted 1px #33CCCC; padding: 5px;">sofort</strong>'.
                        '<strong class="selectalltext" style="margin:5px; width: 20%;text-align:center;display: inline-block;border:dotted 1px #33CCCC; padding: 5px;">eps</strong>'.
                        '<strong class="selectalltext" style="margin:5px; width: 20%;text-align:center;display: inline-block;border:dotted 1px #33CCCC; padding: 5px;">giropay</strong>'.
                        '<strong class="selectalltext" style="margin:5px; width: 20%;text-align:center;display: inline-block;border:dotted 1px #33CCCC; padding: 5px;">fpx</strong>'.
                        '<strong class="selectalltext" style="margin:5px; width: 20%;text-align:center;display: inline-block;border:dotted 1px #33CCCC; padding: 5px;">alipay</strong>'.
                        '<strong class="selectalltext" style="margin:5px; width: 20%;text-align:center;display: inline-block;border:dotted 1px #33CCCC; padding: 5px;">grabpay</strong>'.
                        '<strong class="selectalltext" style="margin:5px; width: 20%;text-align:center;display: inline-block;border:dotted 1px #33CCCC; padding: 5px;">wechat_pay</strong>'.
                        '<strong class="selectalltext" style="margin:5px; width: 20%;text-align:center;display: inline-block;border:dotted 1px #33CCCC; padding: 5px;">acss_debit</strong>'.
                        '<strong class="selectalltext" style="margin:5px; width: 20%;text-align:center;display: inline-block;border:dotted 1px #33CCCC; padding: 5px;">bacs_debit</strong>'.
                        '<strong class="selectalltext" style="margin:5px; width: 20%;text-align:center;display: inline-block;border:dotted 1px #33CCCC; padding: 5px;">afterpay_clearpay</strong>'.
                        '<strong class="selectalltext" style="margin:5px; width: 20%;text-align:center;display: inline-block;border:dotted 1px #33CCCC; padding: 5px;">oxxo</strong>'.
                        '<strong class="selectalltext" style="margin:5px; width: 20%;text-align:center;display: inline-block;border:dotted 1px #33CCCC; padding: 5px;">boleto</strong>'
                ],
                'event_types'          => [
                    'type'              => "info",
                    'name'              => "Webhook Event Types",
                    'description'       => "",
                    'value'             =>
                        '<strong style="margin:5px; width: 25%;text-align:center;display: inline-block;border:dotted 1px #33CCCC; padding: 5px;">checkout.session.completed</strong>'
                ],
                'sandbox'          => [
                    'name'              => "Test Mode",
                    'description'       => "Activate for test mode.",
                    'type'              => "approval",
                    'value'             => 1,
                    'checked'           => (bool) (int) $this->config["settings"]["sandbox"] ?? false,
                ],
            ];
        }

        public function area($params=[])
        {
            $sub_items             = $this->subscribable_items();
            $is_subscription       = Filter::init("REQUEST/is_subscription");

            if($this->config["settings"]["disable_subscription"]) $sub_items = [];

            $begin_subscribe        = $sub_items && $is_subscription == "Y";

            $methods                = $this->config['settings']['methods'] ?? 'card';
            $methods2               = $this->config['settings']['methods_subscription'] ?? $methods;
            if(!$methods2) $methods2 = $methods;
            $methods                = explode(",",$methods);
            $methods2               = explode(",",$methods2);


            if($begin_subscribe)
            {
                $methods = $methods2;
                foreach($methods AS $k => $v)
                {
                    if($v == "giropay" || $v == "p24") unset($methods[$k]);
                }
                $methods = array_values($methods);
            }

            $output                = '';

            if(Utility::strlen($is_subscription) == 0 && $sub_items)
            {
                $info       = $this->page_request_info();
                $url        = $info["address"];
                $p_data     = $info["data"];

                $pref       = stristr($url,'?') ? "&" : "?";

                $url_1      = $url.$pref.($p_data ? http_build_query($p_data)."&"  : "")."is_subscription=Y";
                $url_2      = $url.$pref.($p_data ? http_build_query($p_data)."&"  : "")."is_subscription=N";

                $output     .= '<div align="center">';

                $output     .= '<a class="lbtn yuzde20" href="'.$url_1.'">'.$this->lang["tx1"].'</a>';

                $output     .= '<span style="display:inline-block;width: 10%; text-align: center;">'.$this->lang["tx3"].'</span>';

                $output     .= '<a class="lbtn yuzde20" href="'.$url_2.'">'.$this->lang["tx2"].'</a>';



                $output .= '</div>';
            }
            else
            {
                $output = 'Redirecting...';

                try {
                    $stripe         = $this->stripe_init();

                    $line_items  = [];
                    $subscribed_items = [];

                    if($begin_subscribe)
                    {
                        $subscription_total      = 0;
                        $detect_period           = $sub_items[0]['period'];

                        foreach($sub_items AS $i)
                        {
                            if($detect_period != $i['period']) continue;

                            $exchange           = Money::exChange($i['tax_included'],$i['currency'],$params['currency']);
                            $subscription_total += $exchange;


                            $product    = $stripe->products->create([
                                'name'      => $i['name'],
                                'metadata'  => [
                                    'identifier' => $i['identifier'],
                                    'type'          => $i['product_type'],
                                    'id'            => $i['product_id'],
                                    'option_id'     => $i['option_id'] ?? 0,
                                    'period'        => $i['period'],
                                    'period_time'   => $i['period_time'],
                                ],
                            ]);

                            $price      = $stripe->prices->create([
                                'product' => $product->id,
                                'unit_amount_decimal' => $exchange * 100,
                                'currency' => strtolower($params['currency']),
                                'recurring' => [
                                    'interval'          => $i['period'],
                                    'interval_count'    => $i['period_time'],
                                ],
                            ]);

                            $line_items[] = [
                                'quantity' => 1,
                                'price' => $price->id,
                            ];
                            $subscribed_items[] = $i["identifier"];
                        }

                        $except_recurring = round($params['amount'],2) - round($subscription_total,2);

                        /*
                        throw new Exception(json_encode([
                            'except' => $except_recurring,
                            'amount' => round($params['amount'],2),
                            'amounts' => round($subscription_total,2),
                        ]));
                        */

                        $coupon         = NULL;

                        if($except_recurring > 0.00)
                        {
                            $product    = $stripe->products->create([
                                'name'      => 'Except Recurring Amount',
                            ]);
                            $price      = $stripe->prices->create([
                                'product' => $product->id,
                                'unit_amount' => round($except_recurring * 100),
                                'currency' => strtolower($params['currency']),
                            ]);
                            $line_items[] = [
                                'quantity' => 1,
                                'price' => $price->id
                            ];
                        }
                        elseif($except_recurring < -0.00)
                        {
                            $discount_amount = abs($except_recurring);

                            $coupon = $stripe->coupons->create([
                                'amount_off'    => round($discount_amount * 100),
                                'currency'      => strtolower($params['currency']),
                                'duration'      => 'once',
                            ]);
                        }


                        $session_data       = [
                            'line_items'                => $line_items,
                            'payment_method_types'      => $methods,
                            'mode'                      => 'subscription',
                            'success_url'               => $this->links["successful"],
                            'cancel_url'                => $this->links["failed"],
                            'metadata'                  => [
                                'checkout_id'           => $this->checkout_id,
                            ],
                            'allow_promotion_codes'     => false,
                        ];

                        if($coupon) $session_data['discounts'] =  [['coupon' => $coupon->id]];


                        $session = $stripe->checkout->sessions->create($session_data);

                        header("Location: ".$session->url);
                        $this->save_custom_data(['subscribed_items' => $subscribed_items]);
                    }
                    else
                    {
                        $_items      = $this->getItems();

                        if($_items)
                        {
                            foreach($_items AS $_item)
                            {
                                $addons_name    = '';
                                $addon_list     = [];

                                if($addons = $_item["options"]["addon_items"] ?? ($_item["addon_items"] ?? []))
                                {
                                    foreach($addons AS $addon_item)
                                    {
                                        $addon_list[] = $addon_item["name"];
                                    }
                                }
                                if($addon_list) $addons_name = " + ".implode(", ", $addon_list);

                                $product    = $stripe->products->create([
                                    'name'      => Utility::substr($_item["name"].$addons_name,0,245),
                                ]);
                                $amount     = $_item['amount_including_discount'];
                                if($amount > 0.00)
                                    $amount += ($_item["adds_amount"] ?? 0);
                                $amount2    = $_item['total_amount'];
                                if($amount < 0.01 && $amount2 > 0.00) $amount = $amount2;

                                $amount     = Money::exChange($amount,$this->checkout["data"]["currency"],$params['currency']);
                                $price      = $stripe->prices->create([
                                    'product' => $product->id,
                                    'unit_amount_decimal' => round($amount,2) * 100,
                                    'currency' => strtolower($params['currency']),
                                ]);
                                $line_items[] = [
                                    'quantity' => 1,
                                    'price' => $price->id
                                ];
                            }
                        }

                        $tax        = round($this->checkout["data"]["tax"] ?? 0,2);
                        $comm       = round($this->checkout["data"]["pmethod_commission"] ?? 0,2);

                        if($tax > 0.00)
                        {
                            $tax        = Money::exChange($tax,$this->checkout["data"]["currency"],$params['currency']);
                            $product    = $stripe->products->create([
                                'name'      => Utility::substr(Bootstrap::$lang->get("needs/tax"),0,245),
                            ]);
                            $price      = $stripe->prices->create([
                                'product' => $product->id,
                                'unit_amount_decimal' => round($tax,2) * 100,
                                'currency' => strtolower($params['currency']),
                            ]);
                            $line_items[] = [
                                'quantity' => 1,
                                'price' => $price->id
                            ];
                        }

                        if($comm > 0.00 &&  $this->checkout["type"] == "basket")
                        {
                            $comm        = Money::exChange($comm,$this->checkout["data"]["currency"],$params['currency']);
                            $product    = $stripe->products->create([
                                'name'      => Utility::substr(Bootstrap::$lang->get_cm("website/basket/payment-method-commission"),0,245),
                            ]);
                            $price      = $stripe->prices->create([
                                'product' => $product->id,
                                'unit_amount_decimal' => round($comm,2) * 100,
                                'currency' => strtolower($params['currency']),
                            ]);
                            $line_items[] = [
                                'quantity' => 1,
                                'price' => $price->id
                            ];
                        }

                        $session = $stripe->checkout->sessions->create([
                            'line_items'                => $line_items,
                            'payment_method_types'      => $methods,
                            'mode'                      => 'payment',
                            'success_url'               => $this->links["successful"],
                            'cancel_url'                => $this->links["failed"],
                            'metadata'                  => [
                                'checkout_id'           => $this->checkout_id,
                            ],
                        ]);
                        header("Location: ".$session->url);
                    }
                }
                catch(Exception $e){
                    return 'Error: '.$e->getMessage();
                }
            }
            return $output;
        }

        public function callback()
        {
            $endpoint_secret = $this->config['settings']['wh_secret_key'] ?? '';

            $payload            = @file_get_contents('php://input');
            $sig_header         = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
            $event              = null;

            try {
                $event = \Stripe\Webhook::constructEvent(
                    $payload, $sig_header, $endpoint_secret
                );
            } catch(\UnexpectedValueException $e) {
                // Invalid payload
                exit($e->getMessage());
            } catch(\Stripe\Exception\SignatureVerificationException $e) {
                // Invalid signature
                exit($e->getMessage());
            }

            http_response_code(200);


            switch ($event->type) {
                case 'checkout.session.completed':
                    return $this->session_completed($event->data->object);
                default:
                    echo 'Received unknown event type ' . $event->type;
            }

        }

        public function session_completed($obj)
        {
            $checkout_id        = $obj->metadata->checkout_id ?? 0;
            $subscription_id    = $obj->subscription ?? null;

            $checkout           = $this->get_checkout($checkout_id);

            if(!$checkout)
            {
                $this->error = 'Checkout not found';
                return false;
            }

            $this->set_checkout($checkout);


            $c_data                 = $this->get_custom_data();
            $subscribed_items       = $c_data["subscribed_items"] ?? [];
            $subscribed_payment     = false;

            if($subscription_id && $subscribed_items)
            {
                $subscribed = [];
                foreach($subscribed_items AS $s) $subscribed[$s] = $subscription_id;
                if($subscribed)
                {
                    $subscribed_payment = true;
                    $this->set_subscribed_items($subscribed);
                }
            }

            $return_msg = [
                'status'                    => 'successful',
                'callback_message'          => 'Transaction Successful',
            ];


            $stripe         = $this->stripe_init();


            if($obj->status != "complete")
            {
                $this->error = "Transaction Failed";
                return false;
            }

            $paymentIntent = $obj->payment_inten ?? false;

            if($paymentIntent)
            {
                $intent = $stripe->paymentIntents->retrieve($paymentIntent);
                $charge = $intent->charges->data[0];

                $transaction = $stripe->balanceTransactions->retrieve($charge->balance_transaction);
                $transaction_id = $transaction->id;
                $return_msg['message'] = ['Transaction ID' => $transaction_id];
            }

            return $return_msg;
        }

        public function get_subscription($params=[])
        {
            $sub_id             = $params["identifier"];

            $n_status           = 'active';

            $first_paid_date    = $params['created'];
            $first_paid_amount  = $params['first_paid_fee'];
            $first_paid_curr    = $this->currency($params['currency']);

            $last_paid_date     = $params['last_paid_date'];
            $last_paid_amount   = $params['last_paid_fee'];
            $last_paid_curr     = $this->currency($params['currency']);

            $next_payable_date     = $params['next_payable_date'];
            $next_payable_amount   = $params['next_payable_fee'];
            $next_payable_curr     = $this->currency($params['currency']);

            try {
                $stripe         = $this->stripe_init();

                $subscription   = $stripe->subscriptions->retrieve($sub_id);

                if($subscription->status == 'incomplete_expired')
                    $n_status = "expired";
                elseif($subscription->status == 'canceled')
                    $n_status = "cancelled";
                elseif($subscription->status == 'canceled')
                    $n_status = "cancelled";

                $first_invoice       = $stripe->invoices->all([
                    'subscription' => $subscription->id,
                    'status' => "paid",
                    'limit' => 3,
                    'created' => ['lte' => DateManager::strtotime(DateManager::format("Y-m-d",$params['created']))],
                ]);

                if(($first_invoice->data[0] ?? null))
                {
                    $first_invoice = $first_invoice->data[0];
                    $first_paid_amount      = round($first_invoice->total / 100,2);
                    $first_paid_curr        = strtoupper($first_invoice->currency);
                    $first_paid_date        = DateManager::timetostr("Y-m-d H:i",$first_invoice->status_transitions->paid_at);
                }


                $last_invoice       = $stripe->invoices->all([
                    'subscription' => $subscription->id,
                    'status' => "paid",
                    'limit' => 3,
                ]);

                if(($last_invoice->data[0] ?? null))
                {
                    $last_invoice = $last_invoice->data[0];
                    $last_paid_amount      = round($last_invoice->total / 100,2);
                    $last_paid_curr        = strtoupper($last_invoice->currency);
                    $last_paid_date        = DateManager::timetostr("Y-m-d H:i",$last_invoice->status_transitions->paid_at);
                }

                $next_payable_invoice       = $stripe->invoices->upcoming(['subscription' => $subscription->id]);

                if($next_payable_invoice)
                {
                    $next_payable_amount   = round($next_payable_invoice->total / 100,2);
                    $next_payable_curr     = strtoupper($next_payable_invoice->currency);
                    $next_payable_date     = DateManager::timetostr("Y-m-d H:i",$next_payable_invoice->next_payment_attempt);
                }

            }
            catch(Exception $e)
            {
                Modules::save_log("Payment",__CLASS__,"get_subscription",['subscription' => $sub_id],$e->getMessage());
            }

            return [
                'status'            => $n_status,
                'status_msg'        => '',
                'first_paid'        => [
                    'time'              => $first_paid_date,
                    'fee'               => [
                        'amount'    => $first_paid_amount,
                        'currency'  => $first_paid_curr,
                    ],
                ],
                'last_paid'         => [
                    'time'          => $last_paid_date,
                    'fee'           => [
                        'amount'    => $last_paid_amount,
                        'currency'  => $last_paid_curr,
                    ],
                ],
                'next_payable'      => [
                    'time'              => $next_payable_date,
                    'fee'               => [
                        'amount'            => $next_payable_amount,
                        'currency'          => $next_payable_curr,
                    ],
                ],
                'failed_payments'   => 0,
            ];

        }

        public function cancel_subscription($params=[])
        {
            $sub_id = $params["identifier"];

            try {
                $stripe = $this->stripe_init();

                $stripe->subscriptions->cancel($sub_id);
            }
            catch (Exception $e)
            {
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }

        public function refund($checkout=[])
        {
            $amount         = $checkout["data"]["total"];
            $currency       = $this->currency($checkout["data"]["currency"]);

            $force_curr     = $this->config["settings"]["force_convert_to"] ?? 0;
            if($force_curr > 0)
            {
                $amount         = Money::exChange($amount,$currency,$force_curr);
                $currency       = $this->currency($force_curr);
            }


            try
            {
                $stripe = $this->stripe_init();

                $findInvoice    = false;
                $invoice_ids    = [];
                if(isset($checkout["data"]["invoice_id"]))
                    $invoice_ids[] = $checkout["data"]["invoice_id"];
                if(isset($checkout["data"]["invoices"]))
                    $invoice_ids = array_merge($invoice_ids,$checkout["data"]["invoices"]);

                if($invoice_ids)
                {
                    foreach($invoice_ids AS $inv_id)
                    {
                        $find_inv = Invoices::get($inv_id);
                        if($find_inv)
                        {
                            $pmethod_msg    = Utility::jdecode($find_inv["pmethod_msg"],true);
                            if($pmethod_msg && isset($pmethod_msg['Transaction ID']))
                                $findInvoice = $pmethod_msg['Transaction ID'];
                        }
                    }
                }


                if(!$invoice_ids || !$findInvoice)
                {
                    $this->error = "Invoice Transaction ID cannot be found.";
                    return false;
                }


                $transaction        = $stripe->balanceTransactions->retrieve($findInvoice);
                $stripe->refunds->create(["charge" => $transaction->source, "amount" => $amount]);
            }
            catch (Exception $e) {
                $this->error = $e->getMessage();
                return false;
            }

            return true;
        }

        private function stripe_init():\Stripe\StripeClient
        {
            if($this->stripe !== NULL) return $this->stripe;
            $api_key        = $this->config['settings']["api_key"] ?? '';
            $secret_key     = $this->config['settings']["secret_key"] ?? '';

            $this->stripe = new \Stripe\StripeClient($secret_key);
            return $this->stripe;
        }

    }