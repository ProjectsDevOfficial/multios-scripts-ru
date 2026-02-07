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
            if (!Admin::isPrivilege(Config::get("privileges/FINANCIAL"))) die();
        }


        private function taxation_settings()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Money"]);

            $status = (int)Filter::init("POST/status", "numbers");
            $rate = Filter::init("POST/rate", "amount");
            $taxation_type = (string)Filter::init("POST/taxation_type", "letters");


            $rate = str_replace(",", ".", $rate);
            if (gettype($rate) == "string" && $rate == '') $rate = (string)$rate;
            elseif (stristr($rate, ".")) $rate = (float)$rate;
            else $rate = (int)$rate;


            $config_sets = [];
            $config_sets2 = [];

            if ($status != Config::get("options/taxation")) {
                $config_sets["options"]["taxation"] = $status;
            }

            if ($taxation_type != Config::get("options/taxation-type")) {
                $config_sets["options"]["taxation-type"] = $taxation_type;
            }

            if ($rate != Config::get("options/tax-rate")) {
                $config_sets["options"]["tax-rate"] = $rate;
            }


            $rates = Config::get("options/country-tax-rates");

            $loc_cid = AddressManager::LocalCountryID();
            if (isset($rates[$loc_cid]) && $rates[$loc_cid] != $rate) {
                $config_sets["options"]["country-tax-rates"][$loc_cid] = $rate;
            }


            $changes = 0;

            if ($config_sets) {

                if (isset($config_sets["options"])) {
                    $options_result = Config::set("options", $config_sets["options"]);
                    if (isset($config_sets2["options"]))
                        $options_result = Config::set("options", $config_sets2["options"]);
                    $var_export = Utility::array_export($options_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                    if ($write) $changes++;
                }

                if ($changes) {
                    $adata = UserManager::LoginData("admin");
                    User::addAction($adata["id"], "alteration", "changed-taxation-settings");
                }
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/financial/success1")]);
        }

        private function taxation_advanced()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Money"]);

            $config_sets = [];
            $config_sets2 = [];

            $invoice_show_reqrslgn = (int)Filter::init("POST/invoice-show-requires-login", "numbers");
            $delete_invoice_item_aoc = (int)Filter::init("POST/delete-invoice-item-aoc", "numbers");
            $detect_auto_price_on_invoice = (int)Filter::init("POST/detect-auto-price-on-invoice", "numbers");
            $g_invoices_auto_increment = (int)Filter::init("POST/invoices_auto_increment", "numbers");
            $g_invoices_auto_increment_p = (int)Filter::init("POST/paid_invoices_auto_increment", "numbers");

            $sebilltad_status = (int)Filter::init("POST/sebilltad_status", "numbers");
            $sebilltad_amount = Filter::init("POST/sebilltad_amount", "amount");
            $sebilltad_cid = (int)Filter::init("POST/sebilltad_cid", "numbers");
            $sebilltad_amount = Money::deformatter($sebilltad_amount, $sebilltad_cid);

            $inv_num_ft_status = (int)Filter::init("POST/invoice-num-format-status", "numbers");
            $inv_num_ft_status_p = (int)Filter::init("POST/paid-invoice-num-format-status", "numbers");
            $invoice_num_format = Filter::init("POST/invoice-num-format", "hclear");
            $invoice_num_format_p = Filter::init("POST/paid-invoice-num-format", "hclear");

            $company_tax_office_status = (int)Filter::init("POST/company_tax_office_status", "numbers");
            $company_tax_office_required = (int)Filter::init("POST/company_tax_office_required", "numbers");
            $company_tax_number_status = (int)Filter::init("POST/company_tax_number_status", "numbers");
            $company_tax_number_required = (int)Filter::init("POST/company_tax_number_required", "numbers");
            $company_tax_number_check = (int)Filter::init("POST/company_tax_number_check", "numbers");
            $invoice_special_note = Filter::init("POST/invoice_special_note");
            $invoice_formalization_status = (int)Filter::init("POST/invoice-formalization-status", "numbers");
            $firstly_create_invoice = (int)Filter::init("POST/firstly-create-invoice", "numbers");
            $balance_taxation = (string)Filter::init("POST/balance-taxation", "letters");
            $pdf_font = (string)Filter::init("POST/pdf-font");


            if ($pdf_font != Config::get("options/pdf-font")) $config_sets["options"]["pdf-font"] = $pdf_font;


            if ($company_tax_office_status)
                $config_sets["options"]["sign"]["up"]["kind"]["corporate"]["company_tax_office"] = [
                    'required' => $company_tax_office_required,
                ];
            else
                $config_sets["options"]["sign"]["up"]["kind"]["corporate"]["company_tax_office"] = null;

            if ($company_tax_number_status)
                $config_sets["options"]["sign"]["up"]["kind"]["corporate"]["company_tax_number"] = [
                    'required' => $company_tax_number_required,
                    'check'    => $company_tax_number_check,
                ];
            else
                $config_sets["options"]["sign"]["up"]["kind"]["corporate"]["company_tax_number"] = null;


            if ($invoice_show_reqrslgn != Config::get("options/invoice-show-requires-login")) {
                $config_sets["options"]["invoice-show-requires-login"] = $invoice_show_reqrslgn;
            }


            if ($delete_invoice_item_aoc != Config::get("options/delete-invoice-item-aoc"))
                $config_sets["options"]["delete-invoice-item-aoc"] = $delete_invoice_item_aoc;


            if ($detect_auto_price_on_invoice != Config::get("options/detect-auto-price-on-invoice"))
                $config_sets["options"]["detect-auto-price-on-invoice"] = $detect_auto_price_on_invoice;


            if ($invoice_num_format != Config::get("options/invoice-num-format")) {
                if (!stristr($invoice_num_format, "{NUMBER}"))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='invoice-num-format']",
                        'message' => __("admin/settings/error12"),
                    ]));
                $config_sets["options"]["invoice-num-format"] = $invoice_num_format;
            }

            if ($sebilltad_status != Config::get("options/send-bill-to-address/status")) {
                $config_sets["options"]["send-bill-to-address"]["status"] = $sebilltad_status;
            }

            if ($sebilltad_amount != Config::get("options/send-bill-to-address/amount")) {
                $config_sets["options"]["send-bill-to-address"]["amount"] = $sebilltad_amount;
            }

            if ($sebilltad_cid != Config::get("options/send-bill-to-address/cid")) {
                $config_sets["options"]["send-bill-to-address"]["cid"] = $sebilltad_cid;
            }

            if ($inv_num_ft_status != Config::get("options/invoice-num-format-status"))
                $config_sets["options"]["invoice-num-format-status"] = $inv_num_ft_status;

            if ($inv_num_ft_status_p != Config::get("options/paid-invoice-num-format-status"))
                $config_sets["options"]["paid-invoice-num-format-status"] = $inv_num_ft_status_p;

            if ($invoice_num_format != Config::get("options/invoice-num-format"))
                $config_sets["options"]["invoice-num-format"] = $invoice_num_format;

            if ($invoice_num_format_p != Config::get("options/paid-invoice-num-format"))
                $config_sets["options"]["paid-invoice-num-format"] = $invoice_num_format_p;

            if ($invoice_special_note != Config::get("options/invoice_special_note"))
                $config_sets["options"]["invoice_special_note"] = $invoice_special_note;

            if ($invoice_formalization_status != Config::get("options/invoice-formalization-status"))
                $config_sets["options"]["invoice-formalization-status"] = $invoice_formalization_status;

            if ($firstly_create_invoice != Config::get("options/firstly-create-invoice"))
                $config_sets["options"]["firstly-create-invoice"] = $firstly_create_invoice;

            if ($balance_taxation != Config::get("options/balance-taxation"))
                $config_sets["options"]["balance-taxation"] = $balance_taxation;


            Config::setd("invoice-id", $g_invoices_auto_increment);
            Config::setd("invoice-id-paid", $g_invoices_auto_increment_p);


            $changes = 0;

            if ($config_sets) {

                if (isset($config_sets["options"])) {
                    $options_result = Config::set("options", $config_sets["options"]);
                    if (isset($config_sets2["options"]))
                        $options_result = Config::set("options", $config_sets2["options"]);
                    $var_export = Utility::array_export($options_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                    if ($write) $changes++;
                }

                if ($changes) {
                    $adata = UserManager::LoginData("admin");
                    User::addAction($adata["id"], "alteration", "changed-taxation-settings");
                }
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/financial/success1")]);
        }

        private function update_tax_rates()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Money"]);

            $country_rates = Filter::POST("country_rates");
            $city_rates = Filter::POST("city_rates");


            $config_sets = [];
            $config_sets2 = [];

            $loc_cid = AddressManager::LocalCountryID();


            if ($country_rates && is_array($country_rates)) {
                foreach ($country_rates as $k => $v) {
                    $v = str_replace(",", ".", $v);
                    if (gettype($v) == "string" && $v == '') $v = (string)$v;
                    elseif (stristr($v, ".")) $v = (float)$v;
                    else $v = (int)$v;
                    $config_sets["options"]["country-tax-rates"][$k] = $v;

                    if ($k == $loc_cid) $config_sets["options"]["tax-rate"] = $v;
                }
            }

            if ($city_rates && is_array($city_rates)) {
                foreach ($city_rates as $c_id => $rates) {
                    foreach ($rates as $k => $v) {
                        $v = str_replace(",", ".", $v);
                        if (gettype($v) == "string" && $v == '') $v = (string)$v;
                        elseif (stristr($v, ".")) $v = (float)$v;
                        else $v = (int)$v;
                        $config_sets["options"]["city-tax-rates"][$c_id][$k] = $v;
                    }
                }
            }

            if ($config_sets) {

                if (isset($config_sets["options"])) {
                    $options_result = Config::set("options", $config_sets["options"]);
                    if (isset($config_sets2["options"]))
                        $options_result = Config::set("options", $config_sets2["options"]);
                    $var_export = Utility::array_export($options_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                }

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "changed-tax-rates");
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/financial/success1")]);
        }

        private function edit_tax_rule()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Money"]);

            $country_id = (int)Filter::init("POST/country_id", "numbers");
            $city_id = (int)Filter::init("POST/city_id", "numbers");
            $cc = AddressManager::get_cc_with_id($country_id);
            $local_cc = strtoupper(Config::get("general/country"));
            $rates_names = Filter::init("POST/rates/name");
            $rates_values = Filter::init("POST/rates/value");


            $config_sets = [];
            $config_sets2 = [];

            $rate = '';

            $config_sets["options"]["tax-rates-names"][$country_id][$city_id] = null;
            $config_sets2["options"]["tax-rates-names"][$country_id][$city_id] = [];

            if ($rates_values) {
                foreach ($rates_values as $k => $v) {
                    $rate_name = Filter::html_clear($rates_names[$k] ?? '');
                    $rate_value = Filter::amount($v);
                    $rate_value = str_replace(",", ".", $rate_value);
                    if (gettype($rate_value) == "string" && $rate_value == '') $rate_value = (string)$rate_value;
                    elseif (stristr($rate_value, ".")) $rate_value = (float)$rate_value;
                    else $rate_value = (int)$rate_value;

                    $config_sets2["options"]["tax-rates-names"][$country_id][$city_id][] = [
                        'name'  => $rate_name,
                        'value' => $rate_value,
                    ];
                    if (!(gettype($rate_value) == "string" && $rate_value == '')) {
                        if (!$rate) $rate = 0;
                        $rate += $rate_value;
                    }
                }
            }

            if ($city_id) $config_sets["options"]["city-tax-rates"][$country_id][$city_id] = $rate;
            else {
                $config_sets["options"]["country-tax-rates"][$country_id] = $rate;

                if ($local_cc == $cc) $config_sets["options"]["tax-rate"] = $rate;
            }

            if ($config_sets) {

                if (isset($config_sets["options"])) {
                    $options_result = Config::set("options", $config_sets["options"]);
                    if (isset($config_sets2["options"]))
                        $options_result = Config::set("options", $config_sets2["options"]);
                    $var_export = Utility::array_export($options_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                }

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "changed-tax-rates");
            }

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/financial/success1"),
                'redirect' => $this->AdminCRLink("financial", ["taxation"]) . "?tab=tax-rates",
            ]);
        }

        private function add_new_tax_rule()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Money"]);

            $country_id = (int)Filter::init("POST/country", "numbers");
            $city_id = (int)Filter::init("POST/city_id", "numbers");
            $city_name = Filter::init("POST/city_name", "hclear");
            $rates_names = Filter::init("POST/rates/name");
            $rates_values = Filter::init("POST/rates/value");
            $cc = AddressManager::get_cc_with_id($country_id);


            if (!$country_id || !$cc)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/financial/error15"),
                ]));

            /*
            if(Validation::isEmpty($city_name) && !$city_id)
                die(Utility::jencode([
                    'status' => "error",
                    'message' => __("admin/financial/error16"),
                ]));
            */

            if (!Validation::isEmpty($city_name)) {
                $city_insert = Models::$init->db->insert("cities", [
                    'country_id' => $country_id,
                    'name'       => $city_name,
                    'slug'       => Filter::permalink($city_name),
                ]);
                if (!$city_insert)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => "The City could not be added",
                    ]));
                $city_id = Models::$init->db->lastID();
            }

            if ($city_id && !AddressManager::getCityName($city_id))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/financial/error16"),
                ]));

            $config_sets = [];
            $config_sets2 = [];

            $rate = '';

            $config_sets["options"]["tax-rates-names"][$country_id][$city_id] = null;
            $config_sets2["options"]["tax-rates-names"][$country_id][$city_id] = [];

            if ($rates_values) {
                foreach ($rates_values as $k => $v) {
                    $rate_name = Filter::html_clear($rates_names[$k] ?? '');
                    $rate_value = Filter::amount($v);
                    $rate_value = str_replace(",", ".", $rate_value);
                    if (gettype($rate_value) == "string" && $rate_value == '') $rate_value = (string)$rate_value;
                    elseif (stristr($rate_value, ".")) $rate_value = (float)$rate_value;
                    else $rate_value = (int)$rate_value;

                    $config_sets["options"]["tax-rates-names"][$country_id][$city_id][] = [
                        'name'  => $rate_name,
                        'value' => $rate_value,
                    ];
                    if (!(gettype($rate_value) == "string" && $rate_value == '')) {
                        if (!$rate) $rate = 0;
                        $rate += $rate_value;
                    }
                }
            }

            if ($city_id) $config_sets["options"]["city-tax-rates"][$country_id][$city_id] = $rate;
            else {
                $config_sets2["options"]["country-tax-rates"][$country_id] = $rate;

                if (strtoupper(Config::get("general/country")) == $cc) $config_sets["options"]["tax-rate"] = $rate;
            }

            if ($config_sets) {

                if (isset($config_sets["options"])) {
                    $options_result = Config::set("options", $config_sets["options"]);
                    if (isset($config_sets2["options"]))
                        $options_result = Config::set("options", $config_sets2["options"]);
                    $var_export = Utility::array_export($options_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                }

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "changed-tax-rates");
            }

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/financial/success6"),
                'redirect' => $this->AdminCRLink("financial", ["taxation"]) . "?tab=tax-rates",
            ]);
        }

        private function delete_tax_rule()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Money"]);

            $country_id = (int)Filter::init("POST/country_id", "numbers");
            $city_id = (int)Filter::init("POST/city_id", "numbers");


            if (!$country_id || !AddressManager::get_cc_with_id($country_id))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/financial/error15"),
                ]));

            if (!$city_id)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/financial/error16"),
                ]));

            if (!AddressManager::getCityName($city_id))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/financial/error16"),
                ]));

            $config_sets = Config::get("options");

            $rates_names = Config::get("options/tax-rates-names");
            $rates = Config::get("options/city-tax-rates/" . $country_id);
            if (!$rates) $rates = [];

            if (isset($rates[$city_id])) {
                unset($rates[$city_id]);
                $config_sets["options"]["city-tax-rates"][$country_id] = null;
                $config_sets2["options"]["city-tax-rates"][$country_id] = $rates;
            }

            if (isset($rates_names[$country_id][$city_id])) {
                unset($rates_names[$country_id][$city_id]);
                $config_sets["options"]["tax-rates-names"][$country_id] = null;
                $config_sets2["options"]["tax-rates-names"][$country_id] = $rates_names[$country_id];
            }

            $delete = true;#Models::$init->db->delete("cities")->where("id","=",$city_id)->run();

            if (!$delete)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Failed to Delete",
                ]));


            if ($config_sets) {

                if (isset($config_sets["options"])) {
                    $options_result = Config::set("options", $config_sets["options"]);
                    if (isset($config_sets2["options"]))
                        $options_result = Config::set("options", $config_sets2["options"]);
                    $var_export = Utility::array_export($options_result, ['pwith' => true]);
                    $write = FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                }

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "changed-tax-rates");
            }

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/financial/success7"),
            ]);
        }


        private function set_currencies_rate($isthis = false, $id = 0)
        {

            if (!$isthis) $this->takeDatas("language");

            if ($isthis && DEMO_MODE) return false;

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $cron = Config::get("cronjobs/tasks/auto-currency-rates");

            if (substr($cron["next-run-time"], 0, 4) != "0000" && !$id && !$isthis) {
                $time = DateManager::strtotime();
                $ctime = DateManager::strtotime($cron["next-run-time"]);

                if ($ctime > $time)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/financial/error8", [
                            '{time}' => DateManager::str_expression([$cron['period'] => $cron['time']]),
                        ]),
                    ]));
            }


            Helper::Load("Money");

            if ($id) $currencies = [0 => $this->model->get_currency($id)];
            else $currencies = $this->model->get_currencies();

            $changes = 0;
            $lcurrency = Money::Currency(Config::get("general/currency"));
            $local_code = $lcurrency["code"];

            if ($currencies) {
                $to = [];
                foreach ($currencies as $currency) if ($currency["code"] != $local_code && $currency["status"] == "active") $to[$currency["id"]] = $currency["code"];
                $rates = Money::get_exchange_rates($local_code, $to);
                if ($rates && is_array($rates)) {
                    foreach ($rates as $code => $rate) {
                        $cid = array_search($code, $to);
                        if ($rate) {
                            $changes++;
                            $this->model->set_currency($cid, ['rate' => $rate]);
                        }
                    }
                } elseif (!$isthis)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => $rates,
                    ]));
            }

            if ($changes) {

                $replace_cron = [
                    'tasks' => [
                        'auto-currency-rates' => [
                            'last-run-time' => DateManager::Now(),
                            'next-run-time' => DateManager::next_date([$cron['period'] => $cron["time"]]),
                        ],
                    ],
                ];
                $set_cron = Config::set("cronjobs", $replace_cron);
                $export_cron = Utility::array_export($set_cron, ['pwith' => true]);
                FileManager::file_write(CONFIG_DIR . "cronjobs.php", $export_cron);

                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "changed-currency-rates");
                self::$cache->clear();
            }

            Hook::run("ExchangeRatesUpdated");


            if (!$isthis) echo Utility::jencode(['status' => "successful", 'message' => __("admin/financial/success2")]);
        }


        private function change_currecy_status()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            $status = Filter::init("POST/status", "letters");
            $status = $status == "true" ? "active" : "inactive";

            Helper::Load("Money");

            $currency = $this->model->get_currency($id);
            if (!$currency) die("err1");

            if ($status == $currency["status"]) die("err2");

            /*if($status == "inactive" && $this->model->isit_used($id))
                die(Utility::jencode([
                    'status' => "error",
                    'for' => "local",
                    'message' => __("admin/financial/error9"),
                ]));*/

            if ($currency["local"])
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "local",
                    'message' => __("admin/financial/error1"),
                ]));


            /*
            $defined_data       = $this->model->db->select("id")->from("prices");
            $defined_data->where("cid","=",$id);
            $defined_data       = $defined_data->build() ? $defined_data->rowCounter() : 0;

            if($defined_data > 0 && $status == "inactive")
            {
                die(Utility::jencode([
                    'status' => "error",
                    'for' => "local",
                    'message' => __("admin/financial/error17"),
                ]));
            }
            */

            $update = $this->model->set_currency($id, ['status' => $status]);


            if ($status == "active") $this->set_currencies_rate(true, $id);

            $getC = $this->model->get_currency($id);

            self::$cache->clear();

            /*
            if($status == "inactive"){
                $this->model->db->update("prices",[
                    'cid' => Config::get("general/currency"),
                ])->where('cid','=',$currency['id'])->save();

                $this->model->db->update("tldlist",[
                    'currency' => Config::get("general/currency"),
                ])->where('currency','=',$currency['id'])->save();
            }
            */


            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-currency-status", ['id' => $id, '{code}' => $currency["code"]]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/financial/success1"),
                'rate'    => $getC["rate"],
            ]);

        }


        private function get_currency()
        {

            $this->takeDatas("language");

            $id = (int)Filter::init("POST/id", "numbers");

            $data = $this->model->get_currency($id);
            if ($data) {

                $lang = Bootstrap::$lang->clang;

                $data["countries"] = $this->model->get_currency_countries($lang, $data["countries"], $id);

                $data = Utility::jencode([
                    'status' => "successful",
                    'data'   => $data,
                ]);
            } else {
                $data = Utility::jencode([
                    'status'  => "error",
                    'message' => "Currency is not found.",
                ]);
            }
            echo $data;
        }


        private function change_currency()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            $name = Filter::init("POST/name", "hclear");
            $prefix = Filter::init("POST/prefix", "hclear");
            $suffix = Filter::init("POST/suffix", "hclear");
            $format = Filter::init("POST/format", "rnumbers");
            $rate = (float)Filter::init("POST/rate", "hclear");
            $local = (int)Filter::init("POST/local", "numbers");
            $localc = Config::get("general/currency");
            $countries = Filter::POST("countries");
            $modules = Filter::POST("modules");
            $countries = $countries && is_array($countries) ? implode(",", $countries) : null;
            $modules = $modules && is_array($modules) ? implode(",", $modules) : null;

            $currency = $this->model->get_currency($id);
            if (!$currency) die();
            $lang = Bootstrap::$lang->clang;

            $sets = [];

            if ($countries) {
                $checkCountries = $this->model->check_currency_countries($lang, $countries, $id);
                if ($checkCountries) {
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/financial/error6", [
                            '{country_name}'  => $checkCountries["country_name"],
                            '{currency_name}' => $checkCountries["currency_name"],
                        ]),
                    ]));
                }
            }
            if ($countries != $currency["countries"]) $sets["countries"] = $countries;
            if ($modules != $currency["modules"]) $sets["modules"] = $modules;

            if ($name != $currency["name"]) $sets["name"] = $name;
            if ($prefix != $currency["prefix"]) $sets["prefix"] = $prefix;
            if ($suffix != $currency["suffix"]) $sets["suffix"] = $suffix;
            if ($rate != (float)$currency["rate"]) $sets["rate"] = $rate;
            if ($format != (int)$currency["format"]) $sets["format"] = $format;
            if ($local != $currency["local"] && $id != $localc && $local) {
                $sets["local"] = 1;
                $sets["rate"] = 1;
                $sets["status"] = "active";
                $this->model->set_currency(0, ['local' => '0']);
                $general_result = Config::set("general", ['currency' => $id]);
                $var_export = Utility::array_export($general_result, ['pwith' => true]);
                FileManager::file_write(CONFIG_DIR . "general.php", $var_export);
                $change_rates = true;
            } else
                $change_rates = false;

            if ($sets) {
                $this->model->set_currency($id, $sets);
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "alteration", "changed-currency-settings", ['id' => $id, 'code' => $currency["code"]]);
            }

            self::$cache->clear();

            if ($change_rates) $this->set_currencies_rate(true);


            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/financial/success1"),
                'redirect' => $this->AdminCRLink("financial", ["currencies"]),
            ]);
        }


        private function add_new_coupon()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load("Money");

            $code = Filter::init("POST/code", "hclear");
            $type = Filter::init("POST/type", "letters");
            $rate = (float)Filter::init("POST/rate", "hclear");
            $amount = Filter::init("POST/amount", "amount");
            $cid = (int)Filter::init("POST/cid", "numbers");
            if ($type == "percentage") {
                $amount = 0;
                $cid = 0;
            }
            $pservices = Filter::POST("pservices");
            if (is_array($pservices)) $pservices = array_unique($pservices);
            $duedate = Filter::init("POST/duedate", "numbers");
            $period_type = Filter::init("POST/period_type", "letters");
            $period_duration = (int)Filter::init("POST/period_duration", "numbers");


            $maxuses = (int)Filter::init("POST/maxuses", "numbers");
            $taxfree = (int)Filter::init("POST/taxfree", "numbers");
            $applyonce = (int)Filter::init("POST/applyonce", "numbers");
            $onetime_use_per_order = (int)Filter::init("POST/onetime_use_per_order", "numbers");
            $newsignups = (int)Filter::init("POST/newsignups", "numbers");
            $existingcustomer = (int)Filter::init("POST/existingcustomer", "numbers");
            $dealership = (int)Filter::init("POST/dealership", "numbers");
            $use_merge = (int)Filter::init("POST/use_merge", "numbers");
            $used_in_invoices = (int)Filter::init("POST/used_in_invoices", "numbers");
            $recurring = (int)Filter::init("POST/recurring", "numbers");
            $recurring_num = (int)Filter::init("POST/recurring_num", "numbers");


            $notes = Filter::init("POST/notes", "dtext");

            if (Validation::isEmpty($code))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='code']",
                    'message' => __("admin/financial/error3"),
                ]));

            if ($type == "percentage" && $rate == 0)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='rate']",
                    'message' => __("admin/financial/error4"),
                ]));

            if ($type == "amount" && $amount == 0)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='amount']",
                    'message' => __("admin/financial/error5"),
                ]));

            if ($type == "amount") $amount = Money::deformatter($amount, $cid);


            $this->model->add_new_coupon([
                'code'                  => $code,
                'pservices'             => $pservices ? implode(",", $pservices) : '',
                'period_type'           => $period_type,
                'period_duration'       => $period_duration,
                'type'                  => $type,
                'rate'                  => $rate,
                'amount'                => $amount,
                'currency'              => $cid,
                'maxuses'               => $maxuses,
                'taxfree'               => $taxfree,
                'applyonce'             => $applyonce,
                'onetime_use_per_order' => $onetime_use_per_order,
                'newsignups'            => $newsignups,
                'existingcustomer'      => $existingcustomer,
                'used_in_invoices'      => $used_in_invoices,
                'recurring'             => $recurring,
                'recurring_num'         => $recurring_num,
                'dealership'            => $dealership,
                'use_merge'             => $use_merge,
                'cdate'                 => DateManager::Now(),
                'duedate'               => $duedate ? $duedate : DateManager::ata(),
                'notes'                 => $notes,
            ]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-coupon", [
                'code' => $code,
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/financial/success3"),
                'redirect' => $this->AdminCRLink("financial", ["coupons"]),
            ]);

        }


        private function delete_coupon()
        {
            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) die();

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $coupon = $this->model->get_coupon($id);

            if (!$coupon) die();

            $delete = $this->model->delete_coupon($id);

            if (!$delete) die("Can not delete coupon");

            if ($delete) {
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "delete", "deleted-coupon", ['id' => $coupon["id"], 'code' => $coupon["code"]]);
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/financial/success4")]);

        }


        private function edit_coupon()
        {
            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) die();

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $coupon = $this->model->get_coupon($id);

            if (!$coupon) die();

            Helper::Load("Money");

            $status = (int)Filter::init("POST/status", "numbers");
            $status = $status ? "active" : "inactive";
            $code = Filter::init("POST/code", "hclear");
            $type = Filter::init("POST/type", "letters");
            $rate = (float)Filter::init("POST/rate", "hclear");
            $amount = Filter::init("POST/amount", "amount");
            $cid = (int)Filter::init("POST/cid", "numbers");
            if ($type == "percentage") {
                $amount = 0;
                $cid = 0;
            }
            $pservices = Filter::POST("pservices");
            if (is_array($pservices)) $pservices = array_unique($pservices);
            $duedate = Filter::init("POST/duedate", "numbers");
            $period_type = Filter::init("POST/period_type", "letters");
            $period_duration = (int)Filter::init("POST/period_duration", "numbers");

            $maxuses = (int)Filter::init("POST/maxuses", "numbers");
            $taxfree = (int)Filter::init("POST/taxfree", "numbers");
            $applyonce = (int)Filter::init("POST/applyonce", "numbers");
            $onetime_use_per_order = (int)Filter::init("POST/onetime_use_per_order", "numbers");
            $newsignups = (int)Filter::init("POST/newsignups", "numbers");
            $existingcustomer = (int)Filter::init("POST/existingcustomer", "numbers");
            $used_in_invoices = (int)Filter::init("POST/used_in_invoices", "numbers");
            $dealership = (int)Filter::init("POST/dealership", "numbers");
            $use_merge = (int)Filter::init("POST/use_merge", "numbers");
            $recurring = (int)Filter::init("POST/recurring", "numbers");
            $recurring_num = (int)Filter::init("POST/recurring_num", "numbers");
            $notes = Filter::init("POST/notes", "dtext");

            if (Validation::isEmpty($code))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='code']",
                    'message' => __("admin/financial/error3"),
                ]));

            if ($type == "percentage" && $rate == 0)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='rate']",
                    'message' => __("admin/financial/error4"),
                ]));

            if ($type == "amount" && $amount == 0)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='amount']",
                    'message' => __("admin/financial/error5"),
                ]));


            if ($type == "amount") $amount = Money::deformatter($amount, $cid);


            $this->model->set_coupon($id, [
                'status'                => $status,
                'code'                  => $code,
                'pservices'             => $pservices ? implode(",", $pservices) : '',
                'period_type'           => $period_type,
                'period_duration'       => $period_duration,
                'type'                  => $type,
                'rate'                  => $rate,
                'amount'                => $amount,
                'currency'              => $cid,
                'maxuses'               => $maxuses,
                'taxfree'               => $taxfree,
                'applyonce'             => $applyonce,
                'onetime_use_per_order' => $onetime_use_per_order,
                'newsignups'            => $newsignups,
                'existingcustomer'      => $existingcustomer,
                'used_in_invoices'      => $used_in_invoices,
                'recurring'             => $recurring,
                'recurring_num'         => $recurring_num,
                'dealership'            => $dealership,
                'use_merge'             => $use_merge,
                'duedate'               => $duedate ? $duedate : DateManager::ata(),
                'notes'                 => $notes,
            ]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-coupon", [
                'code' => $code,
                'id'   => $id,
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/financial/success5"),
                'redirect' => $this->AdminCRLink("financial", ["coupons"]),
            ]);
        }


        private function add_new_promotion()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $type = Filter::init("POST/type", "letters");
            $rate = (float)Filter::init("POST/rate", "hclear");
            $amount = (float)Filter::init("POST/amount");
            $cid = (int)Filter::init("POST/cid", "numbers");
            $period1 = (string)Filter::init("POST/period1", "hclear");
            $period2 = (string)Filter::init("POST/period2", "hclear");
            $period_time1 = (int)Filter::init("POST/period_time1", "hclear");
            $period_time2 = (int)Filter::init("POST/period_time2", "hclear");
            $product = (string)Filter::init("POST/product", "hclear");

            if (!$period_time1) $period_time1 = 1;
            if (!$period_time2) $period_time2 = 1;


            if ($type == "percentage") {
                $amount = 0;
                $cid = 0;
            } elseif ($type == "free") {
                $amount = 0;
                $cid = 0;
                $rate = 0;
            }

            $name = Filter::init("POST/name", "hclear");
            $primary_product = Filter::POST("primary_product");
            if (is_array($primary_product)) $primary_product = array_unique($primary_product);
            $duedate = Filter::init("POST/duedate", "numbers");
            $maxuses = (int)Filter::init("POST/maxuses", "numbers");
            $applyonce = (int)Filter::init("POST/applyonce", "numbers");
            $notes = Filter::init("POST/notes", "dtext");


            if (!$name) $name = ___("needs/untitled");


            if (!$primary_product)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='primary_product[]']",
                    'message' => __("admin/financial/error10"),
                ]));

            if (!$product)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='product']",
                    'message' => __("admin/financial/error11"),
                ]));

            if (!$period2)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='period2']",
                    'message' => __("admin/financial/error12"),
                ]));

            if ($period1) {
                if (stristr(implode("\n", $primary_product), "product/domain") && (($period1 == "month" && !$period_time1 != 12) || $period1 != "year"))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "select[name='period1']",
                        'message' => __("admin/financial/error13"),
                    ]));
            }

            if (stristr($product, "product/domain") && (($period2 == "month" && !$period_time2 != 12) || $period2 != "year"))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='period2']",
                    'message' => __("admin/financial/error13"),
                ]));

            if (in_array($product, $primary_product))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='product']",
                    'message' => __("admin/financial/error14"),
                ]));


            if ($type == "percentage" && $rate == 0)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='rate']",
                    'message' => __("admin/financial/error4"),
                ]));

            if ($type == "amount" && $amount == 0)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='amount']",
                    'message' => __("admin/financial/error5"),
                ]));


            $this->model->add_new_promotion([
                'name'            => $name,
                'primary_product' => $primary_product ? implode(",", $primary_product) : '',
                'product'         => $product,
                'period1'         => $period1,
                'period2'         => $period2,
                'period_time1'    => $period_time1,
                'period_time2'    => $period_time2,
                'type'            => $type,
                'rate'            => $rate,
                'amount'          => $amount,
                'currency'        => $cid,
                'maxuses'         => $maxuses,
                'applyonce'       => $applyonce,
                'cdate'           => DateManager::Now(),
                'duedate'         => $duedate ? $duedate : DateManager::ata(),
                'notes'           => $notes,
            ]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-new-promotion", [
                'name' => $name,
                'id'   => $this->model->db->lastID(),
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/financial/success3"),
                'redirect' => $this->AdminCRLink("financial", ["promotions"]),
            ]);

        }


        private function delete_promotion()
        {
            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) die();

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $promotion = $this->model->get_promotion($id);

            if (!$promotion) die();

            $delete = $this->model->delete_promotion($id);

            if (!$delete) die("Can not delete promotion");

            if ($delete) {
                $adata = UserManager::LoginData("admin");
                User::addAction($adata["id"], "delete", "deleted-promotion", ['id' => $promotion["id"], 'name' => $promotion["name"]]);
            }

            echo Utility::jencode(['status' => "successful", 'message' => __("admin/financial/success4")]);

        }


        private function edit_promotion()
        {
            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) die();

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $promotion = $this->model->get_promotion($id);

            if (!$promotion) die();

            $status = (int)Filter::init("POST/status", "numbers");
            $status = $status ? "active" : "inactive";
            $type = Filter::init("POST/type", "letters");
            $rate = (float)Filter::init("POST/rate", "hclear");
            $amount = (float)Filter::init("POST/amount");
            $cid = (int)Filter::init("POST/cid", "numbers");
            $period1 = (string)Filter::init("POST/period1", "hclear");
            $period2 = (string)Filter::init("POST/period2", "hclear");
            $period_time1 = (int)Filter::init("POST/period_time1", "hclear");
            $period_time2 = (int)Filter::init("POST/period_time2", "hclear");
            $product = (string)Filter::init("POST/product", "hclear");

            if (!$period_time1) $period_time1 = 1;
            if (!$period_time2) $period_time2 = 1;

            if ($type == "percentage") {
                $amount = 0;
                $cid = 0;
            } elseif ($type == "free") {
                $amount = 0;
                $cid = 0;
                $rate = 0;
            }

            $name = Filter::init("POST/name", "hclear");
            $primary_product = Filter::POST("primary_product");
            if (is_array($primary_product)) $primary_product = array_unique($primary_product);
            $duedate = Filter::init("POST/duedate", "numbers");
            $maxuses = (int)Filter::init("POST/maxuses", "numbers");
            $applyonce = (int)Filter::init("POST/applyonce", "numbers");
            $notes = Filter::init("POST/notes", "dtext");

            if (!$name) $name = ___("needs/untitled");

            if (!$primary_product)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='primary_product[]']",
                    'message' => __("admin/financial/error10"),
                ]));

            if (!$product)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='product']",
                    'message' => __("admin/financial/error11"),
                ]));

            if (!$period2)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='period2']",
                    'message' => __("admin/financial/error12"),
                ]));

            if ($period1) {
                if (stristr(implode("\n", $primary_product), "product/domain") && (($period1 == "month" && !$period_time1 != 12) || $period1 != "year"))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "select[name='period1']",
                        'message' => __("admin/financial/error13"),
                    ]));
            }

            if (stristr($product, "product/domain") && (($period2 == "month" && !$period_time2 != 12) || $period2 != "year"))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='period2']",
                    'message' => __("admin/financial/error13"),
                ]));

            if (in_array($product, $primary_product))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='product']",
                    'message' => __("admin/financial/error14"),
                ]));


            if ($type == "percentage" && $rate == 0)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='rate']",
                    'message' => __("admin/financial/error4"),
                ]));

            if ($type == "amount" && $amount == 0)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='amount']",
                    'message' => __("admin/financial/error5"),
                ]));


            $this->model->set_promotion($id, [
                'status'          => $status,
                'name'            => $name,
                'primary_product' => $primary_product ? implode(",", $primary_product) : '',
                'product'         => $product,
                'period1'         => $period1,
                'period2'         => $period2,
                'period_time1'    => $period_time1,
                'period_time2'    => $period_time2,
                'type'            => $type,
                'rate'            => $rate,
                'amount'          => $amount,
                'currency'        => $cid,
                'maxuses'         => $maxuses,
                'applyonce'       => $applyonce,
                'duedate'         => $duedate ? $duedate : DateManager::ata(),
                'notes'           => $notes,
            ]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-promotion", [
                'name' => $promotion["name"],
                'id'   => $id,
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/financial/success5"),
                'redirect' => $this->AdminCRLink("financial", ["promotions"]),
            ]);
        }


        private function define_all_tax_rates()
        {

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $config = Config::get("options");
            $rates = [
                14  => 20,
                21  => 21,
                34  => 20,
                54  => 25,
                57  => 19,
                58  => 21,
                60  => 25,
                69  => 20,
                74  => 24,
                75  => 20,
                82  => 19,
                85  => 24,
                100 => 27,
                106 => 23,
                109 => 22,
                122 => 21,
                128 => 21,
                129 => 17,
                137 => 18,
                156 => 21,
                176 => 23,
                177 => 23,
                180 => 19,
                201 => 20,
                202 => 22,
                208 => 21,
                214 => 25,
                227 => 18,
                234 => 20,
            ];

            foreach ($rates as $k => $v) $config["country-tax-rates"][$k] = $v;

            $code = Utility::array_export(['options' => $config], ['pwith' => true]);

            FileManager::file_write(CONFIG_DIR . "options.php", $code);

            Utility::redirect($this->AdminCRLink("financial", ["taxation"]) . "?tab=tax-rates");

            echo Utility::jencode(['status' => "successful"]);
        }


        private function currencies_settings()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $module = Filter::init("POST/module", "route");
            $autocre = Filter::init("POST/auto_currencies_rate", "numbers");
            $autocre = $autocre == 1 ? true : false;
            $update_time = (int)Filter::init("POST/update_time", "numbers");
            $update_period = Filter::init("POST/update_period", "letters");
            $ipaid_subscription = (bool)Filter::init("POST/ipaid-subscription", "numbers");

            $smodule = Config::get("modules/currency");
            $module_data = Filter::POST("module_data");

            $gmodule = Modules::Load("Currency", $module, true);

            if (!$gmodule) return false;

            $config = $gmodule["config"];

            if (!$ipaid_subscription) {

                if ($module == "currencylayer" && Config::get("general/currency") != 4)
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='update_time']",
                        'message' => __("admin/financial/error7"),
                    ]));

                if (isset($config["free-use"]["period"])) {
                    $time1 = DateManager::special_time([$update_period => $update_time]);
                    $time2 = DateManager::special_time([$config["free-use"]["period"] => $config["free-use"]["time"]]);
                    $time2_str = DateManager::str_expression([$config["free-use"]["period"] => $config["free-use"]["time"]]);

                    if ($time1 < $time2)
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "input[name='update_time']",
                            'message' => __("admin/financial/error8", ['{time}' => $time2_str]),
                        ]));
                }
            }

            if ($module != $smodule) {
                $modules_result = Config::set("modules", ['currency' => $module]);
                $var_export = Utility::array_export($modules_result, ['pwith' => true]);
                FileManager::file_write(CONFIG_DIR . "modules.php", $var_export);
            }

            $set_cron = [
                'tasks' => [
                    'auto-currency-rates' => [
                        'status' => $autocre,
                        'time'   => $update_time,
                        'period' => $update_period,
                    ],
                ],
            ];


            $mdata = isset($module_data[$module]) ? $module_data[$module] : [];
            if (isset($gmodule["config"]["ipaid-subscription"]))
                $mdata["ipaid-subscription"] = $ipaid_subscription;

            $config = array_replace_recursive($config, $mdata);
            $export = Utility::array_export($config, ['pwith' => true]);
            FileManager::file_write(MODULE_DIR . "Currency" . DS . $module . DS . "config.php", $export);


            $crons = Config::get("cronjobs");
            $replace_cron = Config::set("cronjobs", $set_cron);
            $export_crons = Utility::array_export($replace_cron, ['pwith' => true]);
            FileManager::file_write(CONFIG_DIR . "cronjobs.php", $export_crons);


            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-currency-settings");

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/financial/success1"),
            ]);

        }


        private function operationMain($operation)
        {

            if ($operation == "taxation_advanced" && Admin::isPrivilege(['FINANCIAL_TAXATION']))
                return $this->taxation_advanced();

            if ($operation == "taxation_settings" && Admin::isPrivilege(['FINANCIAL_TAXATION']))
                return $this->taxation_settings();
            if ($operation == "update_tax_rates" && Admin::isPrivilege(['FINANCIAL_TAXATION']))
                return $this->update_tax_rates();
            if ($operation == "edit_tax_rule" && Admin::isPrivilege(['FINANCIAL_TAXATION']))
                return $this->edit_tax_rule();
            if ($operation == "add_new_tax_rule" && Admin::isPrivilege(['FINANCIAL_TAXATION']))
                return $this->add_new_tax_rule();
            if ($operation == "delete_tax_rule" && Admin::isPrivilege(['FINANCIAL_TAXATION']))
                return $this->delete_tax_rule();
            if ($operation == "currencies_settings" && Admin::isPrivilege(['FINANCIAL_CURRENCIES']))
                return $this->currencies_settings();
            if ($operation == "set_currencies_rate" && Admin::isPrivilege(['FINANCIAL_CURRENCIES']))
                return $this->set_currencies_rate();
            if ($operation == "change_currecy_status" && Admin::isPrivilege(['FINANCIAL_CURRENCIES']))
                return $this->change_currecy_status();
            if ($operation == "get_currency" && Admin::isPrivilege(['FINANCIAL_CURRENCIES']))
                return $this->get_currency();
            if ($operation == "change_currency" && Admin::isPrivilege(['FINANCIAL_CURRENCIES']))
                return $this->change_currency();
            if ($operation == "add_new_coupon" && Admin::isPrivilege(['FINANCIAL_COUPONS']))
                return $this->add_new_coupon();
            if ($operation == "delete_coupon" && Admin::isPrivilege(['FINANCIAL_COUPONS']))
                return $this->delete_coupon();
            if ($operation == "edit_coupon" && Admin::isPrivilege(['FINANCIAL_COUPONS']))
                return $this->edit_coupon();

            if ($operation == "add_new_promotion" && Admin::isPrivilege(['FINANCIAL_COUPONS']))
                return $this->add_new_promotion();
            if ($operation == "delete_promotion" && Admin::isPrivilege(['FINANCIAL_COUPONS']))
                return $this->delete_promotion();
            if ($operation == "edit_promotion" && Admin::isPrivilege(['FINANCIAL_COUPONS']))
                return $this->edit_promotion();

            if ($operation == "define_all_tax_rates" && Admin::isPrivilege(['FINANCIAL_TAXATION']))
                return $this->define_all_tax_rates();

            echo "Operation not found : " . $operation;
        }


        public function pageMain($name = '')
        {
            if ($name == "taxation" && Admin::isPrivilege(['FINANCIAL_TAXATION'])) return $this->taxation_main();
            if ($name == "currencies" && Admin::isPrivilege(['FINANCIAL_CURRENCIES'])) return $this->currencies_main();
            if ($name == "promotions" && Admin::isPrivilege(['FINANCIAL_PROMOTIONS'])) return $this->promotions_main();
            if ($name == "coupons" && Admin::isPrivilege(['FINANCIAL_COUPONS'])) return $this->coupons_main();
            echo "Not found main: " . $name;
        }


        private function promotions_main()
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

            $page = Filter::GET("page");

            if ($page == "edit") {

                $id = (int)Filter::init("GET/id", "numbers");

                if (!$id) die();

                $promotion = $this->model->get_promotion($id);
                if (!$promotion) die();

                $this->addData("links", [
                    'controller' => $this->AdminCRLink("financial", ['promotions']),
                ]);

                $this->addData("meta", __("admin/financial/meta-edit-promotion"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("financial", ['promotions']),
                        'title' => __("admin/financial/breadcrumb-promotions-name"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/financial/breadcrumb-edit-promotions-name"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("settings", [

                ]);

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
                    'get_tlds'               => function () {
                        return $this->model->get_tlds();
                    },
                ]);

                $this->addData("promotion", $promotion);

                $this->view->chose("admin")->render("edit-promotion", $this->data);
                die();
            }

            if ($page == "add") {

                $this->addData("links", [
                    'controller' => $this->AdminCRLink("financial", ['promotions']),
                ]);

                $this->addData("meta", __("admin/financial/meta-add-promotion"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("financial", ['promotions']),
                        'title' => __("admin/financial/breadcrumb-promotions-name"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/financial/breadcrumb-add-promotions-name"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("settings", [

                ]);

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
                    'get_tlds'               => function () {
                        return $this->model->get_tlds();
                    },
                ]);

                $this->view->chose("admin")->render("add-promotion", $this->data);

                die();

            }

            $this->addData("links", [
                'controller' => $this->AdminCRLink("financial", ['promotions']),
            ]);

            $this->addData("meta", __("admin/financial/meta-promotions"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/financial/breadcrumb-promotions-name"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            Helper::Load(["Money", "Products"]);

            $this->addData("settings", [

            ]);

            $this->addData("functions", []);

            $this->addData("list", $this->model->get_promotion_list());

            $this->view->chose("admin")->render("promotions", $this->data);
        }


        private function coupons_main()
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

            $page = Filter::GET("page");

            if ($page == "edit") {

                $id = (int)Filter::init("GET/id", "numbers");

                if (!$id) die();

                $coupon = $this->model->get_coupon($id);
                if (!$coupon) die();

                $this->addData("links", [
                    'controller' => $this->AdminCRLink("financial", ['coupons']),
                ]);

                $this->addData("meta", __("admin/financial/meta-edit-coupon"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("financial", ['coupons']),
                        'title' => __("admin/financial/breadcrumb-coupons-name"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/financial/breadcrumb-edit-coupons-name"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("settings", [

                ]);

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
                    'get_tlds'               => function () {
                        return $this->model->get_tlds();
                    },
                ]);

                $this->addData("coupon", $coupon);

                $this->view->chose("admin")->render("edit-coupon", $this->data);
                die();
            }

            if ($page == "add") {

                $this->addData("links", [
                    'controller' => $this->AdminCRLink("financial", ['coupons']),
                ]);

                $this->addData("meta", __("admin/financial/meta-add-coupon"));

                $breadcrumbs = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("financial", ['coupons']),
                        'title' => __("admin/financial/breadcrumb-coupons-name"),
                    ],
                    [
                        'link'  => null,
                        'title' => __("admin/financial/breadcrumb-add-coupons-name"),
                    ],
                ];

                $this->addData("breadcrumb", $breadcrumbs);

                $this->addData("settings", [

                ]);

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
                    'get_tlds'               => function () {
                        return $this->model->get_tlds();
                    },
                ]);

                $this->view->chose("admin")->render("add-coupon", $this->data);

                die();

            }

            $this->addData("links", [
                'controller' => $this->AdminCRLink("financial", ['coupons']),
            ]);

            $this->addData("meta", __("admin/financial/meta-coupons"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/financial/breadcrumb-coupons-name"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("settings", [

            ]);

            $this->addData("functions", []);

            $this->addData("list", $this->model->get_coupon_list());

            Helper::Load("Money");

            $this->view->chose("admin")->render("coupons", $this->data);

        }


        private function currencies_main()
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

            $this->addData("links", [
                'controller' => $this->AdminCRLink("financial", ['currencies']),
            ]);

            $this->addData("meta", __("admin/financial/meta-currencies"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/financial/breadcrumb-currencies-name"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            Helper::Load("Money");

            $this->addData("settings", [
                'module' => Config::get("modules/currency"),
                'cron'   => Config::get("cronjobs/tasks/auto-currency-rates"),
            ]);

            $this->addData("modules", Modules::Load('Currency', false));

            $this->addData("list", $this->get_currencies_list());

            $modules = Modules::Load("Payment", "All", true);

            $this->addData("paymentModules", $modules);


            $this->view->chose("admin")->render("currencies", $this->data);
        }


        private function get_currencies_list()
        {
            $data = $this->model->get_currencies();
            if ($data) {

            }
            return $data;
        }


        private function taxation_main()
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

            $this->addData("links", [
                'controller' => $this->AdminCRLink("financial", ['taxation']),
            ]);

            $this->addData("meta", __("admin/financial/meta-taxation"));

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/financial/breadcrumb-taxation-name"),
                ],
            ];

            $this->addData("breadcrumb", $breadcrumbs);

            Helper::Load("Money");

            $taxation_type = Config::get("options/taxation-type");
            if (!$taxation_type)
                $taxation_type = "exclusive";

            $this->addData("settings", [
                'status'        => Config::get("options/taxation"),
                'rate'          => Config::get("options/tax-rate"),
                'sebilltad'     => Config::get("options/send-bill-to-address"),
                'taxation-type' => $taxation_type,
            ]);

            $get_primary_cities = function ($country_id = 0) {
                $data = [];
                $primary_cities = Config::get("options/city-tax-rates/" . $country_id);
                #$get_cities         = AddressManager::getCities($country_id);
                if ($primary_cities) {
                    foreach ($primary_cities as $k => $v) {
                        $row["tax_rate"] = '';
                        $row["id"] = $k;
                        $row["name"] = AddressManager::getCityName($k);
                        $data[$row["id"]] = $row;
                    }
                }
                return $data;
            };
            $this->addData("get_primary_cities", $get_primary_cities);

            $get_countries = AddressManager::getCountries("t1.id,t1.a2_iso,t2.name");
            $countries = [];
            foreach ($get_countries as $row) {
                $row["tax_rate"] = '';
                $countries[$row["id"]] = $row;
            }
            $primary_countries = Config::get("options/country-tax-rates");
            if ($primary_countries && is_array($primary_countries)) {
                foreach ($primary_countries as $k => $v) {
                    if (gettype($v) == "string" && $v == '') {
                        unset($primary_countries[$k]);
                        continue;
                    }
                    $countries[$k]["tax_rate"] = $v;
                    $primary_countries[$k] = $countries[$k];
                    unset($countries[$k]);
                }
                if ($primary_countries) $countries = array_merge($primary_countries, $countries);
            }
            $this->addData("countries", $countries);

            $db_name = Config::get("database/name");

            $last_id = $this->model->db->query('SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = "' . $db_name . '" AND TABLE_NAME = "invoices"');
            $last_id = $this->model->db->getAssoc($last_id);
            $invoices_auto_increment = $last_id["AUTO_INCREMENT"];
            $invoices_auto_increment_p = $invoices_auto_increment;

            $fake_invoice_auto_increment = Config::getd("invoice-id");
            if (strlen($fake_invoice_auto_increment) > 0) $invoices_auto_increment = $fake_invoice_auto_increment;

            $fake_invoice_auto_increment = Config::getd("invoice-id-paid");
            if (strlen($fake_invoice_auto_increment) > 0) $invoices_auto_increment_p = $fake_invoice_auto_increment;


            $this->addData("invoices_auto_increment", $invoices_auto_increment);
            $this->addData("paid_invoices_auto_increment", $invoices_auto_increment_p);

            $this->view->chose("admin")->render("taxation", $this->data);
        }


        public function main()
        {

            if (Filter::POST("operation")) return $this->operationMain(Filter::init("POST/operation", "route"));
            if (Filter::GET("operation")) return $this->operationMain(Filter::init("GET/operation", "route"));

            if (isset($this->params[0]) && $this->params[0] != '') return $this->pageMain($this->params[0]);
        }
    }