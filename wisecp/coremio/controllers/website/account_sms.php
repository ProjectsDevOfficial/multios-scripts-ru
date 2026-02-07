<?php
    defined('CORE_FOLDER') or exit('You can not get in here!');

    class Controller extends Controllers
    {
        protected $params, $data = [];


        public function __construct($arg = [])
        {
            parent::__construct();
            $this->params = $arg['params'];

            if (isset($this->params[0]) && $this->params[0] == "introduction") {

            } elseif (!UserManager::LoginCheck("member")) {
                Utility::redirect($this->CRLink("sign-in"));
                die();
            }

            if (!Config::get("options/pg-activation/international-sms")) {
                $this->main_404();
                die();
            }

            if (!isset($this->params[0]) || $this->params[0] != "introduction") {
                $udata = UserManager::LoginData("member");
                $redirect_link = User::full_access_control_account($udata);

                if ($redirect_link) {
                    Utility::redirect($redirect_link);
                    die();
                }
            }
        }


        private function header_background()
        {
            $cache = self::$cache;
            $cache->setCache("account");
            $cname = "account_sms_hbackground";
            $cache->eraseExpired();
            if (!$cache->isCached($cname) || !Config::get("general/cache")) {
                $data = $this->model->header_background();
                if ($data) $data = Utility::image_link_determiner($data, Config::get("pictures/header-background/folder"));
                if (Config::get("general/cache")) $cache->store($cname, $data);
            } else
                $data = $cache->retrieve($cname);
            return $data;
        }


        private function operationMain($operation)
        {
            if ($operation == "introduction") return $this->introduction();
            if ($operation == "get-statistics") return $this->get_statistics();
            if ($operation == "add_new_origin") return $this->add_new_origin();
            if ($operation == "get_sms_report") return $this->get_sms_report();
            if ($operation == "get_sms_reports") return $this->get_sms_reports();
            if ($operation == "delete_origin") return $this->delete_origin();
            if ($operation == "get_pre_register_countries") return $this->get_pre_register_countries();
            if ($operation == "add_new_pre_register_country") return $this->add_new_pre_register_country();
            if ($operation == "add_new_group") return $this->add_new_group_submit();
            if ($operation == "change_group_numbers") return $this->change_group_numbers();
            if ($operation == "delete_group") return $this->delete_group();
            if ($operation == "check_phone_numbers") return $this->check_phone_numbers();
            if ($operation == "submit_sms") return $this->submit_sms();
            return false;
        }


        private function submit_sms()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $origin = Filter::init("POST/origin", "numbers");
            $numbers = Filter::POST("numbers");
            $numbers = is_array($numbers) ? $numbers : explode("\n", $numbers);
            $message = Filter::init("POST/message", "hclear");
            $selectedcs = Filter::POST("selected_countries");
            $result = $this->check_phone_numbers(true, $origin, $numbers, $message, $selectedcs);

            if (!$result)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/error6"),
                ]));

            if (isset($result["status"]) && $result["status"] == "error")
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => $result["message"],
                ]));


            if (!isset($result["total_quantity_sent"]))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/error6"),
                ]));

            if ($result["total_quantity_sent"] == 0)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/error7"),
                ]));

            $message = $result["message"];
            $mlength = $result["message_length"];
            $count = $result["total_quantity_sent"];
            $origin_name = $result["origin_name"];

            if (Validation::isEmpty($origin_name))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/error8"),
                ]));

            if (Validation::isEmpty($message) || $mlength < 3)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/error9"),
                ]));

            if (!isset($result["total_price"]))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/error6"),
                ]));

            $udata = UserManager::LoginData("member");
            $udata = array_merge($udata, User::getData($udata["id"], "balance,balance_currency", "array"));

            $falling_total = $result["total_price"];
            $user_balance = $udata["balance"];
            $new_balance = ($user_balance - $falling_total);

            if ($user_balance < $falling_total)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/error9"),
                ]));

            $module_name = Config::get("modules/sms-intl");

            if ($module_name == '' || $module_name == "none")
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/error13"),
                ]));


            Modules::Load("SMS", $module_name);
            $sms = new $module_name();

            if (!$sms->international)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/error11"),
                ]));

            $countries = [];
            foreach ($result["countries"] as $country) {
                $countries[$country["code"]] = [
                    'code'        => $country["code"],
                    'name'        => $country["name"],
                    'count'       => $country["count"],
                    'part1_price' => $country["part1_price"],
                    'part_price'  => $country["price"],
                    'total_part'  => $country["total_part"],
                    'total_price' => $country["total_price"],
                ];
            }

            $sended = $sms->body($message)->title($origin_name)->AddNumber($result["numbers"])->submit();
            if (!$sended)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/error12", ['{error}' => $sms->getError()]),
                ]));

            User::setData($udata["id"], [
                'balance' => $new_balance,
            ]);

            $rID = $sms->getReportID();
            $data = Utility::jencode([
                'module'       => $module_name,
                'report_id'    => $rID,
                'part'         => $result["sms_part"],
                'total_part'   => $result["total_quantity_sent_sms"],
                'length'       => $mlength,
                'count'        => $count,
                'countries'    => $countries,
                'total_credit' => $falling_total,
                'credit_cid'   => $udata["balance_currency"],
            ]);
            LogManager::Sms_Log($udata["id"], "send-sms", $sms->getTitle(), $sms->getBody(), implode(",", $sms->getNumbers()), $data, 0, "international_sms", 0);

            User::addAction($udata["id"], "info", "send-international-sms");
            echo Utility::jencode(['status' => "successful"]);
        }


        private function check_phone_numbers($returned = false, $origin = 0, $numbers = 0, $message = null, $selectedcs = null)
        {
            $origin = $origin == 0 ? Filter::init("POST/origin_id", "numbers") : $origin;
            $numbers = $numbers == 0 ? Filter::POST("numbers") : $numbers;
            $message = $message == null ? Filter::init("POST/message", "hclear") : $message;
            $length = Utility::strlen($message);
            $selectedcs = $selectedcs == null ? Filter::POST("selected_countries") : $selectedcs;
            $result = [
                'sms_part'                => 0,
                'total_quantity_sent'     => 0,
                'total_quantity_sent_sms' => 0,
                'total_quantity_nsent'    => 0,
                'total_price'             => 0,
                'total_price_symbol'      => 0,
                'unknowns'                => [],
                'countries'               => [],
                'numbers'                 => [],
            ];


            if ($numbers && $origin && Validation::isInt($origin)) {
                $udata = UserManager::LoginData("member");
                $getOrigin = $this->get_origin($origin, $udata["id"]);
                if ($getOrigin) {

                    $part = 1;
                    $dimensions = Config::get("sms/dimensions");
                    if ($dimensions && is_array($dimensions) && sizeof($dimensions) > 0) {
                        $last = end($dimensions);
                        if ($length >= $last["end"]) {
                            $message = Utility::substr($message, 0, $last["end"]);
                            $part = $last["part"];
                            $length = $last["end"];
                        } else {
                            $find = false;
                            foreach ($dimensions as $dimension) {
                                if ($length >= $dimension["start"] && $length <= $dimension["end"]) {
                                    $part = $dimension["part"];
                                    $find = true;
                                    break;
                                }
                            }
                            if (!$find) $part = $last["part"];
                        }
                    }

                    if ($returned) {
                        $result["message"] = $message;
                        $result["message_length"] = $length;
                        $result["sms_part"] = $part;
                        $result["origin_name"] = $getOrigin["name"];
                    }

                    Helper::Load(["Money"]);

                    $udata = UserManager::LoginData("member");
                    $udata = array_merge($udata, User::getData($udata["id"], "balance_currency,lang", "array"));
                    $lang = Bootstrap::$lang->clang;
                    $ucid = $udata["balance_currency"];
                    $preregcs = explode(",", Config::get("sms/pre-register-countries"));

                    Money::$digit = 4;

                    foreach ($numbers as $number) {
                        $number = substr($number, 0, 20);
                        if (is_string($number) || is_numeric($number)) {
                            $validate = Validation::isPhone($number);
                            $data = $validate ? Filter::phone_smash($number) : [];
                            if ($validate && isset($data["cc"])) {
                                $code = !is_array($data["code"]) ? [$data["code"]] : $data["code"];
                                foreach ($code as $co) {
                                    if (($selectedcs && in_array($co, $selectedcs)) || !$selectedcs) {

                                        if (!isset($result["countries"][$co])) {
                                            $prereg_required = in_array($co, $preregcs);
                                            $result["countries"][$co]["prereg_required"] = $prereg_required;
                                            if ($prereg_required) {
                                                $check_prereg = $this->model->check_origin_prereg($origin, $co);
                                                $result["countries"][$co]["prereg_status"] = $check_prereg ? "approved" : "unapproved";
                                            }
                                            $country_name = $name = AddressManager::get_country_name($co, $lang);
                                            $cou = strtoupper($co);
                                            $prices = Config::get("sms/country-prices");
                                            if (isset($prices[$cou])) {
                                                $price = $prices[$cou]["amount"];
                                                $curr = $prices[$cou]["cid"];
                                                $amount = Money::exChange($price, $curr, $ucid);
                                            } else
                                                $amount = 0;

                                            $result["countries"][$co]["code"] = $co;
                                            $result["countries"][$co]["count"] = 1;
                                            $result["countries"][$co]["name"] = $country_name;
                                            $result["countries"][$co]["total_part"] = $part;
                                            $result["countries"][$co]["part1_price"] = $amount;
                                            $result["countries"][$co]["price"] = $amount * $part;
                                            $result["countries"][$co]["price_symbol"] = Money::formatter_symbol($amount, $ucid);
                                            $result["countries"][$co]["total_price"] = $result["countries"][$co]["price"];
                                        } else {
                                            $result["countries"][$co]["total_part"] += $part;
                                            $prereg_required = $result["countries"][$co]["prereg_required"];
                                            $result["countries"][$co]["count"] += 1;
                                            $result["countries"][$co]["total_price"] = ($result["countries"][$co]["price"] * $result["countries"][$co]["count"]);
                                        }

                                        $result["countries"][$co]["numbers"][] = $number;

                                        $result["countries"][$co]["total_price_symbol"] = Money::formatter_symbol($result["countries"][$co]["total_price"], $ucid);

                                        $result["total_price"] += $result["countries"][$co]["price"];
                                        $result["total_quantity_sent"] += 1;
                                        $result["total_quantity_sent_sms"] += $part;
                                        $result["numbers"][] = $number;
                                    }
                                }
                            } else {
                                $result["total_quantity_nsent"] += 1;
                                $result["unknowns"][] = $number;
                            }
                        }
                    }

                    $result["total_price_symbol"] = Money::formatter_symbol($result["total_price"], $ucid);
                    if (!$returned) $result["countries"] = array_values($result["countries"]);
                } else {
                    $result["status"] = "error";
                    $result["message"] = __("website/account_sms/error5");
                }
            }

            if ($returned) return $result;

            echo Utility::jencode($result);
        }


        private function add_new_group_submit()
        {

            $udata = UserManager::LoginData("member");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $name = Filter::init("POST/name", "noun", "\-_\*\+\/");
            if (Validation::isEmpty($name))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='name']",
                    'message' => __("website/account_sms/add-new-group-err1"),
                ]));


            $added = $this->model->add_new_group([
                'user_id' => $udata["id"],
                'pid'     => 0,
                'name'    => $name,
                'ctime'   => DateManager::Now(),
            ]);
            if (!$added)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/add-new-group-err2"),
                ]));

            Helper::Load("User");
            User::addAction($udata["id"], "added", "added-new-sms-group");
            echo Utility::jencode(['status' => "successful"]);

        }


        private function change_group_numbers()
        {
            $udata = UserManager::LoginData("member");
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $group = Filter::init("POST/group", "numbers");
            $numbers = Filter::init("POST/numbers", "text");

            if (Validation::isEmpty($group) || !Validation::isInt($group))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/please-select-group"),
                ]));


            $count = 0;
            if (!Validation::isEmpty($numbers)) {
                $exps = explode("\n", $numbers);
                $nums = [];
                if ($exps && is_array($exps) && sizeof($exps) > 0) {
                    foreach ($exps as $ex) {
                        $ex = Utility::short_text(Filter::numbers($ex, "\+"), 0, 20);
                        if ($ex != '' && !in_array($ex, $nums)) $nums[] = $ex;
                    }
                    $count = sizeof($nums);
                    $numbers = $count > 0 ? implode(",", $nums) : null;
                } else
                    $numbers = null;
            } else
                $numbers = null;

            $check = $this->model->group_check($group, $udata["id"]);
            if (!$check)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/change-group-numbers-err1"),
                ]));

            $changed = $this->model->change_group_numbers($group, $udata["id"], $numbers);

            if (!$changed)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/change-group-numbers-err1"),
                ]));
            Helper::Load("User");
            User::addAction($udata["id"], "alteration", "changed-group-numbers");
            echo Utility::jencode([
                'status' => "successful",
                'count'  => $count,
            ]);
        }


        private function delete_origin()
        {
            return false;
            $udata = UserManager::LoginData("member");
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = Filter::init("POST/id", "numbers");
            if (Validation::isEmpty($id) || !Validation::isInt($id))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/delete-origin-err1"),
                ]));

            $check = $this->model->get_origin($id, $udata["id"]);
            if (!$check)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/delete-origin-err1"),
                ]));


            $deleted = $this->model->delete_origin($id, $udata["id"]);
            if (!$deleted)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/delete-origin-err2"),
                ]));
            Helper::Load("User");
            User::addAction($udata["id"], "delete", "deleted-sms-origin");

            echo Utility::jencode(['status' => "successful"]);
        }


        private function delete_group()
        {
            $udata = UserManager::LoginData("member");
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = Filter::init("POST/id", "numbers");
            if (Validation::isEmpty($id) || !Validation::isInt($id))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/delete-group-err1"),
                ]));

            $check = $this->model->group_check($id, $udata["id"]);
            if (!$check)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/delete-group-err2"),
                ]));


            $deleted = $this->model->delete_group($id, $udata["id"]);
            if (!$deleted)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/delete-group-err2"),
                ]));
            Helper::Load("User");
            User::addAction($udata["id"], "delete", "deleted-sms-group");

            echo Utility::jencode(['status' => "successful"]);
        }


        private function add_new_pre_register_country()
        {

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $this->takeDatas("language");

            $countries = explode(",", Config::get("sms/pre-register-countries"));

            $get_countries = Filter::init("POST/countries");
            $attachments = Filter::FILES("attachments");
            $origin_id = Filter::init("POST/origin_id", "numbers");
            if (!$get_countries)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/error1"),
                ]));

            if ($get_countries) {
                foreach ($get_countries as $get_country) {
                    $get_country = Filter::letters($get_country);

                    if (!in_array($get_country, $countries))
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/account_sms/error2"),
                        ]));
                }
            }


            $udata = UserManager::LoginData("member");

            if (!$origin_id || !Validation::isInt($origin_id))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/error3"),
                ]));

            $checkOrigin = $this->model->get_user_origin($origin_id, $udata["id"]);

            if (!$checkOrigin)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/error3"),
                ]));

            if (!$attachments)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/error4"),
                ]));

            if ($get_countries) {
                foreach ($get_countries as $get_country) {
                    $get_country = Filter::letters($get_country);

                    $alreadyExist = $this->model->PreRegOriginAlreadyExist($origin_id, $get_country);

                    if ($alreadyExist)
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/account_sms/error14"),
                        ]));
                }
            }

            if ($attachments && is_array($attachments)) {
                Helper::Load("Uploads");
                $upload = Helper::get("Uploads");
                $upload->init($attachments, [
                    'date'          => false,
                    'multiple'      => true,
                    'max-file-size' => Config::get("options/attachment-max-file-size"),
                    'folder'        => Config::get("pictures/attachment/folder"),
                    'allowed-ext'   => Config::get("options/attachment-extensions"),
                    'file-name'     => "random",
                    'width'         => Config::get("pictures/attachment/sizing/width"),
                    'height'        => Config::get("pictures/attachment/sizing/height"),
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='attachments[]']",
                        'message' => __("website/account_sms/failed-attachment-upload", ['{error}' => $upload->error]),
                    ]));
                $attachments = '';
                if ($upload->operands) $attachments = Utility::jencode($upload->operands);
            } else
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='attachments[]']",
                    'message' => __("website/account_products/send-origin-error2"),
                ]));

            if ($get_countries) {
                foreach ($get_countries as $get_country) {
                    $get_country = Filter::letters($get_country);

                    $data = [
                        'origin_id'   => $origin_id,
                        'ccode'       => $get_country,
                        'attachments' => $attachments,
                        'status'      => "waiting",
                        'cdate'       => DateManager::Now(),
                    ];
                    $this->model->add_origin_prereg_country($data);
                }
            }

            $get_country = current($get_countries);

            Helper::Load(["User", "Notification"]);

            Notification::sms_intl_origin_request_received($udata["id"], $checkOrigin["name"], $get_country);

            User::addAction($udata["id"], "added", "added-new-sms-origin-pre-register-country");

            echo Utility::jencode(['status' => "successful"]);

        }


        private function get_pre_register_countries()
        {
            $countries = Config::get("sms/pre-register-countries");
            $result = [
                'data' => [],
            ];

            $lang = Bootstrap::$lang->clang;

            if ($countries) {
                $countries = strtolower($countries);
                $getCountries = $this->model->get_pre_register_countries($countries, $lang);
                if ($getCountries) {
                    foreach ($getCountries as $country) {
                        $result["data"][] = [
                            'id'   => $country["id"],
                            'code' => strtolower($country["code"]),
                            'name' => $country["name"],
                        ];
                    }
                }
            }
            echo Utility::jencode($result);

        }


        private function add_new_origin()
        {
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $this->takeDatas("language");

            $origin = Filter::init("POST/origin", "noun");
            $legal_acept = Filter::init("POST/origin_legal_acept", "numbers");
            $length = Utility::strlen($origin);

            if (!$legal_acept)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/send-origin-error2"),
                ]));

            if ($length > 11 || $length < 1)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='origin']",
                    'message' => __("website/account_sms/send-origin-error1"),
                ]));

            $udata = UserManager::LoginData("member");

            $added = $this->model->add_new_origin([
                'user_id'     => $udata["id"],
                'pid'         => 0,
                'name'        => $origin,
                'ctime'       => DateManager::Now(),
                'attachments' => null,
                'status'      => "active",
            ]);

            if (!$added)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_sms/send-origin-error3"),
                ]));

            Helper::Load("User");
            User::addAction($udata["id"], "added", "added-new-sms-origin");

            echo Utility::jencode(['status' => "successful"]);
        }


        private function get_origins($uid = 0)
        {
            $lang = Bootstrap::$lang->clang;
            $data = $this->model->get_origins($uid);
            if ($data) {
                $keys = array_keys($data);
                $size = sizeof($keys) - 1;
                for ($i = 0; $i <= $size; $i++) {
                    $var = $data[$keys[$i]];
                    $preregistered = $this->model->get_pre_registereds($var["id"], $lang);
                    $data[$keys[$i]]["prereg"] = $preregistered ? $preregistered : [];
                }
            }
            return $data;
        }


        private function get_origin($id = 0, $uid = 0)
        {
            $data = $this->model->get_origin($id, $uid);
            return $data;
        }


        private function getGroups($uid = 0)
        {
            $data = $this->model->getGroups($uid);
            if ($data) {
                $keys = array_keys($data);
                $size = sizeof($keys) - 1;
                for ($i = 0; $i <= $size; $i++) $data[$keys[$i]]["numbers"] = $data[$keys[$i]]["numbers"] != '' ? explode(",", $data[$keys[$i]]["numbers"]) : [];
            }
            return $data;
        }


        private function get_statistics()
        {
            if (DEMO_MODE) die(Utility::jencode(['status' => "error"]));

            $output = ['status' => "successful"];
            $udata = UserManager::LoginData("member");
            Helper::Load(["User", "Money"]);
            $udata = array_merge($udata, User::getData($udata["id"], "balance,balance_currency", "array"));
            $credit = Money::formatter($udata["balance"], $udata["balance_currency"]);
            $currency = Money::getSymbol($udata["balance_currency"]);

            $output["currency"] = $currency;
            $output["credit"] = $credit;

            $today = DateManager::Now("Y-m-d");
            $yesterday = DateManager::old_date(['day' => 1], "Y-m-d");
            $month = DateManager::Now("Y-m");
            $last_month = DateManager::old_date(['month' => 1], "Y-m");

            $output["statistic_today"] = 0;
            $today_statistic = $this->model->reports_statistic($today, $udata["id"]);
            if ($today_statistic) {
                foreach ($today_statistic as $row) {
                    $data = Utility::jdecode($row["data"], true);
                    $output["statistic_today"] += $data["total_part"];
                }
            }

            $output["statistic_yesterday"] = 0;
            $yesterday_statistic = $this->model->reports_statistic($yesterday, $udata["id"]);
            if ($yesterday_statistic) {
                foreach ($yesterday_statistic as $row) {
                    $data = Utility::jdecode($row["data"], true);
                    $output["statistic_yesterday"] += $data["total_part"];
                }
            }

            $output["statistic_month"] = 0;
            $month_statistic = $this->model->reports_statistic($month, $udata["id"]);
            if ($month_statistic) {
                foreach ($month_statistic as $row) {
                    $data = Utility::jdecode($row["data"], true);
                    $output["statistic_month"] += $data["total_part"];
                }
            }

            $output["statistic_last_month"] = 0;
            $last_month_statistic = $this->model->reports_statistic($last_month, $udata["id"]);
            if ($last_month_statistic) {
                foreach ($last_month_statistic as $row) {
                    $data = Utility::jdecode($row["data"], true);
                    $output["statistic_last_month"] += $data["total_part"];
                }
            }

            $output["statistic_total"] = 0;
            $total_statistic = $this->model->reports_statistic('', $udata["id"]);
            if ($total_statistic) {
                foreach ($total_statistic as $row) {
                    $data = Utility::jdecode($row["data"], true);
                    $output["statistic_total"] += $data["total_part"];
                }
            }

            echo Utility::jencode($output);
        }


        private function get_sms_report()
        {

            $udata = UserManager::LoginData("member");

            $id = (int)Filter::init("POST/id", "numbers");
            $reportd = $this->model->get_report($id, $udata["id"]);
            if (!$reportd) die("Error 1");

            if ($reportd["data"] == null) die("Error 2");
            $reportd["data"] = Utility::jdecode($reportd["data"], true);

            if (!isset($reportd["data"]["module"]) || !$reportd["data"]["module"]) die("Error 3");

            Modules::Load("SMS", $reportd["data"]["module"]);
            $mname = $reportd["data"]["module"];
            $sms = new $mname();

            $report = $sms->getReport($reportd["data"]["report_id"]);
            if ($report) {
                $output = [
                    'status'          => "successful",
                    'conducted_count' => 0,
                    'waiting_count'   => 0,
                    'erroneous_count' => 0,
                    'items'           => [],
                ];

                $conducted = $report["conducted"];
                $waiting = $report["waiting"];
                $erroneous = $report["erroneous"];

                $output["conducted_count"] = $conducted["count"];
                $output["waiting_count"] = $waiting["count"];
                $output["erroneous_count"] = $erroneous["count"];


                $total = ($conducted["count"] + $waiting["count"] + $erroneous["count"]);

                if ($total > 0) {
                    for ($i = 0; $i <= $total; $i++) {
                        if (isset($conducted["data"][$i]) || isset($waiting["data"][$i]) || isset($erroneous["data"][$i])) {
                            $output["items"][] = [
                                'conducted' => isset($conducted["data"][$i]) ? $conducted["data"][$i] : '',
                                'waiting'   => isset($waiting["data"][$i]) ? $waiting["data"][$i] : '',
                                'erroneous' => isset($erroneous["data"][$i]) ? $erroneous["data"][$i] : '',
                            ];
                        }
                    }
                }

                echo Utility::jencode($output);
            }
        }


        private function get_sms_reports()
        {

            $udata = UserManager::LoginData("member");

            $limit = 10;
            $output = [];

            $start = Filter::init("GET/iDisplayStart", "numbers");
            if (!Validation::isInt($start) || $start < 0 || $start > 5000) $start = 0;
            $end = Filter::init("GET/iDisplayLength", "numbers");
            if ($end == -1) $end = 10000;
            elseif (!Validation::isInt($end) || $end < 1) $end = $limit;

            $filteredList = $this->get_reports($udata["id"], $start, $end);
            $filterTotal = $this->model->get_reports_total($udata["id"]);
            $listTotal = $filterTotal;

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            Helper::Load("Money");

            Money::$digit = 4;

            if ($listTotal) {
                $rank = 0;
                if ($filteredList) {
                    foreach ($filteredList as $report) {
                        $rank++;
                        $item = [];

                        $mes = '
                        <p style="display: none" id="show_message_' . $report["id"] . '">
                            ' . $report["content"] . '
                        </p>
                        <a class="lbtn" href="javascript:showMessage(' . $report["id"] . ');void 0;">' . __("website/account_sms/report-message-content") . '</a><br>
                        (' . $report["data"]["part"] . ' SMS - ' . $report["data"]["length"] . ' ' . __("website/account_sms/dimension-character") . ')
                        ';
                        $flags = '';

                        if (isset($report["data"]["countries"]) && $report["data"]["countries"]) {
                            foreach ($report["data"]["countries"] as $country) {
                                $flags .= '<img src="' . View::$init->get_resources_url() . 'assets/images/flags/' . $country["code"] . '.svg" width="20" style="float: left;"> (' . $country["total_part"] . ' SMS - ' . Money::formatter_symbol($country["total_price"], $report["data"]["credit_cid"]) . ')<br>';
                            }
                        }

                        $controls = '<a href="javascript:getReportDetail(' . $report["id"] . ');void 0;" class="lbtn"><i class="fa fa-search"></i></a>';

                        array_push($item, $rank);
                        array_push($item, $report["ctime"]);
                        array_push($item, $report["title"]);
                        array_push($item, $mes);
                        array_push($item, $report["data"]["count"] ?? __("website/others/none"));
                        array_push($item, Money::formatter_symbol($report["data"]["total_credit"], $report["data"]["credit_cid"]));
                        array_push($item, $flags);
                        array_push($item, $controls);

                        $output["aaData"][] = $item;
                    }
                }
            }

            echo Utility::jencode($output);
        }


        private function get_reports($uid = 0, $start = 0, $end = 1)
        {
            $data = $this->model->get_reports($uid, $start, $end);
            if ($data) {
                $keys = array_keys($data);
                $size = sizeof($keys) - 1;
                for ($i = 0; $i <= $size; $i++) {
                    $var = $data[$keys[$i]];

                    if ($e_c = Crypt::decode($var["content"], "*_LOG_*" . Config::get("crypt/system"))) {
                        $var['content'] = $e_c;
                        $data[$keys[$i]]["content"] = $var["content"];
                    }

                    $data[$keys[$i]]["ctime"] = DateManager::format(Config::get("options/date-format") . " - H:i", $var["ctime"]);
                    $data[$keys[$i]]["data"] = Utility::jdecode($var["data"], true);
                }
            }
            return $data;
        }


        private function introduction()
        {
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
            ]);


            Helper::Load("Money");
            Money::$digit = 4;

            $ucid = Money::getUCID();

            $this->addData("ucid", $ucid);
            $this->addData("country_prices", $this->get_country_prices());
            $ipInfo = UserManager::ip_info();
            $default_country = false;
            if ($ipInfo) $default_country = $ipInfo["countryCode"];
            if (!Config::get("sms/country-prices/" . strtoupper($default_country) . "/status")) $default_country = "us";

            $this->addData("default_country", $default_country);
            $this->addData("api_service", Config::get("options/sms-api-service"));
            $this->addData("faq", __("website/account_sms/introduction-faq"));
            $this->addData("content", __("website/account_sms/introduction-content"));

            $this->addData("download_prices", $this->CRLink("download", ["international-sms"]));

            $this->addData("header_background", $this->header_background());
            $this->addData("meta", __("website/account_sms/meta-introduction"));
            $this->addData("header_title", __("website/account_sms/introduction-header-title"));
            $this->addData("header_description", __("website/account_sms/introduction-header-description"));

            $this->addData("social_share", true);


            $lang_list = $this->getData("lang_list");
            $lang_size = $this->getData("lang_count");
            if ($lang_size > 1) {
                $keys = array_keys($lang_list);
                $lang_size -= 1;
                for ($i = 0; $i <= $lang_size; $i++) {
                    if (!$lang_list[$keys[$i]]["selected"]) {
                        $key = $lang_list[$keys[$i]]["key"];
                        $lang_list[$keys[$i]]["link"] = $this->CRLink("international-sms", false, $key);
                    } else
                        $lang_list[$keys[$i]]["link"] = $this->ControllerURI();
                }
                $this->addData("lang_list", $lang_list);
            }


            $this->view->chose("website")->render("international-sms-introduction", $this->data);
        }


        private function get_country_prices()
        {
            $cache = self::$cache;
            $lang = Bootstrap::$lang->clang;
            $cache->setCache("currencies");
            $cname = "sms_country_prices_" . $lang;
            $cache->eraseExpired();
            if (!$cache->isCached($cname) || !Config::get("general/cache")) {
                $data = [];
                $getCountries = Config::get("sms/country-prices");
                $getPreRegister = Config::get("sms/pre-register-countries");
                $getPreRegister = explode(",", $getPreRegister);
                foreach ($getCountries as $cc => $info) {
                    $cclower = strtolower($cc);
                    $prices = [];
                    foreach (Money::getCurrencies() as $currency) {
                        $exch = Money::exChange($info["amount"], $info["cid"], $currency["id"]);
                        $thousands = $exch * 1000;
                        $prices[$currency["code"]] = [
                            'one'       => Money::formatter($exch, $currency["id"]),
                            'thousands' => Money::formatter($thousands, $currency["id"]),
                        ];
                    }
                    $name = AddressManager::get_country_name($cc, $lang);
                    if ($name && isset($info['status']) && $info['status'])
                        $data[] = [
                            'cc'           => $cclower,
                            'name'         => $name,
                            'prices'       => $prices,
                            'pre-register' => in_array($cclower, $getPreRegister),
                        ];
                }
                if (Config::get("general/cache")) $cache->store($cname, $data);
            } else
                $data = $cache->retrieve($cname);
            return $data;
        }


        public function main()
        {
            if (isset($this->params[0]) && $this->params[0] == "introduction")
                return $this->operationMain("introduction");
            $operation = Filter::init("REQUEST/operation", "route");
            if ($operation) return $this->operationMain($operation);

            $this->addData("pname", "account_sms");
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
            $this->addData("meta", __("website/account_sms/meta"));
            $this->addData("header_title", __("website/account_sms/page-title"));

            $breadcrumb = [
                [
                    'link'  => $this->CRLink("my-account"),
                    'title' => __("website/account/breadcrumb-dashboard"),
                ],
                [
                    'link'  => null,
                    'title' => __("website/account_sms/breadcrumb-sms"),
                ],
            ];
            $this->addData("panel_breadcrumb", $breadcrumb);

            $links = [
                'controller' => $this->CRLink("ac-ps-sms"),
                'buy-credit' => $this->CRLink("ac-ps-balance"),
            ];

            $this->addData("links", $links);

            Helper::Load("User");
            $udata = UserManager::LoginData("member");
            $udata = array_merge($udata, User::getData($udata["id"], "balance,balance_currency", "array"));
            $user_balance = Money::formatter_symbol($udata["balance"], $udata["balance_currency"]);

            $this->addData("user_balance", $user_balance);
            $this->addData("dimensions", Config::get("sms/dimensions"));
            $this->addData("origins", $this->get_origins($udata["id"]));
            $this->addData("groups", $this->getGroups($udata["id"]));

            $this->addData("reports", $this->model->get_reports_total($udata["id"]));

            $user_token = Crypt::encode($udata["id"], Config::get("crypt/system"));
            $this->addData("user_token", $user_token);

            $this->view->chose("website")->render("ac-sms", $this->data);

        }
    }