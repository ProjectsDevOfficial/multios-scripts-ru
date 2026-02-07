<?php
    /**
     * @author WISECP LLC
     * @since 2017
     * @copyright All rights reserved for WISECP LLC.
     * @contract https://my.wisecp.com/en/service-and-use-agreement
     * @warning Unlicensed can not be copied, distributed and can not be used.
     **/

    defined('CORE_FOLDER') or exit('You can not get in here!');

    class Controller extends Controllers
    {
        protected $params, $data = [], $pagination = [];

        public function __construct($arg = [])
        {
            parent::__construct();
            $this->params = $arg['params'];

            $do_login = UserManager::LoginCheck("member") ? false : true;

            if (isset($this->params[0]) && $this->params[0] == "share")
                if (Config::get("options/invoice-show-requires-login") || UserManager::LoginCheck("admin"))
                    $do_login = false;


            if ($do_login) {
                Utility::redirect($this->CRLink("sign-in"));
                die();
            }


            $udata = UserManager::LoginData("member");
            $redirect_link = $udata ? User::full_access_control_account($udata) : false;
            if ($redirect_link) {
                Utility::redirect($redirect_link);
                die();
            }

            if (!Config::get("options/invoice-system")) {
                $this->main_404();
                die();
            }

            Helper::Load(["Invoices"]);
        }

        public function main()
        {
            if (isset($this->params[0]) && $this->params[0] == "detail") return $this->detail_main();
            if (isset($this->params[0]) && $this->params[0] == "share") return $this->share_main();
            elseif (isset($this->params[0]) && $this->params[0] == "ajax") return $this->ajax_list_main();
            elseif (isset($this->params[0]) && $this->params[0] == "bulk-payment") return $this->bulk_payment_main();
            else
                return $this->list_main();
        }

        private function get_invoices($user_id = 0, $searches = [], $orders = [], $start = 0, $end = 10)
        {
            $data = $this->model->get_invoices($user_id, $searches, $orders, $start, $end);
            if ($data) {
                $keys = array_keys($data);
                $size = sizeof($keys);
                $size -= 1;
                for ($i = 0; $i <= $size; $i++) {
                    $var = $data[$keys[$i]];
                    $data[$keys[$i]]["creation_date"] = DateManager::format(Config::get("options/date-format"), $var["cdate"]);
                    $data[$keys[$i]]["due_date"] = DateManager::format(Config::get("options/date-format"), $var["duedate"]);
                    $data[$keys[$i]]["detail_link"] = $this->CRLink("ac-ps-detail-invoice", [$var["id"]]);
                }
            }
            return $data;
        }

        private function ajax_list_main()
        {
            $this->takeDatas("language");

            $udata = UserManager::LoginData("member");
            $limit = Config::get("options/limits/account-invoices");
            $output = [];
            $aColumns = array('id', 'cdate', 'duedate', 'total', 'rank', '');

            $start = Filter::init("GET/iDisplayStart", "numbers");
            if (!Validation::isInt($start) || $start < 0 || $start > 5000) $start = 0;
            $end = Filter::init("GET/iDisplayLength", "numbers");
            if ($end == -1) $end = 10000;
            elseif (!Validation::isInt($end) || $end < 1) $end = $limit;


            $orders = [];
            if (Filter::GET("iSortingCols")) {
                $iSortingCols = Filter::init("GET/iSortingCols", "numbers");
                for ($i = 0; $i < $iSortingCols; $i++) {
                    $isortCol = Filter::init("GET/iSortCol_" . $i, "numbers");
                    if (isset($aColumns[$isortCol]) && $aColumns[$isortCol] != '') {
                        $bSortabLe = Filter::init("GET/bSortable_" . $isortCol, "letters");
                        if ($bSortabLe == "true") {
                            $sortDir = Filter::init("GET/sSortDir_" . $i, "letters");
                            $sortDir = $sortDir == "asc" ? "ASC" : "DESC";
                            $orders[] = $aColumns[$isortCol] . " " . $sortDir;
                        }
                    }
                }
            }

            $searches = [];
            if (!Validation::isEmpty(Filter::GET("sSearch"))) $searches['word'] = Filter::init("GET/sSearch", "hclear");

            $filteredList = $this->get_invoices($udata["id"], $searches, $orders, $start, $end);
            $filterTotal = $this->model->get_total_invoices($udata["id"], $searches);
            $listTotal = $this->model->get_total_invoices($udata["id"]);


            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                Helper::Load("Money");
                $situations = $this->view->chose("website")->render("common-needs", false, true, true);
                $situations = $situations["invoice"];

                if ($filteredList) {
                    $this->addData("situations", $situations);
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("website")->render("ajax-invoices", $this->data, false, true);
                }
            }


            echo Utility::jencode($output);
        }

        private function selection_result($invoice = [], $udata = [], $sharing = [])
        {
            $this->takeDatas("language");

            $pmethods = Config::get("modules/payment-methods");
            $taxation = Config::get("options/taxation");

            $items = Invoices::item_listing($invoice);
            if ($items) {
                foreach ($items as $item) {
                    if (isset($item["options"]["event"]) && $item["options"]["event"] == "ExtendAddonPeriod") {
                        $o_check = Models::$init->db->select("id,subscription_id")->from("users_products_addons");
                        $o_check->where("id", "=", $item["options"]["event_data"]["addon_id"], "&&");
                        $o_check->where("subscription_id", "!=", 0);
                        if ($o_check->build()) {
                            $o_check = $o_check->getObject();
                            $subscription = Orders::get_subscription($o_check->subscription_id);
                            if ($subscription && $subscription["status"] != "cancelled") {
                                $lang = Modules::Lang('Payment', $subscription["module"], Bootstrap::$lang->clang);
                                $m_name = $lang["invoice-name"];
                                echo Utility::jencode([
                                    'status'  => "error",
                                    'message' => __("website/account_invoices/error4", ['{module}' => $m_name]),
                                ]);
                                return false;
                            }
                        }
                    } elseif ($item["user_pid"]) {
                        $o_check = Models::$init->db->select("id,subscription_id")->from("users_products");
                        $o_check->where("id", "=", $item["user_pid"], "&&");
                        $o_check->where("subscription_id", "!=", 0);
                        if ($o_check->build()) {
                            $o_check = $o_check->getObject();
                            $subscription = Orders::get_subscription($o_check->subscription_id);
                            if ($subscription && $subscription["status"] != "cancelled") {
                                $lang = Modules::Lang('Payment', $subscription["module"], Bootstrap::$lang->clang);
                                $m_name = $lang["invoice-name"];
                                echo Utility::jencode([
                                    'status'  => "error",
                                    'message' => __("website/account_invoices/error4", ['{module}' => $m_name]),
                                ]);
                                return false;
                            }
                        }
                    }

                    if (isset($item["options"]["event"]) && $item["options"]["event"] == "addCredit") {
                        if (in_array("Balance", $pmethods)) {
                            $findmethod = array_search("Balance", $pmethods);
                            unset($pmethods[$findmethod]);
                        }
                    }
                }
            }

            if ($sharing) {

                if ($sharing["payer_udata"]) {
                    $udata = $sharing["payer_udata"];
                } else {
                    if (in_array("Balance", $pmethods)) {
                        $findmethod = array_search("Balance", $pmethods);
                        unset($pmethods[$findmethod]);
                    }
                }

            }

            $infos = User::getInfo($udata["id"], "dealership,taxation,block-payment-gateways");
            $datas = User::getData($udata["id"], "lang,balance,balance_currency,currency", "array");

            $udata = is_array($udata) ? array_merge($udata, $infos, $datas) : [];


            $result = [
                'subtotal'        => 0,
                'pcommission'     => 0,
                'total'           => 0,
                'visible_sendbta' => Config::get("options/send-bill-to-address/status") == 1,
            ];

            $s_sendbta = Filter::POST("sendbta") ? true : false;
            $s_pmethod = Filter::init("POST/pmethod", "route");


            if (isset($udata["block-payment-gateways"]) && $udata["block-payment-gateways"] && $pmethods) {
                if ($udata["block-payment-gateways"]) {
                    $block_gws = explode(",", $udata["block-payment-gateways"]);
                    foreach ($pmethods as $k => $row) if (in_array($row, $block_gws)) unset($pmethods[$k]);
                    if ($pmethods) $pmethods = array_values($pmethods);
                }
            }

            $currencyInfo = Money::Currency($invoice["currency"]);
            if ($currencyInfo && $pmethods) {
                $c_modules = $currencyInfo["modules"] ?? '';
                $c_modules = $c_modules ? explode(",", $c_modules) : [];
                $c_modules = array_map('trim', $c_modules);
                if ($c_modules) foreach ($pmethods as $k => $row) if (!in_array($row, $c_modules)) unset($pmethods[$k]);
                if (!$pmethods) {
                    echo Utility::jencode(['status' => "error", 'message' => __("website/basket/error21")]);
                    return false;
                }
            }


            if (!$s_pmethod && $pmethods) $s_pmethod = $pmethods[0];


            $currency = $invoice["currency"];

            if ($taxation && !$invoice["local"]) $taxation = false;
            if ($taxation && !$invoice["legal"]) $taxation = false;

            if (Config::get("options/send-bill-to-address/status") && $s_pmethod != "Balance" && $taxation) {
                $sendbta_amount = Config::get("options/send-bill-to-address/amount");
                $sendbta_curr = Config::get("options/send-bill-to-address/cid");
                $sendbta_price = Money::exChange($sendbta_amount, $sendbta_curr, $currency);

                if ($sendbta_price > 0 && $invoice["taxation_type"] == "inclusive")
                    $sendbta_price -= Money::get_inclusive_tax_amount($sendbta_price, $invoice["taxrate"]);

                $sendbta_fee = __("website/basket/free-paid");
                if ($sendbta_price > 0) {
                    if ($s_sendbta) {
                        $invoice["sendbta"] = 1;
                        $invoice["sendbta_amount"] = $sendbta_price;
                        $result["visible_sendbta"] = true;
                    }
                    $sendbta_fee = Money::formatter_symbol($sendbta_price, $currency);
                }
                if ($s_pmethod == "Balance") $result["visible_sendbta"] = false;
                $result["sendbta_fee"] = $sendbta_fee;
            } else
                $result["visible_sendbta"] = false;

            if (!$s_sendbta) $invoice["sendbta_amount"] = 0;

            if ($invoice["status"] == "unpaid") $invoice["pmethod_commission"] = 0;

            $calculate = Invoices::calculate_invoice($invoice, $items, ['included_d_subtotal' => true]);

            $total = $calculate['subtotal'];

            $methods = [];

            if ($pmethods) {
                foreach ($pmethods as $key => $method) {
                    Modules::Load("Payment", $method);
                    if (class_exists($method)) {
                        $module = new $method();

                        $country_code = $invoice['user_data']['address']['country_code'];

                        if (isset($module->config["settings"]["accepted_countries"])) {
                            if ($module->config["settings"]["accepted_countries"])
                                if (!in_array($country_code, $module->config["settings"]["accepted_countries"])) continue;
                        }

                        if (isset($module->config["settings"]["unaccepted_countries"])) {
                            if ($module->config["settings"]["unaccepted_countries"])
                                if (in_array($country_code, $module->config["settings"]["unaccepted_countries"])) continue;
                        }


                        $mdata = [
                            'method'      => $method,
                            'name'        => $module->lang['invoice-name'],
                            'option_name' => $module->lang['option-name'],
                        ];
                        if ($method == "Balance") $mdata["balance"] = Money::formatter_symbol($udata["balance"], $udata["balance_currency"]);
                        $camount = 0;
                        if (method_exists($module, "commission_fee_calculator") && $module->commission) {
                            $camount = $module->commission_fee_calculator($total);
                            if ($camount) $mdata["commission_fee"] = Money::formatter_symbol($camount, $currency);
                        }
                        if ($s_pmethod == $method) {
                            $mdata["selected"] = true;
                            $force_convert_to = $module->config["settings"]["force_convert_to"] ?? 0;
                            if ($camount) {
                                $result["pcommission"] = $mdata["commission_fee"];
                                $pmethod_commission_rate = $module->get_commission_rate();
                                $result["pcommission_rate"] = $pmethod_commission_rate;
                                $invoice['pmethod_commission'] = $camount;
                            }
                        }
                        $methods[] = $mdata;
                    }
                }
                $result["pmethods"] = $methods;
            }
            $invoice['pmethod'] = $s_pmethod;
            $calculate = Invoices::calculate_invoice($invoice, $items);

            $invoice['subtotal'] = $calculate['subtotal'];
            $invoice['tax'] = $calculate['tax'];
            $invoice['total'] = $calculate['total'];

            $result["subtotal"] = Money::formatter_symbol($invoice["subtotal"], $currency);

            $balance_taxation = Config::get("options/balance-taxation");

            if ($s_pmethod == "Balance" && $balance_taxation == "y") $invoice['tax'] = 0;


            $result["tax"] = Money::formatter_symbol($invoice['tax'], $currency);


            $result["total"] = Money::formatter_symbol($invoice['total'], $currency);

            echo Utility::jencode($result);
        }

        private function selection_result_bulk_payment($udata = [], $invoices = [])
        {
            $this->takeDatas("language");

            Helper::Load("Orders");

            $s_invoices = Filter::init("POST/invoices");
            $s_invoices_x = Filter::html_clear($s_invoices);
            if (!is_array($s_invoices)) $s_invoices = $s_invoices_x ? explode(",", $s_invoices_x) : [];


            $pmethods = Config::get("modules/payment-methods");
            $taxation = Config::get("options/taxation");

            $infos = User::getInfo($udata["id"], "dealership,taxation,block-payment-gateways");
            $datas = User::getData($udata["id"], "balance,balance_currency,currency", "array");

            $udata = is_array($udata) ? array_merge($udata, $infos, $datas) : [];

            $country_code = false;


            if ($invoices) {
                foreach ($invoices as $invoice) {
                    if ($s_invoices && !in_array($invoice['id'], $s_invoices)) continue;
                    $items = Invoices::item_listing($invoice);
                    if ($items) {
                        if (!$country_code) $country_code = $invoice['user_data']['address']['country_code'];

                        foreach ($items as $item) {

                            if (isset($item["options"]["event"]) && $item["options"]["event"] == "ExtendAddonPeriod") {
                                $o_check = Models::$init->db->select("id,subscription_id")->from("users_products_addons");
                                $o_check->where("id", "=", $item["options"]["event_data"]["addon_id"], "&&");
                                $o_check->where("subscription_id", "!=", 0);
                                if ($o_check->build()) {
                                    $o_check = $o_check->getObject();
                                    $subscription = Orders::get_subscription($o_check->subscription_id);
                                    if ($subscription && $subscription["status"] != "cancelled") {
                                        $lang = Modules::Lang('Payment', $subscription["module"], Bootstrap::$lang->clang);
                                        $m_name = $lang["invoice-name"];
                                        echo Utility::jencode([
                                            'status'  => "error",
                                            'message' => __("website/account_invoices/error4", ['{module}' => $m_name]),
                                        ]);
                                        return false;
                                    }
                                }
                            } elseif ($item["user_pid"]) {
                                $o_check = Models::$init->db->select("id,subscription_id")->from("users_products");
                                $o_check->where("id", "=", $item["user_pid"], "&&");
                                $o_check->where("subscription_id", "!=", 0);
                                if ($o_check->build()) {
                                    $o_check = $o_check->getObject();
                                    $subscription = Orders::get_subscription($o_check->subscription_id);
                                    if ($subscription && $subscription["status"] != "cancelled") {
                                        $lang = Modules::Lang('Payment', $subscription["module"], Bootstrap::$lang->clang);
                                        $m_name = $lang["invoice-name"];
                                        echo Utility::jencode([
                                            'status'  => "error",
                                            'message' => __("website/account_invoices/error4", ['{module}' => $m_name]),
                                        ]);
                                        return false;
                                    }
                                }
                            }

                            if (isset($item["options"]["event"]) && $item["options"]["event"] == "addCredit") {
                                if (in_array("Balance", $pmethods)) {
                                    $findmethod = array_search("Balance", $pmethods);
                                    unset($pmethods[$findmethod]);
                                }
                            }
                        }
                    }
                }
            }

            $currency = Money::getUCID();
            $subtotal = 0;
            $tax = 0;
            $total = 0;


            $result = [
                'subtotal'    => 0,
                'tax'         => 0,
                'pcommission' => 0,
                'total'       => 0,
            ];

            $s_pmethod = Filter::init("POST/pmethod", "route");

            if (isset($udata["block-payment-gateways"]) && $udata["block-payment-gateways"] && $pmethods) {
                if ($udata["block-payment-gateways"]) {
                    $block_gws = explode(",", $udata["block-payment-gateways"]);
                    foreach ($pmethods as $k => $row) if (in_array($row, $block_gws)) unset($pmethods[$k]);
                    if ($pmethods) $pmethods = array_values($pmethods);
                }
            }

            $currencyInfo = Money::Currency($currency);
            if ($currencyInfo && $pmethods) {
                $c_modules = $currencyInfo["modules"] ?? '';
                $c_modules = $c_modules ? explode(",", $c_modules) : [];
                $c_modules = array_map('trim', $c_modules);
                if ($c_modules) foreach ($pmethods as $k => $row) if (!in_array($row, $c_modules)) unset($pmethods[$k]);
                if (!$pmethods) {
                    echo Utility::jencode(['status' => "error", 'message' => __("website/basket/error21")]);
                    return false;
                }
            }


            if (!$s_pmethod && $pmethods) $s_pmethod = $pmethods[0];


            if ($invoices) {
                foreach ($invoices as $invoice) {
                    if ($s_invoices && !in_array($invoice['id'], $s_invoices)) continue;

                    $invoice['pmethod'] = $s_pmethod;
                    $items = Invoices::get_items($invoice['id']);
                    $calculate = Invoices::calculate_invoice($invoice, $items, [
                        'included_d_subtotal' => true,
                    ]);

                    $invoice['subtotal'] = $calculate['subtotal'];
                    $invoice['tax'] = $calculate['tax'];
                    $invoice['total'] = $calculate['total'];

                    $subtotal += Money::exChange($invoice["subtotal"], $invoice["currency"], $currency);
                    $tax += Money::exChange($invoice["tax"], $invoice["currency"], $currency);
                    $total += Money::exChange($invoice["total"], $invoice["currency"], $currency);
                }
            }

            $methods = [];
            if ($pmethods) {
                foreach ($pmethods as $key => $method) {
                    Modules::Load("Payment", $method);
                    if (class_exists($method)) {
                        $module = new $method();

                        if (isset($module->config["settings"]["accepted_countries"])) {
                            if ($module->config["settings"]["accepted_countries"])
                                if (!in_array($country_code, $module->config["settings"]["accepted_countries"])) continue;
                        }

                        if (isset($module->config["settings"]["unaccepted_countries"])) {
                            if ($module->config["settings"]["unaccepted_countries"])
                                if (in_array($country_code, $module->config["settings"]["unaccepted_countries"])) continue;
                        }


                        $mdata = [
                            'method'      => $method,
                            'name'        => $module->lang['invoice-name'],
                            'option_name' => $module->lang['option-name'],
                        ];
                        if ($method == "Balance") $mdata["balance"] = Money::formatter_symbol($udata["balance"], $udata["balance_currency"]);
                        $camount = 0;
                        if (method_exists($module, "commission_fee_calculator") && $module->commission) {
                            $camount = $module->commission_fee_calculator($total);
                            if ($camount) $mdata["commission_fee"] = Money::formatter_symbol($camount, $currency);
                        }
                        if ($s_pmethod == $method) {
                            $mdata["selected"] = true;
                            $force_convert_to = $module->config["settings"]["force_convert_to"] ?? 0;
                            if ($camount) {
                                $result["pcommission"] = $mdata["commission_fee"];
                                $pmethod_commission_rate = $module->get_commission_rate();
                                $result["pcommission_rate"] = $pmethod_commission_rate;
                                $subtotal += $camount;
                                $total += $camount;
                            }
                        }
                        $methods[] = $mdata;
                    }
                }
                $result["pmethods"] = $methods;
            }

            $result["subtotal"] = Money::formatter_symbol($subtotal, $currency);

            if ($s_pmethod != "Balance" && $taxation)
                $result["tax"] = Money::formatter_symbol($tax, $currency);

            $result["total"] = Money::formatter_symbol($total, $currency);

            echo Utility::jencode($result);
        }

        private function payment_screen($invoice = [], $udata = [], $sharing = [])
        {
            if (DEMO_MODE) return false;

            Helper::Load("Orders");

            $pmethods = Config::get("modules/payment-methods");
            $taxation = Config::get("options/taxation");
            $tax_rate = $invoice["taxrate"];


            if ($taxation && !$invoice["local"]) $taxation = false;
            if ($taxation && !$invoice["legal"]) $taxation = false;

            if ($taxation && $tax_rate == 0) $tax_rate = Config::get("options/tax-rate");
            elseif (!$taxation) $tax_rate = 0;

            $items = Invoices::get_items($invoice['id']);
            if ($items) {
                foreach ($items as $item) {
                    if (isset($item["options"]["event"]) && $item["options"]["event"] == "addCredit") {
                        if (in_array("Balance", $pmethods)) {
                            $findmethod = array_search("Balance", $pmethods);
                            unset($pmethods[$findmethod]);
                        }
                    }
                }
            }

            if ($sharing) {

                if ($sharing["payer_udata"]) {
                    $udata = $sharing["payer_udata"];
                } else {
                    if (in_array("Balance", $pmethods)) {
                        $findmethod = array_search("Balance", $pmethods);
                        unset($pmethods[$findmethod]);
                    }
                }

            }

            $s_sendbta = (bool)Filter::init("REQUEST/sendbta", "numbers");
            $s_pmethod = Filter::init("REQUEST/pmethod", "route");

            $currency = $invoice["currency"];
            $infos = User::getInfo($udata["id"], "dealership,taxation,identity,gsm,gsm_cc");
            $datas = User::getData($udata["id"], "id,name,surname,full_name,email,phone,ip,blacklist,balance,balance_currency,currency", "array");

            $udata = is_array($udata) ? array_merge($udata, $infos, $datas) : [];

            if (Config::get("options/send-bill-to-address/status")) {
                $sendbta_amount = Config::get("options/send-bill-to-address/amount");
                $sendbta_curr = Config::get("options/send-bill-to-address/cid");
                $sendbta_price = Money::exChange($sendbta_amount, $sendbta_curr, $currency);
                if ($sendbta_price > 0.0 && $invoice["taxation_type"] == "inclusive")
                    $sendbta_price -= Money::get_inclusive_tax_amount($sendbta_price, $invoice["taxrate"]);
                if ($s_sendbta) $invoice["sendbta_amount"] = $sendbta_price;
            }

            if ($invoice["status"] == "unpaid") $invoice["pmethod_commission"] = 0;

            $calculate = Invoices::calculate_invoice($invoice, $items, ['included_d_subtotal' => true]);

            $total = $calculate['subtotal'];

            if ($sharing) {
                $success = $this->CRLink("share-invoice") . "?token=" . $sharing["token"] . "&status=success";
                $failed = $this->CRLink("share-invoice") . "?token=" . $sharing["token"] . "&status=fail";
                $return = $this->CRLink("share-invoice") . "?token=" . $sharing["token"];
            } else {
                $success = $this->CRLink("ac-ps-detail-invoice", [$invoice["id"]]) . "?status=success";
                $failed = $this->CRLink("ac-ps-detail-invoice", [$invoice["id"]]) . "?status=fail";
                $return = $this->CRLink("ac-ps-detail-invoice", [$invoice["id"]]);
            }


            if (!$pmethods)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Empty payment methods",
                ]));

            if (Validation::isEmpty($s_pmethod))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_invoices/error1"),
                ]));

            if (!in_array($s_pmethod, $pmethods))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Not found payment method!",
                ]));

            $method = $s_pmethod;
            $selected_pmethod = $method;

            Modules::Load("Payment", $method);
            if (!class_exists($method)) exit("Payment method class not found!");

            $module = new $method();
            if ($method == "Balance") {
                $balance = Money::exChange($udata["balance"], $udata["balance_currency"], $currency);
                $balance = round($balance, 2);
            }

            if (method_exists($module, "commission_fee_calculator") && $module->commission)
                $camount = $module->commission_fee_calculator($total);
            else
                $camount = 0;

            $force_convert_to = $module->config["settings"]["force_convert_to"] ?? 0;

            $invoice['pmethod'] = $selected_pmethod;
            if ($camount) {
                $pcommission_rate = $module->get_commission_rate();
                $pcommission = $camount;
                $invoice['pmethod_commission'] = $pcommission;
            }


            $calculate = Invoices::calculate_invoice($invoice, $items, [
                'discount_total' => true,
            ]);
            $d_total = $calculate['discount_total'];
            $invoice['subtotal'] = $calculate['subtotal'];
            $invoice['tax'] = $calculate['tax'];
            $invoice['total'] = $calculate['total'];


            if ($selected_pmethod == "Balance") {
                if (!isset($balance) || round($balance, 2) < round($total, 2)) {
                    Utility::redirect($failed);
                    return false;
                }
            }

            $subscribable = [];
            $new_items = [];
            foreach ($items as $item) {
                $nitem = $item;
                $nitem["name"] = $item["description"];
                $new_items[] = $nitem;

                ## SUBSCRIPTION START
                if (isset($item["user_pid"]) && $item["user_pid"] > 0) {
                    $product_price = 0;
                    $product_cid = 0;

                    if (isset($item["options"]["event"]) && ($item["options"]["event"] == "ExtendAddonPeriod" || $item["options"]["event"] == "ProcessAddon")) {
                        $order = Orders::get_addon($item["options"]["event_data"]["addon_id"], 'addon_name AS name,id,addon_id,option_id,period,period_time,subscription_id,option_quantity');
                        $product = Products::addon($order["addon_id"], $invoice["user_data"]["lang"]);
                        $opt_quantity = $order["option_quantity"] ?? 1;
                        if ($opt_quantity == 0) $opt_quantity = 1;

                        if ($product) {
                            foreach ($product["options"] as $k => $v) {
                                if ($v["id"] == $order["option_id"]) {
                                    $product_price = $v["amount"] * $opt_quantity;
                                    $product_cid = $v["cid"];
                                }
                            }
                        }
                    } else {
                        $order = Orders::get($item["user_pid"], 'id,type,product_id,name,period,period_time,subscription_id');
                        if ($order) {
                            $product = Products::get($order["type"], $order["product_id"]);
                            if ($product) {
                                foreach ($product["price"] as $p) {
                                    if ($p["period"] == $order["period"] && $p["time"] == $order["period_time"]) {
                                        $product_price = $p["amount"];
                                        $product_cid = $p["cid"];
                                    }
                                }
                            }
                        }
                    }

                    if ($order && $product_price > 0.00 && ($order["period"] == "day" || $order["period"] == "week" || $order["period"] == "month" || $order["period"] == "year")) {
                        $sub_id = $order["subscription_id"];
                        if ($sub_id) {
                            $get_sub = Orders::get_subscription($sub_id);
                            if ($get_sub && $get_sub["status"] == "active") continue;
                        }

                        if ($invoice["taxation_type"] == "inclusive" && $tax_rate > 0.00)
                            $product_price -= Money::get_inclusive_tax_amount($product_price, $tax_rate);

                        $subscribable_item = [
                            'identifier'   => md5((isset($order["addon_id"]) ? "addon" : $order["type"]) . "|" . ($order["addon_id"] ?? $order["product_id"]) . "|" . $order["period"] . "|" . $order["period_time"]),
                            'product_type' => isset($order["addon_id"]) ? "addon" : $order["type"],
                            'product_id'   => $order["addon_id"] ?? $order["product_id"],
                            'period'       => $order["period"],
                            'period_time'  => $order["period_time"],
                            'name'         => (isset($order["addon_id"]) ? "+ " : '') . $order["name"],
                            'amount'       => $product_price,
                            'tax_included' => $product_price,
                            'currency'     => $product_cid,
                            'tax_rate'     => $tax_rate,
                            'tax_exempt'   => $item["taxexempt"],
                        ];
                        if (isset($order["addon_id"])) $subscribable_item["option_id"] = $order["option_id"];

                        if (isset($pcommission_rate) && $pcommission_rate > 0.00) {
                            $subscribable_item["commission_rate"] = $pcommission_rate;
                            $subscribable_item["amount"] += Money::get_exclusive_tax_amount($product_price, $pcommission_rate);
                            $subscribable_item["tax_included"] = $subscribable_item["amount"];
                        }

                        if ($invoice["tax"] > 0.00 && (!isset($item["taxexempt"]) || !$item["taxexempt"])) {
                            $subscribable_item["tax_rate"] = $tax_rate;
                            $subscribable_item["tax_included"] = $subscribable_item["amount"] + Money::get_exclusive_tax_amount($subscribable_item["amount"], $tax_rate);
                        }
                        $subscribable_item["tax_exempt"] = (isset($item["taxexempt"]) && $item["taxexempt"]) ? 1 : 0;
                        $subscribable[] = $subscribable_item;
                    }
                }
                ## SUBSCRIPTION END
            }

            if ($d_total > 0.0) {
                $new_items[] = [
                    'options'      => [],
                    'name'         => "DISCOUNT",
                    'quantity'     => 1,
                    'amount'       => -$d_total,
                    'total_amount' => -$d_total,
                ];
            }

            if (isset($pcommission) && $pcommission) {
                $new_items[] = [
                    'options'      => [],
                    'name'         => __("website/account_invoices/pmethod_commission", ['{method}' => $selected_pmethod]) . " (%" . $pcommission_rate . ")",
                    'quantity'     => 1,
                    'amount'       => $pcommission,
                    'total_amount' => $pcommission,
                ];
            }

            if (isset($invoice["user_data"]["kind"]) && $invoice["user_data"]["kind"] == "individual")
                if (Validation::isEmpty($invoice["user_data"]["identity"] ?? '') && !Validation::isEmpty($udata["identity"]))
                    $invoice["user_data"]["identity"] = $udata["identity"];

            if (Validation::isEmpty($invoice["user_data"]["phone"] ?? '') && !Validation::isEmpty($udata["phone"])) {
                $invoice["user_data"]["phone"] = $udata["phone"];
                $invoice["user_data"]["gsm_cc"] = $udata["gsm_cc"];
                $invoice["user_data"]["gsm"] = $udata["gsm"];
            }

            $options = [
                'type'                    => "bill",
                'user_data'               => $invoice["user_data"],
                'user_id'                 => $udata["id"],
                'invoice_id'              => $invoice["id"],
                'local'                   => $invoice["local"],
                'legal'                   => $selected_pmethod == "Balance" ? 0 : $invoice["legal"],
                'currency'                => $invoice["currency"],
                'subtotal'                => $invoice['subtotal'],
                'taxrate'                 => $tax_rate,
                'tax'                     => $invoice["tax"],
                'total'                   => $invoice["total"],
                'sendbta'                 => $s_sendbta && isset($sendbta_price) && $sendbta_price > 0.00 ? $sendbta_price : 0,
                'pmethod'                 => $selected_pmethod,
                'pmethod_commission'      => isset($pcommission) ? $pcommission : 0,
                'pmethod_commission_rate' => isset($pcommission_rate) ? $pcommission_rate : 0,
                'subscribable'            => $subscribable,
            ];

            Session::set("last_paid_page", Utility::jencode([
                'success' => $success,
                'failed'  => $failed,
                'return'  => $return,
            ]), true);

            Helper::Load(["Orders", "Invoices", "Products", "Money", "User", "Basket"]);

            $inv_last_ct_id = Session::get("inv_last_ct_id", true);

            if (!$inv_last_ct_id) {
                if (Config::get("options/blacklist/status")) {
                    if (Config::get("options/blacklist/order-blocking")) {
                        if (User::checkBlackList($udata)) {
                            Utility::redirect($failed, 3);
                            echo __("website/basket/error18", ['{err_msg}' => ' Detected by WFraud']);
                            exit();
                        }
                    }

                    if (Config::get("options/blacklist/ip-country-mismatch")) {
                        $ipInfo = UserManager::ip_info();
                        $info_country = strtoupper($ipInfo["countryCode"] ?? 'US');
                        $address_country = $options["user_data"]["address"]["country_code"] ?? 'US';
                        if ($info_country != $address_country) {
                            Utility::redirect($failed, 3);
                            echo __("website/basket/error18", ['{err_msg}' => ' ' . Bootstrap::$lang->get_cm("website/basket/error19")]);
                            exit();
                        }
                    }
                }

                $fraud_modules = Modules::Load('Fraud', 'All', false, true);
                if ($fraud_modules) {
                    $fraud_params = $options;
                    $fraud_params['items'] = isset($items) ? $items : [];
                    foreach ($fraud_modules as $k => $v) {
                        $k_m = "Fraud_" . $k;
                        if (class_exists($k_m)) {
                            $m_init = new $k_m();
                            if (method_exists($m_init, 'check')) {
                                $check = $m_init->check($fraud_params);
                                $err_msg = $m_init->error;
                                if (!$check) {
                                    Utility::redirect($failed, 3);
                                    echo 'Fraud Error: ' . ($err_msg ? $err_msg : __("website/basket/error18", ['{err_msg}' => ' Detected by ' . $k]));
                                    exit();
                                }
                            }
                        }
                    }
                }


                $options['redirect'] = [
                    'success' => $success,
                    'failed'  => $failed,
                    'return'  => $return,
                ];

                $checkout = Basket::add_checkout([
                    'user_id' => $udata["id"],
                    'type'    => "bill",
                    'items'   => Utility::jencode($new_items),
                    'data'    => Utility::jencode($options),
                    'cdate'   => DateManager::Now(),
                    'mdfdate' => DateManager::Now(),
                ]);
                Session::set("inv_last_ct_id", $checkout, true);
            } else
                $checkout = $inv_last_ct_id;

            $checkout = Basket::get_checkout($checkout);

            if (!$checkout) return false;
            $checkout_data = $checkout["data"];
            $module->set_checkout($checkout);

            $token = Crypt::encode(Utility::jencode([
                'id'      => $invoice["id"],
                'user_id' => $invoice["user_id"],
            ]), Config::get("crypt/user"));

            $links = [
                'download'        => $this->CRLink("download-id", ["invoice-file", $invoice["id"]]),
                'controller'      => $this->CRLink("ac-ps-detail-invoice", [$invoice["id"]]),
                'invoices'        => $this->CRLink("ac-ps-invoices"),
                'share'           => $this->CRLink("share-invoice") . "?token=" . $token,
                'successful-page' => $success,
                'failed-page'     => $failed,
            ];

            if ($sharing)
                $links['controller'] = $this->CRLink("share-invoice") . "?token=" . $token;


            if (method_exists($module, "get_auth_token"))
                $links["callback"] = $this->CRLink("payment", [$checkout_data["pmethod"], $module->get_auth_token(), "callback"]);

            $this->addData("links", $links);
            $this->addData("module", $module);
            $this->addData("checkout", $checkout);
            $this->addData("_LANG", $module->lang);
            $this->addData("selected_pmethod", $selected_pmethod);
            $this->addData("selected_sendbta", $s_sendbta);

            $result = [
                'status'  => "successful",
                'content' => $this->view->chose(false, true)->render($module->payform, $this->data, true),
            ];

            return $result;
        }

        private function payment_screen_bulk_payment($udata = [], $invoices = [])
        {
            if (DEMO_MODE) return false;

            Helper::Load("Orders");

            $s_invoices = Filter::init("REQUEST/invoices");
            $s_invoices_x = Filter::html_clear($s_invoices);
            if (!is_array($s_invoices)) $s_invoices = $s_invoices_x ? explode(",", $s_invoices_x) : [];

            $pmethods = Config::get("modules/payment-methods");
            $taxation = Config::get("options/taxation");

            $infos = User::getInfo($udata["id"], "dealership,taxation,identity,gsm,gsm_cc");
            $datas = User::getData($udata["id"], "id,name,surname,full_name,email,phone,ip,balance,balance_currency,currency", "array");

            $udata = is_array($udata) ? array_merge($udata, $infos, $datas) : [];

            if ($invoices) {
                foreach ($invoices as $invoice) {
                    if ($s_invoices && !in_array($invoice['id'], $s_invoices)) continue;
                    $items = Invoices::get_items($invoice['id']);
                    if ($items) {
                        foreach ($items as $item) {
                            if (isset($item["options"]["event"]) && $item["options"]["event"] == "addCredit") {
                                if (in_array("Balance", $pmethods)) {
                                    $findmethod = array_search("Balance", $pmethods);
                                    unset($pmethods[$findmethod]);
                                }
                            }
                        }
                    }
                }
            }


            $s_pmethod = Filter::init("REQUEST/pmethod", "route");


            $tax_rate = Config::get("options/tax-rate");
            $currency = Money::getUCID();
            $subtotal = 0;
            $tax = 0;
            $total = 0;

            $success = $this->CRLink("ac-ps-invoices") . "?from=bulk_payment&status=success";
            $failed = $this->CRLink("ac-ps-invoices") . "?from=bulk_payment&status=fail";
            $return = $this->CRLink("ac-ps-invoices") . "?from=bulk_payment";


            if ($invoices) {
                foreach ($invoices as $invoice) {
                    if ($s_invoices && !in_array($invoice['id'], $s_invoices)) continue;

                    $invoice['pmethod'] = $s_pmethod;
                    $items = Invoices::get_items($invoice['id']);
                    $calculate = Invoices::calculate_invoice($invoice, $items, [
                        'included_d_subtotal' => true,
                    ]);

                    $invoice['subtotal'] = $calculate['subtotal'];
                    $invoice['tax'] = $calculate['tax'];
                    $invoice['total'] = $calculate['total'];

                    $subtotal += Money::exChange($invoice["subtotal"], $invoice["currency"], $currency);
                    $tax += Money::exChange($invoice["tax"], $invoice["currency"], $currency);
                    $total += Money::exChange($invoice["total"], $invoice["currency"], $currency);


                }
            }

            $pcommission = 0;

            if (!$pmethods)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "No payment methods",
                ]));

            if (!$s_pmethod || Validation::isEmpty($s_pmethod))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_invoices/error1"),
                ]));

            if (!in_array($s_pmethod, $pmethods))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Not found payment method!",
                ]));


            Modules::Load("Payment", $s_pmethod);
            if (!class_exists($s_pmethod))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Payment method class not found",
                ]));

            $module = new $s_pmethod();

            if ($s_pmethod == "Balance") {
                $balance = Money::exChange($udata["balance"], $udata["balance_currency"], $currency);
                $balance = round($balance, 2);
            }


            if (method_exists($module, "commission_fee_calculator") && $module->commission)
                $camount = $module->commission_fee_calculator($total);
            else
                $camount = 0;

            $selected_pmethod = $s_pmethod;
            $force_convert_to = $module->config["settings"]["force_convert_to"] ?? 0;
            if ($camount) {
                $pcommission_rate = $module->get_commission_rate();
                $pcommission = $camount;
                $subtotal += $camount;
                $total += $camount;
            }


            if ($selected_pmethod == "Balance") {
                if (!isset($balance) || round($balance, 2) < round($total, 2)) {
                    Utility::redirect($failed);
                    return false;
                }
            }

            $subscribable = [];
            $local_invoice = [];
            $invoice_ids = [];
            $items = [];
            foreach ($invoices as $invoice) {
                if ($s_invoices && !in_array($invoice['id'], $s_invoices)) continue;
                $invoice["user_data"] = Utility::jdecode($invoice["user_data"], true);
                if (!$local_invoice) $local_invoice = $invoice;
                $invoice_ids[] = $invoice["id"];
                $items[] = [
                    'options'      => [],
                    'name'         => __("website/account_invoices/invoice-num") . " #" . $invoice["id"],
                    'quantity'     => 1,
                    'amount'       => Money::exChange($invoice["subtotal"], $invoice["currency"], $currency),
                    'total_amount' => Money::exChange($invoice["total"], $invoice["currency"], $currency),
                ];
                $invoice_items = Invoices::get_items($invoice["id"]);
                if ($invoice_items) {
                    foreach ($invoice_items as $item) {
                        ## SUBSCRIPTION START
                        if (isset($item["user_pid"]) && $item["user_pid"] > 0) {
                            $product_price = 0;
                            $product_cid = 0;


                            if (isset($item["options"]["event"]) && $item["options"]["event"] == "ExtendAddonPeriod") {
                                $order = Orders::get_addon($item["options"]["event_data"]["addon_id"], 'addon_name AS name,id,addon_id,option_id,period,period_time,subscription_id');
                                $product = Products::addon($order["addon_id"], $invoice["user_data"]["lang"]);
                                if ($product) {
                                    foreach ($product["options"] as $k => $v) {
                                        if ($v["id"] == $order["option_id"]) {
                                            $product_price = $v["amount"];
                                            $product_cid = $v["cid"];
                                        }
                                    }
                                }
                            } else {
                                $order = Orders::get($item["user_pid"], 'id,type,product_id,name,period,period_time,subscription_id');
                                if ($order) {
                                    $product = Products::get($order["type"], $order["product_id"]);
                                    if ($product) {
                                        foreach ($product["price"] as $p) {
                                            if ($p["period"] == $order["period"] && $p["time"] == $order["period_time"]) {
                                                $product_price = $p["amount"];
                                                $product_cid = $p["cid"];
                                            }
                                        }
                                    }
                                }
                            }

                            if ($order && $product_price > 0.00 && ($order["period"] == "day" || $order["period"] == "week" || $order["period"] == "month" || $order["period"] == "year")) {
                                $sub_id = $order["subscription_id"];
                                if ($sub_id) {
                                    $get_sub = Orders::get_subscription($sub_id);
                                    if ($get_sub && $get_sub["status"] == "active") continue;
                                }

                                if ($invoice["taxation_type"] == "inclusive" && $tax_rate > 0.00)
                                    $product_price -= Money::get_inclusive_tax_amount($product_price, $tax_rate);


                                $subscribable_item = [
                                    'identifier'   => md5((isset($order["addon_id"]) ? "addon" : $order["type"]) . "|" . ($order["addon_id"] ?? $order["product_id"]) . "|" . $order["period"] . "|" . $order["period_time"]),
                                    'product_type' => isset($order["addon_id"]) ? "addon" : $order["type"],
                                    'product_id'   => $order["addon_id"] ?? $order["product_id"],
                                    'period'       => $order["period"],
                                    'period_time'  => $order["period_time"],
                                    'name'         => (isset($order["addon_id"]) ? "+ " : '') . $order["name"],
                                    'amount'       => $product_price,
                                    'tax_included' => $product_price,
                                    'currency'     => $product_cid,
                                    'tax_rate'     => $tax_rate,
                                    'tax_exempt'   => $item["taxexempt"],
                                ];
                                if (isset($order["addon_id"])) $subscribable_item["option_id"] = $order["option_id"];


                                if (isset($pcommission_rate) && $pcommission_rate > 0.00) {
                                    $subscribable_item["commission_rate"] = $pcommission_rate;
                                    $subscribable_item["amount"] += Money::get_exclusive_tax_amount($product_price, $pcommission_rate);
                                    $subscribable_item["tax_included"] = $subscribable_item["amount"];
                                }


                                if ($invoice["tax"] > 0.00 && (!isset($item["taxexempt"]) || !$item["taxexempt"])) {
                                    $subscribable_item["tax_rate"] = $tax_rate;
                                    $subscribable_item["tax_included"] = $subscribable_item["amount"] + Money::get_exclusive_tax_amount($subscribable_item["amount"], $tax_rate);
                                }
                                $subscribable_item["tax_exempt"] = (isset($item["taxexempt"]) && $item["taxexempt"]) ? 1 : 0;
                                $subscribable[] = $subscribable_item;
                            }
                        }
                        ## SUBSCRIPTION END
                    }
                }
            }

            if ($pcommission) {
                $items[] = [
                    'options'      => [],
                    'name'         => __("website/account_invoices/pmethod_commission", ['{method}' => $selected_pmethod]) . " (%" . $pcommission_rate . ")",
                    'quantity'     => 1,
                    'amount'       => $pcommission,
                    'total_amount' => $pcommission,
                ];
            }

            $user_data = $local_invoice ? $local_invoice["user_data"] : $invoice["user_data"];

            if (isset($user_data["kind"]) && $user_data["kind"] == "individual")
                if (Validation::isEmpty($user_data["identity"] ?? '') && !Validation::isEmpty($udata["identity"]))
                    $user_data["identity"] = $udata["identity"];

            if (Validation::isEmpty($user_data["phone"] ?? '') && !Validation::isEmpty($udata["phone"])) {
                $user_data["phone"] = $udata["phone"];
                $user_data["gsm_cc"] = $udata["gsm_cc"];
                $user_data["gsm"] = $udata["gsm"];
            }


            $options = [
                'type'                    => "invoice-bulk-payment",
                'user_data'               => $user_data,
                'user_id'                 => $udata["id"],
                'currency'                => $currency,
                'subtotal'                => $subtotal,
                'tax'                     => $tax,
                'total'                   => $total,
                'pmethod'                 => $selected_pmethod,
                'pmethod_commission'      => isset($pcommission) ? $pcommission : 0,
                'pmethod_commission_rate' => isset($pcommission_rate) ? $pcommission_rate : 0,
                'invoices'                => $invoice_ids,
                'subscribable'            => $subscribable,
            ];


            Session::set("last_paid_page", Utility::jencode([
                'success' => $success,
                'failed'  => $failed,
                'return'  => $return,
            ]), true);


            Helper::Load(["Orders", "Invoices", "Products", "Money", "User", "Basket"]);

            $inv_last_ct_id = Session::get("bulk_inv_last_ct_id", true);

            if (!$inv_last_ct_id) {

                if (Config::get("options/blacklist/status")) {
                    if (Config::get("options/blacklist/order-blocking")) {
                        if (User::checkBlackList($udata)) {
                            Utility::redirect($failed, 3);
                            echo __("website/basket/error18", ['{err_msg}' => ' Detected by WFraud']);
                            exit();
                        }
                    }

                    if (Config::get("options/blacklist/ip-country-mismatch")) {
                        $ipInfo = UserManager::ip_info();
                        $info_country = strtoupper($ipInfo["countryCode"] ?? 'US');
                        $address_country = $options["user_data"]["address"]["country_code"] ?? 'US';
                        if ($info_country != $address_country) {
                            Utility::redirect($failed, 3);
                            echo __("website/basket/error18", ['{err_msg}' => ' ' . Bootstrap::$lang->get_cm("website/basket/error19")]);
                            exit();
                        }
                    }
                }

                $fraud_modules = Modules::Load('Fraud', 'All', false, true);
                if ($fraud_modules) {
                    $fraud_params = $options;
                    $fraud_params['items'] = isset($items) ? $items : [];
                    foreach ($fraud_modules as $k => $v) {
                        $k_m = "Fraud_" . $k;
                        if (class_exists($k_m)) {
                            $m_init = new $k_m();
                            if (method_exists($m_init, 'check')) {
                                $check = $m_init->check($fraud_params);
                                $err_msg = $m_init->error;
                                if (!$check) {
                                    Utility::redirect($failed, 3);
                                    echo 'Fraud Error: ' . ($err_msg ? $err_msg : __("website/basket/error18", ['{err_msg}' => ' Detected by ' . $k]));
                                    exit();
                                }
                            }
                        }
                    }
                }


                $options['redirect'] = [
                    'success' => $success,
                    'failed'  => $failed,
                    'return'  => $return,
                ];

                $checkout = Basket::add_checkout([
                    'user_id' => $udata["id"],
                    'type'    => "invoice-bulk-payment",
                    'items'   => Utility::jencode($items),
                    'data'    => Utility::jencode($options),
                    'cdate'   => DateManager::Now(),
                    'mdfdate' => DateManager::Now(),
                ]);
                Session::set("bulk_inv_last_ct_id", $checkout, true);
            } else
                $checkout = $inv_last_ct_id;

            $checkout = Basket::get_checkout($checkout);

            if (!$checkout) return false;
            $checkout_data = $checkout["data"];
            $module->set_checkout($checkout);

            $links = [
                'controller'      => $this->CRLink("ac-ps-invoices-p", ["bulk-payment"]),
                'successful-page' => $success,
                'failed-page'     => $failed,
            ];

            if (method_exists($module, "get_auth_token"))
                $links["callback"] = $this->CRLink("payment", [$checkout_data["pmethod"], $module->get_auth_token(), "callback"]);

            $this->addData("selected_pmethod", $selected_pmethod);
            $this->addData("links", $links);
            $this->addData("module", $module);
            $this->addData("checkout", $checkout);
            $this->addData("_LANG", $module->lang);

            $result = [
                'status'  => "successful",
                'content' => $this->view->chose(false, true)->render($module->payform, $this->data, true),
            ];

            return $result;
        }

        private function detail_main()
        {
            $id = (int)(isset($this->params[1]) && $this->params[1] != '' ? $this->params[1] : false);
            if (!$id) {
                Utility::redirect($this->CRLink("ac-ps-invoices"));
                die();
            }


            Helper::Load(["User", "Products", "Money", "Orders", "Invoices", "Basket"]);
            $udata = UserManager::LoginData("member");


            if ($udata) {
                $udata = array_merge($udata, User::getData($udata["id"], "lang,balance_currency,balance_min,balance,full_name,company_name", "array"));
                $udata = array_merge($udata, User::getInfo($udata["id"], ["dealership"]));
            }

            $change_address = (int)Filter::init("GET/change_address", "numbers");

            if ($change_address) {
                $check_address = AddressManager::CheckAddress($change_address, $udata["id"]);
                if ($check_address)
                    User::overwrite_new_address_on_invoices($udata["id"], $change_address, $id);
            }


            $invoice = Invoices::get($id, ['user_id' => $udata["id"]]);
            if (!$invoice) {
                Utility::redirect($this->CRLink("ac-ps-invoices"));
                die();
            }

            if (Hook::run("InvoiceViewDetails", $invoice)) $invoice = Invoices::get($id, ['user_id' => $udata["id"]]);


            if (Filter::POST("operation") == "apply_coupon") return $this->submit_apply_coupon($invoice);


            $this->model->db->update("events")
                ->set(['unread' => 1, 'status' => 'approved'])
                ->where("type", "=", "notification", "&&")
                ->where("owner", "=", "invoice", "&&")
                ->where("owner_id", "=", $invoice["id"], "&&")
                ->where("unread", "=", "0")
                ->save();


            if ($invoice["status"] == "unpaid" && Filter::POST("operation") == "selection-result")
                return $this->selection_result($invoice, $udata);


            $this->addData("udata", $udata);

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

            $this->addData("pname", "account_invoices");

            $page_title = __("website/account_invoices/page-title2", ['{num}' => $invoice["number"] ? $invoice["number"] : "#" . $invoice["id"]]);

            $this->addData("page_type", "account");
            $this->addData("meta", ['title' => __("website/account_invoices/detail-meta", ['{num}' => $invoice["number"] ? $invoice["number"] : "#" . $invoice["id"]])]);
            $this->addData("header_title", $page_title);

            $breadcrumb = [
                [
                    'link'  => $this->CRLink("my-account"),
                    'title' => __("website/account/breadcrumb-dashboard"),
                ],
                [
                    'link'  => $this->CRLink("ac-ps-invoices"),
                    'title' => __("website/account_invoices/breadcrumb-invoices"),
                ],
                [
                    'link'  => null,
                    'title' => $page_title,
                ],
            ];
            $this->addData("panel_breadcrumb", $breadcrumb);

            $token = Crypt::encode(Utility::jencode([
                'id'      => $invoice["id"],
                'user_id' => $invoice["user_id"],
            ]), Config::get("crypt/user"));


            $controller_link = $this->CRLink("ac-ps-detail-invoice", [$invoice["id"]]);
            $change_address_link = $controller_link . "?change_address=";


            $this->addData("links", [
                'download'       => $this->CRLink("download-id", ["invoice-file", $invoice["id"]]),
                'controller'     => $controller_link,
                'change_address' => $change_address_link,
                'invoices'       => $this->CRLink("ac-ps-invoices"),
                'share'          => $this->CRLink("share-invoice") . "?token=" . $token,
            ]);

            $pmethod_name = null;

            if ($invoice["status"] != "unpaid") {
                if ($invoice["pmethod"] == "none") {
                    $invoice["pmethod"] = __("website/others/none");
                } else {
                    Modules::Load("Payment", $invoice["pmethod"], true);
                    $mlang = Modules::Lang("Payment", $invoice["pmethod"]);
                    if ($mlang && isset($mlang["invoice-name"]))
                        $pmethod_name = $mlang["invoice-name"];
                }
            }

            $this->addData("pmethod_name", $pmethod_name);

            $items = Invoices::item_listing($invoice);

            if ($invoice["status"] == "unpaid") {
                Helper::Load(["Orders", "Products"]);
                if (Config::get("options/detect-auto-price-on-invoice")) {
                    $checking = Invoices::detect_auto_price_and_save($udata, $invoice, $items);
                    if ($checking) {
                        $invoice = $checking['attr'];
                        $items = $checking['items'];
                    }
                }
            }

            $this->addData("invoice", $invoice);
            $this->addData("items", $items);

            $this->addData("permission_share", Config::get("options/invoice-show-requires-login"));

            if ($invoice["status"] == "unpaid" && Filter::REQUEST("operation") == "payment-screen")
                $this->addData("payment_screen", $this->payment_screen($invoice, $udata));
            elseif (Session::get("inv_last_ct_id", true)) Session::delete("inv_last_ct_id");


            $percentage_l = '';
            $percentage_r = '';

            if (Bootstrap::$lang->clang == "tr")
                $percentage_l = "%";
            else
                $percentage_r = "%";


            $tax_rates = [];
            $total_tax_rates = 0;
            $allRs = Config::get("options/tax-rates-names/" . $invoice["user_data"]["address"]["country_id"]);
            $city_id = $invoice["user_data"]["address"]["city_id"];

            if (isset($allRs[$city_id]) && $allRs[$city_id]) {
                foreach ($allRs[$city_id] as $r) {
                    if (strlen($r['name']) > 1 && $r["value"] > 0.00) {
                        $tax_rates[] = $r["name"] . " " . $percentage_l . $r["value"] . $percentage_r;
                        $total_tax_rates += $r["value"];
                    }
                }
            } elseif (isset($allRs[0]) && $allRs[0]) {
                foreach ($allRs[0] as $r) {
                    if (strlen($r['name']) > 1 && $r["value"] > 0.00) {
                        $tax_rates[] = $r["name"] . " " . $percentage_l . $r["value"] . $percentage_r;
                        $total_tax_rates += $r["value"];
                    }
                }
            }

            $size_tax_rates = sizeof($tax_rates);
            if ($size_tax_rates > 0)
                $tax_rates = '(' . implode(' + ', $tax_rates) . ') ';
            else
                $tax_rates = '';

            $this->addData("tax_rates", $tax_rates);

            $custom_fields = $this->model->get_custom_fields(Bootstrap::$lang->clang);
            $user_custom_fields = [];

            if ($custom_fields) {
                foreach ($custom_fields as $field) {
                    $save_value = false;
                    $save_data = User::getInfo($udata["id"], ['field_' . $field["id"]]);
                    if (isset($save_data["field_" . $field["id"]]) && Utility::strlen($save_data["field_" . $field["id"]]) > 0)
                        $save_value = $save_data["field_" . $field["id"]];
                    if ($save_value)
                        $user_custom_fields[$field["id"]] = [
                            'name'  => $field["name"],
                            'value' => $save_value,
                        ];
                }
            }
            $this->addData("custom_fields", $user_custom_fields);

            if (!($invoice["pmethod"] == "BankTransfer" || $invoice["pmethod"] == "Balance")) {
                $case = "CASE WHEN status='paid' THEN 0 ELSE 1 END AS rank";
                $transID = Models::$init->db->select("id,data,type," . $case)->from("checkouts");
                $transID->where("JSON_CONTAINS(data, '" . ('"' . $id . '"') . "','$.invoices')", "", "", "||");
                $transID->where("JSON_CONTAINS(data, '" . $id . "','$.invoices')", "", "", "||");
                $transID->where("JSON_UNQUOTE(JSON_EXTRACT(data,'$.invoice_id'))", "=", $id);
                $transID->order_by("rank ASC,cdate DESC");
                $transID->limit(1);
                $transID = $transID->build() ? $transID->getObject() : false;
                $transData = false;
                $payment_bulk = false;
                if ($transID) {
                    $transData = Utility::jdecode($transID->data, true);
                    $payment_bulk = $transID->type == "invoice-bulk-payment";
                    $transID = $transID->id;
                    if (isset($transData["pmethod_stored_card"]) && $transData["pmethod_stored_card"]) {
                        $stored_card = $this->model->db->select("ln4")->from("users_stored_cards")->where("id", "=", $transData["pmethod_stored_card"]);
                        $stored_card_ln4 = $stored_card->build() ? $stored_card->getObject()->ln4 : 0;
                        if ($stored_card_ln4) $this->addData("stored_card_ln4", $stored_card_ln4);
                        $this->addData("is_auto_pay", isset($transData["pmethod_by_auto_pay"]) && $transData["pmethod_by_auto_pay"] ? 2 : 1);
                    }
                }
                $this->addData("payment_transaction_id", $transID);
                $this->addData("payment_transaction", $transData);
                $this->addData("payment_bulk", $payment_bulk);
            }


            if (Filter::GET("print") == "pdf") {
                $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'en', true, 'UTF-8', ['mL', 'mT', 'mR', 'mB']);

                Hook::run("SetPdfFont", $html2pdf, $invoice);

                try {
                    $output = $this->view->chose("website")->render("ac-detail-invoice-pdf", $this->data, true);


                    $currencies = Money::getCurrencies();
                    foreach ($currencies as $row) {
                        if (($row["prefix"] && substr($row["prefix"], -1, 1) == ' ') || ($row["suffix"] && substr($row["suffix"], 0, 1) == ' '))
                            $code = $row["code"];
                        else
                            $code = $row["prefix"] ? $row["code"] . ' ' : ' ' . $row["code"];

                        $convert_if = !in_array($row["code"], ['USD', 'EUR', 'GBP']);

                        if (stristr($row["prefix"], '$')) $convert_if = false;
                        if (stristr($row["suffix"], '$')) $convert_if = false;


                        $row["prefix"] = Utility::text_replace($row["prefix"], [' ' => '']);
                        $row["suffix"] = Utility::text_replace($row["suffix"], [' ' => '']);
                        if (!Validation::isEmpty($row["prefix"]) && $row["prefix"] && $convert_if && !preg_match('/[A-Za-z]/', $row["prefix"]))
                            $output = Utility::text_replace($output, [$row["prefix"] => $code]);
                        elseif (!Validation::isEmpty($row["suffix"]) && $row["suffix"] && $convert_if && !preg_match('/[A-Za-z]/', $row["suffix"]))
                            $output = Utility::text_replace($output, [$row["suffix"] => $row["code"]]);
                    }

                    $html2pdf->writeHTML($output);
                    $html2pdf->output(Bootstrap::$lang->get("needs/invoice") . " " . $invoice["number"] . ".pdf");
                } catch (Exception $e) {
                    echo $e->getMessage();
                }
                return true;
            }

            if ($invoice["status"] == "unpaid") $this->addData('pmethod_name', ___("needs/unknown"));

            $this->addData("acAddresses", AddressManager::getAddressesList($udata["id"], ($udata["company_name"] ? $udata["company_name"] : $udata["full_name"])));


            $this->view->chose("website")->render("ac-detail-invoice", $this->data);
        }

        private function share_main()
        {
            $token = Filter::GET("token");
            if (!$token || !$token = Crypt::decode($token, Config::get("crypt/user"))) {
                return $this->main_404();
            }

            $token_data = Utility::jdecode($token, true);
            if (!$token_data) return $this->main_404();

            $uid = $token_data["user_id"];
            $id = (int)$token_data["id"];

            Helper::Load(["User", "Products", "Money", "Orders", "Invoices", "Basket"]);
            $udata = ['id' => $uid];

            if ($udata) {
                $udata = array_merge($udata, User::getData($udata["id"], "id,lang,balance_currency,balance_min,balance,full_name,company_name", "array"));
                $udata = array_merge($udata, User::getInfo($udata["id"], ["dealership"]));
            }

            $change_address = (int)Filter::init("GET/change_address", "numbers");

            if ($change_address) {
                $check_address = AddressManager::CheckAddress($change_address, $udata["id"]);
                if ($check_address)
                    User::overwrite_new_address_on_invoices($udata["id"], $change_address, $id);
            }


            $invoice = Invoices::get($id);
            if (!$invoice) return $this->main_404();

            if (Hook::run("InvoiceViewDetails", $invoice)) $invoice = Invoices::get($id);

            if (Filter::POST("operation") == "apply_coupon") return $this->submit_apply_coupon($invoice);

            $payer_udata = UserManager::LoginData("member");

            $token = Crypt::encode(Utility::jencode([
                'id'      => $invoice["id"],
                'user_id' => $invoice["user_id"],
            ]), Config::get("crypt/user"));

            if ($invoice["status"] == "unpaid" && Filter::POST("operation") == "selection-result")
                return $this->selection_result($invoice, $udata, [
                    'payer_udata' => $payer_udata,
                    'token'       => $token,
                ]);


            if ($payer_udata) $this->addData("udata", $payer_udata);

            $this->addData("sharing", true);

            if (UserManager::LoginCheck("admin")) $this->addData("admin", true);

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

            $this->addData("pname", "account_invoices");

            $page_title = __("website/account_invoices/page-title2", ['{num}' => $invoice["number"] ? $invoice["number"] : "#" . $invoice["id"]]);

            $this->addData("page_type", "account");
            $this->addData("meta", ['title' => __("website/account_invoices/detail-meta", ['{num}' => $invoice["number"] ? $invoice["number"] : "#" . $invoice["id"]])]);
            $this->addData("header_title", $page_title);

            $breadcrumb = [
                [
                    'link'  => $this->CRLink("my-account"),
                    'title' => __("website/account/breadcrumb-dashboard"),
                ],
                [
                    'link'  => $this->CRLink("ac-ps-invoices"),
                    'title' => __("website/account_invoices/breadcrumb-invoices"),
                ],
                [
                    'link'  => null,
                    'title' => $page_title,
                ],
            ];
            $this->addData("panel_breadcrumb", $breadcrumb);

            $controller_link = $this->CRLink("share-invoice") . "?token=" . $token;
            $change_address_link = $controller_link . "&change_address=";

            $this->addData("links", [
                'share'          => $this->CRLink("share-invoice") . "?token=" . $token,
                'invoices'       => $this->CRLink("ac-ps-invoices"),
                'download'       => $this->CRLink("download-id", ["invoice-file", $invoice["id"]]),
                'controller'     => $controller_link,
                'change_address' => $change_address_link,
            ]);

            $pmethod_name = null;

            if ($invoice["status"] != "unpaid") {
                if ($invoice["pmethod"] == "none") {
                    $invoice["pmethod"] = __("website/others/none");
                } else {
                    Modules::Load("Payment", $invoice["pmethod"], true);
                    $mlang = Modules::Lang("Payment", $invoice["pmethod"]);
                    if ($mlang && isset($mlang["invoice-name"]))
                        $pmethod_name = $mlang["invoice-name"];
                }
            }

            $this->addData("pmethod_name", $pmethod_name);

            $items = Invoices::item_listing($invoice);

            if ($invoice["status"] == "unpaid") {
                Helper::Load(["Orders", "Products"]);
                if (Config::get("options/detect-auto-price-on-invoice")) {
                    $checking = Invoices::detect_auto_price_and_save($udata, $invoice, $items);
                    if ($checking) {
                        $invoice = $checking['attr'];
                        $items = $checking['items'];
                    }
                }
            }


            $this->addData("invoice", $invoice);

            $this->addData("items", $items);

            $this->addData("permission_share", Config::get("options/invoice-show-requires-login"));

            if ($invoice["status"] == "unpaid" && Filter::REQUEST("operation") == "payment-screen")
                $this->addData("payment_screen", $this->payment_screen($invoice, $udata, [
                    'payer_udata' => $payer_udata,
                    'token'       => $token,
                ]));
            elseif (Session::get("inv_last_ct_id", true)) Session::delete("inv_last_ct_id");


            $percentage_l = '';
            $percentage_r = '';

            if (Bootstrap::$lang->clang == "tr")
                $percentage_l = "%";
            else
                $percentage_r = "%";

            $tax_rates = [];
            $total_tax_rates = 0;
            $allRs = Config::get("options/tax-rates-names/" . $invoice["user_data"]["address"]["country_id"]);
            $city_id = $invoice["user_data"]["address"]["city_id"];

            if (isset($allRs[$city_id]) && $allRs[$city_id]) {
                foreach ($allRs[$city_id] as $r) {
                    if (strlen($r['name']) > 1 && $r["value"] > 0.00) {
                        $tax_rates[] = $r["name"] . " " . $percentage_l . $r["value"] . $percentage_r;
                        $total_tax_rates += $r["value"];
                    }
                }
            } elseif (isset($allRs[0]) && $allRs[0]) {
                foreach ($allRs[0] as $r) {
                    if (strlen($r['name']) > 1 && $r["value"] > 0.00) {
                        $tax_rates[] = $r["name"] . " " . $percentage_l . $r["value"] . $percentage_r;
                        $total_tax_rates += $r["value"];
                    }
                }
            }


            $size_tax_rates = sizeof($tax_rates);
            if ($size_tax_rates > 0)
                $tax_rates = '(' . implode(' + ', $tax_rates) . ') ';
            else
                $tax_rates = '';

            $this->addData("tax_rates", $tax_rates);

            $custom_fields = $this->model->get_custom_fields(Bootstrap::$lang->clang);
            $user_custom_fields = [];

            if ($custom_fields) {
                foreach ($custom_fields as $field) {
                    $save_value = false;
                    $save_data = User::getInfo($udata["id"], ['field_' . $field["id"]]);
                    if (isset($save_data["field_" . $field["id"]]) && Utility::strlen($save_data["field_" . $field["id"]]) > 0)
                        $save_value = $save_data["field_" . $field["id"]];
                    if ($save_value)
                        $user_custom_fields[$field["id"]] = [
                            'name'  => $field["name"],
                            'value' => $save_value,
                        ];
                }
            }
            $this->addData("custom_fields", $user_custom_fields);

            if (!($invoice["pmethod"] == "BankTransfer" || $invoice["pmethod"] == "Balance")) {
                $case = "CASE WHEN status='paid' THEN 0 ELSE 1 END AS rank";
                $transID = Models::$init->db->select("id,data,type," . $case)->from("checkouts");
                $transID->where("JSON_CONTAINS(data, '" . ('"' . $id . '"') . "','$.invoices')", "", "", "||");
                $transID->where("JSON_CONTAINS(data, '" . $id . "','$.invoices')", "", "", "||");
                $transID->where("JSON_UNQUOTE(JSON_EXTRACT(data,'$.invoice_id'))", "=", $id);
                $transID->order_by("rank ASC,cdate DESC");
                $transID->limit(1);
                $transID = $transID->build() ? $transID->getObject() : false;
                $transData = false;
                $payment_bulk = false;
                if ($transID) {
                    $transData = Utility::jdecode($transID->data, true);
                    $payment_bulk = $transID->type == "invoice-bulk-payment";
                    $transID = $transID->id;
                    if (isset($transData["pmethod_stored_card"]) && $transData["pmethod_stored_card"]) {
                        $stored_card = $this->model->db->select("ln4")->from("users_stored_cards")->where("id", "=", $transData["pmethod_stored_card"]);
                        $stored_card_ln4 = $stored_card->build() ? $stored_card->getObject()->ln4 : 0;
                        if ($stored_card_ln4) $this->addData("stored_card_ln4", $stored_card_ln4);
                        $this->addData("is_auto_pay", isset($transData["pmethod_by_auto_pay"]) && $transData["pmethod_by_auto_pay"] ? 2 : 1);
                    }
                }
                $this->addData("payment_transaction_id", $transID);
                $this->addData("payment_transaction", $transData);
                $this->addData("payment_bulk", $payment_bulk);
            }

            if (Filter::GET("print") == "pdf") {
                $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', "en", true, 'UTF-8', ['mL', 'mT', 'mR', 'mB']);

                Hook::run("SetPdfFont", $html2pdf, $invoice);

                try {
                    $output = $this->view->chose("website")->render("ac-detail-invoice-pdf", $this->data, true);

                    $currencies = Money::getCurrencies();
                    foreach ($currencies as $row) {
                        if (($row["prefix"] && substr($row["prefix"], -1, 1) == ' ') || ($row["suffix"] && substr($row["suffix"], 0, 1) == ' '))
                            $code = $row["code"];
                        else
                            $code = $row["prefix"] ? $row["code"] . ' ' : ' ' . $row["code"];

                        $convert_if = !in_array($row["code"], ['USD', 'EUR', 'GBP']);

                        if (stristr($row["prefix"], '$')) $convert_if = false;
                        if (stristr($row["suffix"], '$')) $convert_if = false;


                        $row["prefix"] = Utility::text_replace($row["prefix"], [' ' => '']);
                        $row["suffix"] = Utility::text_replace($row["suffix"], [' ' => '']);
                        if (!Validation::isEmpty($row["prefix"]) && $row["prefix"] && $convert_if && !preg_match('/[A-Za-z]/', $row["prefix"]))
                            $output = Utility::text_replace($output, [$row["prefix"] => $code]);
                        elseif (!Validation::isEmpty($row["suffix"]) && $row["suffix"] && $convert_if && !preg_match('/[A-Za-z]/', $row["suffix"]))
                            $output = Utility::text_replace($output, [$row["suffix"] => $row["code"]]);
                    }

                    $html2pdf->writeHTML($output);
                    $html2pdf->output(Bootstrap::$lang->get("needs/invoice") . " " . $invoice["number"] . ".pdf");
                } catch (Exception $e) {
                    echo $e->getMessage();
                }
                return true;
            }

            $this->addData("acAddresses", AddressManager::getAddressesList($udata["id"], ($udata["company_name"] ? $udata["company_name"] : $udata["full_name"])));

            $this->view->chose("website")->render("ac-detail-invoice", $this->data);
        }

        private function list_main()
        {
            $this->addData("pname", "account_invoices");
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
            $this->addData("meta", __("website/account_invoices/list-meta"));
            $this->addData("header_title", __("website/account_invoices/page-title"));

            $breadcrumb = [
                [
                    'link'  => $this->CRLink("my-account"),
                    'title' => __("website/account/breadcrumb-dashboard"),
                ],
                [
                    'link'  => null,
                    'title' => __("website/account_invoices/breadcrumb-invoices"),
                ],
            ];
            $this->addData("panel_breadcrumb", $breadcrumb);
            $this->addData("links", [
                'ajax' => $this->CRLink("ac-ps-invoices") . "/ajax",
            ]);

            $udata = UserManager::LoginData("member");

            $address = AddressManager::getAddress(0, $udata["id"]);
            $udata = array_merge($udata, User::getData($udata["id"], "name,surname,full_name,company_name,email", "array"));

            $udata["address"] = $address;

            $visibility_balance = false;

            $balanceModule = Modules::Load("Payment", "Balance", true);
            if ($balanceModule) $visibility_balance = $balanceModule["config"]["settings"]["status"];

            $this->addData("visibility_balance", $visibility_balance);
            $this->addData("udata", $udata);


            $filteredList = $this->get_invoices($udata["id"], [], [], 0, 9999);
            $filterTotal = $this->model->get_total_invoices($udata["id"]);
            $listTotal = $this->model->get_total_invoices($udata["id"]);

            if ($listTotal) {
                Helper::Load("Money");
                $situations = $this->view->chose("website")->render("common-needs", false, true, true);
                $situations = $situations["invoice"];

                if ($filteredList) {
                    $this->addData("situations", $situations);
                    $this->addData("list", $filteredList);
                    $this->addData("list_ajax", $this->view->chose("website")->render("ajax-invoices", $this->data, false, true));
                }
            }


            $this->view->chose("website")->render("ac-invoices", $this->data);
        }

        private function bulk_payment_main()
        {
            $this->addData("pname", "account_invoices");
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
            $this->addData("meta", __("website/account_invoices/bulk-payment-meta"));
            $this->addData("header_title", __("website/account_invoices/page-bulk-payment"));

            $breadcrumb = [
                [
                    'link'  => $this->CRLink("my-account"),
                    'title' => __("website/account/breadcrumb-dashboard"),
                ],
                [
                    'link'  => $this->CRLink("ac-ps-invoices"),
                    'title' => __("website/account_invoices/breadcrumb-invoices"),
                ],
                [
                    'link'  => null,
                    'title' => __("website/account_invoices/breadcrumb-bulk-payment"),
                ],
            ];
            $this->addData("panel_breadcrumb", $breadcrumb);
            $this->addData("links", [
                'controller' => $this->CRLink("ac-ps-invoices-p", ["bulk-payment"]),
            ]);

            $udata = UserManager::LoginData("member");

            $address = AddressManager::getAddress(0, $udata["id"]);
            $udata = array_merge($udata, User::getData($udata["id"], "name,surname,full_name,company_name,email", "array"));

            $udata["address"] = $address;

            $visibility_balance = false;

            $balanceModule = Modules::Load("Payment", "Balance", true);
            if ($balanceModule) $visibility_balance = $balanceModule["config"]["settings"]["status"];

            $this->addData("visibility_balance", $visibility_balance);
            $this->addData("udata", $udata);

            $s_invoices = Filter::init("POST/invoices");
            $s_invoices_x = Filter::html_clear($s_invoices);
            if (!is_array($s_invoices)) $s_invoices = $s_invoices_x ? explode(",", $s_invoices_x) : [];

            $invoices = $this->model->get_unpaid_invoices($udata["id"]);
            if ($invoices) foreach ($invoices as $k => $v) $invoices[$k] = Invoices::get($v["id"]);

            if (sizeof($invoices) == 0)
                return Utility::redirect($this->CRLink("ac-ps-invoices"));
            elseif (sizeof($invoices) == 1)
                return Utility::redirect($this->CRLink("ac-ps-detail-invoice", [$invoices[0]["id"]]));

            if (Filter::POST("operation") == "selection-result")
                return $this->selection_result_bulk_payment($udata, $invoices);
            elseif (Filter::REQUEST("operation") == "payment-screen")
                $this->addData("payment_screen", $this->payment_screen_bulk_payment($udata, $invoices));
            elseif (Session::get("bulk_inv_last_ct_id", true)) Session::delete("bulk_inv_last_ct_id");

            if ($invoices)
                foreach ($invoices as $k => $invoice)
                    $invoices[$k]['selected'] = !$s_invoices || ($s_invoices && in_array($invoice['id'], $s_invoices));

            $this->addData("unpaid_invoices", $invoices);

            $this->view->chose("website")->render("ac-invoices-bulk-payment", $this->data);
        }

        private function submit_apply_coupon($invoice = [])
        {
            $this->takeDatas("language");

            Helper::Load(["Coupon", "Products", "Orders", "Money"]);
            $code = Filter::init("POST/code", "hclear");

            try {

                $coupon = $code ? Coupon::get($code) : false;
                if (!$coupon) throw new Exception(__("website/basket/error3"));
                if (!Coupon::validate($coupon, $invoice["user_id"])) throw new Exception(Coupon::get_message());
                if (!($coupon["used_in_invoices"] ?? false)) throw new Exception(__("website/account_invoices/error5"));


                $d_rates = [];
                $o_quantity = 0;

                $d_status = Config::get("options/dealership/status");
                $udata = $invoice["user_data"];

                if ($udata) {
                    $infos = User::getInfo($invoice["user_id"], "dealership");
                    $udata = array_merge($udata, $infos);
                    $dealership = !isset($udata["dealership"]) || $udata["dealership"] == null ? [] : Utility::jdecode($udata["dealership"], true);
                    if ($dealership && $dealership["status"] == "active") {
                        $d_rates = (array)Config::get("options/dealership/rates");
                        if (is_array(current($dealership["discounts"])))
                            $d_rates = array_merge($d_rates, $dealership["discounts"]);
                        $o_quantity = sizeof(User::dealership_orders($udata['id'], $d_rates));
                    }
                }

                $coupons = explode(",", $invoice["used_coupons"]);
                $items = Invoices::get_items($invoice["id"]);

                if (in_array($coupon["id"], $coupons)) throw new Exception(__("website/basket/error4"));

                $discounts = $invoice["discounts"];
                $set_invoice = [];
                $used_items = [];


                if ($coupon["pservices"]) {
                    $c_products = Products::find_products_in_coupon($coupon["pservices"]);
                    $used_groups = [];
                    $usable = false;
                    $lang = $udata["lang"];
                    $e_ds = false;


                    if ($coupons) {
                        $_used_items = [];

                        foreach ($coupons as $c) {
                            $c = Coupon::get(null, $c);
                            if ($c && !Coupon::validate($c, $invoice["user_id"] ?? 0)) continue;
                            $c_pds = Products::find_products_in_coupon($c["pservices"]);

                            foreach ($items as $item) {
                                $item_id = $item["id"];
                                $options = isset($item["options"]) ? $item["options"] : [];
                                $order_id = $item["user_pid"];
                                $order = Orders::get($order_id);
                                $product = Products::get($order["type"], $order["product_id"]);
                                $condition = false;
                                $event_name = strtolower($options["event"] ?? '');

                                if (stristr($event_name, 'renewal') || stristr($event_name, 'extend')) {
                                    if ($coupon["recurring"] > 0) {
                                        if ($coupon["recurring_num"] > 0) {
                                            $number_of_uses = Coupon::number_of_uses($coupon, $order_id);
                                            if ($number_of_uses < $coupon["recurring_num"]) $condition = true;
                                        } else
                                            $condition = true;
                                    }
                                } else
                                    $condition = true;

                                if ($product && $condition) {
                                    $p_k = $product["type"];
                                    if ($p_k == "special") $p_k .= "-" . $product["type_id"];
                                    $find_product = isset($c_pds[$p_k][$product["id"]]);

                                    $available_p = true;

                                    if ($c["period_type"]) {
                                        $pd_type = '';
                                        $pd_duration = 0;

                                        if (isset($options["selection"]) && $options["selection"]) {
                                            $pd_type = $options["selection"]["period"];
                                            $pd_duration = $options["selection"]["time"];
                                        } elseif (isset($options["period"]) && $options["period"]) {
                                            $pd_type = $options["period"];
                                            $pd_duration = $options["period_time"];
                                        } elseif ($order) {
                                            $pd_type = $order["period"];
                                            $pd_duration = $order["period_time"];
                                        }

                                        if ($c["period_type"] != $pd_type) $available_p = false;
                                        elseif ($c["period_type"] != "none" && $c["period_duration"] != $pd_duration) $available_p = false;

                                    }

                                    if ($find_product && $available_p && !$c["use_merge"])
                                        $_used_items[] = $item_id;
                                }

                            }


                        }
                    }

                    foreach ($items as $ik => $item) {
                        $item_id = $item["id"];
                        $options = isset($item["options"]) ? $item["options"] : [];
                        $order_id = $item["user_pid"];
                        $order = Orders::get($order_id);
                        $product = Products::get($order["type"], $order["product_id"]);
                        $condition = false;
                        $event_name = strtolower($options["event"] ?? '');

                        if (stristr($event_name, 'renewal') || stristr($event_name, 'extend')) {
                            if ($coupon["recurring"] > 0) {
                                if ($coupon["recurring_num"] > 0) {
                                    $number_of_uses = Coupon::number_of_uses($coupon, $order_id);
                                    if ($number_of_uses < $coupon["recurring_num"]) $condition = true;
                                } else
                                    $condition = true;
                            }
                        } else
                            $condition = true;

                        if ($product && $condition) {
                            $p_k = $product["type"];
                            if ($p_k == "special") $p_k .= "-" . $product["type_id"];

                            $find_product = isset($c_products[$p_k][$product["id"]]);

                            $available_p = true;

                            if ($coupon["period_type"]) {
                                $pd_type = '';
                                $pd_duration = 0;

                                if (isset($options["selection"]) && $options["selection"]) {
                                    $pd_type = $options["selection"]["period"];
                                    $pd_duration = $options["selection"]["time"];
                                } elseif (isset($options["period"]) && $options["period"]) {
                                    $pd_type = $options["period"];
                                    $pd_duration = $options["period_time"];
                                } elseif ($order) {
                                    $pd_type = $order["period"];
                                    $pd_duration = $order["period_time"];
                                }

                                if ($coupon["period_type"] != $pd_type) $available_p = false;
                                elseif ($coupon["period_type"] != "none" && $coupon["period_duration"] != $pd_duration) $available_p = false;
                            }

                            if ($find_product && $available_p) {
                                if (!$coupon["use_merge"] && isset($_used_items[$item_id])) throw new Exception(__("website/basket/error17"));
                                $usable = true;
                                $used_groups[$p_k] = true;
                                $used_items[$ik] = $item_id;
                            }
                            if ($d_rates && Products::find_in_rates($product, $d_rates, $o_quantity)) $e_ds = true;
                        }
                    }

                    if (isset($dealership["status"]) && $d_status && $dealership["status"] == "active" && !$coupon["dealership"] && $e_ds) $usable = false;
                } else
                    $usable = "none";

                if (!$usable && $usable != "none" && $e_ds) throw new Exception(__("website/basket/error5"));
                elseif (!$usable) throw new Exception(__("website/basket/error6"));
                if ($usable === "none") throw new Exception(__("website/basket/error6"));

                $taxation_type = $invoice["taxation_type"];
                $tax_rate = $invoice["taxrate"];
                $ucid = $invoice["currency"];

                if ($taxation_type == "inclusive" && $tax_rate > 0.00 && $coupon["type"] == "amount") {
                    $discount_tax = Money::get_inclusive_tax_amount($coupon["amount"], $tax_rate);
                    if ($discount_tax > 0.00) $coupon["amount"] -= $discount_tax;
                }


                if ($used_items) {
                    $onetime_use_per_order = $coupon["onetime_use_per_order"] ?? false;

                    foreach ($used_items as $uik => $uitem) {
                        if (isset($items[$uik]) && $item = $items[$uik]) {
                            $item_id = $item["id"];
                            $i_amount = $item["amount"];

                            if ($coupon["type"] == "percentage")
                                $item_d_amount = Money::get_discount_amount($i_amount, $coupon["rate"]);
                            else
                                $item_d_amount = Money::exChange($coupon["amount"], $coupon["currency"], $ucid);

                            if ($item_d_amount > $i_amount)
                                $item_d_amount = $i_amount;


                            $discounts["items"]["coupon"][$item_id] = [
                                'id'      => $coupon["id"],
                                'name'    => $coupon["code"],
                                'rate'    => $coupon["rate"],
                                'dvalue'  => $coupon["rate"] == 0 ? Money::formatter_symbol($item_d_amount, $ucid) : "%" . $coupon["rate"],
                                'amount'  => Money::formatter_symbol($item_d_amount, $ucid),
                                'amountd' => $item_d_amount,
                            ];

                            if ($onetime_use_per_order) break;
                        }
                    }
                } else {
                    $sumTotal = 0;

                    if ($coupon["type"] == "percentage")
                        $sumTotal = Money::get_discount_amount($invoice["subtotal"], $coupon["rate"]);
                    elseif ($coupon["type"] == "amount")
                        $sumTotal = Money::exChange($coupon["amount"], $coupon["currency"], $ucid);

                    $count = sizeof($items);

                    foreach ($items as $ik => $v) {
                        $item_id = $item["id"];
                        $item_d_amount = round($sumTotal * $count, 2);

                        $discounts["items"]["coupon"][$item_id] = [
                            'id'      => $coupon["id"],
                            'name'    => $coupon["code"],
                            'rate'    => $coupon["rate"],
                            'dvalue'  => $coupon["rate"] == 0 ? Money::formatter_symbol($item_d_amount, $ucid) : "%" . $coupon["rate"],
                            'amount'  => Money::formatter_symbol($item_d_amount, $ucid),
                            'amountd' => $item_d_amount,
                        ];
                    }
                }

                $coupons[] = $coupon["id"];

                $set_invoice["used_coupons"] = implode(",", $coupons);
                $set_invoice["discounts"] = Utility::jencode($discounts);

                $invoice["used_coupons"] = $set_invoice["used_coupons"];
                $invoice["discounts"] = $discounts;

                if ($coupon["taxfree"]) {
                    $set_invoice["legal"] = 0;
                    $invoice["legal"] = 0;
                }

                $calculate = Invoices::calculate_invoice($invoice, $items);

                $set_invoice = array_merge($set_invoice, $calculate);

                if ($set_invoice) Invoices::set($invoice["id"], $set_invoice);


                echo Utility::jencode([
                    'status'  => "successful",
                    'message' => __("website/account_invoices/successful1"),
                ]);

            } catch (Exception $e) {
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => $e->getMessage(),
                ]));
            }
        }
    }