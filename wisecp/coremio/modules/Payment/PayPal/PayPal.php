<?php

    class PayPal extends PaymentGatewayModule
    {
        private $client_obj;

        function __construct()
        {
            $this->name = __CLASS__;
            $this->payform = __DIR__ . DS . "pages" . DS . "payform";
            $this->links = [
                'subscription' => Controllers::$init->CRLink("payment", [__CLASS__, "function", "subscription"]),
                'direct'       => Controllers::$init->CRLink("payment", [__CLASS__, "function", "direct"]),
            ];

            $this->call_function['subscription'] = 'subscription';
            $this->call_function['direct'] = 'direct';
            $this->call_function['webhook'] = 'webhook';

            parent::__construct();
        }

        public function direct()
        {
            Helper::Load(["Money", "Products"]);

            $s_data = Session::get("PayPal_checkout", true);

            if (!$s_data) {
                echo 'Checkout data not found';
                return false;
            }


            $s_data = Utility::jdecode($s_data, true);
            $amount = $s_data["amount"];
            $currency = $s_data["currency"];
            $checkout = $s_data["checkout"];

            if (!$checkout) {
                echo 'Checkout data not found';
                return false;
            }

            $currency = $this->currency($currency);

            if ($currency == "HUF") Money::$digit = 0;

            $this->client()->version = "v2";

            $desc = '';

            $its = $this->getItems();
            if ($its) {
                $szits = sizeof($its) - 1;
                foreach ($its as $k => $i) {
                    $desc .= $i["description"] ?? ($i["name"] ?? '') . ($k == $szits ? '' : ', ');
                }
            }

            if (strlen($desc) == 0)
                $desc = "Checkout #" . $checkout["id"];

            /*
            [
                'purchase_units' => [
                    [
                        'reference_id'      => "checkout_".$checkout["id"],
                        'amount' => [
                            'currency'      => $this->currency($currency),
                            'total'         =>  (string) round($amount,2),
                        ],
                        'custom'         => $checkout["id"],
                    ]
                ],
                'redirect_urls' => [
                    'return_url'                    => Controllers::$init->CRLink("payment",['PayPal',$this->get_auth_token(),'callback']),
                    'cancel_url'                    => $checkout["data"]["redirect"]["failed"],
                ],

            ]
                */
            $create_order = $this->client()->call("checkout/orders", [

                "intent"              => "CAPTURE",
                "purchase_units"      => [[
                    "reference_id" => "checkout_" . $checkout["id"],
                    "description"  => Utility::substr($desc, 0, 125),
                    "amount"       => [
                        "value"         => (string)round($amount, Money::$digit),
                        "currency_code" => $currency,
                    ],
                    'custom_id'    => $checkout["id"],
                ]],
                "application_context" => [
                    'return_url' => Controllers::$init->CRLink("payment", ['PayPal', $this->get_auth_token(), 'callback']),
                    'cancel_url' => $checkout["data"]["redirect"]["failed"],
                ],


            ], 'POST');

            if ($this->client()->error) {
                echo 'Error: ' . $this->client()->error;
                return false;
            }


            $links = $create_order["links"] ?? [];
            $approve_link = false;

            if ($links) {
                foreach ($links as $k => $v) {
                    if ($v["rel"] == "approve") $approve_link = $v["href"];
                }
            }

            Utility::redirect($approve_link);

            echo 'Redirecting...';
        }

        public function subscription()
        {

            Helper::Load(["Money", "Products"]);

            $s_data = Session::get("PayPal_checkout", true);

            if (!$s_data) {
                echo 'Checkout data not found';
                return false;
            }


            $s_data = Utility::jdecode($s_data, true);
            $amount = $s_data["amount"];
            $currency = $s_data["currency"];
            $checkout = $s_data["checkout"];


            if (!$checkout) {
                echo 'Checkout data not found';
                return false;
            }

            $currency = $this->currency($currency);

            if ($currency == "HUF") Money::$digit = 0;


            if (!isset($checkout["data"]["subscribable"]) || !$checkout["data"]["subscribable"]) {
                echo 'There are no products to subscribe to.';
                return false;
            }

            $subscribable = $checkout["data"]["subscribable"];

            $subscribed_fee = 0;
            $period_type = '';
            $period_time = 1;
            $subscribed = [];

            foreach ($subscribable as $i) {
                if (!$period_type) {
                    $period_type = $i["period"];
                    $period_time = $i["period_time"];
                }

                if ($i["period"] == $period_type && $i["period_time"] == $period_time) {
                    $subscribed_fee += Money::exChange($i["tax_included"], $i["currency"], $currency);
                    $subscribed[$i["identifier"]] = "";
                }
            }

            $api_products = $this->client()->call_all('catalogs/products');

            if ($api_products && isset($api_products["products"]) && sizeof($api_products["products"]) > 0) {
                $new_api_products = [];
                foreach ($api_products["products"] as $k => $v) $new_api_products[$v["id"]] = $v["name"];
                $api_products = $new_api_products;
            }

            $start_time = DateManager::next_date([
                $period_type => $period_time,
            ], "c");

            $product = current($subscribable);

            if (!$api_product_id = array_search($product["name"], $api_products)) {
                $api_create_product = $this->client()->call('catalogs/products', [
                    'name'        => $product["name"],
                    'description' => $product["name"],
                    'type'        => "SERVICE",
                ], 'POST');
                if (!$api_create_product) {
                    echo 'Create Product Error: ' . $this->client()->error;
                    return false;
                }
                $api_product_id = $api_create_product["id"];
            }


            $api_create_plan = $this->client()->call('billing/plans', [
                'product_id'          => $api_product_id,
                'name'                => $product["name"],
                'billing_cycles'      => [
                    [
                        'frequency'      => [
                            'interval_unit'  => strtoupper($period_type),
                            'interval_count' => $period_time,
                        ],
                        'tenure_type'    => "REGULAR",
                        'sequence'       => 1,
                        'total_cycles'   => 0,
                        'pricing_scheme' => [
                            'fixed_price' => [
                                'value'         => (string)round($subscribed_fee, Money::$digit),
                                'currency_code' => $this->cid_convert_code($currency),
                            ],
                        ],
                    ],
                ],
                'payment_preferences' => [
                    'auto_bill_outstanding' => true,

                    'setup_fee'                 => [
                        'value'         => (string)round($amount, Money::$digit),
                        'currency_code' => $this->cid_convert_code($currency),
                    ],
                    'payment_failure_threshold' => 10,
                ],
            ], 'POST');

            if (!$api_create_plan) {
                echo 'Create Plan Error: ' . $this->client()->error;
                return false;
            }

            $detect_locale = 'en-US';
            $lang = $checkout["data"]["user_data"]["lang"];
            $country_code = Bootstrap::$lang->get("package/country-code", $lang);
            if ($country_code) $detect_locale = $lang . "-" . $country_code;

            $api_plan_id = $api_create_plan["id"];

            $api_create_subscription = $this->client()->call('billing/subscriptions', [
                'plan_id'             => $api_plan_id,
                'start_time'          => $start_time,
                'subscriber'          => [
                    'name'          => [
                        'given_name' => $checkout["data"]["user_data"]["name"],
                        'surname'    => $checkout["data"]["user_data"]["surname"],
                    ],
                    'email_address' => $checkout["data"]["user_data"]["email"],
                ],
                'application_context' => [
                    'locale'              => $detect_locale,
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action'         => 'SUBSCRIBE_NOW',
                    'payment_method'      => [
                        'payer_selected'  => 'PAYPAL',
                        'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED',
                    ],
                    'return_url'          => Controllers::$init->CRLink("payment", ['PayPal', $this->get_auth_token(), 'callback']),
                    'cancel_url'          => $checkout["data"]["redirect"]["failed"],
                ],
                'custom_id'           => $checkout["id"],
            ], 'POST');

            if (!$api_create_subscription) {
                echo 'Create Subscription Error: ' . $this->client()->error;
                return false;
            }

            $sub_id = $api_create_subscription["id"];
            $links = $api_create_subscription["links"] ?? [];
            $approve_link = false;

            if ($links) {
                foreach ($links as $k => $v) {
                    if ($v["rel"] == "approve") $approve_link = $v["href"];
                }
            }

            if (!$approve_link) {
                echo 'Error: Unable to get confirmation link';
                return false;
            }

            foreach ($subscribed as $k => $v) $subscribed[$k] = $sub_id;

            $checkout["data"]["subscribed"] = $subscribed;

            Basket::set_checkout($checkout["id"], ['data' => Utility::jencode($checkout["data"])]);

            Utility::redirect($approve_link);

            echo 'Redirecting...';
        }

        public function client()
        {
            if (!$this->client_obj) {
                if (!class_exists("PayPalClient")) include __DIR__ . DS . "client.php";
                $client_id = '';
                $client_secret = '';
                $sandbox = false;
                if (isset($this->config["settings"]["client_id"]))
                    $client_id = $this->config["settings"]["client_id"];

                if (isset($this->config["settings"]["secret_key"]))
                    $client_secret = $this->config["settings"]["secret_key"];

                if (isset($this->config["settings"]["sandbox"]))
                    $sandbox = $this->config["settings"]["sandbox"];

                $this->client_obj = new PayPalClient($client_id, $client_secret, $sandbox);
            }
            return $this->client_obj;
        }

        public function get_auth_token()
        {
            $syskey = Config::get("crypt/system");
            $token = md5(Crypt::encode("PayPal-Auth-Token=" . $syskey, $syskey));
            return $token;
        }

        public function set_checkout($checkout)
        {
            $this->checkout_id = $checkout["id"];
            $this->checkout = $checkout;
        }

        public function commission_fee_calculator($amount)
        {
            $rate = $this->config["settings"]["commission_rate"] ?? 0;
            $calculate = Money::get_discount_amount($amount, $rate);
            return $calculate;
        }

        public function get_commission_rate()
        {
            return $this->config["settings"]["commission_rate"];
        }

        public function cid_convert_code($id = 0)
        {
            Helper::Load("Money");
            $currency = Money::Currency($id);
            if ($currency) return $currency["code"];
            return false;
        }

        public function get_ip()
        {
            if (isset($_SERVER["HTTP_CLIENT_IP"])) {
                $ip = $_SERVER["HTTP_CLIENT_IP"];
            } elseif (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
                $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
            } else {
                $ip = $_SERVER["REMOTE_ADDR"];
            }
            return $ip;
        }

        public function processing()
        {
            $return_data = [];
            $checkout = $this->checkout;
            $amount = $checkout["data"]["total"];
            $currency = $checkout["data"]["currency"];
            $convert_to = 0;

            if (isset($this->config["settings"]["convert_to"]) && $this->config["settings"]["convert_to"] > 0)
                $convert_to = $this->config["settings"]["convert_to"];

            if ($convert_to == 0 && $currency == 147 && Money::Currency(4)) $convert_to = 4;

            if ($convert_to) {
                $amount = Money::exChange($amount, $currency, $convert_to);
                $currency = $convert_to;
            }
            $currency_code = $this->cid_convert_code($currency);

            $subscribable = [];

            if (isset($checkout["data"]["subscribable"]) && $checkout["data"]["subscribable"])
                $subscribable = $checkout["data"]["subscribable"];


            if ($subscribable) $return_data["show_pay_by_subscription"] = true;

            Session::set("PayPal_checkout", Utility::jencode([
                'checkout' => $checkout,
                'amount'   => $amount,
                'currency' => $currency,
            ]), true);

            $return_data['amount'] = $amount;
            $return_data['currency'] = $currency_code;

            return $return_data;
        }

        public function payment_result()
        {
            $payer_email = Filter::init("POST/payer_email", "email");
            $item_number = (int)Filter::init("POST/item_number", "numbers");
            $mc_fee = Filter::init("POST/mc_fee", "amount");
            $mc_gross = Filter::init("POST/mc_gross", "amount");
            $mc_currency = Filter::init("POST/mc_currency", "letters");
            $payment_status = Filter::init("POST/payment_status");
            $txn_id = Filter::init("POST/txn_id");
            $sub_id = Filter::init("GET/subscription_id", "route");
            $sub_id = substr($sub_id, 0, 150);
            $token = Filter::init("GET/token", "route");
            $payer_id = Filter::init("GET/PayerID", "route");

            // Subscription Payment
            if ($sub_id) {
                $checkout = [];

                $this->callback_type = "client-sided";
                $subscription = $this->client()->call('billing/subscriptions/' . $sub_id);

                if (!$subscription) {
                    $this->error = $this->client()->error;
                }

                if (isset($subscription["custom_id"]) && $subscription["custom_id"]) {
                    $checkout = Basket::get_checkout($subscription["custom_id"]);
                    if (!$checkout) {
                        $this->error = Bootstrap::$lang->get("errors/error6", Config::get("general/local"));
                        $subscription = false;
                    }
                }

                if ($subscription && !in_array($subscription['status'], ['APPROVED', 'ACTIVE'])) {
                    $this->error = 'Subscription status must be active. (' . $subscription["status"] . ')';
                }

                if ($this->error)
                    return [
                        'status'     => "ERROR",
                        'return_msg' => $this->error,
                    ];

                $this->set_checkout($checkout);

                Basket::set_checkout($checkout["id"], ['status' => "paid"]);

                return [
                    'status'     => "SUCCESS",
                    'message'    => [
                        'subscription_id' => $sub_id,
                    ],
                    'checkout'   => $checkout,
                    'subscribed' => $checkout["data"]["subscribed"] ?? [],
                ];

            } // Order Payment
            elseif ($token && $payer_id) {
                $this->client()->version = "v2";

                $this->callback_type = "client-sided";

                $response = $this->client()->call("checkout/orders/" . $token);


                if (in_array($response['status'], ['APPROVED', 'COMPLETED'])) {
                    $order_id = $response["id"];
                    if ($order_id) {
                        $pay = $this->client()->call("checkout/orders/" . $order_id . "/capture", [], 'POST');
                        if (!$pay) {
                            $this->error = $this->client()->error;
                            return false;
                        }
                    } else {
                        $this->error = "Order id not found";
                        return false;
                    }

                    $custom_id = $response["purchase_units"][0]["custom_id"] ?? 0;

                    $capture_id = isset($pay) ? $pay["purchase_units"][0]["payments"]["captures"][0]["id"] ?? 0 : 0;

                    $checkout = Basket::get_checkout($custom_id);
                    if (!$checkout)
                        return [
                            'status'     => "ERROR",
                            'status_msg' => Bootstrap::$lang->get("errors/error6", Config::get("general/local")),
                            'return_msg' => Bootstrap::$lang->get("errors/error6", Config::get("general/local")),
                        ];

                    $this->set_checkout($checkout);

                    Basket::set_checkout($checkout["id"], ['status' => "paid"]);

                    return [
                        'status'   => "SUCCESS",
                        'message'  => [
                            'order_id'   => $order_id,
                            'capture_id' => $capture_id,
                        ],
                        'checkout' => $checkout,
                    ];
                }
            } // Non-Subscription Payment
            else {

                if (!$payer_email || !$item_number || !$mc_gross || !$mc_currency || !$payment_status || !$txn_id || !($payment_status == "Completed" || $payment_status == "Pending"))
                    return [
                        'status'     => "ERROR",
                        'status_msg' => Bootstrap::$lang->get("errors/error8", Config::get("general/local")),
                        'return_msg' => Bootstrap::$lang->get("errors/error8", Config::get("general/local")),
                    ];

                $checkout = Basket::get_checkout($item_number);
                if (!$checkout)
                    return [
                        'status'     => "ERROR",
                        'status_msg' => Bootstrap::$lang->get("errors/error6", Config::get("general/local")),
                        'return_msg' => Bootstrap::$lang->get("errors/error6", Config::get("general/local")),
                    ];

                if (Invoices::search_pmethod_msg('"txn_id":"' . $txn_id . '"'))
                    return [
                        'checkout'   => $checkout,
                        'status'     => "ERROR",
                        'status_msg' => Bootstrap::$lang->get("errors/error8", Config::get("general/local")),
                        'return_msg' => Bootstrap::$lang->get("errors/error8", Config::get("general/local")),
                    ];

                $convert_to = (int)$this->config["settings"]["convert_to"] ?? 0;

                if ($convert_to > 0) $mc_currency = $convert_to;

                $current_amount = Money::exChange($checkout["data"]["total"], $checkout["data"]["currency"], $mc_currency);
                $current_amount = round($current_amount, Money::$digit);
                $mc_gross = round($mc_gross, Money::$digit);

                if ($mc_gross < $current_amount)
                    return [
                        'checkout'   => $checkout,
                        'status'     => "ERROR",
                        'status_msg' => Bootstrap::$lang->get("errors/error6", Config::get("general/local")),
                        'return_msg' => Bootstrap::$lang->get("errors/error6", Config::get("general/local")),
                    ];


                $this->set_checkout($checkout);

                Basket::set_checkout($checkout["id"], ['status' => "paid"]);

                return [
                    'status'     => $payment_status == "Completed" ? "SUCCESS" : "PAPPROVAL",
                    'checkout'   => $checkout,
                    'status_msg' => Utility::jencode([
                        'first_name'  => Filter::init("POST/first_name", "noun"),
                        'last_name'   => Filter::init("POST/last_name", "noun"),
                        'payer_email' => $payer_email,
                        'txn_id'      => $txn_id,
                        'mc_fee'      => $mc_fee,
                    ]),
                    'return_msg' => "OK",
                ];
            }

        }

        public function get_subscription($params = [])
        {
            $sub_id = $params["identifier"];
            $request = $this->client()->call("billing/subscriptions/" . $sub_id);

            if (!$request) {
                $this->error = $this->client()->error;
                return false;
            }

            $status = 'pending';

            if ($request['status'] == 'APPROVED')
                $status = 'approved';
            elseif ($request['status'] == 'ACTIVE')
                $status = 'active';
            elseif ($request['status'] == 'SUSPENDED')
                $status = 'suspended';
            elseif ($request['status'] == 'CANCELLED')
                $status = 'cancelled';
            elseif ($request['status'] == 'EXPIRED')
                $status = 'expired';


            $start_time = substr($request["start_time"], 0, -1);
            $start_time_s = explode("T", $start_time);
            $start_time = $start_time_s[0] . " " . $start_time_s[1];

            $next_time = substr($request["billing_info"]["next_billing_time"], 0, -1);
            $next_time_s = explode("T", $next_time);
            $next_time = $next_time_s[0] . " " . $next_time_s[1];

            $last_payment_time = substr($request["billing_info"]["last_payment"]["time"], 0, -1);
            $last_payment_time_s = explode("T", $last_payment_time);
            $last_payment_time = $last_payment_time_s[0] . " " . $last_payment_time_s[1];


            $first_paid_amount = $params["first_paid_fee"];
            $first_paid_currency = $this->cid_convert_code($params["currency"]);
            $next_payable_amount = $params["next_payable_fee"];
            $next_payable_currency = $this->cid_convert_code($params["currency"]);

            $plan = $this->client()->call("billing/plans/" . $request["plan_id"]);

            if ($plan) {
                foreach ($plan["billing_cycles"] as $bc) {
                    if ($bc["tenure_type"] == "TRIAL") {
                        $first_paid_amount = $bc["pricing_scheme"]["fixed_price"]["value"];
                        $first_paid_currency = $bc["pricing_scheme"]["fixed_price"]["currency_code"];
                    }
                    if ($bc["tenure_type"] == "REGULAR") {
                        $next_payable_amount = $bc["pricing_scheme"]["fixed_price"]["value"];
                        $next_payable_currency = $bc["pricing_scheme"]["fixed_price"]["currency_code"];
                    }
                }
            }

            $status_msg = '';

            if (isset($request["status_change_note"]) && strlen($request["status_change_note"]) > 2)
                $status_msg = $request["status_change_note"];


            return [
                'status'          => $status,
                'status_msg'      => $status_msg,
                'first_paid'      => [
                    'time' => $start_time,
                    'fee'  => [
                        'amount'   => $first_paid_amount,
                        'currency' => $first_paid_currency,
                    ],
                ],
                'last_paid'       => [
                    'time' => $last_payment_time,
                    'fee'  => [
                        'amount'   => $request["billing_info"]["last_payment"]["amount"]["value"],
                        'currency' => $request["billing_info"]["last_payment"]["amount"]["currency_code"],
                    ],
                ],
                'next_payable'    => [
                    'time' => $next_time,
                    'fee'  => [
                        'amount'   => $next_payable_amount,
                        'currency' => $next_payable_currency,
                    ],
                ],
                'failed_payments' => $request["billing_info"]["failed_payments_count"],
            ];

        }

        public function cancel_subscription($params = [])
        {
            $sub_id = $params["identifier"];

            $this->client()->call("billing/subscriptions/" . $sub_id . "/cancel", [
                'reason' => 'Service Cancellation',
            ], 'POST');

            if ($this->client()->status_code == 204)
                return true;

            $this->error = $this->client()->error;

            if (stristr($this->error, 'resource does not exist')) {
                $this->error = null;
                return true;
            }
            return false;
        }

        public function change_subscription_fee($params = [], $value = 0, $currency = 0)
        {
            $convert_to = $this->config["settings"]["convert_to"] ?? 0;
            if ($currency == 147) $convert_to = 4;
            if ($convert_to > 0) {
                $value = Money::exChange($value, $currency, $convert_to);
                $currency = $convert_to;
            }

            $sub_id = $params["identifier"];

            $sub = $this->client()->call("billing/subscriptions/" . $sub_id);

            if (!$sub) {
                $this->error = $this->client()->error;
                return false;
            }

            $cycles = $sub["billing_info"]["cycle_executions"] ?? [];
            $last_cycle = $cycles ? end($cycles) : [];
            $sequence = $last_cycle ? ($last_cycle["sequence"] ?? 1) : 1;

            $set = [
                'pricing_schemes' => [
                    [
                        'billing_cycle_sequence' => $sequence,
                        'pricing_scheme'         => [
                            'fixed_price'       => [
                                'currency_code' => $this->cid_convert_code($currency),
                                'value'         => (string)round($value, Money::$digit),
                            ],
                            'roll_out_strategy' => [
                                'effective_time'      => DateManager::Now("c"),
                                'process_change_from' => 'NEXT_PAYMENT',
                            ],
                        ],
                    ],
                ],
            ];

            $this->client()->call("billing/plans/" . $sub["plan_id"] . "/update-pricing-schemes", $set, 'POST');

            if ($this->client()->status_code != 204) {
                $error = $this->client()->error ?: "Status code: " . $this->client()->status_code;

                if (stristr($error, 'The requested action could not be performed, semantically incorrect, or failed business validation')) return true;

                $this->error = $error;
                return false;
            }

            return true;
        }

        public function capture_subscription($params = [])
        {
            $convert_to = $this->config["settings"]["convert_to"];

            if ($params["currency"] == 147) $convert_to = 4;

            $amount = $params["next_payable_fee"];
            $curr = $params["currency"];

            if ($convert_to > 0) {
                $amount = Money::exChange($amount, $curr, $convert_to);
                $curr = $convert_to;
            }

            $set = [
                'note'         => 'Charging as the balance reached the limit',
                'capture_type' => 'OUTSTANDING_BALANCE',
                'amount'       => [
                    'currency_code' => $this->cid_convert_code($curr),
                    'value'         => $amount,
                ],
            ];

            $this->client()->call("billing/subscriptions/" . $params["identifier"] . "/capture", $set, 'POST');
            if ($this->client()->status_code == 202) return true;
            if ($this->client()->error) {
                $this->error = $this->client()->error;
                return false;
            }
            return true;
        }

        public function refund($checkout = [])
        {

            $amount = $checkout["data"]["total"];
            $currency = $this->currency($checkout["data"]["currency"]);
            $invoice_id = $checkout["data"]["invoice_id"] ?? 0;
            $invoice = Invoices::get($invoice_id);
            $method_msg = Utility::jdecode($invoice["pmethod_msg"] ?? [], true);
            $capture_id = $method_msg["capture_id"] ?? false;
            $force_curr = $this->config["settings"]["force_convert_to"] ?? 0;
            if ($force_curr > 0) {
                $amount = Money::exChange($amount, $currency, $force_curr);
                $currency = $this->currency($force_curr);
            }

            if ($currency == "HUF") Money::$digit = 0;

            $data = [
                'invoice_id' => $invoice["number"],

            ];

            $this->client()->version = "v2";

            $request = $this->client()->call("payments/captures/" . $capture_id . "/refund", $data, 'POST');

            if (!$request) {
                $this->error = $this->client()->error;
                return false;
            }

            return true;

        }

        public function testConnection($config = [])
        {
            $this->config = $config;
            if (file_exists(__DIR__ . DS . "auth.php")) FileManager::file_delete(__DIR__ . DS . "auth.php");

            $request = $this->client()->call("billing/plans");
            if (!$request && $this->client()->error) {
                $this->error = $this->client()->error;
                return false;
            }
            return true;
        }
    }