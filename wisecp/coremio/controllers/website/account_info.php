<?php
    defined('CORE_FOLDER') or exit('You can not get in here!');

    class Controller extends Controllers
    {
        protected $params, $data = [];
        public $timezone;


        public function __construct($arg = [])
        {
            parent::__construct();
            $this->params = $arg['params'];
            if (!UserManager::LoginCheck("member") && Filter::POST("operation") != "getCities" && Filter::POST("operation") != "getCounties") {
                Utility::redirect($this->CRLink("sign-in"));
                die();
            }

            Helper::Load("User");

            $this->timezone = User::getLastLoginZone();

        }


        private function tool_array_to_xml($data, &$xml_data)
        {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    if (is_numeric($key)) {
                        $key = 'item' . $key; //dealing with <0/>..<n/> issues
                    }
                    $subnode = $xml_data->addChild($key);
                    $this->tool_array_to_xml($value, $subnode);
                } else {
                    $xml_data->addChild("$key", htmlspecialchars("$value"));
                }
            }
        }


        private function getAddresses($id = 0, $name = null)
        {
            return AddressManager::getAddressesList($id, $name);
        }


        private function verifyEmail($_vcode = '')
        {
            if (!$_vcode) $this->takeDatas("language");
            $send = (int)Filter::init("POST/send", "numbers");
            $vcode = (int)Filter::init("POST/code", "numbers");
            if ($_vcode) $vcode = $_vcode;

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            if (!defined("DISABLE_CSRF") && !$_vcode) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'account'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }


            $sdata = UserManager::LoginData("member");
            $data1 = User::getData($sdata["id"], "id,name,surname,full_name,email", "array");
            $data2 = User::getInfo($sdata["id"], ['verified-email'], "array");
            $udata = array_merge($data1, $data2);

            if ($udata["verified-email"]) die("Your email address is already verified.");
            if (!Config::get("options/sign/up/email/verify")) die("Error #1");

            $checking_limit = Config::get("options/sign/up/email/verify_checking_limit");
            $checking_bte = Config::get("options/blocking-times/email-verify");
            $sending_limit = 1;
            $sending_bte = ['hour' => 1];

            $verifyM = $udata["email"];
            $code = $this->model->get_verify_code($udata["id"], "email", $verifyM);

            if ($send) { // is SEND  - START
                if ($code && User::CheckBlocked("verify-code-send-email", $udata["id"], [
                        'ip'    => UserManager::GetIP(),
                        'email' => $udata["email"],
                    ]))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_info/verify-code-send-blocking", ['{blocking-time}' => DateManager::str_expression($sending_bte)]),
                    ]));

                Helper::Load(["Notification"]);

                $code = rand(1000, 9999);
                $sending = Notification::email_activation($udata["id"], $code);

                if ($sending == "OK") {
                    if ($sending_limit != 0) {
                        $total_sending = LogManager::getLogCount("verify-code-email");
                        $total_sending++;
                        LogManager::setLogCount("verify-code-email", $total_sending);

                        if ($total_sending == $sending_limit && current($sending_bte)) {
                            User::addBlocked("verify-code-send-email", $udata["id"], [
                                'ip'    => UserManager::GetIP(),
                                'email' => $udata["email"],
                            ], DateManager::next_date($sending_bte));
                            LogManager::deleteLogCount("verify-code-email");
                        }
                    }

                    die(Utility::jencode([
                        'status'  => "sent",
                        'message' => __("website/account_info/verify-code-email-sent", ['{email}' => $udata["email"]]),
                    ]));

                } else {
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_info/verify-invalid-send"),
                    ]));
                }
            } // is Send  - END

            if ($verifyM != $udata["email"])
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_info/send-verification-code"),
                ]));

            if (Validation::isEmpty($vcode) || $vcode == 0)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "#email_verify_code",
                    'message' => __("website/account_info/verify-code-email-empty", ['{email}' => $udata["email"]]),
                ]));


            if (User::CheckBlocked("verify-code-check-email", $udata["id"], [
                'ip'    => UserManager::GetIP(),
                'email' => $udata["email"],
            ]))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_info/verify-code-check-blocking", ['{blocking-time}' => DateManager::str_expression($checking_bte)]),
                ]));

            if ($vcode != $code) {

                if ($checking_limit != 0) {
                    $attempt = LogManager::getLogCount("verify-code-email-attempt");
                    $attempt++;

                    LogManager::setLogCount("verify-code-email-attempt", $attempt);

                    if ($attempt == $checking_limit) {

                        User::addBlocked("verify-code-check-email", $udata["id"], [
                            'ip'    => UserManager::GetIP(),
                            'email' => $udata["email"],
                        ], DateManager::next_date($checking_bte));
                        LogManager::deleteLogCount("verify-code-email-attempt");
                    }
                }

                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_info/invalid-verify-code"),
                ]));
            }

            if ($vcode == $code) {
                LogManager::deleteLogCount("verify-code-email-attempt");
                LogManager::deleteLogCount("verify-code-email");
                User::setInfo($udata["id"], ["verified-email" => $udata["email"]]);

                $client_data = array_merge((array)User::getData($udata["id"],
                    [
                        'id',
                        'name',
                        'surname',
                        'full_name',
                        'company_name',
                        'email',
                        'phone',
                        'currency',
                        'lang',
                        'country',
                    ], "array"), User::getInfo($udata["id"],
                    [
                        'company_tax_number',
                        'company_tax_office',
                        'gsm_cc',
                        'gsm',
                        'landline_cc',
                        'landline_phone',
                        'identity',
                        'kind',
                        'taxation',
                    ]));
                $client_data["address"] = AddressManager::getAddress(0, $udata["id"]);
                $client_data["source"] = "client";

                Hook::run("ClientEmailVerificationCompleted", $client_data);


                if ($_vcode)
                    return true;
                else
                    echo Utility::jencode(['status' => "successful"]);

            }

        }


        private function verifyGSM()
        {
            $this->takeDatas("language");
            $send = (int)Filter::init("POST/send", "numbers");
            $vcode = (int)Filter::init("POST/code", "numbers");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'account'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            $sdata = UserManager::LoginData("member");
            $data1 = User::getData($sdata["id"], "id,name,surname,full_name", "array");
            $data2 = User::getInfo($sdata["id"], ['verified-gsm', 'gsm', 'gsm_cc'], "array");
            $udata = array_merge($data1, $data2);
            $udata["phone"] = $udata["gsm_cc"] . $udata["gsm"];

            if ($udata["verified-gsm"]) die("Your gsm address is already verified.");
            if (!Config::get("options/sign/up/gsm/verify")) die("Error #1");

            $checking_limit = Config::get("options/sign/up/gsm/verify_checking_limit");
            $checking_bte = Config::get("options/blocking-times/gsm-verify");
            $sending_limit = 1;
            $sending_bte = ['hour' => 1];

            $verifyS = $udata["phone"];
            $code = $this->model->get_verify_code($udata["id"], "gsm", $verifyS);

            if ($send) { // is SEND  - START
                if ($code && User::CheckBlocked("verify-code-send-gsm", $udata["id"], [
                        'ip'    => UserManager::GetIP(),
                        'phone' => $udata["phone"],
                    ]))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_info/verify-code-send-blocking", ['{blocking-time}' => DateManager::str_expression($sending_bte)]),
                    ]));

                Helper::Load("Notification");

                $code = rand(1000, 9999);
                $sending = Notification::gsm_activation($udata["id"], $code);

                if ($sending == "OK") {
                    if ($sending_limit != 0) {
                        $total_sending = LogManager::getLogCount("verify-code-gsm");
                        $total_sending++;
                        LogManager::setLogCount("verify-code-gsm", $total_sending);

                        if ($total_sending == $sending_limit && current($sending_bte)) {
                            User::addBlocked("verify-code-send-gsm", $udata["id"], [
                                'ip'    => UserManager::GetIP(),
                                'phone' => $udata["phone"],
                            ], DateManager::next_date($sending_bte));
                            LogManager::deleteLogCount("verify-code-gsm");
                        }
                    }

                    die(Utility::jencode([
                        'status'  => "sent",
                        'message' => __("website/account_info/verify-code-gsm-sent", ['{number}' => "+" . $udata["phone"]]),
                    ]));
                } else {
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_info/verify-invalid-send"),
                    ]));
                }
            } // is Send  - END


            if ($verifyS != $udata["phone"])
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_info/send-verification-code"),
                ]));

            if (Validation::isEmpty($vcode) || $vcode == 0)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "#gsm_verify_code",
                    'message' => __("website/account_info/verify-code-gsm-empty", ['{number}' => "+" . $udata["phone"]]),
                ]));

            if (User::CheckBlocked("verify-code-check-gsm", $udata["id"], [
                'ip'    => UserManager::GetIP(),
                'phone' => $udata["phone"],
            ]))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_info/verify-code-check-blocking", ['{blocking-time}' => DateManager::str_expression($checking_bte)]),
                ]));

            if ($vcode != $code) {

                if ($checking_limit != 0) {
                    $attempt = LogManager::getLogCount("verify-code-gsm-attempt");
                    $attempt++;

                    LogManager::setLogCount("verify-code-gsm-attempt", $attempt);

                    if ($attempt == $checking_limit) {
                        User::addBlocked("verify-code-check-gsm", $udata["id"], [
                            'ip'    => UserManager::GetIP(),
                            'phone' => $udata["phone"],
                        ], DateManager::next_date($checking_bte));
                        LogManager::deleteLogCount("verify-code-gsm-attempt");
                    }
                }

                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_info/invalid-verify-code"),
                ]));
            }

            if ($vcode == $code) {
                LogManager::deleteLogCount("verify-code-gsm-attempt");
                LogManager::deleteLogCount("verify-code-gsm");
                User::setInfo($udata["id"], ["verified-gsm" => $udata["phone"]]);

                $client_data = array_merge((array)User::getData($udata["id"],
                    [
                        'id',
                        'name',
                        'surname',
                        'full_name',
                        'company_name',
                        'email',
                        'phone',
                        'currency',
                        'lang',
                        'country',
                    ], "array"), User::getInfo($udata["id"],
                    [
                        'company_tax_number',
                        'company_tax_office',
                        'gsm_cc',
                        'gsm',
                        'landline_cc',
                        'landline_phone',
                        'identity',
                        'kind',
                        'taxation',
                    ]));
                $client_data["address"] = AddressManager::getAddress(0, $udata["id"]);
                $client_data["source"] = "client";

                Hook::run("ClientSMSVerificationCompleted", $client_data);

                echo Utility::jencode(['status' => "successful"]);

            }
        }


        private function PostCenter()
        {
            $operation = Filter::init("POST/operation", "letters_numbers", "\_");
            $operation2 = Filter::init("GET/operation", "letters_numbers", "\_");

            if ($operation == "update_gdpr_status" && Config::get("options/gdpr-status"))
                return $this->update_gdpr_status();

            if ($operation2 == "download_personal_data_gdpr" && Config::get("options/gdpr-status"))
                return $this->download_personal_data_gdpr();

            if ($operation == "gdpr_request" && Config::get("options/gdpr-status"))
                return $this->gdpr_request();

            if ($operation == "SubmitDocumentVerification")
                return $this->SubmitDocumentVerification();
            if ($operation == "ModifyAccountInfo")
                return $this->ModifyAccountInfo();
            if ($operation == "verifyEmail")
                return $this->verifyEmail();
            if ($operation == "verifyGSM")
                return $this->verifyGSM();
            elseif ($operation == "ModifyPreferences")
                return $this->ModifyPreferences();
            elseif ($operation == "DeleteAddress")
                return $this->DeleteAddress();
            elseif ($operation == "addNewAddress")
                return $this->addNewAddress();
            elseif ($operation == "editAddress")
                return $this->editAddress();
            elseif ($operation == "ModifyPassword")
                return $this->ModifyPassword();
            elseif ($operation == "getCities")
                return $this->getCities();
            elseif ($operation == "getCounties")
                return $this->getCounties();
            elseif ($operation == "stored_card_remove")
                return $this->stored_card_remove();
            elseif ($operation == "stored_card_as_default")
                return $this->stored_card_as_default();
            elseif ($operation == "stored_card_auto_payment")
                return $this->stored_card_auto_payment();
            elseif ($operation == "updateDefaultAddress")
                return $this->updateDefaultAddress();
            else
                return false;
        }


        private function gdpr_request()
        {
            $this->takeDatas("language");

            if (DEMO_MODE) die(Utility::jencode(['status' => "error", 'message' => __("website/others/demo-mode-error")]));

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'account'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            $action = Filter::init("POST/action", "letters_numbers", "\-");

            if ($action == "remove" || $action == "anonymize")
                $this->gdpr_create_request($action);
            elseif ($action == "cancel")
                $this->gdpr_cancel_request();
        }


        private function gdpr_create_request($type = '')
        {
            $udata = UserManager::LoginData();

            $gdpr_request = $this->model->gdpr_request($udata["id"]);

            if ($gdpr_request && $gdpr_request["status"] != "cancelled")
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_info/gdpr-tx24"),
                ]));

            $this->model->create_gdpr_request([
                'user_id'    => $udata["id"],
                'type'       => $type,
                'created_at' => DateManager::Now(),
                'updated_at' => DateManager::Now(),
            ]);

            User::addAction($udata["id"], "added", "Created GDPR Request", ['type' => $type]);

            echo Utility::jencode(['status' => "successful"]);

            return true;
        }

        private function gdpr_cancel_request()
        {
            $udata = UserManager::LoginData();

            $gdpr_request = $this->model->gdpr_request($udata["id"]);

            if (!$gdpr_request)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Not found GDPR request",
                ]));

            $this->model->cancel_gdpr_request($gdpr_request["id"]);

            User::addAction($udata["id"], "alteration", "Cancelled GDPR Request");

            echo Utility::jencode(['status' => "successful"]);

            return true;
        }


        private function update_gdpr_status()
        {
            $this->takeDatas("language");

            if (DEMO_MODE) die(Utility::jencode(['status' => "error", 'message' => __("website/others/demo-mode-error")]));

            $status = (int)Filter::init("REQUEST/status", "numbers");

            $udata = UserManager::LoginData();
            if (!$udata) exit();

            $udata = array_merge($udata, User::getInfo($udata["id"], ['contract2']));

            if ($udata["contract2"] != $status && $status) {
                User::addAction($udata["id"], "alteration", "contract2-is-approved");
                User::setInfo($udata["id"], [
                    'contract2'            => 1,
                    'contract2_updated_at' => DateManager::Now(),
                ]);
            } elseif ($udata["contract2"] != $status && !$status) {
                User::setInfo($udata["id"], [
                    'contract2'            => 0,
                    'contract2_updated_at' => DateManager::Now(),
                ]);
            }


            echo Utility::jencode(['status' => "successful"]);

        }


        private function download_personal_data_gdpr()
        {
            $this->takeDatas("language");

            if (DEMO_MODE) die(Utility::jencode(['status' => "error", 'message' => __("website/others/demo-mode-error")]));

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("GET/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'account'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            $udata = UserManager::LoginData();
            if (!$udata) exit();

            $timezone = User::getLastLoginZone("member", $udata["id"]);

            $udata = array_merge(
                User::getData($udata["id"], [
                    'id',
                    'full_name',
                    'company_name',
                    'phone',
                    'email',
                    'currency',
                    'balance',
                    'balance_currency',
                    'lang',
                ], 'array'),
                User::getInfo($udata["id"], [
                    'contract2',
                    'company_tax_office',
                    'company_tax_number',
                    'birthday',
                ])
            );

            Helper::Load(["Money", "Invoices"]);

            $curr_i = Money::Currency($udata["currency"]);
            $balance_curr_i = Money::Currency($udata["balance_currency"]);


            $data = [];
            $xml_data = new SimpleXMLElement('<?xml version="1.0"?><data></data>');


            $data["details"] = [
                'id'                 => $udata["id"],
                'type'               => $udata["kind"] == "individual" ? "Individual" : "Corporate",
                'full_name'          => $udata["full_name"],
                'email'              => $udata["email"],
                'phone'              => $udata["phone"] ? "+" . $udata["phone"] : '',
                'company_name'       => $udata["company_name"],
                'company_tax_number' => $udata["company_tax_number"],
                'company_tax_office' => $udata["company_tax_office"],
                'language'           => $udata["lang"],
            ];


            if ($curr_i) $data["details"]["currency"] = $curr_i["code"];
            if ($balance_curr_i) $data["details"]["balance_currency"] = $balance_curr_i["code"];

            $data["details"]["balance"] = round($udata["balance"], 4);


            if ($udata["birthday"]) $data["details"]["birthday"] = $udata["birthday"];

            $address_list = $this->getAddresses($udata["id"], $udata["company_name"] ? $udata["company_name"] : $udata["full_name"]);

            if ($address_list) {
                $data["details"]["addresses"] = [];
                foreach ($address_list as $k => $a) {
                    $a = AddressManager::getAddress($a["id"]);

                    $data["details"]["addresses"][] = [
                        'default'       => $a["detouse"] == 1 ? "YES" : "NO",
                        'country_A2ISO' => $a["country_code"],
                        'country_name'  => $a["country_name"],
                        'city'          => $a["counti"],
                        'state'         => $a["city"],
                        'address'       => $a["address"],
                        'postal_code'   => $a["zipcode"],
                    ];
                }
            }

            $invoices = $this->model->db->select()->from("invoices");
            $invoices->where("user_id", "=", $udata["id"], "&&");
            $invoices->where("status", "=", "paid");
            $invoices->order_by("id ASC");
            $invoices = $invoices->build() ? $invoices->fetch_assoc() : [];

            if ($invoices) {
                $data["invoices"] = [
                    'total' => sizeof($invoices),
                    'items' => [],
                ];

                foreach ($invoices as $inv) {
                    $currency = Money::Currency($inv["currency"]);
                    $items = Invoices::get_items($inv["id"]);
                    $calculate = Invoices::calculate_invoice($inv, $items, [
                        'discount_total' => true,
                    ]);

                    $data_items = [];

                    foreach ($items as $it) {
                        $data_items[] = [
                            'description' => $it["description"],
                            'taxexempt'   => $it["taxexempt"],
                            'amount'      => round($it["total_amount"], 4),
                            'currency'    => $currency["code"],
                        ];
                    }

                    $invoice_data = [
                        'id'            => $inv["id"],
                        'public_id'     => $inv["number"] ? $inv["number"] : "#" . $inv["id"],
                        'creation_date' => UserManager::formatTimeZone($inv["cdate"], $timezone, "c"),
                        'due_date'      => UserManager::formatTimeZone($inv["duedate"], $timezone, "c"),
                        'paid_date'     => $inv["status"] == "paid" ? UserManager::formatTimeZone($inv["datepaid"], $timezone, "c") : '',
                        'taxation_type' => $inv["taxation_type"],
                        'pmethod'       => $inv["pmethod"],
                        'sendbta'       => round($inv["sendbta_amount"], 4),
                        'pmethod_com'   => round($inv["pmethod_commission"], 4),
                        'subtotal'      => round($inv["subtotal"], 4),
                        'tax_rate'      => round($inv["taxrate"], 4),
                        'tax'           => round($inv["tax"], 4),
                        'discounts'     => round($calculate["discount_total"]),
                        'total'         => round($inv["total"], 4),
                        'currency'      => $currency["code"],
                        'items'         => $data_items,
                    ];

                    $data["invoices"]["items"][] = $invoice_data;
                }
            }

            $services = $this->model->db->select()->from("users_products");
            $services->where("owner_id", "=", $udata["id"]);
            $services->order_by("id ASC");
            $services = $services->build() ? $services->fetch_assoc() : [];


            if ($services) {
                $data["services"] = [];

                foreach ($services as $ser) {
                    $opt = Utility::jdecode($ser["options"], true);

                    $currency = Money::Currency($ser["amount_cid"]);

                    $ser_data = [
                        'id'              => $ser["id"],
                        'name'            => $ser["name"],
                        'group'           => $opt["group_name"] ?? 'N/A',
                        'category'        => $opt["category_name"] ?? '',
                        'status'          => $ser["status"],
                        'creation_date'   => UserManager::formatTimeZone($ser["cdate"], $timezone, "c"),
                        'renewal_date'    => UserManager::formatTimeZone($ser["renewaldate"], $timezone, "c"),
                        'due_date'        => $ser["period"] != "none" ? UserManager::formatTimeZone($ser["duedate"], $timezone, "c") : '',
                        'period'          => $ser["period"] == "none" ? "onetime" : $ser["period"],
                        'period_duration' => $ser["period_time"] > 0 ? $ser["period_time"] : 1,
                        'amount'          => round($ser["amount"], 4),
                        'currency'        => $currency["code"],
                    ];

                    if (isset($opt["domain"]) && $opt["domain"]) $ser_data["extras"]["domain"] = $opt["domain"];
                    if (isset($opt["ip"]) && $opt["ip"]) $ser_data["extras"]["ip"] = $opt["ip"];
                    if (isset($opt["code"]) && $opt["code"]) $ser_data["extras"]["code"] = $opt["code"];

                    $addons = Models::$init->db->select()->from("users_products_addons");
                    $addons->where("owner_id", "=", $ser["id"]);
                    $addons = $addons->build() ? $addons->fetch_assoc() : false;

                    if ($addons) {
                        $ser_data["addons"] = [];

                        foreach ($addons as $ad) {
                            $currency = Money::Currency($ad["cid"]);

                            $ser_data["addons"][] = [
                                'id'              => $ad["id"],
                                'name'            => $ad["addon_name"] . ($ad["option_quantity"] > 1 ? " " . $ad["option_quantity"] . "x" : '') . ($ad["option_name"] ? ": " . $ad["option_name"] : ''),
                                'status'          => $ser["status"],
                                'creation_date'   => UserManager::formatTimeZone($ad["cdate"], $timezone, "c"),
                                'renewal_date'    => UserManager::formatTimeZone($ad["renewaldate"], $timezone, "c"),
                                'due_date'        => $ad["period"] != "none" ? UserManager::formatTimeZone($ad["duedate"], $timezone, "c") : '',
                                'amount'          => $ad["amount"],
                                'period'          => $ad["period"] == "none" ? "onetime" : $ad["period"],
                                'period_duration' => $ad["period_time"] > 0 ? $ad["period_time"] : 1,
                                'currency'        => $currency["code"],
                            ];
                        }
                    }


                    $data["services"][] = $ser_data;
                }

            }

            $tickets = $this->model->db->select()->from("tickets");
            $tickets->where("user_id", "=", $udata["id"]);
            $tickets->order_by("id ASC");
            $tickets = $tickets->build() ? $tickets->fetch_assoc() : [];

            if ($tickets) {
                Helper::Load("Tickets");
                $data["tickets"] = [];
                foreach ($tickets as $tic) {
                    $replies = [];


                    $get_replies = Tickets::get_request_replies($tic["id"]);

                    if ($get_replies) {
                        foreach ($get_replies as $rep) {
                            $replies[] = [
                                'id'                  => $rep["id"],
                                'is_admin'            => $rep["admin"],
                                'creation_date'       => UserManager::formatTimeZone($rep["ctime"], $timezone, "c"),
                                'name'                => $rep["name"],
                                'ip'                  => $rep["admin"] ? '***HIDDEN***' : $rep["ip"],
                                'encrypted_msg'       => $rep["encrypted"],
                                'message_encode_type' => 'base64',
                                'message'             => $rep["encrypted"] ? "***ENCRYPTED MESSAGE***" : base64_encode($rep["message"]),

                            ];
                        }
                    }

                    $department = Tickets::get_department($tic["did"], $udata["lang"]);
                    $ticket_data = [
                        'id'              => $tic["id"],
                        'subject'         => $tic["title"],
                        'department'      => $department["name"],
                        'status'          => $tic["status"],
                        'priority'        => $tic["priority"],
                        'creation_date'   => UserManager::formatTimeZone($tic["ctime"], $timezone, "c"),
                        'last_reply_date' => UserManager::formatTimeZone($tic["lastreply"], $timezone, "c"),
                        'service_id'      => $tic["service"],
                        'replies'         => $replies,
                    ];

                    $data["tickets"][] = $ticket_data;
                }
            }


            $messages = $this->model->db->select()->from("mail_logs");
            $messages->where("user_id", "=", $udata["id"], "&&");
            $messages->where("private", "=", 0);
            $messages->order_by("id ASC");
            $messages = $messages->build() ? $messages->fetch_assoc() : [];

            if ($messages) {
                $data["messages"] = [];
                foreach ($messages as $mes) {
                    $content = $mes["content"];

                    if ($e_c = Crypt::decode($content, "*_LOG_*" . Config::get("crypt/system")))
                        $content = $e_c;

                    $mes_data = [
                        'id'                  => $mes["id"],
                        'subject'             => $mes["subject"],
                        'creation_date'       => UserManager::formatTimeZone($mes["ctime"], $timezone, "c"),
                        'address'             => $mes["addresses"],
                        'message_encode_type' => "base64",
                        'message'             => base64_encode($content),
                    ];

                    $data["messages"][] = $mes_data;
                }
            }

            $actions = $this->model->db->select()->from("users_actions");
            $actions->where("owner_id", "=", $udata["id"]);
            $actions->order_by("id ASC");
            $actions = $actions->build() ? $actions->fetch_assoc() : [];

            if ($actions) {
                $data["actions"] = [];
                foreach ($actions as $act) {
                    $detail = $act["detail"];
                    $act["data"] = $act["data"] == '' ? [] : Utility::jdecode($act["data"], true);
                    $description = User::action_desc($detail, $act["data"]);
                    if (!$description) $description = $detail;


                    $action_data = [
                        'id'            => $act["id"],
                        'creation_date' => UserManager::formatTimeZone($act["ctime"], $timezone, "c"),
                        'ip'            => $act["ip"],
                        'description'   => $description,
                    ];
                    $data["actions"][] = $action_data;
                }
            }

            $this->tool_array_to_xml($data, $xml_data);

            header('Content-disposition: attachment; filename="data.xml"');
            header('Content-type: "text/xml"; charset="utf8"');

            $domxml = new DOMDocument('1.0');
            $domxml->preserveWhiteSpace = false;
            $domxml->formatOutput = true;
            $domxml->loadXML($xml_data->asXML());

            echo $domxml->saveXML();

            User::addAction($udata["id"], "download", "GDPR personal data downloaded");
            User::setInfo($udata["id"], ['gdpr_downloaded_at' => DateManager::Now()]);

        }


        private function stored_card_remove()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error")])
                );

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'account'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            $sdata = UserManager::LoginData("member");
            $data1 = User::getData($sdata["id"], "id,lang", "array");
            $data2 = []; //User::getInfo($sdata["id"],[]);
            $udata = array_merge($data1, $data2);
            $uid = $udata["id"];
            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) exit("ID NOT FOUND");

            $get = $this->model->db->select("id,ln4,module")->from("users_stored_cards")->where("id", "=", $id, "&&")->where("user_id", "=", $uid);
            $get = $get->build() ? $get->getAssoc() : [];

            if (!$get) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Invalid ID",
                ]);
                return false;
            }

            $m_name = $get["module"];

            $m = Modules::Load("Payment", $m_name);

            if ($m && class_exists($m_name)) {
                $module = new $m_name();
                if (method_exists($module, 'remove_stored_card')) {
                    $apply = $module->pre_remove_stored_card($id);

                    if (!$apply) {
                        echo Utility::jencode([
                            'status'  => "error",
                            'message' => $module->error,
                        ]);
                        return false;
                    }
                }
            }

            $this->model->db->delete("users_stored_cards")->where("id", "=", $id)->run();
            $last_id = $this->model->db->select("id")->from("users_stored_cards")->where("user_id", "=", $uid);
            $last_id->order_by("id DESC");
            $last_id = $last_id->build() ? $last_id->getObject()->id : 0;

            if ($last_id) {
                $this->model->db->update("users_stored_cards", ["as_default" => "0"])->where("user_id", "=", $uid)->save();
                $this->model->db->update("users_stored_cards", ["as_default" => "1"])->where("id", "=", $last_id)->save();
            }

            User::addAction($uid, 'alteration', 'credit-card-was-deleted', ['ln4' => $get["ln4"]]);


            echo Utility::jencode(['status' => "successful"]);
        }

        private function stored_card_as_default()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));
            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'account'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            $sdata = UserManager::LoginData("member");
            $data1 = User::getData($sdata["id"], "id,lang", "array");
            $data2 = []; //User::getInfo($sdata["id"],[]);
            $udata = array_merge($data1, $data2);
            $uid = $udata["id"];
            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) exit("ID NOT FOUND");

            $get = $this->model->db->select()->from("users_stored_cards")->where("id", "=", $id, "&&")->where("user_id", "=", $uid);
            $get = $get->build() ? $get->getAssoc() : [];

            if (!$get) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Invalid ID",
                ]);
                return false;
            }

            $this->model->db->update("users_stored_cards", ["as_default" => 0])->where("user_id", "=", $uid)->save();

            $stmt = $this->model->db->update("users_stored_cards", ["as_default" => 1]);
            $stmt->where("user_id", "=", $uid, "&&");
            $stmt->where("id", "=", $id);
            $stmt = $stmt->save();

            User::addAction($uid, 'alteration', 'credit-card-is-set-as-default', ['ln4' => $get["ln4"]]);


            echo Utility::jencode(['status' => "successful"]);
        }

        private function stored_card_auto_payment()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'account'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            $sdata = UserManager::LoginData("member");
            $data1 = User::getData($sdata["id"], "id,lang", "array");
            $data2 = User::getInfo($sdata["id"], ["auto_payment"]);
            $udata = array_merge($data1, $data2);
            $uid = $udata["id"];
            $status = (int)Filter::init("POST/status", "numbers");

            $stored_cards = $this->model->db->select("id")->from("users_stored_cards")->where("user_id", "=", $uid);
            $stored_cards = $stored_cards->build();

            if (!$stored_cards) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_info/stored-cards-12"),
                ]);
                return false;
            }

            User::setInfo($uid, ['auto_payment' => $status]);

            $this->model->db->update("users_products", ["auto_pay" => "1"])->where("owner_id", "=", $uid)->save();

            User::addAction($uid, 'alteration', 'changed-auto-payment-with-credit-card-' . ($status ? "on" : "off"));

            echo Utility::jencode(['status' => "successful"]);
        }


        private function SubmitDocumentVerification()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'account'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            $sdata = UserManager::LoginData("member");
            $data1 = User::getData($sdata["id"], "id,lang", "array");
            $data2 = []; //User::getInfo($sdata["id"],[]);
            $udata = array_merge($data1, $data2);
            $documents = Filter::POST("documents");

            $verifications = User::RemainingVerifications($udata["id"]);

            if (!isset($verifications["document_filters"]) || !$verifications["document_filters"]) return false;

            foreach ($verifications["document_filters"] as $f_id => $filter) {
                $p_filter = isset($documents[$f_id]) ? $documents[$f_id] : [];
                $fields = $filter["fields"][$udata["lang"]];
                foreach ($fields as $f_k => $field) {
                    $f_type = $field["type"];
                    $p_field = isset($p_filter["fields"][$f_k]) ? $p_filter["fields"][$f_k] : false;
                    $p_file = Filter::FILES("documents-" . $f_id . "-fields-" . $f_k);

                    if (isset($field["record"]) && $field["record"]) $record = $field["record"];
                    else $record = false;

                    if ($record && $record["status"] != 'unverified') continue;

                    if (in_array($f_type, ["input", "textarea", 'select', 'radio']) && Validation::isEmpty($p_field))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "*[name='documents[" . $f_id . "][fields][" . $f_k . "]']",
                            'message' => __("website/account_info/error4", ['{field}' => $field["name"]]),
                        ]));

                    if ($f_type == "checkbox" && !$p_field)
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/account_info/error4", ['{field}' => $field["name"]]),
                        ]));

                    if ($f_type == "file" && !$p_file && !$record)
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "input[name='documents-" . $f_id . "-fields-" . $f_k . "']",
                            'message' => __("website/account_info/error4", ['{field}' => $field["name"]]),
                        ]));

                    if ($f_type == "checkbox" && $p_field && is_array($p_field)) {
                        foreach ($p_field as $k => $v) $p_field[$k] = Filter::html_clear($v);
                    }

                    if ($f_type == "file" && $p_file) {
                        $extensions = isset($field["allowed_ext"]) ? $field["allowed_ext"] : false;
                        if (!$extensions) $extensions = Config::get("options/product-fields-extensions");
                        $extensions = str_replace(" ", "", $extensions);
                        $max_file_size = isset($field["max_file_size"]) ? $field["max_file_size"] : 3;

                        $max_file_size = FileManager::converByte($max_file_size . "MB");

                        $upload_folder = RESOURCE_DIR . "uploads" . DS . "attachments" . DS;

                        Helper::Load("Uploads");
                        $upload = Helper::get("Uploads");
                        $upload->init($p_file, [
                            'date'          => false,
                            'multiple'      => false,
                            'max-file-size' => $max_file_size,
                            'folder'        => $upload_folder,
                            'allowed-ext'   => $extensions,
                            'file-name'     => "random",
                        ]);
                        if (!$upload->processed())
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "input[name='documents-" . $f_id . "-fields-" . $f_k . "']",
                                'message' => __("website/osteps/failed-field-upload", ['{error}' => $upload->error]),
                            ]));
                        if ($upload->operands) {
                            $p_field = $upload->operands[0];
                            if ($record["field_value"]) {
                                $r_f_v = Utility::jdecode($record["field_value"], true);
                                if (file_exists($upload_folder . $r_f_v["file_path"]))
                                    Filemanager::file_delete($upload_folder . $r_f_v["file_path"]);
                            }
                        }
                    }

                    if ($record) {

                        if ($f_type == "file" && !$p_file && $record["field_value"])
                            $p_field = Utility::jdecode($record["field_value"], true);

                        $this->model->db->update("users_document_records", [
                            'updated_at'  => DateManager::Now(),
                            'filter_id'   => $f_id,
                            'field_lang'  => $udata["lang"],
                            'field_key'   => $f_k,
                            'field_type'  => $f_type,
                            'field_name'  => $field["name"],
                            'field_value' => is_array($p_field) ? ($p_field ? Utility::jencode($p_field) : '') : Filter::html_clear($p_field),
                            'status'      => 'awaiting',
                            'status_msg'  => '',
                            'unread'      => '0',
                        ])->where("id", "=", $record["id"])->save();
                    } else {
                        $record = $this->model->db->insert("users_document_records", [
                            'created_at'  => DateManager::Now(),
                            'updated_at'  => DateManager::Now(),
                            'user_id'     => $udata["id"],
                            'filter_id'   => $f_id,
                            'field_lang'  => $udata["lang"],
                            'field_key'   => $f_k,
                            'field_type'  => $f_type,
                            'field_name'  => $field["name"],
                            'field_value' => is_array($p_field) ? ($p_field ? Utility::jencode($p_field) : '') : Filter::html_clear($p_field),
                            'status'      => 'awaiting',
                        ]);
                        $record_id = $this->model->db->lastID();
                        if (!$record_id)
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => "Failed to add record",
                            ]));
                        $record = $this->model->db->select()->from("users_document_records");
                        $record->where("id", "=", $record_id);
                        $record = $record->build() ? $record->getAssoc() : false;
                    }

                }
            }

            User::addAction($udata["id"], 'alteration', 'Verification form submitted');

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("website/account_info/success3"),
                'refresh' => true,
            ]);
        }


        private function ModifyAccountInfo()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'account'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            $sdata = UserManager::LoginData("member");
            $data1 = User::getData($sdata["id"], "id,full_name,email,lang,country,password", "array");
            $data2 = User::getInfo($sdata["id"], [
                'identity_required',
                'identity_checker',
                'birthday_required',
                'birthday_adult_verify',
                'birthday',
                'identity',
                'kind',
                'gsm',
                'gsm_cc',
                'landline_phone',
                'company_name',
                'company_tax_number',
                'company_tax_office',
                'verified-email',
                'verified-gsm',
                'security_question',
                'security_question_answer',
                'force_identity',
            ]);
            $udata = array_merge($data1, $data2);

            if ($udata["security_question"])
                $udata["security_question"] = Crypt::decode($udata["security_question"], Config::get("crypt/user"));

            if ($udata["security_question_answer"])
                $udata["security_question_answer"] = Crypt::decode($udata["security_question_answer"], Config::get("crypt/user"));


            $kind = Filter::init("POST/kind", "letters");
            $full_name = Filter::init("POST/full_name", "hclear");
            $full_name = Utility::substr($full_name, 0, 255);
            $full_name = Utility::ucfirst_space($full_name, ___("package/charset-code"));
            $email = Filter::init("POST/email", "email");
            $gsm = Filter::init("POST/gsm", "numbers");
            $landlinep = Filter::init("POST/landline_phone", "numbers");
            $identity = Filter::init("POST/identity", "identity");
            $birthday = Filter::init("POST/birthday", "numbers", "\/\-");
            $company_name = Filter::init("POST/company_name", "hclear");
            $company_taxnu = Filter::init("POST/company_tax_number", "letters_numbers", "-");
            $company_taxoff = Filter::init("POST/company_tax_office", "hclear");
            $security_question = Filter::init("POST/security_question", "hclear");
            $security_question_answer = Filter::init("POST/security_question_answer", "hclear");
            $security_question_answer = trim($security_question_answer);
            if ($udata["security_question_answer"])
                if (str_repeat("*", Utility::strlen($udata["security_question_answer"])) == $security_question_answer)
                    $security_question_answer = $udata["security_question_answer"];

            /*
            $cpw            = Filter::init("POST/current_password","password");
            if(User::crypt("member",$cpw) != $udata["password"])
                die(Utility::jencode([
                    'status' => "error",
                    'for' => "input[name=current_password]",
                    'message' => __("website/account_info/error2"),
                ]));
            */


            $identity_status = Config::get("options/sign/up/kind/individual/identity/status");
            $identity_required = Config::get("options/sign/up/kind/individual/identity/required");
            $identity_checker = Config::get("options/sign/up/kind/individual/identity/checker");
            $birthday_status = Config::get("options/sign/birthday/status");
            $birthday_required = Config::get("options/sign/birthday/required");
            $birthday_adult_verify = Config::get("options/sign/birthday/adult_verify");

            if ($udata["force_identity"]) {
                $identity_status = true;
                $identity_required = true;
                $identity_checker = true;
                $birthday_status = true;
                $birthday_required = true;
            }


            if ($udata['identity_required'] || $udata['identity_checker']) {
                $identity_status = 1;
                if ($udata['identity_required']) $identity_required = 1;
                if ($udata['identity_checker']) $identity_checker = 1;
            }
            if ($udata['birthday_required'] || $udata['birthday_adult_verify']) {
                $birthday_status = 1;
                if ($udata['birthday_required']) $birthday_required = 1;
                if ($udata['birthday_adult_verify']) $birthday_adult_verify = 1;
            }


            $cfields = Filter::POST("cfields");

            $editable = Config::get("options/sign/editable");

            $set_datas = [];
            $set_infos = [];

            $lline_status = Config::get("options/sign/up/landline-phone/status");
            $lline_required = Config::get("options/sign/up/landline-phone/required");
            $lline_editable = $editable["landline_phone"];

            $gsm_status = Config::get("options/sign/up/gsm/status");
            $gsm_required = Config::get("options/sign/up/gsm/required");
            $gsm_editable = $editable["gsm"];

            if ($editable["kind"]) {
                if ($kind == "individual" || $kind == "corporate") $set_infos["kind"] = $kind;
            } else $kind = $udata["kind"];

            if ($kind == "corporate") {
                if (Config::get("options/sign/up/kind/status")) {
                    if (Config::get("options/sign/up/kind/corporate/company_name/required") && Validation::isEmpty($company_name))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "#company_name",
                            'message' => __("website/sign/empty-custom-field", ['{name}' => __("website/account/field-company_name")]),
                        ]));
                    if (Config::get("options/sign/up/kind/corporate/company_tax_number/required") && Validation::isEmpty($company_taxnu))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "#company_tax_number",
                            'message' => __("website/sign/empty-custom-field", ['{name}' => __("website/account/field-company_tax_number")]),
                        ]));
                    if (Config::get("options/sign/up/kind/corporate/company_tax_office/required") && Validation::isEmpty($company_taxoff))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "#company_tax_office",
                            'message' => __("website/sign/empty-custom-field", ['{name}' => __("website/account/field-company_tax_office")]),
                        ]));
                }
                if ($company_name != $udata["company_name"]) $set_infos["company_name"] = $company_name;
                if ($company_taxnu != $udata["company_tax_number"]) $set_infos["company_tax_number"] = $company_taxnu;
                if ($company_taxoff != $udata["company_tax_office"]) $set_infos["company_tax_office"] = $company_taxoff;
            } else {
                if ($udata["company_name"]) $set_infos["company_name"] = '';
                if ($udata["company_tax_number"]) $set_infos["company_tax_number"] = '';
                if ($udata["company_tax_office"]) $set_infos["company_tax_office"] = '';
            }

            if (Config::get("options/sign/security-question/status")) {

                if (Config::get("options/sign/security-question/required") && Validation::isEmpty($security_question))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "#security_question",
                        'message' => __("website/sign/empty-custom-field", ['{name}' => __("website/account/field-security_question")]),
                    ]));

                if (Config::get("options/sign/security-question/required") && Validation::isEmpty($security_question_answer))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "#security_question_answer",
                        'message' => __("website/sign/empty-custom-field", ['{name}' => __("website/account/field-security_question_answer")]),
                    ]));

                if ($security_question != $udata["security_question"])
                    $set_infos["security_question"] = Crypt::encode($security_question, Config::get("crypt/user"));

                if ($security_question_answer != $udata["security_question_answer"])
                    $set_infos["security_question_answer"] = Crypt::encode($security_question_answer, Config::get("crypt/user"));
            }

            if ($editable["full_name"] && $full_name != $udata["full_name"]) {
                if (Validation::isEmpty($full_name))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='full_name']",
                        'message' => __("website/sign/up-submit-empty-full_name"),
                    ]));

                $smash = Filter::name_smash($full_name);
                $name = $smash["first"];
                $surname = $smash["last"];

                if (Validation::isEmpty($name) || Validation::isEmpty($surname))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='full_name']",
                        'message' => __("website/sign/up-submit-empty-full_name"),
                    ]));

                $set_datas["name"] = $name;
                $set_datas["surname"] = $surname;
                $set_datas["full_name"] = $full_name;
            } else
                $full_name = $udata["full_name"];


            $email_disabled = !$editable["email"];
            if (Config::get("options/sign/up/email/verify")) {
                if (!$udata["verified-email"]) $email_disabled = false;
                if (!$editable["email"] && User::ActionCount($udata["id"], "alteration", "changed-email-address")) $email_disabled = true;
            }

            if (!$email_disabled && $email != $udata["email"]) {
                if (Validation::isEmpty($email))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='email']",
                        'message' => __("website/sign/up-submit-empty-email"),
                    ]));

                if (!Validation::isEmail($email))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='email']",
                        'message' => __("website/sign/up-submit-invalid-email"),
                    ]));

                if (User::email_check($email, "member"))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='email']",
                        'message' => __("website/sign/up-submit-email-somebody-else"),
                    ]));
                $set_datas["email"] = $email;
            }


            if ($lline_status) {
                if ($udata["landline_phone"] != null && !$lline_editable) $landlinep = $udata["landline_phone"];
                if ($lline_required && Validation::isEmpty($landlinep))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='landline_phone']",
                        'message' => __("website/sign/up-submit-empty-landlinep"),
                    ]));

                if ($udata["landline_phone"] != $landlinep) {
                    if (Config::get("options/sign/up/landline-phone/checker") && User::landlinep_check($landlinep))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "input[name='landline_phone']",
                            'message' => __("website/sign/up-submit-landlinep-somebody-else"),
                        ]));
                    $set_infos["landline_phone"] = $landlinep;
                }
            }

            if ($gsm_status) {
                $gsm_disabled = !$editable["gsm"];
                if ($gsm_disabled && $udata["gsm"] == '') $gsm_disabled = false;
                if (Config::get("options/sign/up/gsm/verify")) {
                    if (!$udata["verified-gsm"]) $gsm_disabled = false;
                    if (!$editable["gsm"] && User::ActionCount($udata["id"], "alteration", "changed-gsm-number")) $gsm_disabled = true;
                }
                if (!$gsm_disabled && $gsm != $udata["gsm_cc"] . $udata["gsm"]) {
                    if ($gsm_required && Validation::isEmpty($gsm))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "#gsm",
                            'message' => __("website/sign/invalid-gsm-number"),
                        ]));

                    if (strlen($gsm) < 6) $gsm = '';

                    if (Config::get("options/sign/up/gsm/required") && !Validation::isPhone($gsm))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "#gsm",
                            'message' => __("website/sign/invalid-gsm-number"),
                        ]));

                    $gsm_parse = Filter::phone_smash($gsm);
                    $phone = $gsm;
                    $gsm_cc = $gsm_parse["cc"];
                    $gsm = $gsm_parse["number"];

                    if (Config::get("options/sign/up/gsm/checker") && User::gsm_check($gsm, $gsm_cc, "member"))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "#gsm",
                            'message' => __("website/sign/up-submit-gsm-somebody-else"),
                        ]));

                    $set_infos["gsm_cc"] = $gsm_cc;
                    $set_infos["gsm"] = $gsm;
                    $set_infos["phone"] = $phone;
                }
            }

            if ($birthday_status) {
                if ($udata["birthday"] != null && !$editable["birthday"]) $birthday = $udata["birthday"];
                if ($birthday_required && Validation::isEmpty($birthday))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='birthday']",
                        'message' => __("website/sign/up-birthday-empty"),
                    ]));
                $birthday = str_replace("/", "-", $birthday);

                if ($birthday) $birthday = DateManager::format("Y-m-d", $birthday);
                if ($udata["birthday"] != $birthday) $set_infos["birthday"] = $birthday;
            } else
                $birthday = $udata["birthday"];

            if ($identity_status && ($udata["force_identity"] || $udata["country"] == 227)) {
                if ($udata["identity"] != null && !$editable["identity"]) $identity = $udata["identity"];
                if ($identity_required && Validation::isEmpty($identity))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='identity']",
                        'message' => __("website/sign/empty-identity-number"),
                    ]));

                if ($udata["identity"] != $identity || $udata["birthday"] != $birthday || $udata["full_name"] != $full_name) {
                    if ($identity_checker) {

                        if ($birthday_status) {
                            if (Validation::isEmpty($birthday))
                                die(Utility::jencode([
                                    'status'  => "error",
                                    'for'     => "input[name='birthday']",
                                    'message' => __("website/sign/up-birthday-empty"),
                                ]));
                        }


                        $check = Validation::isidentity($identity, $full_name, $birthday);
                        if (!$check)
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "input[name='identity']",
                                'message' => __("website/sign/up-submit-invalid-identity"),
                            ]));

                        if ($udata["identity"] != $identity && User::identity_check($identity))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "input[name='identity']",
                                'message' => "T.C.K.N. bir başkası tarafından kullanılmaktadır.",
                            ]));
                    }
                    $set_infos["identity"] = $identity;
                }
            }

            if ($birthday_adult_verify && $birthday) {
                $age = DateTime::createFromFormat('Y-m-d', $birthday)->diff(new DateTime('now'))->y;
                if ($age < 18)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_info/error3"),
                    ]));
            }


            if ($cfields) {
                foreach ($cfields as $k => $v) {
                    $k = (int)Filter::numbers($k);
                    $f = $this->model->get_custom_field($k);
                    if ($f) {
                        $data = User::getInfo($udata["id"], ['field_' . $k]);
                        $value = $data['field_' . $k];
                        if ($value != null && $f["uneditable"]) $v = $value;

                        if ($f["required"] && Validation::isEmpty($v))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "#cfield_" . $k,
                                'message' => __("website/sign/empty-custom-field", ['{name}' => $f["name"]]),
                            ]));
                        $v = $f["type"] == "checkbox" && is_array($v) ? implode(",", $v) : $v;
                        $v = Filter::html_clear($v);
                        if ($v != $value) $set_infos['field_' . $k] = $v;
                    }
                }
            }


            if (
                Validation::check_prohibited($email, ['domain', 'email', 'word']) ||
                (isset($phone) && Validation::check_prohibited($phone, ['gsm']))
            )
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account/prohibited-alert"),
                ]));

            if ($set_infos || $set_datas) {

                $client_data = array_merge((array)User::getData($udata["id"],
                    [
                        'id',
                        'name',
                        'surname',
                        'full_name',
                        'company_name',
                        'email',
                        'phone',
                        'currency',
                        'lang',
                        'country',
                    ], "array"), User::getInfo($udata["id"],
                    [
                        'company_tax_number',
                        'company_tax_office',
                        'gsm_cc',
                        'gsm',
                        'landline_cc',
                        'landline_phone',
                        'identity',
                        'kind',
                        'taxation',
                    ]));
                $client_data["address"] = AddressManager::getAddress(0, $udata["id"]);
                $client_data["source"] = "admin";

                $h_ClientDetailsValidation = Hook::run("ClientDetailsValidation", $client_data);

                if ($h_ClientDetailsValidation) {
                    foreach ($h_ClientDetailsValidation as $item) {
                        if (isset($item["error"]) && $item["error"])
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => $item["error"],
                            ]));
                    }
                }

                if ($set_infos) User::setInfo($udata["id"], $set_infos);
                if ($set_datas) User::setData($udata["id"], $set_datas);

                if (isset($set_infos["gsm"])) {

                    User::addAction($udata["id"], "alteration", "changed-gsm-number", [
                        'before_gsm' => $udata["gsm_cc"] . $udata["gsm"],
                        'new_gsm'    => $gsm_cc . $gsm,
                    ]);

                    Helper::Load("Notification");
                    if (Config::get("options/sign/up/gsm/verify")) {
                        User::deleteInfo($udata["id"], "verified-gsm");
                        User::DeleteBlocked("verify-code-check-gsm", $udata["id"], [
                            'ip'    => UserManager::GetIP(),
                            'phone' => $udata["gsm_cc"] . $udata["gsm"],
                        ]);
                        User::DeleteBlocked("verify-code-send-gsm", $udata["id"], [
                            'ip'    => UserManager::GetIP(),
                            'phone' => $udata["gsm_cc"] . $udata["gsm"],
                        ]);
                        LogManager::deleteLogCount("verify-code-gsm-attempt");
                        LogManager::deleteLogCount("verify-code-gsm");
                        $code = rand(1000, 9999);
                        Notification::gsm_activation($udata["id"], $code);
                    }
                }

                if (isset($set_datas["email"])) {
                    UserManager::Logout("member");
                    $token = UserManager::Create_Login_Token($udata["id"], $email, $udata["password"]);
                    UserManager::Login("member", $udata["id"], $udata["password"], $udata["lang"], '-O@cb6Dxvarhc+Ghp}1eW~Y0yYfrfI#RBWdl');
                    User::Login_Refresh($udata["id"], $token);
                    User::deleteInfo($udata["id"], "verified-email");
                    User::addAction($udata["id"], "alteration", "changed-email-address", [
                        'before_email' => $udata["email"],
                        'new_email'    => $email,
                    ]);
                    Helper::Load("Notification");
                    if (Config::get("options/sign/up/email/verify")) {
                        User::DeleteBlocked("verify-code-check-email", $udata["id"], [
                            'ip'    => UserManager::GetIP(),
                            'email' => $udata["email"],
                        ]);
                        User::DeleteBlocked("verify-code-send-email", $udata["id"], [
                            'ip'    => UserManager::GetIP(),
                            'email' => $udata["email"],
                        ]);
                        LogManager::deleteLogCount("verify-code-email-attempt");
                        LogManager::deleteLogCount("verify-code-email");
                        $code = rand(1000, 9999);
                        Notification::email_activation($udata["id"], $code);
                    }
                    Notification::email_changed($udata["id"], $udata["email"], $set_datas["email"]);
                }


                $client_data = array_merge((array)User::getData($sdata["id"],
                    [
                        'id',
                        'name',
                        'surname',
                        'full_name',
                        'company_name',
                        'email',
                        'phone',
                        'currency',
                        'country',
                    ], "array"), User::getInfo($sdata["id"],
                    [
                        'company_tax_number',
                        'company_tax_office',
                        'gsm_cc',
                        'gsm',
                        'landline_cc',
                        'landline_phone',
                        'identity',
                        'kind',
                        'taxation',
                    ]));
                $client_data["address"] = AddressManager::getAddress(0, $sdata["id"]);
                $client_data["source"] = "client";

                Hook::run("ClientInformationModified", $client_data);

                User::addAction($sdata["id"], "alteration", "changed-general-information");
            }

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("website/account_info/changed-successfully"),
                'refresh' => true,
            ]);


        }


        private function DeleteAddress()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = Filter::init("POST/id", "numbers");

            if (Validation::isInt($id)) {
                $udata = UserManager::LoginData("member");
                $check = $this->model->CheckAddress($id, $udata["id"]);
                if ($check) {
                    $sdata = UserManager::LoginData("member");
                    $data1 = $sdata;
                    $data2 = User::getInfo($sdata["id"], "default_address", "array");
                    $udata = array_merge($data1, $data2);


                    $delete = $this->model->DeleteAddress($id);
                    if ($delete) {

                        if ($udata["default_address"] == $id) {
                            $addresses = $this->model->getAddresses($udata["id"]);
                            if ($addresses) {
                                User::setInfo($udata["id"], ["default_address" => $addresses[0]["id"]]);
                                User::setData($udata["id"], ["country" => $addresses[0]["country_id"]]);
                                $this->model->setAddress($addresses[0]["id"], ["detouse" => 1]);
                            } else User::deleteInfo($udata["id"], "default_address");
                        }

                        $total = $this->model->totalAddress($udata["id"]);
                        User::addAction($udata["id"], "delete", "address-has-been-deleted");
                        echo Utility::jencode([
                            'status' => "successful",
                            'id'     => $id,
                            'total'  => $total,
                        ]);
                    } else {
                        echo Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/account_info/failed-operation"),
                        ]);
                    }
                }
            } else
                return "Error! An unknown error has occurred";
        }


        private function CountryList()
        {
            $cache = self::$cache;
            $lang = Bootstrap::$lang->clang;
            $cname = "countryList";
            $cache->setCache("account-" . $lang);
            $cache->eraseExpired();
            if (!$cache->isCached($cname) || Config::get("general/cache")) {
                $data = $this->model->getCountryList($lang);
                if (Config::get("general/cache")) $cache->store($cname, $data);
            } else
                $data = $cache->retrieve($cname);
            return $data;
        }


        private function getCities()
        {
            $country = Filter::init("POST/country", "numbers");
            if ($country != '' && Validation::isInt($country) && strlen($country) < 20) {
                $cache = self::$cache;
                $lang = Bootstrap::$lang->clang;
                $cname = $country . "-cities";
                $cache->setCache("account-" . $lang);
                $cache->eraseExpired();
                if (!$cache->isCached($cname) || Config::get("general/cache")) {
                    $data = $this->model->getCities($country);
                    if (Config::get("general/cache")) $cache->store($cname, $data, DateManager::special_time(["month" => 1]));
                } else
                    $data = $cache->retrieve($cname);

                if ($data && is_array($data)) {

                    echo Utility::jencode([
                        'status' => "successful",
                        'data'   => $data,
                    ]);

                } else
                    echo Utility::jencode([
                        'status' => "error",
                    ]);
            }
        }


        private function getCounties()
        {
            $city = Filter::init("POST/city", "numbers");
            if ($city != '' && Validation::isInt($city) && strlen($city) < 20) {
                $cache = self::$cache;
                $lang = Bootstrap::$lang->clang;
                $cname = $city . "-counties";
                $cache->setCache("account-" . $lang);
                $cache->eraseExpired();
                if (!$cache->isCached($cname) || Config::get("general/cache")) {
                    $data = $this->model->getCounties($city);
                    if (Config::get("general/cache")) $cache->store($cname, $data, DateManager::special_time(["month" => 1]));
                } else
                    $data = $cache->retrieve($cname);

                if ($data && is_array($data)) {

                    echo Utility::jencode([
                        'status' => "successful",
                        'data'   => $data,
                    ]);

                } else
                    echo Utility::jencode([
                        'status' => "error",
                    ]);
            }
        }


        private function addNewAddress()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'account'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }


            $udata = UserManager::LoginData("member");
            $udata = array_merge($udata, User::getData($udata["id"], "id,full_name,country", "array"));
            if (!$udata) return false;

            $udata = array_merge($udata, User::getInfo($udata["id"], ["default_address"]));


            $kind = Filter::init("POST/kind", "letters");
            $full_name = Filter::init("POST/full_name", "hclear");
            $full_name = Utility::substr($full_name, 0, 255);
            $full_name = Utility::ucfirst_space($full_name, ___("package/charset-code"));
            $email = Filter::init("POST/email", "email");
            $gsm = Filter::init("POST/gsm", "numbers");
            $identity = Filter::init("POST/identity", "identity");

            $company_name = Filter::init("POST/company_name", "hclear");
            $company_taxnu = Filter::init("POST/company_tax_number", "letters_numbers", "-");
            $company_taxoff = Filter::init("POST/company_tax_office", "hclear");


            $country = Filter::init("POST/country", "numbers");
            $city = Filter::init("POST/city", "hclear");
            $counti = Filter::init("POST/counti", "hclear");
            $address = Filter::init("POST/address", "hclear");
            $zipcode = substr(Filter::init("POST/zipcode", "hclear"), 0, 20);
            $detouse = (int)Filter::init("POST/detouse", "numbers");
            $overwritenadoninv = (int)Filter::init("POST/overwritenadoninv", "numbers");

            if (!$kind) $kind = "individual";


            if ($kind != "corporate") {
                $company_name = '';
                $company_taxnu = '';
                $company_taxoff = '';
            }


            if (Validation::isEmpty($full_name))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='full_name']",
                    'message' => __("website/sign/up-submit-empty-full_name"),
                ]));

            $smash = Filter::name_smash($full_name);
            $name = $smash["first"];
            $surname = $smash["last"];

            if (Validation::isEmpty($name) || Validation::isEmpty($surname))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='full_name']",
                    'message' => __("website/sign/up-submit-empty-full_name"),
                ]));

            $identity_status = Config::get("options/sign/up/kind/individual/identity/status");
            $identity_required = Config::get("options/sign/up/kind/individual/identity/required");

            if ($udata["country"] == 227 && $identity_required && $identity_status && Validation::isEmpty($identity))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='identity']",
                    'message' => __("website/sign/empty-identity-number"),
                ]));

            if ($kind == "corporate") {
                if (Config::get("options/sign/up/kind/status")) {
                    if (Config::get("options/sign/up/kind/corporate/company_name/required") && Validation::isEmpty($company_name))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "#company_name",
                            'message' => __("website/sign/empty-custom-field", ['{name}' => __("website/account/field-company_name")]),
                        ]));
                    if (Config::get("options/sign/up/kind/corporate/company_tax_number/required") && Validation::isEmpty($company_taxnu))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "#company_tax_number",
                            'message' => __("website/sign/empty-custom-field", ['{name}' => __("website/account/field-company_tax_number")]),
                        ]));
                    if (Config::get("options/sign/up/kind/corporate/company_tax_office/required") && Validation::isEmpty($company_taxoff))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "#company_tax_office",
                            'message' => __("website/sign/empty-custom-field", ['{name}' => __("website/account/field-company_tax_office")]),
                        ]));
                }
            }


            if (Validation::isEmpty($email))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='email']",
                    'message' => __("website/sign/up-submit-empty-email"),
                ]));

            if (!Validation::isEmail($email))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='email']",
                    'message' => __("website/sign/up-submit-invalid-email"),
                ]));


            if (Config::get("options/sign/up/gsm/required") && strlen($gsm) < 4)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/sign/invalid-gsm-number"),
                ]));

            if (strlen($gsm) > 4) {
                if (!Validation::isPhone($gsm))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/sign/invalid-gsm-number"),
                    ]));
                $phone = $gsm;
            } else
                $phone = '';


            if (
                Validation::isEmpty($country) ||
                Validation::isEmpty($city) ||
                Validation::isEmpty($counti) ||
                Validation::isEmpty($address)
            )
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_info/empty-have-fields"),
                ]));


            $check_country = AddressManager::CheckCountry($country);
            if (!$check_country) return false;


            if (Validation::isInt($city)) {
                $check_city = AddressManager::CheckCity($city);
                if (!$check_city) return false;
            }

            if (Validation::isInt($counti)) {
                $check_counti = AddressManager::CheckCounti($counti);
                if (!$check_counti) return false;
            }

            $addresses = $this->model->getAddresses($udata["id"]);

            if ($addresses && sizeof($addresses) >= 5)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_info/error1"),
                ]));

            $firstAddress = $addresses == false || $detouse;
            if ($firstAddress) User::setData($udata["id"], ['country' => $country]);

            $added = $this->model->addNewAddress([
                'name'               => $name,
                'surname'            => $surname,
                'full_name'          => $full_name,
                'kind'               => $kind ? $kind : "individual",
                'company_name'       => $company_name,
                'company_tax_office' => $company_taxoff,
                'company_tax_number' => $company_taxnu,
                'email'              => $email,
                'phone'              => $phone,
                'identity'           => $identity,
                'owner_id'           => $udata["id"],
                'country_id'         => $country,
                'city'               => $city,
                'counti'             => $counti,
                'address'            => $address,
                'zipcode'            => $zipcode,
                'detouse'            => $firstAddress ? 1 : 0,
            ]);

            if ($added) {
                echo Utility::jencode([
                    'status'  => "successful",
                    'message' => __("website/account_info/success1"),
                    'id'      => $added,
                ]);
                if ($firstAddress) {
                    User::AddInfo($udata["id"], ['default_address' => $added]);
                    if ($udata["default_address"]) $this->model->setAddress($udata["default_address"], ['detouse' => 0]);
                }

                if ($overwritenadoninv) User::overwrite_new_address_on_invoices($udata["id"], $added);

            } else
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_info/unable-to-add"),
                ]));

            User::addAction($udata["id"], "added", "added-new-address", [
                'id' => $added,
            ]);
        }


        private function editAddress()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'account'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            $udata = UserManager::LoginData("member");
            $udata = array_merge($udata, User::getData($udata["id"], "id,full_name,country", "array"));
            if (!$udata) return false;

            $udata = array_merge($udata, User::getInfo($udata["id"], ["default_address"]));

            $id = (int)Filter::init("POST/id", "numbers");

            $addr = $this->model->getAddress($id, $udata["id"]);
            if (!$addr) return false;


            $kind = Filter::init("POST/kind", "letters");
            $full_name = Filter::init("POST/full_name", "hclear");
            $full_name = Utility::substr($full_name, 0, 255);
            $full_name = Utility::ucfirst_space($full_name, ___("package/charset-code"));
            $email = Filter::init("POST/email", "email");
            $gsm = Filter::init("POST/gsm", "numbers");
            $identity = Filter::init("POST/identity", "identity");

            $company_name = Filter::init("POST/company_name", "hclear");
            $company_taxnu = Filter::init("POST/company_tax_number", "letters_numbers", "-");
            $company_taxoff = Filter::init("POST/company_tax_office", "hclear");


            $country = Filter::init("POST/country", "numbers");
            $city = Filter::init("POST/city", "hclear");
            $counti = Filter::init("POST/counti", "hclear");
            $address = Filter::init("POST/address", "hclear");
            $zipcode = substr(Filter::init("POST/zipcode", "hclear"), 0, 20);
            $detouse = (int)Filter::init("POST/detouse", "numbers");
            $overwritenadoninv = (int)Filter::init("POST/overwritenadoninv", "numbers");

            if (!$kind) $kind = "individual";


            $identity_status = Config::get("options/sign/up/kind/individual/identity/status");
            $identity_required = Config::get("options/sign/up/kind/individual/identity/required");

            if ($udata["country"] == 227 && $identity_required && $identity_status && Validation::isEmpty($identity))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='identity']",
                    'message' => __("website/sign/empty-identity-number"),
                ]));


            if ($kind != "corporate") {
                $company_name = '';
                $company_taxnu = '';
                $company_taxoff = '';
            }


            if (Validation::isEmpty($full_name))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='full_name']",
                    'message' => __("website/sign/up-submit-empty-full_name"),
                ]));

            $smash = Filter::name_smash($full_name);
            $name = $smash["first"];
            $surname = $smash["last"];

            if (Validation::isEmpty($name) || Validation::isEmpty($surname))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='full_name']",
                    'message' => __("website/sign/up-submit-empty-full_name"),
                ]));


            if ($kind == "corporate") {
                if (Config::get("options/sign/up/kind/status")) {
                    if (Config::get("options/sign/up/kind/corporate/company_name/required") && Validation::isEmpty($company_name))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "#company_name",
                            'message' => __("website/sign/empty-custom-field", ['{name}' => __("website/account/field-company_name")]),
                        ]));
                    if (Config::get("options/sign/up/kind/corporate/company_tax_number/required") && Validation::isEmpty($company_taxnu))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "#company_tax_number",
                            'message' => __("website/sign/empty-custom-field", ['{name}' => __("website/account/field-company_tax_number")]),
                        ]));
                    if (Config::get("options/sign/up/kind/corporate/company_tax_office/required") && Validation::isEmpty($company_taxoff))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "#company_tax_office",
                            'message' => __("website/sign/empty-custom-field", ['{name}' => __("website/account/field-company_tax_office")]),
                        ]));
                }
            }


            if (Validation::isEmpty($email))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='email']",
                    'message' => __("website/sign/up-submit-empty-email"),
                ]));

            if (!Validation::isEmail($email))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='email']",
                    'message' => __("website/sign/up-submit-invalid-email"),
                ]));


            if (strlen($gsm) > 4) {

                if (!Validation::isPhone($gsm))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/sign/invalid-gsm-number"),
                    ]));
                $phone = $gsm;
            } else
                $phone = '';


            if (
                Validation::isEmpty($country) ||
                Validation::isEmpty($city) ||
                Validation::isEmpty($counti) ||
                Validation::isEmpty($address)
            )
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_info/empty-have-fields"),
                ]));

            $check_country = AddressManager::CheckCountry($country);
            if (!$check_country) return false;


            if (Validation::isInt($city)) {
                $check_city = AddressManager::CheckCity($city);
                if (!$check_city) return false;
            }

            if (Validation::isInt($counti)) {
                $check_counti = AddressManager::CheckCounti($counti);
                if (!$check_counti) return false;
            }


            $firstAddress = $detouse && !$addr["detouse"];
            if ($firstAddress) User::setData($udata["id"], ['country' => $country]);

            if ($addr["detouse"] && $country != $udata["country"])
                User::setData($udata["id"], ['country' => $country]);


            $set_address = [
                'name'               => $name,
                'surname'            => $surname,
                'full_name'          => $full_name,
                'kind'               => $kind ? $kind : "individual",
                'company_name'       => $company_name,
                'company_tax_office' => $company_taxoff,
                'company_tax_number' => $company_taxnu,
                'email'              => $email,
                'phone'              => $phone,
                'identity'           => $identity,
                'country_id'         => $country,
                'city'               => $city,
                'counti'             => $counti,
                'address'            => $address,
                'zipcode'            => $zipcode,
            ];


            if ($firstAddress) $set_address["detouse"] = $firstAddress ? 1 : 0;

            $set = $this->model->setAddress($id, $set_address);

            if (!$set)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Something went wrong!",
                ]));

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("website/account_info/success2"),
                'id'      => $id,
            ]);
            if ($firstAddress) {
                User::AddInfo($udata["id"], ['default_address' => $id]);
                if ($udata["default_address"]) $this->model->setAddress($udata["default_address"], ['detouse' => 0]);
            }

            if ($overwritenadoninv) User::overwrite_new_address_on_invoices($udata["id"], $id);


            User::addAction($udata["id"], "alteration", "changed-address", [
                'id' => $id,
            ]);
        }


        private function updateDefaultAddress()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'account'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            $udata = UserManager::LoginData("member");
            $udata = array_merge($udata, User::getData($udata["id"], "id,full_name,country", "array"));
            if (!$udata) return false;

            $udata = array_merge($udata, User::getInfo($udata["id"], ["default_address"]));

            $id = (int)Filter::init("POST/address_id", "numbers");

            $addr = $this->model->getAddress($id, $udata["id"]);
            if (!$addr) return false;


            $detouse = 1;
            $overwritenadoninv = (int)Filter::init("POST/overwritenadoninv", "numbers");

            $country = $addr["country"];


            $firstAddress = $detouse && !$addr["detouse"];
            if ($firstAddress) User::setData($udata["id"], ['country' => $country]);

            if ($country != $udata["country"])
                User::setData($udata["id"], ['country' => $country]);


            $set_address = [
                'detouse' => 1,
            ];

            $set = $this->model->setAddress($id, $set_address);

            if (!$set)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Something went wrong!",
                ]));

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("website/account_info/success2"),
                'id'      => $id,
            ]);
            if ($firstAddress) {
                User::AddInfo($udata["id"], ['default_address' => $id]);
                if ($udata["default_address"]) $this->model->setAddress($udata["default_address"], ['detouse' => 0]);
            }

            if ($overwritenadoninv) User::overwrite_new_address_on_invoices($udata["id"], $id);

            User::addAction($udata["id"], "alteration", "changed-address", [
                'id' => $id,
            ]);
        }


        private function ModifyPreferences()
        {

            $this->takeDatas(["language"]);

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'account'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            Helper::Load("User");
            $udata = UserManager::LoginData("member");
            $datx1 = User::getData($udata["id"], "lang,balance,currency", "array");
            $datx2 = User::getInfo($udata["id"], ["email_notifications", "sms_notifications", "contract1", "contract2", "two_factor"]);
            $udata = array_merge($udata, $datx1, $datx2);

            $email_notifications = (int)Filter::init("POST/email_notifications", "rnumbers");
            $sms_notifications = (int)Filter::init("POST/sms_notifications", "rnumbers");
            $contract1 = (int)Filter::init("POST/contract1", "rnumbers");
            $contract2 = (int)Filter::init("POST/contract2", "rnumbers");
            $two_factor = (int)Filter::init("POST/two_factor", "rnumbers");
            $currency = (int)Filter::init("POST/currency", "numbers");
            $lang = strtolower(Filter::init("POST/lang", "letters", "\-_"));


            $idata = [];
            $pdata = [];
            if ($email_notifications != $udata["email_notifications"])
                $idata["email_notifications"] = $email_notifications;

            if ($sms_notifications != $udata["sms_notifications"])
                $idata["sms_notifications"] = $sms_notifications;

            /*if($contract1 != $udata["contract1"]) $idata["contract1"] = $contract1;
            if($contract2 != $udata["contract2"]) $idata["contract2"] = $contract2;*/
            if ($two_factor != $udata["two_factor"]) $idata["two_factor"] = $two_factor;


            if ($currency != 0 && $currency != $udata["currency"]) {
                Helper::Load("Money");
                if (Money::Currency($currency)) {
                    $pdata["currency"] = $currency;
                    if ($currency != Money::getUCID()) Money::setCurrency($currency);
                }
            }

            if (!Validation::isEmpty($lang) && $lang != $udata["lang"]) {
                if (Bootstrap::$lang->LangExists($lang)) {
                    Bootstrap::$lang->change($lang, false);
                    $pdata["lang"] = $lang;
                }
            }


            if ($idata || $pdata) {
                if ($idata) User::setInfo($udata["id"], $idata);
                if ($pdata) User::setData($udata["id"], $pdata);

                if ($idata && isset($idata["contract1"])) {
                    if ($idata["contract1"])
                        User::addAction($udata["id"], "alteration", "contract1-is-approved");
                    else
                        User::addAction($udata["id"], "alteration", "contract1-is-unapproved");
                }

                if ($idata && isset($idata["contract2"])) {
                    if ($idata["contract2"])
                        User::addAction($udata["id"], "alteration", "contract2-is-approved");
                    else
                        User::addAction($udata["id"], "alteration", "contract2-is-unapproved");
                }

                User::addAction($udata["id"], "alteration", "changed-preferences");
            }

            echo Utility::jencode([
                'status'   => "successful",
                'redirect' => $this->CRLink("ac-ps-info") . "?tab=3",
            ]);
        }


        private function ModifyPassword()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'account'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            $udata = UserManager::LoginData("member");
            $udata = User::getData($udata["id"], "id,full_name,email,lang,country,password", "array");

            $password = Filter::init("POST/password", "password");
            $password_again = Filter::init("POST/password_again", "password");


            if (Validation::isEmpty($password))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='password']",
                    'message' => __("website/account_info/empty-have-fields"),
                ]));

            if (Utility::strlen($password) < $min_length = Config::get("options/password-length"))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='password']",
                    'message' => __("website/sign/password-is-too-short", ['{length}' => $min_length]),
                ]));

            if (Validation::isEmpty($password_again))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='password_again']",
                    'message' => __("website/account_info/empty-have-fields"),
                ]));

            if ($password_again !== $password)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='password_again']",
                    'message' => __("website/account_info/password-is-invalid-again"),
                ]));

            $epassword = User::_crypt("member", $password, "encrypt", '-O@cb6Dxvarhc+Ghp}1eW~Y0yYfrfI#RBWdl');
            Helper::Load("User");
            $udata = UserManager::LoginData("member");
            $udata2 = User::getData($udata["id"], ["email", "lang"], "array");
            User::setData($udata["id"], [
                'password' => $epassword,
            ]);

            UserManager::Logout("member");
            $token = UserManager::Create_Login_Token($udata["id"], $udata2["email"], $epassword);
            UserManager::Login("member", $udata["id"], $epassword, $udata2["lang"], '-O@cb6Dxvarhc+Ghp}1eW~Y0yYfrfI#RBWdl');
            User::Login_Refresh($udata["id"], $token);
            User::addAction($udata["id"], "alteration", "changed-password");

            Helper::Load("Notification");

            Notification::password_changed($udata["id"]);


            $client_data = array_merge((array)User::getData($udata["id"],
                [
                    'id',
                    'name',
                    'surname',
                    'full_name',
                    'company_name',
                    'email',
                    'phone',
                    'currency',
                    'lang',
                    'country',
                ], "array"), User::getInfo($udata["id"],
                [
                    'company_tax_number',
                    'company_tax_office',
                    'gsm_cc',
                    'gsm',
                    'landline_cc',
                    'landline_phone',
                    'identity',
                    'kind',
                    'taxation',
                ]));

            $client_data["address"] = AddressManager::getAddress(0, $udata["id"]);
            $client_data["source"] = "client";
            $client_data["password"] = $password;

            Hook::run("ClientChangePassword", $client_data);

            echo Utility::jencode(['status' => "successful"]);
        }


        private function main_bring($bring = '')
        {
            if ($bring == "address-list") return $this->bring_address_list();
            if ($bring == "country-list") return $this->bring_country_list();
            else echo "Not Found Bring: " . $bring;
        }


        private function bring_country_list()
        {

            $data = $this->CountryList();
            $result = [];
            if ($data) $result = ['status' => "successful", 'data' => $data];
            else $result = ['status' => "error"];

            echo Utility::jencode($result);
        }


        private function bring_address_list()
        {
            $sdata = UserManager::LoginData("member");
            $data1 = User::getData($sdata["id"], "full_name", "array");
            $data2 = User::getInfo($sdata["id"], "kind,company_name");
            $udata = array_merge($sdata, $data1, $data2);
            $name = ($udata["kind"] == "corporate") ? $udata["company_name"] : $udata["full_name"];

            $data = $this->getAddresses($udata["id"], $name);
            $result = [];
            if ($data) $result = ['status' => "successful", 'data' => $data];
            else $result = ['status' => "error"];

            echo Utility::jencode($result);
        }


        public function main()
        {
            $bring = Filter::init("GET/bring", "route");
            if ($bring && $bring != '') return $this->main_bring($bring);
            if (Filter::isPOST() || Filter::GET("operation")) return $this->PostCenter();

            $this->addData("pname", "account_info");
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

            if ($_vcode = Filter::init("GET/v_email_code")) $this->verifyEmail($_vcode);

            $this->addData("operation_link", $this->CRLink("ac-ps-info"));
            $this->addData("page_type", "account");
            $this->addData("meta", __("website/account_info/meta"));
            $this->addData("header_title", __("website/account/info-page-title"));

            $breadcrumb = [
                [
                    'link'  => $this->CRLink("my-account"),
                    'title' => __("website/account/breadcrumb-dashboard"),
                ],
                [
                    'link'  => null,
                    'title' => __("website/account/breadcrumb-info"),
                ],
            ];

            $this->addData("panel_breadcrumb", $breadcrumb);


            $sdata = UserManager::LoginData("member");
            $data1 = User::getData($sdata["id"], "id,full_name,email,currency,lang,country", "array");
            $data2 = User::getInfo($sdata["id"], [
                'identity_required',
                'force_identity',
                'identity_checker',
                'birthday_required',
                'birthday_adult_verify',
                'birthday',
                'identity',
                'kind',
                'gsm_cc',
                'gsm',
                'landline_phone',
                'company_name',
                'company_tax_number',
                'company_tax_office',
                'default_address',
                'email_notifications',
                'sms_notifications',
                'verified-email',
                'verified-gsm',
                'contract1',
                'contract1_updated_at',
                'contract2',
                'contract2_updated_at',
                'security_question',
                'security_question_answer',
                'two_factor',
                'auto_payment',
                'gdpr_downloaded_at',
            ]);
            $cfields = $this->model->get_custom_fields($data1["lang"]);
            $data3 = [];
            if ($cfields) {
                $fields = [];
                foreach ($cfields as $field) {
                    $fields[] = "field_" . $field["id"];
                }
                $data3 = User::getInfo($sdata["id"], $fields, "array");
            }
            $udata = array_merge($data1, $data2, $data3);

            if (!$udata["contract2_updated_at"]) {
                $is_approved = $this->model->db->select("ctime")->from("users_actions")->where("owner_id", "=", $udata["id"], "&&")->where("detail", "=", "contract2-is-approved");
                if ($is_approved->build())
                    $udata["contract2_updated_at"] = $is_approved->getObject()->ctime;
                else {
                    $is_approved = $this->model->db->select("updated_at")->from("users_informations")->where("owner_id", "=", $udata["id"], "&&")->where("name", "=", "contract2");
                    if ($is_approved->build())
                        $udata["contract2_updated_at"] = $is_approved->getObject()->updated_at;

                }

            }


            $identity_status = Config::get("options/sign/up/kind/individual/identity/status");
            $birthday_status = Config::get("options/sign/birthday/status");
            $birthday_adult_verify = Config::get("options/sign/birthday/adult_verify");

            if ($udata["force_identity"]) $identity_status = 1;


            if ($udata['identity_required'] || $udata['identity_checker']) $identity_status = 1;
            if ($udata['birthday_required'] || $udata['birthday_adult_verify']) {
                $birthday_status = 1;
                if ($udata['birthday_adult_verify']) $birthday_adult_verify = 1;
            }


            $this->addData("kind_status", Config::get("options/sign/up/kind/status"));
            $this->addData("gsm_status", Config::get("options/sign/up/gsm/status"));
            $this->addData("gsm_verify_status", Config::get("options/sign/up/gsm/verify"));
            $this->addData("email_verify_status", Config::get("options/sign/up/email/verify"));
            $this->addData("landlinep_status", Config::get("options/sign/up/landline-phone/status"));
            $this->addData("identity_status", $identity_status);
            $this->addData("birthday_status", $birthday_status);
            $this->addData("birthday_adult_verify", $birthday_adult_verify);


            if ($udata["security_question"])
                $udata["security_question"] = Crypt::decode($udata["security_question"], Config::get("crypt/user"));

            if ($udata["security_question_answer"])
                $udata["security_question_answer"] = Crypt::decode($udata["security_question_answer"], Config::get("crypt/user"));

            $this->addData("editable", Config::get("options/sign/editable"));
            $this->addData("cfields", $cfields);
            $this->addData("requiredFields", User::requiredFields($udata["id"]));
            $this->addData("remainingVerifications", User::RemainingVerifications($udata["id"]));

            $this->addData("u_lang", Bootstrap::$lang->clang);

            Helper::Load(["Money", "Invoices"]);
            $name = ($udata["kind"] == "corporate") ? $udata["company_name"] : $udata["full_name"];
            $stored_cards = Models::$init->db->select()->from("users_stored_cards");
            $stored_cards->where("user_id", "=", $udata["id"]);
            $stored_cards->order_by("id DESC");
            $stored_cards = $stored_cards->build() ? $stored_cards->fetch_assoc() : [];


            $this->addData("acAddresses", $this->getAddresses($sdata["id"], $name));
            $this->addData("countryList", $this->countryList());
            $this->addData("stored_cards", $stored_cards);
            $this->addData("udata", $udata);

            $c_s_m = Config::get("modules/card-storage-module");

            if ($c_s_m && $c_s_m != "none") {
                Modules::Load("Payment", $c_s_m);
                $module = new $c_s_m();

                $refund_support = method_exists($module, 'refund');

                $this->addData("refund_support", $refund_support);

                if ($refund_support) {
                    $this->addData("links", [
                        'successful-page' => $this->CRLink("ac-ps-info") . "?tab=csm&status=successful",
                        'failed-page'     => $this->CRLink("ac-ps-info") . "?tab=csm&status=failed",
                    ]);

                    $checkout = [
                        'id'   => $udata["id"],
                        'data' => [
                            'type' => "card-identification",
                        ],
                    ];

                    $checkout["data"]["user_data"] = Invoices::generate_user_data($udata["id"]);

                    $currency = $udata["currency"];

                    if (isset($checkout["data"]["user_data"]["address"]) && $checkout["data"]["user_data"]["address"] && $checkout["data"]["user_data"]["address"]["country_code"] == "TR" && Config::get("general/currency") == 147) $currency = 147;

                    $checkout["data"]["total"] = 1;
                    $checkout["data"]["currency"] = $currency;

                    $this->addData("module", $module);
                    $this->addData("checkout", $checkout);
                    $c_s_m_content = $this->view->chose(false, true)->render($module->payform, $this->data, true);
                    $this->addData("c_s_m_content", $c_s_m_content);
                }

            }

            $this->addData("c_s_m", $c_s_m);

            $this->addData("timezone", $this->timezone);

            $this->addData("gdpr_request", $this->model->gdpr_request($udata["id"]));


            $this->view->chose("website")->render("ac-info", $this->data);

        }
    }