<?php
    defined('CORE_FOLDER') or exit('You can not get in here!');

    class Controller extends Controllers
    {
        protected $params, $data = [];
        private $updowngrade_users = [];
        private $contact_types = [
            'registrant',
            'administrative',
            'technical',
            'billing',
        ];


        public function __construct($arg = [])
        {
            parent::__construct();
            $this->params = $arg['params'];


            if (!UserManager::LoginCheck("admin")) {
                Utility::redirect($this->AdminCRLink("sign-in"));
                die();
            }
            Helper::Load(["Admin", "Orders"]);
            $onPage = (!$this->params && Filter::REQUEST("operation") == "user-list.json");
            if (!Admin::isPrivilege(Config::get("privileges/ORDERS")) && !$onPage) die("Unauthorized");
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
            $product_group = Filter::init("POST/product_group");
            $invoiceOpt = Filter::init("POST/invoice-option", "letters");
            if (!$invoiceOpt) $invoiceOpt = "none";
            $pmethod = Filter::init("POST/pmethod", "route");
            $notification = Filter::init("POST/notification", "numbers");
            $total = 0;
            $order_status = Filter::init("POST/order-status", "letters");

            if (!$user_id)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error23"),
                ]));

            $udata = User::getData($user_id, "id,lang,country", "array");

            if (!$udata)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error23"),
                ]));


            if (!$product_group)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error24"),
                ]));


            Helper::Load(["Registrar", "Products", "Money", "Orders", "Invoices"]);

            if ($product_group == "domain") { // Domain START

                $domain = Filter::init("POST/domain", "domain");

                if (Validation::isEmpty($domain))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error22"),
                    ]));


                $parse = Utility::domain_parser($domain);

                if (!$parse)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error20"),
                    ]));

                $name = $parse["host"];
                $tld = $parse["tld"];
                if (!$tld) $tld = "com";

                $domain = $name . "." . $tld;

                $check = Registrar::check($name, $tld);
                $check = $check[$domain];

                $status = $check["status"];

                if ($status == "unknown" && stristr($check["message"], "Inquiry")) $status = "unavailable";
                elseif ($status == "unknown")
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error10", ['{error}' => $check["message"]]),
                    ]));

                $tld_info = Registrar::get_tld($tld, "id,module");

                if (!$tld_info)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error21"),
                    ]));

                $transaction = Filter::init("POST/transaction", "letters");
                $tcode = Filter::init("POST/tcode");
                $module = Filter::init("POST/module", "route");
                $whois_privacy = Filter::init("POST/whois_privacy", "numbers");
                $ns1 = Filter::init("POST/domain_ns1", "domain");
                $ns2 = Filter::init("POST/domain_ns2", "domain");
                $ns3 = Filter::init("POST/domain_ns3", "domain");
                $ns4 = Filter::init("POST/domain_ns4", "domain");
                $period_time = Filter::init("POST/domain_period_time", "numbers");
                $amount = Filter::init("POST/domain_amount", "amount");
                $cid = Filter::init("POST/domain_amount_cid", "numbers");
                $amount = Money::deformatter($amount, $cid);
                $cdate = Filter::init("POST/cdate", "numbers", "\-");
                $duedate = Filter::init("POST/duedate", "numbers", "\-");

                if (!$transaction)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error25"),
                    ]));

                if ((!$module || $module == "none") && (!$ns1 || !$ns2))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error26"),
                    ]));

                $item = [
                    'name'         => $domain,
                    'amount'       => $amount,
                    'total_amount' => $amount,
                    'options'      => [],
                ];

                $options = [
                    'category'    => __("website/osteps/category-domain", false, $udata["lang"]),
                    'event'       => "DomainNameRegisterOrder",
                    'type'        => "domain",
                    'id'          => $tld_info["id"],
                    'period'      => "year",
                    'period_time' => $period_time,
                    'sld'         => $name,
                    'tld'         => $tld,
                    'dns'         => [
                        'ns1' => $ns1,
                        'ns2' => $ns2,
                        'ns3' => $ns3,
                        'ns4' => $ns4,
                    ],
                ];

                $options["transaction"] = $transaction;

                if (!$transaction || $transaction == "none") {
                    if ($cdate) $options["cdate"] = $cdate . " 00:00:00";
                    if ($cdate) $options["renewaldate"] = $cdate . " 00:00:00";
                    if ($duedate) $options["duedate"] = $duedate . " 00:00:00";
                    $options["module"] = $module;
                } else {
                    if ($tcode) {
                        $options["event"] = "DomainNameTransferRegisterOrder";
                        $options["tcode"] = $tcode;
                    }
                }


                if ((!$transaction || $transaction == "none") && $module != "none") {
                    $mdata = Modules::Load("Registrars", $module);
                    if ($mdata) {
                        $moduleClass = new $module();
                        $getDomainInfo = $moduleClass->get_info([
                            'domain' => $domain,
                            'name'   => $name,
                            'sld'    => $name,
                            'tld'    => $tld,
                        ]);
                        if ($getDomainInfo) {

                            if (!isset($getDomainInfo["whois"]["registrant"])) {
                                $whois_new = [];
                                foreach ($this->contact_types as $ct) $whois_new[$ct] = $getDomainInfo["whois"];
                                $getDomainInfo["whois"] = $whois_new;
                            }

                            $options["cdate"] = $getDomainInfo["creation_time"];
                            $options["renewaldate"] = $getDomainInfo["creation_time"];
                            $options["duedate"] = $getDomainInfo["end_time"];
                            $options["transferlock"] = $getDomainInfo["transferlock"];

                            if (isset($getDomainInfo["whois_privacy"])) {
                                $options["wprivacy"] = true;
                                $options["whois_privacy"] = $getDomainInfo["whois_privacy"]["status"] == "enable";
                                if (isset($getDomainInfo["whois_privacy"]["end_time"]))
                                    $options["whois_privacy_endtime"] = $getDomainInfo["whois_privacy"]["end_time"];
                            }

                            if (isset($getDomainInfo["ns1"])) $options["dns"]["ns1"] = $getDomainInfo["ns1"];
                            if (isset($getDomainInfo["ns2"])) $options["dns"]["ns2"] = $getDomainInfo["ns2"];
                            if (isset($getDomainInfo["ns3"])) $options["dns"]["ns3"] = $getDomainInfo["ns3"];
                            if (isset($getDomainInfo["ns4"])) $options["dns"]["ns4"] = $getDomainInfo["ns4"];

                            if (isset($getDomainInfo["whois"])) $options["whois"] = $getDomainInfo["whois"];
                            if (isset($getDomainInfo["cns"])) $options["cns"] = $getDomainInfo["cns"];

                        } else
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => __("admin/orders/error32"),
                            ]));
                    }
                }

                if ($whois_privacy || isset($options["wprivacy"])) {
                    $options["wprivacy"] = true;
                    $whidden_amount = Config::get("options/domain-whois-privacy/amount");
                    $whidden_cid = Config::get("options/domain-whois-privacy/cid");

                    if ($tld_info["module"] != "none") {
                        $mdata = Modules::Load("Registrars", $tld_info["module"]);
                        if ($mdata) {
                            $whidden_amount = $mdata["config"]["settings"]["whidden-amount"];
                            $whidden_cid = $mdata["config"]["settings"]["whidden-currency"];
                        }
                    }

                    $whidden_price = Money::exChange($whidden_amount, $whidden_cid, $cid);

                    if ($whidden_price) $total += $whidden_price;
                }

                if (!$transaction || $transaction == "none") $options["established"] = true;


                $item["options"] = $options;


                $total += $amount;

            } // Domain END

            if ($product_group == "hosting") { // Hosting product START
                $product_id = Filter::init("POST/hosting_pid", "numbers");

                if (!$product_id)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error28"),
                    ]));

                $product = Products::get("hosting", $product_id, Config::get("general/local"));

                $domain = Filter::init("POST/hosting_domain", "domain");
                $period = Filter::init("POST/hosting_period", "letters");
                $period_time = Filter::init("POST/hosting_period_time", "numbers");
                $amount = Filter::init("POST/hosting_amount", "amount");
                $cid = Filter::init("POST/hosting_amount_cid", "numbers");
                $amount = Money::deformatter($amount, $cid);
                $adds = Filter::POST("hosting_addons");

                $selection = (int)Filter::init("POST/software_selected_price", "numbers");

                if (isset($product["price"][$selection])) $selection = $product["price"][$selection];
                else $selection = [];

                $hide_domain = $product["options"]["hide_domain"] ?? false;


                if (!$hide_domain && !$domain)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error29"),
                    ]));


                $item = [
                    'name'         => $product["title"],
                    'amount'       => $amount,
                    'total_amount' => $amount,
                    'options'      => [],
                ];

                $options = [
                    'event'       => "HostingOrder",
                    'type'        => "hosting",
                    'id'          => $product["id"],
                    'period'      => $period,
                    'period_time' => $period_time,
                    'selection'   => $selection,
                ];

                if ($domain) $options['domain'] = $domain;


                $item["options"] = $options;

                $total += $amount;

            } // Hosting product END

            if ($product_group == "server") { // Server product START
                $product_id = Filter::init("POST/server_pid", "numbers");

                if (!$product_id)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error28"),
                    ]));

                $hostname = Filter::init("POST/hostname", "domain");
                $ns1 = Filter::init("POST/server_ns1", "domain");
                $ns2 = Filter::init("POST/server_ns2", "domain");
                $ns3 = Filter::init("POST/server_ns3", "domain");
                $ns4 = Filter::init("POST/server_ns4", "domain");
                $product = Products::get("server", $product_id, Config::get("general/local"));
                $period = Filter::init("POST/server_period", "letters");
                $period_time = Filter::init("POST/server_period_time", "numbers");
                $amount = Filter::init("POST/server_amount", "amount");
                $cid = Filter::init("POST/server_amount_cid", "numbers");
                $amount = Money::deformatter($amount, $cid);
                $adds = Filter::POST("server_addons");

                $selection = (int)Filter::init("POST/server_selected_price", "numbers");

                if (isset($product["price"][$selection])) $selection = $product["price"][$selection];
                else $selection = [];

                $item = [
                    'name'         => $product["title"],
                    'amount'       => $amount,
                    'total_amount' => $amount,
                    'options'      => [],
                ];

                $options = [
                    'hostname'    => $hostname,
                    'ns1'         => $ns1,
                    'ns2'         => $ns2,
                    'ns3'         => $ns3,
                    'ns4'         => $ns4,
                    'password'    => null,
                    'event'       => "ServerOrder",
                    'type'        => "server",
                    'id'          => $product["id"],
                    'period'      => $period,
                    'period_time' => $period_time,
                    'selection'   => $selection,
                ];

                $item["options"] = $options;


                $total += $amount;

            } // Server product END

            if ($product_group == "software") { // Hosting product START
                $product_id = Filter::init("POST/software_pid", "numbers");

                if (!$product_id)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error28"),
                    ]));

                $product = Products::get("software", $product_id, Config::get("general/local"));
                $domain = Filter::init("POST/software_domain", "domain");
                $period = Filter::init("POST/software_period", "letters");
                $period_time = Filter::init("POST/software_period_time", "numbers");
                $amount = Filter::init("POST/software_amount", "amount");
                $cid = Filter::init("POST/software_amount_cid", "numbers");
                $amount = Money::deformatter($amount, $cid);
                $adds = Filter::POST("software_addons");

                $selection = (int)Filter::init("POST/software_selected_price", "numbers");

                if (isset($product["price"][$selection])) $selection = $product["price"][$selection];
                else $selection = [];


                $item = [
                    'name'         => $product["title"],
                    'amount'       => $amount,
                    'total_amount' => $amount,
                    'options'      => [],
                ];

                $options = [
                    'event'       => "SoftwareOrder",
                    'type'        => "software",
                    'id'          => $product["id"],
                    'period'      => $period,
                    'period_time' => $period_time,
                    'selection'   => $selection,
                ];

                if ($domain) $options["domain"] = $domain;

                $item["options"] = $options;

                $total += $amount;

            } // Software product END

            if ($product_group == "sms") { // SMS product START
                $product_id = Filter::init("POST/sms_pid", "numbers");

                if (!$product_id)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error28"),
                    ]));

                $product = Products::get("sms", $product_id, Config::get("general/local"));
                $amount = Filter::init("POST/sms_amount", "amount");
                $cid = Filter::init("POST/sms_amount_cid", "numbers");
                $amount = Money::deformatter($amount, $cid);

                $full_name = Filter::init("POST/sms_name", "hclear");
                $full_name = Utility::substr($full_name, 0, 255);
                $full_name = Utility::ucfirst_space($full_name, ___("package/charset-code"));

                $identity = Filter::init("POST/sms_identity", "identity");
                $birthday = Filter::init("POST/sms_birthday", "numbers", "\/");

                if ($birthday) {
                    $birthday = str_replace("/", "-", $birthday);
                    $birthday = DateManager::format("Y-m-d", $birthday);
                }

                if (Validation::isEmpty($full_name))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='sms_name']",
                        'message' => __("website/sign/up-submit-empty-full_name"),
                    ]));

                if (Validation::isEmpty($birthday))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='sms_birthday']",
                        'message' => __("website/sign/up-birthday-empty"),
                    ]));

                if (Validation::isEmpty($identity))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='sms_identity']",
                        'message' => __("website/sign/empty-identity-number"),
                    ]));

                $check = Validation::isidentity($identity, $full_name, $birthday);
                if (!$check)
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='sms_identity']",
                        'message' => __("website/sign/up-submit-invalid-identity"),
                    ]));


                $item = [
                    'name'         => $product["title"],
                    'amount'       => $amount,
                    'total_amount' => $amount,
                    'options'      => [],
                ];

                $options = [
                    'event'       => "SmsProductOrder",
                    'fields'      => [
                        'name'     => $full_name,
                        'birthday' => $birthday,
                        'identity' => $identity,
                    ],
                    'type'        => "sms",
                    'id'          => $product["id"],
                    'period'      => "none",
                    'period_time' => 1,
                ];

                $item["options"] = $options;

                $total += $amount;

            } // SMS product END

            if ($product_group == "special") { // Special product START
                $product_id = Filter::init("POST/special_pid", "numbers");

                if (!$product_id)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error28"),
                    ]));

                $product = Products::get("special", $product_id, Config::get("general/local"));
                $domain = Filter::init("POST/special_domain", "domain");
                $period = Filter::init("POST/special_period", "letters");
                $period_time = Filter::init("POST/special_period_time", "numbers");
                $amount = Filter::init("POST/special_amount", "amount");
                $cid = Filter::init("POST/special_amount_cid", "numbers");
                $amount = Money::deformatter($amount, $cid);
                $adds = Filter::POST("special_addons");
                $selection = (int)Filter::init("POST/special_selected_price", "numbers");

                if (isset($product["price"][$selection])) $selection = $product["price"][$selection];
                else $selection = [];


                $item = [
                    'name'         => $product["title"],
                    'amount'       => $amount,
                    'total_amount' => $amount,
                    'options'      => [],
                ];

                $options = [
                    'event'       => "SpecialProductOrder",
                    'type'        => "special",
                    'id'          => $product["id"],
                    'period'      => $period,
                    'period_time' => $period_time,
                    'selection'   => $selection,
                ];

                if (isset($product["options"]["show_domain"]) && $product["options"]["show_domain"]) {
                    if (!$domain)
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("admin/orders/error29"),
                        ]));
                    $options["domain"] = $domain;
                }

                $total += $amount;

                $item["options"] = $options;
            } // Special product END


            $addons = [];
            if (isset($adds) && $adds && is_array($adds)) {
                foreach ($adds as $ad_id => $ad_va) {
                    if ($ad_va != "none") {
                        $getAddon = Products::addon($ad_id, Config::get("general/local"));
                        $options = $getAddon["options"];
                        if ($options) {

                            foreach ($options as $k => $opt) {
                                if ($opt["id"] != $ad_va) continue;
                                $exCh = Money::exChange($opt["amount"], $opt["cid"], $cid);
                                if ($exCh) $total += $exCh;
                                $addons[$ad_id] = $ad_va;
                            }
                        }

                    }
                }

                if (isset($selection) && $selection) {
                    if ($selection["setup"] > 0.00) {
                        $setup_fee = Money::exChange($selection["setup"], $selection["cid"], $cid);
                        $total += $setup_fee;
                    }
                }

            }

            if ($addons) $item["options"]["addons"] = $addons;

            if (isset($selection["setup"]) && $selection["setup"] > 0.00) {
                $setup_fee = Money::exChange($selection["setup"], $selection["cid"], $cid);
                $total += $setup_fee;
                $item["total_amount"] += $setup_fee;
            }

            if(Invoices::getTaxationType() == "inclusive") {
                $tax_rate   = Invoices::getTaxRate($udata["country"],0,$user_id);
                $total      -= Money::get_inclusive_tax_amount($total,$tax_rate);
                $item["amount"] -= Money::get_inclusive_tax_amount($item["amount"],$tax_rate);
            }


            $item["total_amount"] = $total;

            if ($invoiceOpt == "none")
                define("CREATE_ORDER_INVOICE_DELETED", true);

            $odata = [
                'user_id' => $user_id,
                'status'  => $invoiceOpt,
                'pmethod' => $pmethod,
                'amount'  => $total,
                'cid'     => $cid,
            ];

            $item["process"] = true;
            $item["options"]["creation_by_admin"] = true;
            $item["taxexempt"] = isset($product["taxexempt"]) ? $product["taxexempt"] : 0;

            if ($invoiceOpt == "none") $odata["status"] = "paid";

            if (isset($odata) && isset($item)) $invoice = Invoices::generate_create_order($odata, [$item]);
            else $invoice = false;


            if (!$invoice) {
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
                    'message' => __("admin/orders/error27"),
                ]));
            }

            if ($invoiceOpt == "none") Invoices::delete($invoice["id"]);

            if ($notification) {
                Helper::Load(["Notification"]);
                if ($invoiceOpt == "paid") Notification::invoice_has_been_approved($invoice);
                if ($invoiceOpt == "unpaid") Notification::invoice_created($invoice);
            }

            if ($order_status != "active") Orders::MakeOperation($order_status, $invoice["order_id"], false, $notification == 1);

            $redirect = $this->AdminCRLink("orders-2", ["detail", $invoice["order_id"]]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/orders/success25"),
                'redirect' => $redirect,
            ]);

        }

        private function create_domain_order_detail_module()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $domain = Filter::init("POST/domain", "domain");
            $module = Filter::init("POST/module", "route");

            if (Validation::isEmpty($domain))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error22"),
                ]));

            $parse = Utility::domain_parser($domain);

            if (!$parse)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error20"),
                ]));

            $name = $parse["host"];
            $tld = $parse["tld"];
            if (!$tld) $tld = "com";


            Helper::Load(["Registrar", "Products", "Money", "Orders", "Invoices"]);

            $mdata = Modules::Load("Registrars", $module);
            if ($mdata) {
                $moduleClass = new $module();
                $getDomainInfo = $moduleClass->get_info([
                    'domain' => $domain,
                    'sld'    => $name,
                    'tld'    => $tld,
                ]);
                if ($getDomainInfo) {

                    if (!isset($getDomainInfo["whois"]["registrant"])) {
                        $whois_new = [];
                        foreach ($this->contact_types as $ct) $whois_new[$ct] = $getDomainInfo["whois"];
                        $getDomainInfo["whois"] = $whois_new;
                    }

                    $return = [];

                    $return["cdate"] = DateManager::format("Y-m-d", $getDomainInfo["creation_time"]);
                    $return["duedate"] = DateManager::format("Y-m-d", $getDomainInfo["end_time"]);

                    if (isset($getDomainInfo["whois_privacy"])) {
                        $return["whois_privacy"] = $getDomainInfo["whois_privacy"]["status"] == "enable";
                        if (isset($getDomainInfo["whois_privacy"]["end_time"]))
                            $return["whois_privacy_endtime"] = $getDomainInfo["whois_privacy"]["end_time"];
                    }

                    if (isset($getDomainInfo["ns1"])) $return["ns1"] = $getDomainInfo["ns1"];
                    if (isset($getDomainInfo["ns2"])) $return["ns2"] = $getDomainInfo["ns2"];
                    if (isset($getDomainInfo["ns3"])) $return["ns3"] = $getDomainInfo["ns3"];
                    if (isset($getDomainInfo["ns4"])) $return["ns4"] = $getDomainInfo["ns4"];

                    $return["status"] = "successful";

                    echo Utility::jencode($return);

                } else
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error32"),
                    ]));
            }

        }


        private function get_create_product_info()
        {
            $this->takeDatas("language");
            $type = Filter::init("POST/type", "letters");
            $id = (int)Filter::init("POST/id", "numbers");
            $user_id = (int)Filter::init("POST/user_id", "numbers");
            $locall = Config::get("general/local");
            $localc = Config::get("general/currency");

            $ulang = User::getData($user_id, "lang")->lang;
            if ($ulang) $locall = $ulang;

            Helper::Load(["Products", "Money"]);

            $product = Products::get($type, $id, $locall,'');
            if (!$product) return false;

            $output = [];

            if ($type == "hosting" || $type == "server" || $type == "special") {
                $addss = Products::addons($product["addons"], $locall);
                $fees = Products::get_prices("periodicals", "products", $id, $locall);

            } elseif ($type == "software") {
                $addss = Products::addons($product["addons"], $locall);
                $fees = Products::get_prices("periodicals", "softwares", $id, $locall);
            } elseif ($type == "sms") {
                $fees = Products::get_prices("sale", "products", $id, $locall);
                $addss = false;
            }


            if ($user_id) {
                $udata = User::getInfo($user_id, ["dealership"]);
                if ($udata["dealership"]) {
                    $dealership = Utility::jdecode($udata["dealership"], true);
                    if ($dealership && isset($dealership["status"]) && $dealership["status"] == "active") {
                        $discounts = $dealership["discounts"];
                        if ($type == "special" && isset($discounts["special/" . $product["type_id"]]))
                            $discount_rate = $discounts["special/" . $product["type_id"]];
                        elseif (isset($discounts[$type])) $discount_rate = $discounts[$type];
                    }
                }
            }


            if ($fees) {
                $prices = [];
                foreach ($fees as $fee) {
                    $amount = Money::exChange($fee["amount"], $fee["cid"], $localc);
                    $setup = 0;
                    if (isset($fee["setup"]) && $fee["setup"] > 0.00)
                        $setup = Money::exChange($fee["setup"], $fee["cid"], $localc);


                    if (isset($discount_rate) && $discount_rate) {
                        $discount_amount = Money::get_discount_amount($amount, $discount_rate);
                        $amount -= $discount_amount;
                    }


                    $format = Money::formatter($amount, $localc);
                    $format2 = Money::formatter($setup, $localc);
                    $format3 = Money::formatter($amount + $setup, $localc);
                    $price2 = '';


                    if ($amount) {
                        $period = View::period($fee["time"], $fee["period"]);
                        $price = Money::formatter_symbol($amount, $localc);
                        if ($setup > 0.00)
                            $price2 = Money::formatter_symbol($setup, $localc);
                    } else {
                        $price = ___("needs/free-amount");
                        $period = null;
                    }
                    $text = $period ? $price . " (" . $period . ")" : $price;
                    if ($price2)
                        $text .= ' + ' . __("website/osteps/setup-fee") . ': ' . $price2;
                    $prices[] = [
                        'text'   => $text,
                        'amount' => $format,
                        'setup'  => $format2,
                        'total'  => $format3,
                        'period' => $fee["period"],
                        'time'   => $fee["time"] ? $fee["time"] : '',
                    ];
                }
                $output["prices"] = $prices;
            }

            if ($addss) {
                $addons = [];
                foreach ($addss as $ad) {
                    $opts = $ad["options"] ?? [];
                    if (!$opts) $opts = [];
                    $options = [];
                    $product_link = Products::get($ad["product_type_link"], $ad["product_id_link"]);

                    if ($product_link && ($product_link["price"] ?? [])) {
                        foreach ($product_link["price"] as $p_row) {
                            $opts[] = [
                                'id'          => $p_row["id"],
                                'name'        => ___("needs/iwwant"),
                                'period'      => $p_row["period"],
                                'period_time' => $p_row["time"],
                                'amount'      => $p_row["amount"],
                                'cid'         => $p_row["cid"],
                            ];

                        }
                    }

                    if ($opts) {
                        foreach ($opts as $opt) {
                            $amount = Money::exChange($opt["amount"], $opt["cid"], $localc);
                            $format = Money::formatter($amount, $localc);
                            if ($amount) {
                                $period = View::period($opt["period_time"], $opt["period"]);
                                $price = Money::formatter_symbol($amount, $localc);
                            } else {
                                $price = ___("needs/free-amount");
                                $period = null;
                            }
                            $options[] = [
                                'id'     => $opt["id"],
                                'name'   => $opt["name"],
                                'text'   => $period ? $opt["name"] . " - " . $price . " (" . $period . ")" : $opt["name"] . " - " . $price,
                                'period' => $opt["period"],
                                'time'   => $opt["period_time"],
                                'amount' => $format,
                            ];
                        }
                    }

                    $addons[] = [
                        'id'         => $ad["id"],
                        'name'       => $ad["name"],
                        'compulsory' => isset($ad["properties"]["compulsory"]) ? $ad["properties"]["compulsory"] : false,
                        'options'    => $options,
                    ];
                }
                $output["addons"] = $addons;
            }

            if ($type == "special" && isset($product["options"]["show_domain"]))
                $output["show_domain"] = $product["options"]["show_domain"] == 1;


            echo Utility::jencode($output);
        }


        private function get_order($id = 0)
        {
            $id = (int)$id;
            $order = $this->model->get_order($id);
            if ($order) {
                $order["options"] = $order["options"] ? Utility::jdecode($order["options"], true) : [];
            }
            return $order;
        }


        private function updown_products($grade = 'up', $order = [], $product = [], $remaining_amount = false)
        {
            $output = [
                'categories' => [],
                'products'   => [],
                'prices'     => [],
            ];

            $onlyonsameperiods = Config::get("options/product-upgrade/only-on-same-periods");

            if (!$product)
                $product = [
                    'id'      => $order["product_id"],
                    'type'    => $order["type"],
                    'type_id' => $order["type_id"],
                    'title'   => $order["name"],
                ];

            $type = $product["type"];

            $type_id = $product["type_id"];
            $categories = $this->model->get_select_categories($type, $type_id, '');
            if (!$categories) $categories = [];

            if ($type == "special") {
                $mainCategory = Products::getCategory($type_id, false, 't1.id,t1.parent,t1.options,t2.title,t2.options AS optionsl');
                $mainCategory["title"] = Bootstrap::$lang->get("needs/uncategorized");
                $categories = array_merge([$mainCategory], $categories);
            }


            if ($type == "software") {
                $pricesow = "softwares";
            } else {
                $pricesow = "products";
            }

            $order_amount = (float)$order["amount"];

            $duedate = DateManager::format("Y-m-d", $order["duedate"]);
            $duedate_time = DateManager::strtotime($duedate);
            $now_time = DateManager::strtotime();

            if (!class_exists("Invoices")) Helper::Load(["Invoices"]);

            $country = 0;
            $city = 0;
            $tax_rate = 0;
            $taxation_type = Invoices::getTaxationType();

            if (!isset($this->updowngrade_users[$order["owner_id"]])) {
                $udata = User::getData($order["owner_id"], "id,country", "array");
                $udata = array_merge($udata, User::getInfo($udata["id"], ["taxation"]));
                if ($getAddress = AddressManager::getAddress(0, $udata["id"])) $udata["address"] = $getAddress;
                $this->updowngrade_users[$udata["id"]] = $udata;
            }

            $udata = $this->updowngrade_users[$order["owner_id"]];
            $getAddress = isset($udata["address"]) ? $udata["address"] : [];

            $country = $udata["country"];
            if ($getAddress) {
                $country = $getAddress["country_id"];
                if (isset($getAddress["city_id"])) $city = $getAddress["city_id"];
                else
                    $city = $getAddress["city"];
            }

            $taxation = Invoices::getTaxation($country, $udata["taxation"]);
            $isLocal = Invoices::isLocal($country, $udata["id"]);
            if ($isLocal) $tax_rate = Invoices::getTaxRate($country, $city, $udata["id"]);


            if ($categories) {
                foreach ($categories as $category) {
                    $getps = $this->model->get_products_with_category($type, $category["id"]);
                    if ($getps) {
                        foreach ($getps as $p) {
                            //$mdata  = isset($p["module_data"]) && $p["module_data"] ? Utility::jdecode($p["module_data"],true) : [];

                            if (!isset($products[$p["id"]]) && $p["id"] != $product["id"]) {
                                $prices = Products::get_prices("periodicals", $pricesow, $p["id"]);
                                if ($prices) {
                                    $pprices = [];
                                    foreach ($prices as $price) {
                                        $price_ = $price["amount"];
                                        if ($price["setup"] > 0.00) $price_ += $price["setup"];
                                        $exch = Money::exChange($price_, $price["cid"], $order["amount_cid"]);

                                        if ($onlyonsameperiods)
                                            $same_periods = $price["period"] == $order["period"] && $price["time"] == $order["period_time"];
                                        else
                                            $same_periods = true;


                                        if ($price["period"] != "none" && $same_periods && (($grade == "up" && $exch >= $order_amount) || ($grade == "down" && $exch <= $order_amount))) {
                                            $price["amount"] = $exch;
                                            $price["cid"] = $order["amount_cid"];
                                            if ($grade == "up") {
                                                $price["payable"] = ($exch - $remaining_amount);

                                                if ($taxation_type == "inclusive") {
                                                    $price["payable"] -= Money::get_inclusive_tax_amount($price["payable"], $tax_rate);
                                                }

                                                $price["tax"] = $taxation ? Money::get_tax_amount($price["payable"], $tax_rate) : 0;

                                                $price["taxed_payable"] = $price["payable"] + $price["tax"];
                                            } else {
                                                if ($duedate_time > $now_time)
                                                    $price["difference"] = abs($exch - $remaining_amount);
                                                else
                                                    $price["difference"] = 0;
                                            }
                                            $pprices[$price["id"]] = $price;
                                        }
                                    }
                                    if ($pprices) {
                                        $output["products"][$category["id"]][$p["id"]] = $p;
                                        $output["prices"][$p["id"]] = $pprices;
                                    }
                                }
                            }
                        }
                    }
                    if (isset($output["products"][$category["id"]]))
                        $output["categories"][$category["id"]] = $category;
                }
            }
            return $output;
        }


        private function check_domain_availability()
        {

            $this->takeDatas("language");

            $domain = Filter::init("POST/domain", "domain");
            $user_id = (int)Filter::init("POST/user_id", "numbers");

            if (Validation::isEmpty($domain))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error22"),
                ]));

            $name = $domain;
            $tld = false;

            if (strlen($domain) > 2) {
                $parse = Utility::domain_parser($domain);

                if (!$parse || $parse["host"] == '')
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error20"),
                    ]));
                $name = $parse["host"];
                $tld = $parse["tld"];
            }


            if (!$tld) $tld = "com";

            $domain = $name . "." . $tld;

            if ($user_id) {
                $udata = User::getInfo($user_id, ["dealership"]);
                if ($udata["dealership"]) {
                    $dealership = Utility::jdecode($udata["dealership"], true);
                    if ($dealership && isset($dealership["status"]) && $dealership["status"] == "active") {
                        $discounts = $dealership["discounts"];
                        $discount_rate = $discounts["domain"] ?? false;
                    }
                }
            }

            Helper::Load(["Registrar", "Products", "Money"]);

            $check = Registrar::check($name, $tld);
            $check = $check[$domain];

            $status = $check["status"];

            $result = [];


            if ($status == "unknown" && stristr($check["message"], "Inquiry")) $status = "unavailable";
            elseif ($status == "unknown")
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error10", ['{error}' => $check["message"]]),
                ]));


            $tld_info = Registrar::get_tld($tld, "id,module,promo_status,promo_duedate,promo_register_price,promo_transfer_price");

            if (!$tld_info)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error21"),
                ]));

            $localc = Config::get("general/currency");
            $fees = [];
            $register = Products::get_price("register", "tld", $tld_info["id"]);
            $transfer = Products::get_price("transfer", "tld", $tld_info["id"]);

            if ($tld_info["promo_status"] && (substr($tld_info["promo_duedate"], 0, 4) == '1881' || DateManager::strtotime($tld_info["promo_duedate"] . " 23:59:59") > DateManager::strtotime()) && $tld_info["promo_register_price"] > 0)
                $register["amount"] = $tld_info["promo_register_price"];

            if ($tld_info["promo_status"] && (substr($tld_info["promo_duedate"], 0, 4) == '1881' || DateManager::strtotime($tld_info["promo_duedate"] . " 23:59:59") > DateManager::strtotime()) && $tld_info["promo_transfer_price"] > 0)
                $transfer["amount"] = $tld_info["promo_transfer_price"];


            $result["status"] = $status;

            for ($i = 0; $i <= 9; $i++) {
                $year = $i + 1;
                $register_amount = Money::exChange($register["amount"], $register["cid"], $localc) * $year;
                $transfer_amount = Money::exChange($transfer["amount"], $transfer["cid"], $localc) * $year;

                if (isset($discount_rate) && $discount_rate) {
                    $discount_amount = Money::get_discount_amount($register_amount, $discount_rate);
                    $register_amount -= $discount_amount;

                    $discount_amount = Money::get_discount_amount($transfer_amount, $discount_rate);
                    $transfer_amount -= $discount_amount;
                }

                $fees["register"][$i]["format"] = Money::formatter_symbol($register_amount, $localc);
                $fees["register"][$i]["amount"] = Money::formatter($register_amount, $localc);
                $fees["transfer"][$i]["format"] = Money::formatter_symbol($transfer_amount, $localc);
                $fees["transfer"][$i]["amount"] = Money::formatter($transfer_amount, $localc);
            }

            $result["fees"] = $fees;

            echo Utility::jencode($result);

        }


        private function sms_origins($order_id = 0)
        {
            $data = $this->model->get_origins($order_id);
            if ($data) {
                $keys = array_keys($data);
                $size = sizeof($keys) - 1;
                for ($i = 0; $i <= $size; $i++) {
                    $ori = $data[$keys[$i]];
                    $attachmentsx = $ori["attachments"] ? Utility::jdecode($ori["attachments"], true) : [];
                    $attachments = [];
                    if ($attachmentsx) {
                        foreach ($attachmentsx as $attachment) {
                            $link = Utility::link_determiner($attachment["file_path"], RESOURCE_DIR . "uploads" . DS . "attachments" . DS, false);
                            $item = $attachment;
                            $item["link"] = $link;
                            $attachments[] = $item;
                        }
                    }
                    $data[$keys[$i]]["attachments"] = $attachments;
                }

            }
            return $data;
        }


        private function ajax_list()
        {
            $limit = 10;
            $output = [];
            $aColumns = [
                '',
                '',
                '',
                '',
                '',
                't1.renewaldate',
                '',
                '',
                '',
            ];

            $start = Filter::init("GET/iDisplayStart", "numbers");
            if (!Validation::isInt($start) || $start < 0) $start = 0;
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

            $from = Filter::init("GET/from", "letters");

            if ($from == "user")
                $searches["user_id"] = (int)Filter::init("GET/id", "numbers");

            if ($from == "product") {
                $p_type = Filter::init("GET/type", "letters");
                $p_id = (int)Filter::init("GET/id", "numbers");

                if (!$p_type) $p_type = 'hosting';
                $searches["product_type"] = $p_type;
                $searches["product_id"] = $p_id;
            }


            if ($l_type = Filter::init("GET/l_type", "letters_numbers")) $searches["l_type"] = $l_type;


            if (!Validation::isEmpty(Filter::GET("sSearch"))) $searches['word'] = Filter::init("GET/sSearch", "hclear");

            $status = isset($this->params[0]) ? $this->params[0] : false;
            $group = substr(Filter::init("GET/group", "route"), 0, 16);

            if ($status && $status != "active" && $status != "suspended" && $status != "inprocess" && $status != "cancelled" && $status != "overdue") die();


            $filteredList = $this->model->get_orders($status, $group, $searches, $orders, $start, $end);
            $filterTotal = $this->model->get_orders_total($status, $group, $searches);
            if (($from == "user" || $from == "product") && isset($searches["word"])) unset($searches["word"]);
            $listTotal = $this->model->get_orders_total($status, $group, $searches);

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                Helper::Load("Money");

                $privOperation = Admin::isPrivilege("ORDERS_OPERATION");
                $privDelete = Admin::isPrivilege("ORDERS_DELETE");

                $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                $situations = $situations["orders"];

                if ($filteredList) {
                    $this->addData("from", $from);
                    $this->addData("privOperation", $privOperation);
                    $this->addData("privDelete", $privDelete);
                    $this->addData("situations", $situations);
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-orders", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function ajax_updowngrades()
        {
            $limit = 10;
            $output = [];
            $aColumns = array();

            $start = Filter::init("GET/iDisplayStart", "numbers");
            if (!Validation::isInt($start) || $start < 0) $start = 0;
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


            $filteredList = $this->model->get_updowngrades($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_updowngrades_total($searches);
            $listTotal = $this->model->get_updowngrades_total();

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                Helper::Load(["Orders", "Products", "Money", "Invoices"]);

                $privOperation = Admin::isPrivilege("ORDERS_OPERATION");
                $privDelete = Admin::isPrivilege("ORDERS_DELETE");

                $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                $situations = $situations["updowngrades"];

                if ($filteredList) {
                    $this->addData("privOperation", $privOperation);
                    $this->addData("privDelete", $privDelete);
                    $this->addData("situations", $situations);
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-orders-updowngrades", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function ajax_cancellation_requests()
        {
            $limit = 10;
            $output = [];
            $aColumns = array();

            $start = Filter::init("GET/iDisplayStart", "numbers");
            if (!Validation::isInt($start) || $start < 0) $start = 0;
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

            $params = [
                'type'  => "operation",
                'owner' => "order",
                'name'  => "cancelled-product-request",
            ];

            Helper::Load(["Orders"]);

            $filteredList = $this->model->get_events($params, $searches, $orders, $start, $end);
            $filterTotal = $this->model->get_events_total($params, $searches);
            $listTotal = $this->model->get_events_total($params);

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                Helper::Load(["Orders", "Products", "Money"]);

                $privOperation = Admin::isPrivilege("ORDERS_OPERATION");
                $privDelete = Admin::isPrivilege("ORDERS_DELETE");

                $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                $situations = $situations["cancellation"];

                if ($filteredList) {
                    $this->addData("privOperation", $privOperation);
                    $this->addData("privDelete", $privDelete);
                    $this->addData("situations", $situations);
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-orders-cancellation-requests", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function ajax_addons($id = 0)
        {
            $limit = 10;
            $output = [];
            $aColumns = array();

            $start = Filter::init("GET/iDisplayStart", "numbers");
            if (!Validation::isInt($start) || $start < 0) $start = 0;
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
            $id = (int)$id;
            if ($id) $searches["order_id"] = $id;

            if ($l_type = Filter::init("GET/l_type", "letters_numbers")) $searches["l_type"] = $l_type;

            $filteredList = $this->model->get_addons($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_addons_total($searches);
            if (isset($searches["word"])) unset($searches["word"]);
            $listTotal = $this->model->get_addons_total($searches);

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                Helper::Load(["Orders", "Products", "Money"]);

                $privOperation = Admin::isPrivilege("ORDERS_OPERATION");
                $privDelete = Admin::isPrivilege("ORDERS_DELETE");

                $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                $addon_situations = $situations["orders"];
                $subscription_situations = $situations["subscription"];


                if ($filteredList) {
                    $this->addData("order_id", $id);
                    $this->addData("privOperation", $privOperation);
                    $this->addData("privDelete", $privDelete);
                    $this->addData("situations", $addon_situations);
                    $this->addData("subscription_situations", $subscription_situations);
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-orders-addons", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function select_users_json()
        {
            $this->takeDatas("language");
            $search = Filter::init("GET/search", "hclear");
            $none = Filter::init("GET/none");
            $data = [];
            if ($none) $data[] = ['id' => 0, 'text' => ___("needs/none")];
            $data2 = $this->model->select_users($search);

            if ($data2) {
                foreach ($data2 as $d) {
                    $data[] = [
                        'id'   => $d["id"],
                        'text' => $d["full_name"] . ($d["company_name"] ? ' - ' . $d["company_name"] : ''),
                    ];
                }
            }

            echo Utility::jencode(['results' => $data]);
        }

        private function select_linked_products_json($id = 0)
        {
            $this->takeDatas("language");
            Helper::Load(["Orders", "Products"]);
            $order = Orders::get($id);
            if (!$order) return false;

            $search = Filter::init("GET/search", "hclear");
            $none = Filter::init("GET/none");
            $data = [];
            $data3 = [];

            if ($order["type"] == "software") {
                $data2 = $this->model->select_software_products($search);
                $data3 = $this->model->select_software_category_products($search);
            } elseif ($order["type"] == "domain") {
                $data2 = $this->model->select_domain_products($search);
            } else {
                $data2 = $this->model->select_products($search, $order["type"], $order["type_id"]);
                $data3 = $this->model->select_category_products($search, $order["type"], $order["type_id"]);
            }

            if (!$data2) $data2 = [];
            if (!$data3) $data3 = [];

            $new_data = [];

            if ($order["type"] == "domain") $new_data = $data2;
            else {
                if ($data2)
                    $data2 = [
                        [
                            'title'    => ___("needs/uncategorized"),
                            'products' => $data2,
                        ],
                    ];

                $data = array_merge($data, $data2, $data3);
                if ($data) {
                    foreach ($data as $datum) {
                        $item = [
                            'text'     => $datum["title"],
                            'children' => [],
                        ];
                        if (isset($datum["products"]) && $datum["products"]) {
                            $item["children"] = $datum["products"];
                            $new_data[] = $item;
                        }
                    }
                }
            }

            if ($none) array_unshift($new_data, ['id' => 0, 'text' => ___("needs/none")]);

            echo Utility::jencode(['results' => $new_data]);
        }


        private function sms_reports_json($id = 0)
        {
            $order = $this->get_order($id);
            if (!$order) die();

            $limit = 10;
            $output = [];
            $aColumns = array('', '', '', '', '', '');

            $start = Filter::init("GET/iDisplayStart", "numbers");
            if (!Validation::isInt($start) || $start < 0) $start = 0;
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


            $filteredList = $this->model->get_sms_reports($order["id"], $searches, $orders, $start, $end);
            $filterTotal = $this->model->get_sms_reports_total($order["id"], $searches);
            $listTotal = $this->model->get_sms_reports_total($order["id"]);

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                if ($filteredList) {

                    foreach ($filteredList as $k => $v)
                        if ($e_c = Crypt::decode($v["content"], "*_LOG_*" . Config::get("crypt/system")))
                            $filteredList[$k]['content'] = $e_c;

                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-orders-sms-reports", $this->data, false, true);

                }
            }

            echo Utility::jencode($output);
        }


        private function get_sms_report($id = 0)
        {
            $order = $this->get_order($id);
            if (!$order) die();

            $this->takeDatas("language");

            $id = (int)Filter::init("POST/id", "numbers");
            $reportd = $this->model->get_report($id, $order["id"]);
            if (!$reportd) die();

            Modules::Load("SMS", $order["module"]);
            $config = isset($order["options"]["config"]) ? $order["options"]["config"] : [];

            if (!$config) return false;

            $mname = $order["module"];
            $sms = new $mname($config);
            $reportd["data"] = Utility::jdecode($reportd["data"], true);

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


        private function generate_renew_invoice($id = 0)
        {
            $this->takeDatas("language");

            Helper::Load(["Money", "Orders", "Products", "Invoices"]);

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = Orders::get($id);
            if (!$order) die("Not found order");

            $order['user_id'] = $order["owner_id"];
            $order['cid'] = $order["amount_cid"];


            $previouslyCreated = false;
            $period_begin = new DateTime($order["duedate"]);
            $period_end = new DateTime(DateManager::next_date(['year' => 1], "Y-m-d H:i"));
            $duedates = [];
            $interval = DateInterval::createFromDateString($order["period_time"] . ' ' . $order["period"]);
            $loop = new DatePeriod($period_begin, $interval, $period_end);
            if ($loop) foreach ($loop as $d) $duedates[] = (string)$d->format("Y-m-d H:i");

            if (sizeof($duedates) > 1) {
                foreach ($duedates as $duedate) {
                    $order["duedate"] = $duedate;
                    $previouslyCreated = Invoices::previously_created_check("order", $order, true);
                    if (!$previouslyCreated) break;
                }
            } else
                $previouslyCreated = Invoices::previously_created_check("order", $order);


            if ($previouslyCreated)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error33"),
                ]));

            $udata = User::getData($order["owner_id"], "id,lang,balance,balance_currency,balance_min,full_name", "array");
            $udata = array_merge($udata, User::getInfo($udata["id"], ["auto_payment_by_credit", "dealership"]));

            $invoice = Invoices::generate_renewal_bill('order', $udata, $order);

            if (!is_array($invoice) && $invoice == 'continue')
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => 'It is not produced because the invoice amount cannot exceed 0.',
                ]));


            if (Invoices::$message && Invoices::$message == "no-user-address")
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error6"),
                ]));

            $adata = UserManager::LoginData("admin");

            Orders::add_history($adata["id"], $order['id'], 'order-manuel-generated-renewal-invoice', [
                'id' => $invoice['id'],
            ]);

            Orders::set($order["id"], ['invoice_id' => $invoice["id"]]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/orders/success27"),
                'redirect' => $this->AdminCRLink("invoices-2", ["detail", $invoice["id"]]),
            ]);

        }


        private function update_detail($id = 0)
        {
            $this->takeDatas("language");

            Helper::Load(["Money", "Orders", "Products", "Invoices"]);

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = Orders::get($id);
            if (!$order) die();

            $adata = defined("CRON") ? [] : UserManager::LoginData("admin");
            $aid = isset($adata["id"]) ? $adata["id"] : 0;

            $transfer_user = Filter::init("POST/transfer_user", "numbers");
            $name = Filter::init("POST/name", "hclear");
            $cdate = Filter::init("POST/cdate", "numbers");
            $ctime = Filter::init("POST/ctime", "numbers", ":");
            $duedate = Filter::init("POST/duedate", "numbers");
            $suspend_date = Filter::init("POST/suspend_date", "numbers");
            $cancel_date = Filter::init("POST/cancel_date", "numbers");
            $duetime = Filter::init("POST/duetime", "numbers", ":");
            $prcextn = Filter::init("POST/process_exemption_date", "numbers");
            $renewaldate = Filter::init("POST/renewaldate", "numbers");
            $renewaltime = Filter::init("POST/renewaltime", "numbers", ":");
            $pmethod = Filter::init("POST/pmethod", "route");
            $notes = Filter::POST("notes");
            $period = Filter::init("POST/period", "letters");
            $period_time = Filter::init("POST/period_time", "numbers");
            $amount = Filter::init("POST/amount", "amount");
            $amount_cid = (int)Filter::init("POST/amount_cid", "numbers");
            $amount = Money::deformatter($amount, $amount_cid);
            $apply_type = Filter::init("POST/apply", "route");
            if (isset($_POST["product_id"])) $product_id = (int)Filter::init("POST/product_id", "numbers");
            $pricing_type = (int)Filter::init("POST/pricing-type", "numbers");
            $block_access = (bool)(int)Filter::init("POST/block_access", "numbers");
            $apply_on_module = (bool)(int)Filter::init("POST/apply_on_module", "numbers");
            $subscription = Filter::init("POST/subscription");
            $suspended_reason = Filter::init("POST/suspended_reason", "hclear");

            $invoice = Invoices::get_last_invoice($id, '', 't2.*');

            // We did this because if it is a tax-inclusive system, the order amount should appear tax-included.
            $taxation_type = Invoices::getTaxationType();
            $tax_rate = Invoices::getTaxRate();

            if ($invoice && $invoice["taxrate"] > 0.00) $tax_rate = $invoice["taxrate"];

            if ($taxation_type == 'inclusive' && $amount > 0.00 && $tax_rate > 0.00)
                $amount -= Money::get_inclusive_tax_amount($amount, $tax_rate);


            if ($apply_type == "suspended") Orders::$suspended_reason = $suspended_reason;

            if ($order["type"] == "special" || $order["type"] == "software") {
                $ip = Filter::init("POST/ip", "ip");
                $domain = Filter::init("POST/domain", "hclear");
                $delivery_filebt = Filter::init("POST/delivery-file-button-title", "hclear");
                $delivery_file = Filter::FILES("delivery-file");
                $delivery_titlenm = Filter::init("POST/delivery-title-name", "hclear");
                $delivery_titledn = Filter::init("POST/delivery-title-description");
                $license_parameters = Filter::init("POST/license_parameters");
            } elseif ($order["type"] == "domain") {
                $intcode = Filter::init("POST/tcode");
                $tcode = Filter::init("POST/transfer-code");
                $transferlock = (int)Filter::init("POST/transferlock", "numbers");
                $s_module = Filter::init("POST/module", "route");
            }
            if ($order["type"] == "software") {
                $code = Filter::init("POST/code", "hclear");
                $change_domain = Filter::init("POST/change-domain", "numbers");
            }

            if ($order["type"] == "sms") {
                $module = Filter::init("POST/module", "route");
                if (!$module) $module = "none";
                $config = Filter::POST("config");
                $id_name = Filter::init("POST/id_name", "hclear");
                $id_birthday = Filter::init("POST/birthday", "hclear");
                $id_identity = Filter::init("POST/identity", "numbers");
                $balance = Filter::init("POST/balance", "numbers");
            }


            if (!$cdate) $cdate = DateManager::Now("Y-m-d");
            if (!$ctime) $ctime = DateManager::Now("H:i");
            if (!$duetime) $duetime = "00:00";
            if (!$suspend_date) $suspend_date = "1970-01-01";
            if (!$cancel_date) $cancel_date = "1970-01-01";

            if (!$duedate && $period != "none") $duedate = DateManager::next_date([$period => $period_time], "Y-m-d");
            elseif (!$duedate) {
                $duedate = "1881-05-19";
                $duetime = "00:00";
            }

            if (!Validation::isDate($cdate))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='cdate']",
                    'message' => ___("needs/invalid-date"),
                ]));

            if (!Validation::isTime($ctime))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='ctime']",
                    'message' => ___("needs/invalid-time"),
                ]));

            if ($duedate && $period != "none") {
                if (!Validation::isDate($duedate))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='duedate']",
                        'message' => ___("needs/invalid-date"),
                    ]));

                if (!Validation::isTime($duetime))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='duetime']",
                        'message' => ___("needs/invalid-time"),
                    ]));
            }

            if ($renewaldate) {
                if (!Validation::isDate($renewaldate))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='renewaldate']",
                        'message' => ___("needs/invalid-date"),
                    ]));

                if (!Validation::isTime($renewaltime))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='renewaltime']",
                        'message' => ___("needs/invalid-time"),
                    ]));
            }

            if ($prcextn && !Validation::isDate($prcextn))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='process_exemption_date']",
                    'message' => ___("needs/invalid-date"),
                ]));


            $cdatetime = $cdate . " " . $ctime . ":" . DateManager::format("s", $order["cdate"]);
            $duedatetime = $duedate && $period != "none" ? $duedate . " " . $duetime . ":" . DateManager::format("s", $order["cdate"]) : '';
            $renewaldatetime = $renewaldate ? $renewaldate . " " . $renewaltime . ":" . DateManager::format("s", $order["renewaldate"]) : '';
            $prcextn = $prcextn ? $prcextn . " 23:59:59" : str_replace("00:00:00", "23:59:59", DateManager::ata());

            $set_order = [];
            $set_oroptions = $order["options"];

            if ($period != "none" && DateManager::strtotime($duedatetime) < DateManager::strtotime($cdatetime))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error34"),
                ]));

            if ($period != "none" && DateManager::strtotime($duedatetime) < DateManager::strtotime($renewaldatetime))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error34"),
                ]));


            if ($order["type"] == "special" || $order["type"] == "software") {
                if (isset($set_oroptions["delivery_file_button_title"]) && $delivery_filebt == '')
                    unset($set_oroptions["delivery_file_button_title"]);
                elseif (!isset($set_oroptions["delivery_file_button_title"]) && $delivery_filebt)
                    $set_oroptions["delivery_file_button_title"] = $delivery_filebt;
                elseif (isset($set_oroptions["delivery_file_button_title"]) && $delivery_filebt != $set_oroptions["delivery_file_button_title"])
                    $set_oroptions["delivery_file_button_title"] = $delivery_filebt;

                if ($delivery_file) {
                    Helper::Load(["Uploads"]);
                    $folder = RESOURCE_DIR . "uploads" . DS . "orders" . DS;
                    $upload = Helper::get("Uploads");
                    $upload->init($delivery_file, [
                        'folder'    => $folder,
                        'file-name' => "random",
                    ]);
                    if (!$upload->processed())
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "#delivery-file",
                            'message' => ___("needs/faieled-upload-file", ['{error}' => $upload->error]),
                        ]));
                    $dyfile = current($upload->operands);
                    $dyfile = $dyfile["file_path"];
                    if (isset($set_oroptions["delivery_file"]))
                        FileManager::file_delete($folder . $set_oroptions["delivery_file"]);
                    $set_oroptions["delivery_file"] = $dyfile;
                }

                if (isset($set_oroptions["delivery_title_name"]) && $delivery_titlenm == '')
                    unset($set_oroptions["delivery_title_name"]);
                elseif (!isset($set_oroptions["delivery_title_name"]) && $delivery_titlenm)
                    $set_oroptions["delivery_title_name"] = $delivery_titlenm;
                elseif (isset($set_oroptions["delivery_title_name"]) && $delivery_titlenm != $set_oroptions["delivery_title_name"])
                    $set_oroptions["delivery_title_name"] = $delivery_titlenm;

                if (isset($set_oroptions["delivery_title_description"]) && $delivery_titledn == '')
                    unset($set_oroptions["delivery_title_description"]);
                elseif (!isset($set_oroptions["delivery_title_description"]) && $delivery_titledn)
                    $set_oroptions["delivery_title_description"] = $delivery_titledn;
                elseif (isset($set_oroptions["delivery_title_description"]) && $delivery_titledn != $set_oroptions["delivery_title_description"])
                    $set_oroptions["delivery_title_description"] = $delivery_titledn;

            }

            $from = Filter::init("POST/from", "route");

            if ($order["type"] == "domain") {

                if (Validation::isEmpty($intcode)) unset($set_oroptions["tcode"]);
                else {
                    if (!isset($set_oroptions["tcode"]) || (isset($set_oroptions["tcode"]) && $intcode != $set_oroptions["tcode"]))
                        $set_oroptions["tcode"] = $intcode;
                }

                if ($order["status"] == "active" && !empty(trim($order["module"])) && $order["module"] != "none") {
                    if (Modules::Load("Registrars", $order["module"])) {
                        $module = new $order["module"]();
                    }
                }

                if ($tcode != '') {
                    if (!isset($set_oroptions["transfer-code"]) || (isset($set_oroptions["transfer-code"]) && $tcode != $set_oroptions["transfer-code"])) {
                        if ($order["status"] == "active" && $module && method_exists($module, "modifyAuthCode")) {
                            $modify = $module->modifyAuthCode($set_oroptions, $tcode);
                            if (!$modify)
                                die(Utility::jencode([
                                    'status'  => "error",
                                    'message' => __("admin/orders/error10", ['{error}' => $module->error]),
                                ]));
                        }
                    }
                }

                $set_oroptions["transfer-code"] = $tcode;


                if ($order["status"] != "inprocess" && $order["status"] != "waiting") {
                    if ($transferlock && !$set_oroptions["transferlock"]) {

                        $set_oroptions["transferlock"] = true;
                        $set_oroptions["transferlock_latest_update"] = DateManager::Now();

                        if (isset($module) && $module && method_exists($module, 'ModifyTransferLock')) {
                            $modify = $module->ModifyTransferLock($set_oroptions, "enable");
                            if (!$modify)
                                die(Utility::jencode([
                                    'status'  => "error",
                                    'message' => __("admin/orders/error10", ['{error}' => $module->error]),
                                ]));
                        }
                    } elseif (!$transferlock && $set_oroptions["transferlock"]) {
                        $set_oroptions["transferlock"] = false;
                        $set_oroptions["transferlock_latest_update"] = DateManager::Now();

                        if (isset($module) && $module && method_exists($module, 'ModifyTransferLock')) {
                            $modify = $module->ModifyTransferLock($set_oroptions, "disable");
                            if (!$modify)
                                die(Utility::jencode([
                                    'status'  => "error",
                                    'message' => __("admin/orders/error10", ['{error}' => $module->error]),
                                ]));
                        }

                    }
                }

                if ($order["module"] != $s_module) $set_order["module"] = $s_module;
            }

            if ($order["type"] == "special" || $order["type"] == "software") {
                $_parameters = [];
                if (isset($license_parameters) && $license_parameters) foreach ($license_parameters as $k => $v) if ($v) $_parameters[$k] = $v;
                if ($_parameters) $set_oroptions["license_parameters"] = $_parameters;
                elseif (isset($set_oroptions["license_parameters"])) unset($set_oroptions["license_parameters"]);

                $first_domain = isset($set_oroptions["domain"]) ? $set_oroptions["domain"] : false;
                if ($domain) {
                    if (isset($set_oroptions["domain"]) && $domain != $set_oroptions["domain"])
                        $set_oroptions["domain"] = $domain;
                    if (!isset($set_oroptions["domain"]))
                        $set_oroptions["domain"] = $domain;
                } else unset($set_oroptions["domain"]);

                if ($ip) {
                    if (isset($set_oroptions["ip"]) && $ip != $set_oroptions["ip"])
                        $set_oroptions["ip"] = $ip;
                    if (!isset($set_oroptions["ip"]))
                        $set_oroptions["ip"] = $ip;
                } else unset($set_oroptions["ip"]);
            }

            if (in_array($order["type"], ['software', 'special']))
                if ($first_domain != $domain)
                    Orders::add_history($aid, $order["id"], "change-domain", [
                        'old_domain' => $first_domain,
                        'new_domain' => $domain,
                    ]);


            if ($order["type"] == "software") {
                if ($code) {
                    if (isset($set_oroptions["code"]) && $code != $set_oroptions["code"])
                        $set_oroptions["code"] = $code;
                    if (!isset($set_oroptions["code"]))
                        $set_oroptions["code"] = $code;
                } else unset($set_oroptions["code"]);

                if ($change_domain) {
                    if (isset($set_oroptions["change-domain"]) && $change_domain != $set_oroptions["change-domain"])
                        $set_oroptions["change-domain"] = $change_domain;
                    if (!isset($set_oroptions["change-domain"]))
                        $set_oroptions["change-domain"] = $change_domain;
                } else unset($set_oroptions["change-domain"]);

            }

            if ($order["type"] == "sms") {
                if ($module != $order["module"]) $set_order["module"] = $module;
                if ($config)
                    $set_oroptions["config"] = $config;
                else
                    unset($set_oroptions["config"]);

                $set_oroptions["name"] = $id_name;
                $set_oroptions["birthday"] = $id_birthday;
                $set_oroptions["identity"] = $id_identity;

                if (Validation::isEmpty($balance)) unset($set_oroptions["balance"]);
                else $set_oroptions["balance"] = $balance;
            }


            if (isset($subscription["identifier"]) && strlen($subscription["identifier"]) > 1) {
                $c_sub = $order["subscription_id"] ? Orders::get_subscription($order["subscription_id"]) : false;
                $create_new_sub = false;
                if ($c_sub && $c_sub["identifier"] == $subscription["identifier"]) {
                    $set_order["subscription_id"] = $c_sub["id"];
                    $find_sub = false;
                } else {
                    $find_sub = true;
                    $create_new_sub = true;
                }

                if ($find_sub) {
                    $s_sub = Orders::get_subscription(0, $subscription["identifier"], $pmethod);
                    if ($s_sub) {
                        $set_order["subscription_id"] = $s_sub["id"];
                        $create_new_sub = false;
                    } else
                        $create_new_sub = true;
                }

                if ($create_new_sub && $pmethod && $pmethod !== "none") {
                    $create_subscription = Orders::create_subscription([
                        'user_id'           => $order["owner_id"],
                        'module'            => $pmethod,
                        'status'            => 'active',
                        'identifier'        => $subscription['identifier'],
                        'currency'          => $amount_cid,
                        'last_paid_date'    => $order["renewaldate"],
                        'next_payable_date' => $order["duedate"],
                        'created_at'        => $order["renewaldate"],
                        'updated_at'        => DateManager::Now(),
                    ]);
                    if ($create_subscription) $set_order["subscription_id"] = $this->model->db->lastID();
                }
            } elseif ($order["subscription_id"] > 0)
                $set_order["subscription_id"] = 0;


            if ($transfer_user && $transfer_user != $order["owner_id"]) $set_order["owner_id"] = $transfer_user;
            if (!Validation::isEmpty($name) && $name != $order["name"]) {
                $set_order["name"] = $name;

                if ($order["type"] == "domain") {
                    $parse = Utility::domain_parser($name);
                    if ($parse["host"] != '' && strlen($parse["host"]) >= 2) {
                        $sld = $parse["host"];
                        $tld = $parse["tld"];
                        $set_oroptions["domain"] = $name;
                        $set_oroptions["sld"] = $sld;
                        $set_oroptions["tld"] = $tld;
                    }

                }

            }
            if ($cdatetime != $order["cdate"]) $set_order["cdate"] = $cdatetime;
            if ($duedatetime != $order["duedate"]) $set_order["duedate"] = $duedatetime ? $duedatetime : DateManager::ata();
            if ($suspend_date != $order["suspend_date"]) $set_order["suspend_date"] = $suspend_date;
            if ($cancel_date != $order["cancel_date"]) $set_order["cancel_date"] = $cancel_date;
            if ($prcextn != $order["process_exemption_date"]) $set_order["process_exemption_date"] = $prcextn;
            if ($notes != $order["notes"]) $set_order["notes"] = $notes;
            if ($renewaldatetime != $order["renewaldate"]) $set_order["renewaldate"] = $renewaldatetime;
            if ($pmethod != $order["pmethod"]) $set_order["pmethod"] = $pmethod;
            if ($period != $order["period"]) $set_order["period"] = $period;
            if ($period_time != $order["period_time"]) $set_order["period_time"] = $period_time;
            if ($amount != $order["amount"]) $set_order["amount"] = $amount;
            if ($amount_cid != $order["amount_cid"]) $set_order["amount_cid"] = $amount_cid;
            if (isset($product_id) && $product_id != $order["product_id"]) {
                $set_order["product_id"] = $product_id;
                $u_data = User::getData($order["owner_id"], "lang", "array");
                $product_u = Products::get($order["type"], $product_id, $u_data["lang"]);
                $product_l = Products::get($order["type"], $product_id, Config::get("general/local"));
                $new_name = Bootstrap::$lang->get("needs/none");

                if ($order["type"] !== "domain" && $product_u && $product_l) {
                    if (!Validation::isEmpty($name) && $name == $order["name"]) {
                        $set_order["name"] = $product_u["title"];
                        $new_name = $product_u["title"];
                    }
                    $set_oroptions["category_id"] = $product_l["category"];
                    $set_oroptions["local_category_name"] = $product_l["category_title"];
                    $set_oroptions["category_name"] = $product_u["category_title"];
                }
                Orders::add_history($aid, $order["id"], 'order-product-changed', [
                    'old_id'   => $order["product_id"],
                    'old_name' => $order["name"],
                    'new_id'   => $product_id,
                    'new_name' => $new_name,
                ]);
            }


            if ($pricing_type) $set_oroptions["pricing-type"] = $pricing_type;

            if ($block_access) $set_oroptions["block_access"] = $block_access;
            elseif (isset($set_oroptions["block_access"])) unset($set_oroptions["block_access"]);

            if ($set_oroptions != $order["options"]) $set_order["options"] = Utility::jencode($set_oroptions);

            $updates = 0;

            if ($apply_type == 'cancelled') $set_order["unread"] = 1;

            if ($suspended_reason != $order["suspended_reason"]) $set_order["suspended_reason"] = $suspended_reason;

            if ($set_order) {

                Hook::run("PreOrderModified", $order);

                $this->model->set_order($id, $set_order);

                $updates++;

                if (isset($set_order['owner_id'])) {
                    $old_name = User::getData($order['owner_id'], 'full_name')->full_name;
                    $new_name = User::getData($set_order['owner_id'], 'full_name')->full_name;
                    Orders::add_history($aid, $order["id"], 'order-user-changed', [
                        'old_id'   => $order['owner_id'],
                        'old_name' => $old_name,
                        'new_id'   => $set_order['owner_id'],
                        'new_name' => $new_name,
                    ]);
                }

                if (isset($set_order['cdate']) && $set_order['cdate'] != $order['cdate'])
                    Orders::add_history($aid, $order["id"], 'order-cdate-changed', [
                        'old' => DateManager::format(Config::get("options/date-format") . " H:i", $order['cdate']),
                        'new' => DateManager::format(Config::get("options/date-format") . " H:i", $set_order['cdate']),
                    ]);

                if (isset($set_order['renewaldate']) && $set_order['renewaldate'] != $order['renewaldate'])
                    Orders::add_history($aid, $order["id"], 'order-renewaldate-changed', [
                        'old' => DateManager::format(Config::get("options/date-format") . " H:i", $order['renewaldate']),
                        'new' => DateManager::format(Config::get("options/date-format") . " H:i", $set_order['renewaldate']),
                    ]);

                if (isset($set_order['duedate']) && $set_order['duedate'] != $order['duedate'])
                    Orders::add_history($aid, $order["id"], 'order-duedate-changed', [
                        'old' => DateManager::format(Config::get("options/date-format") . " H:i", $order['duedate']),
                        'new' => DateManager::format(Config::get("options/date-format") . " H:i", $set_order['duedate']),
                    ]);

                if (isset($set_order['process_exemption_date']) && $set_order['process_exemption_date'] != $order['process_exemption_date'])
                    Orders::add_history($aid, $order["id"], 'order-process-exemption-date-changed', [
                        'date' => substr($set_order['process_exemption_date'], 0, 4) == '1881' ? '00/00/0000' : DateManager::format(Config::get("options/date-format"), $set_order['process_exemption_date']),
                    ]);

                if (isset($set_order['suspend_date']) && $set_order['suspend_date'] != $order['suspend_date']) {
                    $defined_order_suspend_date = ((int) substr($order['suspend_date'],0,4)) > 2000 ? $order['suspend_date'] : '1970-01-01';
                    $defined_detail_suspend_date = ((int) substr($set_order['suspend_date'],0,4)) > 2000 ? $set_order['suspend_date'] : '1970-01-01';

                    if($defined_detail_suspend_date != $defined_order_suspend_date)
                        Orders::add_history($aid, $order["id"], 'order-suspend-date-changed', [
                            'date' => (int) substr($set_order['suspend_date'], 0, 4) > 2000 ? DateManager::format(Config::get("options/date-format"), $set_order['suspend_date']) : ___("needs/none"),
                        ]);
                }

                if (isset($set_order['cancel_date']) && $set_order['cancel_date'] != $order['cancel_date']) {
                    $defined_order_cancel_date = ((int) substr($order['cancel_date'],0,4)) > 2000 ? $order['cancel_date'] : '1970-01-01';
                    $defined_detail_cancel_date = ((int) substr($set_order['cancel_date'],0,4)) > 2000 ? $set_order['cancel_date'] : '1970-01-01';

                    if($defined_detail_cancel_date != $defined_order_cancel_date)
                        Orders::add_history($aid, $order["id"], 'order-cancel-date-changed', [
                            'date' => (int) substr($set_order['cancel_date'], 0, 4) > 2000 ? DateManager::format(Config::get("options/date-format"), $set_order['cancel_date']) : ___("needs/none"),
                        ]);
                }

                if (!isset($order['options']['pricing-type'])) $order['options']['pricing-type'] = 1;

                if ($pricing_type != $order['options']['pricing-type']) {
                    $old_pricing_type = __("admin/orders/detail-pricing-type-" . $order['options']['pricing-type']);
                    $new_pricing_type = __("admin/orders/detail-pricing-type-" . $pricing_type);
                    Orders::add_history($aid, $order["id"], 'order-pricing-type-changed', [
                        'old' => $old_pricing_type,
                        'new' => $new_pricing_type,
                    ]);
                }

                if ((round($amount, 2)) != round($order["amount"], 2) || $amount_cid != $order["amount_cid"] || $period != $order["period"] || $period_time != $order["period_time"]) {
                    if ($taxation_type == "inclusive") {
                        $order["amount"] += Money::get_tax_amount($order["amount"], $tax_rate);
                        $amount += Money::get_tax_amount($amount, $tax_rate);
                    }

                    $old_period = View::period($order["period_time"], $order["period"]);
                    $old_amount = Money::formatter_symbol($order["amount"], $order["amount_cid"]);
                    $new_period = View::period($period_time, $period);
                    $new_amount = Money::formatter_symbol($amount, $amount_cid);
                    Orders::add_history($aid, $order["id"], 'order-pricing-changed', [
                        'old_period' => $old_period,
                        'old_amount' => $old_amount,
                        'new_period' => $new_period,
                        'new_amount' => $new_amount,
                    ]);
                }

                if ($name != $order['name'])
                    Orders::add_history($aid, $order["id"], 'order-name-changed', [
                        'old' => $order['name'],
                        'new' => $set_order["name"],
                    ]);
                $order = Orders::get($id);

                if ($h_returns = Hook::run("OrderModified", $order))
                    foreach ($h_returns as $h_return)
                        if ($h_return && isset($h_return["error"]) && $h_return["error"])
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => $h_return['error'],
                            ]));
            }


            if ((!isset($set_order["subscription_id"]) && ($order["subscription_id"] ?? false)) || ($order["subscription_id"] ?? false) != ($set_order["subscription_id"] ?? false)) {
                $subscription = Orders::get_subscription($set_order["subscription_id"] ?? 0);
                if ($subscription) Orders::auto_d_i_subscription($subscription);
            }


            Helper::Load(["Notification"]);

            $adata = UserManager::LoginData("admin");

            if ($apply_type) {
                if ($apply_type == "activation-message" && ($order["type"] == "server" || $order["type"] == "hosting"))
                    $apply = Notification::order_hosting_server_activation($order);
                else
                    $apply = Orders::MakeOperation($apply_type, $order, false, true, $apply_on_module);

                if ($apply) {
                    if ($apply_type == "activation-message")
                        User::addAction($adata["id"], "alteration", "activation-message-sent", [
                            'id'   => $id,
                            'name' => $order["name"],
                        ]);
                    if ($apply_type == "approve")
                        User::addAction($adata["id"], "alteration", "approved-order", [
                            'id'   => $id,
                            'name' => $order["name"],
                        ]);
                    elseif ($apply_type == "active")
                        User::addAction($adata["id"], "alteration", "activated-order", [
                            'id'   => $id,
                            'name' => $order["name"],
                        ]);
                    elseif ($apply_type == "suspended")
                        User::addAction($adata["id"], "alteration", "suspended-order", [
                            'id'   => $id,
                            'name' => $order["name"],
                        ]);
                    elseif ($apply_type == "cancelled")
                        User::addAction($adata["id"], "alteration", "cancelled-order", [
                            'id'   => $id,
                            'name' => $order["name"],
                        ]);
                    elseif ($apply_type == "delete")
                        User::addAction($adata["id"], "deleted", "deleted-order", [
                            'id'   => $id,
                            'name' => $order["name"],
                        ]);
                } else
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "status",
                        'message' => Orders::$message ? Orders::$message : __("admin/orders/error1"),
                    ]));
            }


            if ($updates) User::addAction($adata["id"], "alteration", "updated-order-detail", [
                'id'   => $id,
                'name' => $order["name"],
            ]);

            $redirect = $this->AdminCRLink("orders-2", ["detail", $order["id"]]);

            if ($apply_type == "delete")
                $redirect = $this->AdminCRLink("orders");

            if ($from == "transfer-code")
                die(Utility::jencode([
                    'status'   => "successful",
                    'message'  => __("admin/orders/success19"),
                    'redirect' => $redirect,
                ]));


            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/orders/success1"),
                'redirect' => $redirect,
            ]);
        }

        private function modify_order_blocks($id = 0)
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();


            $blocks = Filter::POST("blocks");
            $option_blocks = [];

            if ($blocks && isset($blocks["title"])) {
                $size = sizeof($blocks["title"]) - 1;
                for ($i = 0; $i <= $size; $i++) {
                    $b_title = $blocks["title"][$i];
                    $b_desc = $blocks["description"][$i];
                    if ($b_title)
                        $option_blocks[] = [
                            'title'       => $b_title,
                            'description' => $b_desc,
                        ];

                }
            }

            $options = $order["options"];
            if (isset($options["blocks"]) && !$option_blocks) unset($options["blocks"]);
            if ($option_blocks) $options["blocks"] = $option_blocks;

            Helper::Load(["Orders"]);

            $update = Orders::set($order["id"], [
                'options' => Utility::jencode($options),
            ]);

            if (!$update)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error1"),
                ]));

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "oder-blocks-has-been-modified", [
                'id'   => $id,
                'name' => $order["name"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/orders/success1"),
                'redirect' => $this->AdminCRLink("orders-2", ["detail", $order["id"]]) . "?content=blocks",
            ]);
        }


        private function event_ok($id = 0)
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $event_id = (int)Filter::init("POST/id", "numbers");
            if (!$event_id) return false;

            Helper::Load(["Events", "Orders", "Invoices"]);

            $event = Events::get($event_id);
            if (!$event) return false;
            $order = Orders::get($id);


            if ($event["name"] == "cancelled-product-request" && $order) {
                $data = $event["data"];
                if ($data["urgency"] == "now") {
                    $apply = Orders::MakeOperation("cancelled", $order, false, true);
                    if ($apply) {
                        User::addAction(0, "alteration", "cancelled-order", [
                            'id'   => $order["id"],
                            'name' => $order["name"],
                        ]);
                    } else
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "status",
                            'message' => Orders::$message ? Orders::$message : __("admin/orders/error1"),
                        ]));
                } else {
                    $cancel_invoice = Orders::cancel_order_invoices($order);
                    if (!$cancel_invoice)
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "status",
                            'message' => Orders::$message ? Orders::$message : __("admin/orders/error1"),
                        ]));
                }
            }

            $status = Events::approved($event);
            if (!$status)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "status",
                    'message' => Events::$message ? Events::$message : __("admin/orders/error1"),
                ]));

            if ($order) {
                if ($order["status"] == "active" || $order["status"] == "cancelled" || $order["status"] == "suspended")
                    Orders::set($order["id"], ['unread' => 1]);
            }

            echo Utility::jencode(['status' => "successful"]);

        }

        private function event_del($id = 0)
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $event_id = (int)Filter::init("POST/id", "numbers");
            if (!$event_id) return false;

            Helper::Load(["Events", "Orders", "Invoices"]);

            $event = Events::get($event_id);
            if (!$event) return false;
            $order = Orders::get($id);

            $status = Events::delete($event["id"]);
            if (!$status)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "status",
                    'message' => Events::$message ? Events::$message : __("admin/orders/error1"),
                ]));

            echo Utility::jencode(['status' => "successful"]);

        }

        private function msg_ok($id = 0)
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->model->get_order($id);

            if (!$order) return false;

            $set_data = ['status_msg' => ""];

            $this->model->set_order($id, $set_data);

            echo Utility::jencode(['status' => "successful"]);

        }


        private function add_addon($id = 0)
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();

            Helper::Load("Money");

            $addon_name = Filter::init("POST/addon_name", "hclear");
            $option_name = Filter::init("POST/option_name", "hclear");

            $addon_id = (int)Filter::init("POST/addon_id", "numbers");
            $option_id = (int)Filter::init("POST/option_id", "numbers");
            $option_q = (int)Filter::init("POST/option_quantity", "numbers");
            $u_lang = User::getData($order["owner_id"], "lang")->lang;


            if ($addon_id) {
                Helper::Load("Products");
                $addon = Products::addon($addon_id, $u_lang);
                if (!$addon) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => "There is no addon from the value you defined.",
                    ]);
                    return false;
                }
                $options = $addon["options"];
                $option = [];
                if ($options) foreach ($options as $o) if ($o["id"] == $option_id) $option = $o;
                if (!$option) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => "There is no addon option from the value you defined.",
                    ]);
                    return false;
                }

                $addon_name = $addon["name"];
                $option_name = $option["name"];
                if ($addon["type"] == "qunatity" && $option_q < 1) $option_q = 1;
            }


            $cdate = Filter::init("POST/cdate", "numbers", "\-");
            $ctime = Filter::init("POST/ctime", "numbers", ":");

            $renewaldate = Filter::init("POST/renewaldate", "numbers", "\-");
            $renewaltime = Filter::init("POST/renewaltime", "numbers", ":");

            $duedate = Filter::init("POST/duedate", "numbers", "\-");
            $duetime = Filter::init("POST/duetime", "numbers", ":");

            $period = Filter::init("POST/period", "letters");
            $period_time = Filter::init("POST/period_time", "numbers");
            if (!$period_time) $period_time = 1;
            $amount = Filter::init("POST/amount", "amount");
            $cid = (int)Filter::init("POST/cid", "numbers");
            $amount = Money::deformatter($amount, $cid);

            $status = Filter::init("POST/status", "letters");
            $igeneration = Filter::init("POST/invoice-generation", "letters");
            $pmethod = Filter::init("POST/pmethod", "route");
            $notification = Filter::init("POST/notification", "numbers");

            if (!$cdate) $cdate = DateManager::Now("Y-m-d");
            if (!$renewaldate) $renewaldate = DateManager::Now("Y-m-d");
            if (!$ctime) $ctime = DateManager::Now("H:i");
            if (!$renewaltime) $renewaltime = DateManager::Now("H:i");

            if (!$duedate && $period != "none") $duedate = DateManager::next_date([$period => $period_time], "Y-m-d");
            if ($duedate && !$duetime) $duetime = $ctime;
            if (!$duedate) {
                $duedate = "1881-05-19";
                $duetime = "00:00";
            }

            if (Validation::isEmpty($addon_name))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='addon_name']",
                    'message' => __("admin/orders/error17"),
                ]));

            if (!Validation::isDate($cdate))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='cdate']",
                    'message' => ___("needs/invalid-date"),
                ]));

            if (!Validation::isDate($renewaldate))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='renewaldate']",
                    'message' => ___("needs/invalid-date"),
                ]));

            if (!Validation::isTime($ctime))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='ctime']",
                    'message' => ___("needs/invalid-time"),
                ]));

            if (!Validation::isTime($renewaltime))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='renewaltime']",
                    'message' => ___("needs/invalid-time"),
                ]));

            if (!Validation::isDate($duedate))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='duedate']",
                    'message' => ___("needs/invalid-date"),
                ]));

            if (!Validation::isTime($duetime))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='duetime']",
                    'message' => ___("needs/invalid-time"),
                ]));

            $cdatetime = $cdate . " " . $ctime . ":00";
            $renewaldatetime = $renewaldate . " " . $renewaltime . ":00";
            $duedatetime = $duedate . " " . $duetime . ":00";

            Helper::Load(["Notification", "Orders", "Invoices"]);


            if ($order["module"] && $order["module"] != "none") {
                if ($igeneration == "unpaid") $status = "waiting";
                else $status = "inprocess";
            }


            $add_opt = [
                'owner_id'        => $order["id"],
                'addon_id'        => $addon_id,
                'option_id'       => $option_id,
                'addon_name'      => $addon_name,
                'option_name'     => $option_name,
                'option_quantity' => $option_q,
                'period_time'     => $period_time,
                'period'          => $period,
                'amount'          => $amount,
                'cid'             => $cid,
                'status'          => $status,
                'cdate'           => $cdatetime,
                'renewaldate'     => $renewaldatetime,
                'duedate'         => $duedatetime,
                'unread'          => 1,
            ];

            $add = Orders::insert_addon($add_opt);

            if (!$add)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error18"),
                ]));

            if ($igeneration == "paid" || $igeneration == "unpaid") {
                $generate = Invoices::bill_generate(
                    [
                        'user_id' => $order["owner_id"],
                        'status'  => $igeneration,
                        'pmethod' => $pmethod,
                        'amount'  => $amount,
                        'cid'     => $cid,
                    ],
                    [
                        [
                            'process'  => false,
                            'name'     => Orders::detail_name($order) . " - " . $addon_name . ": " . ($option_q > 0 ? $option_q . "x " : '') . $option_name,
                            'user_pid' => $order["id"],
                        ],
                    ]
                );
                if ($generate && $notification) {
                    if ($igeneration == "paid") Notification::invoice_has_been_approved($generate);
                    elseif ($igeneration == "unpaid") Notification::invoice_created($generate);
                }
            }

            if ($igeneration && $generate) $this->model->set_addon($add, ['invoice_id' => $generate["id"]]);
            $addon = Orders::get_addon($add);

            Orders::MakeOperationAddon($status, $order, $addon, $notification);

            $adata = UserManager::LoginData("admin");

            User::addAction($adata["id"], "added", "added-user-addon");

            Hook::run("AddonAddedtoOrder", $addon);


            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/orders/success21"),
            ]);
        }


        private function edit_addon()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Money", "Notification", "Orders"]);

            $addon_id = (int)Filter::init("POST/addon_id", "numbers");


            $addon = Orders::get_addon($addon_id);
            if (!$addon) return false;

            $order = Orders::get($addon["owner_id"]);


            $addon_name = Filter::init("POST/addon_name", "hclear");
            $option_name = Filter::init("POST/option_name", "hclear");
            $option_quantity = (int)Filter::init("POST/option_quantity", "numbers");

            $cdate = Filter::init("POST/cdate", "numbers", "\-");
            $ctime = Filter::init("POST/ctime", "numbers", ":");

            $renewaldate = Filter::init("POST/renewaldate", "numbers", "\-");
            $renewaltime = Filter::init("POST/renewaltime", "numbers", ":");

            $duedate = Filter::init("POST/duedate", "numbers", "\-");
            $duetime = Filter::init("POST/duetime", "numbers", ":");

            $period = Filter::init("POST/period", "letters");
            $period_time = Filter::init("POST/period_time", "numbers");
            if (!$period_time) $period_time = 1;
            $amount = Filter::init("POST/amount", "amount");
            $cid = (int)Filter::init("POST/cid", "numbers");
            $amount = Money::deformatter($amount, $cid);

            $subscription = Filter::init("POST/subscription");


            if ($amount < 0.1) $link_addon_id = 0;

            $notification = Filter::init("POST/notification", "numbers");
            $status = Filter::init("POST/status", "letters");
            $suspended_reason = Filter::init("POST/suspended_reason", "hclear");

            if (!$cdate) $cdate = DateManager::Now("Y-m-d");
            if (!$renewaldate) $renewaldate = DateManager::Now("Y-m-d");
            if (!$ctime) $ctime = DateManager::Now("H:i");
            if (!$renewaltime) $renewaltime = DateManager::Now("H:i");

            if (!$duedate && $period != "none") $duedate = DateManager::next_date([$period => $period_time], "Y-m-d");
            if ($duedate && !$duetime) $duetime = $ctime;
            if (!$duedate) {
                $duedate = "1881-05-19";
                $duetime = "00:00";
            }

            if (Validation::isEmpty($addon_name))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='addon_name']",
                    'message' => __("admin/orders/error17"),
                ]));

            if (!Validation::isDate($cdate))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='cdate']",
                    'message' => ___("needs/invalid-date"),
                ]));

            if (!Validation::isDate($renewaldate))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='renewaldate']",
                    'message' => ___("needs/invalid-date"),
                ]));

            if (!Validation::isTime($ctime))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='ctime']",
                    'message' => ___("needs/invalid-time"),
                ]));

            if (!Validation::isTime($renewaltime))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='renewaltime']",
                    'message' => ___("needs/invalid-time"),
                ]));

            if (!Validation::isDate($duedate))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='duedate']",
                    'message' => ___("needs/invalid-date"),
                ]));

            if (!Validation::isTime($duetime))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='duetime']",
                    'message' => ___("needs/invalid-time"),
                ]));

            $cdatetime = $cdate . " " . $ctime . ":00";
            $duedatetime = $duedate . " " . $duetime . ":00";
            $renewaldatetime = $renewaldate . " " . $renewaltime . ":00";
            $pmethod = Filter::init("POST/pmethod", "route");


            $set_addon = [
                'addon_name'      => $addon_name,
                'option_name'     => $option_name,
                'option_quantity' => $option_quantity,
                'period_time'     => $period_time,
                'period'          => $period,
                'amount'          => $amount,
                'cid'             => $cid,
                'status'          => $status,
                'status_msg'      => "",
                'cdate'           => $cdatetime,
                'renewaldate'     => $renewaldatetime,
                'duedate'         => $duedatetime,
                'pmethod'         => $pmethod,
            ];

            if (isset($subscription["identifier"]) && strlen($subscription["identifier"]) > 1) {
                $c_sub = $addon["subscription_id"] ? Orders::get_subscription($addon["subscription_id"]) : false;
                $create_new_sub = false;
                if ($c_sub && $c_sub["identifier"] == $subscription["identifier"]) {
                    $set_addon["subscription_id"] = $c_sub["id"];
                    $find_sub = false;
                } else {
                    $find_sub = true;
                    $create_new_sub = true;
                }


                if ($find_sub) {
                    $s_sub = Orders::get_subscription(0, $subscription["identifier"], $pmethod);
                    if ($s_sub) {
                        $set_addon["subscription_id"] = $s_sub["id"];
                        $create_new_sub = false;
                    } else
                        $create_new_sub = true;
                }

                if ($create_new_sub && $pmethod && $pmethod !== "none") {
                    $create_subscription = Orders::create_subscription([
                        'user_id'           => $order["owner_id"],
                        'module'            => $pmethod,
                        'status'            => 'active',
                        'identifier'        => $subscription['identifier'],
                        'currency'          => $addon["cid"],
                        'last_paid_date'    => $addon["renewaldate"],
                        'next_payable_date' => $addon["duedate"],
                        'created_at'        => $addon["renewaldate"],
                        'updated_at'        => DateManager::Now(),
                    ]);
                    if ($create_subscription) $set_addon["subscription_id"] = $this->model->db->lastID();
                }
            } elseif ($addon["subscription_id"] > 0)
                $set_addon["subscription_id"] = 0;


            if ($status == "active" || $status == 'completed' || $status == "suspended" || $status == "cancelled")
                $set_addon["unread"] = 1;
            else
                $set_addon["unread"] = 0;

            if ($period != "none" && DateManager::strtotime($duedatetime) < DateManager::strtotime($cdatetime))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error34"),
                ]));

            if ($period != "none" && DateManager::strtotime($duedatetime) < DateManager::strtotime($renewaldatetime))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error34"),
                ]));


            if ($status == "suspended") Orders::$suspended_reason = $suspended_reason;

            if ($status != $addon["status"]) {
                if (!Orders::MakeOperationAddon($status, $addon["owner_id"], $addon_id, $notification)) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error19") . " " . Orders::$message,
                    ]);
                    return false;
                }
            } else {
                Orders::$set_data = $set_addon;
                if (!Orders::MakeOperationAddon("change", $addon["owner_id"], $addon_id, $notification)) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error19") . " " . Orders::$message,
                    ]);
                    return false;
                }

                $set_addon["suspended_reason"] = $suspended_reason;
            }

            $edit = Orders::set_addon($addon_id, $set_addon);

            if (!$edit)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error19"),
                ]));

            $adata = UserManager::LoginData("admin");

            User::addAction($adata["id"], "alteration", "changed-user-addon", [
                'order_id'   => $addon["owner_id"],
                'addon_id'   => $addon_id,
                'addon_name' => $addon["addon_name"],
            ]);


            if ((!isset($set_addon["subscription_id"]) && $addon["subscription_id"]) || $addon["subscription_id"] != $set_addon["subscription_id"]) {
                $subscription = Orders::get_subscription($set_addon["subscription_id"] ?? 0);
                Orders::auto_d_i_subscription($subscription);
            }


            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/orders/success22"),
            ]);
        }


        private function delete_addon($id = 0)
        {
            $this->takeDatas("language");

            Helper::Load(["Orders"]);

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();

            $addon_id = Filter::init("POST/id", "numbers");

            $addon = $this->model->get_addon($addon_id);
            if (!$addon) return false;

            $handle = Orders::MakeOperationAddon('delete', $addon["owner_id"], $addon, false);
            if (!$handle) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => Orders::$message,
                ]);
                return false;
            }

            $del = $this->model->delete_addon($addon_id);
            if (!$del) return false;

            if ($addon["addon_key"] == "whois-privacy") {
                $order = Orders::get($addon["owner_id"], "id,options");
                $options = $order["options"];
                if (isset($options["whois_privacy"])) unset($options["whois_privacy"]);
                if (isset($options["whois_privacy_endtime"])) unset($options["whois_privacy_endtime"]);
                Orders::set($order["id"], ['options' => Utility::jencode($options)]);
            }

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "deleted", "deleted-order-addon", [
                'id'         => $addon_id,
                'order_id'   => $id,
                'addon_name' => $addon["addon_name"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);

        }


        private function update_requirements($id = 0)
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();

            Helper::Load(["Money"]);

            $values = Filter::POST("values");

            $updates = 0;

            if ($values && is_array($values)) {
                foreach ($values as $k => $v) {
                    $k = (int)$k;
                    $v = is_array($v) ? Utility::jencode($v) : $v;
                    $update = $this->model->set_requirement($k, [
                        'response' => $v,
                    ]);
                    if ($update) $updates++;
                }
            }

            if ($updates) {
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "updated-order-requirements", [
                    'id'   => $id,
                    'name' => $order["name"],
                ]);
            }

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/orders/success1"),
                'redirect' => $this->AdminCRLink("orders-2", ["detail", $order["id"]]) . "?content=requirements",
            ]);
        }


        private function upgrade($id = 0)
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();

            if ($order["period"] == "none") return false;

            Helper::Load(["Money", "Products", "Orders", "Invoices"]);

            $product = Products::get($order["type"], $order["product_id"], Config::get("general/local"));

            if (!$product)
                $product = [
                    'id'      => $order["product_id"],
                    'type'    => $order["type"],
                    'type_id' => $order["type_id"],
                    'title'   => $order["name"],
                ];


            $ordinfo = Orders::period_info($order);
            $up_products = $this->updown_products("up", $order, $product, $ordinfo["remaining-amount"]);

            $sproduct = (int)Filter::init("POST/product", "numbers");
            $sprice = (int)Filter::init("POST/sprice", "numbers");
            $pmethod = Filter::init("POST/pmethod", "route");
            $igeneration = Filter::init("POST/invoice-generation", "letters");
            $notification = Filter::init("POST/notification", "numbers");

            if (!$sproduct && $err = $product["type"] == "special" ? "error2" : "error3")
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='product']",
                    'message' => __("admin/orders/" . $err),
                ]));

            if (!isset($up_products["prices"][$sproduct][$sprice])) return false;
            $sprice = $up_products["prices"][$sproduct][$sprice];
            $sproduct = Products::get($product["type"], $sproduct, Config::get("general/local"));

            Helper::Load("Notification");

            $invoice = false;

            if ($igeneration == "unpaid" || $igeneration == "paid") {
                $invoice = Invoices::generate_upgrade($order, $product, $sproduct, $sprice, $igeneration, $pmethod);
                if (!$invoice) {
                    if (Invoices::$message == "repetition")
                        $errmsg = "error5";
                    elseif (Invoices::$message == "no-user-address")
                        $errmsg = "error6";
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/" . $errmsg),
                    ]));
                }
            }

            $updown = Orders::generate_updown("up", $invoice, $order, $product, $sproduct, $sprice);

            if (!$igeneration || $igeneration == "paid") {
                $process = Orders::updown($order, "up", [
                    'new_pid'         => $sproduct["id"],
                    'new_period'      => $sprice["period"],
                    'new_period_time' => $sprice["time"],
                    'new_amount'      => $sprice["amount"],
                ]);
                if ($process) {
                    if ($process["status"] == "inprocess") Notification::need_manually_upgrading($updown["id"]);
                    Orders::set_updown($updown["id"], $process);
                }
            }


            $adata = UserManager::LoginData("admin");

            if ($notification && $invoice) {
                if ($igeneration == "paid") Notification::invoice_has_been_approved($invoice);
                elseif ($igeneration == "unpaid") Notification::invoice_created($invoice);
            }

            if ($invoice && $igeneration == "unpaid") {
                User::addAction($adata["id"], "added", "added-upgrade-invoice", [
                    'order_id'    => $order["id"],
                    'old-product' => $product["title"],
                    'new-product' => $sproduct["title"],
                ]);

                die(Utility::jencode([
                    'status'   => "successful",
                    'message'  => __("admin/orders/success6"),
                    'redirect' => $this->AdminCRLink('orders'),
                ]));
            }

            User::addAction($adata["id"], "alteration", "upgraded-order", [
                'order_id'    => $order["id"],
                'old-product' => $product["title"],
                'new-product' => $sproduct["title"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/orders/success5"),
                'redirect' => $this->AdminCRLink('orders-1', ["updowngrades"]),
            ]);


        }


        private function downgrade($id = 0)
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();

            if ($order["period"] == "none") return false;

            Helper::Load(["Money", "Products", "Orders", "Invoices"]);

            $product = Products::get($order["type"], $order["product_id"], Config::get("general/local"));

            if (!$product)
                $product = [
                    'id'      => $order["product_id"],
                    'type'    => $order["type"],
                    'type_id' => $order["type_id"],
                    'title'   => $order["name"],
                ];

            $ordinfo = Orders::period_info($order);
            $down_products = $this->updown_products("down", $order, $product, $ordinfo["remaining-amount"]);

            $sproduct = (int)Filter::init("POST/product", "numbers");
            $sprice = (int)Filter::init("POST/sprice", "numbers");
            $refund = Filter::init("POST/refund", "letters");

            if (!$sproduct && $err = $product["type"] == "special" ? "error2" : "error3")
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='product']",
                    'message' => __("admin/orders/" . $err),
                ]));

            if (!isset($down_products["prices"][$sproduct][$sprice])) return false;
            $sprice = $down_products["prices"][$sproduct][$sprice];
            $sproduct = Products::get($product["type"], $sproduct, Config::get("general/local"));

            $process_order = Orders::updown($order, "down", [
                'new_pid'         => $sproduct["id"],
                'new_period'      => $sprice["period"],
                'new_period_time' => $sprice["time"],
                'new_amount'      => $sprice["amount"],
            ]);

            if ($process_order) {
                $updown = Orders::generate_updown("down", false, $order, $product, $sproduct, $sprice, $refund);

                if ($process_order) {
                    Orders::set_updown($updown["id"], $process_order);
                }

                $desc = Bootstrap::$lang->get_cm("admin/invoices/cash-descriptions/order-downgraded-money", ['{order_id}' => $order["id"]], Config::get("general/local"));

                if ($refund == "credit") {
                    $udata = User::getData($order["owner_id"], "id,balance,balance_currency", "array");
                    $cbalance = $udata["balance"];
                    $ucurr = $udata["balance_currency"];
                    $exch = Money::exChange($sprice["difference"], $sprice["cid"], $ucurr);
                    $nbalance = ($cbalance + $exch);
                    User::setData($order["owner_id"], ['balance' => $nbalance]);

                    User::insert_credit_log([
                        'user_id'     => $udata["id"],
                        'description' => $desc,
                        'type'        => "up",
                        'amount'      => $exch,
                        'cid'         => $udata["balance_currency"],
                        'cdate'       => DateManager::Now(),
                    ]);
                }

                if ($refund == "credit" || $refund == "money") {
                    $exch = Money::exChange($sprice["difference"], $sprice["cid"], $order["amount_cid"]);
                    Invoices::insert_inex([
                        'type'        => "expense",
                        'amount'      => $exch,
                        'currency'    => $order["amount_cid"],
                        'cdate'       => DateManager::Now(),
                        'description' => $desc,
                    ]);

                }

                $adata = UserManager::LoginData("admin");

                User::addAction($adata["id"], "alteration", "downgraded-order", [
                    'id'          => $order["id"],
                    'old-product' => $product["title"],
                    'new-product' => $sproduct["title"],
                ]);

                echo Utility::jencode([
                    'status'   => "successful",
                    'message'  => __("admin/orders/success7"),
                    'redirect' => $this->AdminCRLink('orders-1', ['updowngrades']),
                ]);

            }
        }


        private function cancelled($id = 0)
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();

            if ($order["period"] == "none") return false;

            Helper::Load(["Money", "Products", "Orders", "Invoices"]);

            $user = User::getData($order["owner_id"], "id,full_name", "array");
            $product = Products::get($order["type"], $order["product_id"], Config::get("general/local"));

            $refund = Filter::init("POST/refund", "letters");
            $apply_on_module = (bool)(int)Filter::init("POST/apply_on_module", "numbers");

            $ordinfo = Orders::period_info($order);
            $o_type = $order["type"];

            $cancelled = Orders::MakeOperation("cancelled", $order, $product, true, $apply_on_module);

            if ($cancelled) {

                $desc = Bootstrap::$lang->get_cm("admin/invoices/cash-descriptions/order-cancelled-refund-money", ['{order_id}' => $order["id"]], Config::get("general/local"));

                if ($refund == "credit") {
                    $udata = User::getData($order["owner_id"], "id,balance,balance_currency", "array");
                    $cbalance = $udata["balance"];
                    $ucurr = $udata["balance_currency"];
                    $exch = Money::exChange($ordinfo["remaining-amount"], $order["amount_cid"], $ucurr);
                    $nbalance = ($cbalance + $exch);
                    User::setData($order["owner_id"], ['balance' => $nbalance]);

                    User::insert_credit_log([
                        'user_id'     => $udata["id"],
                        'description' => $desc,
                        'type'        => "up",
                        'amount'      => $exch,
                        'cid'         => $udata["balance_currency"],
                        'cdate'       => DateManager::Now(),
                    ]);
                }

                if ($refund == "credit" || $refund == "money") {
                    Invoices::insert_inex([
                        'type'        => "expense",
                        'amount'      => $ordinfo["remaining-amount"],
                        'currency'    => $order["amount_cid"],
                        'cdate'       => DateManager::Now(),
                        'description' => $desc,
                    ]);

                }

                $adata = UserManager::LoginData("admin");

                User::addAction($adata["id"], "alteration", "order-cancelled", [
                    'id'   => $order["id"],
                    'name' => $order["name"],
                ]);

                echo Utility::jencode([
                    'status'   => "successful",
                    'message'  => __("admin/orders/success3-cancelled"),
                    'redirect' => $this->AdminCRLink("orders-2", ["detail", $order["id"]]) . "?content=detail",
                ]);

            } else
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error4"),
                ]));
        }


        private function delete_delivery_file($id = 0)
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();

            $set_order = [];
            $set_oroptions = $order["options"];

            if ($order["type"] == "special" || $order["type"] == "software") {
                if (isset($set_oroptions["delivery_file"])) {
                    $folder = RESOURCE_DIR . "uploads" . DS . "orders" . DS;
                    FileManager::file_delete($folder . $set_oroptions["delivery_file"]);
                    unset($set_oroptions["delivery_file"]);
                }
            }

            if ($set_oroptions != $order["options"]) $set_order["options"] = Utility::jencode($set_oroptions);

            $updates = 0;

            if ($set_order) {
                $this->model->set_order($id, $set_order);
                $updates++;
            }

            if ($updates) {
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "updated-order-detail", [
                    'id'   => $id,
                    'name' => $order["name"],
                ]);
            }

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/orders/success2"),
            ]);
        }


        private function apply_operation()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $type = Filter::init("POST/type", "route");
            $id = Filter::POST("id");
            $from = Filter::init("POST/from", "letters");

            if (Validation::isEmpty($type) || !$id) die("Invalid params");

            if (!is_array($id)) $id = [$id];
            if (!$id) die();
            Helper::Load(["Orders", "Invoices", "Products", "Money", "Notification", "Events"]);

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

            $adata = UserManager::LoginData("admin");

            $apply_on_module = (bool)(int)Filter::init("POST/apply_on_module", "numbers");

            foreach ($id as $k => $v) {
                $v = (int)Filter::numbers($v);
                $order = $this->get_order($v);
                if ($order && $type != $order["status"]) {
                    if ($type == "mark-read" || $type == "activation-message" || $type == "approve" || $type == "active" || $type == "cancelled" || $type == "suspended" || $type == "delete") {
                        if ($type == "activation-message" && ($order["type"] == "server" || $order["type"] == "hosting"))
                            $apply = Notification::order_hosting_server_activation($order);
                        elseif ($type == "mark-read") {
                            $apply = Events::apply_approved('operation', 'order', $v, false, 'pending');
                            Orders::set($v, ['status_msg' => '', 'unread' => 1]);
                        } else
                            $apply = Orders::MakeOperation($type, $order, false, true, $apply_on_module);

                        if ($apply) {
                            $successful++;
                            if ($type == "activation-message")
                                User::addAction($adata["id"], "alteration", "activation-message-sent", [
                                    'id'   => $v,
                                    'name' => $order["name"],
                                ]);
                            if ($type == "approve")
                                User::addAction($adata["id"], "alteration", "approved-order", [
                                    'id'   => $v,
                                    'name' => $order["name"],
                                ]);
                            elseif ($type == "active")
                                User::addAction($adata["id"], "alteration", "activated-order", [
                                    'id'   => $v,
                                    'name' => $order["name"],
                                ]);
                            elseif ($type == "suspended")
                                User::addAction($adata["id"], "alteration", "suspended-order", [
                                    'id'   => $v,
                                    'name' => $order["name"],
                                ]);
                            elseif ($type == "cancelled")
                                User::addAction($adata["id"], "alteration", "cancelled-order", [
                                    'id'   => $v,
                                    'name' => $order["name"],
                                ]);
                            elseif ($type == "delete")
                                User::addAction($adata["id"], "deleted", "deleted-order", [
                                    'id'   => $v,
                                    'name' => $order["name"],
                                ]);
                            elseif ($type == "approve")
                                User::addAction($adata["id"], "alteration", "Marked Read  Order #" . $v, [
                                    'id'   => $v,
                                    'name' => $order["name"],
                                ]);
                        } else $failed++;
                    } else $failed++;
                } else $failed++;
            }

            if ($from == "detail") {
                if ($successful)
                    echo Utility::jencode([
                        'status'   => "successful",
                        'message'  => __("admin/orders/success3-" . $type),
                        'redirect' => $type == "delete" ? $this->AdminCRLink("orders") : $this->AdminCRLink("orders-2", ['detail', $id[0]]),
                    ]);
                else
                    echo Utility::jencode([
                        'status'  => "error",
                        'for'     => "status",
                        'message' => Orders::$message ? Orders::$message : __("admin/orders/error1"),
                    ]);
            } elseif ($from == "list") {
                if ($successful)
                    echo Utility::jencode([
                        'status'  => "successful",
                        'message' => __("admin/orders/success4-" . $type),
                    ]);
                else
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error1"),
                    ]);
            }
        }


        private function apply_operation_updowngrades()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $type = Filter::init("POST/type", "letters");
            $id = Filter::POST("id");

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

            $adata = UserManager::LoginData("admin");

            foreach ($id as $k => $v) {
                $v = (int)Filter::numbers($v);
                $updown = $this->model->get_updowngrade($v);
                if ($updown && $type != $updown["status"]) {
                    $options = Utility::jdecode($updown["options"], true);
                    if ($type == "delete") {
                        $apply = $this->model->delete_updowngrade($v);
                        if ($apply) {
                            $successful++;
                            User::addAction($adata["id"], "deleted", "deleted-updown-request", [
                                'id' => $v,
                            ]);
                        } else $failed++;
                    } elseif ($type == "completed") {
                        $order = Orders::get($updown["owner_id"]);
                        $apply = Orders::updown($order, $updown["type"], [
                            'new_pid'         => $updown["new_pid"],
                            'new_period'      => $options["new_period"],
                            'new_period_time' => $options["new_period_time"],
                            'new_amount'      => $options["new_amount"],
                        ]);
                        if ($apply) {

                            if ($apply["status"] == "inprocess" && $apply["status_msg"])
                                $this->model->set_updowngrade($v, $apply);
                            else {
                                $this->model->set_updowngrade($v, ['status' => "completed", 'status_msg' => '', 'unread' => 1]);

                                Orders::MakeOperation("active", $order["id"]);

                                $successful++;
                                User::addAction($adata["id"], "alteration", "approved-updown-request", [
                                    'id'     => $v,
                                    'status' => $type,
                                ]);

                            }
                        } else {
                            $this->model->set_updowngrade($v, ['status_msg' => Orders::$message]);
                            $failed++;
                        }
                    } else $failed++;
                } else $failed++;
            }

            if ($successful)
                echo Utility::jencode([
                    'status'  => "successful",
                    'message' => __("admin/orders/success23-" . $type),
                ]);
            else
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error1"),
                ]);

        }


        private function apply_operation_cancellation()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $type = Filter::init("POST/type", "letters");
            $id = Filter::POST("id");

            if (Validation::isEmpty($type) || !$id) die("Invalid params");

            if (!is_array($id)) $id = [$id];
            if (!$id) die();
            Helper::Load(["Orders", "Invoices", "Products", "Money", "Events"]);

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

            $adata = UserManager::LoginData("admin");

            foreach ($id as $k => $v) {
                $v = (int)Filter::numbers($v);
                $event = Events::get($v);
                if ($event && $type != $event["status"]) {

                    if ($type == "delete") {
                        $apply = Events::delete($v);
                        if ($apply) {
                            $successful++;
                            User::addAction($adata["id"], "deleted", "deleted-order-cancellation-request", [
                                'id' => $v,
                            ]);
                        } else $failed++;
                    } elseif ($type == "approve") {
                        $data = $event["data"];
                        $order = Orders::get($event["owner_id"]);
                        if ($data["urgency"] == "now") {
                            $apply = Orders::MakeOperation("cancelled", $order, false, true);
                            if ($apply) {
                                User::addAction(0, "alteration", "cancelled-order", [
                                    'id'   => $order["id"],
                                    'name' => $order["name"],
                                ]);
                                Events::approved($v);
                            }
                        } else {
                            $cancel_invoice = Orders::cancel_order_invoices($order);
                            if (!$cancel_invoice) {
                                echo Utility::jencode([
                                    'status'  => "error",
                                    'message' => Orders::$message,
                                ]);
                                return false;
                            }

                            $apply = Events::approved($v);

                        }

                        if ($apply) {
                            $successful++;
                            User::addAction($adata["id"], "alteration", "approved-order-cancellation-request", [
                                'order_name' => $order["name"],
                                'order_id'   => $order["id"],
                                'id'         => $v,
                            ]);
                        } else $failed++;
                    } else $failed++;
                } else $failed++;
            }

            if ($successful)
                echo Utility::jencode([
                    'status'  => "successful",
                    'message' => __("admin/orders/success26-" . $type),
                ]);
            else
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error1"),
                ]);

        }


        private function apply_operation_addons()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $type = Filter::init("POST/type", "letters");
            $id = Filter::POST("id");

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

            $adata = UserManager::LoginData("admin");

            foreach ($id as $k => $v) {
                $v = (int)Filter::numbers($v);
                $addon = $this->model->get_addon($v);
                if ($addon && $type != $addon["status"]) {
                    if ($type == "delete") {
                        $apply = Orders::MakeOperationAddon('delete', $addon["owner_id"], $addon["id"]);
                        if ($apply) {
                            if ($addon["addon_key"] == "whois-privacy") {
                                $order = Orders::get($addon["owner_id"], "id,options");
                                $options = $order["options"];
                                if (isset($options["whois_privacy"])) unset($options["whois_privacy"]);
                                if (isset($options["whois_privacy_endtime"])) unset($options["whois_privacy_endtime"]);
                                Orders::set($order["id"], ['options' => Utility::jencode($options)]);
                            }

                            $successful++;
                            User::addAction($adata["id"], "deleted", "deleted-order-addon", [
                                'id' => $v,
                            ]);
                        } else $failed++;
                    } else {
                        $apply = Orders::MakeOperationAddon($type, $addon["owner_id"], $v);
                        if ($apply) {
                            $addon = Orders::get_addon($v);
                            $successful++;

                            if ($type == "active")
                                User::addAction($adata["id"], "alteration", "order-addon-has-been-activated", [
                                    'order_id' => $addon["owner_id"],
                                    'id'       => $v,
                                ]);
                        } else $failed++;
                    }
                } else $failed++;
            }

            if ($successful)
                echo Utility::jencode([
                    'status'  => "successful",
                    'message' => __("admin/orders/success24-" . $type),
                ]);
            else
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error1"),
                ]);
        }


        private function requirement_file_download()
        {
            $rid = (int)Filter::init("GET/rid", "numbers");
            $key = (int)Filter::init("GET/key", "numbers");
            if (!$rid) die();

            $requirement = $this->model->get_requirement($rid);
            if (!$requirement) die();
            if (!$requirement["response"]) die();

            $response = $requirement["response"];
            $response = Utility::jdecode($response, true);
            if (!isset($response[$key])) die();
            $re = $response[$key];
            $file = RESOURCE_DIR . "uploads" . DS . "product-requirements" . DS . $re["file_path"];

            $quoted = $re["file_name"];
            $size = filesize($file);

            echo FileManager::file_read($file);

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . $quoted);
            header('Content-Transfer-Encoding: binary');
            header('Connection: Keep-Alive');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . $size);

        }


        private function hosting_informations($id = 0)
        {
            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];

            if (!$order["module"] || $order["module"] == "none") return false;
            $server_id = $options["server_id"];

            $this->takeDatas("language");

            Helper::Load(["Products"]);

            $server = Products::get_server($server_id);
            if (!$server) return false;

            $mname = $server["type"];

            Modules::Load("Servers", $mname);
            if (!class_exists($mname . "_Module")) return false;
            $cname = $mname . "_Module";

            if (isset($options["config"]["password"]) && $options["config"]["password"])
                $options["config"]["password"] = Crypt::decode($options["config"]["password"], Config::get("crypt/user"));


            $order["options"] = $options;
            $config = isset($options["config"]) ? $options["config"] : [];

            $module = new $cname($server, $options);

            if (method_exists($module, 'set_order')) $module->set_order($order);

            $result = [];

            if ($config) {
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

            echo Utility::jencode($result);
        }


        private function hosting_creation_info($id = 0)
        {
            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];

            if (!$order["module"] || $order["module"] == "none") return false;
            $server_id = $options["server_id"];

            $this->takeDatas("language");

            Helper::Load(["Products", "Orders"]);

            $server = Products::get_server($server_id);
            if (!$server) return false;

            $mname = $server["type"];

            Modules::Load("Servers", $mname);
            if (!class_exists($mname . "_Module")) return false;
            $cname = $mname . "_Module";

            $module = new $cname($server, $options);

            if (method_exists($module, "set_order")) $module->set_order($order);
            $module->area_link = $this->AdminCRLink("orders-2", ["detail", $id]);

            $data = [
                'module' => $module,
                'order'  => $order && isset($order) && $order ? $order : false,
            ];
            echo Modules::getPage("Servers", $server["type"], "order-detail", $data);

        }


        private function get_sms_config_data($id = 0)
        {
            $order = $this->get_order($id);
            if (!$order) die();

            $mname = Filter::init("POST/module", "route");

            if (!$mname || $mname == "none") return false;

            $this->takeDatas("language");

            Helper::Load(["Products"]);

            Modules::Load("SMS", $mname);
            if (!class_exists($mname)) return false;

            $module = new $mname();

            $data = [
                'module' => $module,
                'order'  => $order && isset($order) && $order ? $order : false,
            ];
            echo Modules::getPage("SMS", $mname, "order-detail", $data);

        }


        private function status_sms_origin()
        {

            $this->takeDatas("language");

            $oid = Filter::init("POST/id", "numbers");
            $status = Filter::init("POST/status", "letters");
            $note = Filter::init("POST/note");

            $origin = $this->model->get_origin($oid);

            if (!$origin) return false;

            if ($origin["status"] == $status) return false;

            $id = $origin["pid"];

            $transaction = false;

            Helper::Load(["Notification"]);

            if ($status == "inactive") {
                $transaction = $this->model->set_origin($oid, [
                    'status'         => "inactive",
                    'status_message' => $note,
                ]);

                Notification::sms_origin_has_been_inactivated($id, $origin["name"], $note);
            }

            if ($status == "active") {
                $transaction = $this->model->set_origin($oid, [
                    'status'         => "active",
                    'status_message' => __("admin/orders/sms-origin-active-note", ['{date}' => DateManager::format(Config::get("options/date-format") . " H:i")]),
                    'approved_date'  => DateManager::Now(),
                ]);

                Notification::sms_origin_has_been_approved($id, $origin["name"]);

            }

            if ($status == "delete") $transaction = $this->model->delete_origin($oid);

            if (!$transaction)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "İşlem Yapılamadı",
                ]));

            echo Utility::jencode(['status' => "successful"]);

        }


        private function update_sms_origin($id = 0)
        {

            $this->takeDatas("language");

            $oid = Filter::init("POST/oid", "numbers");
            $status = Filter::init("POST/status", "letters");
            $status_message = Filter::init("POST/status_message");
            $name = Filter::init("POST/name", "hclear");
            $cdate = Filter::init("POST/cdate", "hclear");
            $approved_date = Filter::init("POST/approved_date", "hclear");
            $cdate = str_replace("T", " ", $cdate);
            $approved_date = str_replace("T", " ", $approved_date);
            $attachments = Filter::FILES("attachments");


            $get = $this->model->get_origin($oid);
            if (!$get) return false;

            if (isset($get["attachments"]) && $get["attachments"]) $get_attachments = Utility::jdecode($get["attachments"], true);
            else $get_attachments = [];

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
                        'message' => __("website/account_products/failed-attachment-upload", ['{error}' => $upload->error]),
                    ]));
                if ($upload->operands) foreach ($upload->operands as $operand) $get_attachments[] = $operand;
            }


            if (!$approved_date && $status == "active" && $get["status"] != "active") $approved_date = DateManager::Now();
            elseif (!$approved_date) $approved_date = DateManager::ata();
            else $approved_date .= ":00";

            if ($status == "active" && $get["status"] != "active" && $status_message == '')
                $status_message = __("admin/orders/sms-origin-active-note", ['{date}' => DateManager::format(Config::get("options/date-format") . " H:i")]);

            $this->model->set_origin($oid, [
                'status'         => $status,
                'status_message' => $status_message,
                'name'           => $name,
                'ctime'          => $cdate . ":00",
                'approved_date'  => $approved_date,
                'attachments'    => $get_attachments ? Utility::jencode($get_attachments) : '',
            ]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-origin-detail", [
                'order_id'  => $id,
                'origin_id' => $oid,
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'redirect' => $this->AdminCRLink("orders-2", ["detail", $id]) . "?content=origins",
                'message'  => __("admin/orders/success20"),
            ]);

        }


        private function hosting_transactions($id = 0)
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];

            Helper::Load(["Products", "Orders"]);

            $transaction = Filter::init("POST/transaction", "route");
            $output = ['status' => "successful"];

            $adata = UserManager::LoginData("admin");

            if ($transaction == "reset-password") { // Reset Password START
                $rstpw_ntf = (int)Filter::init("POST/rstpwd_ntf", "numbers");
                $new_password = Orders::generate_password(12);


                if (isset($options["server_id"])) {
                    Helper::Load("Products");
                    $server = Products::get_server($options["server_id"]);
                    if ($server["type"] == "Plesk")
                        $new_password = Orders::generate_password(5) . Orders::generate_password(3, false, 'l') . Orders::generate_password_force(3, false, 's') . Orders::generate_password(2, false, 'u') . Orders::generate_password(3, false, 'd');
                }


                $cpassword = Crypt::encode($new_password, Config::get("crypt/user"));
                $options["config"]["password"] = $cpassword;
                if (isset($options["ftp_info"]["username"]) && $options["ftp_info"]["username"])
                    $options["ftp_info"]["password"] = $cpassword;

                Orders::set($order["id"], ['options' => Utility::jencode($options)]);


                $process = Orders::ModuleHandler($order, false, "change-password", [
                    'password' => $new_password,
                ]);

                if ($process == "successful") {

                    $output["for"] = "reset-password";
                    $output["password"] = $new_password;
                    $output["message"] = __("admin/orders/success8");

                    Helper::Load(["Notification"]);

                    if ($rstpw_ntf) Notification::order_hosting_server_activation($order["id"]);

                    User::addAction($adata["id"], "alteration", "hosting-password-has-been-reset", ['id' => $id]);

                    $adata = UserManager::LoginData("admin");

                    Orders::add_history($adata['id'], $order['id'], 'hosting-order-password-changed');


                } else {
                    $old_pw = Crypt::decode($options["config"]["password"], Config::get("crypt/user"));
                    $cpassword = Crypt::encode($old_pw, Config::get("crypt/user"));
                    $options["config"]["password"] = $cpassword;
                    if (isset($options["ftp_info"]["username"]) && $options["ftp_info"]["username"])
                        $options["ftp_info"]["password"] = $cpassword;


                    Orders::set($order["id"], ['options' => Utility::jencode($options)]);

                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error10", ['{error}' => Orders::$message]),
                    ]));
                }
            } // Reset Password END

            if ($transaction == "re-install") { // Re-Install START

                $process = Orders::ModuleHandler($order, false, "re-install");

                if ($process == "successful") {

                    $output["message"] = __("admin/orders/success9");
                    $output["redirect"] = $this->AdminCRLink("orders-2", ["detail", $id]) . "?content=hosting";

                    User::addAction($adata["id"], "alteration", "hosting-has-been-re-established", ['id' => $id]);

                    $adata = UserManager::LoginData("admin");

                    Orders::add_history($adata['id'], $order['id'], 'hosting-order-re-installed');

                } else
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error10", ['{error}' => Orders::$message]),
                    ]));

            } // Re-Install END

            if ($transaction == "terminate") { // Terminate START

                $process = Orders::ModuleHandler($order, false, "terminate");

                if ($process == "successful") {

                    Orders::MakeOperation("cancelled", $order);

                    $output["message"] = __("admin/orders/success10");
                    $output["redirect"] = $this->AdminCRLink("orders-2", ["detail", $id]) . "?content=hosting";

                    User::addAction($adata["id"], "alteration", "hosting-has-been-removed", ['id' => $id]);

                    $adata = UserManager::LoginData("admin");

                    Orders::add_history($adata['id'], $order['id'], 'hosting-order-terminated');

                } else
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error10", ['{error}' => Orders::$message]),
                    ]));

            } // Terminate END

            echo Utility::jencode($output);
        }


        private function domain_send_transfer_code($id = 0)
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];

            Helper::Load(["Products", "Orders", "Notification"]);

            $code = Filter::init("POST/code", "hclear");

            $module_name = $order["module"];

            /*
            if($module_name && $module_name != "none" && !$code)
            {
                if(Modules::Load("Registrars",$order["module"])){
                    $module = new $module_name();
                    if(method_exists($module,'set_order')) $module->set_order($order);

                    $code     = $module->getAuthCode($options);
                    if(!$code)
                        die(Utility::jencode([
                            'status' => "error",
                            'message' => __("admin/orders/error10",['{error}' => $module->error]),
                        ]));
                }

            }

            */


            $submit = Notification::domain_submit_transfer_code($order, $code);

            if ($submit != "OK")
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error12"),
                ]));

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/orders/success11"),
            ]);

        }


        private function update_whois($id = 0)
        {
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];

            if ($order["module"] != "none" && $order["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $order["module"])) {
                    $module = new $order["module"]();
                }
            }


            $data = [];


            $apply_to_all = Filter::init("POST/apply_to_all");

            foreach ($this->contact_types as $ct) {
                $full_name = Filter::init("POST/info/" . $ct . "/Name", "hclear");
                $company_name = Filter::init("POST/info/" . $ct . "/Company", "hclear");
                $email = Filter::init("POST/info/" . $ct . "/EMail", "email");
                $pcountry_code = Filter::init("POST/info/" . $ct . "/PhoneCountryCode", "numbers");
                $phone = Filter::init("POST/info/" . $ct . "/Phone", "numbers");
                $fcountry_code = Filter::init("POST/info/" . $ct . "/FaxCountryCode", "numbers");
                $fax = Filter::init("POST/info/" . $ct . "/Fax", "numbers");
                $address = Filter::html_clear(Filter::init("POST/info/" . $ct . "/Address"));
                $city = Filter::init("POST/info/" . $ct . "/City", "hclear");
                $state = Filter::init("POST/info/" . $ct . "/State", "hclear");
                $zipcode = Filter::init("POST/info/" . $ct . "/ZipCode", "hclear");
                $country_code = Filter::init("POST/info/" . $ct . "/Country", "letters");

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

                $validation = !$apply_to_all || (isset($apply_to_all[$ct]) && $apply_to_all[$ct]);

                if ($validation) {
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
                    )
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/account_products/modify-whois-error1"),
                        ]));

                    if (!Validation::isEmail($email))
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/account_products/modify-whois-error2"),
                        ]));
                }


                $names = Filter::name_smash($full_name);
                $first_name = $names["first"];
                $last_name = $names["last"];
                if (Utility::strlen($address) > 64) {
                    $address1 = Utility::short_text($address, 0, 64);
                    $address2 = Utility::short_text($address, 64, 64);
                } else {
                    $address1 = $address;
                    $address2 = null;
                }

                $data[$ct] = [
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

            if ($apply_to_all && is_array($apply_to_all)) {
                foreach ($apply_to_all as $ct => $ok) {
                    $ct = Filter::letters($ct);
                    if (!in_array($ct, $this->contact_types)) continue;
                    $data_x = $data[$ct];
                    foreach ($this->contact_types as $c) $data[$c] = $data_x;
                }
            }


            if ($h_operations = Hook::run("DomainWhoisChange", ['order' => $order, 'whois' => $data]))
                foreach ($h_operations as $h_operation)
                    if ($h_operation && isset($h_operation["error"]) && $h_operation["error"])
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => $h_operation["error"],
                        ]));

            $apply_module = true;

            if ($order["status"] == "inprocess" && (!isset($options["config"]) || !$options["config"]))
                $apply_module = false;

            if ($apply_module && $module && method_exists($module, 'ModifyWhois')) {

                $wh_data = $data;

                if (isset($wh_data["registrant"])) {
                    if (!isset($module->config["settings"]["whois-types"]) || !$module->config["settings"]["whois-types"]) $wh_data = $wh_data["registrant"];
                }


                $modify = $module->ModifyWhois($options, $wh_data);
                if (!$modify)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error10", ['{error}' => $module->error]),
                    ]));
            }


            $profile_ids = Filter::init("POST/profile_id");
            if ($profile_ids && is_array($profile_ids)) {
                $apply_all_profile_id = 0;

                foreach ($profile_ids as $ct => $profile_id) {
                    $profile_id = Filter::letters_numbers($profile_id);
                    if ($apply_all_profile_id) $profile_id = $apply_all_profile_id;
                    $ct = Filter::letters($ct);
                    if (!in_array($ct, $this->contact_types)) continue;
                    $profile_name = Filter::init("POST/profile_name/" . $ct, "hclear");
                    if ($profile_id == "new") {

                        if (Validation::isEmpty($profile_name)) $profile_name = 'Untitled';

                        $profile_id = User::create_whois_profile([
                            'owner_id'    => $order["owner_id"],
                            'detouse'     => 0,
                            'name'        => $profile_name,
                            'information' => Utility::jencode($data[$ct]),
                            'created_at'  => DateManager::Now(),
                            'updated_at'  => DateManager::Now(),
                        ]);
                    }
                    $data[$ct]["profile_id"] = $profile_id;
                    if (isset($apply_to_all[$ct]) && $apply_to_all[$ct]) {
                        $apply_all_profile_id = $profile_id;
                        foreach ($this->contact_types as $c) {
                            $data[$c]["profile_id"] = $profile_id;
                        }
                    }
                }
                $rows = User::whois_profiles($order["owner_id"]);
                if ($rows) {
                    $has_detouse = 0;
                    foreach ($rows as $row) if ($row["detouse"] == 1) $has_detouse = $row["id"];
                    if ($has_detouse < 1) {
                        User::remove_detouse_whois_profile($order["owner_id"]);
                        User::set_whois_profile($rows[0]["id"], ['detouse' => 1]);
                    }
                }
            }


            $options["whois"] = $data;
            $options = Utility::jencode($options);
            $this->model->set_order($id, [
                'options' => $options,
            ]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-domain-whois", [
                'id'   => $id,
                'name' => $order["name"],
            ]);

            $order = Orders::get($id);

            Hook::run("DomainWhoisChanged", $order);


            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/orders/success12"),
            ]);

        }


        private function update_whois_privacy($id = 0)
        {
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];

            $module = false;
            if ($order["module"] != "none" && $order["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $order["module"])) {
                    $module = new $order["module"]();
                }
            }

            $status = Filter::init("POST/status", "letters");

            Helper::Load(["Products", "Orders", "Invoices", "Money"]);

            $wprivacy = isset($options["whois_privacy"]) && $options["whois_privacy"];
            $whidden_amount = Config::get("options/domain-whois-privacy/amount");
            $whidden_cid = Config::get("options/domain-whois-privacy/cid");

            if (isset($fetchModule) && $fetchModule) {
                $whidden_amount = $fetchModule["config"]["settings"]["whidden-amount"];
                $whidden_cid = $fetchModule["config"]["settings"]["whidden-currency"];
            }
            $whois_privacy_price = 0;
            if ($whidden_amount)
                $whois_privacy_price = Money::exChange($whidden_amount, $whidden_cid, $order["amount_cid"]);

            $whois_privacy_purchase = $whidden_amount > 0.00;

            if ($whois_privacy_purchase) {
                $isAddon = WDB::select("id")->from("users_products_addons");
                $isAddon->where("status", "=", "active", "&&");
                $isAddon->where("owner_id", "=", $order["id"], "&&");
                $isAddon->where("addon_key", "=", "whois-privacy");
                $isAddon = $isAddon->build() ? $isAddon->getObject()->id : false;
                if ($isAddon) $whois_privacy_purchase = false;
            }

            if ($status == "enable") {
                $istatus = Filter::init("POST/invoice_status", "letters");
                $pmethod = Filter::init("POST/pmethod", "route");

                if ($whois_privacy_purchase && $istatus == "unpaid" || $istatus == "paid") {
                    $invoice = Invoices::set_wpp($order, $whois_privacy_price, $istatus, $pmethod);
                    if (!$invoice && Invoices::$message == "repetition")
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("admin/orders/error13"),
                        ]));
                    if (!$invoice && Invoices::$message == "no-user-address")
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("admin/orders/error14"),
                        ]));

                    if ($istatus == "unpaid" && $invoice) {
                        echo Utility::jencode([
                            'status'  => "successful",
                            'message' => __("admin/orders/success13"),
                        ]);

                        $adata = UserManager::LoginData("admin");
                        User::addAction($adata["id"], "added", "added-new-invoice", [
                            'id' => $invoice["id"],
                        ]);
                        return false;
                    }
                }

                if (isset($module) && $module) {
                    if ($whois_privacy_purchase)
                        $modify = $module->purchasePrivacyProtection($options);
                    else
                        $modify = $module->modifyPrivacyProtection($options, "enable");

                    if (!$modify)
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("admin/orders/error10", ['{error}' => $module->error]),
                        ]));
                }

                $options["whois_privacy"] = true;
                if ($whois_privacy_price) {
                    $options["whois_privacy_endtime"] = DateManager::next_date(['year' => 1]);

                    $udata = User::getData($order["owner_id"], "id,lang", "array");
                    $ulang = $udata["lang"];

                    $isAddon = Models::$init->db->select("id")->from("users_products_addons");
                    $isAddon->where("owner_id", "=", $order["id"], "&&");
                    $isAddon->where("addon_key", "=", "whois-privacy");
                    $isAddon = $isAddon->build() ? $isAddon->getObject()->id : false;

                    if ($isAddon) {
                        Orders::set_addon($isAddon, [
                            "invoice_id"  => isset($invoice) && $invoice ? $invoice["id"] : 0,
                            "renewaldate" => DateManager::Now(),
                            "duedate"     => $options["whois_privacy_endtime"],
                            "amount"      => $whois_privacy_price,
                            "cid"         => $order["amount_cid"],
                            "status"      => "active",
                            "unread"      => 1,
                        ]);
                    } else
                        Orders::insert_addon([
                            "invoice_id"  => isset($invoice) && $invoice ? $invoice["id"] : 0,
                            "owner_id"    => $order["id"],
                            "addon_key"   => "whois-privacy",
                            "addon_id"    => 0,
                            "addon_name"  => Bootstrap::$lang->get_cm("website/account_products/whois-privacy", false, $ulang),
                            "option_id"   => 0,
                            "option_name" => Bootstrap::$lang->get("needs/iwwant", $ulang),
                            "period"      => "year",
                            "period_time" => 1,
                            "cdate"       => DateManager::Now(),
                            "renewaldate" => DateManager::Now(),
                            "duedate"     => $options["whois_privacy_endtime"],
                            "amount"      => $whois_privacy_price,
                            "cid"         => $order["amount_cid"],
                            "status"      => "active",
                            "unread"      => 1,
                        ]);

                }

                $this->model->set_order($id, [
                    'options' => Utility::jencode($options),
                ]);


                echo Utility::jencode([
                    'status'   => "successful",
                    'message'  => __("admin/orders/success14"),
                    'redirect' => $this->AdminCRLink("orders-2", ["detail", $id]) . "?content=whois",
                ]);

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "changed-domain-whois-privacy", [
                    'id'     => $order["id"],
                    'status' => $status,
                ]);


            } // Whois privacy is enable

            if ($status == "disable") { // Whois privacy is disable

                if (isset($module) && $module) {
                    $modify = $module->modifyPrivacyProtection($options, "disable");
                    if (!$modify)
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("admin/orders/error10", ['{error}' => $module->error]),
                        ]));
                }

                $options["whois_privacy"] = false;
                $this->model->set_order($id, [
                    'options' => Utility::jencode($options),
                ]);

                echo Utility::jencode([
                    'status'   => "successful",
                    'message'  => __("admin/orders/success14"),
                    'redirect' => $this->AdminCRLink("orders-2", ["detail", $id]) . "?content=whois",
                ]);

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "changed-domain-whois-privacy", [
                    'id'     => $order["id"],
                    'status' => $status,
                ]);

            } // Whois privacy is disable
        }


        private function delete_domain_doc($id = 0)
        {
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];


            $id = (int)Filter::init("POST/id", "numbers");

            $doc = $this->model->db->select()->from("users_products_docs");
            $doc->where("id", "=", $id, "&&");
            $doc->where("owner_id", "=", $order["id"]);
            $doc = $doc->build() ? $doc->getAssoc() : [];

            if (!$doc) {
                echo 'Not found Document';
                return false;
            }


            $doc["file"] = Crypt::decode($doc["file"], Config::get("crypt/user"));
            $doc["file"] = Utility::jdecode($doc["file"], true);
            $f = $doc["file"] ?? [];
            $file = $f["path"] ?? '';

            if ($file) FileManager::file_delete($file);


            $this->model->db->delete("users_products_docs")->where("id", "=", $id)->run();

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/financial/success7"),
            ]);
        }


        private function domain_verification($id = 0)
        {
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];

            $docs = Filter::init("POST/docs");

            if ($docs) {
                foreach ($docs as $id => $d) {
                    $doc_id = Filter::route($d["doc_id"] ?? '');
                    $name = Filter::html_clear($d["name"] ?? '');
                    $value = Filter::html_clear($d["value"]);
                    $status = Filter::letters_numbers($d["status"] ?? 'pending');
                    $status_msg = Filter::html_clear($d["status_msg"] ?? '');

                    if ($value) $value = Crypt::encode($value, Config::get("crypt/user"));

                    $set_data = [
                        'doc_id'     => $doc_id,
                        'status'     => $status,
                        'status_msg' => $status_msg,
                        'value'      => $value,
                    ];
                    if (!Validation::isEmpty($name)) $set_data["name"] = $name;

                    $this->model->db->update("users_products_docs", $set_data)->where("id", "=", $id)->save();
                }
            }

            $operator_docs = Filter::init("POST/operator_docs");
            $new_ope_docs = [];

            if ($operator_docs) {
                foreach ($operator_docs as $o) {
                    $name = $o["name"] ?? '';
                    $type = $o["type"];
                    $allowed_ext = $o["allowed_ext"] ?? '';
                    $max_file_size = $o["max_file_size"] ?? '';
                    $opts = [];

                    if ($type == "file")
                        $opts = [
                            'allowed_ext'   => $allowed_ext,
                            'max_file_size' => $max_file_size,
                        ];

                    $op_data = [
                        'name' => $name,
                        'type' => $type,
                    ];
                    if ($opts) $op_data["options"] = $opts;

                    $new_ope_docs[] = $op_data;

                }
                $options["verification_operator_docs"] = $new_ope_docs;
            } elseif (isset($options["verification_operator_docs"]))
                unset($options["verification_operator_docs"]);

            $verification_operator_note = Filter::init("POST/verification_operator_note", "hclear");

            if ($verification_operator_note)
                $options["verification_operator_note"] = $verification_operator_note;
            elseif (isset($options["verification_operator_note"]))
                unset($options["verification_operator_note"]);


            Orders::set($order["id"], ['options' => Utility::jencode($options)]);


            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/financial/success5"),
            ]);
        }


        private function download_domain_doc_file($id = 0)
        {
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];


            $id = (int)Filter::init("GET/id", "numbers");

            $doc = $this->model->db->select()->from("users_products_docs");
            $doc->where("id", "=", $id, "&&");
            $doc->where("owner_id", "=", $order["id"]);
            $doc = $doc->build() ? $doc->getAssoc() : [];

            if (!$doc) {
                echo 'Not found Document';
                return false;
            }


            $doc["file"] = Crypt::decode($doc["file"], Config::get("crypt/user"));
            $doc["file"] = Utility::jdecode($doc["file"], true);
            $f = $doc["file"];

            if (!$f) return false;


            $file = $f["path"];

            $quoted = $f["name"];
            $size = $f["size"];
            if (!$size) $size = filesize($file);

            echo FileManager::file_read($file, $size);

            $file_extension = strtolower(substr(strrchr($quoted, "."), 1));

            switch ($file_extension) {
                case "gif":
                    $ctype = "image/gif";
                    break;
                case "png":
                    $ctype = "image/png";
                    break;
                case "jpeg":
                case "jpg":
                    $ctype = "image/jpeg";
                    break;
                default:
                    $ctype = false;
            }

            if ($ctype)
                header('Content-type: ' . $ctype);
            else {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Transfer-Encoding: binary');
                header('Content-Disposition: attachment; filename=' . $quoted);
            }

            header('Connection: Keep-Alive');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . $size);

        }


        private function domain_modify_dns($id = 0)
        {
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];

            $module = false;
            if ($order["module"] != "none" && $order["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $order["module"])) {
                    $module = new $order["module"]();
                }
            }

            $dns = Filter::POST("dns");

            if (!is_array($dns)) return false;

            $new_dns = [];

            for ($i = 0; $i <= sizeof($dns) - 1; $i++) {
                $dn = isset($dns[$i]) ? $dns[$i] : false;
                if (!$dn) continue;
                $dn = Filter::domain($dn);
                /*if($dn && !Validation::NSCheck($dn))
                    die(Utility::jencode([
                        'status' => "error",
                        'for' => "input[name='dns[]']:eq(".$i.")",
                        'message' => __("website/account_products/error6"),
                    ]));*/
                $new_dns[] = $dn;
            }

            if (!isset($new_dns[0]) || !isset($new_dns[1]) || !($new_dns[0] && $new_dns[1]))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error31"),
                ]));


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


            if ($order["status"] != "inprocess" && isset($module) && $module) {
                $modifyDns = $module->ModifyDns($options, $new_dns);
                if (!$modifyDns)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error10", [
                            '{error}' => $module->error,
                        ]),
                    ]));
            }

            $this->model->set_order($id, ['options' => Utility::jencode($options)]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-domain-dns", [
                'id'   => $id,
                'name' => $order["name"],
            ]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/orders/success15"),
            ]);

        }


        private function domain_add_cns($id = 0)
        {
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];


            if ($order["module"] != "none" && $order["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $order["module"])) {
                    $module = new $order["module"]();
                }
            }

            $ns = Filter::init("POST/ns", "domain");
            $ip = Filter::init("POST/ip", "ip");

            if (Validation::isEmpty($ns) || Validation::isEmpty($ip))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/orders/error16"),
                ]));

            if (isset($module) && $module) {
                $addCNS = $module->addCNS($options, $ns, $ip);
                if (!$addCNS)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error10", [
                            '{error}' => $module->error,
                        ]),
                    ]));

                $cnsList = $module->CNSList($options);

                $options["cns_list"] = $cnsList;
            }


            $this->model->set_order($order["id"], ['options' => Utility::jencode($options)]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-domain-cns", [
                'id'   => $id,
                'name' => $order["name"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'redirect' => $this->AdminCRLink("orders-2", ['detail', $order["id"]]) . "?content=dns&for=cns",
                'message'  => __("admin/orders/success16"),
            ]);

        }


        private function domain_modify_cns($id = 0)
        {
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];


            if ($order["module"] != "none" && $order["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $order["module"])) {
                    $module = new $order["module"]();
                }
            }


            $cns_id = Filter::init("POST/id", "numbers");

            if (strlen($cns_id) < 1) return false;


            if (isset($module) && $module)
                $cns_list = $module->CNSList($options);
            else
                $cns_list = $options["cns_list"] ?? [];

            $cn_ns = Filter::init("POST/ns", "domain");
            $cn_ip = Filter::init("POST/ip", "ip");


            if (isset($module) && $module) {
                $cns = $cns_list[$cns_id] ?? [];

                $modify = $module->ModifyCNS($options, $cns, $cn_ns, $cn_ip);
                if (!$modify)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error10", ['{error}' => $module->error]),
                    ]));
            } else {
                $cns = $cns_list[$cns_id] ?? [];

                if ($cns["ns"] != $cn_ns || $cns["ip"] != $cn_ip) {
                    $options["cns_list"][$cns_id] = ['ns' => $cn_ns, 'ip' => $cn_ip];
                }
            }


            if (isset($module) && $module) {
                $cns_list = $module->CNSList($options);
                if (!$cns_list) $cns_list = [];
                $options["cns_list"] = $cns_list;
            }


            $this->model->set_order($order["id"], ['options' => Utility::jencode($options)]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-domain-cns", [
                'id'   => $id,
                'name' => $order["name"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'redirect' => $this->AdminCRLink("orders-2", ['detail', $order["id"]]) . "?content=dns&for=cns",
                'message'  => __("admin/orders/success17"),
            ]);

        }


        private function domain_delete_cns($id = 0)
        {
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];


            if ($order["module"] != "none" && $order["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $order["module"])) {
                    $module = new $order["module"]();
                }
            }

            $cns_id = (int)Filter::init("POST/id", "numbers");
            if (strlen($cns_id) < 1) return false;

            $cns_list = $module->CNSList($options);
            $cns = $cns_list[$cns_id];


            if (isset($module) && $module) {
                $delete = $module->DeleteCNS($options, $cns["ns"], $cns["ip"]);
                if (!$delete)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error10", ['{error}' => $module->error]),
                    ]));
            }

            $cns_list = $module->CNSList($options);

            if (!$cns_list) $cns_list = [];

            $options["cns_list"] = $cns_list;

            $this->model->set_order($order["id"], ['options' => Utility::jencode($options)]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "deleted", "deleted-domain-cns", [
                'id'       => $id,
                'cns-name' => $cns["ns"],
                'cns-ip'   => $cns["ip"],
                'name'     => $order["name"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'redirect' => $this->AdminCRLink("orders-2", ['detail', $order["id"]]) . "?content=dns&for=cns",
                'message'  => __("admin/orders/success18"),
            ]);

        }


        private function domain_add_dns_record($id = 0)
        {
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];


            if ($order["module"] != "none" && $order["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $order["module"])) {
                    $module = new $order["module"]();
                }
            }

            if (!isset($module)) return false;

            $type = Filter::init("POST/type", "letters_numbers");
            $name = Filter::init("POST/name", "hclear");
            $value = Filter::init("POST/value", "hclear");
            $ttl = Filter::init("POST/ttl", "numbers");
            $priority = Filter::init("POST/priority", "numbers");


            if (Validation::isEmpty($type) || Validation::isEmpty($name) || Validation::isEmpty($value))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-dns-records-12"),
                ]));


            if (!in_array($type, $fetchModule["config"]["settings"]["dns-record-types"] ?? []))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown dns type",
                ]));

            if (method_exists($module, "set_order")) $module->set_order($order);

            if (!method_exists($module, 'addDnsRecord'))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "addDnsRecord method not found in class",
                ]));


            $apply = $module->addDnsRecord($type, $name, $value, $ttl, $priority);

            if (!$apply)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]));


            $udata = UserManager::LoginData("admin");

            User::addAction($udata["id"], "alteration", "domain-dns-record-created", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);
        }

        private function domain_update_dns_record($id = 0)
        {
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];


            if ($order["module"] != "none" && $order["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $order["module"])) {
                    $module = new $order["module"]();
                }
            }

            if (!isset($module)) return false;

            $type = Filter::init("POST/type", "letters_numbers");
            $name = Filter::init("POST/name", "hclear");
            $value = Filter::init("POST/value", "hclear");
            $identity = Filter::init("POST/identity", "hclear");
            $ttl = Filter::init("POST/ttl", "numbers");
            $priority = Filter::init("POST/priority", "numbers");

            if (Validation::isEmpty($type) || Validation::isEmpty($name) || Validation::isEmpty($value))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-dns-records-12"),
                ]));

            if (!in_array($type, $fetchModule["config"]["settings"]["dns-record-types"] ?? []))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown dns type",
                ]));

            if (method_exists($module, "set_order")) $module->set_order($order);

            if (!method_exists($module, 'updateDnsRecord'))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "updateDnsRecord method not found in class",
                ]));

            $apply = $module->updateDnsRecord($type, $name, $value, $identity, $ttl, $priority);

            if (!$apply)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]));

            $udata = UserManager::LoginData("admin");

            User::addAction($udata["id"], "alteration", "domain-dns-record-updated", [
                'domain' => $options["domain"],
                'type'   => $type,
                'name'   => $name,
                'value'  => $value,
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);

        }

        private function domain_delete_dns_record($id = 0)
        {
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];


            if ($order["module"] != "none" && $order["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $order["module"])) {
                    $module = new $order["module"]();
                }
            }

            if (!isset($module)) return false;


            $type = Filter::init("POST/type", "letters_numbers");
            $name = Filter::init("POST/name", "hclear");
            $value = Filter::init("POST/value", "hclear");
            $identity = Filter::init("POST/identity", "hclear");


            if (Validation::isEmpty($type) || Validation::isEmpty($name) || Validation::isEmpty($value))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-dns-records-12"),
                ]));


            $options = $order["options"];
            if ($order["module"] == "none") return false;


            if (!in_array($type, $fetchModule["config"]["settings"]["dns-record-types"] ?? [])) {
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown dns type",
                ]));
            }

            if (method_exists($module, "set_order")) $module->set_order($order);

            if (!method_exists($module, 'deleteDnsRecord')) return false;

            $apply = $module->deleteDnsRecord($type, $name, $value, $identity);

            if (!$apply) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]);
                return false;
            }

            $udata = UserManager::LoginData("admin");

            User::addAction($udata["id"], "alteration", "domain-dns-record-deleted", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);

        }


        private function domain_add_dns_sec_record($id = 0)
        {
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];


            if ($order["module"] != "none" && $order["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $order["module"])) {
                    $module = new $order["module"]();
                }
            }

            if (!isset($module)) return false;

            $digest = Filter::init("POST/digest", "hclear");
            $key_tag = Filter::init("POST/key_tag", "hclear");
            $digest_type = Filter::init("POST/digest_type", "numbers");
            $algorithm = Filter::init("POST/algorithm", "numbers");


            if (Validation::isEmpty($digest) || Validation::isEmpty($key_tag) || Validation::isEmpty($digest_type) || Validation::isEmpty($algorithm))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-dns-records-12"),
                ]));


            if (!in_array($digest_type, array_keys($fetchModule["config"]["settings"]["dns-digest-types"]) ?? []))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown dns digest type",
                ]));

            if (!in_array($algorithm, array_keys($fetchModule["config"]["settings"]["dns-algorithms"]) ?? []))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown dns algorithm",
                ]));


            if (method_exists($module, "set_order")) $module->set_order($order);

            if (!method_exists($module, 'addDnsSecRecord'))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "addDnsRecord method not found in class",
                ]));

            $apply = $module->addDnsSecRecord($digest, $key_tag, $digest_type, $algorithm);

            if (!$apply)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]));

            $udata = UserManager::LoginData("admin");

            User::addAction($udata["id"], "alteration", "domain-dns-sec-record-created", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);

        }

        private function domain_delete_dns_sec_record($id = 0)
        {
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];


            if ($order["module"] != "none" && $order["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $order["module"])) {
                    $module = new $order["module"]();
                }
            }

            if (!isset($module)) return false;

            $digest = Filter::init("POST/digest", "hclear");
            $key_tag = Filter::init("POST/key_tag", "hclear");
            $digest_type = Filter::init("POST/digest_type", "numbers");
            $algorithm = Filter::init("POST/algorithm", "numbers");
            $identity = Filter::init("POST/identity", "hclear");


            if (Validation::isEmpty($digest) || Validation::isEmpty($key_tag) || Validation::isEmpty($digest_type) || Validation::isEmpty($algorithm))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-dns-records-12"),
                ]));


            if (!in_array($digest_type, array_keys($fetchModule["config"]["settings"]["dns-digest-types"]) ?? []))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown dns digest type",
                ]));

            if (!in_array($algorithm, array_keys($fetchModule["config"]["settings"]["dns-algorithms"]) ?? []))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown dns algorithm",
                ]));

            if (method_exists($module, "set_order")) $module->set_order($order);

            if (!method_exists($module, 'deleteDnsSecRecord')) return false;

            $apply = $module->deleteDnsSecRecord($digest, $key_tag, $digest_type, $algorithm, $identity);

            if (!$apply) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]);
                return false;
            }

            $udata = UserManager::LoginData("admin");

            User::addAction($udata["id"], "alteration", "domain-dns-sec-record-deleted", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);

        }


        private function domain_set_forward_domain($id = 0)
        {
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];


            if ($order["module"] != "none" && $order["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $order["module"])) {
                    $module = new $order["module"]();
                }
            }

            if (!isset($module)) return false;

            $protocol = Filter::init("POST/protocol", "letters");
            $method = Filter::init("POST/method", "numbers");
            $domain = str_replace(["https://", "http://"], "", Utility::strtolower(Filter::init("POST/domain")));

            if (stristr($domain, '/')) {
                $parse_domain = explode("/", $domain);
                $domain = $parse_domain[0];
            }
            $domain = Filter::domain($domain);

            if (Validation::isEmpty($protocol) || Validation::isEmpty($method) || Validation::isEmpty($domain))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-forwarding-tx13"),
                ]));

            if (!in_array($method, [301, 302])) $method = 301;
            if (!in_array($protocol, ["http", "https"])) $protocol = "http";

            if (method_exists($module, "set_order")) $module->set_order($order);

            if (!method_exists($module, 'setForwardingDomain')) return false;

            $apply = $module->setForwardingDomain($protocol, $method, $domain);

            if (!$apply) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]);
                return false;
            }


            $udata = UserManager::LoginData("admin");


            User::addAction($udata["id"], "alteration", "domain-set-forward-domain");

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("website/account_products/domain-forwarding-tx7"),
            ]);


        }

        private function domain_cancel_forward_domain($id = 0)
        {
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];


            if ($order["module"] != "none" && $order["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $order["module"])) {
                    $module = new $order["module"]();
                }
            }

            if (!isset($module)) return false;

            if (method_exists($module, "set_order")) $module->set_order($order);

            if (!method_exists($module, 'cancelForwardingDomain')) return false;

            $apply = $module->cancelForwardingDomain();

            if (!$apply) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]);
                return false;
            }

            $udata = UserManager::LoginData("admin");

            User::addAction($udata["id"], "alteration", "domain-set-forward-domain");

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("website/account_products/domain-forwarding-tx8"),
            ]);

        }

        private function domain_add_email_forward($id = 0)
        {
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];


            if ($order["module"] != "none" && $order["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $order["module"])) {
                    $module = new $order["module"]();
                }
            }

            if (!isset($module)) return false;

            $prefix = Filter::init("POST/prefix", "email");
            $target = Filter::init("POST/target", "email");


            if (Validation::isEmpty($prefix) || Validation::isEmpty($target))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-forwarding-tx20"),
                ]));

            if (method_exists($module, "set_order")) $module->set_order($order);

            if (!method_exists($module, 'addForwardingEmail'))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "addForwardingEmail method not found in class",
                ]));

            $apply = $module->addForwardingEmail($prefix, $target);

            if (!$apply)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]));

            $udata = UserManager::LoginData("admin");

            User::addAction($udata["id"], "alteration", "domain-email-forward-created", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);

        }

        private function domain_update_email_forward($id = 0)
        {
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];


            if ($order["module"] != "none" && $order["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $order["module"])) {
                    $module = new $order["module"]();
                }
            }

            if (!isset($module)) return false;

            $prefix = Filter::init("POST/prefix", "email");
            $target = Filter::init("POST/target", "email");
            $target_new = Filter::init("POST/target_new", "email");
            $identity = Filter::init("POST/identity", "hclear");


            if (Validation::isEmpty($prefix) || Validation::isEmpty($target))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-forwarding-tx20"),
                ]));


            if (method_exists($module, "set_order")) $module->set_order($order);

            if (!method_exists($module, 'updateForwardingEmail'))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "updateForwardingEmail method not found in class",
                ]));

            $apply = $module->updateForwardingEmail($prefix, $target, $target_new, $identity);

            if (!$apply)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]));

            $udata = UserManager::LoginData("admin");

            User::addAction($udata["id"], "alteration", "domain-email-forward-updated", [
                'domain' => $options["domain"],
                'prefix' => $prefix,
                'target' => $target,
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);

        }

        private function domain_delete_email_forward($id = 0)
        {
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];


            if ($order["module"] != "none" && $order["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $order["module"])) {
                    $module = new $order["module"]();
                }
            }

            if (!isset($module)) return false;

            $prefix = Filter::init("POST/prefix", "email");
            $target = Filter::init("POST/target", "email");
            $identity = Filter::init("POST/identity", "hclear");


            if (Validation::isEmpty($prefix) || Validation::isEmpty($target))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-forwarding-tx20"),
                ]));

            if (method_exists($module, "set_order")) $module->set_order($order);

            if (!method_exists($module, 'deleteForwardingEmail')) return false;

            $prefix_split = explode("@", $prefix);
            $prefix = $prefix_split[0] ?? '';


            $apply = $module->deleteForwardingEmail($prefix, $target, $identity);

            if (!$apply) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]);
                return false;
            }

            $udata = UserManager::LoginData("admin");

            User::addAction($udata["id"], "alteration", "domain-email-forward-deleted", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);

        }


        private function update_hosting($id = 0)
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];

            Helper::Load(["Products", "Orders"]);

            $config = Filter::POST("config");
            $creation_info = Filter::POST("creation_info");
            $domain = Filter::init("POST/domain", "domain");
            $panel_type = Filter::init("POST/panel_type", "hclear");
            $panel_link = Filter::init("POST/panel_link", "hclear");
            $disk_limit = Filter::init("POST/disk_limit", "numbers");
            $bandwidth_limit = Filter::init("POST/bandwidth_limit", "numbers");
            $email_limit = Filter::init("POST/email_limit", "numbers");
            $database_limit = Filter::init("POST/database_limit", "numbers");
            $addons_limit = Filter::init("POST/addons_limit", "numbers");
            $subdomain_limit = Filter::init("POST/subdomain_limit", "numbers");
            $ftp_limit = Filter::init("POST/ftp_limit", "numbers");
            $park_limit = Filter::init("POST/park_limit", "numbers");
            $max_email_per_hour = Filter::init("POST/max_email_per_hour", "numbers");
            $cpu_limit = Filter::init("POST/cpu_limit", "hclear");
            $server_features = Filter::POST("server_features");
            $dns = Filter::POST("dns");
            $ftp_raw = Filter::POST("ftp_raw");

            if (!$domain) $domain = $options["domain"];

            $module = false;
            if ($order["module"] && $order["module"] != "none") {
                $server_id = $options["server_id"];
                $server = Products::get_server($server_id);
                if ($server) {
                    $mname = $server["type"];
                    Modules::Load("Servers", $mname);
                    if (class_exists($mname . "_Module")) {
                        $cname = $mname . "_Module";
                        $oconfig = isset($options["config"]) ? $options["config"] : [];
                        $ooptions = $options;
                        if (!$oconfig && isset($config["user"]) && $config["user"]) {
                            $oconfig["user"] = $config["user"];
                            $ooptions["config"] = $oconfig;
                        }
                        $module = new $cname($server, $ooptions);
                        if (method_exists($module, "set_order")) $module->set_order($order);
                    }
                }
            }


            $set_options = array_merge($options, [
                'config'             => $config,
                'creation_info'      => $creation_info,
                'disk_limit'         => $disk_limit === '' ? "unlimited" : $disk_limit,
                'bandwidth_limit'    => $bandwidth_limit === '' ? "unlimited" : $bandwidth_limit,
                'email_limit'        => $email_limit === '' ? "unlimited" : $email_limit,
                'database_limit'     => $database_limit === '' ? "unlimited" : $database_limit,
                'addons_limit'       => $addons_limit === '' ? "unlimited" : $addons_limit,
                'subdomain_limit'    => $subdomain_limit === '' ? "unlimited" : $subdomain_limit,
                'ftp_limit'          => $ftp_limit === '' ? "unlimited" : $ftp_limit,
                'park_limit'         => $park_limit === '' ? "unlimited" : $park_limit,
                'max_email_per_hour' => $max_email_per_hour === '' ? "unlimited" : $max_email_per_hour,
                'cpu_limit'          => $cpu_limit ? $cpu_limit : null,
                'server_features'    => $server_features ? $server_features : null,
                'dns'                => $dns ? $dns : false,
                'panel_link'         => $panel_link,
            ]);

            if ($ftp_raw) $set_options["ftp_raw"] = $ftp_raw;

            if (!$dns) $set_options["dns"] = $options["dns"];

            if (!$module && $panel_type != $options["panel_type"]) $set_options['panel_type'] = $panel_type;
            if (!$module && (!isset($options["panel_link"]) || $panel_link != $set_options["panel_link"]))
                $set_options['panel_link'] = $panel_link;
            if ($domain != $options["domain"]) $set_options["domain"] = $domain;


            if ($module && method_exists($module, "apply_options")) {

                $apply = $module->apply_options($options, $set_options);

                if (!$apply)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/orders/error10", ['{error}' => $module->error]),
                    ]));

                $set_options = $apply;
            } else {
                unset($set_options["config"]);
                unset($set_options["ftp_info"]);
                unset($options["config"]);
                unset($options["ftp_info"]);
            }


            $sets = [];

            if (isset($set_options["config"]) && $set_options["config"] && is_array($set_options["config"])) {
                $config_is_full = false;
                foreach ($set_options["config"] as $k => $v) if ($v) $config_is_full = true;
                if (!$config_is_full) unset($set_options["config"]);
            }

            $sets["options"] = Utility::jencode($set_options);

            $update = $this->model->set_order($id, $sets);

            if ($update) {
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "updated-order-hosting", [
                    'id'   => $id,
                    'name' => $order["name"],
                ]);

                if (isset($set_options['domain']) && isset($options['domain']) && $options['domain'] != $set_options['domain'])
                    Orders::add_history($adata['id'], $order['id'], 'hosting-order-domain-changed', [
                        'old' => $options['domain'],
                        'new' => $set_options['domain'],
                    ]);

                if (isset($set_options['config']['user']) && isset($options['config']['user']) && $set_options['config']['user'] != $options['config']['user'])
                    Orders::add_history($adata['id'], $order['id'], 'hosting-order-username-changed', [
                        'old' => isset($options['config']['user']) && $options['config']['user'] ? $options['config']['user'] : ___("needs/null"),
                        'new' => isset($set_options['config']['user']) && $set_options['config']['user'] ? $set_options['config']['user'] : ___("needs/null"),
                    ]);
                elseif (isset($set_options['config']['user']))
                    Orders::add_history($adata['id'], $order['id'], 'hosting-order-username-changed', [
                        'old' => isset($options['config']['user']) && $options['config']['user'] ? $options['config']['user'] : ___("needs/null"),
                        'new' => isset($set_options['config']['user']) && $set_options['config']['user'] ? $set_options['config']['user'] : ___("needs/null"),
                    ]);

                if (isset($set_options['config']['password']) && isset($options['config']['password']) && $set_options['config']['password'] != $options['config']['password'])
                    Orders::add_history($adata['id'], $order['id'], 'hosting-order-password-changed');
                elseif (isset($set_options['config']['password']))
                    Orders::add_history($adata['id'], $order['id'], 'hosting-order-password-changed');

                if ($options['disk_limit'] != $set_options['disk_limit'])
                    Orders::add_history($adata['id'], $order['id'], 'hosting-order-disk-limit-changed', [
                        'old' => $options['disk_limit'],
                        'new' => $set_options['disk_limit'],
                    ]);

                if ($options['bandwidth_limit'] != $set_options['bandwidth_limit'])
                    Orders::add_history($adata['id'], $order['id'], 'hosting-order-bandwidth-limit-changed', [
                        'old' => $options['bandwidth_limit'],
                        'new' => $set_options['bandwidth_limit'],
                    ]);

            }

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/orders/success1"),
                'redirect' => $this->AdminCRLink("orders-2", ["detail", $order["id"]]) . "?content=hosting",
            ]);

        }

        private function change_shared_server($id = 0)
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];

            Helper::Load(["Products", "Orders"]);

            $old_server = isset($options['server_id']) ? Products::get_server($options['server_id']) : [];
            $new_server = [];

            $server_id = Filter::init("POST/server_id");

            if ($server_id == '' && $server_id != 0) return false;

            $module = 'none';
            $server = Products::get_server($server_id);
            if ($server) {
                $mname = $server["type"];
                Modules::Load("Servers", $mname);
                if (class_exists($mname . "_Module")) $module = $mname;
            }

            $sets = [];

            $options["server_id"] = $server_id;
            $options["panel_type"] = $module ? $module : "other";
            $sets["module"] = $module;


            $sets["options"] = Utility::jencode($options);

            $update = $this->model->set_order($id, $sets);

            if ($update) {
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "updated-order-hosting", [
                    'id'   => $id,
                    'name' => $order["name"],
                ]);

                Orders::add_history($adata['id'], $order['id'], 'hosting-order-shared-server-changed', [
                    'old' => $old_server ? $old_server['name'] . ' ' . $old_server['ip'] : ___("needs/none"),
                    'new' => $server ? $server['name'] . ' ' . $server['ip'] : ___("needs/none"),
                ]);

            }

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/orders/success1"),
                'redirect' => $this->AdminCRLink("orders-2", ["detail", $order["id"]]) . "?content=hosting",
            ]);

        }


        private function update_server($id = 0)
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];

            Helper::Load(["Products", "Orders"]);

            $panel_type = Filter::init("POST/panel_type", "hclear");
            $panel_link = Filter::init("POST/panel_link", "hclear");
            $hostname = Filter::init("POST/hostname", "hclear");
            $ns1 = Filter::init("POST/ns1", "domain");
            $ns2 = Filter::init("POST/ns2", "domain");
            $ip = Filter::init("POST/ip", "ip");
            $login = Filter::POST("login");
            $server_features = Filter::POST("server_features");
            $descriptions = Filter::POST("descriptions");
            $assigned_ips = Filter::POST("assigned_ips");

            if (isset($login["password"]) && $login["password"])
                $login["password"] = Crypt::encode($login["password"], Config::get("crypt/user"));


            $set_options = $options;

            $set_options["panel_type"] = $panel_type;

            $set_options["panel_link"] = $panel_link;

            if (!isset($options["hostname"]) || $hostname != $options["hostname"]) $set_options["hostname"] = $hostname;
            if (!isset($options["ns1"]) || $ns1 != $options["ns1"]) $set_options["ns1"] = $ns1;
            if (!isset($options["ns2"]) || $ns2 != $options["ns2"]) $set_options["ns2"] = $ns2;
            if (!isset($options["ip"]) || $ip != $options["ip"]) $set_options["ip"] = $ip;
            if (!isset($options["login"]) || $login) $set_options["login"] = $login;

            if (!isset($options["server_features"]) || $server_features != $options["server_features"]) $set_options["server_features"] = $server_features;

            if (!isset($options["descriptions"]) || $descriptions != $options["descriptions"]) $set_options["descriptions"] = $descriptions;
            if (!isset($options["assigned_ips"]) || $assigned_ips != $options["assigned_ips"]) $set_options["assigned_ips"] = $assigned_ips;


            $options = array_merge($options, $set_options);

            $sets = [];

            $sets["options"] = Utility::jencode($options);

            $update = $this->model->set_order($id, $sets);

            if ($update) {
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "updated-order-server", [
                    'id'   => $id,
                    'name' => $order["name"],
                ]);
            }

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/orders/success1"),
                'redirect' => $this->AdminCRLink("orders-2", ["detail", $order["id"]]) . "?content=server",
            ]);

        }


        private function hosting_use_method($id = 0)
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];

            Helper::Load(["Products", "Orders"]);

            $server_id = $options["server_id"];
            if (isset($options["config"]["password"]) && $options["config"]["password"])
                $options["config"]["password"] = Crypt::decode($options["config"]["password"], Config::get("crypt/user"));

            if (isset($options["ftp_info"]["password"]) && $options["ftp_info"]["password"])
                $options["ftp_info"]["password"] = Crypt::decode($options["ftp_info"]["password"], Config::get("crypt/user"));

            $method = Filter::REQUEST("use_method");

            if ($server_id) {
                $server = Products::get_server($server_id);
                if ($server) {
                    $module = $server["type"];

                    Modules::Load("Servers", $module);
                    $class_name = $module . "_Module";
                    $moduleClass = new $class_name($server, $options);

                    if (method_exists($moduleClass, 'set_order')) $moduleClass->set_order($order);

                    $moduleClass->area_link = $this->AdminCRLink('orders-2', ['detail', $id]);

                    if ($method && method_exists($moduleClass, 'use_method')) {

                        if ($method == 'SingleSignOn' || $method == 'SingleSignOn2') {
                            $adata = UserManager::LoginData("admin");
                            User::addAction($adata['id'], 'accessed', 'hosting-order-panel-accessed', [
                                'id'   => $server['id'],
                                'ip'   => $server["ip"],
                                'type' => $server["type"],
                                'name' => $server["name"],
                            ]);
                            Orders::add_history($adata['id'], $order['id'], 'hosting-order-panel-accessed', [
                                'id'   => $server['id'],
                                'ip'   => $server["ip"],
                                'type' => $server["type"],
                                'name' => $server["name"],
                            ]);
                        } elseif ($method == 'root_SingleSignOn' || $method == 'root_SingleSignOn2') {
                            $adata = UserManager::LoginData("admin");
                            User::addAction($adata['id'], 'accessed', 'root-panel-accessed', [
                                'id'   => $server['id'],
                                'ip'   => $server["ip"],
                                'type' => $server["type"],
                                'name' => $server["name"],
                            ]);
                            Orders::add_history($adata['id'], $order['id'], 'hosting-order-root-panel-accessed', [
                                'id'   => $server['id'],
                                'ip'   => $server["ip"],
                                'type' => $server["type"],
                                'name' => $server["name"],
                            ]);
                        }
                        $moduleClass->use_method($method);
                        return true;
                    }

                }
            }

        }

        private function operation_server_automation($id = 0)
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];

            Helper::Load(["Products", "Orders"]);

            $server_id = (int)Filter::init("REQUEST/server_id", "rnumbers");
            $use_method = (string)Filter::init("REQUEST/use_method", "hclear");

            if (!isset($_REQUEST["server_id"]) && $use_method) $server_id = $options["server_id"];

            $module = "none";
            $options["server_id"] = 0;

            if ($server_id) {
                Helper::Load("Products");
                $server = Products::get_server($server_id);
                if ($server) {
                    $module = $server["type"];
                    $options["server_id"] = $server_id;

                    Modules::Load("Servers", $module);

                    $class_name = $module . "_Module";
                    $moduleClass = new $class_name($server, $options);
                    if (method_exists($moduleClass, 'set_order')) $moduleClass->set_order($order);
                    $moduleClass->area_link = $this->AdminCRLink('orders-2', ['detail', $id]);

                    if ($use_method) {

                        if ($use_method == 'SingleSignOn') {
                            $adata = UserManager::LoginData("admin");
                            User::addAction($adata['id'], 'accessed', 'server-order-panel-accessed', [
                                'id'   => $server['id'],
                                'ip'   => $server["ip"],
                                'type' => $server["type"],
                                'name' => $server["name"],
                            ]);
                            Orders::add_history($adata['id'], $order['id'], 'server-order-panel-accessed', [
                                'id'   => $server['id'],
                                'ip'   => $server["ip"],
                                'type' => $server["type"],
                                'name' => $server["name"],
                            ]);
                        } elseif ($use_method == 'root_SingleSignOn') {
                            $adata = UserManager::LoginData("admin");
                            User::addAction($adata['id'], 'accessed', 'root-panel-accessed', [
                                'id'   => $server['id'],
                                'ip'   => $server["ip"],
                                'type' => $server["type"],
                                'name' => $server["name"],
                            ]);
                            Orders::add_history($adata['id'], $order['id'], 'server-order-root-panel-accessed', [
                                'id'   => $server['id'],
                                'ip'   => $server["ip"],
                                'type' => $server["type"],
                                'name' => $server["name"],
                            ]);
                        }


                        $moduleClass->use_method($use_method);
                        return true;
                    } else {
                        $apply = $moduleClass->edit_order_params();

                        if (!$apply && $moduleClass->error)
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => $moduleClass->error,
                            ]));
                        if (is_array($apply) && $apply) $options = $apply;
                    }
                }
            }

            $sets = [];

            $sets["options"] = Utility::jencode($options);
            $sets["module"] = $module;

            $update = $this->model->set_order($id, $sets);

            if ($update) {
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "updated-order-server", [
                    'id'   => $id,
                    'name' => $order["name"],
                ]);
            }

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/orders/success1"),
                'redirect' => $this->AdminCRLink("orders"),
            ]);

        }

        private function operation_special_automation($id = 0)
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $order = $this->get_order($id);
            if (!$order) die();
            $options = $order["options"];

            Helper::Load(["Products", "Orders"]);

            $module = (string)Filter::init("REQUEST/module", "letters_numbers", "_-");
            $use_method = (string)Filter::init("REQUEST/use_method", "hclear");

            if ($use_method && !$module) $module = $order["module"];

            if ($module && $module != "none") {
                Helper::Load("Products");

                Modules::Load("Product", $module);

                $class_name = $module;
                $moduleClass = new $class_name();
                if (method_exists($moduleClass, "set_order")) $moduleClass->set_order($order);
                $moduleClass->area_link = $this->AdminCRLink("orders-2", ["detail", $id]);

                if ($use_method) {
                    $moduleClass->use_method($use_method);
                    return true;
                } else {
                    $apply = $moduleClass->edit_order_params();

                    if (!$apply && $moduleClass->error)
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => $moduleClass->error,
                        ]));
                    if (is_array($apply) && $apply) $options = $apply;
                }

            } else
                $module = "none";

            $sets = [];

            $sets["options"] = Utility::jencode($options);
            $sets["module"] = $module;

            $update = $this->model->set_order($id, $sets);

            if ($update) {
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "updated-order-automation", [
                    'id'   => $id,
                    'name' => $order["name"],
                ]);
            }

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/orders/success1"),
                'redirect' => $this->AdminCRLink("orders"),
            ]);

        }


        private function get_server_order_automation_info()
        {

            $this->takeDatas("language");
            Helper::Load(["Products", "Orders"]);

            $server_id = (int)Filter::init("POST/server_id", "numbers");
            $order_id = (int)Filter::init("POST/order_id", "numbers");
            $page = Filter::init("REQUEST/m_page", "route");

            if (!$server_id) return false;

            $server = Products::get_server($server_id);
            if (!$server) return false;
            if ($order_id) {
                $order = Orders::get($order_id);
                if (!$order) return false;
            } else
                return false;
            $type = $server["type"];
            if ($type == "none") return false;

            Modules::Load("Servers", $type);

            $module = $type . "_Module";
            $module = new $module($server, $order["options"]);
            if (method_exists($module, 'set_order')) $module->set_order($order);
            $module->area_link = $this->AdminCRLink('orders-2', ['detail', $order_id]);

            if ($module->config["type"] != "virtualization") return false;

            if (method_exists($module, 'adminArea')) {
                $module->page = $page;

                echo $module->adminArea();

                return true;
            } else {
                $data = [
                    'module' => $module,
                    'order'  => $order,
                ];
                echo Modules::getPage("Servers", $server["type"], "order-detail", $data);
            }
        }

        private function get_special_order_automation_info()
        {
            $this->takeDatas("language");
            Helper::Load(["Products", "Orders"]);

            $module = (string)Filter::init("POST/module", "letters_numbers", "-_");
            $order_id = (int)Filter::init("POST/order_id", "numbers");
            if (!$module) return false;

            if ($order_id) {
                $order = Orders::get($order_id);
                if (!$order) return false;
            } else
                return false;


            if ($module == "none") return false;

            Modules::Load("Product", $module);

            $module_name = $module;

            $module = new $module_name();

            if (method_exists($module, 'set_order')) $module->set_order($order);
            $module->area_link = $this->AdminCRLink('orders-2', ['detail', $order_id]);

            $data = [
                'module' => $module,
                'order'  => $order,
            ];

            echo Modules::getPage("Product", $module_name, "order-detail", $data);

        }


        private function cancel_subscription()
        {
            $sub_id = (int)Filter::init("POST/id", "numbers");
            $order_id = (int)Filter::init("POST/order_id", "numbers");
            $addon_id = (int)Filter::init("POST/addon_id", "numbers");

            $order = $order_id ? Orders::get($order_id) : false;
            $addon = $addon_id ? Orders::get_addon($addon_id) : false;
            $subscription = $sub_id ? Orders::get_subscription($sub_id) : false;

            if (!$subscription || !($order || $addon)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Invalid  ID Number",
                ]);
                return false;
            }

            $cancel = Orders::cancel_subscription($subscription, $order, $addon);

            if (!$cancel) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => Orders::$message,
                ]);
                return false;
            }

            $a_data = UserManager::LoginData("admin");
            User::addAction($a_data["id"], "cancellation", "recurrence-subscription-cancelled", [
                'identifier' => $subscription["identifier"],
            ]);

            if ($order_id) Orders::add_history($a_data["id"], $order_id, 'recurrence-subscription-cancelled', ['identifier' => $subscription["identifier"]]);

            echo Utility::jencode(['status' => "successful"]);
        }


        private function addon_subscription_detail()
        {
            $this->takeDatas("language");

            $addon_id = Filter::init("REQUEST/addon_id", "numbers");

            if (!$addon_id) {
                echo "Not found addon id";
                return false;
            };

            Helper::Load(["Orders", "Products", "Money"]);

            $addon = Orders::get_addon($addon_id);

            if (!$addon) {
                echo "Not found addon";
                return false;
            }

            if (!isset($addon["subscription_id"]) || $addon["subscription_id"] < 1) {
                echo "Not found subscription #1";
                return false;
            }


            $situations = $this->view->chose("admin")->render("common-needs", false, false, true);
            $subscription_situations = $situations["subscription"];


            $subscription = Orders::get_subscription($addon["subscription_id"]);

            if (!$subscription) {
                echo "Not found subscription #2";
                return false;
            }

            $subscription = Orders::sync_subscription($subscription);


            $this->addData("subscription", $subscription);

            $this->addData("subscription_situations", $subscription_situations);


            $this->view->chose("admin")->render("addon-subscription-detail", $this->data);
            return true;
        }


        private function operationMain($operation)
        {
            if ($operation == "addon_subscription_detail")
                return $this->addon_subscription_detail();
            if ($operation == "cancel_subscription") return $this->cancel_subscription();
            if ($operation == "check_domain_availability") return $this->check_domain_availability();
            if ($operation == "ajax-list") return $this->ajax_list();
            if ($operation == "ajax-updowngrades") return $this->ajax_updowngrades();
            if ($operation == "ajax-cancellation-requests") return $this->ajax_cancellation_requests();
            if ($operation == "ajax-addons") {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->ajax_addons($id);
            }
            if ($operation == "user-list.json") return $this->select_users_json();
            if ($operation == "select-linked-products.json") {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->select_linked_products_json($id);
            }
            if ($operation == "requirement-file-download") return $this->requirement_file_download();
            if ($operation == "apply_operation" && Admin::isPrivilege(["ORDERS_OPERATION"]))
                return $this->apply_operation();

            if ($operation == "create" && Admin::isPrivilege(["ORDERS_OPERATION"]))
                return $this->create();

            if ($operation == "create_domain_order_detail_module" && Admin::isPrivilege(["ORDERS_OPERATION"]))
                return $this->create_domain_order_detail_module();

            if ($operation == "get_create_product_info" && Admin::isPrivilege(["ORDERS_OPERATION"]))
                return $this->get_create_product_info();

            if ($operation == "apply_operation_updowngrades" && Admin::isPrivilege(["ORDERS_OPERATION"]))
                return $this->apply_operation_updowngrades();

            if ($operation == "apply_operation_addons" && Admin::isPrivilege(["ORDERS_OPERATION"]))
                return $this->apply_operation_addons();

            if ($operation == "apply_operation_cancellation" && Admin::isPrivilege(["ORDERS_OPERATION"]))
                return $this->apply_operation_cancellation();

            if ($operation == "generate_renew_invoice" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->generate_renew_invoice($id);
            }

            if ($operation == "update_detail" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->update_detail($id);
            }

            if ($operation == "update_detail" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->update_detail($id);
            }

            if ($operation == "modify_order_blocks" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->modify_order_blocks($id);
            }

            if ($operation == "event_ok" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->event_ok($id);
            }

            if ($operation == "status_ok" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->msg_ok($id);
            }

            if ($operation == "event_del" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->event_del($id);
            }

            if ($operation == "msg_ok" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->msg_ok($id);
            }

            if ($operation == "sms-reports.json" && Admin::isPrivilege(["ORDERS_LOOK"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->sms_reports_json($id);
            }

            if ($operation == "get_sms_report" && Admin::isPrivilege(["ORDERS_LOOK"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->get_sms_report($id);
            }

            if ($operation == "update_requirements" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->update_requirements($id);
            }

            if ($operation == "upgrade" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->upgrade($id);
            }

            if ($operation == "downgrade" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->downgrade($id);
            }

            if ($operation == "cancelled" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->cancelled($id);
            }

            if ($operation == "get_hosting_informations") {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->hosting_informations($id);
            }

            if ($operation == "get_hosting_creation_info") {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->hosting_creation_info($id);
            }
            if ($operation == "get_server_order_automation_info") return $this->get_server_order_automation_info();
            if ($operation == "get_special_order_automation_info") return $this->get_special_order_automation_info();
            if ($operation == "get_sms_config_data") {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->get_sms_config_data($id);
            }

            if ($operation == "status_sms_origin" && Admin::isPrivilege(["ORDERS_OPERATION"]))
                return $this->status_sms_origin();

            if ($operation == "update_sms_origin" && Admin::isPrivilege(["ORDERS_OPERATION"]))
                return $this->update_sms_origin();

            if ($operation == "update_hosting" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->update_hosting($id);
            }

            if ($operation == "change_shared_server" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->change_shared_server($id);
            }

            if ($operation == "update_server" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->update_server($id);
            }

            if ($operation == "operation_server_automation" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->operation_server_automation($id);
            }
            if ($operation == "hosting_use_method" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->hosting_use_method($id);
            }
            if ($operation == "operation_special_automation" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->operation_special_automation($id);
            }

            if ($operation == "update_whois" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->update_whois($id);
            }

            if ($operation == "update_whois_privacy" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->update_whois_privacy($id);
            }

            if ($operation == "domain_verification" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->domain_verification($id);
            }

            if ($operation == "delete_domain_doc" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->delete_domain_doc($id);
            }

            if ($operation == "download_domain_doc_file" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->download_domain_doc_file($id);
            }

            if ($operation == "domain_modify_dns" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->domain_modify_dns($id);
            }

            if ($operation == "domain_add_cns" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->domain_add_cns($id);
            }

            if ($operation == "domain_modify_cns" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->domain_modify_cns($id);
            }

            if ($operation == "domain_delete_cns" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->domain_delete_cns($id);
            }

            if ($operation == "domain_add_dns_record" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->domain_add_dns_record($id);
            }

            if ($operation == "domain_update_dns_record" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->domain_update_dns_record($id);
            }

            if ($operation == "domain_delete_dns_record" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->domain_delete_dns_record($id);
            }


            if ($operation == "domain_add_dns_sec_record" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->domain_add_dns_sec_record($id);
            }
            if ($operation == "domain_delete_dns_sec_record" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->domain_delete_dns_sec_record($id);
            }
            if ($operation == "domain_set_forward_domain" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->domain_set_forward_domain($id);
            }
            if ($operation == "domain_cancel_forward_domain" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->domain_cancel_forward_domain($id);
            }
            if ($operation == "domain_add_email_forward" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->domain_add_email_forward($id);
            }
            if ($operation == "domain_update_email_forward" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->domain_update_email_forward($id);
            }
            if ($operation == "domain_delete_email_forward" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->domain_delete_email_forward($id);
            }


            if ($operation == "hosting_transactions" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->hosting_transactions($id);
            }

            if ($operation == "domain_send_transfer_code" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->domain_send_transfer_code($id);
            }

            if ($operation == "delete_delivery_file" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->delete_delivery_file($id);
            }

            if ($operation == "edit_addon" && Admin::isPrivilege(["ORDERS_OPERATION"])) return $this->edit_addon();

            if ($operation == "add_addon" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->add_addon($id);
            }

            if ($operation == "delete_addon" && Admin::isPrivilege(["ORDERS_OPERATION"])) {
                $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0;
                return $this->delete_addon($id);
            }

            echo "Not found operation: " . $operation;
        }


        private function pageMain($name = '')
        {
            if ($name == "create")
                return $this->create_detail();
            elseif (!$name || $name == "all" || $name == "active" || $name == "inprocess" || $name == "suspended" || $name == "cancelled" || $name == "overdue")
                return $this->orders($name);
            elseif ($name == "updowngrades") return $this->updowngrades();
            elseif ($name == "cancellation-requests") return $this->cancellation_requests();
            elseif ($name == "addons") return $this->addons();
            elseif ($name == "detail" && $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0)
                return $this->order_detail($id);
            echo "Not found main: " . $name;
        }


        public function create_detail()
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

            Helper::Load(["Money", "Products", "Orders", "Invoices"]);

            $links = [
                'controller' => $this->AdminCRLink("orders-1", ["create"]),
            ];

            $links["select-users.json"] = $links["controller"] . "?operation=user-list.json";


            $this->addData("links", $links);

            $meta = __("admin/orders/meta-create");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("orders"),
                'title' => __("admin/orders/breadcrumb-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/orders/breadcrumb-create"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);


            $user_id = (int)Filter::init("GET/user_id", "numbers");
            if ($user_id) {
                $user = User::getData($user_id, "id,full_name,lang", "array");
                if ($user) {
                    $user = array_merge($user, User::getInfo($user["id"], "company_name"));
                    $this->addData("user", $user);
                }
            }

            Helper::Load("Money");

            $this->addData("special_groups", $this->model->get_product_special_groups());

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

            $this->addData("functions", [
                'get_special_pgroups'    => function () {
                    $data = $this->model->get_product_special_groups();
                    return $data;
                },
                'get_product_categories' => function ($type = '', $kind = '', $parent = 0) {
                    if ($type == "softwares") {
                        return $this->model->get_software_categories();
                    } elseif ($type == "products") {
                        return $this->model->get_product_group_categories($kind, $parent);
                    }
                },
                'get_category_products'  => function ($type = '', $category = 0) {
                    return $this->model->get_category_products($type, $category);
                },
            ]);

            $modules = Modules::Load("Registrars", false, true);
            $registrars = [];
            if ($modules) foreach ($modules as $key => $val) $registrars[] = $key;
            $this->addData("registrars", $registrars);


            $this->view->chose("admin")->render("add-order", $this->data);
        }


        public function order_detail($id = 0)
        {
            $order = $this->get_order($id);
            if (!$order) die();

            Helper::Load(["Events"]);

            $method_name = $order["type"] . "_detail";
            return method_exists($this, $method_name) ? $this->$method_name($order) : die("Not found method : " . $method_name);
        }


        private function special_detail($order)
        {

            Helper::Load(["Money", "Products", "Orders", "Invoices"]);

            $invoice = Invoices::get_last_invoice($order["id"], '', 't2.*');
            $product = Products::get($order["type"], $order["product_id"], Config::get("general/local"));

            $user = User::getData($order["owner_id"], "id,email,phone,name,surname,full_name,company_name,lang,blacklist,ip", "array");
            $user = array_merge($user, User::getInfo($user["id"], ["identity", "blacklist_reason", "blacklist_time", "blacklist_by_admin"]));
            $user["blacklist"] = User::checkBlackList($user, in_array($order['status'], ['waiting', 'inprocess']));

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

            $meta = __("admin/orders/meta-special-detail");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => $this->AdminCRLink("orders"),
                    'title' => __("admin/orders/breadcrumb-list"),
                ],
                [
                    'link'  => false,
                    'title' => __("admin/orders/breadcrumb-special-detail", ['{name}' => $order["name"]]),
                ],
            ];
            $this->addData("breadcrumb", $breadcrumbs);

            $situations = $this->view->chose("admin")->render("common-needs", false, false, true);
            $order_situations = $situations["orders"];
            $invoice_situations = $situations["invoices"];
            $subscription_situations = $situations["subscription"];

            $links = [
                'controller'       => $this->AdminCRLink("orders-2", ["detail", $order["id"]]),
                'list'             => $this->AdminCRLink("orders"),
                'create-new-order' => $this->AdminCRLink("orders-1", ["create"]),
                'detail-user-link' => $this->AdminCRLink("users-2", ["detail", $user["id"]]),
                'ajax-addons'      => $this->AdminCRLink("orders-2", ["detail", $order["id"]]) . "?operation=ajax-addons",
            ];

            $group = $this->model->get_category($order["type_id"]);

            $cat_id = isset($order["options"]["category_id"]) ? $order["options"]["category_id"] : 0;

            $category = $this->model->get_category($cat_id);


            $links["select-users.json"] = $links["controller"] . "?operation=user-list.json";
            $links["group-link"] = $this->AdminCRLink("products", ["special-" . $order["type_id"]]);
            $links["category-link"] = $this->AdminCRLink("products-2", ["special-" . $order["type_id"], "edit-category"]) . "?id=" . $cat_id;
            if ($invoice) $links["invoice-link"] = $this->AdminCRLink("invoices-2", ["detail", $invoice["id"]]);

            $this->addData("links", $links);

            $modules = Modules::Load("Payment", false, true);
            $actveMods = Config::get("modules/payment-methods");
            $pmethods = [];
            if ($modules) {
                foreach ($modules as $key => $val) {
                    if (in_array($key, $actveMods) || $key == $order["pmethod"]) {
                        $pmethods[$key] = $val["lang"]["invoice-name"];
                    }
                }
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

            if ($download_file || $download_link)
                $this->addData("download_link", $this->CRLink("download-id", ["order", $order["id"]]));


            if (isset($order["options"]["delivery_file"])) {
                $delivery_folder = RESOURCE_DIR . "uploads" . DS . "orders" . DS;
                $delivery_file = Utility::link_determiner($delivery_folder . $order["options"]["delivery_file"], '', false);
            } else
                $delivery_file = null;

            if (isset($order["options"]["delivery_file_button_title"]))
                $delivery_file_button_title = $order["options"]["delivery_file_button_title"];
            else
                $delivery_file_button_title = null;

            if (isset($order["options"]["delivery_title_name"]))
                $delivery_title_name = $order["options"]["delivery_title_name"];
            else
                $delivery_title_name = null;

            if (isset($order["options"]["delivery_title_description"]))
                $delivery_title_description = $order["options"]["delivery_title_description"];
            else
                $delivery_title_description = null;

            if ($order["period"] != "none") {
                $ordinfo = Orders::period_info($order);
                $foreign_user = User::isforeign($user["id"]);

                $this->addData("updown_times_used", $ordinfo["times-used-day"]);
                $this->addData("updown_times_used_amount", $ordinfo["format-times-used-amount"]);
                $this->addData("updown_remaining_day", $ordinfo["remaining-day"]);
                $this->addData("updown_remaining_amount", $ordinfo["format-remaining-amount"]);
                $this->addData("foreign_user", $foreign_user);
                $this->addData("upgproducts", $this->updown_products("up", $order, $product, $ordinfo["remaining-amount"]));
                $this->addData("dowgproducts", $this->updown_products("down", $order, $product, $ordinfo["remaining-amount"]));

            }


            // We did this because if it is a tax-inclusive system, the order amount should appear tax-included.
            $taxation_type = Invoices::getTaxationType();
            $tax_rate = Invoices::getTaxRate();

            if ($invoice && $invoice["taxrate"] > 0.00) $tax_rate = $invoice["taxrate"];

            if ($taxation_type == 'inclusive' && $order["amount"] > 0.00 && $tax_rate > 0.00)
                $order["amount"] += Money::get_tax_amount($order["amount"], $tax_rate);
            $this->addData("taxation_type", $taxation_type);
            $this->addData("tax_rate", $tax_rate);

            // Events
            $p_events = Events::getList("operation", "order", $order["id"], false, "pending");
            $p_events2 = Events::getList("operation", "order", $order["id"], "cancelled-product-request");
            if ($p_events2) {
                foreach ($p_events2 as $k => $p) {
                    if ($p["status"] == "approved" && DateManager::strtotime($p["cdate"]) < DateManager::strtotime($order["renewaldate"])) unset($p_events2[$k]);
                }
                if ($p_events2) $p_events = array_merge($p_events, $p_events2);
            }
            $this->addData("pending_events", $p_events);

            $this->addData("privOperation", Admin::isPrivilege(["ORDERS_OPERATION"]));
            $this->addData("privDelete", Admin::isPrivilege(["ORDERS_DELETE"]));
            $this->addData("order", $order);
            $this->addData("product", $product);
            $this->addData("group", $group);
            $this->addData("category", $category);
            $this->addData("invoice", $invoice);
            $this->addData("user", $user);
            $this->addData("situations", $order_situations);
            $this->addData("invoice_situations", $invoice_situations);
            $this->addData("subscription_situations", $subscription_situations);
            $this->addData("pmethods", $pmethods);
            $this->addData("delivery_file", $delivery_file);
            $this->addData("delivery_file_button_title", $delivery_file_button_title);
            $this->addData("delivery_title_name", $delivery_title_name);
            $this->addData("delivery_title_description", $delivery_title_description);
            $this->addData("myrequirements", $this->model->requirements($order["id"]));

            $module_groups = [];
            $modules = Modules::Load("Product", "All");
            if ($modules) {
                foreach ($modules as $k => $v) {
                    $v["created_at"] = $v["config"]["created_at"];
                    $modules[$k] = $v;
                }
                Utility::sksort($modules, "created_at");
                foreach ($modules as $k => $v) $module_groups[$v["config"]["group"]][$k] = $v;
            }
            $this->addData("module_groups", $module_groups);

            $invoices = Invoices::get_order_invoices($order["id"]);
            $this->addData("invoices", $invoices);

            $product_addons = [];

            $lang = $user["lang"] ?? Bootstrap::$lang->clang;

            $addon_categories = $this->model->db->select("t1.id,t2.title")->from("categories AS t1");
            $addon_categories->join("LEFT", "categories_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
            $addon_categories->where("t2.id", "IS NOT NULL", "", "&&");
            $addon_categories->where("t1.type", "=", "addon");
            $addon_categories->order_by("t1.rank ASC,t1.id DESC");
            if ($addon_categories->build()) {
                foreach ($addon_categories->fetch_assoc() as $c) {
                    $get_addons = $this->model->db->select("t1.id,t1.override_usrcurrency,t2.name,t2.description,t2.type,t2.properties,t2.options")->from("products_addons AS t1");
                    $get_addons->join("LEFT", "products_addons_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
                    $get_addons->where("t2.id", "IS NOT NULL", "", "&&");
                    $get_addons->where("t1.mcategory", "=", "special_" . $order["type_id"], "&&");
                    $get_addons->where("t1.category", "=", $c["id"]);
                    $get_addons->order_by("t1.rank ASC,t1.id DESC");
                    if ($get_addons->build()) {
                        if (!isset($product_addons[$c["id"]])) {
                            $c["addons"] = [];
                            $product_addons[$c["id"]] = $c;
                        }
                        foreach ($get_addons->fetch_assoc() as $ad) $product_addons[$c["id"]]["addons"][$ad["id"]] = $ad;
                    }
                }
            }

            $this->addData("product_addons", $product_addons);

            $sub_id = $order["subscription_id"];
            $subscription = $sub_id ? Orders::get_subscription($sub_id) : [];

            if (Filter::REQUEST("bring") == "subscription_detail" && $subscription)
                $subscription = Orders::sync_subscription($subscription);

            $this->addData("subscription", $subscription);

            if (Filter::REQUEST("bring") == "subscription_detail" && $subscription)
                return $this->view->chose("admin")->render("order-subscription-detail", $this->data);


            $this->view->chose("admin")->render("special-order-detail", $this->data);
        }


        private function hosting_detail($order)
        {

            Helper::Load(["Money", "Products", "Orders", "Invoices"]);

            $invoice = Invoices::get_last_invoice($order["id"], '', 't2.*');
            $product = Products::get($order["type"], $order["product_id"], Config::get("general/local"));

            $user = User::getData($order["owner_id"], "id,email,phone,name,surname,full_name,company_name,lang,blacklist,ip", "array");
            $user = array_merge($user, User::getInfo($user["id"], ["identity", "blacklist_reason", "blacklist_time", "blacklist_by_admin"]));
            $user["blacklist"] = User::checkBlackList($user, in_array($order['status'], ['waiting', 'inprocess']));

            $options = $order["options"];

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

            $meta = __("admin/orders/meta-hosting-detail");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => $this->AdminCRLink("orders"),
                    'title' => __("admin/orders/breadcrumb-list"),
                ],
                [
                    'link'  => false,
                    'title' => __("admin/orders/breadcrumb-hosting-detail", ['{name}' => $order["name"]]),
                ],
            ];
            $this->addData("breadcrumb", $breadcrumbs);

            $situations = $this->view->chose("admin")->render("common-needs", false, false, true);
            $order_situations = $situations["orders"];
            $invoice_situations = $situations["invoices"];
            $subscription_situations = $situations["subscription"];

            $links = [
                'controller'       => $this->AdminCRLink("orders-2", ["detail", $order["id"]]),
                'list'             => $this->AdminCRLink("orders"),
                'create-new-order' => $this->AdminCRLink("orders-1", ["create"]),
                'detail-user-link' => $this->AdminCRLink("users-2", ["detail", $user["id"]]),
                'ajax-addons'      => $this->AdminCRLink("orders-2", ["detail", $order["id"]]) . "?operation=ajax-addons",
            ];

            $cat_id = isset($order["options"]["category_id"]) ? $order["options"]["category_id"] : 0;

            $category = $this->model->get_category($cat_id);


            $links["select-users.json"] = $links["controller"] . "?operation=user-list.json";
            $links["group-link"] = $this->AdminCRLink("products", ["hosting"]);
            $links["category-link"] = $this->AdminCRLink("products-2", ["hosting", "edit-category"]) . "?id=" . $cat_id;
            if ($invoice) $links["invoice-link"] = $this->AdminCRLink("invoices-2", ["detail", $invoice["id"]]);

            $modules = Modules::Load("Payment", false, true);
            $actveMods = Config::get("modules/payment-methods");
            $pmethods = [];
            if ($modules) {
                foreach ($modules as $key => $val) {
                    if (in_array($key, $actveMods) || $key == $order["pmethod"]) {
                        $pmethods[$key] = $val["lang"]["invoice-name"];
                    }
                }
            }

            if ($order["period"] != "none") {
                $ordinfo = Orders::period_info($order);
                $foreign_user = User::isforeign($user["id"]);

                $this->addData("updown_times_used", $ordinfo["times-used-day"]);
                $this->addData("updown_times_used_amount", $ordinfo["format-times-used-amount"]);
                $this->addData("updown_remaining_day", $ordinfo["remaining-day"]);
                $this->addData("updown_remaining_amount", $ordinfo["format-remaining-amount"]);
                $this->addData("foreign_user", $foreign_user);
                $this->addData("upgproducts", $this->updown_products("up", $order, $product, $ordinfo["remaining-amount"]));
                $this->addData("dowgproducts", $this->updown_products("down", $order, $product, $ordinfo["remaining-amount"]));

            }

            $server = false;
            if ($order["module"] && $order["module"] != "none" && isset($order["options"]["server_id"])) {
                $server_id = $order["options"]["server_id"];
                $server = Products::get_server($server_id);
                if ($server) {
                    $links["edit-server"] = $this->AdminCRLink("products-2", ["hosting", "edit-shared-server"]) . "?id=" . $server_id;

                    $mname = $server["type"];
                    Modules::Load("Servers", $mname);
                    if (class_exists($mname . "_Module")) {
                        $cname = $mname . "_Module";
                        $module = new $cname($server, $options);
                        if (method_exists($module, "set_order")) $module->set_order($order);
                        $this->addData("module", $module);
                        $this->addData("module_name", $server["type"]);
                    }

                }
            }


            // We did this because if it is a tax-inclusive system, the order amount should appear tax-included.
            $taxation_type = Invoices::getTaxationType();
            $tax_rate = Invoices::getTaxRate();

            if ($invoice && $invoice["taxrate"] > 0.00) $tax_rate = $invoice["taxrate"];

            if ($taxation_type == 'inclusive' && $order["amount"] > 0.00 && $tax_rate > 0.00)
                $order["amount"] += Money::get_tax_amount($order["amount"], $tax_rate);
            $this->addData("taxation_type", $taxation_type);
            $this->addData("tax_rate", $tax_rate);


            // Events
            $p_events = Events::getList("operation", "order", $order["id"], false, "pending");
            $p_events2 = Events::getList("operation", "order", $order["id"], "cancelled-product-request");
            if ($p_events2) {
                foreach ($p_events2 as $k => $p) {
                    if ($p["status"] == "approved" && DateManager::strtotime($p["cdate"]) < DateManager::strtotime($order["renewaldate"])) unset($p_events2[$k]);
                }
                if ($p_events2) $p_events = array_merge($p_events, $p_events2);
            }
            $this->addData("pending_events", $p_events);

            $this->addData("links", $links);
            $this->addData("privOperation", Admin::isPrivilege(["ORDERS_OPERATION"]));
            $this->addData("privDelete", Admin::isPrivilege(["ORDERS_DELETE"]));
            $this->addData("order", $order);
            $this->addData("product", $product);
            $this->addData("category", $category);
            $this->addData("invoice", $invoice);
            $this->addData("user", $user);
            $this->addData("situations", $order_situations);
            $this->addData("invoice_situations", $invoice_situations);
            $this->addData("subscription_situations", $subscription_situations);
            $this->addData("pmethods", $pmethods);
            $this->addData("server", $server);
            $this->addData("myrequirements", $this->model->requirements($order["id"]));

            $shared_servers = $this->model->get_shared_servers();
            if ($shared_servers) {
                $modules = Modules::Load("Servers");
                $hosting = [];
                if ($modules)
                    foreach ($modules as $module_name => $module)
                        if ($module["config"]["type"] == "hosting")
                            $hosting[] = $module_name;
                $servers = [];
                foreach ($shared_servers as $server) if (in_array($server["type"], $hosting)) $servers[] = $server;
                $this->addData("shared_servers", $servers);
            }

            $invoices = Invoices::get_order_invoices($order["id"]);
            $this->addData("invoices", $invoices);

            $product_addons = [];

            $lang = $user["lang"] ?? Bootstrap::$lang->clang;

            $addon_categories = $this->model->db->select("t1.id,t2.title")->from("categories AS t1");
            $addon_categories->join("LEFT", "categories_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
            $addon_categories->where("t2.id", "IS NOT NULL", "", "&&");
            $addon_categories->where("t1.type", "=", "addon");
            $addon_categories->order_by("t1.rank ASC,t1.id DESC");
            if ($addon_categories->build()) {
                foreach ($addon_categories->fetch_assoc() as $c) {
                    $get_addons = $this->model->db->select("t1.id,t1.override_usrcurrency,t2.name,t2.description,t2.type,t2.properties,t2.options")->from("products_addons AS t1");
                    $get_addons->join("LEFT", "products_addons_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
                    $get_addons->where("t2.id", "IS NOT NULL", "", "&&");
                    $get_addons->where("t1.mcategory", "=", "hosting", "&&");
                    $get_addons->where("t1.category", "=", $c["id"]);
                    $get_addons->order_by("t1.rank ASC,t1.id DESC");
                    if ($get_addons->build()) {
                        if (!isset($product_addons[$c["id"]])) {
                            $c["addons"] = [];
                            $product_addons[$c["id"]] = $c;
                        }
                        foreach ($get_addons->fetch_assoc() as $ad) $product_addons[$c["id"]]["addons"][$ad["id"]] = $ad;
                    }
                }
            }

            $this->addData("product_addons", $product_addons);

            $sub_id = $order["subscription_id"];
            $subscription = $sub_id ? Orders::get_subscription($sub_id) : [];

            if (Filter::REQUEST("bring") == "subscription_detail" && $subscription)
                $subscription = Orders::sync_subscription($subscription);

            $this->addData("subscription", $subscription);

            if (Filter::REQUEST("bring") == "subscription_detail" && $subscription)
                return $this->view->chose("admin")->render("order-subscription-detail", $this->data);


            $this->view->chose("admin")->render("hosting-order-detail", $this->data);
        }


        private function software_detail($order)
        {

            Helper::Load(["Money", "Products", "Orders", "Invoices"]);

            $invoice = Invoices::get_last_invoice($order["id"], '', 't2.*');
            $product = Products::get($order["type"], $order["product_id"], Config::get("general/local"));

            $user = User::getData($order["owner_id"], "id,email,phone,name,surname,full_name,company_name,lang,blacklist,ip", "array");
            $user = array_merge($user, User::getInfo($user["id"], ["identity", "blacklist_reason", "blacklist_time", "blacklist_by_admin"]));
            $user["blacklist"] = User::checkBlackList($user, in_array($order['status'], ['waiting', 'inprocess']));

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

            $meta = __("admin/orders/meta-software-detail");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => $this->AdminCRLink("orders"),
                    'title' => __("admin/orders/breadcrumb-list"),
                ],
                [
                    'link'  => false,
                    'title' => __("admin/orders/breadcrumb-software-detail", ['{name}' => $order["name"]]),
                ],
            ];
            $this->addData("breadcrumb", $breadcrumbs);

            $situations = $this->view->chose("admin")->render("common-needs", false, false, true);
            $order_situations = $situations["orders"];
            $invoice_situations = $situations["invoices"];
            $subscription_situations = $situations["subscription"];

            $links = [
                'controller'       => $this->AdminCRLink("orders-2", ["detail", $order["id"]]),
                'list'             => $this->AdminCRLink("orders"),
                'create-new-order' => $this->AdminCRLink("orders-1", ["create"]),
                'detail-user-link' => $this->AdminCRLink("users-2", ["detail", $user["id"]]),
                'ajax-addons'      => $this->AdminCRLink("orders-2", ["detail", $order["id"]]) . "?operation=ajax-addons",
            ];

            $cat_id = isset($order["options"]["category_id"]) ? $order["options"]["category_id"] : 0;

            $category = $this->model->get_category($cat_id);


            $links["select-users.json"] = $links["controller"] . "?operation=user-list.json";
            $links["group-link"] = $this->AdminCRLink("products", ["software"]);
            $links["category-link"] = $this->AdminCRLink("products-2", ["software", "edit-category"]) . "?id=" . $cat_id;
            if ($invoice) $links["invoice-link"] = $this->AdminCRLink("invoices-2", ["detail", $invoice["id"]]);

            $this->addData("links", $links);

            $modules = Modules::Load("Payment", false, true);
            $actveMods = Config::get("modules/payment-methods");
            $pmethods = [];
            if ($modules) {
                foreach ($modules as $key => $val) {
                    if (in_array($key, $actveMods) || $key == $order["pmethod"]) {
                        $pmethods[$key] = $val["lang"]["invoice-name"];
                    }
                }
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

            if ($download_file || $download_link)
                $this->addData("download_link", $this->CRLink("download-id", ["order", $order["id"]]));


            if (isset($order["options"]["delivery_file"])) {
                $delivery_folder = RESOURCE_DIR . "uploads" . DS . "orders" . DS;
                $delivery_file = Utility::link_determiner($delivery_folder . $order["options"]["delivery_file"]);
            } else
                $delivery_file = null;


            if ($order["period"] != "none") {
                $ordinfo = Orders::period_info($order);
                $foreign_user = User::isforeign($user["id"]);

                $this->addData("updown_times_used", $ordinfo["times-used-day"]);
                $this->addData("updown_times_used_amount", $ordinfo["format-times-used-amount"]);
                $this->addData("updown_remaining_day", $ordinfo["remaining-day"]);
                $this->addData("updown_remaining_amount", $ordinfo["format-remaining-amount"]);
                $this->addData("foreign_user", $foreign_user);
            }

            // We did this because if it is a tax-inclusive system, the order amount should appear tax-included.
            $taxation_type = Invoices::getTaxationType();
            $tax_rate = Invoices::getTaxRate();

            if ($invoice && $invoice["taxrate"] > 0.00) $tax_rate = $invoice["taxrate"];

            if ($taxation_type == 'inclusive' && $order["amount"] > 0.00 && $tax_rate > 0.00)
                $order["amount"] += Money::get_tax_amount($order["amount"], $tax_rate);
            $this->addData("taxation_type", $taxation_type);
            $this->addData("tax_rate", $tax_rate);

            // Events
            $p_events = Events::getList("operation", "order", $order["id"], false, "pending");
            $p_events2 = Events::getList("operation", "order", $order["id"], "cancelled-product-request");
            if ($p_events2) {
                foreach ($p_events2 as $k => $p) {
                    if ($p["status"] == "approved" && DateManager::strtotime($p["cdate"]) < DateManager::strtotime($order["renewaldate"])) unset($p_events2[$k]);
                }
                if ($p_events2) $p_events = array_merge($p_events, $p_events2);
            }
            $this->addData("pending_events", $p_events);

            $this->addData("privOperation", Admin::isPrivilege(["ORDERS_OPERATION"]));
            $this->addData("privDelete", Admin::isPrivilege(["ORDERS_DELETE"]));
            $this->addData("order", $order);
            $this->addData("product", $product);
            $this->addData("category", $category);
            $this->addData("invoice", $invoice);
            $this->addData("user", $user);
            $this->addData("situations", $order_situations);
            $this->addData("invoice_situations", $invoice_situations);
            $this->addData("subscription_situations", $subscription_situations);
            $this->addData("pmethods", $pmethods);
            $this->addData("delivery_file", $delivery_file);
            $this->addData("myrequirements", $this->model->requirements($order["id"]));

            $invoices = Invoices::get_order_invoices($order["id"]);
            $this->addData("invoices", $invoices);

            $product_addons = [];

            $lang = $user["lang"] ?? Bootstrap::$lang->clang;

            $addon_categories = $this->model->db->select("t1.id,t2.title")->from("categories AS t1");
            $addon_categories->join("LEFT", "categories_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
            $addon_categories->where("t2.id", "IS NOT NULL", "", "&&");
            $addon_categories->where("t1.type", "=", "addon");
            $addon_categories->order_by("t1.rank ASC,t1.id DESC");
            if ($addon_categories->build()) {
                foreach ($addon_categories->fetch_assoc() as $c) {
                    $get_addons = $this->model->db->select("t1.id,t1.override_usrcurrency,t2.name,t2.description,t2.type,t2.properties,t2.options")->from("products_addons AS t1");
                    $get_addons->join("LEFT", "products_addons_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
                    $get_addons->where("t2.id", "IS NOT NULL", "", "&&");
                    $get_addons->where("t1.mcategory", "=", "software", "&&");
                    $get_addons->where("t1.category", "=", $c["id"]);
                    $get_addons->order_by("t1.rank ASC,t1.id DESC");
                    if ($get_addons->build()) {
                        if (!isset($product_addons[$c["id"]])) {
                            $c["addons"] = [];
                            $product_addons[$c["id"]] = $c;
                        }
                        foreach ($get_addons->fetch_assoc() as $ad) $product_addons[$c["id"]]["addons"][$ad["id"]] = $ad;
                    }
                }
            }

            $this->addData("product_addons", $product_addons);

            $sub_id = $order["subscription_id"];
            $subscription = $sub_id ? Orders::get_subscription($sub_id) : [];

            if (Filter::REQUEST("bring") == "subscription_detail" && $subscription)
                $subscription = Orders::sync_subscription($subscription);

            $this->addData("subscription", $subscription);

            if (Filter::REQUEST("bring") == "subscription_detail" && $subscription)
                return $this->view->chose("admin")->render("order-subscription-detail", $this->data);


            $this->view->chose("admin")->render("software-order-detail", $this->data);
        }


        private function server_detail($order)
        {

            Helper::Load(["Money", "Products", "Orders", "Invoices"]);

            $invoice = Invoices::get_last_invoice($order["id"], '', 't2.*');
            $product = Products::get($order["type"], $order["product_id"], Config::get("general/local"));

            $user = User::getData($order["owner_id"], "id,email,phone,name,surname,full_name,company_name,lang,blacklist,ip", "array");
            $user = array_merge($user, User::getInfo($user["id"], ["identity", "blacklist_reason", "blacklist_time", "blacklist_by_admin"]));
            $user["blacklist"] = User::checkBlackList($user, in_array($order['status'], ['waiting', 'inprocess']));


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

            $meta = __("admin/orders/meta-server-detail");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => $this->AdminCRLink("orders"),
                    'title' => __("admin/orders/breadcrumb-list"),
                ],
                [
                    'link'  => false,
                    'title' => __("admin/orders/breadcrumb-server-detail", ['{name}' => $order["name"]]),
                ],
            ];
            $this->addData("breadcrumb", $breadcrumbs);

            $situations = $this->view->chose("admin")->render("common-needs", false, false, true);
            $order_situations = $situations["orders"];
            $invoice_situations = $situations["invoices"];
            $subscription_situations = $situations["subscription"];

            $links = [
                'controller'       => $this->AdminCRLink("orders-2", ["detail", $order["id"]]),
                'list'             => $this->AdminCRLink("orders"),
                'create-new-order' => $this->AdminCRLink("orders-1", ["create"]),
                'detail-user-link' => $this->AdminCRLink("users-2", ["detail", $user["id"]]),
                'ajax-addons'      => $this->AdminCRLink("orders-2", ["detail", $order["id"]]) . "?operation=ajax-addons",
            ];

            $cat_id = isset($order["options"]["category_id"]) ? $order["options"]["category_id"] : 0;

            $category = $this->model->get_category($cat_id);


            $links["select-users.json"] = $links["controller"] . "?operation=user-list.json";
            $links["group-link"] = $this->AdminCRLink("products", ["server"]);
            $links["category-link"] = $this->AdminCRLink("products-2", ["server", "edit-category"]) . "?id=" . $cat_id;
            if ($invoice) $links["invoice-link"] = $this->AdminCRLink("invoices-2", ["detail", $invoice["id"]]);

            $this->addData("links", $links);

            $modules = Modules::Load("Payment", false, true);
            $actveMods = Config::get("modules/payment-methods");
            $pmethods = [];
            if ($modules) {
                foreach ($modules as $key => $val) {
                    if (in_array($key, $actveMods) || $key == $order["pmethod"]) {
                        $pmethods[$key] = $val["lang"]["invoice-name"];
                    }
                }
            }


            if ($order["period"] != "none") {
                $ordinfo = Orders::period_info($order);
                $foreign_user = User::isforeign($user["id"]);

                $this->addData("updown_times_used", $ordinfo["times-used-day"]);
                $this->addData("updown_times_used_amount", $ordinfo["format-times-used-amount"]);
                $this->addData("updown_remaining_day", $ordinfo["remaining-day"]);
                $this->addData("updown_remaining_amount", $ordinfo["format-remaining-amount"]);
                $this->addData("foreign_user", $foreign_user);
                $this->addData("upgproducts", $this->updown_products("up", $order, $product, $ordinfo["remaining-amount"]));
                $this->addData("dowgproducts", $this->updown_products("down", $order, $product, $ordinfo["remaining-amount"]));

            }

            // We did this because if it is a tax-inclusive system, the order amount should appear tax-included.
            $taxation_type = Invoices::getTaxationType();
            $tax_rate = Invoices::getTaxRate();

            if ($invoice && $invoice["taxrate"] > 0.00) $tax_rate = $invoice["taxrate"];

            if ($taxation_type == 'inclusive' && $order["amount"] > 0.00 && $tax_rate > 0.00)
                $order["amount"] += Money::get_tax_amount($order["amount"], $tax_rate);
            $this->addData("taxation_type", $taxation_type);
            $this->addData("tax_rate", $tax_rate);

            // Events
            $p_events = Events::getList("operation", "order", $order["id"], false, "pending");
            $p_events2 = Events::getList("operation", "order", $order["id"], "cancelled-product-request");
            if ($p_events2) {
                foreach ($p_events2 as $k => $p) {
                    if ($p["status"] == "approved" && DateManager::strtotime($p["cdate"]) < DateManager::strtotime($order["renewaldate"])) unset($p_events2[$k]);
                }
                if ($p_events2) $p_events = array_merge($p_events, $p_events2);
            }
            $this->addData("pending_events", $p_events);

            $shared_servers = $this->model->get_shared_servers();
            if ($shared_servers) {
                $modules = Modules::Load("Servers");
                $virtualization = [];
                if ($modules)
                    foreach ($modules as $module_name => $module)
                        if ($module["config"]["type"] == "virtualization")
                            $virtualization[] = $module_name;
                $servers = [];
                foreach ($shared_servers as $server) if (in_array($server["type"], $virtualization)) $servers[] = $server;
                $this->addData("shared_servers", $servers);
            }

            if (isset($order["options"]["login"]["password"])) {
                $password = $order["options"]["login"]["password"];
                $password_d = Crypt::decode($password, Config::get("crypt/user"));
                if ($password_d) $order["options"]["login"]["password"] = $password_d;
            }


            $this->addData("privOperation", Admin::isPrivilege(["ORDERS_OPERATION"]));
            $this->addData("privDelete", Admin::isPrivilege(["ORDERS_DELETE"]));
            $this->addData("order", $order);
            $this->addData("product", $product);
            $this->addData("category", $category);
            $this->addData("invoice", $invoice);
            $this->addData("user", $user);
            $this->addData("situations", $order_situations);
            $this->addData("invoice_situations", $invoice_situations);
            $this->addData("subscription_situations", $subscription_situations);
            $this->addData("pmethods", $pmethods);
            $this->addData("myrequirements", $this->model->requirements($order["id"]));

            $invoices = Invoices::get_order_invoices($order["id"]);
            $this->addData("invoices", $invoices);

            $product_addons = [];

            $lang = $user["lang"] ?? Bootstrap::$lang->clang;

            $addon_categories = $this->model->db->select("t1.id,t2.title")->from("categories AS t1");
            $addon_categories->join("LEFT", "categories_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
            $addon_categories->where("t2.id", "IS NOT NULL", "", "&&");
            $addon_categories->where("t1.type", "=", "addon");
            $addon_categories->order_by("t1.rank ASC,t1.id DESC");
            if ($addon_categories->build()) {
                foreach ($addon_categories->fetch_assoc() as $c) {
                    $get_addons = $this->model->db->select("t1.id,t1.override_usrcurrency,t2.name,t2.description,t2.type,t2.properties,t2.options")->from("products_addons AS t1");
                    $get_addons->join("LEFT", "products_addons_lang AS t2", "t2.owner_id=t1.id AND t2.lang='" . $lang . "'");
                    $get_addons->where("t2.id", "IS NOT NULL", "", "&&");
                    $get_addons->where("t1.mcategory", "=", "server", "&&");
                    $get_addons->where("t1.category", "=", $c["id"]);
                    $get_addons->order_by("t1.rank ASC,t1.id DESC");
                    if ($get_addons->build()) {
                        if (!isset($product_addons[$c["id"]])) {
                            $c["addons"] = [];
                            $product_addons[$c["id"]] = $c;
                        }
                        foreach ($get_addons->fetch_assoc() as $ad) $product_addons[$c["id"]]["addons"][$ad["id"]] = $ad;
                    }
                }
            }

            $this->addData("product_addons", $product_addons);

            $sub_id = $order["subscription_id"];
            $subscription = $sub_id ? Orders::get_subscription($sub_id) : [];

            if (Filter::REQUEST("bring") == "subscription_detail" && $subscription)
                $subscription = Orders::sync_subscription($subscription);

            $this->addData("subscription", $subscription);

            if (Filter::REQUEST("bring") == "subscription_detail" && $subscription)
                return $this->view->chose("admin")->render("order-subscription-detail", $this->data);


            $this->view->chose("admin")->render("server-order-detail", $this->data);
        }


        private function sms_detail($order)
        {

            Helper::Load(["Money", "Products", "Orders", "Invoices"]);

            $invoice = Invoices::get_last_invoice($order["id"], '', 't2.*');
            $product = Products::get($order["type"], $order["product_id"], Config::get("general/local"));

            $user = User::getData($order["owner_id"], "id,email,phone,name,surname,full_name,company_name,lang,blacklist,ip", "array");
            $user = array_merge($user, User::getInfo($user["id"], ["identity", "blacklist_reason", "blacklist_time", "blacklist_by_admin"]));
            $user["blacklist"] = User::checkBlackList($user, in_array($order['status'], ['waiting', 'inprocess']));

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

            $meta = __("admin/orders/meta-sms-detail");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => $this->AdminCRLink("orders"),
                    'title' => __("admin/orders/breadcrumb-list"),
                ],
                [
                    'link'  => false,
                    'title' => __("admin/orders/breadcrumb-sms-detail", ['{name}' => $order["name"]]),
                ],
            ];
            $this->addData("breadcrumb", $breadcrumbs);

            $situationsx = $this->view->chose("admin")->render("common-needs", false, false, true);
            $order_situations = $situationsx["orders"];
            $invoice_situations = $situationsx["invoices"];
            $osituations = $situationsx["origins"];

            $links = [
                'controller'       => $this->AdminCRLink("orders-2", ["detail", $order["id"]]),
                'list'             => $this->AdminCRLink("orders"),
                'create-new-order' => $this->AdminCRLink("orders-1", ["create"]),
                'detail-user-link' => $this->AdminCRLink("users-2", ["detail", $user["id"]]),
            ];

            $links["ajax-reports"] = $links["controller"] . "?operation=sms-reports.json";

            $links["select-users.json"] = $links["controller"] . "?operation=user-list.json";
            $links["group-link"] = $this->AdminCRLink("products", ["sms"]);
            if ($invoice) $links["invoice-link"] = $this->AdminCRLink("invoices-2", ["detail", $invoice["id"]]);

            $this->addData("links", $links);

            $modules = Modules::Load("Payment", false, true);
            $actveMods = Config::get("modules/payment-methods");
            $pmethods = [];
            if ($modules) {
                foreach ($modules as $key => $val) {
                    if (in_array($key, $actveMods) || $key == $order["pmethod"]) {
                        $pmethods[$key] = $val["lang"]["invoice-name"];
                    }
                }
            }

            $modules = Modules::Load("SMS", "All", true);
            $this->addData("modules", $modules);

            // We did this because if it is a tax-inclusive system, the order amount should appear tax-included.
            $taxation_type = Invoices::getTaxationType();
            $tax_rate = Invoices::getTaxRate();

            if ($invoice && $invoice["taxrate"] > 0.00) $tax_rate = $invoice["taxrate"];

            if ($taxation_type == 'inclusive' && $order["amount"] > 0.00 && $tax_rate > 0.00)
                $order["amount"] += Money::get_tax_amount($order["amount"], $tax_rate);
            $this->addData("taxation_type", $taxation_type);
            $this->addData("tax_rate", $tax_rate);

            // Events
            $p_events = Events::getList("operation", "order", $order["id"], false, "pending");
            $p_events2 = Events::getList("operation", "order", $order["id"], "cancelled-product-request");
            if ($p_events2) {
                foreach ($p_events2 as $k => $p) {
                    if ($p["status"] == "approved" && DateManager::strtotime($p["cdate"]) < DateManager::strtotime($order["renewaldate"])) unset($p_events2[$k]);
                }
                if ($p_events2) $p_events = array_merge($p_events, $p_events2);
            }

            $this->addData("pending_events", $p_events);

            $this->addData("privOperation", Admin::isPrivilege(["ORDERS_OPERATION"]));
            $this->addData("privDelete", Admin::isPrivilege(["ORDERS_DELETE"]));
            $this->addData("order", $order);
            $this->addData("product", $product);
            $this->addData("invoice", $invoice);
            $this->addData("user", $user);
            $this->addData("situations", $order_situations);
            $this->addData("invoice_situations", $invoice_situations);
            $this->addData("pmethods", $pmethods);
            $this->addData("origin_situations", $osituations);
            $this->addData("origins", $this->sms_origins($order["id"]));
            $this->addData("secret_key", Crypt::encode($order["id"], Config::get("crypt/system")));

            $invoices = Invoices::get_order_invoices($order["id"]);
            $this->addData("invoices", $invoices);

            $this->view->chose("admin")->render("sms-order-detail", $this->data);
        }


        private function domain_detail($order)
        {

            Helper::Load(["Money", "Products", "Orders", "Invoices"]);

            $invoice = Invoices::get_last_invoice($order["id"], '', 't2.*');
            $product = Products::get($order["type"], $order["product_id"], Config::get("general/local"));

            $user = User::getData($order["owner_id"], "id,email,phone,name,surname,full_name,company_name,lang,blacklist,ip", "array");
            $user = array_merge($user, User::getInfo($user["id"], ["identity", "blacklist_reason", "blacklist_time", "blacklist_by_admin", "gsm", "gsm_cc"]));
            $user["blacklist"] = User::checkBlackList($user, in_array($order['status'], ['waiting', 'inprocess']));

            $ulang = $user["lang"];

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

            $meta = __("admin/orders/meta-domain-detail");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => $this->AdminCRLink("orders"),
                    'title' => __("admin/orders/breadcrumb-list"),
                ],
                [
                    'link'  => false,
                    'title' => __("admin/orders/breadcrumb-domain-detail", ['{name}' => $order["name"]]),
                ],
            ];
            $this->addData("breadcrumb", $breadcrumbs);

            $situations = $this->view->chose("admin")->render("common-needs", false, false, true);
            $order_situations = $situations["orders"];
            $invoice_situations = $situations["invoices"];
            $subscription_situations = $situations["subscription"];

            $links = [
                'controller'       => $this->AdminCRLink("orders-2", ["detail", $order["id"]]),
                'list'             => $this->AdminCRLink("orders"),
                'create-new-order' => $this->AdminCRLink("orders-1", ["create"]),
                'detail-user-link' => $this->AdminCRLink("users-2", ["detail", $user["id"]]),
            ];

            $links["select-users.json"] = $links["controller"] . "?operation=user-list.json";
            $links["group-link"] = $this->AdminCRLink("products", ["domain"]);
            if ($invoice) $links["invoice-link"] = $this->AdminCRLink("invoices-2", ["detail", $invoice["id"]]);

            $modules = Modules::Load("Payment", false, true);
            $actveMods = Config::get("modules/payment-methods");
            $pmethods = [];
            if ($modules) {
                foreach ($modules as $key => $val) {
                    if (in_array($key, $actveMods) || $key == $order["pmethod"]) {
                        $pmethods[$key] = $val["lang"]["invoice-name"];
                    }
                }
            }


            $options = $order["options"];


            $whidden_amount = Config::get("options/domain-whois-privacy/amount");
            $whidden_cid = Config::get("options/domain-whois-privacy/cid");

            if ($order["module"] != "none" && $order["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $order["module"])) {
                    $module = new $order["module"]();
                    $whidden_amount = $fetchModule["config"]["settings"]["whidden-amount"];
                    $whidden_cid = $fetchModule["config"]["settings"]["whidden-currency"];
                    $this->addData("module", $module);
                }
            }


            $whois = $options["whois"];
            if ($whois) {
                if (!isset($whois["registrant"])) {
                    $whois["Address"] = $whois["AddressLine1"];
                    if ($whois["AddressLine2"]) $whois["Address"] .= $whois["AddressLine2"];

                    $whois = [
                        'registrant'     => $whois,
                        'administrative' => $whois,
                        'technical'      => $whois,
                        'billing'        => $whois,
                    ];
                }
            } else {
                $whois = [
                    'Name'             => null,
                    'EMail'            => null,
                    'Company'          => null,
                    'PhoneCountryCode' => null,
                    'Phone'            => null,
                    'FaxCountryCode'   => null,
                    'Fax'              => null,
                    'City'             => null,
                    'State'            => null,
                    'Address'          => null,
                    'Country'          => null,
                    'ZipCode'          => null,
                ];

                $whois = [
                    'registrant'     => $whois,
                    'administrative' => $whois,
                    'technical'      => $whois,
                    'billing'        => $whois,
                ];
            }

            $this->addData("whois", $whois);


            $wprivacy = isset($options["whois_privacy"]) && $options["whois_privacy"];

            $whois_privacy_price = 0;
            if ($whidden_amount)
                $whois_privacy_price = Money::formatter_symbol($whidden_amount, $whidden_cid, $order["amount_cid"]);

            $whois_privacy_purchase = $whidden_amount > 0.00;
            $whois_privacy_endtime = false;

            if ($whois_privacy_purchase) {
                $isAddon = WDB::select("id,duedate,period")->from("users_products_addons");
                $isAddon->where("status", "=", "active", "&&");
                $isAddon->where("owner_id", "=", $order["id"], "&&");
                $isAddon->where("addon_key", "=", "whois-privacy");
                $isAddon = $isAddon->build() ? $isAddon->getObject() : false;

                if ($isAddon) {
                    $whois_privacy_purchase = false;
                    if ($isAddon->period != "none")
                        $whois_privacy_endtime = DateManager::format(Config::get("options/date-format"), $isAddon->duedate);
                }
            }

            // WHOIS
            $this->addData("wprivacy", $wprivacy);
            $this->addData("wprivacy_purchase", $whois_privacy_purchase);
            $this->addData("wprivacy_endtime", $whois_privacy_endtime);
            $this->addData("wprivacy_price", $whois_privacy_price);

            // We did this because if it is a tax-inclusive system, the order amount should appear tax-included.
            $taxation_type = Invoices::getTaxationType();
            $tax_rate = Invoices::getTaxRate();

            if ($invoice && $invoice["taxrate"] > 0.00) $tax_rate = $invoice["taxrate"];

            if ($taxation_type == 'inclusive' && $order["amount"] > 0.00 && $tax_rate > 0.00)
                $order["amount"] += Money::get_tax_amount($order["amount"], $tax_rate);
            $this->addData("taxation_type", $taxation_type);
            $this->addData("tax_rate", $tax_rate);

            // Events
            $p_events = Events::getList("operation", "order", $order["id"], false, "pending");
            $p_events2 = Events::getList("operation", "order", $order["id"], "cancelled-product-request");
            if ($p_events2) {
                foreach ($p_events2 as $k => $p) {
                    if ($p["status"] == "approved" && DateManager::strtotime($p["cdate"]) < DateManager::strtotime($order["renewaldate"])) unset($p_events2[$k]);
                }
                if ($p_events2) $p_events = array_merge($p_events, $p_events2);
            }
            $this->addData("pending_events", $p_events);

            $this->addData("links", $links);
            $this->addData("privOperation", Admin::isPrivilege(["ORDERS_OPERATION"]));
            $this->addData("privDelete", Admin::isPrivilege(["ORDERS_DELETE"]));
            $this->addData("order", $order);
            $this->addData("product", $product);
            $this->addData("invoice", $invoice);
            $this->addData("user", $user);
            $this->addData("situations", $order_situations);
            $this->addData("invoice_situations", $invoice_situations);
            $this->addData("subscription_situations", $subscription_situations);
            $this->addData("pmethods", $pmethods);

            $invoices = Invoices::get_order_invoices($order["id"]);
            $this->addData("invoices", $invoices);

            $registrar_modules = Modules::Load("Registrars", "All", true);
            $this->addData("registrar_modules", $registrar_modules);


            $sub_id = $order["subscription_id"];
            $subscription = $sub_id ? Orders::get_subscription($sub_id) : [];

            if (Filter::REQUEST("bring") == "subscription_detail" && $subscription)
                $subscription = Orders::sync_subscription($subscription);

            $this->addData("subscription", $subscription);

            if (Filter::REQUEST("bring") == "subscription_detail" && $subscription)
                return $this->view->chose("admin")->render("order-subscription-detail", $this->data);


            $whois_profiles = User::whois_profiles($order["owner_id"]);

            $this->addData("whois_profiles", $whois_profiles);

            $this->addData("contact_types", [
                'registrant'     => __("website/account_products/whois-contact-type-registrant"),
                'administrative' => __("website/account_products/whois-contact-type-administrative"),
                'technical'      => __("website/account_products/whois-contact-type-technical"),
                'billing'        => __("website/account_products/whois-contact-type-billing"),
            ]);


            $user["address"] = AddressManager::getAddress(0, $user["id"]);

            $zipcode = AddressManager::generate_postal_code($user["address"]["country_code"]);
            $state_x = $user["address"]["counti"];
            $city_y = $user["address"]["city"];
            $country_code = $user["address"]["country_code"];

            Filter::$transliterate_cc = $country_code;


            if ($country_code == "TR") {
                $state = $state_x;
                $city = $city_y;
            } else {
                $state = $city_y;
                $city = $state_x;
            }

            $user_whois_info = [
                'Name'             => $user["full_name"],
                'FirstName'        => $user["name"],
                'LastName'         => $user["surname"],
                'Company'          => $user["company_name"],
                'AddressLine1'     => $user["address"]["address"],
                'AddressLine2'     => null,
                'ZipCode'          => $user["address"]["zipcode"] ? $user["address"]["zipcode"] : $zipcode,
                'State'            => $state,
                'City'             => $city,
                'Country'          => $country_code,
                'EMail'            => $user["email"],
                'Phone'            => $user["gsm"],
                'PhoneCountryCode' => $user["gsm_cc"],
                'Fax'              => null,
                'FaxCountryCode'   => null,
            ];

            $this->addData("user_whois_info", $user_whois_info);

            $require_verification = false;
            $manuel_doc_fields = [];
            $module_docs = [];


            if ($order["module"] && $order["module"] != "none") {
                $module_config = Modules::Load("Registrars", $order["module"]);
                $module_name = $order["module"];
                if (class_exists($module_name)) {
                    $module_con = new $module_name();

                    if (method_exists($module_con, 'set_order')) $module_con->set_order($order);
                    $this->addData("module_con", $module_con);

                    if (isset($module_con->config["settings"]["doc-fields"][$options["tld"]]) && $module_con->config["settings"]["doc-fields"][$options["tld"]])
                        $module_docs = $module_con->config["settings"]["doc-fields"][$options["tld"]];
                }
            }

            // Found Manuel Information/Document Fields
            $found_doc_fields = $this->model->db->select()->from("tldlist_docs");
            $found_doc_fields->where("tld", "=", $options["tld"]);
            $found_doc_fields->order_by("sortnum ASC");
            if ($found_doc_fields->build()) $manuel_doc_fields = $found_doc_fields->fetch_assoc();

            // added information/documents
            $uploaded_docs = $this->model->db->select()->from("users_products_docs");
            $uploaded_docs->where("owner_id", "=", $order["id"]);
            $uploaded_docs->order_by("id DESC");
            $uploaded_docs = $uploaded_docs->build() ? $uploaded_docs->fetch_assoc() : [];
            if ($uploaded_docs) {
                foreach ($uploaded_docs as $k => $v) {
                    $value = $v["value"] ? Crypt::decode($v["value"], Config::get("crypt/user")) : '';
                    $file = $v["file"] ? Crypt::decode($v["file"], Config::get("crypt/user")) : '';
                    $m_data = $v["module_data"] ? Crypt::decode($v["module_data"], Config::get("crypt/user")) : '';

                    if ($file) $file = Utility::jdecode($file, true);
                    if ($m_data) $m_data = Utility::jdecode($m_data, true);

                    $uploaded_docs[$k]["value"] = $value;
                    $uploaded_docs[$k]["file"] = $file;
                    $uploaded_docs[$k]["module_data"] = $m_data;
                }
            }

            // External Verification Docs
            $operator_docs = $options["verification_operator_docs"] ?? [];

            $info_docs = [];

            if (is_array($module_docs) && sizeof($module_docs) > 0) {
                foreach ($module_docs as $md_k => $md_c) {
                    $md_c["name"] = RegistrarModule::get_doc_lang($md_c["name"]);
                    if (isset($md_c["options"]) && $md_c["options"])
                        foreach ($md_c["options"] as $k => $v) $md_c["options"][$k] = RegistrarModule::get_doc_lang($v);
                    $info_docs["mod_" . $md_k] = $md_c;
                }
            }

            if (is_array($manuel_doc_fields) && sizeof($manuel_doc_fields) > 0) {
                foreach ($manuel_doc_fields as $md) {
                    $md["languages"] = Utility::jdecode($md["languages"], true);
                    $md["options"] = Utility::jdecode($md["options"], true);

                    $first_d_ch = current($md["languages"]);
                    $d_name = $first_d_ch["name"] ?? 'Noname';

                    if (isset($md["languages"][$ulang]["name"]))
                        $d_name = $md["languages"][$ulang]["name"] ?? 'Noname';

                    if (!$d_name) $d_name = "Noname";

                    $ll = Config::get("general/local");


                    $d_opts = [];

                    if ($md["type"] == "select" && $md["options"] && sizeof($md["options"]) > 0) {
                        if (is_array($md["options"]) && sizeof($md["options"]) > 0) {
                            foreach ($md["options"] as $d_opt_k => $d_opt) {
                                $d_opt_name = $d_opt[$ll]["name"] ?? 'Noname';
                                if (isset($d_opt[$ulang])) $d_opt_name = $d_opt[$ulang]["name"] ?? 'Noname';
                                $d_opts[$d_opt_k] = $d_opt_name;
                            }
                        }

                    }


                    $info_docs["d_" . $md["id"]] = [
                        'type' => $md["type"],
                        'name' => $d_name,
                    ];

                    if (sizeof($d_opts) > 0) $info_docs["d_" . $md["id"]]["options"] = $d_opts;
                }
            }

            if (is_array($operator_docs) && sizeof($operator_docs)) {
                foreach ($operator_docs as $od_k => $od) {
                    $info_docs["op_" . $od_k] = [
                        'type' => $od["type"],
                        'name' => $od["name"],
                    ];
                    if (isset($od["options"]) && $od["options"]) $info_docs["op_" . $od_k]["options"] = $od["options"];
                }
            }


            $this->addData("info_docs", $info_docs);
            $this->addData("uploaded_docs", $uploaded_docs);


            $this->view->chose("admin")->render("domain-order-detail", $this->data);
        }


        public function orders($status = '')
        {

            $group = substr(Filter::init("GET/group", "route"), 0, 16);

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
                'controller'       => $status ? $this->AdminCRLink("orders-1", [$status]) : $this->AdminCRLink("orders"),
                'create-new-order' => $this->AdminCRLink("orders-1", ["create"]),
                'all'              => $this->AdminCRLink("orders"),
                'active'           => $this->AdminCRLink("orders-1", ["active"]),
                'inprocess'        => $this->AdminCRLink("orders-1", ["inprocess"]),
                'suspended'        => $this->AdminCRLink("orders-1", ["suspended"]),
                'cancelled'        => $this->AdminCRLink("orders-1", ["cancelled"]),
                'overdue'          => $this->AdminCRLink("orders-1", ["overdue"]),
            ];

            $links["ajax-list"] = $links["controller"] . "?operation=ajax-list" . ($group ? '&group=' . $group : '');

            $this->addData("links", $links);

            $meta = __("admin/orders/meta-list");
            if ($status) $meta = __("admin/orders/meta-list-" . $status);

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            if ($status) {
                array_push($breadcrumbs, [
                    'link'  => $this->AdminCRLink("orders"),
                    'title' => __("admin/orders/breadcrumb-list"),
                ]);

                array_push($breadcrumbs, [
                    'link'  => false,
                    'title' => __("admin/orders/breadcrumb-list-" . $status),
                ]);

            } else
                array_push($breadcrumbs, [
                    'link'  => false,
                    'title' => __("admin/orders/breadcrumb-list"),
                ]);


            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("status", $status);

            Helper::Load("Money");

            $this->addData('bubble_count', $this->model->get_orders_total($status, $group, ['l_type' => "notifications"]));

            $product_groups = [];

            if (Config::get("options/pg-activation/domain"))
                $product_groups['domain'] = __("website/account_products/product-type-names/domain");

            if (Config::get("options/pg-activation/hosting"))
                $product_groups['hosting'] = __("website/account_products/product-type-names/hosting");

            if (Config::get("options/pg-activation/server"))
                $product_groups['server'] = __("website/account_products/product-type-names/server");

            if (Config::get("options/pg-activation/software"))
                $product_groups['software'] = __("website/account_products/product-type-names/software");

            if (Config::get("options/pg-activation/sms"))
                $product_groups['sms'] = __("website/account_products/product-type-names/sms");

            Helper::Load("Products");

            foreach (Products::special_groups() as $g)
                $product_groups['special-' . $g["id"]] = $g["title"];

            $this->addData("product_groups", $product_groups);
            $this->addData("group", $group);

            $this->view->chose("admin")->render("orders", $this->data);
        }


        public function updowngrades()
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
                'controller' => $this->AdminCRLink("orders-1", ["updowngrades"]),
            ];

            $links["ajax-updowngrades"] = $links["controller"] . "?operation=ajax-updowngrades";

            $this->addData("links", $links);

            $meta = __("admin/orders/meta-updowngrades");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/orders/breadcrumb-updowngrades"),
            ]);


            $this->addData("breadcrumb", $breadcrumbs);

            Helper::Load("Money");

            $this->view->chose("admin")->render("orders-updowngrades", $this->data);
        }


        public function cancellation_requests()
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
                'controller' => $this->AdminCRLink("orders-1", ["cancellation-requests"]),
            ];

            $links["ajax-cancellation-requests"] = $links["controller"] . "?operation=ajax-cancellation-requests";

            $this->addData("links", $links);

            $meta = __("admin/orders/meta-cancellation-requests");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/orders/breadcrumb-cancellation-requests"),
            ]);


            $this->addData("breadcrumb", $breadcrumbs);

            Helper::Load("Money");

            $this->view->chose("admin")->render("orders-cancellation-requests", $this->data);
        }


        public function addons()
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
                'controller' => $this->AdminCRLink("orders-1", ["addons"]),
            ];

            $links["ajax-addons"] = $links["controller"] . "?operation=ajax-addons";

            $this->addData("links", $links);

            $meta = __("admin/orders/meta-addons");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/orders/breadcrumb-addons"),
            ]);


            $this->addData("breadcrumb", $breadcrumbs);

            Helper::Load("Money");

            $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
            $situations = $situations["orders"];

            $this->addData("situations", $situations);

            $this->addData('bubble_count', $this->model->get_addons_total(['l_type' => "notifications"]));

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

            $this->view->chose("admin")->render("orders-addons", $this->data);
        }


        public function main()
        {
            $operation = Filter::init("REQUEST/operation", "route");
            if ($operation) return $this->operationMain($operation);

            $page = isset($this->params[0]) ? $this->params[0] : false;
            return $this->pageMain($page);
        }

    }