<?php
    defined('CORE_FOLDER') or exit('You can not get in here!');

    class Controller extends Controllers
    {
        protected $params, $data = [];
        private $user = [];


        public function __construct($arg = [])
        {
            parent::__construct();
            $this->params = $arg['params'];

            define("API_CL", true);
        }


        public function main()
        {

            header("Content-Type:application/json");

            $data = file_get_contents("php://input");
            $service = (string)isset($this->params[0]) ? $this->params[0] : null;
            if (!$service) die();
            $param1 = isset($this->params[1]) ? $this->params[1] : false;
            $param2 = isset($this->params[2]) ? $this->params[2] : false;
            $api_actions = Config::get("api-actions");
            $action_cat = ucfirst(Filter::letters_numbers($service));
            $action = ucfirst(Filter::letters_numbers($param1));
            $detectHook = Hook::run("ApiEndpoint", [
                'endpoint' => $this->params,
                'body'     => $data,
            ]);

            if ($detectHook) foreach ($detectHook as $h) if ($h) return true;
            if ($action && $action_cat && isset($api_actions[$action_cat]) && in_array($action, $api_actions[$action_cat])) {
                unset($this->params[0]);
                unset($this->params[1]);
                $this->params = array_values($this->params);
                return $this->api($action_cat, $action, $data);
            }
            if ($service == "sms" && $param1 == "cancel") return $this->sms_cancellation($param2);
            elseif ($service == "sms") return $this->sms($data);
            elseif ($service == "international-sms") return $this->intl_sms($data);
            elseif ($service == "reseller") return $this->reseller($param1, $param2, $data);
            else
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "The specified endpoint is invalid.",
                ]);
        }


        private function intl_sms($data = '')
        {
            $data = Utility::jdecode($data, true);
            if (!$data)
                die(Utility::jencode([
                    'status'  => "error",
                    'errno'   => "01",
                    'message' => "The sent data could not be resolved.",
                ]));

            if (!isset($data["user_token"]) || (isset($data["user_token"]) && !$data["user_token"]))
                die(Utility::jencode([
                    'status'  => "error",
                    'errno'   => "02",
                    'message' => "Please enter user token",
                ]));

            Helper::Load(["User", "Money"]);

            $user_token = Crypt::decode($data["user_token"], Config::get("crypt/system"));

            if (!$user_token)
                die(Utility::jencode([
                    'status'  => "error",
                    'errno'   => "03",
                    'message' => "Invalid user token",
                ]));

            $user_id = (int)$user_token;

            $user = User::getData($user_id, "id,type,status,balance,balance_currency,lang", "array");
            if (!$user || $user["type"] != "member")
                die(Utility::jencode([
                    'status'  => "error",
                    'errno'   => "04",
                    'message' => "Invalid user token",
                ]));

            if (!Config::get("options/international-sms-service") || $user["status"] != "active")
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Access Denied",
                ]));

            $param1 = isset($this->params[1]) ? $this->params[1] : false;

            if ($param1 == "submit") return $this->intl_sms_submit($data, $user);
            elseif ($param1 == "balance") return $this->intl_sms_balance($data, $user);
            elseif ($param1 == "report") return $this->intl_sms_report($data, $user);
            elseif ($param1 == "prices") return $this->intl_sms_prices($data, $user);
            else
                die(Utility::jencode([
                    'status'  => "error",
                    'errno'   => "05",
                    'message' => "Invalid parameter information <" . $param1 . "> ",
                ]));

        }

        private function check_phone_numbers($udata,$origin='',$numbers=0,$message=NULL){
            $message        = Filter::transliterate($message);
            $length         = Utility::strlen($message);

            $result         = [
                'sms_part'  => 0,
                'total_quantity_sent' => 0,
                'total_quantity_sent_sms' => 0,
                'total_quantity_nsent' => 0,
                'total_price' => 0,
                'total_price_symbol' => 0,
                'unknowns'   => [],
                'countries' => [],
                'numbers'   => [],
            ];


            if($numbers && $origin){
                $part               = 1;
                $dimensions         = Config::get("sms/dimensions");
                if($dimensions && is_array($dimensions) && sizeof($dimensions)>0){
                    $last           = end($dimensions);
                    if($length >= $last["end"]){
                        $message	= Utility::substr($message,0,$last["end"]);
                        $part		= $last["part"];
                        $length		= $last["end"];
                    }else{
                        $find       = false;
                        foreach($dimensions AS $dimension){
                            if($length >= $dimension["start"] && $length <= $dimension["end"]){
                                $part = $dimension["part"];
                                $find   = true;
                                break;
                            }
                        }
                        if(!$find) $part = $last["part"];
                    }
                }

                $result["message"] = $message;
                $result["message_length"] = $length;
                $result["sms_part"] = $part;
                $result["origin_name"] = $origin;

                $ucid       = $udata["balance_currency"];
                $preregcs   = explode(",",Config::get("sms/pre-register-countries"));

                Money::$digit=4;

                foreach($numbers AS $number){
                    $number = substr($number,0,20);
                    if(is_string($number) || is_numeric($number)){
                        $validate = Validation::isPhone($number);
                        $data     = $validate ? Filter::phone_smash($number) : [];
                        if($validate && isset($data["cc"])){
                            $code = !is_array($data["code"]) ? [$data["code"]] : $data["code"];
                            foreach($code AS $co){

                                if(!isset($result["countries"][$co])){
                                    $prereg_required = in_array($co,$preregcs);
                                    $result["countries"][$co]["prereg_required"] = $prereg_required;
                                    if($prereg_required){
                                        $check_prereg = $this->model->check_origin_prereg($origin,$co);
                                        $result["countries"][$co]["prereg_status"] = $check_prereg ? "approved" : "unapproved";
                                    }
                                    $country_name = AddressManager::get_country_name($co,$udata["lang"]);
                                    $cou = strtoupper($co);
                                    $prices     = Config::get("sms/country-prices");
                                    if(isset($prices[$cou])){
                                        $price  = $prices[$cou]["amount"];
                                        $curr   = $prices[$cou]["cid"];
                                        $amount = Money::exChange($price,$curr,$ucid);
                                    }else
                                        $amount = 0;

                                    $result["countries"][$co]["code"] = $co;
                                    $result["countries"][$co]["count"] = 1;
                                    $result["countries"][$co]["name"] = $country_name;
                                    $result["countries"][$co]["total_part"] = $part;
                                    $result["countries"][$co]["part1_price"] = $amount;
                                    $result["countries"][$co]["price"] = $amount * $part;
                                    $result["countries"][$co]["price_symbol"] = Money::formatter_symbol($amount,$ucid);
                                    $result["countries"][$co]["total_price"] = $result["countries"][$co]["price"];
                                }else{
                                    $result["countries"][$co]["total_part"] += $part;
                                    $prereg_required = $result["countries"][$co]["prereg_required"];
                                    $result["countries"][$co]["count"] +=1;
                                    $result["countries"][$co]["total_price"] = ($result["countries"][$co]["price"] * $result["countries"][$co]["count"]);
                                }

                                $result["countries"][$co]["numbers"][] = $number;

                                $result["countries"][$co]["total_price_symbol"] = Money::formatter_symbol($result["countries"][$co]["total_price"],$ucid);

                                $result["total_price"] += $result["countries"][$co]["price"];
                                $result["total_quantity_sent"] +=1;
                                $result["total_quantity_sent_sms"] +=$part;
                                $result["numbers"][] = $number;

                            }
                        }else{
                            $result["total_quantity_nsent"] +=1;
                            $result["unknowns"][] = $number;
                        }
                    }
                }

                $result["total_price_symbol"] = Money::formatter_symbol($result["total_price"],$ucid);
                $result["countries"] = array_values($result["countries"]);


            }else{
                $result["status"] = "error";
                $result["message"] = __("website/account_sms/error5");
            }

            return $result;
        }

        private function intl_sms_submit($data = [], $udata = [])
        {
            $this->takeDatas(["language"]);
            Bootstrap::$lang->change($udata["lang"]);
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $message = isset($data["message"]) ? Filter::dtext($data["message"]) : false;
            $numbers = isset($data["numbers"]) ? $data["numbers"] : [];
            $origin = isset($data["origin"]) ? Filter::noun($data["origin"]) : false;
            $result = $this->check_phone_numbers($udata, $origin, $numbers, $message);

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
                'with-api'     => true,
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
            $newrID = LogManager::Sms_Log($udata["id"], "send-sms", $sms->getTitle(), $sms->getBody(), implode(",", $sms->getNumbers()), $data, 0, "international_sms", 0);

            User::addAction($udata["id"], "info", "send-international-sms-with-api");
            echo Utility::jencode([
                'status'    => "successful",
                'report_id' => $newrID,
            ]);
        }

        private function intl_sms_balance($data = [], $udata = [])
        {
            $this->takeDatas(["language"]);
            Bootstrap::$lang->change($udata["lang"]);

            $currency = Money::Currency($udata["balance_currency"], true);
            if (!$currency)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Invalid a currency",
                ]));

            echo Utility::jencode([
                'status'             => "successful",
                'formatted'          => Money::formatter($udata["balance"], $udata["balance_currency"]),
                'formatted_symbolic' => Money::formatter_symbol($udata["balance"], $udata["balance_currency"]),
                'unformatted'        => $udata["balance"],
                'currency'           => $currency["code"],
            ]);
        }

        private function intl_sms_report($data = [], $udata = [])
        {
            $this->takeDatas(["language"]);
            Bootstrap::$lang->change($udata["lang"]);

            $id = (int)isset($data["report_id"]) ? Filter::numbers($data["report_id"]) : 0;
            if (!$id)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Please a enter Report ID",
                ]));

            $reportd = $this->model->get_intl_sms_report($id, $udata["id"]);
            if (!$reportd)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Invalid a Report ID",
                ]));

            if ($reportd["data"] == null)
                die(Utility::jencode([
                    'status'  => "error",
                    'errno'   => "06",
                    'message' => "I have a problem!",
                ]));

            $reportd["data"] = Utility::jdecode($reportd["data"], true);

            if (!isset($reportd["data"]["module"]) || !$reportd["data"]["module"])
                die(Utility::jencode([
                    'status'  => "error",
                    'errno'   => "07",
                    'message' => "I have a problem!",
                ]));

            Modules::Load("SMS", $reportd["data"]["module"]);
            $mname = $reportd["data"]["module"];
            $sms = new $mname();

            $report = $sms->getReport($reportd["data"]["report_id"]);
            if (!$report)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "No return from API.",
                ]));

            $report["status"] = "successful";
            echo Utility::jencode($report);
        }

        private function intl_sms_prices($data = [], $udata = [])
        {
            $this->takeDatas(["language"]);
            Bootstrap::$lang->change($udata["lang"]);
            $result = [
                'status' => "successful",
                'prices' => [],
            ];

            $prices = Config::get("sms/country-prices");

            foreach ($prices as $countryCode => $info) {
                $dat = [];

                $dat["countryCode"] = $countryCode;
                $dat["prices"] = [];
                foreach (Money::getCurrencies() as $currency)
                    $dat["prices"][$currency["code"]] = Money::exChange($info["amount"], $info["cid"], $currency["id"]);
                $result["prices"][] = $dat;
            }
            echo Utility::jencode($result);
        }


        private function sms($data = '')
        {

            $data = Utility::jdecode($data, true);
            if (!$data)
                die(Utility::jencode([
                    'status'  => "error",
                    'errno'   => "01",
                    'message' => "The sent data could not be resolved.",
                ]));

            if (!isset($data["secret_key"]) || (isset($data["secret_key"]) && !$data["secret_key"]))
                die(Utility::jencode([
                    'status'  => "error",
                    'errno'   => "02",
                    'message' => "Please enter secret key",
                ]));

            Helper::Load(["Orders", "User"]);

            $secret_key = Crypt::decode($data["secret_key"], Config::get("crypt/system"));

            if (!$secret_key)
                die(Utility::jencode([
                    'status'  => "error",
                    'errno'   => "03",
                    'message' => "Invalid secret key",
                ]));

            $order_id = (int)$secret_key;

            $order = Orders::get($order_id);
            if (!$order)
                die(Utility::jencode([
                    'status'  => "error",
                    'errno'   => "04",
                    'message' => "Invalid secret key",
                ]));

            if ($order["type"] != "sms" || $order["status"] != "active")
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Access Denied",
                ]));

            $param1 = isset($this->params[1]) ? $this->params[1] : false;

            if ($param1 == "submit") return $this->sms_submit($data, $order);
            elseif ($param1 == "balance") return $this->sms_balance($data, $order);
            elseif ($param1 == "report") return $this->sms_report($data, $order);
            else
                die(Utility::jencode([
                    'status'  => "error",
                    'errno'   => "05",
                    'message' => "Invalid parameter information",
                ]));

        }

        private function sms_balance($data = [], $order = [])
        {
            $this->takeDatas(["language"]);
            Bootstrap::$lang->change(Config::get("general/local"));

            $options = $order["options"];

            Modules::Load("SMS", $order["module"]);
            $config = isset($order["options"]["config"]) ? $order["options"]["config"] : [];

            if (!$config) return false;

            $mname = $order["module"];
            $sms = new $mname($config);
            $balance = $sms->getBalance();

            if (is_bool($balance) && !$balance)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/sms-balance-info-not-available"),
                ]));

            if ($balance != $options["balance"]) {
                $options["balance"] = $balance;
                $options = Utility::jencode($options);
                Orders::set($order["id"], ['options' => $options]);
            }

            echo Utility::jencode([
                'status'  => "successful",
                'balance' => $balance,
            ]);

        }

        private function sms_report($data = [], $order = [])
        {
            $this->takeDatas(["language"]);
            Bootstrap::$lang->change(Config::get("general/local"));
            $id = (int)isset($data["report_id"]) ? Filter::numbers($data["report_id"]) : 0;
            if (!$id)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/error10"),
                ]));
            $reportd = $this->model->get_sms_report($id, $order["id"]);
            if (!$reportd) die();

            Modules::Load("SMS", $order["module"]);
            $config = isset($order["options"]["config"]) ? $order["options"]["config"] : [];

            if (!$config)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/error11"),
                ]));

            $mname = $order["module"];
            $sms = new $mname($config);
            $reportd["data"] = Utility::jdecode($reportd["data"], true);

            $report = $sms->getReport($reportd["data"]["report_id"]);
            if (!$report)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/error11"),
                ]));

            $report["status"] = "successful";

            echo Utility::jencode($report);
        }

        private function sms_submit($data = [], $order = [])
        {
            $this->takeDatas(["language"]);
            Bootstrap::$lang->change(Config::get("general/local"));
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $message = isset($data["message"]) ? Filter::dtext($data["message"]) : false;
            $numbers = isset($data["numbers"]) ? $data["numbers"] : [];
            $origin = isset($data["origin"]) ? Filter::noun($data["origin"]) : false;

            if (Validation::isEmpty($origin))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/preview-error1"),
                ]));

            if (!$numbers)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/preview-error2"),
                ]));

            if (Validation::isEmpty($message))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/preview-error3"),
                ]));

            $getOrigin = $this->model->get_sms_origin($order["id"], $origin);
            if (!$getOrigin)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/preview-error1"),
                ]));

            $black_list = isset($order["options"]["black_list"]) ? $order["options"]["black_list"] : false;
            $black_nums = [];
            if ($black_list != '') {
                $exps = explode(",", $black_list);
                if ($exps && is_array($exps) && sizeof($exps) > 0) {
                    foreach ($exps as $ex) {
                        $ex = Utility::short_text(Filter::numbers($ex), 0, 20);
                        if (substr($ex, 0, 2) == "90") $ex = substr($ex, 2);
                        elseif (substr($ex, 0, 1) == "0") $ex = substr($ex, 1);
                        if ($ex != '' && !in_array($ex, $black_nums)) $black_nums[] = $ex;
                    }
                }
            }


            $nums = [];
            foreach ($numbers as $ex) {
                $ex = Utility::short_text(Filter::numbers($ex), 0, 20);
                if (substr($ex, 0, 2) == "90") $ex = substr($ex, 2);
                elseif (substr($ex, 0, 1) == "0") $ex = substr($ex, 1);
                if ($ex != '' && !in_array($ex, $nums) && !in_array($ex, $black_nums)) $nums[] = $ex;
            }
            $count = sizeof($nums);

            if (!$count)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/preview-error2"),
                ]));

            $credit = 1;
            $length = Utility::strlen($message);
            $dimensions = Bootstrap::$lang->get("sms-settings/dimensions");

            if ($dimensions && is_array($dimensions) && sizeof($dimensions) > 0) {
                $last = end($dimensions);
                if ($length >= $last["end"]) {
                    $message = Utility::short_text($message, 0, $last["end"]);
                    $credit = $last["credit"];
                    $length = $last["end"];
                } else {
                    $find = false;
                    foreach ($dimensions as $dimension) {
                        if ($length >= $dimension["start"] && $length <= $dimension["end"]) {
                            $credit = $dimension["credit"];
                            $find = true;
                            break;
                        }
                    }
                    if (!$find) $credit = $last["credit"];
                }
            }

            $total_credit = $credit * $count;

            Modules::Load("SMS", $order["module"]);
            $mname = $order["module"];
            $sms = new $mname($order["options"]["config"]);

            $balance = $sms->getBalance();

            if (is_bool($balance) && !$balance)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/sms-balance-info-not-available"),
                ]));

            $options = $order["options"];

            if ($balance != $options["balance"]) {
                $options["balance"] = $balance;
                $options = Utility::jencode($options);
                Orders::set($order["id"], ['options' => $options]);
            }


            if ($balance < $total_credit)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/dont-have-sms-credit"),
                ]));

            if (property_exists($sms, "prevent_transmission_to_intl")) $sms->prevent_transmission_to_intl = true;
            $sended = $sms->body($message)->title($origin)->AddNumber($nums)->submit();
            if (!$sended)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/sending-failed", ['{error}' => $sms->getError()]),
                ]));


            $rID = $sms->getReportID();

            $data = Utility::jencode([
                'with-api'     => true,
                'report_id'    => $rID,
                'length'       => $length,
                'count'        => $count,
                'credit'       => $credit,
                'total_credit' => $total_credit,
            ]);
            $newrID = LogManager::Sms_Log($order["owner_id"], "send-sms", $sms->getTitle(), $sms->getBody(), implode(",", $sms->getNumbers()), $data, 0, "users_products", $order["id"]);

            echo Utility::jencode([
                'status'    => "successful",
                'report_id' => $newrID,
            ]);
        }

        private function sms_cancellation($id = 0)
        {
            $id = (int)Filter::numbers($id);
            Helper::Load(["Orders"]);
            $order = Orders::get($id);
            if (!$order || $order["status"] != "active" || $order["type"] != "sms") return false;
            if (Filter::POST()) return $this->sms_cancellation_post($order);

            $this->takeDatas([
                "canonical_link",
                "favicon_link",
                "language",
                "header_type",
            ]);

            $this->view->chose("website")->render("sms-cancellation", $this->data);

        }


        private function reseller($param1 = '', $param2 = '', $data = '')
        {
            header("Content-Type:application/json");
            $this->takeDatas("language");

            if (!Config::get("options/dealership/status") || !Config::get("options/dealership/api")) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This service is disabled",
                ]);
                return false;
            }

            if ($data) {
                $data = Utility::jdecode($data, true);
                if (!$data)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => "The sent data could not be resolved.",
                    ]));
            }
            $api_key = isset($data["key"]) ? $data["key"] : '';

            if (Validation::isEmpty($api_key)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Please define the api key",
                ]);
                return false;
            }
            $user_id = Crypt::decode($api_key, "RESELLER_KEY<(" . Config::get("crypt/system") . ")>RESELLER_KEY");
            if (!$user_id) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Invalid or Incorrect API Key",
                ]);
                return false;
            }
            Helper::Load(["Money", "User", "Invoices", "Products", "Orders", "Events", "Notification"]);
            $this->user = User::getData($user_id, "id,name,surname,full_name,company_name,ip,email,lang,country,balance,balance_currency,status", "array");
            if (!$this->user) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Invalid or Incorrect API Key",
                ]);
                return false;
            }

            if ($this->user["status"] != "active") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Your account is not active",
                ]);
                return false;
            }

            $this->user = array_merge($this->user, User::getInfo($user_id, ["dealership", "taxation"]));
            $dealership = Utility::jdecode($this->user["dealership"], true);
            if (!$dealership || $dealership["status"] != "active") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/api/tx1", false, $this->user["lang"]),
                ]);
                return false;
            }
            $rates = (array)Config::get("options/dealership/rates");
            if (isset($dealership["discounts"])) {
                $u_rates = $dealership["discounts"];
                if ($u_rates && is_array(current($u_rates))) $rates = array_replace_recursive($rates, $u_rates);
                unset($dealership["discounts"]);
            }
            $dealership = array_replace_recursive((array)Config::get("options/dealership"), $dealership);
            $dealership["rates"] = $rates;

            $this->user["dealership"] = $dealership;
            $this->user["address"] = AddressManager::getAddress(0, $this->user["id"]);

            Bootstrap::$lang->change($this->user["lang"]);

            $endpoint = $this->params;
            unset($endpoint[0]);
            $endpoint = array_values($endpoint);

            $detectHook = Hook::run("ResellerApiEndpoint", [
                'endpoint' => $endpoint,
                'body'     => $data,
                'user'     => $this->user,
            ]);

            if ($detectHook) foreach ($detectHook as $h) if ($h) return true;
            if ($param1 == "me") return $this->reseller_me();
            elseif ($param1 == "products") return $this->reseller_products($data);
            elseif ($param1 == "domain-availability-check") return $this->reseller_domain_availability_check($data);
            elseif ($param1 == "buy") return $this->reseller_buy($data);
            elseif ($param1 == "order") return $this->reseller_order($param2, $data);
            else {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Incorrect endpoint",
                ]);
            }

            return true;
        }

        private function reseller_me()
        {
            $currency = Money::Currency($this->user["balance_currency"]);
            echo Utility::jencode([
                'status'            => "successful",
                'balance'           => $this->user["balance"],
                'currency'          => $currency["code"],
                'balance_formatted' => Money::formatter_symbol($this->user["balance"], $this->user["balance_currency"]),
            ]);
        }

        private function reseller_products($options = [])
        {
            Helper::Load(["Products", "Money", "Orders"]);

            $countryCode = $this->user["address"]["country_id"] ?? 0;
            $city = $this->user["address"]["city_id"] ?? ($this->user["address"]["city"]);
            $taxation = Invoices::getTaxation($countryCode, $this->user["taxation"] ?? null);
            $tax_rate = Invoices::getTaxRate($countryCode, $city, $this->user["id"]);
            $taxation_type = Invoices::getTaxationType();
            $balance_txn = Config::get("options/balance-taxation") == "n";


            $products = [];

            $gTypeS = $options["types"];
            $gTypeIds = $options["type_ids"];
            $typeList = [];

            if ($gTypeS && !is_array($gTypeS)) $gTypeS = [];

            if (Config::get("options/pg-activation/domain") && (!$gTypeS || in_array('domain', $gTypeS)))
                $typeList["domain"] = "all";
            if (Config::get("options/pg-activation/hosting") && (!$gTypeS || in_array('hosting', $gTypeS)))
                $typeList["hosting"] = "all";
            if (Config::get("options/pg-activation/server") && (!$gTypeS || in_array('server', $gTypeS)))
                $typeList["server"] = "all";
            if (Config::get("options/pg-activation/software") && (!$gTypeS || in_array('software', $gTypeS)))
                $typeList["software"] = "all";
            if (Config::get("options/pg-activation/sms") && (!$gTypeS || in_array('sms', $gTypeS)))
                $typeList["sms"] = "all";
            $groups = Products::special_groups(false, 't1.status,t1.visibility,t1.id');
            if ($groups)
                foreach ($groups as $g)
                    if ($g["status"] == "active" && $g["visibility"] == "visible" && (!$gTypeS || (in_array('special', $gTypeS) && in_array($g["id"], $gTypeIds))))
                        $typeList["special"][] = $g["id"];

            if ($typeList) {
                foreach ($typeList as $k => $v) {
                    if ($k == "special") {
                        foreach ($v as $g)
                            $products = array_merge($products, Products::get_products('special', -1, $g));
                    } else
                        $products = array_merge($products, Products::get_products($k));
                }
            }

            $result = [
                'status' => 'successful',
                'data'   => [],
            ];
            if ($products) {

                if (isset($typeList["domain"])) {
                    $main_whidden_amount = Config::get("options/domain-whois-privacy/amount");
                    $main_whidden_cid = Config::get("options/domain-whois-privacy/cid");
                }

                $dealership = $this->user["dealership"] ?? [];
                $quantity = sizeof(User::dealership_orders($this->user["id"], $dealership["rates"]));

                foreach ($products as $p) {
                    $p = Products::get($p["type"], $p["id"], false, 'active');
                    if (!$p) continue;
                    $res = [
                        'type'          => $p["type"],
                        'type_id'       => $p["type_id"],
                        'id'            => $p["id"],
                        'category_name' => isset($p["category_title"]) ? $p["category_title"] : '',
                        'category_id'   => isset($p["category"]) ? (int)$p["category"] : 0,
                        'stock'         => isset($p["haveStock"]) ? (int)$p["haveStock"] : 0,
                        'name'          => isset($p["name"]) ? $p["name"] : $p["title"],
                        'pricing'       => [],
                    ];
                    if ($p["type"] != "software") $res["features"] = isset($p["features"]) ? $p["features"] : '';
                    if (isset($p["options"]["popular"])) $res["popular"] = $p["options"]["popular"] ? true : false;

                    if ($p["type"] == "domain") {
                        $res = [
                            'type'          => $p["type"],
                            'id'            => $p["id"],
                            'name'          => $p["name"],
                            'min_years'     => $p["min_years"],
                            'max_years'     => $p["max_years"],
                            'epp_code'      => (bool)$p["epp_code"],
                            'whois_privacy' => (bool)$p["whois_privacy"],
                            'documents'     => Orders::detect_docs_in_domain(false, $p),
                            'pricing'       => [],
                        ];
                    } elseif ($p["type"] == "hosting") {
                        if (isset($p["options"]["disk_limit"]) && $p["options"]["disk_limit"])
                            $res["disk_limit"] = $p["options"]["disk_limit"];
                        if (isset($p["options"]["bandwidth_limit"]) && $p["options"]["bandwidth_limit"])
                            $res["bandwidth_limit"] = $p["options"]["bandwidth_limit"];
                        if (isset($p["options"]["email_limit"]) && $p["options"]["email_limit"])
                            $res["email_limit"] = $p["options"]["email_limit"];
                        if (isset($p["options"]["database_limit"]) && $p["options"]["database_limit"])
                            $res["database_limit"] = $p["options"]["database_limit"];
                        if (isset($p["options"]["addons_limit"]) && $p["options"]["addons_limit"])
                            $res["addons_limit"] = $p["options"]["addons_limit"];
                        if (isset($p["options"]["subdomain_limit"]) && $p["options"]["subdomain_limit"])
                            $res["subdomain_limit"] = $p["options"]["subdomain_limit"];
                        if (isset($p["options"]["ftp_limit"]) && $p["options"]["ftp_limit"])
                            $res["ftp_limit"] = $p["options"]["ftp_limit"];
                        if (isset($p["options"]["park_limit"]) && $p["options"]["park_limit"])
                            $res["park_limit"] = $p["options"]["park_limit"];
                        if (isset($p["options"]["max_email_per_hour"]) && $p["options"]["max_email_per_hour"])
                            $res["max_email_per_hour"] = $p["options"]["max_email_per_hour"];
                        if (isset($p["options"]["cpu_limit"]) && $p["options"]["cpu_limit"])
                            $res["cpu_limit"] = $p["options"]["cpu_limit"];
                        if (isset($p["options"]["server_features"]) && $p["options"]["server_features"])
                            $res["server_features"] = $p["options"]["server_features"];
                    } elseif ($p["type"] == "server") {
                        if (isset($p["options"]["processor"]) && $p["options"]["processor"])
                            $res["processor"] = $p["options"]["processor"];
                        if (isset($p["options"]["ram"]) && $p["options"]["ram"])
                            $res["ram"] = $p["options"]["ram"];
                        if (isset($p["options"]["disk-space"]) && $p["options"]["disk-space"])
                            $res["disk_space"] = $p["options"]["disk-space"];
                        if (isset($p["options"]["bandwidth"]) && $p["options"]["bandwidth"])
                            $res["bandwidth"] = $p["options"]["bandwidth"];
                        if (isset($p["optionsl"]["location"]) && $p["optionsl"]["location"])
                            $res["location"] = $p["optionsl"]["location"];
                    }


                    if (isset($p["price"]) && $p["price"]) {

                        $d = Products::find_in_rates($p, $dealership["rates"], $quantity);
                        $dRate = $d ? $d["rate"] : 0;

                        foreach ($p["price"] as $k => $pr) {
                            $amount = $pr["amount"];

                            if ($p["type"] == "domain") {
                                if ($k == "register" || $k == "transfer") {
                                    $res["pricing"][$k]["promo_status"] = false;
                                    $res["pricing"][$k]["promo_expiry"] = "0000-00-00";
                                    $res["pricing"][$k]["promo_amount"] = 0;
                                }

                                $pr["period"] = "year";
                                $pr["time"] = 1;

                                if ($p["promo_status"] == 1 && ($k == "register" || $k == "transfer")) {
                                    $promo_dueDate = $p["promo_duedate"];
                                    $promo_due = substr($promo_dueDate, 0, 4) > 2000;
                                    if (!$promo_due) $promo_dueDate = "0000-00-00";

                                    $promo = !$promo_due || (DateManager::strtotime($promo_dueDate) > DateManager::strtotime());

                                    if ($promo && ($p["promo_" . $k . "_price"] > 0.00 || (Config::get("options/domain-promotion-free") && $p["promo_" . $k . "_price"] < 0.01))) {
                                        $promo_amount = Money::exChange($p["promo_" . $k . "_price"], $p["currency"], $pr["cid"]);
                                        if ($promo_amount > 0.00) {
                                            if ($taxation && $tax_rate > 0.00 && $taxation_type == "exclusive" && $balance_txn)
                                                $promo_amount += Money::get_exclusive_tax_amount($promo_amount, $tax_rate);
                                            if ($taxation && $tax_rate > 0.00 && $taxation_type == "inclusive" && !$balance_txn)
                                                $promo_amount -= Money::get_inclusive_tax_amount($promo_amount, $tax_rate);
                                            if (!$taxation && $tax_rate > 0.00 && $taxation_type == "inclusive")
                                                $promo_amount -= Money::get_inclusive_tax_amount($promo_amount, $tax_rate);

                                            if ($dRate > 0.00) $promo_amount -= Money::get_discount_amount($promo_amount, $dRate);

                                            $promo_amount = Money::exChange($promo_amount, $pr["cid"], $this->user["balance_currency"]);
                                            $promo_amount = round($promo_amount, 2);
                                        }


                                        $res["pricing"][$k]["promo_status"] = true;
                                        $res["pricing"][$k]["promo_expiry"] = $promo_dueDate;
                                        $res["pricing"][$k]["promo_amount"] = $promo_amount;
                                    }
                                }
                            }

                            if ($amount > 0.00) {
                                if ($taxation && $tax_rate > 0.00 && $taxation_type == "exclusive" && $balance_txn)
                                    $amount += Money::get_exclusive_tax_amount($amount, $tax_rate);
                                if ($taxation && $tax_rate > 0.00 && $taxation_type == "inclusive" && !$balance_txn)
                                    $amount -= Money::get_inclusive_tax_amount($amount, $tax_rate);
                                if (!$taxation && $tax_rate > 0.00 && $taxation_type == "inclusive")
                                    $amount -= Money::get_inclusive_tax_amount($amount, $tax_rate);

                                if ($dRate > 0.00) $amount -= Money::get_discount_amount($amount, $dRate);


                                $amount = Money::exChange($amount, $pr["cid"], $this->user["balance_currency"]);
                                $amount = round($amount, 2);
                            }

                            $res["pricing"][$k]["id"] = $pr["id"];
                            $res["pricing"][$k]["period"] = $pr["period"];
                            $res["pricing"][$k]["period_duration"] = $pr["time"];
                            $res["pricing"][$k]["amount"] = $amount;
                            $res["pricing"][$k]["currency"] = (Money::Currency($this->user["balance_currency"]))["code"];
                            $res["pricing"][$k]["format"] = Money::formatter_symbol($amount, $this->user["balance_currency"]);
                        }

                        if ($p["module"] && $p["module"] != "none") {
                            $md = Modules::Load("Registrars", $p["module"]);
                            $mdc = $md["config"];

                            $whidden_amount = $mdc["settings"]["whidden-amount"] ?? 0;
                            $whidden_cid = $mdc["settings"]["whidden-currency"] ?? 4;

                        } else {
                            $whidden_amount = $main_whidden_amount;
                            $whidden_cid = $main_whidden_cid;
                        }

                        $whidden_price = $whidden_amount;

                        if ($whidden_amount > 0.00 && $balance_txn && $taxation_type == "exclusive" && $taxation && $tax_rate > 0.00) $whidden_price += Money::get_tax_amount($whidden_amount, $tax_rate);

                        if ($whidden_price > 0.00)
                            $whidden_price = Money::exChange($whidden_price, $whidden_cid, $this->user["balance_currency"]);
                        $res["pricing"]["whois_privacy"] = [
                            'id'              => 0,
                            'period'          => $whidden_price > 0.00 ? 'year' : 'none',
                            'period_duration' => $whidden_price > 0.00 ? 1 : 0,
                            'amount'          => $whidden_price,
                            'currency'        => (Money::Currency($this->user["balance_currency"]))["code"],
                            'format'          => Money::formatter_symbol($whidden_price, $this->user["balance_currency"]),
                        ];
                    }


                    $result["data"][] = $res;
                }
            }

            echo Utility::jencode($result);
        }

        private function reseller_domain_availability_check($data = [])
        {
            Helper::Load(["Registrar", "Money", "Products"]);

            $sld = Filter::domain($data["sld"] ?? false);
            $tlds = $data["tlds"] ?? [];
            if (!is_array($tlds)) $tlds = [$tlds];
            $tlds = array_map(function ($e) {
                return Filter::letters_numbers($e);
            }, $tlds);
            if ($tlds) {
                foreach ($tlds as $t) {
                    $p = Products::get("domain", $t, false, 'active');
                    if (!$p) {
                        echo Utility::jencode([
                            'status'  => "error",
                            'message' => $t . " domain name extension is not supported.",
                        ]);
                    }
                }
            }

            if (Validation::isEmpty($sld) || Utility::strlen($sld) >= 150 || sizeof($tlds) < 1)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/domain/error1"),
                ]));

            $check = Registrar::check($sld, $tlds);

            echo Utility::jencode([
                'status' => "successful",
                'data'   => $check ?: [],
            ]);

        }

        private function reseller_buy($data = [])
        {
            $p_type = Filter::letters(isset($data["type"]) ? $data["type"] : '');
            $p_id = isset($data["id"]) ? $data["id"] : 0;
            $period = Filter::letters(isset($data["period_type"]) ? $data["period_type"] : '');
            $duration = (int)(isset($data["period_duration"]) ? $data["period_duration"] : 0);
            $options = (array)(isset($data["options"]) ? $data["options"] : []);
            $dealership = $this->user["dealership"];


            if (!in_array($p_type, ['domain', 'hosting', 'server', 'software', 'sms', 'special'])) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/api/tx2", false, $this->user["lang"]),
                ]);
                return false;
            }

            if ($p_type == "domain") Helper::Load("Registrar");
            if ($p_type == "domain" && !Validation::isInt($p_id)) {
                $getTLD = Registrar::get_tld($p_id, 'id');
                if ($getTLD) $p_id = (int)$getTLD["id"];
            }
            if (!Validation::isInt($p_id)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Invalid product id",
                ]);
                return false;
            }


            $product = Products::get($p_type, $p_id, $this->user["lang"], "active");

            if (!$product) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/api/tx3", false, $this->user["lang"]),
                ]);
                return false;
            }

            $s = [];

            if ($p_type == "domain") {
                if ($duration < 1) $duration = 1;

                $is_transfer = $options["transfer"] ?? false;
                $s = $product["price"][$is_transfer ? "transfer" : "register"];

                $domain = Filter::domain($options["domain"] ?? '');
                $tld = $product["name"];
                $sld = $domain ? rtrim($domain, "." . $tld) : '';

                $check = $domain ? Registrar::check($sld, [$tld]) : false;
                if (!$check) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/domain/error3", false, $this->user["lang"]),
                    ]);
                    return false;
                }
                $check = $check[$domain] ?? [];
                $available = $check && ($check["status"] ?? 'unavailable') == "available";
                $premium = $check && $check["premium"] ?? false;

                if ($premium || (!$available && !$is_transfer)) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => "Check: " . __("admin/orders/create-domain-status-unavailable", false, $this->user["lang"]),
                    ]);
                    return false;
                }
                if ($available && $is_transfer) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/domain/error8", false, $this->user["lang"]),
                    ]);
                    return false;
                }
            } else {
                if (isset($product["price"]) && $product["price"])
                    foreach ($product["price"] as $k => $v)
                        if (!$s && $v["period"] == $period && $v["time"] == $duration) $s = $v;
            }

            if (!$s) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/api/tx4", false, $this->user["lang"]),
                ]);
                return false;
            }

            if ($dealership["require_min_discount_amount"] > 0.00) {
                $rqmcdt = $dealership["require_min_discount_amount"];
                $rqmcdt_cid = $dealership["require_min_discount_cid"];
                $myBalance = Money::exChange($this->user["balance"], $this->user["balance_currency"], $rqmcdt_cid);
                if ($myBalance < $rqmcdt) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/api/tx5", false, $this->user["lang"]),
                    ]);
                    return false;
                }
            }

            $s_amount = $s["amount"];

            if ($p_type == "domain") {
                $promo_dueDate = $product["promo_duedate"];
                $promo = (substr($promo_dueDate, 0, 4) > 2020 && DateManager::strtotime($promo_dueDate) > DateManager::strtotime());
                $dte = ($is_transfer ? "transfer" : "register");
                if ($promo && ($product["promo_" . $dte . "_price"] > 0.00 || (Config::get("options/domain-promotion-free") && $product["promo_" . $dte . "_price"] < 0.01)) && $duration < 2)
                    $s_amount = Money::exChange($product["promo_" . $dte . "_price"], $product["currency"], $s["cid"]);
                $s_amount = $s_amount * $duration;
            }

            $balance_txn = Config::get("options/balance-taxation") == "n";
            $quantity = sizeof(User::dealership_orders($this->user["id"], $dealership["rates"]));
            $d = Products::find_in_rates($product, $dealership["rates"], $quantity);
            $price = Money::exChange($s_amount, $s["cid"], $this->user["balance_currency"]);
            $taxation_type = Invoices::getTaxationType();
            $city_id = isset($this->user["address"]["city_id"]) ? $this->user["address"]["city_id"] : 0;
            $country_id = isset($this->user["address"]["country_id"]) ? $this->user["address"]["country_id"] : 0;
            if (!$country_id) $country_id = $this->user["country"];
            $tax_rate = Invoices::getTaxRate($country_id, $city_id, $this->user["id"]);
            $taxation = Invoices::getTaxation($country_id, $this->user["taxation"]);
            $local = Invoices::isLocal($country_id, $this->user["id"]);
            $legal = (int)$balance_txn;
            $adds_amount = 0;
            $discounts = [];

            if ($taxation_type == "inclusive" && $balance_txn) $price -= Money::get_inclusive_tax_amount($price, $tax_rate);

            $including_price = $price;

            if ($d) {
                $dRate = $d["rate"];
                $amountd = round(Money::get_discount_amount($price, $dRate), 2);
                $including_price -= $amountd;

                $discounts["items"]["dealership"] = [];
                $discounts["items"]["dealership"][] = [
                    'dkey'    => $d["k"],
                    'name'    => $d["name"],
                    'rate'    => $dRate,
                    'amount'  => Money::formatter_symbol($amountd, $this->user["balance_currency"]),
                    'amountd' => $amountd,
                ];
            }

            $items = [];

            $_options = [
                'type'           => $p_type,
                'id'             => $p_id,
                'selection'      => $s,
                'category'       => '',
                'category_route' => '',
                'period'         => $period,
                'period_time'    => $duration,
            ];

            if ($p_type == "software") $_options["event"] = "SoftwareOrder";
            elseif ($p_type == "hosting") $_options["event"] = "HostingOrder";
            elseif ($p_type == "server") $_options["event"] = "ServerOrder";
            elseif ($p_type == "sms") $_options["event"] = "SmsProductOrder";
            elseif ($p_type == "special") $_options["event"] = "SpecialProductOrder";
            elseif ($p_type == "domain") $_options["event"] = $is_transfer ? "DomainNameTransferRegisterOrder" : "DomainNameRegisterOrder";

            if (isset($options["domain"]) && $options["domain"])
                $_options["domain"] = Filter::domain($options["domain"]);

            if (isset($options["ip"]) && $options["ip"])
                $_options["ip"] = Filter::ip($options["ip"]);

            if (isset($options["hostname"]) && $options["hostname"])
                $_options["hostname"] = Filter::domain($options["hostname"]);

            if (isset($options["password"]) && $options["password"])
                $_options["password"] = Filter::password($options["password"]);

            if ($p_type == "domain") {
                $opDons = array_values($options["nameservers"] ?? []);
                $getDns = $opDons ?: Config::get("options/ns-addresses");
                $_options["category"] = __("website/osteps/category-domain", false, $this->user["lang"]);
                $_options["category_route"] = $this->CRLink("domain");
                $_options["sld"] = $sld;
                $_options["tld"] = $tld;
                if ($getDns) for ($i = 0; $i <= sizeof($getDns) - 1; $i++) $_options["dns"]["ns" . ($i + 1)] = $getDns[$i];
                $_options["wprivacy"] = $options["whois_privacy"] ?? false;
                if ($options["epp_code"] ?? false) $_options["tcode"] = (string)$options["epp_code"] ?? '';
                $whois = [];
                if ($options["whois"] ?? [] && is_array($options["whois"])) {
                    $contact_types = [
                        'registrant',
                        'administrative',
                        'technical',
                        'billing',
                    ];

                    foreach ($contact_types as $ct) {
                        $cd = $options["whois"][$ct] ?? false;
                        $full_name = Filter::html_clear($cd["FirstName"] . " " . $cd["LastName"]);
                        $company_name = Filter::html_clear($cd["Company"] ?? '');
                        $email = Filter::email($cd["EMail"]);
                        $pcountry_code = Filter::numbers($cd["PhoneCountryCode"] ?? '');
                        $phone = Filter::numbers($cd["Phone"] ?? '');
                        $fcountry_code = Filter::numbers($cd["FaxCountryCode"] ?? '');
                        $fax = Filter::numbers($cd["Fax"] ?? '');
                        $address = Filter::html_clear($cd["AddressLine1"] . ($cd["AddressLine2"] ? " " . $cd["AddressLine2"] : ''));
                        $city = Filter::html_clear($cd["City"]);
                        $state = Filter::html_clear($cd["State"] ?? '');
                        $zipcode = Filter::html_clear($cd["ZipCode"] ?? '');
                        $country_code = Filter::letters($cd["Country"] ?? '');

                        $full_name = htmlentities($full_name, ENT_QUOTES);
                        $company_name = htmlentities($company_name, ENT_QUOTES);
                        $email = htmlentities($email, ENT_QUOTES);
                        $pcountry_code = htmlentities($pcountry_code, ENT_QUOTES);
                        $phone = htmlentities($phone, ENT_QUOTES);
                        $fcountry_code = htmlentities($fcountry_code, ENT_QUOTES);
                        $fax = htmlentities($fax, ENT_QUOTES);
                        $address = htmlentities($address, ENT_QUOTES);
                        $city = htmlentities($city, ENT_QUOTES);
                        $state = htmlentities($state, ENT_QUOTES);
                        $zipcode = htmlentities($zipcode, ENT_QUOTES);
                        $country_code = htmlentities($country_code, ENT_QUOTES);
                        if (
                            Validation::isEmpty($full_name) ||
                            Validation::isEmpty($email) ||
                            Validation::isEmpty($pcountry_code) ||
                            Validation::isEmpty($phone) ||
                            Validation::isEmpty($address) ||
                            Validation::isEmpty($city) ||
                            Validation::isEmpty($state) ||
                            Validation::isEmpty($zipcode) ||
                            Validation::isEmpty($country_code)
                        ) {
                            echo Utility::jencode([
                                'status'  => "error",
                                'message' => __("website/account_products/modify-whois-error1", false, $this->user["lang"]),
                            ]);
                            return false;
                        }

                        if (!Validation::isEmail($email)) {
                            echo Utility::jencode([
                                'status'  => "error",
                                'message' => __("website/account_products/modify-whois-error2"),
                            ]);
                            return false;
                        }

                        $names = Filter::name_smash($full_name);
                        $first_name = $names["first"];
                        $last_name = $names["last"];

                        if (Utility::strlen($last_name) < 1) {
                            echo Utility::jencode([
                                'status'  => "error",
                                'message' => __("website/account_products/modify-whois-error1"),
                            ]);
                            return false;
                        }

                        if (Utility::strlen($address) > 64) {
                            $address1 = Utility::short_text($address, 0, 64);
                            $address2 = Utility::short_text($address, 64, 64);
                        } else {
                            $address1 = $address;
                            $address2 = null;
                        }

                        $whois[$ct] = [
                            'Name'             => $full_name,
                            'FirstName'        => $first_name,
                            'LastName'         => $last_name,
                            'Company'          => $company_name,
                            'Address'          => $address,
                            'AddressLine1'     => $address1,
                            'AddressLine2'     => $address2,
                            'ZipCode'          => $zipcode,
                            'State'            => $state,
                            'City'             => $city,
                            'Country'          => $country_code,
                            'Phone'            => $phone,
                            'Fax'              => $fax,
                            'EMail'            => $email,
                            'FaxCountryCode'   => $fcountry_code,
                            'PhoneCountryCode' => $pcountry_code,
                        ];
                    }
                }
                if ($whois) $_options["whois"] = $whois;
                $product["title"] = $domain;
                $require_docs = Orders::detect_docs_in_domain(false, $product);
                $get_docs = $options["docs"] ?? [];
                if ($require_docs) {
                    foreach ($require_docs as $dk => $di) {
                        $file_data = [];
                        $module_data = [];

                        $value = $get_docs[$dk] ?? false;
                        $select_value = $value;
                        $required = $requirement["required"] ?? false;
                        $file_data = null;

                        if ($di['type'] != "file")
                            $value = Filter::quotes(Filter::html_clear($value));

                        if (is_array($required) && $required) {
                            $required_fields = $required;
                            $required = false;

                            $pref = explode("_", $dk)[0] ?? false;
                            foreach ($required_fields as $target_f_id => $search_values) {
                                if ($required) continue;

                                $ptf = $pref . "_" . $target_f_id;
                                if (isset($require_docs[$ptf])) {
                                    $notEmpty = false;

                                    if (!is_array($search_values) && $search_values == "NOT_EMPTY") $notEmpty = true;
                                    if (!is_array($search_values)) $search_values = [$search_values];

                                    $target_f = $require_docs[$ptf];
                                    $target_value = $get_docs[$ptf] ?? false;
                                    $target_type = $target_f["type"];

                                    if ($target_type != 'file')
                                        $target_value = Filter::quotes(Filter::html_clear($target_value));

                                    if (!$notEmpty && $target_type == "select" || $target_type == "text") {
                                        if (in_array($target_value, $search_values)) $required = true;
                                    } elseif (strlen($target_value) > 0) $required = true;
                                }
                            }
                        }

                        if ($required && (($di["type"] == "file" && !$value) || ($di["type"] != "file" && Utility::strlen($value) < 1))) {
                            echo Utility::jencode([
                                'status'  => "error",
                                'message' => __("website/osteps/field-required", ['{name}' => $di["name"]]),
                            ]);
                            return false;
                        }

                        if ($di["type"] == "select" && strlen($value) > 0) {
                            if (!isset($di["options"][$value])) {
                                echo Utility::jencode([
                                    'status'  => "error",
                                    'message' => __("website/osteps/field-required", ['{name}' => $di["name"]]),
                                ]);
                                return false;
                            }
                            $select_value = RegistrarModule::get_doc_lang($di["options"][$value]);
                        } elseif ($di["type"] == "file" && $value) {
                            $exts = $di["allowed_ext"] ?? '';
                            if (!$exts) $exts = Config::get("options/product-fields-extensions");
                            $exts = $exts ? array_map('trim', explode(',', $exts)) : ['.jpg'];
                            $max_file_size = (int)($di["max_file_size"] ?? 3);

                            $max_file_size = FileManager::converByte($max_file_size . "MB");

                            $upload_folder = ROOT_DIR . "temp" . DS;
                            $upload = false;

                            if (is_array($value) && isset($value["name"]) && isset($value["data"]) && $value["name"] && $value["data"]) {
                                $file_name = $value["name"] ?? '';
                                $file_data = base64_decode($value["data"] ?? '');
                                $extension = explode(".", $file_name);
                                $extension = end($extension);

                                if ($extension && in_array("." . $extension, $exts) && $file_data) {
                                    $file_size = strlen($file_data);

                                    if ($file_size <= $max_file_size) {
                                        $new_file_name = strtolower(substr(md5(uniqid(rand())), 0, 23)) . "." . $extension;
                                        $upload = FileManager::file_write($upload_folder . $new_file_name, $file_data);
                                        if (!$upload)
                                            $error = "Failed upload";
                                    } else
                                        $error = "Maximum file size exceeded. " . $file_size . ' <=> ' . $max_file_size . ' -- ' . $file_data;
                                } else
                                    $error = "." . $extension . " file extension is not supported";
                            } else
                                $error = "Failed upload";

                            if (!$upload) {
                                echo Utility::jencode([
                                    'status'  => "error",
                                    'message' => $di["name"] . ": " . __("website/osteps/failed-field-upload", ['{error}' => $error]),
                                ]);
                                return false;
                            }
                            $file_data = [
                                'name'       => $file_name,
                                'local_name' => $new_file_name,
                                'path'       => $upload_folder . $new_file_name,
                                'size'       => $file_size,
                            ];
                        }

                        if ($di["type"] != "file" && substr($dk, 0, 4) == "mod_" && strlen($value) > 0) {
                            $mod_k = substr($dk, 4);
                            $module_data = ['key' => $mod_k];

                            if ($di["type"] == "text") $module_data["value"] = $value;
                            elseif ($di["type"] == "select") $module_data["value"] = $value;
                            elseif ($di["type"] == "file") $module_data["value"] = $file_data["path"];
                        }

                        if ($di["type"] != "file" && strlen($value) < 1) continue;

                        $_options["docs"][$dk] = [
                            'name'        => $di['name'],
                            'value'       => $file_data ? '' : $select_value,
                            'module_data' => $module_data,
                            'file'        => $file_data,
                        ];
                    }
                }

                if ($_options["wprivacy"]) {

                    if ($product["module"] != "none") {
                        $rgstrModule = Modules::Load("Registrars", $product["module"], true);
                        $whidden_amount = $rgstrModule["config"]["settings"]["whidden-amount"];
                        $whidden_cid = $rgstrModule["config"]["settings"]["whidden-currency"];
                    } else {
                        $whidden_amount = Config::get("options/domain-whois-privacy/amount");
                        $whidden_cid = Config::get("options/domain-whois-privacy/cid");
                    }

                    if ($whidden_amount > 0.00) {
                        $whidden_price = Money::exChange($whidden_amount, $whidden_cid, $this->user["balance_currency"]);
                        if ($taxation_type == "inclusive")
                            $whidden_price -= Money::get_inclusive_tax_amount($whidden_price, $tax_rate);

                        $whidden_price_tax = Money::get_exclusive_tax_amount($whidden_price, $tax_rate);
                        $adds_amount += $whidden_price;

                        $whidden_period = "year";
                        $whidden_period_time = 1;
                    } else {
                        $whidden_price = 0;
                        $whidden_price_tax = 0;
                        $whidden_period = "none";
                        $whidden_period_time = 0;
                    }
                    $_options["addon_items"] = [
                        [
                            'product_type' => "addon",
                            'product_id'   => "whois-privacy",
                            'option_id'    => 0,
                            'period'       => $whidden_period,
                            'period_time'  => $whidden_period_time,
                            'name'         => __("admin/orders/whois-privacy-invoice-description", ['{name}' => $product["title"]]),
                            'amount'       => $whidden_price,
                            'tax_included' => $whidden_price + $whidden_price_tax,
                            'tax_exempt'   => 0,
                            'currency'     => $this->user["balance_currency"],
                        ],
                    ];
                }
            }

            $items[] = [
                'id'                        => rand(10000, 99999),
                'unique'                    => md5(rand(10000, 99999)),
                'name'                      => $product["title"],
                'options'                   => $_options,
                'plural'                    => false,
                'quantity'                  => 1,
                'amount_including_discount' => $including_price,
                'adds_amount'               => $adds_amount,
                'amount'                    => $price,
                'total_amount'              => $price + $adds_amount,
            ];

            $subtotal = $price + $adds_amount;
            $tax = 0;
            $total = $including_price + $adds_amount;

            if ($taxation && $balance_txn && $tax_rate > 0.00) {
                $legal = 1;
                if (!($product["taxexempt"] ?? 0))
                    $tax += Money::get_tax_amount($including_price, $tax_rate);
                $tax += Money::get_tax_amount($adds_amount, $tax_rate);
            } else
                $legal = 0;

            $checkout = [
                'id'      => "api",
                'user_id' => $this->user["id"],
                'type'    => "basket",
                'status'  => "paid",
                'items'   => $items,
                'data'    => [
                    'type'                    => "pay",
                    'user_id'                 => $this->user["id"],
                    'user_data'               => Invoices::generate_user_data($this->user["id"]),
                    'local'                   => $local,
                    'legal'                   => $legal,
                    'currency'                => $this->user["balance_currency"],
                    'taxrate'                 => $tax_rate,
                    'tax'                     => $tax,
                    'subtotal'                => $subtotal,
                    'total'                   => $total + $tax,
                    'sendbta'                 => 0,
                    'sendbta_amount'          => 0,
                    'pmethod'                 => "Balance",
                    'pmethod_commission'      => 0,
                    'pmethod_commission_rate' => 0,
                    'discounts'               => $discounts,
                ],
                'cdate'   => DateManager::Now(),
                'mdfdate' => DateManager::Now(),
            ];

            if ($this->user["balance"] < $total) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/api/tx6", false, $this->user["lang"]),
                ]);
                return false;
            }

            $newBalance = $this->user["balance"] - $total;
            if ($newBalance < 0.00) $newBalance = 0;

            User::setData($this->user["id"], ['balance' => $newBalance]);
            User::insert_credit_log([
                'user_id'     => $this->user["id"],
                'description' => __("website/balance/cart-payment", false, Config::get("general/local")),
                'type'        => "down",
                'amount'      => $total,
                'cid'         => $this->user["balance_currency"],
                'cdate'       => DateManager::Now(),
            ]);

            User::addAction($this->user["id"], "alteration", "i-paid-by-credit", [
                'checkout_id'   => "api",
                'amount'        => Money::formatter_symbol($total, $this->user["balance_currency"]),
                'before_credit' => Money::formatter_symbol($this->user["balance"], $this->user["balance_currency"]),
                'last_credit'   => Money::formatter_symbol($newBalance, $this->user["balance_currency"]),
                'currency'      => $this->user["balance_currency"],
            ]);

            $status = "SUCCESS";
            $status_msg = Utility::jencode([
                'format-amount-paid'   => Money::formatter_symbol($total, $this->user["balance_currency"]),
                'amount-paid'          => $total,
                'amount-paid-currency' => $this->user["balance_currency"],
            ]);

            $invoice = Invoices::process($checkout, $status, $status_msg);

            if (!$invoice) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/api/tx7", false, $this->user["lang"]),
                ]);
                return false;
            }

            echo Utility::jencode([
                'status'        => "successful",
                'invoice_id'    => $invoice["id"],
                'order_id'      => $invoice["order"]["id"],
                'order_status'  => $invoice["order"]["status"],
                'fee'           => $invoice["total"],
                'fee_formatted' => Money::formatter_symbol($invoice["total"], $invoice["currency"]),
            ]);
        }

        private function reseller_order($param = '', $data = [])
        {
            if (in_array($param, ['detail', 'renewal', 'upgrade', 'cancel', 'transaction', 'download'])) {
                $id = (int)(isset($data["id"]) ? $data["id"] : 0);
                $domain = Filter::domain(isset($data["domain"]) ? $data["domain"] : '');

                if (!$id && !$domain) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/api/tx8", false, $this->user["lang"]),
                    ]);
                    return false;
                }

                if ($domain) {
                    $found = WDB::select("id")->from("users_products");
                    $found->where("type", "=", "domain", "&&");
                    $found->where("name", "=", $domain, "&&");
                    $found->where("owner_id", "=", $this->user["id"]);
                    if ($found->build()) $id = $found->getObject()->id;
                }

                $order = Orders::get($id);

                if (!$order || $order["owner_id"] != $this->user["id"]) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/api/tx8", false, $this->user["lang"]),
                    ]);
                    return false;
                }

                $order["product"] = Products::get($order["type"], $order["product_id"]);

                if ($param == "detail") return $this->reseller_order_detail($order, $data);
                elseif ($param == "renewal") return $this->reseller_order_renewal($order, $data);
                elseif ($param == "upgrade") return $this->reseller_order_upgrade($order, $data);
                elseif ($param == "cancel") return $this->reseller_order_cancel($order, $data);
                elseif ($param == "transaction") return $this->reseller_order_transaction($order, $data);
                elseif ($param == "download") return $this->reseller_order_download($order, $data);
            } elseif ($param == 'list') return $this->reseller_order_list($data);
            else {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown endpoint",
                ]);
                return false;
            }
        }

        private function reseller_order_renewal($order = [], $data = [])
        {
            $order["user_id"] = $order["owner_id"];
            $product = $order["product"];
            $dealership = $this->user["dealership"];
            $period = Filter::letters(isset($data["period_type"]) ? $data["period_type"] : '');
            $duration = (int)(isset($data["period_duration"]) ? $data["period_duration"] : 0);
            $s = [];

            if ($order["type"] == "domain") {
                if ($period != "year") $period = "year";
                if ($duration < 1) $duration = 1;
                if ($product["price"]["renewal"] ?? []) $s = $product["price"]["renewal"];
            } else {
                if (isset($product["price"]) && $product["price"])
                    foreach ($product["price"] as $k => $v)
                        if (!$s && $v["period"] == $period && $v["time"] == $duration) $s = $v;
            }


            if (!$s) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/api/tx4", false, $this->user["lang"]),
                ]);
                return false;
            }

            if ($dealership["require_min_discount_amount"] > 0.00) {
                $rqmcdt = $dealership["require_min_discount_amount"];
                $rqmcdt_cid = $dealership["require_min_discount_cid"];
                $myBalance = Money::exChange($this->user["balance"], $this->user["balance_currency"], $rqmcdt_cid);
                if ($myBalance < $rqmcdt) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/api/tx5", false, $this->user["lang"]),
                    ]);
                    return false;
                }
            }


            $invoice = false;

            $previously = Invoices::previously_created_check("order", $order);
            if ($previously) {
                foreach ($previously as $inv) {
                    if ($inv["status"] == "unpaid") {
                        $invoice = Invoices::get($inv["id"]);
                        break;
                    }
                }
            }

            if ($invoice) {
                $items = Invoices::get_items($invoice["id"]);
                if ($order["type"] == "domain" && sizeof($items) > 2) $invoice = false;
                elseif (sizeof($items) > 1) $invoice = false;
            }

            if (!$invoice) {
                $params = $order;
                $params["period"] = $period;
                $params["period_time"] = $duration;
                $params["cid"] = $order["amount_cid"];
                $params["select_pmethod"] = "Balance";
                $params["do_not_equal"] = true;

                $invoice = Invoices::generate_renewal_bill("order", $this->user, $params);

                if (!$invoice && Invoices::$message == "no-user-address") {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/balance/buy-error5", false, $this->user["lang"]),
                    ]);

                    return false;
                }
            }

            if (!$invoice) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "An invoice could not be generated due to a problem.",
                ]);
                return false;
            }

            $total = $invoice["total"];
            if ($invoice["currency"] != $this->user["balance_currency"])
                $total = round(Money::exChange($invoice["total"], $invoice["currency"], $this->user["balance_currency"]), 2);


            if ($this->user["balance"] < $total) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/api/tx6", false, $this->user["lang"]),
                ]);
                return false;
            }


            $newBalance = $this->user["balance"] - $total;
            if ($newBalance < 0.00) $newBalance = 0;

            User::setData($this->user["id"], ['balance' => $newBalance]);
            User::insert_credit_log([
                'user_id'     => $this->user["id"],
                'description' => $invoice["number"],
                'type'        => "down",
                'amount'      => $total,
                'cid'         => $this->user["balance_currency"],
                'cdate'       => DateManager::Now(),
            ]);
            User::addAction($this->user["id"], "alteration", "i-paid-by-credit", [
                'checkout_id'   => "api",
                'amount'        => Money::formatter_symbol($total, $this->user["balance_currency"]),
                'before_credit' => Money::formatter_symbol($this->user["balance"], $this->user["balance_currency"]),
                'last_credit'   => Money::formatter_symbol($newBalance, $this->user["balance_currency"]),
                'currency'      => $this->user["balance_currency"],
            ]);


            $status = "SUCCESS";
            $status_msg = Utility::jencode([
                'format-amount-paid'   => Money::formatter_symbol($total, $this->user["balance_currency"]),
                'amount-paid'          => $total,
                'amount-paid-currency' => $this->user["balance_currency"],
            ]);

            $invoice["invoice_id"] = $invoice["id"];
            $invoice["reseller_renewal"] = true;

            Invoices::paid([
                'data' => $invoice,
            ], $status, $status_msg, true);

            $order = Orders::get($order["id"]);

            echo Utility::jencode([
                'status'        => "successful",
                'invoice_id'    => $invoice["id"],
                'new_duedate'   => $order["duedate"],
                'fee'           => $total,
                'fee_formatted' => Money::formatter_symbol($total, $this->user["balance_currency"]),
            ]);
        }

        private function reseller_order_upgrade($order = [], $data = [])
        {
            if ($order["status"] != "active") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Order is not active",
                ]);
                return false;
            }

            $product = $order["product"];
            $lang = $this->user["lang"];
            if ($order["period"] == "none") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This order does not have a period",
                ]);
                return false;
            }
            if (!($order["type"] == "hosting" || $order["type"] == "server" || $order["type"] == "special")) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This order is not eligible for upgrade",
                ]);
                return false;
            }
            if ($order["type"] == "hosting" && !Config::get("options/product-upgrade/hosting")) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This order is not eligible for upgrade",
                ]);
                return false;
            }
            if ($order["type"] == "server" && !Config::get("options/product-upgrade/server")) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This order is not eligible for upgrade",
                ]);
                return false;
            }
            if ($order["type"] == "special") {
                $group = Products::getCategory($order["type_id"], $lang, "t1.options");
                if ($group && isset($group["options"]["upgrading"])) {
                    if (!$group["options"]["upgrading"]) {
                        echo Utility::jencode([
                            'status'  => "error",
                            'message' => "This order is not eligible for upgrade",
                        ]);
                        return false;
                    }
                }
            }

            if (!$product)
                $product = [
                    'id'      => $order["product_id"],
                    'type'    => $order["type"],
                    'type_id' => $order["type_id"],
                    'title'   => $order["name"],
                ];


            $ordinfo = Orders::period_info($order);
            $up_products = Products::upgrade_products($order, $product, $ordinfo["remaining-amount"]);

            $u_p_type = isset($data["upgrade_product_type"]) ? $data["upgrade_product_type"] : $order["type"];
            $u_p_id = isset($data["upgrade_product_id"]) ? $data["upgrade_product_id"] : 0;
            $u_p_period = isset($data["upgrade_product_period_type"]) ? $data["upgrade_product_period_type"] : $order["period"];
            $u_p_period_t = (int)(isset($data["upgrade_product_period_duration"]) ? $data["upgrade_product_period_duration"] : $order["period_time"]);


            if ($u_p_type != $order["type"]) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "The product to be upgraded must be from the same product group",
                ]);
                return false;
            }

            $sproduct = Products::get($u_p_type, $u_p_id, $lang);
            $sprice = [];

            if (!$sproduct) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/error7", false, $this->user["lang"]),
                ]);
                return false;
            }

            if ($sproduct["price"])
                foreach ($sproduct["price"] as $v)
                    if ($v["period"] == $u_p_period && $v["time"] == $u_p_period_t)
                        $sprice = $v;

            if (!$sprice || !isset($up_products["prices"][$sproduct["id"]][$sprice["id"]])) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "The product to be upgraded does not have the same period as the order",
                ]);
                return false;
            }
            $sprice = $up_products["prices"][$sproduct["id"]][$sprice["id"]];

            if ($order["product_id"] == $sproduct["id"]) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "The product to be upgraded is the same as the order product",
                ]);
                return false;
            }


            $invoice = Invoices::generate_upgrade($order, $product, $sproduct, $sprice, "unpaid", "Balance");
            if (!$invoice) {
                if (Invoices::$message == "repetition")
                    $errmsg = "error8";
                elseif (Invoices::$message == "no-user-address")
                    $errmsg = "error9";
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/" . $errmsg, false, $this->user["lang"]),
                ]);
                return false;
            }
            Orders::generate_updown("up", $invoice, $order, $product, $sproduct, $sprice);

            $total = $invoice["total"];

            if ($this->user["balance"] < $total) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/api/tx6", false, $this->user["lang"]),
                ]);
                return false;
            }

            $newBalance = $this->user["balance"] - $total;
            if ($newBalance < 0.00) $newBalance = 0;

            User::setData($this->user["id"], ['balance' => $newBalance]);
            User::insert_credit_log([
                'user_id'     => $this->user["id"],
                'description' => $invoice["number"],
                'type'        => "down",
                'amount'      => $total,
                'cid'         => $this->user["balance_currency"],
                'cdate'       => DateManager::Now(),
            ]);

            User::addAction($this->user["id"], "alteration", "i-paid-by-credit", [
                'checkout_id'   => "api",
                'amount'        => Money::formatter_symbol($total, $this->user["balance_currency"]),
                'before_credit' => Money::formatter_symbol($this->user["balance"], $this->user["balance_currency"]),
                'last_credit'   => Money::formatter_symbol($newBalance, $this->user["balance_currency"]),
                'currency'      => $this->user["balance_currency"],
            ]);

            $status = "SUCCESS";
            $status_msg = Utility::jencode([
                'format-amount-paid'   => Money::formatter_symbol($total, $this->user["balance_currency"]),
                'amount-paid'          => $total,
                'amount-paid-currency' => $this->user["balance_currency"],
            ]);

            $invoice["invoice_id"] = $invoice["id"];
            Invoices::paid(['data' => $invoice], $status, $status_msg, true);

            echo Utility::jencode([
                'status'        => "successful",
                'invoice_id'    => $invoice["id"],
                'fee'           => $total,
                'fee_formatted' => Money::formatter_symbol($invoice["total"], $invoice["currency"]),
            ]);
        }

        private function reseller_order_cancel($order = [], $data = [])
        {
            if ($order["status"] != "active") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Order status not active",
                ]);
                return false;
            }

            $reason = Filter::text(isset($data["reason"]) ? $data["reason"] : '');
            $urgency = Filter::route(isset($data["urgency"])) ? $data["urgency"] : 'now';
            if (!($urgency == "now" || $urgency == "period-ending")) $urgency = "now";

            Helper::Load(["Events"]);
            if (!$reason) $reason = 'Via Reseller API';

            $previouslyCheck = Events::isCreated("operation", "order", $order["id"], "cancelled-product-request", "pending");

            if ($previouslyCheck) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/canceled-err2", false, $this->user["lang"]),
                ]);
                return false;
            }

            $insert = Events::create([
                'user_id'  => $this->user["id"],
                'type'     => "operation",
                'owner'    => "order",
                'owner_id' => $order["id"],
                'name'     => "cancelled-product-request",
                'data'     => [
                    'reason'  => $reason,
                    'urgency' => $urgency,
                ],
            ]);

            User::addAction($this->user["id"], "added", "canceled-product-request", [
                'id' => $order["id"],
            ]);

            Hook::run("OrderCancellationRequest");
            echo Utility::jencode(['status' => "successful"]);
        }

        private function reseller_order_detail($order = [], $data = [])
        {
            $product = $order["product"];
            $options = $order["options"];
            $result = [];

            $tax_rate = Invoices::getTaxRate();
            $inv = Invoices::get_last_invoice($order["id"], '', 't2.taxrate');

            if ($inv && $inv["taxrate"] > 0.00) $tax_rate = $inv["taxrate"];

            if ($tax_rate > 0.00 && $order["amount"] > 0.00) {
                $tax_amount = Money::get_tax_amount($order["amount"], $tax_rate);
                $order["amount"] = $order["amount"] + $tax_amount;
            }


            $result["id"] = $order["id"];
            $result["status"] = $order["status"];
            $result["period_type"] = $order["period"];
            $result["period_duration"] = $order["period_time"];
            $result["product_name"] = $order["name"];
            $result["product_type"] = $order["type"];
            $result["product_id"] = $order["product_id"];
            $result["product_amount"] = $order["amount"];
            $result["product_amount_f"] = Money::formatter_symbol($order["amount"], $order["amount_cid"]);
            $result["currency"] = Money::Currency($order["amount_cid"])["code"];


            if (isset($order["options"]["domain"]) && $order["options"]["domain"])
                $result["domain"] = $order["options"]["domain"];

            if (isset($order["options"]["hostname"]) && $order["options"]["hostname"])
                $result["hostname"] = $order["options"]["hostname"];

            if (isset($order["options"]["ip"]) && $order["options"]["ip"])
                $result["ip"] = $order["options"]["ip"];

            if (isset($order["options"]["assigned_ips"]) && $order["options"]["assigned_ips"])
                $result["assigned_ips"] = explode("\n", $order["options"]["assigned_ips"]);

            if (isset($order["options"]["ns1"]) && $order["options"]["ns1"])
                $result["ns1"] = $order["options"]["ns1"];

            if (isset($order["options"]["ns2"]) && $order["options"]["ns2"])
                $result["ns2"] = $order["options"]["ns2"];

            if (isset($order["options"]["ns3"]) && $order["options"]["ns3"])
                $result["ns3"] = $order["options"]["ns3"];

            if (isset($order["options"]["ns4"]) && $order["options"]["ns4"])
                $result["ns4"] = $order["options"]["ns4"];


            $server_id = isset($order["options"]["server_id"]) ? $order["options"]["server_id"] : 0;
            $server = $server_id ? Products::get_server($server_id) : false;
            $module = false;

            if ($server) {
                $mname = $server["type"];
                Modules::Load("Servers", $mname);
                if (class_exists($mname . "_Module")) {
                    $cname = $mname . "_Module";
                    $module = new $cname($server, $order["options"]);
                    if (method_exists($module, 'set_order')) $module->set_order($order);
                }
            } elseif ($order["type"] == "special" && $order["module"] && $order["module"] != "none") {
                $mname = $order["module"];
                Modules::Load("Products", $mname);
                if (class_exists($mname)) {
                    $module = new $mname();
                    if (method_exists($module, 'set_order')) $module->set_order($order);
                }
            } elseif ($order["type"] == "domain" && $order["module"] && $order["module"] != "none") {
                $className = $order["module"];
                Modules::Load("Registrars", $className);
                $module = new $className($order["options"]);
                if (method_exists($module, 'set_order')) $module->set_order($order);
            }


            if ($order["type"] == "domain") {
                $result["sld"] = $options["name"];
                $result["tld"] = $options["tld"];
                $result["whois"] = $options["whois"];
                $whois_privacy = false;
                $whois_privacy_ex = DateManager::zero();

                $isAddon = Models::$init->db->select("id,duedate")->from("users_products_addons");
                $isAddon->where("owner_id", "=", $order["id"], "&&");
                $isAddon->where("addon_key", "=", "whois-privacy", "&&");
                $isAddon->where("status", "=", "active");
                $isAddon = $isAddon->build() ? $isAddon->getObject() : false;
                if ($isAddon) {
                    $whois_privacy = true;
                    $whois_privacy_ex = $isAddon->duedate;
                    if (in_array(substr($whois_privacy_ex, 0, 4), ['1881', '0000', '1970', '1971']))
                        $whois_privacy_ex = DateManager::zero();
                }
                $result["whois_privacy"] = [
                    'status'   => $whois_privacy,
                    'due_date' => $whois_privacy_ex,
                ];
                $result["transfer_lock"] = $options["transferlock"];
            } elseif ($order["type"] == "hosting") {
                $result["features"] = [
                    'disk_limit'         => $order["options"]["disk_limit"],
                    'bandwidth_limit'    => $order["options"]["bandwidth_limit"],
                    'email_limit'        => $order["options"]["email_limit"],
                    'database_limit'     => $order["options"]["database_limit"],
                    'addons_limit'       => $order["options"]["addons_limit"],
                    'subdomain_limit'    => $order["options"]["subdomain_limit"],
                    'ftp_limit'          => $order["options"]["ftp_limit"],
                    'park_limit'         => $order["options"]["park_limit"],
                    'max_email_per_hour' => $order["options"]["max_email_per_hour"],
                    'cpu_limit'          => $order["options"]["cpu_limit"],
                ];

                if ($module) {
                    $summary = method_exists($module, 'getDisk') || method_exists($module, 'getBandwidth');
                    if ($summary) {
                        $bandwidth = method_exists($module, 'getBandwidth') ? $module->getBandwidth() : false;
                        $disk = method_exists($module, 'getDisk') ? $module->getDisk() : false;

                        if ($bandwidth) {
                            $result["usage"]["bandwidth_limit_byte"] = $bandwidth["limit"];
                            $result["usage"]["bandwidth_used_byte"] = $bandwidth["used"];
                            $result["usage"]["bandwidth_limit_format"] = $bandwidth["format-limit"];
                            $result["usage"]["bandwidth_used_format"] = $bandwidth["format-used"];
                            $result["usage"]["bandwidth_used_percent"] = $bandwidth["used-percent"];
                        }

                        if ($disk) {
                            $result["usage"]["disk_limit_byte"] = $disk["limit"];
                            $result["usage"]["disk_used_byte"] = $disk["used"];
                            $result["usage"]["disk_limit_format"] = $disk["format-limit"];
                            $result["usage"]["disk_used_format"] = $disk["format-used"];
                            $result["usage"]["disk_used_percent"] = $disk["used-percent"];
                        }
                    }
                }
            } elseif ($order["type"] == "server") {
                if (isset($order["options"]["server_features"]) && $order["options"]["server_features"])
                    $result["features"] = $order["options"]["server_features"];

                if ($module)
                    foreach (['suspend', 'unsuspend', 'start', 'stop', 'shutdown', 'reboot', 'reset'] as $act)
                        if (method_exists($module, $act)) $result["commands"][] = $act;
                if ($module && method_exists($module, 'get_status')) $result["server_status"] = $module->get_status();
            } elseif ($order["type"] == "software") {
                $change_domain = Config::get("options/software-change-domain/status");
                $change_domain_limit = Config::get("options/software-change-domain/limit");

                if (isset($product["options"]["change-domain"])) $change_domain = $product["options"]["change-domain"];
                if (isset($options["change-domain"])) $change_domain = $options["change-domain"];
                if ($change_domain) {
                    Helper::Load("Events");
                    $apply_changes = Events::getList('log', 'order', $order["id"], 'change-domain');
                    $apply_count = $apply_changes ? sizeof($apply_changes) : 0;

                    $result["change_domain_has_expired"] = strlen($change_domain_limit) > 0 && $apply_count >= (int)$change_domain_limit;
                    $result["change_domain_used"] = $apply_count;
                    $result["change_domain_limit"] = $change_domain_limit;
                }
                $result["change_domain"] = $change_domain;
            }


            $delivery_file = isset($order["options"]["delivery_file"]) ? $order["options"]["delivery_file"] : false;
            $product_file = isset($product["options"]["download_file"]) ? $product["options"]["download_file"] : false;
            $download_link = isset($product["options"]["download_link"]) ? $product["options"]["download_link"] : false;

            if ($delivery_file)
                $download_file = RESOURCE_DIR . "uploads" . DS . "orders" . DS . $delivery_file;
            elseif ($product_file)
                $download_file = RESOURCE_DIR . "uploads" . DS . "products" . DS . $product_file;
            else
                $download_file = false;

            $hooks = Hook::run("OrderDownload", $order, $product, [
                'download_file' => $download_file,
                'download_link' => $download_link,
            ]);

            if ($hooks) {
                foreach ($hooks as $hook) {
                    if ($hook && is_array($hook)) {
                        if (isset($hook["download_file"]) && $hook["download_file"])
                            $download_file = $hook["download_file"];
                        if (isset($hook["download_link"]) && $hook["download_link"])
                            $download_link = $hook["download_link"];
                    }
                }
            }


            if ($download_link) {
                $result["download_type"] = "link";
                $result["download_link"] = $download_link;
            } elseif ($download_file && file_exists($download_file)) {
                $file_sha1 = file_exists($download_file) ? sha1($download_file) : '';
                $order_sha1 = sha1(Utility::jencode($order["options"]));
                $file_hash = $file_sha1 . $order_sha1;
                $result["download_type"] = "file";
                $result["download_hash"] = md5($file_hash);
                $file_ext = explode(".", $download_file);
                $result["download_ext"] = end($file_ext);
            }

            if ($order["module"] && $order["module"] != "none")
                $result["established"] = isset($options["established"]) ? $options["established"] : false;


            $result["creation_date"] = $order["cdate"];
            $result["renewal_date"] = $order["renewaldate"];
            $result["due_date"] = substr($order["duedate"], 0, 4) == "1881" ? DateManager::zero() : $order["duedate"];


            if ($module && method_exists($module, 'api_order_detail')) {
                $api_detail = $module->api_order_detail();
                if ($api_detail && is_array($api_detail))
                    $result = array_replace_recursive($result, $api_detail);
            }


            echo Utility::jencode($result);

            return true;
        }

        private function reseller_order_transaction($order = [], $data = [])
        {
            if ($order["status"] != "active") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Order status not active",
                ]);
                return false;
            }

            if (isset($data["action"]) && $data["action"]) {
                if ($data["action"] == "change-domain") return $this->reseller_order_change_domain($order, $data);
                if ($order["type"] == "domain") {
                    if ($data["action"] == "modify-whois") return $this->reseller_order_modify_whois($order, $data);
                    if ($data["action"] == "modify-whois-privacy") return $this->reseller_order_modify_whois_privacy($order, $data);
                    if ($data["action"] == "purchase-whois-privacy") return $this->reseller_order_purchase_whois_privacy($order, $data);
                    if ($data["action"] == "modify-transferlock") return $this->reseller_order_modify_transferlock($order, $data);
                    if ($data["action"] == "modify-nameservers") return $this->reseller_order_modify_nameservers($order, $data);
                    if ($data["action"] == "child-nameservers") return $this->reseller_order_child_nameservers($order, $data);
                    if ($data["action"] == "add-child-nameserver") return $this->reseller_order_add_child_nameserver($order, $data);
                    if ($data["action"] == "modify-child-nameserver") return $this->reseller_order_modify_child_nameserver($order, $data);
                    if ($data["action"] == "delete-child-nameserver") return $this->reseller_order_delete_child_nameserver($order, $data);
                    if ($data["action"] == "get-auth-code") return $this->reseller_order_get_auth_code($order, $data);
                    if ($data["action"] == "get-dns-records") return $this->reseller_order_dns_records($order, $data);
                    if ($data["action"] == "add-dns-record") return $this->reseller_order_add_dns_record($order, $data);
                    if ($data["action"] == "modify-dns-record") return $this->reseller_order_modify_dns_record($order, $data);
                    if ($data["action"] == "delete-dns-record") return $this->reseller_order_delete_dns_record($order, $data);
                    if ($data["action"] == "get-dnssec-records") return $this->reseller_order_dnssec_records($order, $data);
                    if ($data["action"] == "add-dnssec-record") return $this->reseller_order_add_dnssec_record($order, $data);
                    if ($data["action"] == "delete-dnssec-record") return $this->reseller_order_delete_dnssec_record($order, $data);
                    if ($data["action"] == "get-forwarding-domain") return $this->reseller_order_forwarding_domain($order, $data);
                    if ($data["action"] == "set-forwarding-domain") return $this->reseller_order_set_forwarding_domain($order, $data);
                    if ($data["action"] == "get-forwarding-email") return $this->reseller_order_forwarding_email($order, $data);
                    if ($data["action"] == "add-forwarding-email") return $this->reseller_order_add_forwarding_email($order, $data);
                    if ($data["action"] == "modify-forwarding-email") return $this->reseller_order_modify_forwarding_email($order, $data);
                    if ($data["action"] == "delete-forwarding-email") return $this->reseller_order_delete_forwarding_email($order, $data);
                }
                if ($data["action"] == "change-password") return $this->reseller_order_change_password($order, $data);
                if ($data["action"] == "suspend") return $this->reseller_order_server_suspend($order, $data);
                if ($data["action"] == "unsuspend") return $this->reseller_order_server_unsuspend($order, $data);
                if ($data["action"] == "start") return $this->reseller_order_server_start($order, $data);
                if ($data["action"] == "stop") return $this->reseller_order_server_stop($order, $data);
                if ($data["action"] == "shutdown") return $this->reseller_order_server_shutdown($order, $data);
                if ($data["action"] == "reboot") return $this->reseller_order_server_reboot($order, $data);
                if ($data["action"] == "reset") return $this->reseller_order_server_reset($order, $data);
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown request",
                ]);
            }
        }

        private function reseller_order_download($order = [], $data = [])
        {
            if ($order["status"] != "active") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Order status not active",
                ]);
                return false;
            }

            $result = Orders::download($order);
            if (!$result) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => Orders::$message,
                ]);
                return false;
            }
        }

        private function reseller_order_change_domain($order = [], $data = [])
        {
            if ($order["type"] != "software") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the software product",
                ]);
                return false;
            }

            $domain = Filter::domain(isset($data["domain"]) ? $data["domain"] : '');

            if (Validation::isEmpty($domain)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/error13", false, $this->user["lang"]),
                ]);
                return false;
            }

            $domain = Utility::strtolower($domain);

            $domain = str_replace('www.', '', $domain);

            $parse = Utility::domain_parser("http://" . $domain);

            if (!$parse || !$parse["host"] || !$parse["tld"]) {
                echo Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='domain']",
                    'message' => __("website/account_products/error13", false, $this->user["lang"]),
                ]);
                return false;
            }

            $domain = $parse["domain"];
            $options = $order["options"];
            $product = $order["product"];

            if ($domain == $options["domain"]) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/error13", false, $this->user["lang"]),
                ]);
                return false;
            }

            $change_domain = Config::get("options/software-change-domain/status");
            $change_domain_limit = Config::get("options/software-change-domain/limit");

            Helper::Load("Events");

            if (isset($product["options"]["change-domain"])) $change_domain = $product["options"]["change-domain"];
            if (isset($options["change-domain"])) $change_domain = $options["change-domain"];
            if ($change_domain && strlen($change_domain_limit) > 0) {
                $apply_changes = Events::getList('log', 'order', $order["id"], 'change-domain');
                $apply_count = $apply_changes ? sizeof($apply_changes) : 0;
                if ($apply_count >= (int)$change_domain_limit) $change_domain = false;
            }
            if (!$change_domain) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Domain cannot be changed",
                ]);
                return false;
            }

            $options["domain"] = $domain;

            Orders::set($order["id"], ['options' => Utility::jencode($options)]);

            Orders::add_history($this->user["id"], $order["id"], "change-domain", [
                'old_domain' => $order["options"]["domain"],
                'new_domain' => $options["domain"],
            ]);

            User::addAction($this->user["id"], "alteration", "change-software-domain", [
                'order_id'   => $order["id"],
                'old_domain' => $order["options"]["domain"],
                'new_domain' => $options["domain"],
                'order_name' => $order["name"],
            ]);

            echo Utility::jencode(['status' => "successful"]);

            return true;
        }

        private function reseller_order_change_password($order = [], $data = [])
        {
            if ($order["type"] != "hosting") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This transaction applies only to the hosting product",
                ]);
                return false;
            }

            $options = $order["options"];

            if (isset($options["server_id"]) && $options["server_id"] != 0) {
                $server = Products::get_server($options["server_id"]);
                if ($server) {
                    if ($server["status"] == "active") {
                        Modules::Load("Servers", $server["type"]);
                        $module_name = $server["type"] . "_Module";
                        $operations = new $module_name($server, $options);
                        if (method_exists($operations, "set_order")) $operations->set_order($order);
                        $password = Filter::password(isset($data["password"]) ? $data["password"] : '');

                        if (Validation::isEmpty($password)) {
                            echo Utility::jencode([
                                'status'  => "error",
                                'message' => __("website/account_products/hosting-change-password-err1", false, $this->user["lang"]),
                            ]);
                            return false;
                        }

                        if (method_exists($operations, 'getPasswordStrength')) {
                            $strength = $operations->getPasswordStrength($password);
                            if (!$strength) {
                                echo Utility::jencode([
                                    'status'  => "error",
                                    'message' => $operations->error,
                                ]);
                                return false;
                            }
                            if ($strength < 65) {
                                echo Utility::jencode([
                                    'status'  => "error",
                                    'message' => __("website/account_products/password-strength-weak", false, $this->user["lang"]),
                                ]);
                                return false;
                            }
                        }

                        if (method_exists($operations, 'change_password'))
                            $changed = $operations->change_password($password);
                        else
                            $changed = $operations->changePassword(false, $password);

                        if (!$changed) {
                            echo Utility::jencode([
                                'status'  => "error",
                                'message' => __("website/account_products/hosting-change-password-err2", ['{error}' => $operations->error], $this->user["lang"]),
                            ]);
                            return false;
                        }

                        if (isset($order["options"]["ftp_info"]["password"])) {
                            $options = $order["options"];
                            $cry_pass = Crypt::encode($password, Config::get("crypt/user"));
                            $options["ftp_info"]["password"] = $cry_pass;
                            $options["config"]["password"] = $cry_pass;
                            $options = Utility::jencode($options);
                            Orders::set($order["id"], ['options' => $options]);
                        }
                        User::addAction($this->user["id"], "alteration", "changed-hosting-password", [
                            'order_id'   => $order["id"],
                            'order_name' => $order["name"],
                        ]);
                        Orders::add_history($this->user['id'], $order["id"], 'hosting-order-password-changed');
                        echo Utility::jencode(['status' => "successful"]);
                    }
                }
            }
        }

        private function reseller_order_server_command($order = [], $data = [])
        {
            if ($order["type"] != "server") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This transaction only applies to the server product",
                ]);
                return false;
            }

            $server_id = isset($order["options"]["server_id"]) ? $order["options"]["server_id"] : 0;
            $server = $server_id ? Products::get_server($server_id) : false;

            if (!$server) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This order is not affiliated with the server",
                ]);
                return false;
            }

            if ($server["status"] != "active") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "The status of the associated server is not active",
                ]);
                return false;
            }

            $m_name = $server["type"];
            Modules::Load("Servers", $m_name);
            $m_name = $m_name . "_Module";

            if (!class_exists($m_name)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Server module files are damaged",
                ]);
                return false;
            }

            $module = new $m_name();

            if (method_exists($module, 'set_order')) $module->set_order($order);

            $action = isset($data["action"]) ? $data["action"] : '';

            if (!$action) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "The 'action' attribute is missing",
                ]);
                return false;
            }

            if (!in_array($action, ['suspend', 'unsuspend', 'start', 'stop', 'shutdown', 'reboot', 'reset'])) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown action",
                ]);
                return false;
            }

            if (!method_exists($module, $action)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Module does not support this action",
                ]);
                return false;
            }

            $apply = $module->{$action}();

            if (!$apply) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]);
                return false;
            }

            $user_id = $this->user['id'];
            User::addAction($user_id, 'transaction', 'The command "' . $action . '" has been sent for service #' . $this->order["id"] . ' on the module.');
            Orders::add_history($user_id, $order["id"], 'server-order-' . $action);

            echo Utility::jencode(['status' => "successful"]);
        }

        private function reseller_order_server_suspend($order = [], $data = [])
        {
            return $this->reseller_order_server_command($order, $data);
        }

        private function reseller_order_server_unsuspend($order = [], $data = [])
        {
            return $this->reseller_order_server_command($order, $data);
        }

        private function reseller_order_server_start($order = [], $data = [])
        {
            return $this->reseller_order_server_command($order, $data);
        }

        private function reseller_order_server_stop($order = [], $data = [])
        {
            return $this->reseller_order_server_command($order, $data);
        }

        private function reseller_order_server_shutdown($order = [], $data = [])
        {
            return $this->reseller_order_server_command($order, $data);
        }

        private function reseller_order_server_reboot($order = [], $data = [])
        {
            return $this->reseller_order_server_command($order, $data);
        }

        private function reseller_order_server_reset($order = [], $data = [])
        {
            return $this->reseller_order_server_command($order, $data);
        }

        private function reseller_order_modify_whois($order = [], $data = [])
        {

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $whois = $data["whois"] ?? [];

            if (!$whois || !is_array($whois)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Please identify whois information.",
                ]);
                return false;
            }

            $contact_types = [
                'registrant',
                'administrative',
                'technical',
                'billing',
            ];

            foreach ($contact_types as $ct) {
                $cd = $whois[$ct] ?? false;
                $full_name = Filter::html_clear($cd["FirstName"] . " " . $cd["LastName"]);
                $company_name = Filter::html_clear($cd["Company"] ?? '');
                $email = Filter::email($cd["EMail"]);
                $pcountry_code = Filter::numbers($cd["PhoneCountryCode"] ?? '');
                $phone = Filter::numbers($cd["Phone"] ?? '');
                $fcountry_code = Filter::numbers($cd["FaxCountryCode"] ?? '');
                $fax = Filter::numbers($cd["Fax"] ?? '');
                $address = Filter::html_clear($cd["AddressLine1"] . ($cd["AddressLine2"] ? " " . $cd["AddressLine2"] : ''));
                $city = Filter::html_clear($cd["City"]);
                $state = Filter::html_clear($cd["State"] ?? '');
                $zipcode = Filter::html_clear($cd["ZipCode"] ?? '');
                $country_code = Filter::letters($cd["Country"] ?? '');

                $full_name = htmlentities($full_name, ENT_QUOTES);
                $company_name = htmlentities($company_name, ENT_QUOTES);
                $email = htmlentities($email, ENT_QUOTES);
                $pcountry_code = htmlentities($pcountry_code, ENT_QUOTES);
                $phone = htmlentities($phone, ENT_QUOTES);
                $fcountry_code = htmlentities($fcountry_code, ENT_QUOTES);
                $fax = htmlentities($fax, ENT_QUOTES);
                $address = htmlentities($address, ENT_QUOTES);
                $city = htmlentities($city, ENT_QUOTES);
                $state = htmlentities($state, ENT_QUOTES);
                $zipcode = htmlentities($zipcode, ENT_QUOTES);
                $country_code = htmlentities($country_code, ENT_QUOTES);
                if (
                    Validation::isEmpty($full_name) ||
                    Validation::isEmpty($email) ||
                    Validation::isEmpty($pcountry_code) ||
                    Validation::isEmpty($phone) ||
                    Validation::isEmpty($address) ||
                    Validation::isEmpty($city) ||
                    Validation::isEmpty($state) ||
                    Validation::isEmpty($zipcode) ||
                    Validation::isEmpty($country_code)
                ) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/modify-whois-error1", false, $this->user["lang"]),
                    ]);
                    return false;
                }

                if (!Validation::isEmail($email)) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/modify-whois-error2"),
                    ]);
                    return false;
                }

                $names = Filter::name_smash($full_name);
                $first_name = $names["first"];
                $last_name = $names["last"];

                if (Utility::strlen($last_name) < 1) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/modify-whois-error1"),
                    ]);
                    return false;
                }

                if (Utility::strlen($address) > 64) {
                    $address1 = Utility::short_text($address, 0, 64);
                    $address2 = Utility::short_text($address, 64, 64);
                } else {
                    $address1 = $address;
                    $address2 = null;
                }

                $new_whois[$ct] = [
                    'Name'             => $full_name,
                    'FirstName'        => $first_name,
                    'LastName'         => $last_name,
                    'Company'          => $company_name,
                    'Address'          => $address,
                    'AddressLine1'     => $address1,
                    'AddressLine2'     => $address2,
                    'ZipCode'          => $zipcode,
                    'State'            => $state,
                    'City'             => $city,
                    'Country'          => $country_code,
                    'Phone'            => $phone,
                    'Fax'              => $fax,
                    'EMail'            => $email,
                    'FaxCountryCode'   => $fcountry_code,
                    'PhoneCountryCode' => $pcountry_code,
                ];
            }

            $diff1 = $order["options"]["whois"];

            if (!isset($diff1["registrant"])) {
                $diff1_n = [];
                foreach ($contact_types as $ct) $diff1_n[$ct] = $diff1;
                $diff1 = $diff1_n;
            }

            $diff2 = $new_whois;
            $result = [];

            foreach ($diff2 as $ct => $ct_data)
                foreach ($ct_data as $k => $v)
                    if (!($diff1[$ct][$k] == '' && $v == '') && $diff1[$ct][$k] != $v) $result[$ct][$k] = $v;

            if ($h_operations = Hook::run("DomainWhoisChange", ['order' => $order, 'whois' => $new_whois])) {
                foreach ($h_operations as $h_operation) {
                    if ($h_operation && isset($h_operation["error"]) && $h_operation["error"]) {
                        echo Utility::jencode([
                            'status'  => "error",
                            'message' => $h_operation["error"],
                        ]);
                        return false;
                    }
                }
            }


            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            $set_order = [];

            if ($module && method_exists($module, 'ModifyWhois')) {

                $modify = $module->ModifyWhois($order["options"], $new_whois);
                if (!$modify) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/modify-whois-error3", ['{error}' => $module->error]),
                    ]);
                    return false;
                }
            } else {

                Helper::Load(["Events", "Notification"]);

                $isCreated = Events::isCreated("operation", "order", $order["id"], "modify-whois-infos", "pending");

                if ($isCreated) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/error4"),
                    ]);
                    return false;
                }

                $evID = Events::create([
                    'type'     => "operation",
                    'owner'    => "order",
                    'owner_id' => $order["id"],
                    'name'     => "modify-whois-infos",
                    'data'     => [
                        'modified' => $result,
                        'domain'   => $order["options"]["domain"] ?? $order["name"],
                    ],
                ]);

                if ($evID) Notification::need_manually_transaction($order["id"], $evID);

                $set_order['unread'] = 0;
            }

            $order["options"]["whois"] = $new_whois;

            $set_order["options"] = Utility::jencode($order["options"]);

            Orders::set($order["id"], $set_order);

            User::addAction($this->user["id"], "alteration", "changed-domain-whois-infos", [
                'name' => $order["name"],
                'id'   => $order["id"],
            ]);

            $order = Orders::get($order["id"]);

            Hook::run("DomainWhoisChanged", $order);

            echo Utility::jencode([
                'status' => "successful",
            ]);

            return true;
        }

        private function reseller_order_modify_whois_privacy($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            $status = Filter::letters($data["status"] ?? "disable");

            if (!in_array($status, ['enable', 'disable'])) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "The ‘status’ field you specified is invalid.",
                ]);
                return false;
            }

            if (!isset($options["whois_manage"])) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "You're not authorised to manage whois.",
                ]);
                return false;
            }

            $whidden_amount = Config::get("options/domain-whois-privacy/amount");
            $whidden_cid = Config::get("options/domain-whois-privacy/cid");

            if ($module) {
                $whidden_amount = $module_data["config"]["settings"]["whidden-amount"] ?? 0;
                $whidden_cid = $module_data["config"]["settings"]["whidden-currency"] ?? 4;
            }

            $whois_privacy_purchase = $whidden_amount > 0.00;

            if ($whois_privacy_purchase) {
                $isAddon = WDB::select("id")->from("users_products_addons");
                $isAddon->where("status", "=", "active", "&&");
                $isAddon->where("owner_id", "=", $order["id"], "&&");
                $isAddon->where("addon_key", "=", "whois-privacy");
                $isAddon = $isAddon->build() ? $isAddon->getObject()->id : false;

                if (!$isAddon) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => "Cannot continue because the whois privacy product is not active.",
                    ]);
                    return false;
                }
            }

            $set_order = [];

            if ($module && method_exists($module, 'modifyPrivacyProtection')) {

                $modify = $module->modifyPrivacyProtection($options, $status);
                if (!$modify) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/error2", ['{error}' => $module->error]),
                    ]);
                    return false;

                }
            } else {
                $isCreated = Events::isCreated("operation", "order", $order["id"], "modify-whois-privacy", "pending");
                if ($isCreated) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/error4"),
                    ]);
                    return false;
                }

                $evID = Events::create([
                    'type'     => "operation",
                    'owner'    => "order",
                    'owner_id' => $order["id"],
                    'name'     => "modify-whois-privacy",
                    'data'     => [
                        'status' => $status,
                        'domain' => $options["domain"],
                    ],
                ]);
                if ($evID) Notification::need_manually_transaction($order["id"], $evID);
                $set_order['unread'] = 0;
            }

            $options["whois_privacy"] = $status == "enable";

            $set_order['options'] = Utility::jencode($options);

            Orders::set($order["id"], $set_order);

            User::addAction($this->user["id"], "alteration", "domain-whois-privacy-" . $status . "d", [
                'domain' => $options["domain"],
            ]);


            echo Utility::jencode([
                'status' => "successful",
            ]);

            return true;
        }

        private function reseller_order_purchase_whois_privacy($order = [], $data = [])
        {
            Helper::Load(["Invoices", "Money", "Basket", "Events", "Notification"]);

            $dealership = $this->user["dealership"];
            $options = $order["options"];
            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            $whidden_amount = Config::get("options/domain-whois-privacy/amount");
            $whidden_cid = Config::get("options/domain-whois-privacy/cid");

            if ($module) {
                $whidden_amount = $module_data["config"]["settings"]["whidden-amount"] ?? 0;
                $whidden_cid = $module_data["config"]["settings"]["whidden-currency"] ?? 4;
            }

            $whidden_amount = Money::exChange($whidden_amount, $whidden_cid, $this->user["balance_currency"]);
            $whidden_cid = $this->user["balance_currency"];

            $btxn = Config::get("options/balance-taxation") == "n";
            $taxation = Invoices::getTaxation($this->user["address"]["country_id"], $this->user["taxation"]);
            $tax_rate = Invoices::getTaxRate($this->user["address"]["country_id"], $this->user["address"]["city_id"] ?? $this->user["address"]["city"], $this->user["id"]);
            $taxation_type = Invoices::getTaxationType();

            $whidden_price = $whidden_amount;

            if ($taxation_type == "inclusive" && $taxation && $tax_rate > 0.00)
                $whidden_price -= Money::get_inclusive_tax_amount($whidden_price);

            if ($taxation && $btxn && $tax_rate > 0.00)
                $whidden_price += Money::get_exclusive_tax_amount($whidden_price, $tax_rate);


            $total = $whidden_price;

            if ($dealership["require_min_discount_amount"] > 0.00) {
                $rqmcdt = $dealership["require_min_discount_amount"];
                $rqmcdt_cid = $dealership["require_min_discount_cid"];
                $myBalance = Money::exChange($this->user["balance"], $this->user["balance_currency"], $rqmcdt_cid);
                if ($myBalance < $rqmcdt) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/api/tx5", false, $this->user["lang"]),
                    ]);
                    return false;
                }
            }


            if ($this->user["balance"] < $total) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/api/tx6", false, $this->user["lang"]),
                ]);
                return false;
            }


            $invoice = Invoices::set_wpp($order, $whidden_amount, 'waiting', "Balance");
            if (!$invoice && Invoices::$message == "repetition") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error13"),
                ]);
                return false;
            }
            if (!$invoice && Invoices::$message == "no-user-address") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error14"),
                ]);
                return false;
            }

            $total = Money::exChange($invoice["total"], $invoice["currency"], $this->user["balance_currency"]);
            $total = round($total, 2);

            if (!Invoices::MakeOperation("paid", $invoice["id"], true)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error14"),
                ]);
                return false;
            }


            $newBalance = $this->user["balance"] - $total;
            if ($newBalance < 0.00) $newBalance = 0;

            User::setData($this->user["id"], ['balance' => $newBalance]);
            User::insert_credit_log([
                'user_id'     => $this->user["id"],
                'description' => __("website/balance/cart-payment", false, Config::get("general/local")),
                'type'        => "down",
                'amount'      => $total,
                'cid'         => $this->user["balance_currency"],
                'cdate'       => DateManager::Now(),
            ]);


            User::addAction($this->user["id"], "alteration", "i-paid-by-credit", [
                'checkout_id'   => "api",
                'amount'        => Money::formatter_symbol($total, $this->user["balance_currency"]),
                'before_credit' => Money::formatter_symbol($this->user["balance"], $this->user["balance_currency"]),
                'last_credit'   => Money::formatter_symbol($newBalance, $this->user["balance_currency"]),
                'currency'      => $this->user["balance_currency"],
            ]);


            echo Utility::jencode([
                'status'        => "successful",
                'invoice_id'    => $invoice["id"],
                'order_id'      => $order["id"],
                'fee'           => $total,
                'fee_formatted' => Money::formatter_symbol($invoice["total"], $invoice["currency"]),
            ]);

        }

        private function reseller_order_modify_transferlock($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            $status = Filter::letters($data["status"] ?? "disable");

            if (!in_array($status, ['enable', 'disable'])) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "The ‘status’ field you specified is invalid.",
                ]);
                return false;
            }


            $set_order = [];

            if ($module && method_exists($module, 'ModifyTransferLock')) {
                $modify = $module->ModifyTransferLock($options, $status);
                if (!$modify) {
                    echo Utility::jencode([
                        'status' => "error",
                        __("website/account_products/modify-transferlock-error1", ['{error}' => $module->error]),
                    ]);
                    return false;

                }
            } else {
                $isCreated = Events::isCreated("operation", "order", $order["id"], "modify-domain-transferlock", "pending");
                if ($isCreated) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/error4"),
                    ]);
                    return false;
                }

                $evID = Events::create([
                    'type'     => "operation",
                    'owner'    => "order",
                    'owner_id' => $order["id"],
                    'name'     => "modify-domain-transferlock",
                    'data'     => [
                        'status' => $status,
                        'domain' => $options["domain"],
                    ],
                ]);
                if ($evID) Notification::need_manually_transaction($order["id"], $evID);
                $set_order['unread'] = 0;
            }


            $options["transferlock"] = $status == "enable";
            $options["transferlock_latest_update"] = DateManager::Now();

            $set_order['options'] = Utility::jencode($options);

            Orders::set($order["id"], $set_order);

            User::addAction($this->user["id"], "alteration", "changed-domain-transferlock", [
                'status' => $status,
                'domain' => $options["domain"],
            ]);


            echo Utility::jencode([
                'status' => "successful",
            ]);

            return true;
        }

        private function reseller_order_modify_nameservers($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }


            $dns = $data["nameservers"] ?? [];
            if (!is_array($dns)) $dns = [];


            $new_dns = [];

            if ($dns) {
                for ($i = 0; $i <= sizeof($dns) - 1; $i++) {
                    $dn = isset($dns[$i]) ? $dns[$i] : false;
                    if (!$dn) continue;
                    $dn = Filter::domain($dn);
                    $new_dns[] = $dn;
                }
            }

            if (!($new_dns[0] && $new_dns[1])) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/error12"),
                ]);
                return false;
            }

            $modified = [];

            if ($new_dns[0] != $options["ns1"]) {
                $modified["ns1"] = $new_dns[0];
                $options["ns1"] = $new_dns[0];
            }

            if ($new_dns[1] != $options["ns2"]) {
                $modified["ns2"] = $new_dns[1];
                $options["ns2"] = $new_dns[1];
            }

            if (isset($new_dns[2])) {
                if (!(isset($options["ns3"]) && $options["ns3"] == $new_dns[2])) {
                    if (!isset($options["ns3"]) || $new_dns[2] != $options["ns3"]) {
                        $modified["ns3"] = $new_dns[2];
                        $options["ns3"] = $new_dns[2];
                    } else {
                        $modified["ns3"] = ___("needs/deleted", false, Config::get("general/local"));
                        unset($options["ns3"]);
                    }
                }
            } elseif (isset($options["ns3"])) {
                $modified["ns3"] = ___("needs/deleted", false, Config::get("general/local"));
                unset($options["ns3"]);
            }

            if (isset($new_dns[3])) {
                if (!(isset($options["ns4"]) && $options["ns4"] == $new_dns[3])) {
                    if (!isset($options["ns4"]) || $new_dns[3] != $options["ns4"]) {
                        $modified["ns4"] = $new_dns[3];
                        $options["ns4"] = $new_dns[3];
                    } else {
                        $modified["ns4"] = ___("needs/deleted", false, Config::get("general/local"));
                        unset($options["ns4"]);
                    }
                }
            } elseif (isset($options["ns4"])) {
                $modified["ns4"] = ___("needs/deleted", false, Config::get("general/local"));
                unset($options["ns4"]);
            }

            if (!$modified) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/error5"),
                ]);
                return false;
            }

            $set_order = [];

            if ($module && method_exists($module, 'ModifyDns')) {
                $modify = $module->ModifyDns($options, $new_dns);
                if (!$modify) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/modify-dns-error2", [
                            '{error}' => $module->error,
                        ]),
                    ]);
                    return false;
                }
            } else {
                $isCreated = Events::isCreated("operation", "order", $order["id"], "modify-domain-dns", "pending");
                if ($isCreated) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/error4"),
                    ]);
                    return false;
                }

                $evID = Events::create([
                    'type'     => "operation",
                    'owner'    => "order",
                    'owner_id' => $order["id"],
                    'name'     => "modify-domain-dns",
                    'data'     => [
                        'modified' => $modified,
                        'domain'   => $options["domain"],
                    ],
                ]);
                if ($evID) Notification::need_manually_transaction($order["id"], $evID);
                $set_order['unread'] = 0;
            }

            $set_order['options'] = Utility::jencode($options);

            Orders::set($order["id"], $set_order);

            User::addAction($this->user["id"], "alteration", "changed-domain-dns", [
                'domain' => $options["domain"],
            ]);


            echo Utility::jencode([
                'status' => "successful",
            ]);

            return true;
        }

        private function reseller_order_child_nameservers($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            if (!$module || !method_exists($module, 'CNSList')) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This service is not supported for this domain.",
                ]);
                return false;
            }

            $response = $module->CNSList($options);

            if (!is_array($response)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error ?: 'Unknown error',
                ]);
                return false;
            }

            echo Utility::jencode([
                'status' => "successful",
                'data'   => $response,
            ]);

            return true;
        }

        private function reseller_order_add_child_nameserver($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            if (!$module || !method_exists($module, 'addCNS')) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This service is not supported for this domain.",
                ]);
                return false;
            }

            $ns = Filter::domain($data["ns"]);
            $ip = Filter::ip($data["ip"]);

            if (Validation::isEmpty($ns) || Validation::isEmpty($ip)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/add-cns-error1"),
                ]);
                return false;
            }

            if (!stristr($ns, $order["options"]["domain"])) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/add-cns-error3"),
                ]);
                return false;
            }

            $response = $module->addCNS($options, $ns, $ip);
            if (!$response) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error ?: 'Unknown error',
                ]);
                return false;
            }

            $options["cns_list"] = $module->CNSList($options) ?: [];

            $set_order = [];

            $set_order['options'] = Utility::jencode($options);

            Orders::set($order["id"], $set_order);

            User::addAction($this->user["id"], "added", "added-domain-cns", [
                'ns'     => $ns,
                'ip'     => $ip,
                'name'   => $order["name"],
                'id'     => $order["id"],
                'domain' => $options["domain"],
            ]);


            echo Utility::jencode([
                'status' => "successful",
            ]);

            return true;
        }

        private function reseller_order_modify_child_nameserver($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            if (!$module || !method_exists($module, 'ModifyCNS')) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This service is not supported for this domain.",
                ]);
                return false;
            }

            $ns = Filter::domain($data["ns"]);
            $ip = Filter::ip($data["ip"]);

            if (Validation::isEmpty($ns) || Validation::isEmpty($ip)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/add-cns-error1"),
                ]);
                return false;
            }

            if (!stristr($ns, $order["options"]["domain"])) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/add-cns-error3"),
                ]);
                return false;
            }

            $list = $module->CNSList($options);
            $cns = [
                'ns' => $ns,
                'ip' => $ip,
            ];

            if ($list) foreach ($list as $r) if ($r["ns"] == $ns) $cns = $r;


            $response = $module->ModifyCNS($options, $cns, $ns, $ip);
            if (!$response) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error ?: 'Unknown error',
                ]);
                return false;
            }

            $options["cns_list"] = $module->CNSList($options) ?: [];

            $set_order = [];

            $set_order['options'] = Utility::jencode($options);

            Orders::set($order["id"], $set_order);

            User::addAction($this->user["id"], "added", "changed-domain-cns", [
                'old_ns' => $cns["ns"],
                'old_ip' => $cns["ip"],
                'new_ns' => $ns,
                'new_ip' => $ip,
                'domain' => $options["domain"],
            ]);


            echo Utility::jencode([
                'status' => "successful",
            ]);

            return true;
        }

        private function reseller_order_delete_child_nameserver($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            if (!$module || !method_exists($module, 'DeleteCNS')) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This service is not supported for this domain.",
                ]);
                return false;
            }

            $ns = Filter::domain($data["ns"]);
            $ip = Filter::ip($data["ip"]);

            if (Validation::isEmpty($ns)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/add-cns-error1"),
                ]);
                return false;
            }

            if (!stristr($ns, $order["options"]["domain"])) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/add-cns-error3"),
                ]);
                return false;
            }

            $list = $module->CNSList($options);
            $cns = [
                'ns' => $ns,
                'ip' => $ip,
            ];

            if ($list) foreach ($list as $r) if ($r["ns"] == $ns) $cns = $r;


            $response = $module->DeleteCNS($options, $cns["ns"], $cns["ip"]);
            if (!$response) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error ?: 'Unknown error',
                ]);
                return false;
            }

            $options["cns_list"] = $module->CNSList($options) ?: [];

            $set_order = [];

            $set_order['options'] = Utility::jencode($options);

            Orders::set($order["id"], $set_order);

            User::addAction($this->user["id"], "added", "deleted-domain-cns", [
                'cns-name' => $cns["ns"],
                'cns-ip'   => $cns["ip"],
                'name'     => $order["name"],
                'id'       => $order["id"],
            ]);


            echo Utility::jencode([
                'status' => "successful",
            ]);

            return true;
        }

        private function reseller_order_get_auth_code($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            if (isset($options["transferlock"]) && $options["transferlock"]) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/submit-transfer-code-error1"),
                ]);
                return false;
            }

            if ($module && method_exists($module, "getAuthCode")) {
                $getAuthCode = $module->getAuthCode($options);
                if (!$getAuthCode) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/submit-transfer-code-error3", ['{error}' => $module->error]),
                    ]);
                    return false;
                }

                User::addAction($this->user["id"], "send", "sent-domain-transfer-code", [
                    'domain' => $options["domain"],
                    'id'     => $order["id"],
                ]);
            } else {
                $getAuthCode = $options["transfer-code"] ?? '';

                if (!$getAuthCode) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/submit-transfer-code-error3", ['{error}' => 'Not defined']),
                    ]);
                    return false;
                }
            }


            echo Utility::jencode([
                'status' => "successful",
                'code'   => $getAuthCode,
            ]);

            return true;
        }

        private function reseller_order_dns_records($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            if (!$module || !method_exists($module, 'getDnsRecords')) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This service is not supported for this domain.",
                ]);
                return false;
            }

            $response = $module->getDnsRecords();

            if (!is_array($response)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error ?: 'Unknown error',
                ]);
                return false;
            }

            echo Utility::jencode([
                'status' => "successful",
                'data'   => $response,
            ]);

            return true;
        }

        private function reseller_order_add_dns_record($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            if (!$module || !method_exists($module, 'addDnsRecord')) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This service is not supported for this domain.",
                ]);
                return false;
            }

            $type = Filter::letters_numbers($data["type"] ?? false);
            $name = Filter::html_clear($data["name"] ?? false);
            $value = Filter::html_clear($data["value"] ?? false);
            $ttl = Filter::numbers($data["ttl"] ?? false);
            $priority = Filter::numbers($data["priority"] ?? '');


            if (Validation::isEmpty($type) || Validation::isEmpty($name) || Validation::isEmpty($value)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-dns-records-12"),
                ]);
                return false;
            }

            if (!in_array($type, $module_data["config"]["settings"]["dns-record-types"] ?? [])) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown dns type",
                ]);
                return false;
            }

            $apply = $module->addDnsRecord($type, $name, $value, $ttl, $priority);

            if (!$apply) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]);
                return false;
            }

            User::addAction($this->user["id"], "alteration", "domain-dns-record-created", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);

            return true;
        }

        private function reseller_order_modify_dns_record($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            if (!$module || !method_exists($module, 'updateDnsRecord')) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This service is not supported for this domain.",
                ]);
                return false;
            }

            $identity = Filter::html_clear($data["identity"] ?? false);
            $type = Filter::letters_numbers($data["type"] ?? false);
            $name = Filter::html_clear($data["name"] ?? false);
            $value = Filter::html_clear($data["value"] ?? false);
            $ttl = Filter::numbers($data["ttl"] ?? false);
            $priority = Filter::numbers($data["priority"] ?? '');


            if (Validation::isEmpty($type) || Validation::isEmpty($name) || Validation::isEmpty($value)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-dns-records-12"),
                ]);
                return false;
            }

            if (!in_array($type, $module_data["config"]["settings"]["dns-record-types"] ?? [])) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown dns type",
                ]);
                return false;
            }

            $apply = $module->updateDnsRecord($type, $name, $value, $identity, $ttl, $priority);

            if (!$apply) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]);

                return false;
            }

            User::addAction($this->user["id"], "alteration", "domain-dns-record-updated", [
                'domain' => $options["domain"],
                'type'   => $type,
                'name'   => $name,
                'value'  => $value,
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);

            return true;
        }

        private function reseller_order_delete_dns_record($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            if (!$module || !method_exists($module, 'deleteDnsRecord')) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This service is not supported for this domain.",
                ]);
                return false;
            }

            $identity = Filter::html_clear($data["identity"] ?? false);
            $type = Filter::letters_numbers($data["type"] ?? false);
            $name = Filter::html_clear($data["name"] ?? false);
            $value = Filter::html_clear($data["value"] ?? false);


            if (Validation::isEmpty($type) || Validation::isEmpty($name) || Validation::isEmpty($value)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-dns-records-12"),
                ]);
                return false;
            }

            if (!in_array($type, $module_data["config"]["settings"]["dns-record-types"] ?? [])) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown dns type",
                ]);
                return false;
            }

            $apply = $module->deleteDnsRecord($type, $name, $value, $identity);

            if (!$apply) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]);

                return false;
            }

            User::addAction($this->user["id"], "alteration", "domain-dns-record-deleted", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);

            return true;
        }

        private function reseller_order_dnssec_records($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            if (!$module || !method_exists($module, 'getDnsSecRecords')) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This service is not supported for this domain.",
                ]);
                return false;
            }

            $response = $module->getDnsSecRecords();

            if (!is_array($response)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error ?: 'Unknown error',
                ]);
                return false;
            }

            echo Utility::jencode([
                'status' => "successful",
                'data'   => $response,
            ]);

            return true;
        }

        private function reseller_order_add_dnssec_record($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            if (!$module || !method_exists($module, 'addDnsSecRecord')) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This service is not supported for this domain.",
                ]);
                return false;
            }

            $digest = Filter::html_clear($data["digest"] ?? '');
            $key_tag = Filter::html_clear($data["key_tag"] ?? '');
            $digest_type = Filter::numbers($data["digest_type"] ?? '');
            $algorithm = Filter::numbers($data["algorithm"] ?? '');


            if (Validation::isEmpty($digest) || Validation::isEmpty($key_tag) || Validation::isEmpty($digest_type) || Validation::isEmpty($algorithm)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-dns-records-12"),
                ]);
                return false;
            }

            if (!in_array($digest_type, array_keys($module_data["config"]["settings"]["dns-digest-types"]) ?? []))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown dns digest type",
                ]));

            if (!in_array($algorithm, array_keys($module_data["config"]["settings"]["dns-algorithms"]) ?? []))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown dns algorithm",
                ]));


            $apply = $module->addDnsSecRecord($digest, $key_tag, $digest_type, $algorithm);

            if (!$apply) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]);
                return false;
            }


            User::addAction($this->user["id"], "alteration", "domain-dns-sec-record-created", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);

            return true;
        }

        private function reseller_order_delete_dnssec_record($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            if (!$module || !method_exists($module, 'deleteDnsSecRecord')) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This service is not supported for this domain.",
                ]);
                return false;
            }

            $identity = Filter::html_clear($data["identity"] ?? '');
            $digest = Filter::html_clear($data["digest"] ?? '');
            $key_tag = Filter::html_clear($data["key_tag"] ?? '');
            $digest_type = Filter::numbers($data["digest_type"] ?? '');
            $algorithm = Filter::numbers($data["algorithm"] ?? '');


            if (Validation::isEmpty($digest) || Validation::isEmpty($key_tag) || Validation::isEmpty($digest_type) || Validation::isEmpty($algorithm)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-dns-records-12"),
                ]);
                return false;
            }

            if (!in_array($digest_type, array_keys($module_data["config"]["settings"]["dns-digest-types"]) ?? []))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown dns digest type",
                ]));

            if (!in_array($algorithm, array_keys($module_data["config"]["settings"]["dns-algorithms"]) ?? []))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown dns algorithm",
                ]));


            $apply = $module->deleteDnsSecRecord($digest, $key_tag, $digest_type, $algorithm, $identity);

            if (!$apply) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]);
                return false;
            }


            User::addAction($this->user["id"], "alteration", "domain-dns-sec-record-deleted", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);

            return true;
        }

        private function reseller_order_forwarding_domain($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            if (!$module || !method_exists($module, 'getForwardingDomain')) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This service is not supported for this domain.",
                ]);
                return false;
            }

            $response = $module->getForwardingDomain();

            if (!$response) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error ?: 'Unknown error',
                ]);
                return false;
            }

            if ($response["domain"]) {
                $response["address"] = $response["domain"];
                unset($response["domain"]);
            }


            echo Utility::jencode([
                'status' => "successful",
                'data'   => $response,
            ]);

            return true;
        }

        private function reseller_order_set_forwarding_domain($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            if (!$module || !method_exists($module, 'setForwardingDomain')) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This service is not supported for this domain.",
                ]);
                return false;
            }

            $protocol = Filter::letters($data["protocol"] ?? '');
            $method = Filter::numbers($data["method"] ?? '');
            $domain = str_replace(["https://", "http://"], "", Utility::strtolower($data["address"] ?? ''));

            if (stristr($domain, '/')) {
                $parse_domain = explode("/", $domain);
                $domain = $parse_domain[0];
            }
            $domain = Filter::domain($domain);

            if (Validation::isEmpty($protocol) || Validation::isEmpty($method) || Validation::isEmpty($domain)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-forwarding-tx13") . $method,
                ]);
                return false;
            }

            if (!in_array($method, [301, 302])) $method = 301;
            if (!in_array($protocol, ["http", "https"])) $protocol = "http";

            $apply = $module->setForwardingDomain($protocol, $method, $domain);

            if (!$apply) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]);
                return false;
            }

            User::addAction($this->user["id"], "alteration", "domain-set-forward-domain", [
                'domain' => $options["domain"],
                'id'     => $order["id"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);

            return true;
        }

        private function reseller_order_forwarding_email($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            if (!$module || !method_exists($module, 'getEmailForwards')) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This service is not supported for this domain.",
                ]);
                return false;
            }

            $response = $module->getEmailForwards();

            if (!is_array($response)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error ?: 'Unknown error',
                ]);
                return false;
            }

            echo Utility::jencode([
                'status' => "successful",
                'data'   => $response,
            ]);

            return true;
        }

        private function reseller_order_add_forwarding_email($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            if (!$module || !method_exists($module, 'addForwardingEmail')) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This service is not supported for this domain.",
                ]);
                return false;
            }

            $prefix = Filter::email($data["prefix"] ?? '');
            $target = Filter::email($data["target"] ?? '');

            if (Validation::isEmpty($prefix) || Validation::isEmpty($target)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-forwarding-tx20"),
                ]);
                return false;
            }


            $apply = $module->addForwardingEmail($prefix, $target);

            if (!$apply) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error ?: 'Unknown error',
                ]);
                return false;
            }

            User::addAction($this->user["id"], "alteration", "domain-email-forward-created", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);

            return true;
        }

        private function reseller_order_modify_forwarding_email($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            if (!$module || !method_exists($module, 'updateForwardingEmail')) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This service is not supported for this domain.",
                ]);
                return false;
            }

            $prefix = Filter::email($data["prefix"] ?? '');
            $target = Filter::email($data["target"] ?? '');
            $target_new = Filter::email($data["target_new"] ?? '');
            $identity = Filter::html_clear($data["identity"] ?? '');

            if (Validation::isEmpty($prefix) || Validation::isEmpty($target) || Validation::isEmpty($target_new)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-forwarding-tx20"),
                ]);
                return false;
            }

            $apply = $module->updateForwardingEmail($prefix, $target, $target_new, $identity);

            if (!$apply) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error ?: 'Unknown error',
                ]);
                return false;
            }

            User::addAction($this->user["id"], "alteration", "domain-email-forward-updated", [
                'domain' => $options["domain"],
                'prefix' => $prefix,
                'target' => $target,
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);

            return true;
        }

        private function reseller_order_delete_forwarding_email($order = [], $data = [])
        {
            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $options = $order["options"];

            if ($order["type"] != "domain") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "It is only available for the domain product",
                ]);
                return false;
            }

            $moduleName = $order["module"] ?? "none";
            $module = false;
            if ($moduleName && $moduleName != "none") {
                $module_data = Modules::Load("Registrars", $moduleName);
                if (class_exists($moduleName))
                    $module = new $moduleName();
            }

            if (!$module || !method_exists($module, 'deleteForwardingEmail')) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "This service is not supported for this domain.",
                ]);
                return false;
            }

            $prefix = Filter::email($data["prefix"] ?? '');
            $target = Filter::email($data["target"] ?? '');
            $identity = Filter::html_clear($data["identity"] ?? '');

            if (Validation::isEmpty($prefix) || Validation::isEmpty($target)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-forwarding-tx20"),
                ]);
                return false;
            }

            $apply = $module->deleteForwardingEmail($prefix, $target, $identity);

            if (!$apply) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error ?: 'Unknown error',
                ]);
                return false;
            }

            User::addAction($this->user["id"], "alteration", "domain-email-forward-deleted", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);

            return true;
        }

        private function reseller_order_list($data = [])
        {
            $sth = $this->model->db->select()->from("users_products");
            $sth->where("owner_id", "=", $this->user["id"]);
            $sth->order_by("id DESC");
            $sth = $sth->build() ? $sth->fetch_assoc() : [];

            $result = [
                'status' => 'successful',
                'total'  => sizeof($sth),
            ];

            if ($sth) {
                foreach ($sth as $row) {
                    $row["options"] = Utility::jdecode($row["options"], true);

                    $_data = [
                        'id'           => $row["id"],
                        'status'       => $row["status"],
                        'product_type' => $row["type"],
                        'product_id'   => $row["product_id"],
                        'product_name' => $row["name"],
                    ];

                    $_data["period_type"] = $row["period"];
                    $_data["period_duration"] = $row["period_time"];
                    $_data["product_amount"] = $row["amount"];
                    $_data["product_amount_f"] = Money::formatter_symbol($row["amount"], $row["amount_cid"]);
                    $_data["currency"] = Money::Currency($row["amount_cid"])["code"];


                    if (isset($row["options"]["domain"]) && $row["options"]["domain"])
                        $_data["domain"] = $row["options"]["domain"];

                    if (isset($row["options"]["hostname"]) && $row["options"]["hostname"])
                        $_data["hostname"] = $row["options"]["hostname"];

                    if (isset($row["options"]["ip"]) && $row["options"]["ip"])
                        $_data["ip"] = $row["options"]["ip"];

                    if (isset($row["options"]["assigned_ips"]) && $row["options"]["assigned_ips"])
                        $_data["assigned_ips"] = explode("\n", $row["options"]["assigned_ips"]);

                    if (isset($row["options"]["ns1"]) && $row["options"]["ns1"])
                        $_data["ns1"] = $row["options"]["ns1"];

                    if (isset($row["options"]["ns2"]) && $row["options"]["ns2"])
                        $_data["ns2"] = $row["options"]["ns2"];

                    if (isset($row["options"]["ns3"]) && $row["options"]["ns3"])
                        $_data["ns3"] = $row["options"]["ns3"];

                    if (isset($row["options"]["ns4"]) && $row["options"]["ns4"])
                        $_data["ns4"] = $row["options"]["ns4"];


                    $_data["creation_date"] = $row["cdate"];
                    $_data["renewal_date"] = $row["renewaldate"];
                    $_data["due_date"] = substr($row["duedate"], 0, 4) == "1881" ? DateManager::zero() : $row["duedate"];


                    $result["data"][] = $_data;
                }
            }

            echo Utility::jencode($result);

            return true;
        }

        private function api($group, $action, $data)
        {
            try {
                $headers = getallheaders();
                if (!$headers) $headers = [];
                $key = $headers["Apikey"] ?? ($headers["apikey"] ?? '');
                $ip = UserManager::GetIP();

                $api = $this->model->db->select()->from("api_credentials");
                $api->where("identifier", "=", $key);
                $api = $api->build() ? $api->getAssoc() : [];
                if (!$api) throw new Exception("Credentials do not match");

                # IP Check #
                if (strlen($api["ips"]) > 3) {
                    $ips = explode(",", $api["ips"]);
                    if (!in_array($ip, $ips)) throw new Exception("IP address " . $ip . " does not have access authority");
                }
                # IP Check #

                # Action Check #
                $permissions = Utility::jdecode($api["permissions"]);
                if (!in_array($group . "/" . $action, $permissions)) throw new Exception('You are not authorized to use action "' . $group . "/" . $action . '"');
                # Action Check #

                Helper::Load("Api");

                $data_de = Utility::jdecode($data, true);
                if ($data_de) $data = $data_de;

                Api::set_credential($api);
                if (method_exists("Api", "check_limit")) Api::check_limit();

                Api::set($api["id"], ['last_access' => DateManager::Now()]);
                $instance = Api::$group();
                $reflection = new ReflectionMethod($instance, $action);
                $parameters = $reflection->getParameters();
                if (isset($_GET["route"])) unset($_GET["route"]);
                if (property_exists($instance, "endpoint")) $instance->endpoint = $this->params;
                if (property_exists($instance, "query_params")) $instance->query_params = $_GET;

                $headers["endpoint"] = $this->params;
                $headers["query_params"] = $_GET;

                $result = $parameters ? $instance->$action($data) : $instance->$action();

                echo Utility::jencode($result);


                Api::save_log($api["id"], $_SERVER["REQUEST_METHOD"] ?? "GET", $action, $headers, $data, $result, $ip);


                return true;
            } catch (Exception $e) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $e->getMessage(),
                ]);
                return false;
            }
        }


        private function sms_cancellation_post($order = [])
        {

            $this->takeDatas([
                "language",
            ]);

            $phone = Filter::init("POST/phone", "numbers");

            if (Validation::isEmpty($phone))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Lütfen gsm numaranızı giriniz.",
                ]));

            if (strlen($phone) != 11)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Lütfen geçerli bir gsm numarası giriniz.",
                ]));

            if (!Validation::isPhone("+9" . $phone))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Lütfen geçerli bir gsm numarası giriniz.",
                ]));

            $options = $order["options"];
            $black_list = isset($options["black_list"]) ? $options["black_list"] : false;

            if (stristr($black_list, $phone))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "GSM Numarası sistemde zaten kayıtlıdır.",
                ]));

            $black_list .= "\n" . $phone;

            $options["black_list"] = $black_list;

            Orders::set($order["id"], [
                'options' => Utility::jencode($options),
            ]);

            Helper::Load(["User"]);

            User::addAction($order["owner_id"], "added", "the-number-owner-added-himself-to-the-black-list", [
                'order_id' => $order["id"],
            ]);

            echo Utility::jencode(['status' => "successful"]);

        }

    }