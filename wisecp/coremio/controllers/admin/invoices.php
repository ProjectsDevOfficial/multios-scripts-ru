<?php
    defined('CORE_FOLDER') or exit('You can not get in here!');

    class Controller extends Controllers
    {
        protected $params, $data = [];


        public function __construct($arg = [])
        {
            parent::__construct();
            $this->params = $arg['params'];

            if (!UserManager::LoginCheck("admin")) {
                Utility::redirect($this->AdminCRLink("sign-in"));
                die();
            }
            Helper::Load("Admin");
            if (!Admin::isPrivilege(Config::get("privileges/INVOICES"))) die();
        }


        private function create()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $user_id = (int)Filter::init("POST/user_id", "numbers");
            $status = Filter::init("POST/status", "letters");
            $taxed = Filter::init("POST/taxed", "hclear");

            if ($taxed == "free") {
                $legal = 0;
                $taxed = 0;
            } else
                $taxed = (int)Filter::rnumbers($taxed);


            $taxed_file = Filter::FILES("taxed_file");
            $pmethod = Filter::init("POST/pmethod", "route");
            $cdate = Filter::init("POST/cdate", "numbers", "\-");
            $duedate = Filter::init("POST/duedate", "numbers", "\-");
            $datepaid = Filter::init("POST/datepaid", "numbers", "\-");
            $refunddate = Filter::init("POST/refunddate", "numbers", "\-");
            $ctime = Filter::init("POST/ctime", "numbers", ":");
            $duetime = Filter::init("POST/duetime", "numbers", ":");
            $timepaid = Filter::init("POST/timepaid", "numbers", ":");
            $refundtime = Filter::init("POST/refundtime", "numbers", ":");
            if (!$ctime) $ctime = DateManager::Now("H:i");
            if (!$duetime && $duedate) $duetime = $ctime;
            if (!$timepaid && $datepaid) $timepaid = $ctime;
            if (!$refundtime && $refunddate) $refundtime = $ctime;
            if ($status != "paid" && ($datepaid || $timepaid)) {
                $datepaid = '';
                $timepaid = '';
            }
            $currency = (int)Filter::init("POST/currency", "numbers");
            $sendbta = Filter::init("POST/sendbta", "amount");
            $pmethod_commission = Filter::init("POST/pmethod_commission", "amount");
            $items = Filter::POST("items");
            $notification = Filter::init("POST/notification", "numbers");

            if (!$currency) return false;

            if (!$user_id)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='user_id']",
                    'message' => __("admin/invoices/error1"),
                ]));

            if (!$items || !is_array($items))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "textarea[name='description']",
                    'message' => "Please add an invoice item.",
                ]));

            if (Validation::isEmpty($status))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='status']",
                    'message' => __("admin/invoices/error3"),
                ]));

            if (!$cdate)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='cdate']",
                    'message' => __("admin/invoices/error5"),
                ]));

            if (!$duedate)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='duedate']",
                    'message' => __("admin/invoices/error6"),
                ]));

            if (!$datepaid && ($status == "paid" || $status == "waiting" || $status == "refund"))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='datepaid']",
                    'message' => __("admin/invoices/error7"),
                ]));

            if (!$refunddate && $status == "refund")
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='refunddate']",
                    'message' => __("admin/invoices/error8"),
                ]));

            Helper::Load(["Money", "Invoices", "Orders", "Products"]);

            if ($sendbta)
                $sendbta = Money::deformatter($sendbta, $currency);

            if ($pmethod_commission) $pmethod_commission = Money::deformatter($pmethod_commission, $currency);


            $set_items = [];

            $rank = 0;
            foreach ($items as $item) {
                $rank++;
                $amount = Filter::amount($item["amount"]);
                $amount = Money::deformatter($amount, $currency);
                $tax_exempt = isset($item["taxexempt"]) ? $item["taxexempt"] : 0;
                $tax_exempt = (int)Filter::numbers($tax_exempt);

                $set_items[] = [
                    'rank'      => $rank,
                    'name'      => $item["description"],
                    'taxexempt' => $tax_exempt,
                    'amount'    => $amount,
                    'cid'       => $currency,
                ];
            }


            $cdate = $cdate . " " . $ctime . ":00";
            $duedate = $duedate . " " . $duetime . ":00";
            $datepaid = $datepaid ? $datepaid . " " . $timepaid . ":00" : DateManager::ata();
            $refunddate = $refunddate ? $refunddate . " " . $refundtime . ":00" : DateManager::ata();


            if ($taxed_file) {
                Helper::Load("Uploads");
                $upload = Helper::get("Uploads");
                $upload->init($taxed_file, [
                    'folder'    => RESOURCE_DIR . "uploads" . DS . "invoices" . DS,
                    'file-name' => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='taxed_file']",
                        'message' => __("admin/invoices/error12", ['{error}' => $upload->error]),
                    ]));
                $taxed_file = Utility::jencode($upload->operands[0]);
            }

            $invoice_data = [
                'user_id'            => $user_id,
                'cdate'              => $cdate,
                'duedate'            => $duedate,
                'datepaid'           => $datepaid,
                'refunddate'         => $refunddate,
                'status'             => $status,
                'currency'           => $currency,
                'taxed'              => $taxed,
                'taxed_file'         => $taxed_file,
                'pmethod'            => $pmethod ? $pmethod : '',
                'pmethod_commission' => $pmethod_commission ? $pmethod_commission : 0,
                'sendbta'            => $sendbta ? 1 : 0,
                'sendbta_amount'     => $sendbta ? $sendbta : 0,
                'unread'             => 1,
            ];
            if (isset($legal)) $invoice_data["legal"] = $legal;

            $create = Invoices::bill_generate($invoice_data, $set_items);

            if (!$create) {
                if (Invoices::$message == "no-user-address")
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error6"),
                    ]));
                elseif (Invoices::$message != '')
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error10", ['{error}' => Invoices::$message]),
                    ]));

                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/invoices/error11"),
                ]));
            }


            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/invoices/success1"),
                'redirect' => $this->AdminCRLink("invoices"),
            ]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "created-new-bill", [
                'id' => $create["id"],
            ]);

            if ($notification) {
                Helper::Load(["Notification"]);
                if ($status == "paid")
                    Notification::invoice_has_been_approved($create);
                elseif ($status == "unpaid")
                    Notification::invoice_created($create);
                elseif ($status == "refund")
                    Notification::invoice_returned($create);
                elseif ($status == "cancelled")
                    Notification::invoice_cancelled($create);
            }

            if ($taxed && $status == "paid") Invoices::MakeOperation("taxed", $create, $notification);

        }


        private function edit_detail()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
            if (!$id) return false;

            Helper::Load(["Invoices", "Orders", "Money"]);

            $invoice = Invoices::get($id);
            if (!$invoice) return false;


            $taxation = Invoices::getTaxation();
            $status = Filter::init("POST/status", "letters");
            $tax_rate = $invoice["taxrate"];
            $taxed = Filter::init("POST/taxed", "hclear");
            $taxfree = $taxed == "free";

            if ($taxfree) {
                $taxed = 0;
            } else {
                $taxed = (int)Filter::rnumbers($taxed);
            }

            $taxed_file = Filter::FILES("taxed_file");
            $pmethod = Filter::init("POST/pmethod", "route");
            $cdate = Filter::init("POST/cdate", "numbers", "\-");
            $duedate = Filter::init("POST/duedate", "numbers", "\-");
            $datepaid = Filter::init("POST/datepaid", "numbers", "\-");
            $refunddate = Filter::init("POST/refunddate", "numbers", "\-");
            $ctime = Filter::init("POST/ctime", "numbers", ":");
            $duetime = Filter::init("POST/duetime", "numbers", ":");
            $timepaid = Filter::init("POST/timepaid", "numbers", ":");
            $refundtime = Filter::init("POST/refundtime", "numbers", ":");
            if (!$ctime) $ctime = DateManager::Now("H:i");
            if (!$duetime && $duedate) $duetime = $ctime;
            if (!$timepaid && $datepaid) $timepaid = DateManager::Now("H:i");
            if (!$refundtime && $refunddate) $refundtime = DateManager::Now("H:i");
            $currency = (int)Filter::init("POST/currency", "numbers");
            $sendbta = Filter::init("POST/sendbta", "amount");
            $pmethod_commission = Filter::init("POST/pmethod_commission", "amount");
            $notification = (int)Filter::init("POST/notification", "numbers");
            $refund_via_module = (int)Filter::init("POST/refund_via_module", "numbers");

            if (!$currency) return false;

            if (Validation::isEmpty($status))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='status']",
                    'message' => __("admin/invoices/error3"),
                ]));

            if (!$cdate)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='cdate']",
                    'message' => __("admin/invoices/error5"),
                ]));

            if (!$duedate)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='duedate']",
                    'message' => __("admin/invoices/error6"),
                ]));

            if (!$datepaid && ($status == "paid" || $status == "refund"))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='datepaid']",
                    'message' => __("admin/invoices/error7"),
                ]));

            if (!$refunddate && $status == "refund")
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='refunddate']",
                    'message' => __("admin/invoices/error8"),
                ]));


            $cdate = $cdate . " " . $ctime . ":00";
            $duedate = $duedate . " " . $duetime . ":00";
            $datepaid = $datepaid ? $datepaid . " " . $timepaid . ":00" : DateManager::ata();
            $refunddate = $refunddate ? $refunddate . " " . $refundtime . ":00" : DateManager::ata();

            $u_taxation = $invoice["user_data"]["taxation"];


            if ($status == "unpaid") {
                $new_user_data = User::getInfo($invoice["user_id"], ["taxation"]);
                $u_taxation = $new_user_data["taxation"];
            }

            $getAddress = $invoice["user_data"]["address"];

            $country_id = $getAddress["country_id"];
            $city = isset($getAddress["city_id"]) ? $getAddress["city_id"] : $getAddress["city"];
            $taxation = Invoices::getTaxation($country_id, $u_taxation);
            if ($invoice["status"] != "unpaid" && $invoice["taxrate"]) {
                $legal = 1;
                $taxation = true;
            } else {
                $legal = $taxation ? 1 : 0;
            }


            if ($taxfree) $legal = 0;
            $real_tax_rate = Invoices::getTaxRate($country_id, $city, $invoice["user_id"]);
            $local = (int)Invoices::isLocal($country_id, $invoice["user_id"]);

            if (!($invoice["status"] == "paid" && $status == "paid"))
                if ($taxation && $local && $legal) $tax_rate = $real_tax_rate;


            if ($taxed_file) {
                Helper::Load("Uploads");
                $upload = Helper::get("Uploads");
                $upload->init($taxed_file, [
                    'date'      => false,
                    'folder'    => RESOURCE_DIR . "uploads" . DS . "invoices" . DS,
                    'file-name' => "random",
                ]);
                if (!$upload->processed())
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='taxed_file']",
                        'message' => __("admin/invoices/error12", ['{error}' => $upload->error]),
                    ]));
                $taxed_file = Utility::jencode($upload->operands[0]);
            } else $taxed_file = $invoice["taxed_file"];


            $subtotal = 0;
            $tax = 0;
            $total = 0;

            $sendbta = Money::deformatter($sendbta, $currency);

            if ($invoice["taxation_type"] == "inclusive")
                $sendbta -= Money::get_inclusive_tax_amount($sendbta, $tax_rate);

            if (round($invoice["sendbta_amount"], 2) == round($sendbta, 2))
                $sendbta = $invoice["sendbta_amount"];

            $pmethod_commission = Money::deformatter($pmethod_commission, $currency);

            if (round($invoice["pmethod_commission"], 2) == round($pmethod_commission, 2))
                $pmethod_commission = $invoice["pmethod_commission"];

            $invoice_data = [
                'cdate'              => $cdate,
                'duedate'            => $duedate,
                'datepaid'           => $datepaid,
                'refunddate'         => $refunddate,
                'currency'           => $currency,
                'tax'                => $tax,
                'taxrate'            => $tax_rate,
                'taxed_file'         => $taxed_file,
                'subtotal'           => $subtotal,
                'total'              => $total,
                'local'              => $local,
                'legal'              => $legal,
                'pmethod'            => $pmethod ? $pmethod : '',
                'pmethod_commission' => $pmethod_commission,
                'sendbta'            => $sendbta > 0.00 ? 1 : 0,
                'sendbta_amount'     => $sendbta,
                'unread'             => $status == "waiting" ? 0 : 1,
            ];

            if ($pmethod == "BankTransfer") {
                $pmethod_msg = Filter::POST("pmethod_msg");
                if ($pmethod_msg && is_array($pmethod_msg)) $invoice_data["pmethod_msg"] = Utility::jencode($pmethod_msg);
            }


            if ($invoice["pmethod"] == "BankTransfer" && $pmethod != $invoice["pmethod"])
                $invoice_data["pmethod_msg"] = '';

            if ($status == "unpaid" && $invoice["status"] != $status) {
                $invoice_data["pmethod_msg"] = '';
                $invoice_data["pmethod_status"] = '';
                $invoice_data["sendbta"] = 0;
                $invoice_data["sendbta_amount"] = 0;
                $invoice_data["pmethod_commission"] = 0;
                $invoice_data["tax"] = 0;
                $invoice_data["total"] = 0;
                $invoice_data["pmethod"] = "none";
                $invoice_data["discounts"] = '';
                $invoice_data["datepaid"] = DateManager::ata();
            }

            if ($taxed == 0 && $invoice["taxed"] != 0) $invoice_data["taxed"] = 0;
            if ($status == "unpaid" && $invoice["status"] != $status) $invoice_data["status"] = $status;


            $items = Filter::POST("items");
            $real_items = Invoices::get_items($invoice["id"]);
            $deleted_items = [];

            foreach ($real_items as $item)
                if (!isset($items[$item["id"]]))
                    $deleted_items[$item["id"]] = Invoices::delete_item($item["id"]);

            if ($items) {
                $rank = 0;
                foreach ($items as $k_id => $item) {
                    $rank++;
                    $desc = Filter::html_clear($item["description"]);
                    $tax_exempt = isset($item["taxexempt"]) ? $item["taxexempt"] : 0;
                    $tax_exempt = (int)Filter::numbers($tax_exempt);
                    $amount = Money::deformatter(Filter::amount($item["amount"]), $currency);

                    if ($invoice["taxation_type"] == "inclusive" && !$tax_exempt)
                        $amount -= Money::get_inclusive_tax_amount($amount, $tax_rate);

                    $real_item = Invoices::bring_item($invoice["id"], $k_id);
                    if (!$real_item) {
                        if ($real_item_id = Invoices::add_item([
                            'owner_id'     => $invoice["id"],
                            'user_id'      => $invoice["user_id"],
                            'description'  => $desc,
                            'amount'       => $amount,
                            'total_amount' => $amount,
                            'currency'     => $invoice["currency"],
                            'taxexempt'    => $tax_exempt,
                            'oduedate'     => $duedate,
                            'rank'         => $rank,
                        ])) $real_item = Invoices::bring_item($invoice["id"], $real_item_id);
                    }
                    if ($real_item) {

                        if (round($real_item["total_amount"], 2) == round($amount, 2)) $amount = $real_item["total_amount"];

                        $real_item["options"] = Utility::jdecode($real_item["options"], true);
                        $item_data = [];
                        if ($desc != $real_item["description"]) $item_data["description"] = $desc;
                        if ($amount != $real_item["amount"]) $item_data["amount"] = $amount;
                        if ($amount != $real_item["total_amount"]) $item_data["total_amount"] = $amount;
                        if ($rank != $real_item["rank"]) $item_data["rank"] = $rank;
                        if ($currency != $real_item["currency"]) $item_data["currency"] = $currency;
                        if ($tax_exempt != $real_item["taxexempt"]) $item_data["taxexempt"] = $tax_exempt;
                        if (isset($item_data['options'])) $item_data['options'] = Utility::jencode($item_data['options']);
                        if ($item_data) Invoices::set_item($real_item["id"], $item_data);
                    }

                }
            }


            $discounts = Filter::POST("discounts");

            if ($discounts && is_array($discounts) && $discounts) {
                foreach ($discounts as $disco_type => $disco_items) {
                    foreach ($disco_items as $disco_id => $disco_val) {
                        $amount = Filter::amount($disco_val);
                        $amount = rtrim($amount, ",");
                        $amount = rtrim($amount, ".");
                        $amount = Money::deformatter($amount, $currency);

                        if (isset($invoice["discounts"]["items"][$disco_type][$disco_id]))
                            if (round($amount, 2) == round($invoice["discounts"]["items"][$disco_type][$disco_id]["amountd"], 2))
                                $amount = $invoice["discounts"]["items"][$disco_type][$disco_id]["amountd"];


                        $amount_d = Money::formatter_symbol($amount, $currency);

                        if ((isset($deleted_items[$disco_id]) && $deleted_items[$disco_id]) || $amount < 0.1) {
                            unset($invoice["discounts"]["items"][$disco_type][$disco_id]);
                            continue;
                        }


                        $invoice["discounts"]["items"][$disco_type][$disco_id]["amountd"] = $amount;
                        $invoice["discounts"]["items"][$disco_type][$disco_id]["amount"] = $amount_d;
                        if (isset($invoice["discounts"]["items"][$disco_type][$disco_id]["dvalue"]))
                            if ($invoice["discounts"]["items"][$disco_type][$disco_id]["rate"] < 1)
                                $invoice["discounts"]["items"][$disco_type][$disco_id]["dvalue"] = $amount_d;
                    }
                }
                $invoice_data["discounts"] = $invoice["discounts"];
            }

            if ($deleted_items && isset($invoice_data["discounts"]["items"]) && $invoice_data["discounts"]["items"]) {
                foreach (array_keys($deleted_items) as $item_id) {
                    foreach (array_keys($invoice_data["discounts"]["items"]) as $t) {
                        if (isset($invoice_data["discounts"]["items"][$t][$item_id]))
                            unset($invoice_data["discounts"]["items"][$t][$item_id]);
                        if (!$invoice_data["discounts"]["items"][$t]) unset($invoice_data["discounts"]["items"][$t]);
                    }
                }
            }


            if ($pmethod_commission && $pmethod != "none" && $pmethod != "BankTransfer" && $pmethod != "Balance")
                $invoice_data["pmethod_commission"] = $pmethod_commission;

            $items = Invoices::get_items($id);
            $calculate = Invoices::calculate_invoice($invoice_data, $items);

            if ($calculate) {
                $subtotal += $calculate['subtotal'];
                $tax += $calculate['tax'];
                $total += $calculate['total'];
            }


            $invoice_data["subtotal"] = $subtotal;
            $invoice_data["tax"] = $tax;
            $invoice_data["total"] = $total;

            Hook::run("PreInvoiceModified", Invoices::get($invoice["id"]), $invoice_data);

            $invoice_data["discounts"] = $discounts ? Utility::jencode($invoice_data["discounts"]) : '';


            if ($pmethod != $invoice["pmethod"] && $pmethod == "Balance" && $invoice["status"] != "paid" && $status == "paid") {
                $udata = User::getData($invoice["user_data"]["id"], "id,balance,balance_currency", "array");

                $u_amount = round($udata["balance"], 2);
                $c_amount = round($total, 2);

                if ($udata["balance_currency"] != $invoice["currency"])
                    $c_amount = Money::exChange($total, $invoice["currency"], $udata["balance_currency"]);

                if ($u_amount < $c_amount) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => Bootstrap::$lang->get("errors/error4", Config::get("general/local")),
                    ]);
                    return false;
                }

                $newBalance = $u_amount - $c_amount;

                if ($newBalance < 0.00) $newBalance = 0;

                User::setData($udata["id"], ['balance' => $newBalance]);
                User::insert_credit_log([
                    'user_id'     => $udata["id"],
                    'description' => $invoice["number"],
                    'type'        => "down",
                    'amount'      => $c_amount,
                    'cid'         => $udata["balance_currency"],
                    'cdate'       => DateManager::Now(),
                ]);
            }

            $update = Invoices::set($id, $invoice_data);

            if (!$update)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/invoices/error11"),
                ]));


            if ($status == "refund" && $invoice["status"] != $status) {
                $apply = Invoices::MakeOperation("refund", $invoice["id"], $notification, $refund_via_module);
                if (!$apply) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => Invoices::$message,
                    ]);
                    return false;
                }
            }

            $get_inex = Invoices::get_inex(0, $id);
            if ($get_inex)
                Invoices::set_inex($get_inex["id"], [
                    'amount'   => $total,
                    'currency' => $currency,
                ]);

            Hook::run("InvoiceModified", Invoices::get($invoice["id"]));

            $redirect = $this->AdminCRLink("invoices");


            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/invoices/success4"),
                'redirect' => $redirect,
            ]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-bill-detail", [
                'id' => $id,
            ]);

            define("EDIT_DETAIL_BILL", true);

            if ($status == "paid" && $invoice["status"] != $status) Invoices::MakeOperation("paid", $invoice["id"], $notification);
            if ($status == "cancelled" && $invoice["status"] != $status) Invoices::MakeOperation("cancelled", $invoice["id"], $notification);
            if ($taxed == 1 && $invoice["taxed"] != 1) Invoices::MakeOperation("taxed", $invoice["id"], $notification);

        }


        private function split_selected_items()
        {

            Helper::Load(["Invoices", "Money"]);

            $this->takeDatas("language");

            if (isset($this->params[0]) && $this->params[0] == "detail" && isset($this->params[1])) {
                $invoice_id = (int)Filter::numbers($this->params[1]);
                $invoice = Invoices::get($invoice_id);
            } else {
                $invoice = false;
                $invoice_id = 0;
            }

            if (!$invoice) return false;

            $selected = Filter::POST("selected");

            if (!$selected || !is_array($selected)) return false;

            $main_data = $invoice;
            $invoice_data = $invoice;
            unset($invoice_data['id']);
            if(isset($invoice_data["number"])) unset($invoice_data["number"]);
            $items = [];

            foreach ($selected as $i_id) {
                if (Validation::isInt($i_id) && $i_id) {
                    $item = Invoices::bring_item(0, $i_id);
                    if ($item['owner_id'] == $invoice_id)
                        $items[$i_id] = $item;
                }
            }

            if (!$items) return false;

            if (isset($invoice_data['user_data'])) $invoice_data['user_data'] = Utility::jencode($invoice_data['user_data']);
            if (isset($invoice_data['data'])) $invoice_data['data'] = Utility::jencode($invoice_data['data']);

            $new_discounts = [];

            if (isset($invoice['discounts']['items']) && $invoice['discounts']['items']) {
                foreach (array_keys($invoice['discounts']['items']) as $g) {
                    if (isset($invoice['discounts']['items'][$g])) {
                        if ($invoice['discounts']['items'][$g]) {
                            $_discounts = $invoice['discounts']['items'][$g];
                            foreach ($selected as $i_id) {
                                if (isset($_discounts[$i_id])) {
                                    $new_discounts[$g][$i_id] = $_discounts[$i_id];
                                    unset($main_data['discounts']['items'][$g][$i_id]);
                                }
                            }
                        }
                    }
                    if (isset($main_data['discounts']['items'][$g]) && !$main_data['discounts']['items'][$g])
                        unset($main_data['discounts']['items'][$g]);
                }
            }

            if ($new_discounts) $invoice_data['discounts'] = ['items' => $new_discounts];

            $calculate = Invoices::calculate_invoice($invoice_data, $items);

            $invoice_data['subtotal'] = $calculate['subtotal'];
            $invoice_data['tax'] = $calculate['tax'];
            $invoice_data['total'] = $calculate['total'];

            if (isset($invoice_data['discounts'])) $invoice_data['discounts'] = Utility::jencode($invoice_data['discounts']);

            $new_id = Invoices::add($invoice_data);

            foreach ($items as $item) Invoices::set_item($item['id'], ['owner_id' => $new_id]);

            $items = Invoices::get_items($main_data['id']);
            $calculate = Invoices::calculate_invoice($main_data, $items);
            Invoices::set($main_data['id'], [
                'discounts' => $main_data['discounts'] ? Utility::jencode($main_data['discounts']) : '',
                'subtotal'  => $calculate['subtotal'],
                'tax'       => $calculate['tax'],
                'total'     => $calculate['total'],
            ]);


            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/invoices/success10"),
                'redirect' => $this->AdminCRLink("invoices-2", ["detail", $new_id]),
            ]);
        }


        private function calculate()
        {

            Helper::Load(["Invoices", "Money"]);

            $this->takeDatas("language");

            if (isset($this->params[0]) && $this->params[0] == "detail" && isset($this->params[1])) {
                $invoice_id = (int)Filter::numbers($this->params[1]);
                $invoice = Invoices::get($invoice_id);
                $user_id = $invoice["user_id"];
            } else {
                $invoice = false;
                $user_id = (int)Filter::init("POST/user_id", "numbers");
            }

            $items = Filter::POST("items");
            $discounts = Filter::POST("discounts");
            $currency = (int)Filter::init("POST/currency", "numbers");
            $taxed = Filter::init("POST/taxed");
            $pmethod = Filter::init("POST/pmethod");
            $sendbta = Filter::init("POST/sendbta", "amount");
            $pcommission = Filter::init("POST/pmethod_commission", "amount");
            $status = Filter::init("POST/status", "letters");
            $subtotal = 0;
            $tax = 0;
            $total = 0;
            $taxfree = $taxed == "free";

            if ($invoice && $invoice["status"] == "paid" && $status == "paid" && $invoice['tax'] > 0.00) $taxfree = false;

            $curr_udata = $invoice ? $invoice["user_data"] : [];


            $infos = User::getInfo($user_id, "default_address,dealership,taxation");
            $datas = User::getData($user_id, "id,country", "array");

            $new_udata = $infos && $datas ? array_merge($infos, $datas) : [];
            $taxation_type = $invoice ? $invoice["taxation_type"] : Invoices::getTaxationType();
            $tax_rate = 0;
            $city = 0;

            if ($invoice) {
                $legal = $invoice["legal"];
                $getAddress = $curr_udata["address"];
                $city = isset($getAddress["city_id"]) ? $getAddress["city_id"] : $getAddress["city"];
                $country = $getAddress["country_id"];
            } else {
                if (isset($new_udata["id"])) {
                    $country = $new_udata["country"];
                    if ($new_udata["default_address"] && AddressManager::CheckAddress($new_udata["default_address"], $user_id)) {
                        $getAddress = AddressManager::getAddress($new_udata["default_address"]);
                        if ($getAddress)
                            $city = isset($getAddress["city_id"]) ? $getAddress["city_id"] : $getAddress["city"];
                    }
                } else
                    $country = AddressManager::LocalCountryID();
            }

            if ($status != "unpaid" && $invoice)
                $u_taxation = $curr_udata["taxation"];
            else
                $u_taxation = isset($new_udata["taxation"]) ? $new_udata["taxation"] : null;

            $taxation = Invoices::getTaxation($country, $u_taxation);

            if ($invoice && $status !== "unpaid" && $invoice["taxrate"] > 0.00)
                $taxation = true;

            if ($taxfree) $taxation = false;

            $real_tax_rate = Invoices::getTaxRate($country, $city, $user_id);

            if ($status != "unpaid" && $invoice && $invoice["status"] == "paid") $real_tax_rate = $invoice["taxrate"];

            $tax_rate = $real_tax_rate;

            if ($sendbta) {
                $sendbta = rtrim($sendbta, ",");
                $sendbta = rtrim($sendbta, ".");
                $sendbta = Money::deformatter($sendbta, $currency);

                if ($taxation_type == "inclusive" && $tax_rate > 0)
                    $sendbta -= Money::get_inclusive_tax_amount($sendbta, $tax_rate);

                if ($invoice && round($invoice["sendbta_amount"], 2) == round($sendbta, 2))
                    $sendbta = $invoice["sendbta_amount"];
            }


            if ($pcommission) {
                $pcommission = rtrim($pcommission, ",");
                $pcommission = rtrim($pcommission, ".");
                $pcommission = Money::deformatter($pcommission, $currency);
            }

            if ($pcommission > 0.00 && $pmethod != "none" && $pmethod != "BankTransfer" && $pmethod != "Balance") {
                if ($invoice && round($invoice["pmethod_commission"], 2) == $pcommission)
                    $pcommission = $invoice["pmethod_commission"];

            }

            if ($discounts && is_array($discounts)) {
                $new_discounts = [];
                foreach ($discounts as $disco_type => $disco_items) {
                    foreach ($disco_items as $disco_id => $disco_val) {
                        $amount = Filter::amount($disco_val);
                        $amount = rtrim($amount, ",");
                        $amount = rtrim($amount, ".");
                        $amount = Money::deformatter($amount, $currency);

                        if ($invoice)
                            if (isset($invoice["discounts"]["items"][$disco_type][$disco_id]))
                                if (round($invoice["discounts"]["items"][$disco_type][$disco_id]["amountd"], 2) == round($amount, 2))
                                    $amount = $invoice["discounts"]["items"][$disco_type][$disco_id]["amountd"];
                        $new_discounts['items'][$disco_type][$disco_id] = ['amountd' => $amount];
                    }
                }
                $discounts = $new_discounts;
            }

            $attr = [
                'taxation_type'      => $taxation_type,
                'taxrate'            => $tax_rate,
                'sendbta_amount'     => $sendbta,
                'pmethod_commission' => $pcommission,
                'discounts'          => $discounts,
            ];

            if ($status == "unpaid") {
                if ($taxfree) $attr["legal"] = 0;
                else $attr["legal"] = 1;
            }


            if ($invoice) $attr = array_replace_recursive($invoice, $attr);

            if ($items && is_array($items)) {
                $new_items = [];
                foreach ($items as $item_id => $item) {
                    $amount = Filter::amount($item["amount"]);
                    $tax_exempt = isset($item["taxexempt"]) ? $item["taxexempt"] : 0;
                    $tax_exempt = Filter::numbers($tax_exempt);
                    $amount = rtrim($amount, ",");
                    $amount = rtrim($amount, ".");
                    $amount = Money::deformatter($amount, $currency);

                    if ($taxation_type == "inclusive" && !$tax_exempt)
                        $amount -= Money::get_inclusive_tax_amount($amount, $tax_rate);

                    $b_item = false;
                    if ($invoice && $b_item = Invoices::bring_item(0, $item_id))
                        if (round($b_item["total_amount"], 2) == round($amount, 2))
                            $amount = $b_item["total_amount"];

                    $new_items[$item_id] = [
                        'amount'       => $amount,
                        'total_amount' => $amount,
                        'taxexempt'    => $tax_exempt,
                        'options'      => $b_item ? $b_item["options"] : [],
                    ];
                }
                $items = $new_items;
            }

            $calculate = Invoices::calculate_invoice($attr, $items, ['discount_total' => true]);


            $discount = $calculate["discount_total"];
            $subtotal += $calculate['subtotal'];
            $tax += $calculate['tax'];
            $total += $calculate['total'];


            $zero_placeholder = Money::formatter_symbol(1.11, $currency);
            $zero_placeholder = str_replace("1", "0", $zero_placeholder);

            echo Utility::jencode([
                'status'   => "successful",
                'tax_rate' => round($tax_rate, 2),
                'subtotal' => $subtotal > 0 ? Money::formatter_symbol($subtotal, $currency) : $zero_placeholder,
                'discount' => $discount > 0 ? Money::formatter_symbol($discount, $currency) : $zero_placeholder,
                'tax'      => $tax > 0 ? Money::formatter_symbol($tax, $currency) : $zero_placeholder,
                'total'    => $total > 0 ? Money::formatter_symbol($total, $currency) : $zero_placeholder,
            ]);

        }


        private function update_informations()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
            if (!$id) return false;

            Helper::Load(["Invoices", "Orders", "Money"]);

            $invoice = Invoices::get($id);
            if (!$invoice) return false;

            $number = Filter::init("POST/number", "hclear");
            $kind = Filter::init("POST/kind", "letters");
            $full_name = Filter::init("POST/full_name", "hclear");
            $full_name = Utility::substr($full_name, 0, 255);
            $full_name = Utility::ucfirst_space($full_name, ___("package/charset-code"));
            $email = Filter::init("POST/email", "email");
            $gsm = Filter::init("POST/gsm", "numbers");
            $landlinep = Filter::init("POST/landline_phone", "numbers");
            $identity = Filter::init("POST/identity", "identity");
            $company_name = Filter::init("POST/company_name", "hclear");
            $company_taxnu = Filter::init("POST/company_tax_number", "letters_numbers", "-");
            $company_taxoff = Filter::init("POST/company_tax_office", "letters");

            $country = Filter::init("POST/country", "numbers");
            $city = Filter::init("POST/city", "hclear");
            $counti = Filter::init("POST/counti", "hclear");
            $address = Filter::init("POST/address", "hclear");
            $zipcode = substr(Filter::init("POST/zipcode", "hclear"), 0, 20);

            $set_user_data = $invoice["user_data"];

            $set_user_data["identity"] = $identity;

            if ($kind == "individual" || $kind == "corporate") $set_user_data["kind"] = $kind;

            if ($kind == "corporate") {
                $set_user_data["company_name"] = $company_name;
                $set_user_data["company_tax_number"] = $company_taxnu;
                $set_user_data["company_tax_office"] = $company_taxoff;
            }

            if ($kind == "individual" && Validation::isEmpty($full_name))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='full_name']",
                    'message' => __("admin/users/error1"),
                ]));

            $set_user_data["full_name"] = $full_name;

            if (Validation::isEmpty($email))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='email']",
                    'message' => __("admin/users/error2"),
                ]));

            if (!Validation::isEmail($email))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='email']",
                    'message' => __("admin/users/error3"),
                ]));

            $set_user_data["email"] = $email;

            $set_user_data["landline_phone"] = $landlinep;

            if (strlen($gsm) >= 10) {
                if (!Validation::isPhone($gsm))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "#gsm",
                        'message' => __("admin/users/error6"),
                    ]));

                $gsm_parse = Filter::phone_smash($gsm);
                $gsm_cc = $gsm_parse["cc"];
                $gsm = $gsm_parse["number"];

                $set_user_data["gsm_cc"] = $gsm_cc;
                $set_user_data["gsm"] = $gsm;
            }

            if (!$country)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='country']",
                    'message' => __("admin/invoices/error15"),
                ]));

            $country = AddressManager::getCountry($country, "t1.id,t1.a2_iso,t2.name", Config::get("general/local"));

            if (!$country)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='country']",
                    'message' => __("admin/invoices/error15"),
                ]));


            $set_user_data["address"]["country_id"] = $country["id"];
            $set_user_data["address"]["country_code"] = $country["a2_iso"];
            $set_user_data["address"]["country_name"] = $country["name"];
            $set_user_data["address"]["city"] = $city;
            $set_user_data["address"]["counti"] = $counti;
            $set_user_data["address"]["zipcode"] = $zipcode;
            $set_user_data["address"]["address"] = $address;
            if (isset($set_user_data["address"]["city_id"])) unset($set_user_data["address"]["city_id"]);

            $tax_rate = 0;
            $local = (int)Invoices::isLocal($country["id"], $invoice["user_id"]);
            if ($invoice["legal"] && $local) $tax_rate = Invoices::getTaxRate($country["id"], $city, $invoice["user_id"]);

            Invoices::set($id, [
                'number'    => $number,
                'user_data' => Utility::jencode($set_user_data),
                'local'     => $local,
                'taxrate'   => $tax_rate,
            ]);

            $adata = UserManager::LoginData("admin");

            User::addAction($adata["id"], "alteration", "changed-invoice-informations", [
                'id' => $id,
            ]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/invoices/success9"),
            ]);

        }


        private function delete_taxed_file()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
            if (!$id) return false;

            Helper::Load(["Invoices"]);

            $invoice = Invoices::get($id);
            if (!$invoice) return false;

            if ($invoice["taxed_file"]) {
                $taxed_file = Utility::jdecode($invoice["taxed_file"], true);
                $taxed_file = $taxed_file;

                $folder = RESOURCE_DIR . "uploads" . DS . "invoices" . DS;
                $file = $taxed_file["file_path"];
                $filepath = $folder . $file;
                FileManager::file_delete($filepath);

                Invoices::set($id, ['taxed_file' => '']);
            }


            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/invoices/success5"),
            ]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "deleted", "deleted-bill-taxed-file", [
                'id' => $id,
            ]);

        }


        private function ajax_bills()
        {

            $limit = 10;
            $output = [];
            $aColumns = array();

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


            $status = isset($this->params[0]) ? $this->params[0] : false;
            $user_id = (int)Filter::init("GET/user_id", "numbers");
            $from = Filter::init("GET/from", "letters");

            if (Filter::init("GET/search", "letters") == "true") {
                $word = Filter::init("GET/word", "hclear");
                $startx = Filter::init("GET/start", "numbers", "\-");
                $endx = Filter::init("GET/end", "numbers", "\-");
                $taxed = (int)Filter::init("GET/taxed", "numbers");
                if (!Validation::isDate($startx)) $startx = false;
                if (!Validation::isDate($endx)) $endx = false;
                if ($startx && !$endx) $endx = DateManager::Now("Y-m-d");
                if ($word) $searches["word"] = $word;
                if ($startx) $searches["start"] = $startx;
                if ($endx) $searches["end"] = $endx;
                if ($taxed) $searches["taxed"] = $taxed;
            }

            if (!Validation::isEmpty(Filter::GET("sSearch"))) $searches['word'] = Filter::init("GET/sSearch", "hclear");
            if ($from == "user" && (int)Filter::init("GET/id", "numbers")) $user_id = (int)Filter::init("GET/id", "numbers");

            $filteredList = $this->model->get_bills($status, $user_id, $searches, $orders, $start, $end);
            $filterTotal = $this->model->get_bills_total($status, $user_id, $searches);
            $listTotal = $this->model->get_bills_total($status, $user_id);

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                Helper::Load("Money", "Invoices");

                $privOperation = Admin::isPrivilege("INVOICES_OPERATION");
                $privDelete = Admin::isPrivilege("INVOICES_DELETE");

                $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                $situations = $situations["invoices"];

                if ($filteredList) {
                    $this->addData("from", $from);
                    $this->addData("privOperation", $privOperation);
                    $this->addData("privDelete", $privDelete);
                    $this->addData("situations", $situations);
                    $this->addData("status", $status);
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-invoices", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function apply_operation()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $type = Filter::init("POST/type", "letters");
            $id = Filter::POST("id");
            $from = Filter::init("POST/from", "letters");

            if (Validation::isEmpty($type) || !$id) die("Invalid params");

            if (!is_array($id)) $id = [$id];
            if (!$id) die();
            Helper::Load(["Orders", "Invoices", "Products", "Money"]);

            $id_size = sizeof($id);

            if ($id_size > 1 && $type == "delete") {
                $password = Filter::init("POST/password", "password");
                $apassword = UserManager::LoginData("admin");
                $apassword = User::getData($apassword["id"], "password", "array");
                $apassword = $apassword["password"];

                if (!$password)
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "#password1",
                        'message' => ___("needs/permission-delete-item-empty-password"),
                    ]));

                if (!User::_password_verify("admin", $password, $apassword))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/permission-delete-item-invalid-password"),
                    ]));
            }

            $successful = 0;
            $failed = 0;
            $message = '';

            Helper::Load(["Notification"]);

            $adata = UserManager::LoginData("admin");
            $delPriv = Admin::isPrivilege(["INVOICES_DELETE"]);

            if ($type == 'merge') {
                $invoices = [];
                $max_duedate = 0;

                foreach ($id as $inv_id) {
                    $inv = Invoices::get($inv_id);
                    $inv_duedate = DateManager::strtotime($inv["duedate"]);
                    if ($max_duedate < $inv_duedate) $max_duedate = $inv_duedate;
                    if ($inv['status'] == 'unpaid') $invoices[$inv_id] = $inv;
                }

                if ($invoices && sizeof($invoices) > 1) {
                    $main_invoice = array_pop($invoices);
                    $main_cid = $main_invoice["currency"];
                    $main_discounts = $main_invoice['discounts'];
                    foreach ($invoices as $invoice) {
                        $items = Invoices::get_items($invoice['id']);
                        if ($items) {
                            foreach ($items as $item) {
                                $i_amount = Money::exChange($item['amount'], $item['currency'], $main_cid);
                                $i_t_amount = Money::exChange($item['total_amount'], $item['currency'], $main_cid);
                                $i_amount = round($i_amount, 2);
                                $i_t_amount = round($i_t_amount, 2);
                                Invoices::set_item($item['id'], [
                                    'owner_id'     => $main_invoice['id'],
                                    'amount'       => $i_amount,
                                    'total_amount' => $i_t_amount,
                                    'currency'     => $main_cid,
                                ]);
                            }
                        }
                        $discounts = $invoice['discounts'];

                        if ($discounts) {
                            if (isset($discounts['items']) && $d_items = $discounts['items']) {
                                foreach ($d_items as $d_v_k => $d_v_s) {
                                    if ($d_v_s) {
                                        foreach ($d_v_s as $d_v_i_k => $d_v_i) {
                                            $amount_d = $d_v_i["amountd"];
                                            $amount_d = Money::exChange($amount_d, $invoice["currency"], $main_cid);
                                            $amount_d = round($amount_d, 2);
                                            $d_amount = Money::formatter_symbol($amount_d, $main_cid);
                                            $discounts['items'][$d_v_k][$d_v_i_k]['amountd'] = $amount_d;
                                            $discounts['items'][$d_v_k][$d_v_i_k]['amount'] = $d_amount;
                                            if (isset($d_v_i['rate']) && isset($d_v_i['dvalue']) && $d_v_i['rate'] < 1)
                                                $discounts['items'][$d_v_k][$d_v_i_k]['dvalue'] = $d_amount;
                                        }
                                    }
                                }
                            }
                        }

                        if ($discounts) $main_discounts = array_merge_recursive($main_discounts, $discounts);
                        Invoices::delete($invoice['id']);

                        Models::$init->db
                            ->update("users_products", ['invoice_id' => $main_invoice['id']])
                            ->where("invoice_id", "=", $invoice["id"])
                            ->save();

                        Models::$init->db
                            ->update("users_products_addons", ['invoice_id' => $main_invoice['id']])
                            ->where("invoice_id", "=", $invoice["id"])
                            ->save();

                        Models::$init->db
                            ->update("users_products_updowngrades", ['invoice_id' => $main_invoice['id']])
                            ->where("invoice_id", "=", $invoice["id"])
                            ->save();
                    }
                    $main_invoice['discounts'] = $main_discounts;
                    $main_items = Invoices::get_items($main_invoice['id']);
                    $set_main_data = Invoices::calculate_invoice($main_invoice, $main_items);
                    $set_main_data['discounts'] = $main_discounts ? Utility::jencode($main_discounts) : '';
                    $set_main_data["duedate"] = DateManager::timetostr("Y-m-d H:i:s", $max_duedate);
                    Invoices::set($main_invoice['id'], $set_main_data);
                    if ($get_inex = Invoices::get_inex(0, $main_invoice["id"]))
                        Invoices::set_inex($get_inex["id"], ['amount' => $set_main_data['total']]);
                    $successful += 1;
                }
            } else {
                foreach ($id as $k => $v) {
                    $v = (int)Filter::numbers($v);
                    $invoice = Invoices::get($v);
                    if ($invoice && $type != $invoice["status"]) {
                        $condition = $type == "paid" || $type == "unpaid" || $type == "delete" || $type == "cancelled";
                        if (!$condition && ($type == "remind" && $invoice["status"] == "unpaid")) $condition = true;

                        if ($condition) {
                            if ($type == "remind") {
                                $remaining_day = DateManager::remaining_day(DateManager::format('Y-m-d', $invoice["duedate"]), DateManager::Now("Y-m-d"));
                                if ($remaining_day == 0)
                                    $remaining_day = 1;

                                if ($remaining_day < 0)
                                    $apply = Notification::invoice_overdue($invoice);
                                else
                                    $apply = Notification::invoice_reminder($invoice, $remaining_day);
                            } else
                                $apply = Invoices::MakeOperation($type, $invoice, true);

                            if ($apply) {
                                if ($type == "paid" && $invoice["status"] == "unpaid") {
                                    Invoices::set($v, ['datepaid' => DateManager::Now()]);
                                }
                                $successful++;
                                if ($type == "delete" && $delPriv)
                                    User::addAction($adata["id"], "deleted", "deleted-invoice", [
                                        'id' => $v,
                                    ]);
                                elseif ($type == "remind")
                                    User::addAction($adata["id"], "alteration", "was-reminded-invoice", [
                                        'id' => $v,
                                    ]);
                                elseif ($type == "paid")
                                    User::addAction($adata["id"], "alteration", "invoice-has-been-approved", [
                                        'id'     => $v,
                                        'status' => $type,
                                    ]);
                                elseif ($type == "unpaid")
                                    User::addAction($adata["id"], "alteration", "Invoice Marked Successfully Paid", [
                                        'id'     => $v,
                                        'status' => $type,
                                    ]);
                                elseif ($type == "cancelled")
                                    User::addAction($adata["id"], "alteration", "Invoice Marked Successfully Cancelled", [
                                        'id'     => $v,
                                        'status' => $type,
                                    ]);
                            } else {
                                if (Invoices::$message) $message = Invoices::$message;
                                $failed++;
                            }
                        } else $failed++;
                    } else $failed++;
                }
            }

            if ($from == "detail") {
                if ($successful)
                    echo Utility::jencode([
                        'status'   => "successful",
                        'message'  => __("admin/invoices/success2-" . $type),
                        'redirect' => $type == "delete" ? $this->AdminCRLink("invoices") : $this->AdminCRLink("invoices-2", ['detail', $id[0]]),
                    ]);
                else
                    echo Utility::jencode([
                        'status'  => "error",
                        'for'     => "status",
                        'message' => $message ? $message : __("admin/invoices/error13"),
                    ]);
            } elseif ($from == "list") {
                if ($successful)
                    echo Utility::jencode([
                        'status'  => "successful",
                        'message' => __("admin/invoices/success3-" . $type),
                    ]);
                else
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => $message ? $message : __("admin/invoices/error13"),
                    ]);
            }
        }


        private function add_cash()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Money"]);

            $amount = Filter::init("POST/amount", "amount");
            $currency = Filter::init("POST/currency", "numbers");
            $amount = Money::deformatter($amount, $currency);
            $type = Filter::init("POST/type", "letters");
            $description = Filter::init("POST/description", "hclear");
            $cdate = Filter::init("POST/cdate", "numbers", "\-");
            $ctime = Filter::init("POST/ctime", "numbers", ":");

            if (!$amount)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='amount']",
                    'message' => __("admin/invoices/error14"),
                ]));

            if (Validation::isEmpty($cdate) || !Validation::isDate($cdate)) $cdate = DateManager::Now("Y-m-d");
            if (Validation::isEmpty($ctime) || !Validation::isTime($ctime)) $ctime = DateManager::Now("H:i");

            $datetime = $cdate . " " . $ctime . ":00";

            $insert = $this->model->insert_inex([
                'type'        => $type,
                'amount'      => $amount,
                'currency'    => $currency,
                'description' => $description,
                'cdate'       => $datetime,
            ]);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/invoices/error11"),
                ]));

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-in-cash");

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/invoices/success6"),
            ]);

        }


        private function edit_cash()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Money"]);

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) return false;

            $amount = Filter::init("POST/amount", "amount");
            $currency = Filter::init("POST/currency", "numbers");
            $amount = Money::deformatter($amount, $currency);
            $type = Filter::init("POST/type", "letters");
            $description = Filter::init("POST/description", "hclear");

            $cdate = Filter::init("POST/cdate", "numbers", "\-");
            $ctime = Filter::init("POST/ctime", "numbers", ":");


            if (!$amount)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='amount']",
                    'message' => __("admin/invoices/error14"),
                ]));

            if (Validation::isEmpty($cdate) || !Validation::isDate($cdate)) $cdate = DateManager::Now("Y-m-d");
            if (Validation::isEmpty($ctime) || !Validation::isTime($ctime)) $ctime = DateManager::Now("H:i");

            $datetime = $cdate . " " . $ctime . ":00";

            $update = $this->model->set_inex($id, [
                'type'        => $type,
                'amount'      => $amount,
                'currency'    => $currency,
                'description' => $description,
                'cdate'       => $datetime,
            ]);

            if (!$update)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/invoices/error11"),
                ]));

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-inex");

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/invoices/success8"),
            ]);

        }


        private function delete_cash()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) return false;

            $delete = $this->model->delete_inex($id);
            if (!$delete)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/invoices/error11"),
                ]));

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "deleted", "deleted-cash-item");

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/invoices/success7"),
            ]);
        }


        private function add_periodic_outgoing()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Money"]);

            $amount = Filter::init("POST/amount", "amount");
            $currency = Filter::init("POST/currency", "numbers");
            $amount = Money::deformatter($amount, $currency);
            $description = Filter::init("POST/description", "hclear");
            $cdate = DateManager::Now();
            $period_day = (int)Filter::init("POST/period_day", "amount");
            $period_hour_minute = Filter::init("POST/period_hour_minute", "hclear");

            if ($period_day < 0)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='period_day']",
                    'message' => __("admin/invoices/error19"),
                ]));

            if (!$period_hour_minute)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='period_day']",
                    'message' => __("admin/invoices/error20"),
                ]));

            $split = explode(":", $period_hour_minute);
            $period_hour = (int)$split[0];
            $period_minute = (int)$split[1];


            if (!$amount)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='amount']",
                    'message' => __("admin/invoices/error14"),
                ]));


            $insert = $this->model->db->insert("periodic_outgoings", [
                'amount'        => $amount,
                'currency'      => $currency,
                'description'   => $description,
                'cdate'         => $cdate,
                'period_day'    => $period_day,
                'period_hour'   => $period_hour,
                'period_minute' => $period_minute,
            ]);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/invoices/error11"),
                ]));

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-periodic-outgoing");

            echo Utility::jencode([
                'status' => "successful",
            ]);

        }

        private function edit_periodic_outgoing()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Money"]);

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) return false;

            $amount = Filter::init("POST/amount", "amount");
            $currency = Filter::init("POST/currency", "numbers");
            $amount = Money::deformatter($amount, $currency);
            $description = Filter::init("POST/description", "hclear");

            $period_day = (int)Filter::init("POST/period_day", "amount");
            $period_hour_minute = Filter::init("POST/period_hour_minute", "hclear");

            if ($period_day < 0)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='period_day']",
                    'message' => __("admin/invoices/error19"),
                ]));

            if (!$period_hour_minute)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='period_day']",
                    'message' => __("admin/invoices/error20"),
                ]));

            $split = explode(":", $period_hour_minute);
            $period_hour = (int)$split[0];
            $period_minute = (int)$split[1];

            if (!$amount)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='amount']",
                    'message' => __("admin/invoices/error14"),
                ]));

            $update = $this->model->db->update("periodic_outgoings", [
                'amount'        => $amount,
                'currency'      => $currency,
                'description'   => $description,
                'period_day'    => $period_day,
                'period_hour'   => $period_hour,
                'period_minute' => $period_minute,
            ])->where("id", "=", $id)->save();

            if (!$update)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/invoices/error11"),
                ]));

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-periodic-outgoing");

            echo Utility::jencode([
                'status' => "successful",
            ]);
        }

        private function delete_outgoing()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) return false;

            $delete = $this->model->db->delete("periodic_outgoings")->where("id", "=", $id)->run();
            if (!$delete)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/invoices/error11"),
                ]));

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "deleted", "deleted-periodic-outgoing");

            echo Utility::jencode([
                'status' => "successful",
            ]);
        }


        private function operationMain($operation)
        {

            if ($operation == "calculate")
                return $this->calculate();

            if ($operation == "create" && Admin::isPrivilege(["INVOICES_OPERATION"]))
                return $this->create();

            if ($operation == "split_selected_items" && Admin::isPrivilege(["INVOICES_OPERATION"]))
                return $this->split_selected_items();

            if ($operation == "add_cash" && Admin::isPrivilege(["INVOICES_CASH"]))
                return $this->add_cash();

            if ($operation == "edit_cash" && Admin::isPrivilege(["INVOICES_CASH"]))
                return $this->edit_cash();

            if ($operation == "add_periodic_outgoing" && Admin::isPrivilege(["INVOICES_CASH"]))
                return $this->add_periodic_outgoing();

            if ($operation == "edit_periodic_outgoing" && Admin::isPrivilege(["INVOICES_CASH"]))
                return $this->edit_periodic_outgoing();

            if ($operation == "edit_detail" && Admin::isPrivilege(["INVOICES_OPERATION"]))
                return $this->edit_detail();

            if ($operation == "update_informations" && Admin::isPrivilege(["INVOICES_OPERATION"]))
                return $this->update_informations();

            if ($operation == "delete_taxed_file" && Admin::isPrivilege(["INVOICES_OPERATION"]))
                return $this->delete_taxed_file();

            if ($operation == "apply_operation" && Admin::isPrivilege(["INVOICES_OPERATION"]))
                return $this->apply_operation();

            if ($operation == "delete_cash" && Admin::isPrivilege(["INVOICES_CASH"])) return $this->delete_cash();
            if ($operation == "delete_outgoing" && Admin::isPrivilege(["INVOICES_CASH"]))
                return $this->delete_outgoing();

            if ($operation == "ajax-bills.json") return $this->ajax_bills();

            echo "Not found operation: " . $operation;
        }


        private function pageMain($name = '')
        {
            if ($name == "create") return $this->create_detail();
            elseif (!$name || in_array($name, ['paid', 'unpaid', 'cancelled-refund', 'taxed', 'untaxed', 'overdue', 'upcoming']))
                return $this->bills($name);
            elseif ($name == "detail" && $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0)
                return $this->detail($id);
            elseif ($name == "cash" && Admin::isPrivilege(["INVOICES_CASH"]))
                return $this->cash();
            echo "Not found main: " . $name;
        }


        private function detail($id = 0)
        {
            Helper::Load(["Invoices"]);
            $invoice = Invoices::get($id);
            if (!$invoice) return false;

            $this->takeDatas([
                "dashboard-link",
                "admin-sign-all",
                "language",
                "lang_list",
                "home_link",
                "canonical_link",
                "favicon_link",
                "header_type",
                "header_logo_link",
                "footer_logo_link",
                "meta_color",
                "admin_info",
            ]);

            $token = Crypt::encode(Utility::jencode([
                'user_id' => $invoice["user_id"],
                'id'      => $invoice["id"],
            ]), Config::get("crypt/user"));

            $links = [
                'users'             => $this->AdminCRLink("users"),
                'controller'        => $this->AdminCRLink("invoices-2", ["detail", $id]),
                'select-users.json' => $this->AdminCRLink("orders") . "?operation=user-list.json",
                'share'             => $this->CRLink("share-invoice") . "?token=" . $token,
            ];

            $num = $invoice["number"] ? $invoice["number"] : "#" . $id;
            $this->addData("meta", ['title' => __("admin/invoices/meta-detail-title", ["{id}" => $num])]);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("invoices"),
                'title' => __("admin/invoices/breadcrumb-bills"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/invoices/breadcrumb-detail", ["{id}" => $num]),
            ]);


            $this->addData("breadcrumb", $breadcrumbs);

            Helper::Load("Money");

            $modules = Modules::Load("Payment", false, true);
            $actveMods = Config::get("modules/payment-methods");
            $actveMods[] = "Free";
            $pmethods = [];
            if ($modules) {
                foreach ($modules as $key => $val) {
                    if (in_array($key, $actveMods)) {
                        $pmethods[$key] = $val["lang"]["invoice-name"];
                    }
                }
            }
            $this->addData("pmethods", $pmethods);

            $this->addData("invoice", $invoice);

            $this->addData("items", Invoices::get_items($id));

            if ($invoice["taxed_file"]) {
                $this->addData("taxed_file", $this->AdminCRLink("download-id", ["invoice", $id]));
            }

            if ($invoice["pmethod"] == "BankTransfer" && $invoice["pmethod_msg"])
                $this->addData("banktransfer_info", Utility::jdecode($invoice["pmethod_msg"], true));

            $check_user_data = User::getData($invoice["user_id"], "id,full_name", "array");
            if ($check_user_data) {
                $check_user_data = array_merge($check_user_data, User::getInfo($check_user_data["id"], "company_name"));
                $user_info = $check_user_data;
                $this->addData("user", $user_info);
                $links["user"] = $this->AdminCRLink("users-2", ["detail", $check_user_data["id"]]);
            } else
                $user_info = $invoice["user_data"];

            $this->addData("user_info", $user_info);

            $this->addData("links", $links);

            if (!($invoice["pmethod"] == "BankTransfer" || $invoice["pmethod"] == "Balance")) {
                $case = "CASE WHEN status='paid' THEN 0 ELSE 1 END AS rank";
                $transID = Models::$init->db->select("id,data," . $case)->from("checkouts");
                $transID->where("data", "LIKE", '%"invoice_id":"' . $id . '"%');
                $transID->order_by("rank ASC,cdate DESC");
                $transID->limit(1);
                $transID = $transID->build() ? $transID->getObject() : false;
                if ($transID) {
                    $transData = Utility::jdecode($transID->data, true);
                    $transID = $transID->id;
                    if (isset($transData["pmethod_stored_card"]) && $transData["pmethod_stored_card"]) {
                        $stored_card = $this->model->db->select("ln4")->from("users_stored_cards")->where("id", "=", $transData["pmethod_stored_card"]);
                        $stored_card_ln4 = $stored_card->build() ? $stored_card->getObject()->ln4 : 0;
                        if ($stored_card_ln4) $this->addData("stored_card_ln4", $stored_card_ln4);
                        $this->addData("is_auto_pay", isset($transData["pmethod_by_auto_pay"]) && $transData["pmethod_by_auto_pay"] ? 2 : 1);
                    }
                }
                $this->addData("payment_transaction_id", $transID);
            }

            if ($invoice["pmethod"] != "none" && $invoice["pmethod"] && $invoice["status"] == "paid") {
                Modules::Load("Payment", $invoice["pmethod"]);
                $p_m = $invoice["pmethod"];
                if (class_exists($p_m)) {
                    $p_m_o = new $p_m();
                    if (method_exists($p_m_o, 'refund')) $this->addData("support_refund", true);
                    elseif (method_exists($p_m_o, 'refundInvoice')) $this->addData("support_refund", true);
                }
            }


            $this->addData("countryList", AddressManager::getCountryList(Bootstrap::$lang->clang));

            $this->addData("num", $num);


            $this->view->chose("admin")->render("detail-invoice", $this->data);
        }


        private function create_detail()
        {
            $this->takeDatas([
                "dashboard-link",
                "admin-sign-all",
                "language",
                "lang_list",
                "home_link",
                "canonical_link",
                "favicon_link",
                "header_type",
                "header_logo_link",
                "footer_logo_link",
                "meta_color",
                "admin_info",
            ]);

            $links = [
                'controller'        => $this->AdminCRLink("invoices-1", ["create"]),
                'select-users.json' => $this->AdminCRLink("orders") . "?operation=user-list.json",
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/invoices/meta-create"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("invoices"),
                'title' => __("admin/invoices/breadcrumb-bills"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/invoices/breadcrumb-create"),
            ]);

            $user_id = (int)Filter::init("GET/user_id", "numbers");
            if ($user_id) {
                $user = User::getData($user_id, "id,full_name,lang", "array");
                if ($user) {
                    $user = array_merge($user, User::getInfo($user["id"], "company_name"));
                    $this->addData("user", $user);
                }
            }


            $this->addData("breadcrumb", $breadcrumbs);

            Helper::Load(["Money", "Invoices"]);

            $modules = Modules::Load("Payment", false, true);
            $actveMods = Config::get("modules/payment-methods");
            $pmethods = [];
            if ($modules) {
                foreach ($modules as $key => $val) {
                    if (in_array($key, $actveMods)) {
                        $pmethods[$key] = $val["lang"]["invoice-name"];
                    }
                }
            }
            $this->addData("pmethods", $pmethods);

            $this->view->chose("admin")->render("add-invoice", $this->data);
        }


        private function bills($status = false)
        {
            $this->takeDatas([
                "dashboard-link",
                "admin-sign-all",
                "language",
                "lang_list",
                "home_link",
                "canonical_link",
                "favicon_link",
                "header_type",
                "header_logo_link",
                "footer_logo_link",
                "meta_color",
                "admin_info",
            ]);

            $links = [
                'controller'        => $status ? $this->AdminCRLink("invoices-1", [$status]) : $this->AdminCRLink("invoices"),
                'all'               => $this->AdminCRLink("invoices"),
                'unpaid'            => $this->AdminCRLink("invoices-1", ["unpaid"]),
                'paid'              => $this->AdminCRLink("invoices-1", ["paid"]),
                'cancelled-refund'  => $this->AdminCRLink("invoices-1", ["cancelled-refund"]),
                'taxed'             => $this->AdminCRLink("invoices-1", ["taxed"]),
                'untaxed'           => $this->AdminCRLink("invoices-1", ["untaxed"]),
                'overdue'           => $this->AdminCRLink("invoices-1", ["overdue"]),
                'upcoming'          => $this->AdminCRLink("invoices-1", ["upcoming"]),
                'create'            => $this->AdminCRLink("invoices-1", ["create"]),
                'select-users.json' => $this->AdminCRLink("orders") . "?operation=user-list.json",
            ];
            $links["ajax"] = $links["controller"] . "?operation=ajax-bills.json";

            $this->addData("links", $links);

            $meta = __("admin/invoices/meta-bills");
            if ($status) $meta = __("admin/invoices/meta-bills-" . $status);

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            if ($status) {
                array_push($breadcrumbs, [
                    'link'  => $this->AdminCRLink("invoices"),
                    'title' => __("admin/invoices/breadcrumb-bills"),
                ]);

                array_push($breadcrumbs, [
                    'link'  => false,
                    'title' => __("admin/invoices/breadcrumb-bills-" . $status),
                ]);

            } else
                array_push($breadcrumbs, [
                    'link'  => false,
                    'title' => __("admin/invoices/breadcrumb-bills"),
                ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("status", $status);

            Helper::Load("Money");

            $localc = Config::get("general/currency");

            $search = Filter::init("GET/search", "letters");
            if ($search == "true") {
                $search = true;
                $user_id = (int)Filter::init("GET/user_id", "numbers");
                $word = Filter::init("GET/word", "hclear");
                $start = Filter::init("GET/start", "numbers", "\-");
                $end = Filter::init("GET/end", "numbers", "\-");
                $taxed = (int)Filter::init("GET/taxed", "numbers");
                if (!Validation::isDate($start)) $start = false;
                if (!Validation::isDate($end)) $end = false;
                if ($start && !$end) $end = DateManager::Now("Y-m-d");
                if ($start && $end && ($status == "taxed" || $taxed)) {
                    $totalPaidTaxesAmount = 0;
                    $totalPaidTaxesCount = 0;

                    $getPaidTaxes = $this->model->get_paid_taxes($user_id, $word, $start, $end);
                    if ($getPaidTaxes) {
                        foreach ($getPaidTaxes as $getPaidTax) {
                            $totalPaidTaxesCount += $getPaidTax["total_bill"];
                            $totalPaidTaxesAmount += Money::exChange($getPaidTax["sum_tax"], $getPaidTax["currency"], $localc);
                        }
                        $totalPaidTaxesAmount = Money::formatter_symbol($totalPaidTaxesAmount, $localc);
                    }
                }
            } else {
                $search = false;
                $lMonth = DateManager::last_month("Y-m");
                $nMonth = DateManager::Now("Y-m");
                $lMonth_name = DateManager::month_name(substr($lMonth, 5));
                $nMonth_name = DateManager::month_name(substr($nMonth, 5));

                $lMonth_count = 0;
                $nMonth_count = 0;
                $total_unpaid_count = 0;
                $lMonth_amount = 0;
                $nMonth_amount = 0;
                $total_unpaid_amount = 0;

                $lMonth_taxes = $this->model->get_paid_taxes(false, false, $lMonth);
                $nMonth_taxes = $this->model->get_paid_taxes(false, false, $nMonth);
                $gTotal_unpaid = $this->model->get_total_unpaid($status);
                if ($lMonth_taxes) {
                    foreach ($lMonth_taxes as $lMonth_tax) {
                        $lMonth_count += $lMonth_tax["total_bill"];
                        $lMonth_amount += Money::exChange($lMonth_tax["sum_tax"], $lMonth_tax["currency"], $localc);
                    }
                    $lMonth_amount = Money::formatter_symbol($lMonth_amount, $localc);
                }
                if ($nMonth_taxes) {
                    foreach ($nMonth_taxes as $nMonth_tax) {
                        $nMonth_count += $nMonth_tax["total_bill"];
                        $nMonth_amount += Money::exChange($nMonth_tax["sum_tax"], $nMonth_tax["currency"], $localc);
                    }
                    $nMonth_amount = Money::formatter_symbol($nMonth_amount, $localc);
                }
                if ($gTotal_unpaid) {
                    foreach ($gTotal_unpaid as $row) {
                        $total_unpaid_count += $row["b_count"];
                        $total_unpaid_amount += Money::exChange($row["b_total"], $row["currency"], $localc);
                    }
                    $total_unpaid_amount = Money::formatter_symbol($total_unpaid_amount, $localc);
                }
            }

            $this->addData("search", $search);

            if (isset($user_id) && $user_id) {
                $getUserData = User::getData($user_id, "full_name", "array");
                if ($getUserData) {
                    $this->addData("user_id", $user_id);
                    $getUserData = array_merge($getUserData, User::getInfo($user_id, "company_name"));
                    $user_name = $getUserData["full_name"];
                    if ($getUserData["company_name"]) $user_name .= " - " . $getUserData["company_name"];
                    $this->addData("user_name", $user_name);
                }
            }
            if (isset($word)) $this->addData("word", $word);
            if (isset($start) && $start) $this->addData("start", $start);
            if (isset($end) && $end) $this->addData("end", $end);
            if (isset($taxed)) $this->addData("taxed", $taxed);
            if (isset($totalPaidTaxesAmount) && isset($totalPaidTaxesCount)) {
                $this->addData("total_paid_taxes_amount", $totalPaidTaxesAmount);
                $this->addData("total_paid_taxes_count", $totalPaidTaxesCount);
            }
            if (!$search) {
                $this->addData("last_month_name", $lMonth_name);
                $this->addData("now_month_name", $nMonth_name);
                $this->addData("last_month_taxes_amount", $lMonth_amount);
                $this->addData("now_month_taxes_amount", $nMonth_amount);
                $this->addData("last_month_taxes_count", $lMonth_count);
                $this->addData("now_month_taxes_count", $nMonth_count);

                $this->addData("total_unpaid_invoices_amount", $total_unpaid_amount);
                $this->addData("total_unpaid_invoices_count", $total_unpaid_count);

            }


            $this->view->chose("admin")->render("invoices", $this->data);
        }


        private function cash()
        {
            $param = isset($this->params[1]) ? $this->params[1] : false;

            if ($param) {

                if ($param == "listing.json") return $this->cash_listing_json();
            }

            $this->takeDatas([
                "dashboard-link",
                "admin-sign-all",
                "language",
                "lang_list",
                "home_link",
                "canonical_link",
                "favicon_link",
                "header_type",
                "header_logo_link",
                "footer_logo_link",
                "meta_color",
                "admin_info",
            ]);

            $links = [
                'controller' => $this->AdminCRLink("invoices-1", ["cash"]),
                'ajax'       => $this->AdminCRLink("invoices-2", ["cash", "listing.json"]),
            ];

            $this->addData("links", $links);

            $this->addData("meta", __("admin/invoices/meta-cash"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/invoices/breadcrumb-cash"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);


            Helper::Load("Money");

            $localc = Config::get("general/currency");
            $income_amount = 0;
            $expense_amount = 0;
            $search = Filter::init("GET/search", "letters");
            if ($search == "true") {
                $search = true;
                $word = Filter::init("GET/word", "hclear");
                $start = Filter::init("GET/start", "numbers", "\-");
                $end = Filter::init("GET/end", "numbers", "\-");
                if ($start || ($start && $end)) {
                    $inAmounts = $this->model->get_inex_amount("income", $word, $start, $end);
                    $exAmounts = $this->model->get_inex_amount("expense", $word, $start, $end);
                }
            }

            if (!((isset($start) && $start) || (isset($word) && $word))) {
                $search = false;

                $inAmounts = $this->model->get_inex_amount("income", false, DateManager::Now("Y-m"));
                $exAmounts = $this->model->get_inex_amount("expense", false, DateManager::Now("Y-m"));

            }


            $thisIn_amount = 0;
            $thisEx_amount = 0;
            $thisInAmount = $this->model->get_this_inex_amount("income");
            $thisExAmount = $this->model->get_this_inex_amount("expense");

            if (isset($thisInAmount) && $thisInAmount) foreach ($thisInAmount as $row) $thisIn_amount += Money::exChange($row["sum_total"], $row["currency"], $localc);

            if (isset($thisExAmount) && $thisExAmount) foreach ($thisExAmount as $row) $thisEx_amount += Money::exChange($row["sum_total"], $row["currency"], $localc);

            $thisAmount = ($thisIn_amount - $thisEx_amount);


            $this->addData("search", $search);

            if (isset($word)) $this->addData("word", $word);
            if (isset($start) && $start) $this->addData("start", $start);
            if (isset($end) && $end) $this->addData("end", $end);

            if (isset($inAmounts) && $inAmounts) foreach ($inAmounts as $row) $income_amount += Money::exChange($row["sum_total"], $row["currency"], $localc);

            if (isset($exAmounts) && $exAmounts) foreach ($exAmounts as $row) $expense_amount += Money::exChange($row["sum_total"], $row["currency"], $localc);

            if ($income_amount < 0) $income_amount = 0;
            if ($expense_amount < 0) $expense_amount = 0;

            $income_amount = Money::formatter_symbol($income_amount, $localc);
            $expense_amount = Money::formatter_symbol($expense_amount, $localc);
            $thisAmount = Money::formatter_symbol($thisAmount, $localc);

            $this->addData("thisAmount", $thisAmount);
            $this->addData("income_amount", $income_amount);
            $this->addData("expense_amount", $expense_amount);

            $this->addData("periodicOutgoings", $this->model->get_periodic_outgoings());


            $this->view->chose("admin")->render("cash", $this->data);
        }


        private function cash_listing_json()
        {

            $limit = 10;
            $output = [];
            $aColumns = array();

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

            if (Filter::init("GET/search", "letters") == "true") {
                $word = Filter::init("GET/word", "hclear");
                $startx = Filter::init("GET/start", "numbers", "\-");
                $endx = Filter::init("GET/end", "numbers", "\-");
                if (!Validation::isDate($startx)) $startx = false;
                if (!Validation::isDate($endx)) $endx = false;
                if ($word) $searches["word"] = $word;
                if ($startx) $searches["start"] = $startx;
                if ($endx) $searches["end"] = $endx;
            }

            $filteredList = $this->model->get_cash_list($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_cash_list_total($searches);
            $listTotal = $this->model->get_cash_list_total();

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                Helper::Load("Money", "Invoices");

                $privOperation = Admin::isPrivilege("INVOICES_OPERATION");
                $privDelete = Admin::isPrivilege("INVOICES_DELETE");

                if ($filteredList) {
                    $this->addData("privOperation", $privOperation);
                    $this->addData("privDelete", $privDelete);
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-cash", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        public function main()
        {

            if (Filter::POST("operation")) return $this->operationMain(Filter::init("POST/operation", "route"));
            if (Filter::GET("operation")) return $this->operationMain(Filter::init("GET/operation", "route"));

            $page = isset($this->params[0]) ? $this->params[0] : false;
            return $this->pageMain($page);
        }
    }