<?php
    defined('CORE_FOLDER') or exit('You can not get in here!');

    class Controller extends Controllers
    {
        protected $params, $data = [], $moduleProperties, $moduleClass;


        public function __construct($arg = [])
        {
            parent::__construct();
            $this->params = $arg['params'];

            if (!UserManager::LoginCheck("member")) {
                Utility::redirect($this->CRLink("sign-in"));
                die();
            }

            $udata = UserManager::LoginData("member");
            if ($redirect_link = User::full_access_control_account($udata)) {
                Utility::redirect($redirect_link);
                die();
            }

            $this->moduleProperties = Modules::Load("Payment", "Balance");
            if (!$this->moduleProperties || !$this->moduleProperties["config"]["settings"]["status"]) {
                $this->main_404();
                die();
            }
            $this->moduleClass = new Balance();
        }


        private function operationMain($operation = '')
        {
            if ($operation == "update_settings") return $this->update_settings();
            if ($operation == "buy_credit") return $this->buy_credit();

            echo "Not found operation: " . $operation;
        }

        private function buy_credit()
        {
            $amount = (int)Filter::init("POST/amount", "numbers");
            $amount = (int)$amount;
            $currency = (int)Filter::init("POST/currency", "numbers");
            $convert = (int)Filter::init("POST/convert", "numbers");
            $confirm = (int)Filter::init("POST/confirm", "numbers");

            $this->takeDatas("language");

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'account'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }


            if (DEMO_MODE) {
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));
            }


            if ($amount < 1)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/balance/buy-error1"),
                ]));

            Helper::Load(["User", "Money", "Basket"]);
            $udata = UserManager::LoginData("member");
            $udata = array_merge($udata, User::getInfo($udata["id"], "dealership,default_address,identity"), User::getData($udata["id"], "balance,balance_currency,email,phone,full_name,ip", "array"));
            $ucid = $udata["balance_currency"];


            if (Config::get("options/blacklist/status"))
                if (Config::get("options/blacklist/order-blocking"))
                    if (User::checkBlackList($udata))
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/account/prohibited-alert"),
                        ]));


            if (Validation::check_prohibited($udata["email"], ['email', 'word']))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account/prohibited-alert"),
                ]));

            if (strlen($udata["phone"]) > 4 && Validation::check_prohibited($udata["phone"], ['gsm']))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account/prohibited-alert"),
                ]));


            $current_currency = Money::Currency($ucid, true);
            $new_currency = Money::Currency($currency, true);
            if (!$new_currency) die();
            if ($udata["balance"] && !$current_currency)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/balance/buy-error3"),
                ]));

            if (!$udata["default_address"])
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/balance/buy-error6"),
                ]));

            $address = AddressManager::getAddress($udata["default_address"], $udata["id"]);
            if (!$address)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/balance/buy-error6"),
                ]));


            if ($udata["dealership"]) {
                $dealership = Utility::jdecode($udata["dealership"], true);
                if ($dealership && $dealership["status"]) {
                    $min_credit_amount = $dealership["require_min_credit_amount"];
                    $min_credit_cid = $dealership["require_min_credit_cid"];
                    if ($min_credit_amount) {
                        $dealershipCheck = true;
                        $min_amount = Money::exChange($min_credit_amount, $min_credit_cid, $currency);
                        if ($min_amount && $amount < $min_amount) {
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => __("website/balance/buy-error2"),
                            ]));
                        }
                    }
                }
            }

            if (!isset($dealershipCheck) || !$dealershipCheck) {
                $min_credit_amount = $this->moduleProperties["config"]["settings"]["min-amount"];
                $min_credit_cid = $this->moduleProperties["config"]["settings"]["min-amount-cid"];
                $min_amount = Money::exChange($min_credit_amount, $min_credit_cid, $currency);
                if ($min_amount && $amount < $min_amount) {
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/balance/buy-error4", ['{amount}' => Money::formatter_symbol($min_amount, $currency)]),
                    ]));
                }
            }

            if ($udata["balance"] == 0) $convert = true;

            if ($udata["balance"] && $ucid != $currency && !$convert) {
                $ct_currency = $current_currency["name"] . " (" . $current_currency["code"] . ")";
                $nw_currency = $new_currency["name"] . " (" . $new_currency["code"] . ")";
                $current_credit = Money::formatter_symbol($udata["balance"], $ucid);
                $buy_credit = Money::formatter_symbol($amount, $currency);
                $new_credit = Money::exChange($udata["balance"], $ucid, $currency);

                die(Utility::jencode([
                    'status'           => "convert",
                    'current_currency' => $ct_currency,
                    'new_currency'     => $nw_currency,
                    'current_credit'   => $current_credit,
                    'buy_credit'       => $buy_credit,
                    'new_credit'       => Money::formatter_symbol($new_credit, $currency),
                ]));
            }

            if ($ucid != $currency && $convert) {
                $new_credit = Money::exChange($udata["balance"], $udata["balance_currency"], $currency);
                User::setData($udata["id"], [
                    'balance_currency' => $currency,
                    'currency'         => $currency,
                    'balance'          => $new_credit,
                ]);
                Money::setCurrency($currency);
                $udata["balance_currency"] = $currency;
                $udata["balance"] = $new_credit;
                $ucid = $currency;
            }
            Helper::Load(["Invoices"]);

            if (!$confirm)
                die(Utility::jencode([
                    'status' => "confirm",
                ]));

            $loaded_amount = $amount;

            $taxRate = Invoices::getTaxRate($address["country_id"], $address["city"], $udata["id"]);
            $taxation_type = Invoices::getTaxationType();

            $btxn = Config::get("options/balance-taxation");
            if (!$btxn) $btxn = "y";


            if($taxation_type == "inclusive" && $taxRate > 0 && $btxn == "y") {
                $amount -= Money::get_inclusive_tax_amount($amount, $taxRate);
                $loaded_amount = $amount;
            }


            $detail = [
                'user_id' => $udata["id"],
                'status'  => "unpaid",
                'pmethod' => "none",
                'amount'  => $amount,
                'cid'     => $ucid,
            ];

            if ($btxn == "n") {
                $detail["taxation_type"] = "exclusive";
                $detail["tax_rate"] = 0;
                $detail["legal"] = 0;
            }


            $items = [
                [
                    'process'  => false,
                    'name'     => __("website/balance/basket-account-credit"),
                    'user_pid' => 0,
                    'options'  => [
                        'event'      => "addCredit",
                        'event_data' => [
                            'user_id'  => $udata["id"],
                            'amount'   => $loaded_amount,
                            'currency' => $ucid,
                        ],
                    ],
                ],
            ];

            $generate = Invoices::bill_generate(
                $detail,
                $items
            );

            if (!$generate && Invoices::$message == "no-user-address")
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/balance/buy-error5"),
                ]));
            elseif (!$generate)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "I have a problem.",
                ]));


            echo Utility::jencode([
                'status'   => "successful",
                'redirect' => $this->CRLink("ac-ps-detail-invoice", [$generate["id"]]),
            ]);


        }


        private function update_settings()
        {
            $this->takeDatas("language");

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'account'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            $autopbc = Filter::init("POST/auto_payment_by_credit", "rnumbers");
            $balance_min = Filter::init("POST/balance_min", "numbers");
            if ($autopbc != 0 && $autopbc != 1) $autopbc = 0;

            $isChanges = 0;

            Helper::Load(["User", "Money"]);
            $udata = UserManager::LoginData("member");
            $udata = array_merge($udata, User::getInfo($udata["id"], "auto_payment_by_credit"), User::getData($udata["id"], "balance,balance_min,balance_currency", "array"));

            if ($balance_min != $udata["balance_min"] && strlen($balance_min) < 10) {
                $balance_min = $balance_min ? Money::deformatter($balance_min, $udata["balance_currency"]) : 0;
                $update = User::setData($udata["id"], ['balance_min' => $balance_min]);
                if ($update) $isChanges += 1;
            }

            if ($autopbc != $udata["auto_payment_by_credit"]) {
                $update = User::setInfo($udata["id"], ['auto_payment_by_credit' => $autopbc]);
                if ($update) $isChanges += 1;
            }

            if ($isChanges) User::addAction($udata["id"], "alteration", "update-balance-settings");

            echo Utility::jencode(['status' => "successful", 'changes' => $isChanges]);

        }


        public function main()
        {
            $operation = Filter::init("POST/operation", "route");
            if ($operation) return $this->operationMain($operation);

            $this->addData("pname", "account_balance");
            $this->takeDatas([
                "sign-all",
                "language",
                "lang_list",
                "newsletter",
                "contacts",
                "socials",
                "header_menus",
                "footer_menus",
                "home_link",
                "canonical_link",
                "favicon_link",
                "header_logo_link",
                "footer_logo_link",
                "header_type",
                "meta_color",
                "footer_logos",
                "account_header_info",
                "account_sidebar_links",
            ]);

            $this->addData("page_type", "account");
            $this->addData("meta", __("website/balance/meta"));
            $this->addData("header_title", __("website/balance/page-title"));

            $breadcrumb = [
                [
                    'link'  => $this->CRLink("my-account"),
                    'title' => __("website/account/breadcrumb-dashboard"),
                ],
                [
                    'link'  => null,
                    'title' => __("website/balance/breadcrumb-balance"),
                ],
            ];
            $this->addData("panel_breadcrumb", $breadcrumb);
            $this->addData("links", [
                'controller' => $this->CRLink("ac-ps-balance"),
            ]);

            Helper::Load(["User", "Money"]);
            $udata = UserManager::LoginData("member");
            $udata = array_merge($udata, User::getData($udata["id"], "balance_currency,balance,balance_min", "array"), User::getInfo($udata["id"], "pay_latest_balance,auto_payment_by_credit"));

            $address = AddressManager::getAddress(0, $udata["id"]);
            $udata = array_merge($udata, User::getData($udata["id"], "name,surname,full_name,company_name,email", "array"));

            $udata["address"] = $address;

            $visibility_balance = false;

            $balanceModule = Modules::Load("Payment", "Balance", true);
            if ($balanceModule) $visibility_balance = $balanceModule["config"]["settings"]["status"];

            $this->addData("visibility_balance", $visibility_balance);


            $symbol = Money::getSymbol($udata["balance_currency"]);
            if ($udata["pay_latest_balance"])
                $pay_latest_balance = DateManager::format(Config::get("options/date-format") . " H:i", $udata["pay_latest_balance"]);
            $autoPaybyCredit = $udata["auto_payment_by_credit"] ? 1 : 0;
            $balance_min = $udata["balance_min"] > 0 ? explode(".", $udata["balance_min"])[0] : null;
            $this->addData("currency", $symbol);
            $this->addData("user_balance", Money::formatter_symbol($udata["balance"], $udata["balance_currency"]));
            $this->addData("udata", $udata);
            $this->addData("pay_latest_balance", isset($pay_latest_balance) ? $pay_latest_balance : false);
            $this->addData("auto_payment_by_credit", $autoPaybyCredit);
            $this->addData("balance_min", $balance_min);
            $this->addData("visibility_auto_payment", $this->moduleProperties["config"]["settings"]["auto-payment"]);

            $this->view->chose("website")->render("ac-balance", $this->data);

        }
    }