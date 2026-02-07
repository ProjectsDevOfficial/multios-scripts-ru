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
        protected $params, $data = [];


        public function __construct($arg = [])
        {
            parent::__construct();
            $this->params = $arg['params'];

            if (!Config::get("options/basket-system")) {
                $this->main_404();
                die();
            }
        }


        public function checking_user($udata = null, $u_validate = true)
        {
            if ($udata == null) $udata = UserManager::LoginData("member");

            if ($udata && !Config::get("options/easy-order")) {
                $redirect_link = User::full_access_control_account($udata);
                if ($u_validate && $redirect_link) {
                    Utility::redirect($redirect_link);
                    return false;
                }
            } elseif (!$udata) {
                Utility::redirect($this->CRLink("sign-in"));
                return false;
            }
            return true;
        }


        private function header_background()
        {
            $cache = self::$cache;
            $cache->setCache("basket");
            $cname = "header_background";
            $cache->eraseExpired();
            if (!$cache->isCached($cname) || !Config::get("general/cache")) {
                $data = $this->model->header_background();
                if ($data) $data = Utility::image_link_determiner($data, Config::get("pictures/header-background/folder"));
                if (Config::get("general/cache")) $cache->store($cname, $data);
            } else
                $data = $cache->retrieve($cname);
            return $data;
        }


        private function main_bring($bring)
        {
            if ($bring == "item-list") return $this->item_list();
            if ($bring == "delete-item") return $this->delete_item();
            if ($bring == "set-wprivacy") return $this->set_wprivacy();
            if ($bring == "change-selection-period") return $this->change_selection_period();
            if ($bring == "change-selection-year") return $this->change_selection_year();
            if ($bring == "change-domain-ns") return $this->change_domain_ns();
            if ($bring == "change-domain-whois") return $this->change_domain_whois();
            if ($bring == "order-summary") return $this->order_summary();
            if ($bring == "coupon-check") return $this->coupon_check();
            if ($bring == "delete-coupon") return $this->delete_coupon();
            else echo "Not Found Bring: " . $bring;
        }


        private function item_list()
        {

            $this->takeDatas("language");
            $lang = Bootstrap::$lang->clang;

            Helper::Load(["Money", "Basket", "Products", "User", "Orders", "Invoices"]);
            $result = ['status' => "none"];

            $udata = UserManager::LoginData("member");

            if (!Config::get("options/visitors-will-see-basket") && !$this->checking_user($udata)) return false;

            $infos = isset($udata["id"]) ? User::getInfo($udata["id"], "default_address,dealership,taxation,gsm_cc,gsm,landline_cc,landline_phone,identity,kind,company_name,company_tax_number,company_tax_office,contract1,contract2") : [];
            $datas = isset($udata["id"]) ? User::getData($udata["id"], "country,lang,email,balance,balance_currency,currency,name,surname,full_name,company_name", "array") : [];
            $udata = is_array($udata) ? array_merge($udata, $infos, $datas) : [];
            $u_taxation = isset($udata["taxation"]) ? $udata["taxation"] : null;
            $taxation_type = Invoices::getTaxationType();
            $tax_rate = 0;
            $legal = 0;
            $country = 0;
            $city = 0;
            $defineAddress = false;

            if (UserManager::LoginCheck("member")) {
                $defAd = (int)$udata["default_address"];
                if ($defAd && AddressManager::CheckAddress($defAd, $udata["id"])) {
                    $defineAddress = true;
                    $getAddress = AddressManager::getAddress($defAd);
                    if ($getAddress) {
                        if ($getAddress["id"] != $udata["default_address"]) {
                            $this->model->set_address($udata["default_address"], ['detouse' => 0]);
                            $this->model->set_address($getAddress["id"], ['detouse' => 1]);
                            User::setInfo($udata["id"], [
                                'default_address' => $getAddress["id"],
                            ]);
                            $udata["default_address"] = $getAddress["id"];
                            User::setData($udata["id"], [
                                'country' => $getAddress["country_id"],
                            ]);
                            $udata["country"] = $getAddress["country_id"];
                        }
                        $country = $getAddress["country_id"];
                        $city = isset($getAddress["city_id"]) ? $getAddress["city_id"] : $getAddress["city"];
                    }
                }
            }
            if (!$defineAddress) {
                $ipInfo = UserManager::ip_info();
                $info_country = $ipInfo["countryCode"];
                $info_country = AddressManager::get_id_with_cc($info_country);
                $country = $info_country;
                $city = $ipInfo["city"];
            }

            $taxation = Invoices::getTaxation($country, $u_taxation);
            $isLocal = Invoices::isLocal($country, isset($udata["id"]) ? $udata["id"] : 0);
            if ($isLocal) $tax_rate = Invoices::getTaxRate($country, $city, isset($udata["id"]) ? $udata["id"] : 0);
            if ($taxation) $legal = 1;


            //if(!$legal) $tax_rate = 0;


            $count = Basket::count();

            if ($count) {

                $result["status"] = "listing";
                $result["data"] = [];
                $result["count"] = $count;
                $list = Basket::listing();
                $promotions = [];

                foreach ($list as $k => $item) {
                    $item['options'] = Utility::jdecode($item['options'], true);

                    if ($h_changes = Hook::run("CartListItemOverwrite", $item))
                        foreach ($h_changes as $h_change)
                            if ($h_change) $list[$k] = $h_change;
                }

                ## PROMOTION DETECTED START ##
                foreach ($list as $item) {
                    $options = isset($item["options"]) ? $item["options"] : [];
                    if (isset($options["type"]) && isset($options["id"])) {
                        $product = Products::get($options["type"], $options["id"], $lang);
                        if (isset($product["price"])) {

                            if ($product["type"] != "domain") {
                                $selection = isset($options["selection"]) ? $options["selection"] : false;
                                $find = false;
                                foreach ($product["price"] as $row) {
                                    if ($row["id"] == $selection["id"]) {
                                        $find = true;
                                        $selection = $row;
                                    }
                                }
                                if (!$find) continue;

                                $options["period"] = $selection["period"];
                                $options["period_time"] = $selection["time"];
                            }

                            $get_promotions = Products::get_promotions_for_product($options["type"], $options["id"], $options["period"], $options["period_time"]);

                            if ($get_promotions) {
                                foreach ($get_promotions as $pro_k => $promotion) {
                                    if (isset($udata["id"]) && $udata["id"] && $promotion["applyonce"] && $this->model->check_applyonce_promotion($promotion["id"], $udata["id"])) continue;

                                    if (isset($promotions[$pro_k]))
                                        $promotion["use_count"] = $promotions[$pro_k]["use_count"] + 1;
                                    else
                                        $promotion["use_count"] = 1;
                                    $promotions[$pro_k] = $promotion;
                                }
                            }
                        }
                    }
                }
                ## PROMOTION DETECTED END ##


                $key = -1;
                foreach ($list as $item) {
                    $key++;
                    $options = isset($item["options"]) ? $item["options"] : [];
                    $data = [];
                    $data["name"] = $item["name"];
                    $data["id"] = $item["id"];
                    $setup_fee = false;
                    $selection = false;

                    if (isset($options["category"])) {
                        $data["category"] = $options["category"];
                        $data["category_route"] = isset($options["category_route"]) ? $options["category_route"] : "#";
                    }


                    if (isset($options["amount"]) && isset($options["cid"])) {
                        $amount = Filter::init($options["amount"], "amount");
                        $currency = $options["cid"];
                        $amount = Money::formatter_symbol($amount, $currency, true);
                    } elseif (isset($options["type"]) && isset($options["id"])) {
                        $product = Products::get($options["type"], $options["id"], $lang);
                        if (isset($product["price"])) {
                            if ($options["type"] == "domain") {
                                $year = $options["period_time"] < 1 ? 1 : $options["period_time"];
                                $isPromo = $product["promo_status"] && (substr($product["promo_duedate"], 0, 4) == '1881' || DateManager::strtotime($product["promo_duedate"] . " 23:59:59") > DateManager::strtotime());
                                if (isset($options["renewal"]) && $options["renewal"]) {
                                    $price = $product["price"]["renewal"]["amount"];
                                    $curr = $product["price"]["renewal"]["cid"];
                                    $disco = $product["price"]["renewal"]["discount"];
                                } elseif (isset($options["tcode"]) && $options["tcode"]) {
                                    $price = $product["price"]["transfer"]["amount"];
                                    $curr = $product["price"]["transfer"]["cid"];
                                    $disco = $product["price"]["transfer"]["discount"];

                                    if ($isPromo && ($product["promo_transfer_price"] > 0.00 || (Config::get("options/domain-promotion-free") && $product["promo_transfer_price"] < 0.01)) && $year < 2) {
                                        $data["promotion_applied"] = true;
                                        $price = $product["promo_transfer_price"];
                                    }
                                } else {
                                    $price = $product["price"]["register"]["amount"];
                                    $curr = $product["price"]["register"]["cid"];
                                    $disco = $product["price"]["register"]["discount"];

                                    if ($isPromo && ($product["promo_register_price"] > 0.00 || (Config::get("options/domain-promotion-free") && $product["promo_register_price"] < 0.01)) && $year < 2) {
                                        $data["promotion_applied"] = true;
                                        $price = $product["promo_register_price"];
                                    }
                                }
                                $price_d = $price;
                                $price = $price * $year;

                            } else {
                                $selection = isset($options["selection"]) ? $options["selection"] : false;
                                $find = false;
                                if ($product["price"]) {
                                    foreach ($product["price"] as $row) {
                                        if ($row["id"] == $selection["id"]) {
                                            $find = true;
                                            $selection = $row;
                                        }
                                    }
                                }
                                if (!$find && $product["type"] == "sms")
                                    $selection = $product["price"][0];
                                elseif (!$find) continue;

                                $options["period"] = $selection["period"];
                                $options["period_time"] = $selection["time"];


                                $price = $selection["amount"];
                                $curr = $selection["cid"];
                                if ($selection["discount"] != 0)
                                    $disco = $selection["discount"];
                                else
                                    $disco = 0;
                            }


                            if ($taxation_type == "inclusive")
                                $price -= Money::get_inclusive_tax_amount($price, $tax_rate);

                            ## APPLY PROMOTION START ##
                            $is_promotional = Products::get_product_promotional($options["type"], $options["id"], $options["period"], $options["period_time"]);
                            if ($is_promotional) {
                                foreach ($is_promotional as $pro_k => $promo) {
                                    if (isset($promotions[$pro_k]) && $promotions[$pro_k]["use_count"]) {
                                        $price = Products::apply_promotion($promo, $price, $curr);
                                        $data["promotion_applied"] = true;
                                        $promotions[$pro_k]["use_count"] -= 1;
                                    }
                                }
                            }
                            ## APPLY PROMOTION END ##

                            if ($price <= 0)
                                $amount = 0;
                            else
                                $amount = Money::formatter_symbol($price, $curr, true);
                            if ($disco != 0 && $disco != '') $data["reduced"] = $disco;

                            if ($product["type"] != "sms" && $product["type"] !== "domain") {
                                if (isset($options["event"]) && preg_match("/Order$/i", $options["event"])) {
                                    if (sizeof($product["price"]) > 1) {
                                        $data["selected_period"] = isset($selection["id"]) ? $selection["id"] : '';
                                        foreach ($product["price"] as $k => $v) {
                                            $period_x = Orders::detail_period([
                                                'period'      => $v["period"],
                                                'period_time' => $v["time"],
                                            ]);

                                            if ($v["id"] != $selection["id"] && $v["discount"])
                                                $period_x .= " (" . __("website/basket/reduced2", ['{rate}' => $v["discount"]]) . ")";

                                            $data["selection_period"][$k] = [
                                                'id'     => $v["id"],
                                                'period' => $period_x,
                                                'amount' => Money::formatter_symbol($v["amount"], $v["cid"], true),
                                            ];
                                        }
                                    }
                                }
                            } elseif ($product["type"] == "domain") {
                                if (isset($options["event"]) && in_array($options["event"], ['DomainNameRegisterOrder', 'DomainNameTransferRegisterOrder', 'RenewalDomain'])) {
                                    $data["year"] = $year;
                                    $min_years = $product["min_years"];
                                    $max_years = $product["max_years"];

                                    if ($options["event"] == "DomainNameTransferRegisterOrder") $max_years = 1;

                                    for ($x = $min_years; $x <= $max_years; $x++) {
                                        $period_x = Orders::detail_period([
                                            'period'      => "year",
                                            'period_time' => $x,
                                        ]);
                                        $data["selection_year"][] = [
                                            'year'   => $x,
                                            'period' => $period_x,
                                            'amount' => Money::formatter_symbol(($price_d * $x), $curr, true),
                                        ];
                                    }
                                }
                            }


                        } else $amount = 0;
                    } else $amount = 0;

                    if (isset($options["period"])) {
                        $get_period = View::period($options["period_time"], $options["period"], false, true);

                        $data["period_type"] = $options["period"];
                        $data["period_name"] = $get_period["name"];
                        if (isset($options["period_time"])) {
                            $data["period_time"] = $get_period["duration"];
                            if ($options["period_time"] > 1) {
                                $data["period_name_x"] = $data["period_name"];
                                $data["period_name"] = $data["period_time"] . " " . $data["period_name"];
                            }
                        }
                    }


                    if (!(isset($options["event"]) && $options["event"] == "ExtendOrderPeriod") && isset($selection) && $selection) $setup_fee = $selection["setup"];


                    $adds = [];

                    if (isset($options["addons"]) && $options["addons"]) {
                        foreach ($options["addons"] as $ad => $selected) {
                            $addon = Products::addon($ad);
                            if ($addon) {
                                $adopts = $addon["options"];
                                foreach ($adopts as $opt) {
                                    if ($selected == $opt["id"]) {
                                        $name = $opt["name"];

                                        $adamount = $opt["amount"];
                                        if ($addon["type"] == "quantity") {
                                            $addon_val = 0;
                                            if (isset($options["addons_values"][$addon["id"]]))
                                                $addon_val = $options["addons_values"][$addon["id"]];
                                            if ($addon_val < 1) continue;
                                            $adamount = ($adamount * $addon_val);
                                            $name = $addon_val . "x " . $name;
                                        }
                                        if ($taxation_type == "inclusive")
                                            $adamount -= Money::get_inclusive_tax_amount($adamount, $tax_rate);

                                        $adamount = Money::formatter_symbol($adamount, $opt["cid"], true);
                                        if (!$opt["amount"]) $adamount = ___("needs/free-amount");
                                        $periodic = View::period($opt["period_time"], $opt["period"]);
                                        $show_name = $addon["name"] . " - " . $name;
                                        $adds[] = [
                                            'name'        => $show_name,
                                            'period'      => ($opt["amount"] && $opt["period"] == "none") || $opt["amount"] ? $periodic : '',
                                            'period_time' => $opt["period_time"],
                                            'amount'      => $adamount,
                                        ];
                                    }
                                }
                            }
                        }
                    }

                    if ($setup_fee && $setup_fee > 0.00 && isset($selection) && $selection) {
                        $adamount = $setup_fee;
                        if ($taxation_type == "inclusive")
                            $adamount -= Money::get_inclusive_tax_amount($adamount, $tax_rate);

                        $adamount = Money::formatter_symbol($adamount, $selection["cid"], true);

                        $adds[] = [
                            'name'        => __("website/osteps/setup-fee"),
                            'period'      => ___("date/period-none"),
                            'period_time' => 0,
                            'amount'      => $adamount,
                        ];
                    }


                    if ($adds) $data["adds"] = $adds;


                    if (isset($options["domain"])) $data["domain"] = $options["domain"];
                    if (isset($options["ip"])) $data["ip"] = $options["ip"];

                    if (isset($options["event"]) && ($options["event"] == "DomainNameRegisterOrder" || $options["event"] == "DomainNameTransferRegisterOrder") && $product["whois_privacy"]) {

                        if ($product["module"] != "none") {
                            $rgstrModule = Modules::Load("Registrars", $product["module"], true);
                            $whidden_amount = $rgstrModule["config"]["settings"]["whidden-amount"];
                            $whidden_cid = $rgstrModule["config"]["settings"]["whidden-currency"];
                        } else {
                            $whidden_amount = Config::get("options/domain-whois-privacy/amount");
                            $whidden_cid = Config::get("options/domain-whois-privacy/cid");
                        }
                        $whidden_price = __("website/basket/free-paid");
                        if ($whidden_amount) {
                            if ($taxation_type == "inclusive")
                                $whidden_amount -= Money::get_inclusive_tax_amount($whidden_amount, $tax_rate);
                            $whidden_price = Money::formatter_symbol($whidden_amount, $whidden_cid, true);
                        }
                        $data["visible_wprivacy"] = true;
                        $data["wprivacy_price"] = $whidden_price;
                        if (isset($options["wprivacy"])) $data["wprivacy"] = true;
                    }
                    $data["amount"] = is_numeric($amount) && $amount == 0 ? __("website/basket/free-paid") : $amount;

                    $data["product_type"] = $options["type"];
                    $data["event_name"] = is_string($options["event"]) ? $options["event"] : '';

                    if ($options["type"] == "domain") {
                        $data["ns_details"] = $options["dns"];

                        if (!isset($options["whois"])) {
                            $profiles = User::whois_profiles($udata["id"]);
                            $profile = false;
                            if ($profiles) foreach ($profiles as $pf) if (!$profile) $profile = $profiles[0];


                            if (isset($getAddress) && $getAddress) {
                                $zipcode = AddressManager::generate_postal_code($getAddress["country_code"]);
                                $address = $getAddress["address"];
                                $state_x = $getAddress["counti"];
                                $city_y = $getAddress["city"];
                                $country_code = $getAddress["country_code"];

                                Filter::$transliterate_cc = $country_code;


                                if ($country_code == "TR") {
                                    $state = $state_x;
                                    $city = $city_y;
                                } else {
                                    $state = $city_y;
                                    $city = $state_x;
                                }

                            } else {
                                $city = '';
                                $state = '';
                                $address = '';
                                $country_code = '';
                                $zipcode = '';
                            }

                            $options["whois"] = [
                                'Name'             => $udata["full_name"] ?? '',
                                'EMail'            => $udata["email"] ?? '',
                                'Company'          => $udata["company_name"] ?? '',
                                'PhoneCountryCode' => $udata["gsm_cc"] ?? '',
                                'Phone'            => $udata["gsm"] ?? '',
                                'FaxCountryCode'   => '',
                                'Fax'              => '',
                                'City'             => $city,
                                'State'            => $state,
                                'Address'          => $address,
                                'Country'          => $country_code,
                                'ZipCode'          => $zipcode,
                            ];

                        }

                        if (!isset($options["whois"]["registrant"])) {
                            foreach (['registrant', 'administrative', 'technical', 'billing'] as $t)
                                $new_whois[$t] = $options["whois"];
                            $options["whois"] = $new_whois;
                        }


                        $data["whois_details"] = $options["whois"];
                    }

                    $result["data"][$key] = $data;
                }
            }

            echo Utility::jencode($result);
        }


        private function order_summary($reload = false)
        {
            if (!$reload) $this->takeDatas("language");
            if (!$reload) Helper::Load(["Money", "Basket", "User", "Products", "Orders", "Invoices", "Coupon"]);
            $result = ['status' => "successful"];
            $count = Basket::count();
            $lang = Bootstrap::$lang->clang;
            $address = (int)Filter::init("POST/address", "numbers");
            $sendbta = (int)Filter::init("POST/sendbta", "numbers");
            $pmethod = Filter::init("POST/pmethod", "route");
            $pay = Filter::init("POST/pay", "numbers");
            $contract1 = Filter::init("POST/contract1", "letters");
            $contract2 = Filter::init("POST/contract2", "letters");
            $payment = Filter::init("POST/payment", "numbers");


            $subscribable = [];

            $udata = UserManager::LoginData("member");

            if ($pay && !$this->checking_user($udata)) return false;

            $dealership = (array)Config::get("options/dealership");
            $d_status = $dealership["status"];
            $d_rates = $dealership["rates"];
            $dealership["status"] = false;


            $infos = isset($udata["id"]) ? User::getInfo($udata["id"], "default_address,dealership,taxation,gsm_cc,gsm,landline_cc,landline_phone,identity,kind,company_name,company_tax_number,company_tax_office,contract1,contract2,block-payment-gateways,block-proxy-usage") : [];
            $datas = isset($udata["id"]) ? User::getData($udata["id"], "country,lang,email,balance,balance_currency,currency,name,surname,full_name,blacklist,phone", "array") : [];
            $udata = is_array($udata) ? array_merge($udata, $infos, $datas) : [];
            if ($udata) $udata["ip"] = UserManager::GetIP();
            if (!$udata) {
                $payment = 0;
                $pmethod = '';
            }
            $u_taxation = isset($udata["taxation"]) ? $udata["taxation"] : null;
            $taxation_type = Invoices::getTaxationType();
            $tax_rate = 0;
            $legal = 0;
            $country = 0;
            $city = 0;
            $defineAddress = false;

            if (UserManager::LoginCheck("member")) {
                $u_address = AddressManager::getAddress(0, $udata["id"]);
                if ($u_address) $udata["default_address"] = $u_address["id"];
                if ($address) $defAd = $address;
                else $defAd = (int)$udata["default_address"];
                if ($defAd && AddressManager::CheckAddress($defAd, $udata["id"])) {
                    $defineAddress = true;
                    $getAddress = AddressManager::getAddress($defAd);
                    if ($getAddress) {
                        if ($getAddress["id"] != $udata["default_address"]) {
                            $this->model->set_address($udata["default_address"], ['detouse' => 0]);
                            $this->model->set_address($getAddress["id"], ['detouse' => 1]);
                            User::setInfo($udata["id"], [
                                'default_address' => $getAddress["id"],
                            ]);
                            $udata["default_address"] = $getAddress["id"];
                            User::setData($udata["id"], [
                                'country' => $getAddress["country_id"],
                            ]);
                            $udata["country"] = $getAddress["country_id"];
                        }
                        $country = $getAddress["country_id"];
                        $city = isset($getAddress["city_id"]) ? $getAddress["city_id"] : $getAddress["city"];
                    }
                }
            }
            if (!$defineAddress) {
                $ipInfo = UserManager::ip_info();
                $info_country = $ipInfo["countryCode"];
                $info_country = AddressManager::get_id_with_cc($info_country);
                $country = $info_country;
                $city = $ipInfo["city"];
            }

            $taxation = Invoices::getTaxation($country, $u_taxation);
            $isLocal = Invoices::isLocal($country, isset($udata["id"]) ? $udata["id"] : 0);
            // if($isLocal)  : It is not local, but it cannot impose taxes on another country because of this condition.
            $tax_rate = Invoices::getTaxRate($country, $city, isset($udata["id"]) ? $udata["id"] : 0);
            if ($taxation) $legal = 1;
            else $legal = 0;


            $ucid = $udata && $pmethod == "Balance" ? $udata["balance_currency"] : Money::getUCID();
            $use_coupon = (bool)Config::get("options/use-coupon");
            $u_dealership = isset($udata["dealership"]) ? $udata["dealership"] : [];
            $u_dealership = $u_dealership == null ? [] : Utility::jdecode($u_dealership, true);
            if ($d_status && $u_dealership && $u_dealership) {
                $u_dealership["status"] = $u_dealership["status"] == "active";
                if (isset($u_dealership["discounts"]) && $u_dealership["discounts"])
                    if (is_array(current($u_dealership["discounts"])))
                        $d_rates = array_replace_recursive($d_rates, $u_dealership["discounts"]);

                $dealership = array_replace_recursive($dealership, $u_dealership);
            }


            if (!$udata || ($dealership && !$dealership["status"])) {
                $dealership = [];
                $d_rates = [];
            }

            ## Auto Define Reseller Status START ##
            if (Config::get("options/dealership/status") && Config::get("options/dealership/activation") == 'auto' && $udata && !$d_rates) {
                $_d_rates = (array)Config::get("options/dealership/rates");
                if ($_d_rates) {
                    foreach ($_d_rates as $k => $vrs) {
                        $min_from = [];
                        foreach ($vrs as $v) $min_from[] = $v["from"];
                        $min_from = min($min_from);
                        $quantity = sizeof(User::dealership_orders($udata["id"], [$k => $vrs]));

                        if ($quantity >= $min_from) {
                            $u_dealership = isset($u_dealership) && $u_dealership ? $u_dealership : [];
                            $u_dealership["status"] = "active";
                            User::setInfo($udata["id"], ['dealership' => Utility::jencode($u_dealership)]);
                            Helper::Load("Notification");
                            Notification::dealership_has_been_activated($udata["id"]);
                            return $this->order_summary(true);
                        }
                    }
                }
            }
            ## Auto Define Reseller Status END ##

            $onlyCreditPaid = ($dealership["status"] ?? "") == "active" && isset($dealership["only_credit_paid"]) ? $dealership["only_credit_paid"] : false;
            $dcoupons = Session::get("coupons") ? explode(",", Session::get("coupons", true)) : [];
            $dpromotions = [];
            $coupons = [];

            /*
            if($payment && Config::get("general/country") == "tr" && isset($getAddress) && $getAddress && $pmethod != "Balance"){

                if($getAddress["country_id"] != 227 && $ucid == 147){
                    $defined = false;
                    foreach(Money::getCurrencies() AS $curr){
                        if(!$defined && $curr["code"] == "USD"){
                            $ucid = $curr["id"];
                            $defined = true;
                        }elseif(!$defined && $curr["code"] == "EUR"){
                            $ucid = $curr["id"];
                            $defined = true;
                        }
                    }
                }
                if($getAddress["country_id"] == 227 && $ucid != 147){
                    if(Money::Currency(147,true)) $ucid = 147;
                }
            }
            */

            $pmethods = Config::get("modules/payment-methods");

            if (isset($udata["block-payment-gateways"]) && $udata["block-payment-gateways"] && $pmethods) {
                if ($udata["block-payment-gateways"]) {
                    $block_gws = explode(",", $udata["block-payment-gateways"]);
                    foreach ($pmethods as $k => $row) if (in_array($row, $block_gws)) unset($pmethods[$k]);
                }
            }

            if ($payment == 1) {
                $currencyInfo = Money::Currency($ucid);
                if ($currencyInfo && $pmethods) {
                    $c_modules = $currencyInfo["modules"] ?? '';
                    $c_modules = $c_modules ? explode(",", $c_modules) : [];
                    $c_modules = array_map('trim', $c_modules);
                    if ($c_modules) foreach ($pmethods as $k => $row) if (!in_array($row, $c_modules)) unset($pmethods[$k]);
                    if (!$pmethods)
                        die(Utility::jencode(['status' => "error", 'message' => __("website/basket/error21")]));
                }
            }


            if ($payment || $pay) {
                if (in_array("Balance", $pmethods) && $onlyCreditPaid) $pmethods = ["Balance"];
            }

            if ($pmethods && isset($pmethods[0]) && !$pmethod && $payment)
                $pmethod = $pmethods[0];

            $btxn = Config::get("options/balance-taxation");
            if (!$btxn) $btxn = "y";

            if ($pmethod == "Balance" && $btxn == "y") $legal = 0;

            if ($udata && $pmethod == "Balance") $ucid = $udata["balance_currency"];


            if (!$count && $dcoupons) Session::delete("coupons");

            if ($count) {
                $total_amount = 0;
                $total_adds_amount = 0;
                $total_taxexempt = 0;

                if ($udata && $dealership && $d_status) {
                    if (isset($dealership["status"]) && $dealership["status"] && $d_rates) {
                        if ($dealership["require_min_discount_amount"] > 0.00) {
                            $rqmcdt = $dealership["require_min_discount_amount"];
                            $rqmcdt_cid = $dealership["require_min_discount_cid"];
                            $myBalance = Money::exChange($udata["balance"], $udata["balance_currency"], $rqmcdt_cid);
                            if ($myBalance < $rqmcdt) $d_rates = [];
                        }
                    }
                }


                $items = Basket::listing();

                if ($items) {
                    foreach ($items as $key => $item) {
                        if ($h_changes = Hook::run("CartSummaryItemOverwrite", $item))
                            foreach ($h_changes as $h_change) $items[$key] = $h_change;
                    }
                }

                $promotions = [];

                ## PROMOTION DETECTED START ##
                foreach ($items as $key => $item) {
                    $options = isset($item["options"]) ? $item["options"] : [];
                    if (isset($options["type"]) && isset($options["id"])) {
                        $product = Products::get($options["type"], $options["id"], $lang);
                        if (isset($product["price"])) {

                            if ($product["type"] !== "domain") {
                                $selection = isset($options["selection"]) ? $options["selection"] : false;
                                $find = false;
                                foreach ($product["price"] as $row) {
                                    if ($row["id"] == $selection["id"]) {
                                        $find = true;
                                        $selection = $row;
                                    }
                                }
                                if (!$find && $product["type"] == "sms")
                                    $selection = $product["price"][0];
                                elseif (!$find) continue;

                                $options["period"] = $selection["period"];
                                $options["period_time"] = $selection["time"];
                            }

                            $items[$key]["options"] = $options;

                            $get_promotions = Products::get_promotions_for_product($options["type"], $options["id"], $options["period"], $options["period_time"]);
                            if ($get_promotions) {
                                foreach ($get_promotions as $pro_k => $promotion) {
                                    if (isset($udata["id"]) && $udata["id"] && $promotion["applyonce"] && $this->model->check_applyonce_promotion($promotion["id"], $udata["id"])) continue;
                                    if (isset($promotions[$pro_k]))
                                        $promotion["use_count"] = $promotions[$pro_k]["use_count"] + 1;
                                    else
                                        $promotion["use_count"] = 1;
                                    $promotions[$pro_k] = $promotion;
                                }
                            }
                        }
                    }
                }
                ## PROMOTION DETECTED END ##


                $used_promotions = [];
                $coupon_products = [];
                $used_coupons = [];
                $used_coupons_groups = [];
                $temp_coupons = [];
                $dealership_discount_amount = 0;
                $dealership_discounts = [];
                $promotion_total = 0;
                $product_counts = [];

                $uid = $udata ? $udata["id"] : 0;
                $o_quantity = sizeof(User::dealership_orders($uid, $d_rates));


                if ($dcoupons) {
                    foreach ($dcoupons as $coupon) {
                        $coupon = (int)$coupon;
                        $coupon = Coupon::get(null, $coupon);
                        if ($coupon && !Coupon::validate($coupon, $uid)) $coupon = false;
                        if ($coupon) {
                            $temp_coupons[$coupon["id"]] = $coupon;
                            $coupon_products[$coupon["id"]] = Products::find_products_in_coupon($coupon["pservices"]);
                        }
                    }
                }


                foreach ($items as $key => $item) {
                    $options = isset($item["options"]) ? $item["options"] : [];
                    $taxexempt = 0;
                    $original_a = 0;
                    $original_c = $ucid;
                    $selection = false;

                    if (isset($options["type"]) && isset($options["id"])) {
                        $product = Products::get($options["type"], $options["id"], $lang);

                        if (!$product) {
                            Basket::delete($item["unique"]);
                            Basket::save();
                            unset($items[$key]);
                            continue;
                        }
                    } else
                        $product = false;

                    if (isset($options["amount"]) && isset($options["cid"])) {
                        $amount = Filter::init($options["amount"], "amount");
                        $currency = $options["cid"];
                        $original_a = $amount;
                        $original_c = $currency;
                        $amount = Money::formatter_symbol($amount, $currency);
                        $amount = (Money::deformatter($amount, $currency) * $item["quantity"]);
                        $amount = Money::exChange($amount, $currency, $ucid);
                    } elseif ($product) {
                        if (isset($product["price"])) {
                            if ($options["type"] == "domain") {
                                $year = $options["period_time"] < 1 ? 1 : $options["period_time"];
                                $isPromo = $product["promo_status"] && (substr($product["promo_duedate"], 0, 4) == '1881' || DateManager::strtotime($product["promo_duedate"] . " 23:59:59") > DateManager::strtotime());
                                if (isset($options["renewal"]) && $options["renewal"]) {
                                    $price = $product["price"]["renewal"]["amount"];
                                    $curr = $product["price"]["renewal"]["cid"];
                                } elseif (isset($options["tcode"]) && $options["tcode"]) {
                                    $price = $product["price"]["transfer"]["amount"];
                                    $curr = $product["price"]["transfer"]["cid"];

                                    if ($isPromo && ($product["promo_transfer_price"] > 0.00 || (Config::get("options/domain-promotion-free") && $product["promo_transfer_price"] < 0.01)) && $year < 2) {
                                        $price = $product["promo_transfer_price"];
                                    }
                                } else {
                                    $price = $product["price"]["register"]["amount"];
                                    $curr = $product["price"]["register"]["cid"];

                                    if ($isPromo && ($product["promo_register_price"] > 0.00 || (Config::get("options/domain-promotion-free") && $product["promo_register_price"] < 0.01)) && $year < 2) {
                                        $price = $product["promo_register_price"];
                                    }
                                }
                                $price = $price * $year;

                            } else {
                                $selection = isset($options["selection"]) ? $options["selection"] : false;
                                $find = false;
                                foreach ($product["price"] as $row) {
                                    if ($row["id"] == $selection["id"]) {
                                        $find = true;
                                        $selection = $row;
                                    }
                                }
                                if (!$find) continue;

                                $options["period"] = $selection["period"];
                                $options["period_time"] = $selection["time"];

                                $price = $selection["amount"];
                                $price = ($price * $item["quantity"]);
                                $curr = $selection["cid"];

                            }

                            if ($taxation_type == "inclusive")
                                $price -= Money::get_inclusive_tax_amount($price, $tax_rate);

                            $original_a = $price;
                            $original_c = $curr;

                            ## APPLY PROMOTION START ##
                            $is_promotional = Products::get_product_promotional($options["type"], $options["id"], $options["period"], $options["period_time"]);
                            if ($is_promotional) {
                                $_price = $price;
                                foreach ($is_promotional as $pro_k => $promo) {
                                    if (isset($promotions[$pro_k]) && $promotions[$pro_k]["use_count"]) {
                                        $old_price = $_price;
                                        $_price = Products::apply_promotion($promo, $old_price, $curr);

                                        $promotions[$pro_k]["use_count"] -= 1;

                                        $used_data = [];
                                        $used_data["id"] = $pro_k;
                                        $used_data["name"] = $promo["name"];
                                        $used_data["rate"] = $promo["rate"];
                                        $promo_amount = 0;
                                        if ($promo["type"] == "free") {
                                            $promo_amount = $old_price;
                                            $used_data["dvalue"] = ___("needs/free-amount");
                                        } elseif ($promo["type"] == "percentage") {
                                            $promo_amount = Money::get_discount_amount($old_price, $promo["rate"]);
                                            $used_data["dvalue"] = "%" . $promo["rate"];
                                        } elseif ($promo["type"] == "amount") {
                                            $promo_amount = Money::exChange($promo["amount"], $promo["currency"], $curr);
                                            $used_data["dvalue"] = Money::formatter_symbol($promo_amount, $curr, $ucid);
                                        }

                                        $used_data["amountd"] = Money::exChange($promo_amount, $curr, $ucid);
                                        $used_data["amount"] = Money::formatter_symbol($used_data["amountd"], $ucid);
                                        $used_promotions[$key] = $used_data;
                                        if (!in_array($promo["id"], $dpromotions)) $dpromotions[] = $promo["id"];

                                        $promotion_total += $used_data["amountd"];
                                        $items[$key]["amount_including_discount"] = Money::exChange($_price, $curr, $ucid);
                                    }
                                }
                            }
                            ## APPLY PROMOTION END ##
                            $amount = Money::exChange($price, $curr, $ucid);
                        } else $amount = 0;
                        if (isset($product["taxexempt"]) && $product["taxexempt"])
                            $taxexempt = 1;
                    } else $amount = 0;


                    if (isset($options["taxexempt"]) && $options["taxexempt"]) $taxexempt = 1;

                    if (isset($selection) && $selection) {
                        if (!(isset($options["event"]) && $options["event"] == "ExtendOrderPeriod") && isset($selection["setup"]) && $selection["setup"] > 0.00) {
                            $t_setup_fee = Money::exChange($selection["setup"], $selection["cid"], $ucid);

                            if ($taxation_type == "inclusive")
                                $t_setup_fee -= Money::get_inclusive_tax_amount($t_setup_fee, $tax_rate);
                            $amount += $t_setup_fee;
                        }
                    }

                    $total_amount += $amount;

                    if (isset($options["period"]) && ($options["period"] == "day" || $options["period"] == "week" || $options["period"] == "month" || $options["period"] == "year") && $original_a > 0.00)
                        $subscribable[] = [
                            'identifier'   => md5($product["type"] . "|" . $product["id"] . "|" . $options["period"] . "|" . $options["period_time"]),
                            'product_type' => $product["type"],
                            'product_id'   => $product["id"],
                            'period'       => $options["period"],
                            'period_time'  => $options["period_time"],
                            'name'         => $item["name"],
                            'amount'       => $original_a,
                            'tax_included' => $original_a + Money::get_exclusive_tax_amount($original_a, $tax_rate),
                            'tax_exempt'   => $taxexempt,
                            'tax_rate'     => $tax_rate,
                            'currency'     => $original_c,
                        ];


                    $adds_amount = 0;
                    if (isset($options["addons"]) && $options["addons"]) {
                        foreach ($options["addons"] as $ad => $selected) {
                            $addon = Products::addon($ad);
                            if ($addon) {
                                $adopts = $addon["options"];
                                foreach ($adopts as $opt) {
                                    if ($selected == $opt["id"]) {
                                        $ad_original_a = $opt["amount"];
                                        $ad_original_c = $opt["cid"];

                                        $ad_amount = Money::exChange($opt["amount"], $opt["cid"], $ucid);
                                        if ($addon["type"] == "quantity") {
                                            $addon_val = 0;
                                            if (isset($options["addons_values"][$addon["id"]]))
                                                $addon_val = $options["addons_values"][$addon["id"]];
                                            if ($addon_val < 1) continue;
                                            $ad_amount = ($ad_amount * $addon_val);
                                            $ad_original_a = ($ad_original_a * $addon_val);
                                        }
                                        $ad_taxexempt = isset($addon["taxexempt"]) && $addon["taxexempt"] ? 1 : 0;
                                        if ($taxation_type == "inclusive")
                                            $ad_amount -= Money::get_inclusive_tax_amount($ad_amount, $tax_rate);
                                        if ($taxation_type == "inclusive")
                                            $ad_original_a -= Money::get_inclusive_tax_amount($ad_original_a, $tax_rate);
                                        $adds_amount += $ad_amount;

                                        $ad_tax = Money::get_exclusive_tax_amount($ad_original_a, $tax_rate);

                                        $items[$key]["options"]["addon_items"][] = [
                                            'product_type' => "addon",
                                            'product_id'   => $ad,
                                            'option_id'    => $opt["id"],
                                            'period'       => $opt["period"],
                                            'period_time'  => $opt["period_time"],
                                            'name'         => $addon["name"],
                                            'amount'       => $ad_original_a,
                                            'tax_included' => $ad_original_a + $ad_tax,
                                            'tax_exempt'   => $ad_taxexempt,
                                            'currency'     => $ad_original_c,
                                        ];

                                        if (isset($opt["period"]) && ($opt["period"] == "day" || $opt["period"] == "week" || $opt["period"] == "month" || $opt["period"] == "year") && $ad_original_a > 0.00) {
                                            $subscribable[] = [
                                                'identifier'   => md5("addon|" . $ad . "|" . $opt["period"] . "|" . $opt["period_time"]),
                                                'item_id'      => $key,
                                                'product_type' => "addon",
                                                'product_id'   => $ad,
                                                'option_id'    => $opt["id"],
                                                'period'       => $opt["period"],
                                                'period_time'  => $opt["period_time"],
                                                'name'         => $addon["name"],
                                                'amount'       => $ad_original_a,
                                                'tax_included' => $ad_original_a + $ad_tax,
                                                'tax_rate'     => $tax_rate,
                                                'tax_exempt'   => $ad_taxexempt,
                                                'currency'     => $ad_original_c,
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if (isset($options["wprivacy"]) && $options["wprivacy"]) {
                        if ($product["module"] != "none") {
                            $rgstrModule = Modules::Load("Registrars", $product["module"], true);
                            $whidden_amount = $rgstrModule["config"]["settings"]["whidden-amount"];
                            $whidden_cid = $rgstrModule["config"]["settings"]["whidden-currency"];
                        } else {
                            $whidden_amount = Config::get("options/domain-whois-privacy/amount");
                            $whidden_cid = Config::get("options/domain-whois-privacy/cid");
                        }

                        if ($whidden_amount > 0.00) {
                            $whidden_price = Money::exChange($whidden_amount, $whidden_cid, $ucid);
                            if ($taxation_type == "inclusive")
                                $whidden_price -= Money::get_inclusive_tax_amount($whidden_price, $tax_rate);

                            $whidden_price_tax = Money::get_exclusive_tax_amount($whidden_price, $tax_rate);

                            $adds_amount += $whidden_price;
                            if ($whidden_price > 0.00) {
                                $subscribable[] = [
                                    'identifier'   => md5("addon|whois-privacy|year|1"),
                                    'item_id'      => $key,
                                    'product_type' => "addon",
                                    'product_id'   => "whois-privacy",
                                    'option_id'    => 0,
                                    'period'       => "year",
                                    'period_time'  => 1,
                                    'name'         => __("admin/orders/whois-privacy-invoice-description", ['{name}' => $item["name"]]),
                                    'amount'       => $whidden_price,
                                    'tax_included' => $whidden_price + $whidden_price_tax,
                                    'tax_rate'     => $tax_rate,
                                    'tax_exempt'   => 0,
                                    'currency'     => $ucid,
                                ];
                            }
                            $whidden_period = "year";
                            $whidden_period_time = 1;
                        } else {
                            $whidden_price = 0;
                            $whidden_price_tax = 0;
                            $whidden_period = "none";
                            $whidden_period_time = 0;
                        }

                        $items[$key]["options"]["addon_items"][] = [
                            'product_type' => "addon",
                            'product_id'   => "whois-privacy",
                            'option_id'    => 0,
                            'period'       => $whidden_period,
                            'period_time'  => $whidden_period_time,
                            'name'         => __("admin/orders/whois-privacy-invoice-description", ['{name}' => $item["name"]]),
                            'amount'       => $whidden_price,
                            'tax_included' => $whidden_price + $whidden_price_tax,
                            'tax_exempt'   => 0,
                            'currency'     => $ucid,
                        ];
                    }


                    $condition = false;
                    $condition2 = false;
                    if (isset($options["event"]) && isset($options["type"])) {
                        if ($options["type"] == "hosting" && $options["event"] == "HostingOrder")
                            $condition = true;
                        if ($options["type"] == "server" && $options["event"] == "ServerOrder")
                            $condition = true;
                        if ($options["type"] == "domain" && $options["event"] == "DomainNameRegisterOrder")
                            $condition = true;
                        if ($options["type"] == "domain" && $options["event"] == "DomainNameTransferRegisterOrder")
                            $condition = true;
                        if ($options["type"] == "domain" && $options["event"] == "RenewalDomain")
                            $condition = true;
                        if ($options["type"] == "software" && $options["event"] == "SoftwareOrder")
                            $condition = true;
                        if ($options["type"] == "special" && $options["event"] == "SpecialProductOrder")
                            $condition = true;
                        if ($options["type"] == "sms" && $options["event"] == "SmsProductOrder")
                            $condition = true;
                        $condition2 = $condition;
                        if ($options["event"] == "ExtendOrderPeriod") $condition2 = true;
                    }


                    if (isset($product) && $product) {
                        if ($condition && $coupon_products) {
                            foreach ($coupon_products as $coupon_id => $c_products) {
                                $product_k = $product["type"];
                                if ($product_k == "special") $product_k .= "-" . $product["type_id"];
                                if (isset($c_products[$product_k][$product["id"]])) {

                                    $_coupon = $temp_coupons[$coupon_id];

                                    $available_p = true;

                                    if ($_coupon["period_type"]) {
                                        $pd_type = '';
                                        $pd_duration = 0;

                                        if (isset($options["selection"]) && $options["selection"]) {
                                            $pd_type = $options["selection"]["period"];
                                            $pd_duration = $options["selection"]["time"];
                                        } elseif (isset($options["period"]) && $options["period"]) {
                                            $pd_type = $options["period"];
                                            $pd_duration = $options["period_time"];
                                        }
                                        if ($_coupon["period_type"] != $pd_type) $available_p = false;
                                        elseif ($_coupon["period_type"] != "none" && $_coupon["period_duration"] != $pd_duration) $available_p = false;
                                    }

                                    if (!$available_p) continue;

                                    $used_coupons[$coupon_id][] = $key;
                                    $used_coupons_groups[$coupon_id][$product_k] = true;
                                }
                            }
                        }
                        if ($condition2 && $d_rates && $d_status) {
                            $d = Products::find_in_rates($product, $d_rates, $o_quantity);
                            if ($d) {
                                $_amount = isset($items[$key]["amount_including_discount"]) ? $items[$key]["amount_including_discount"] : $amount;
                                $dRate = $d["rate"];
                                $dAmount = Money::get_discount_amount($_amount, $dRate);
                                $a_inc_disc = $dAmount > $_amount ? 0 : $_amount - $dAmount;
                                $items[$key]["amount_including_discount"] = $a_inc_disc;

                                $dealership_discount_amount += round($dAmount, 2);

                                $dealership_discounts[$key] = [
                                    'dkey'    => $d["k"],
                                    'name'    => $d["name"],
                                    'rate'    => $dRate,
                                    'amount'  => Money::formatter_symbol($dAmount, $ucid),
                                    'amountd' => $dAmount,
                                ];
                            }
                        }
                    }


                    $i_total_amount = $amount + $adds_amount;
                    $total_adds_amount += $adds_amount;
                    $items[$key]["adds_amount"] = $adds_amount;
                    $items[$key]["amount"] = $amount;
                    $items[$key]["total_amount"] = $i_total_amount;
                    if ($taxexempt) {
                        $tax_exempt_amount = Money::get_exclusive_tax_amount($i_total_amount, $tax_rate);
                        $total_taxexempt += $tax_exempt_amount;
                        $items[$key]["taxexempt"] = $taxexempt;
                    }

                    if (isset($product) && $product && !(in_array($options["event"] ?? '', ['RenewalDomain', 'ExtendOrderPeriod', 'ExtendAddonPeriod']))) {
                        if (isset($product_counts[$product["type"]][$product["id"]]))
                            $product_counts[$product["type"]][$product["id"]]["count"] += 1;
                        else {
                            $product_counts[$product["type"]][$product["id"]] = [
                                'count'   => 1,
                                'name'    => $product["title"] ?? ($product["name"] ?? ''),
                                'options' => $product["options"] ?? [],
                            ];
                        }

                    }
                }

                $discount_amounts = 0;
                $disco_total_amount = $total_amount;
                foreach ($dcoupons as $coupon) {
                    $coupon = (int)$coupon;
                    $coupon = Coupon::get(null, $coupon);
                    if ($coupon && !Coupon::validate($coupon, $uid)) $coupon = false;
                    if ($coupon) {

                        if ($taxation_type == "inclusive" && $tax_rate > 0.00 && $coupon["type"] == "amount") {
                            $discount_tax = Money::get_inclusive_tax_amount($coupon["amount"], $tax_rate);
                            if ($discount_tax > 0.00) $coupon["amount"] -= $discount_tax;
                        }


                        $usable = true;
                        if (!$udata && ($coupon["applyonce"] || $coupon["newsignups"] || $coupon["existingcustomer"]))
                            $usable = false;

                        if ($coupon["taxfree"]) $legal = 0;

                        if ($coupon["applyonce"] && $this->model->check_applyonce_coupon($coupon["id"], $udata["id"]))
                            $usable = false;


                        if ($coupon["newsignups"] && $this->model->check_ActivOrder($udata["id"]))
                            $usable = false;

                        if ($coupon["existingcustomer"] && !$this->model->check_lastActivOrder($udata["id"]))
                            $usable = false;

                        if ($coupon["pservices"]) {
                            $apply = isset($used_coupons[$coupon["id"]]) ? $used_coupons[$coupon["id"]] : [];
                        } else
                            $apply = "none";

                        if ($apply != "none") {
                            if (isset($used_coupons_groups[$coupon["id"]])) {
                                if (!$coupon["dealership"] && $d_status && isset($dealership_discounts) && $dealership_discounts)
                                    $usable = false;
                            }

                            if ($coupon["pservices"]) {
                                if (!isset($used_coupons_groups[$coupon["id"]])) $usable = false;
                                elseif (!isset($used_coupons[$coupon["id"]])) $usable = false;
                            }
                        }

                        if ($usable) {
                            $sumTotal = 0;

                            $onetime_use_per_order = isset($coupon["onetime_use_per_order"]) && $coupon["onetime_use_per_order"];

                            if ($apply == "none") {
                                if ($coupon["type"] == "percentage") {
                                    $sumTotal = Money::get_discount_amount($disco_total_amount, $coupon["rate"]);
                                } elseif ($coupon["type"] == "amount") {
                                    $sumTotal = Money::exChange($coupon["amount"], $coupon["currency"], $ucid);
                                }

                                foreach ($items as $item_id => $v) {
                                    $item_d_amount = round($sumTotal * $count, 2);

                                    $i_amount = ($v["amount"] - $item_d_amount);
                                    if ($i_amount < 0) $i_amount = 0;
                                    $i_amount = round($i_amount, 2);

                                    if (isset($item["amount_including_discount"]))
                                        $item["amount_including_discount"] = $i_amount;
                                    else
                                        $items[$item_id]["amount_including_discount"] = $i_amount;


                                    $coupons[$item_id] = [
                                        'id'      => $coupon["id"],
                                        'name'    => $coupon["code"],
                                        'rate'    => $coupon["rate"],
                                        'dvalue'  => $coupon["rate"] == 0 ? Money::formatter_symbol($item_d_amount, $ucid) : "%" . $coupon["rate"],
                                        'amount'  => Money::formatter_symbol($item_d_amount, $ucid),
                                        'amountd' => $item_d_amount,
                                    ];


                                    if (!isset($items[$item_id]["discounts"]))
                                        $items[$item_id]["discounts"] = [];
                                    if (!isset($items[$item_id]["discounts"]["coupons"]))
                                        $items[$item_id]["discounts"]["coupons"] = [];

                                    $items[$item_id]["discounts"]["coupons"][$coupon["id"]] = $item_d_amount;
                                    $disco_total_amount -= $item_d_amount;
                                }
                            } elseif ($apply) {
                                foreach ($apply as $item_id) {
                                    if (isset($items[$item_id]) && $item = $items[$item_id]) {
                                        $i_amount = $item["amount"];

                                        if (isset($item["amount_including_discount"]))
                                            $i_amount = $item["amount_including_discount"];
                                        else
                                            $items[$item_id]["amount_including_discount"] = $i_amount;

                                        if ($coupon["type"] == "percentage") {
                                            $item_d_amount = Money::get_discount_amount($i_amount, $coupon["rate"]);
                                        } else {
                                            $item_d_amount = $coupon["amount"];
                                            $item_d_amount = Money::exChange($item_d_amount, $coupon["currency"], $ucid);
                                        }

                                        if ($item_d_amount > $i_amount)
                                            $item_d_amount = $i_amount;

                                        $amount_calc = ($i_amount - $item_d_amount);
                                        if ($amount_calc < 0) $amount_calc = 0;
                                        $amount_calc = round($amount_calc, 2);


                                        $items[$item_id]["amount_including_discount"] = $amount_calc;

                                        $sumTotal += $item_d_amount;


                                        $coupons[$item_id] = [
                                            'id'      => $coupon["id"],
                                            'name'    => $coupon["code"],
                                            'rate'    => $coupon["rate"],
                                            'dvalue'  => $coupon["rate"] == 0 ? Money::formatter_symbol($item_d_amount, $ucid) : "%" . $coupon["rate"],
                                            'amount'  => Money::formatter_symbol($item_d_amount, $ucid),
                                            'amountd' => $item_d_amount,
                                        ];
                                        if ($onetime_use_per_order) break;

                                        if (!isset($items[$item_id]["discounts"]))
                                            $items[$item_id]["discounts"] = [];
                                        if (!isset($items[$item_id]["discounts"]["coupons"]))
                                            $items[$item_id]["discounts"]["coupons"] = [];

                                        $items[$item_id]["discounts"]["coupons"][$coupon["id"]] = $item_d_amount;
                                    }
                                }
                            }

                            if (isset($sumTotal) && $sumTotal) {
                                $discount_amounts += $sumTotal;
                            }
                        } else {
                            $index = array_search($coupon["id"], $dcoupons);
                            $ndcoupons = $dcoupons;
                            unset($ndcoupons[$index]);
                            $ndcoupons = array_values($ndcoupons);
                            if ($ndcoupons) {
                                $ndcoupons = implode(",", $ndcoupons);
                                Session::set("coupons", $ndcoupons, true);
                            } else
                                Session::delete("coupons");
                        }
                    }
                }

                $total_amount += $total_adds_amount;

                if (isset($promotion_total) && $promotion_total > 0.00) $total_amount -= $promotion_total;

                $result["total_amount"] = Money::formatter_symbol($total_amount, $ucid);
            }


            if ($legal && isset($getAddress) && $getAddress && $pmethod != "Balance" && Config::get("options/send-bill-to-address/status") && ($payment || $pay)) {
                $sendbta_amount = Config::get("options/send-bill-to-address/amount");
                $sendbta_curr = Config::get("options/send-bill-to-address/cid");
                $sendbta_price = Money::exChange($sendbta_amount, $sendbta_curr, $ucid);
                $result["sendbta_price"] = $sendbta_price ? Money::formatter_symbol($sendbta_price, $ucid) : __("website/basket/free-paid");
                $result["sendbta_visible"] = true;
                if ($sendbta) {
                    $result["sendbta_selected"] = true;
                    $total_amount += $sendbta_price;
                }
            }

            if (isset($total_amount)) $subtotal = $total_amount;

            if (isset($discount_amounts) && $discount_amounts) {
                if ($total_amount < $discount_amounts) $total_amount = 0;
                else $total_amount -= $discount_amounts;
            }

            if (isset($dealership_discount_amount) && $dealership_discount_amount) {
                if ($total_amount < $dealership_discount_amount) $total_amount = 0;
                else $total_amount -= $dealership_discount_amount;
            }

            if ($pmethods && isset($getAddress) && $getAddress && ($payment || $pay)) {
                $methods = [];
                if (!(round($total_amount, 2) > 0.00)) {
                    $pmethods = ["Free"];
                    $pmethod = "Free";
                }
                $balance_k = false;
                $k = -1;
                foreach ($pmethods as $key => $method) {
                    Modules::Load("Payment", $method);
                    if (class_exists($method)) {
                        $module = new $method();
                        if (isset($module->config["settings"]["accepted_countries"])) {
                            if ($module->config["settings"]["accepted_countries"])
                                if (!in_array($getAddress['country_code'], $module->config["settings"]["accepted_countries"])) continue;
                        }

                        if (isset($module->config["settings"]["unaccepted_countries"])) {
                            if ($module->config["settings"]["unaccepted_countries"])
                                if (in_array($getAddress['country_code'], $module->config["settings"]["unaccepted_countries"])) continue;
                        }


                        $mdata = [
                            'method'      => $method,
                            'name'        => $module->lang['invoice-name'],
                            'option_name' => $module->lang['option-name'],
                        ];
                        if (($pmethod == $method) || !$pmethod) {
                            $pmethod = $method;
                            $result["select_pmethod"] = $method;
                        }

                        $k++;
                        if ($method == "Balance") {
                            $balance = $module->get_credit();
                            $mdata["int_balance"] = round($balance, 2);
                            $mdata["balance"] = Money::formatter_symbol($balance, $udata["balance_currency"]);
                            $balance_k = $k;
                        }
                        if (method_exists($module, "commission_fee_calculator") && $module->commission) {
                            $camount = $module->commission_fee_calculator($total_amount);


                            if ($camount) $mdata["commission_fee"] = "+" . Money::formatter_symbol($camount, $ucid);
                            if ($pmethod == $method || (!$pmethod && $key == 0)) {
                                if ($camount) {

                                    $pmethod_commission = $camount;
                                    $total_amount += $camount;
                                    $subtotal += $camount;
                                    $result["pm_commission"] = __("website/basket/payment-method-commission", ['{name}' => $mdata["name"]]);
                                    $pmethod_commission_rate = $module->get_commission_rate();
                                    $result["pm_commission_rate"] = $pmethod_commission_rate;
                                    $result["pm_commission_amount"] = Money::formatter_symbol($camount, $ucid);
                                }
                            }
                        }
                        $methods[] = $mdata;
                    }
                }
            }

            if (isset($methods) && $methods && ($payment || $pay)) $result["payment_methods"] = $methods;

            if (isset($dealership_discounts) && $dealership_discounts) {
                $v_dealership_discounts = [];

                foreach ($dealership_discounts as $d) {
                    if (isset($v_dealership_discounts[$d["dkey"]])) {
                        $dAmount = $v_dealership_discounts[$d["dkey"]]["amountd"];
                        $dAmount += $d["amountd"];
                        $v_dealership_discounts[$d["dkey"]]["amountd"] = $dAmount;
                        $v_dealership_discounts[$d["dkey"]]["amount"] = Money::formatter_symbol($dAmount, $ucid);
                    } else
                        $v_dealership_discounts[$d["dkey"]] = $d;
                }
                $v_dealership_discounts = array_values($v_dealership_discounts);
                $discounts["dealership"] = $dealership_discounts;
                $result["dealership_discounts"] = $v_dealership_discounts;
            }

            if (isset($coupons) && $coupons) {
                $discounts["coupon"] = $coupons;

                $v_coupons = [];

                foreach ($coupons as $c) {
                    if (isset($v_coupons[$c["id"]])) {
                        $dAmount = $v_coupons[$c["id"]]["amountd"];
                        $dAmount += $c["amountd"];
                        $v_coupons[$c["id"]]["amountd"] = $dAmount;
                        $v_coupons[$c["id"]]["amount"] = Money::formatter_symbol($dAmount, $ucid);
                    } else
                        $v_coupons[$c["id"]] = $c;
                }
                $v_coupons = array_values($v_coupons);
                $result["coupon_discounts"] = $v_coupons;
            }

            if (isset($used_promotions) && $used_promotions) $discounts["promotions"] = $used_promotions;
            if ($count) {

                if ($legal) {
                    $result["taxation"] = true;
                    $result["tax_rate"] = $tax_rate;

                    $percentage_l = "";
                    $percentage_r = "";

                    if (Bootstrap::$lang->clang == "tr")
                        $percentage_l = "%";
                    else
                        $percentage_r = "%";


                    if (isset($getAddress) && $getAddress) {
                        $tax_rates = [];
                        $allRs = Config::get("options/tax-rates-names/" . $getAddress["country_id"]);
                        if (isset($allRs[$getAddress["city_id"]]) && $allRs[$getAddress["city_id"]]) {
                            foreach ($allRs[$getAddress["city_id"]] as $r) {
                                if (strlen($r['name']) > 1 && $r["value"] > 0.00)
                                    $tax_rates[] = $r["name"] . " " . $percentage_l . $r["value"] . $percentage_r;
                            }
                        }

                        if (isset($allRs[0]) && $allRs[0]) {
                            foreach ($allRs[0] as $r) {
                                if (strlen($r['name']) > 1 && $r["value"] > 0.00)
                                    $tax_rates[] = $r["name"] . " " . $percentage_l . $r["value"] . $percentage_r;
                            }
                        }
                        $size_tax_rates = sizeof($tax_rates);
                        if ($size_tax_rates > 0)
                            $tax_rates = '(' . implode(' + ', $tax_rates) . ')' . ($size_tax_rates > 1 ? '<br>' : '');
                        else
                            $tax_rates = '';
                        $result['tax_rates'] = $tax_rates;
                    }

                    if (isset($total_amount)) {
                        $total_tax_amount = Money::get_tax_amount($total_amount, $tax_rate);
                        if ($total_taxexempt) $total_tax_amount -= $total_taxexempt;
                        if ($total_tax_amount < 0.00) $total_tax_amount = 0;

                        $result["total_tax_amount"] = Money::formatter_symbol($total_tax_amount, $ucid);
                    }
                }

                if (isset($total_amount)) {
                    $total_payable = $total_amount;
                    if (isset($total_tax_amount)) $total_payable += $total_tax_amount;
                    $result["total_amount_payable"] = Money::formatter_symbol($total_payable, $ucid);
                    $result["int_total_amount_payable"] = round($total_payable, 2);
                }

                $result["use_coupon"] = $use_coupon;


                if ($pmethod == "Balance") {
                    $m_data = $methods[$balance_k];


                    $changing_balance = $m_data["int_balance"] - $total_payable;

                    if ($changing_balance < 0.01) $changing_balance = 0;

                    $changing_balance = Money::formatter_symbol($changing_balance, $ucid);
                    $m_data["changing_balance"] = $changing_balance;
                    $methods[$balance_k] = $m_data;
                    $result["payment_methods"] = $methods;
                }

            }

            if ($count && isset($udata) && $pay) {

                if (DEMO_MODE) {
                    die();
                }

                if ($contract1 != "true" || $contract2 != "true")
                    die(Utility::jencode([
                        'type'    => "pay",
                        'status'  => "error",
                        'message' => __("website/basket/error16"),
                    ]));

                if (!$udata["contract1"] || !$udata["contract2"]) {
                    User::setInfo($udata["id"], [

                        'contract2' => 1,
                    ]);
                    if (!$udata["contract1"]) {
                        User::addAction($udata["id"], "alteration", "contract1-is-approved");
                        User::setInfo($udata["id"], [
                            'contract1'            => 1,
                            'contract1_updated_at' => DateManager::Now(),
                        ]);
                    }
                    if (!$udata["contract2"]) {
                        User::addAction($udata["id"], "alteration", "contract2-is-approved");
                        User::setInfo($udata["id"], [
                            'contract2'            => 1,
                            'contract2_updated_at' => DateManager::Now(),
                        ]);
                    }
                }

                if (Validation::isEmpty($pmethod))
                    die(Utility::jencode([
                        'type'    => "pay",
                        'status'  => "error",
                        'message' => __("website/basket/error7"),
                    ]));

                if (!isset($getAddress) || !$getAddress)
                    die(Utility::jencode([
                        'type'    => "pay",
                        'status'  => "error",
                        'message' => __("website/basket/error8"),
                    ]));

                if ($pmethod == "Balance" && !($balance == 0 && $total_payable == 0)) {

                    if ($balance < round($total_payable, 2))
                        die(Utility::jencode([
                            'type'    => "pay",
                            'status'  => "error",
                            'message' => __("website/basket/error10"),
                        ]));
                }

                $discounts = [];
                $user_data = [];
                $options = ['type' => "pay"];
                $udata["lang"] = $lang;
                $user_data = array_merge($user_data, $udata);


                if (Utility::strlen($getAddress["email"]) > 1) $user_data["email"] = $getAddress["email"];
                if (Utility::strlen($getAddress["name"]) > 1) $user_data["name"] = $getAddress["name"];
                if (Utility::strlen($getAddress["surname"]) > 1) $user_data["surname"] = $getAddress["surname"];
                if (Utility::strlen($getAddress["full_name"]) > 1) $user_data["full_name"] = $getAddress["full_name"];
                if (Utility::strlen($getAddress["full_name"]) > 1) {
                    if (Utility::strlen($getAddress["phone"]) > 4) {
                        $user_data["phone"] = $getAddress["phone"];
                        $phone_smash = Filter::phone_smash($getAddress["phone"]);
                        $user_data["gsm_cc"] = $phone_smash["cc"];
                        $user_data["gsm"] = $phone_smash["number"];

                    } else {
                        $user_data["phone"] = '';
                        $user_data["gsm_cc"] = '';
                        $user_data["gsm"] = '';
                    }
                }
                if (Utility::strlen($getAddress["kind"]) > 1) $user_data["kind"] = $getAddress["kind"];

                if (Utility::strlen($getAddress["full_name"]) > 1) {
                    $user_data["company_name"] = $getAddress["company_name"];
                    $user_data["company_tax_number"] = $getAddress["company_tax_number"];
                    $user_data["company_tax_office"] = $getAddress["company_tax_office"];
                }

                $identity_status = Config::get("options/sign/up/kind/individual/identity/status");
                $identity_required = Config::get("options/sign/up/kind/individual/identity/required");
                if ($identity_status && $identity_required && !Validation::isEmpty($getAddress["identity"]))
                    $user_data["identity"] = $getAddress["identity"];


                $fake_addr = $getAddress;

                unset($fake_addr["name"]);
                unset($fake_addr["surname"]);
                unset($fake_addr["full_name"]);
                unset($fake_addr["kind"]);
                unset($fake_addr["company_name"]);
                unset($fake_addr["company_tax_office"]);
                unset($fake_addr["company_tax_number"]);
                unset($fake_addr["phone"]);
                unset($fake_addr["email"]);
                unset($fake_addr["identity"]);

                $user_data["address"] = $fake_addr;


                $user_data["address"] = $getAddress;

                if (isset($user_data["kind"]) && $user_data["kind"] == "individual")
                    if (Validation::isEmpty($user_data["identity"] ?? '') && !Validation::isEmpty($udata["identity"]))
                        $user_data["identity"] = $udata["identity"];

                if (Validation::isEmpty($user_data["phone"] ?? '') && !Validation::isEmpty($udata["phone"])) {
                    $user_data["phone"] = $udata["phone"];
                    $user_data["gsm_cc"] = $udata["gsm_cc"];
                    $user_data["gsm"] = $udata["gsm"];
                }

                $currency_check = Money::Currency($ucid, true);
                if (!$currency_check)
                    die(Utility::jencode([
                        'type'    => "pay",
                        'status'  => "error",
                        'message' => "Invalid currency: " . $ucid,
                    ]));


                if (isset($coupons) && $coupons) $discounts["coupon"] = $coupons;
                if (isset($dealership_discounts) && $dealership_discounts) $discounts["dealership"] = $dealership_discounts;
                if (isset($used_promotions) && $used_promotions) $discounts["promotions"] = $used_promotions;
                $options['user_id'] = $udata["id"];
                $options['user_data'] = $user_data;
                $options['local'] = (int)$isLocal;
                $options['legal'] = (int)$legal;
                $options['currency'] = $ucid;
                $options['taxrate'] = $tax_rate;
                $options['subtotal'] = isset($subtotal) ? $subtotal : 0;
                $options['tax'] = isset($total_tax_amount) ? $total_tax_amount : 0;
                $options['total'] = isset($total_payable) ? $total_payable : 0;
                $options['sendbta'] = isset($sendbta) ? $sendbta : 0;
                $options['sendbta_amount'] = isset($sendbta) && isset($sendbta_price) ? $sendbta_price : 0;
                $options['pmethod'] = $pmethod;
                $options['pmethod_commission'] = isset($pmethod_commission) ? $pmethod_commission : 0;
                $options['pmethod_commission_rate'] = isset($pmethod_commission_rate) ? $pmethod_commission_rate : 0;
                if ($discounts) {
                    $options["discounts"] = [
                        'used_coupons'    => $dcoupons,
                        'used_promotions' => $dpromotions,
                        'items'           => $discounts,
                    ];
                }

                if (isset($subscribable) && $subscribable) {
                    foreach ($subscribable as $k => $v) {
                        if (isset($pmethod_commission_rate) && $pmethod_commission_rate > 0.00) {
                            $v["commission_rate"] = $pmethod_commission_rate;
                            $v["amount"] += Money::get_exclusive_tax_amount($v["amount"], $pmethod_commission_rate);
                            $v["tax_included"] = $v["amount"];
                        }

                        if ($options["tax"] > 0.00 && (!isset($v["tax_exempt"]) || !$v["tax_exempt"])) {
                            $v["tax_rate"] = $tax_rate;
                            $v["tax_included"] = $v["amount"] + Money::get_exclusive_tax_amount($v["amount"], $tax_rate);
                        }
                        $subscribable[$k] = $v;
                    }
                }

                $options["subscribable"] = $subscribable ?? [];


                if (($udata["block-proxy-usage"] || Config::get("options/proxy-block")) && UserManager::is_proxy() === true)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("errors/error9"),
                    ]));


                if (Config::get("options/blacklist/status")) {
                    if (Config::get("options/blacklist/order-blocking")) {
                        if (User::checkBlackList($udata))
                            die(Utility::jencode([
                                'type'    => "pay",
                                'status'  => "error",
                                'message' => __("website/basket/error18", ['{err_msg}' => ' Detected by WFraud']),
                            ]));
                    }

                    if (Config::get("options/blacklist/ip-country-mismatch")) {
                        $ipInfo = UserManager::ip_info();
                        $info_country = strtoupper($ipInfo["countryCode"] ?? 'US');
                        $address_country = $options["user_data"]["address"]["country_code"] ?? 'US';
                        if ($info_country != $address_country) {
                            die(Utility::jencode([
                                'type'    => "pay",
                                'status'  => "error",
                                'message' => __("website/basket/error18", ['{err_msg}' => ' ' . Bootstrap::$lang->get_cm("website/basket/error19")]),
                            ]));
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
                            $m_init = new $k_m;
                            if (method_exists($m_init, 'check')) {
                                $check = $m_init->check($fraud_params);
                                $err_msg = $m_init->error;
                                if (!$check)
                                    die(Utility::jencode([
                                        'type'    => "pay",
                                        'status'  => "error",
                                        'message' => $err_msg ? $err_msg : __("website/basket/error18", ['{err_msg}' => ' Detected by ' . $k]),
                                    ]));
                            }
                        }
                    }
                }

                if (isset($product_counts) && $product_counts) {
                    foreach ($product_counts as $pg => $ps) {
                        foreach ($ps as $pid => $pv) {
                            $olpu = (int)$pv["options"]["order_limit_per_user"] ?? 0;
                            if ($olpu > 0) {
                                $used_orders = $this->model->db->select("COUNT(id) AS count")->from("users_products");
                                $used_orders->where("type", "=", $pg, "&&");
                                $used_orders->where("product_id", "=", $pid, "&&");
                                $used_orders->where("owner_id", "=", $uid);
                                $used_orders = $used_orders->build() ? $used_orders->getObject()->count : 0;
                                $used_orders_m = $used_orders + ($pv["count"] ?? 0);

                                if ($used_orders_m > $olpu) {
                                    echo Utility::jencode([
                                        'type'    => "pay",
                                        'status'  => "error",
                                        'message' => __("website/basket/error20", ['{name}' => $pv["name"], '{limit}' => $olpu]),
                                    ]);
                                    return false;
                                }
                            }
                        }
                    }
                }

                $options['redirect'] = [
                    'success' => $this->CRLink("pay-successful"),
                    'failed'  => $this->CRLink("basket-payment") . "?status=fail",
                    'return'  => $this->CRLink("basket-payment"),
                ];


                $checkout = Basket::add_checkout([
                    'user_id' => $udata["id"],
                    'type'    => "basket",
                    'items'   => Utility::jencode(isset($items) ? $items : []),
                    'data'    => Utility::jencode($options),
                    'cdate'   => DateManager::Now(),
                    'mdfdate' => DateManager::Now(),
                ]);

                if ($checkout) {
                    $redirect = $this->CRLink("basket-pay", [$checkout]);

                    if (!in_array($pmethod, ['Balance', 'Free']) && Config::get("options/firstly-create-invoice")) {
                        $invoice = Invoices::process(Basket::get_checkout($checkout), 'UNPAID');
                        if (!$invoice) {
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => "Somethings went wrong!",
                            ]));
                        }
                        Basket::delete_checkout($checkout);
                        $redirect = $this->CRLink("ac-ps-detail-invoice", [$invoice["id"]]) . "?operation=payment-screen&sendbta=" . $sendbta . "&pmethod=" . $pmethod;
                        Basket::clear();
                    }

                    echo Utility::jencode([
                        'type'     => "pay",
                        'status'   => "successful",
                        'redirect' => $redirect,
                    ]);

                } else
                    die(Utility::jencode([
                        'type'    => "pay",
                        'status'  => "error",
                        'message' => __("website/basket/error9"),
                    ]));


            } else {
                echo Utility::jencode($result);
            }

        }


        private function delete_item()
        {

            $this->takeDatas("language");

            $id = Filter::init("POST/id", "amount");
            if (!$id) return false;


            Helper::Load("Basket");
            $delete = Basket::delete(false, $id);

            if (!$delete)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/basket/error2"),
                ]));


            Basket::save();

            if (Basket::count() == 0) Session::delete("discount_coupons");


            die(Utility::jencode([
                'status'  => "successful",
                'message' => __("website/basket/successful1"),
            ]));

        }


        private function set_wprivacy()
        {

            $this->takeDatas("language");

            $id = (int)Filter::init("POST/id", "numbers");
            $check = Filter::init("POST/check", "letters");

            if (!$id) return false;

            Helper::Load(["Basket", "Products"]);
            $idata = Basket::get(false, $id);
            if (!isset($idata["options"])) return false;
            $options = $idata["options"];

            if (isset($options["event"]) && ($options["event"] == "DomainNameRegisterOrder" || $options["event"] == "DomainNameTransferRegisterOrder")) {

                $new_opt = $options;
                if ($check == "true") $new_opt["wprivacy"] = true;
                elseif ($check == "false" && isset($new_opt["wprivacy"])) unset($new_opt["wprivacy"]);

                Basket::set($idata["unique"], $idata["name"], $new_opt);
                Basket::save();
                echo Utility::jencode(['status' => "successful"]);
            }

        }

        private function change_selection_period()
        {

            $this->takeDatas("language");

            $lang = Bootstrap::$lang->clang;
            $id = (int)Filter::init("POST/id", "numbers");
            $selection = (int)Filter::init("POST/selection", "numbers");

            if (!$id || ($selection != 0 && Validation::isEmpty($selection))) die("Invalid params");

            Helper::Load(["Basket", "Products"]);
            $idata = Basket::get(false, $id);
            if (!isset($idata["options"])) die("Invalid ID");
            $options = $idata["options"];

            if (isset($options["type"]) && isset($options["id"]))
                $product = Products::get($options["type"], $options["id"]);

            if (!isset($product) || !$product) die("Not found product");


            if (isset($options["event"]) && preg_match("/Order$/i", $options["event"])) {
                if (sizeof($product["price"]) > 1 && isset($product["price"][$selection])) {
                    $selection = $product["price"][$selection];
                    $new_opt = $options;

                    if (isset($options["addons"]) && $options["addons"]) {
                        foreach ($options["addons"] as $addon_id => $option_id) {
                            $get_addon = Products::addon($addon_id);
                            $find_c_option = false;
                            $find_n_option = false;
                            $show_by_pp = $get_addon["properties"]["show_by_pp"] ?? false;

                            if ($show_by_pp) {
                                unset($new_opt["addons"][$addon_id]);

                                foreach ($get_addon["options"] as $ad_opt)
                                    if ($option_id == $ad_opt["id"]) $find_c_option = $ad_opt;
                                if ($find_c_option) {
                                    foreach ($get_addon["options"] as $ad_opt) {
                                        $opt_period = $ad_opt["period"];
                                        $opt_time = (int)$ad_opt["period_time"];
                                        if (!$opt_time) $opt_time = 1;
                                        if ($find_c_option["name"] == $ad_opt["name"] && $selection["period"] == $opt_period && $selection["time"] == $opt_time) $find_n_option = $ad_opt;
                                        elseif ($find_c_option["name"] == $ad_opt["name"] && $find_c_option["period"] == "none" && $ad_opt["period"] == "none") $find_n_option = $ad_opt;
                                    }
                                    if ($find_n_option) $new_opt["addons"][$addon_id] = $find_n_option["id"];
                                }

                            }
                        }
                    }

                    $new_opt["selection"] = $selection;

                    Basket::set($idata["unique"], $idata["name"], $new_opt);
                    Basket::save();
                    echo Utility::jencode(['status' => "successful"]);
                }
            }
        }

        private function change_selection_year()
        {

            $this->takeDatas("language");

            $id = (int)Filter::init("POST/id", "numbers");
            $selection = (int)Filter::init("POST/selection", "numbers");

            if (!$id || $selection < 0 || $selection > 20) die("Invalid params");

            Helper::Load(["Basket", "Products"]);
            $idata = Basket::get(false, $id);
            if (!isset($idata["options"])) die("Invalid ID");
            $options = $idata["options"];

            if (isset($options["type"]) && isset($options["id"]))
                $product = Products::get($options["type"], $options["id"]);

            if (!isset($product) || !$product) die("Not found product");


            if (isset($options["event"]) && in_array($options["event"], ['DomainNameRegisterOrder', 'DomainNameTransferRegisterOrder', 'RenewalDomain'])) {
                if ($selection < 0 || $selection > 100) die("Unknown Error: Period Time");

                $new_opt = $options;
                $new_opt["period_time"] = $selection;
                if (isset($options["event_data"]["year"])) $new_opt["event_data"]["year"] = $selection;

                Basket::set($idata["unique"], $idata["name"], $new_opt);
                Basket::save();
                echo Utility::jencode(['status' => "successful"]);
            }
        }

        private function change_domain_ns()
        {

            $this->takeDatas("language");

            $id = (int)Filter::init("POST/item_id", "numbers");


            if (!$id) die("Invalid params");

            Helper::Load(["Basket", "Products"]);
            $idata = Basket::get(false, $id);
            if (!isset($idata["options"])) die("Invalid ID");
            $options = $idata["options"];

            if (isset($options["type"]) && isset($options["id"]))
                $product = Products::get($options["type"], $options["id"]);

            if (!isset($product) || !$product) die("Not found product");

            if (isset($options["event"]) && in_array($options["event"], ['DomainNameRegisterOrder', 'DomainNameTransferRegisterOrder'])) {
                $post_dns = Filter::init("POST/dns");

                $x_dns = [];

                if (!$post_dns || !is_array($post_dns)) exit("Data not found");
                $i = 0;

                foreach ($post_dns as $p) {
                    $i++;
                    $p = str_replace(["https://", "http://", "www."], "", Utility::strtolower($p));
                    if (Utility::strlen($p) > 3) $x_dns["ns" . $i] = $p;
                }


                $new_opt = $options;
                $new_opt["dns"] = $x_dns;

                Basket::set($idata["unique"], $idata["name"], $new_opt);
                Basket::save();


                echo Utility::jencode([
                    'status'  => "successful",
                    'message' => __("website/basket/dns-tx4"),
                ]);

            }
        }

        private function change_domain_whois()
        {

            $this->takeDatas("language");

            $id = (int)Filter::init("POST/item_id", "numbers");


            if (!$id) die("Invalid params");

            Helper::Load(["Basket", "Products"]);
            $idata = Basket::get(false, $id);
            if (!isset($idata["options"])) die("Invalid ID");
            $options = $idata["options"];

            if (isset($options["type"]) && isset($options["id"]))
                $product = Products::get($options["type"], $options["id"]);

            if (!isset($product) || !$product) die("Not found product");

            if (isset($options["event"]) && in_array($options["event"], ['DomainNameRegisterOrder', 'DomainNameTransferRegisterOrder'])) {
                $contact_types = ['registrant', 'administrative', 'technical', 'billing'];

                $udata = UserManager::LoginData();
                $data = [];

                $apply_to_all = Filter::init("POST/apply_to_all");

                foreach ($contact_types as $ct) {
                    $full_name = Filter::init("POST/info/" . $ct . "/Name", "hclear");
                    $company_name = Filter::init("POST/info/" . $ct . "/Company", "hclear");
                    $email = Filter::init("POST/info/" . $ct . "/EMail", "email");
                    $pcountry_code = Filter::init("POST/info/" . $ct . "/PhoneCountryCode", "numbers");
                    $phone = Filter::init("POST/info/" . $ct . "/Phone", "numbers");
                    $fcountry_code = Filter::init("POST/info/" . $ct . "/FaxCountryCode", "numbers");
                    $fax = Filter::init("POST/info/" . $ct . "/Fax", "numbers");
                    $address = Filter::init("POST/info/" . $ct . "/Address", "hclear");
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
                        if ($validation)
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => __("website/account_products/modify-whois-error1"),
                            ]));
                    }

                    if ($validation && !Validation::isEmail($email))
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/account_products/modify-whois-error2"),
                        ]));

                    $names = Filter::name_smash($full_name);
                    $first_name = $names["first"];
                    $last_name = $names["last"];

                    if ($validation && Utility::strlen($last_name) < 1)
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/account_products/modify-whois-error1"),
                        ]));


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
                        if (!in_array($ct, $contact_types)) continue;
                        $data_x = $data[$ct];
                        foreach ($contact_types as $c) $data[$c] = $data_x;
                    }
                }

                $profile_ids = Filter::init("POST/profile_id");
                if ($profile_ids && is_array($profile_ids)) {
                    $apply_all_profile_id = 0;

                    foreach ($profile_ids as $ct => $profile_id) {
                        $profile_id = Filter::letters_numbers($profile_id);
                        if ($apply_all_profile_id) $profile_id = $apply_all_profile_id;
                        $ct = Filter::letters($ct);
                        if (!in_array($ct, $contact_types)) continue;
                        $profile_name = Filter::init("POST/profile_name/" . $ct, "hclear");
                        if ($profile_id == "new") {

                            if (Validation::isEmpty($profile_name)) $profile_name = 'Untitled';

                            $profile_id = User::create_whois_profile([
                                'owner_id'    => $udata["id"],
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
                            foreach ($contact_types as $c) $data[$c]["profile_id"] = $profile_id;
                        }
                    }
                    $rows = User::whois_profiles($udata["id"]);
                    if ($rows) {
                        $has_detouse = 0;
                        foreach ($rows as $row) if ($row["detouse"] == 1) $has_detouse = $row["id"];
                        if ($has_detouse < 1) {
                            User::remove_detouse_whois_profile($udata["id"]);
                            User::set_whois_profile($rows[0]["id"], ['detouse' => 1]);
                        }
                    }
                }

                $options["whois"] = $data;


                Basket::set($idata["unique"], $idata["name"], $options);
                Basket::save();


                echo Utility::jencode([
                    'status'  => "successful",
                    'message' => __("website/basket/whois-tx4"),
                ]);

            }
        }


        private function coupon_check()
        {
            $this->takeDatas("language");
            Helper::Load(["Basket", "Products", "Money", "Coupon"]);

            if (Config::get("options/use-coupon") && Basket::count()) {
                $code = Filter::init("POST/code", "hclear");
                if (Utility::strlen($code) >= 3) {
                    $coupon = Coupon::get($code);
                    if (!$coupon)
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/basket/error3"),
                        ]));

                    $d_rates = [];
                    $o_quantity = 0;

                    $d_status = Config::get("options/dealership/status");
                    $udata = UserManager::LoginData("member");
                    if ($udata) {
                        $infos = User::getInfo($udata["id"], "dealership");
                        $udata = array_merge($udata, $infos);
                        $dealership = !isset($udata["dealership"]) || $udata["dealership"] == null ? [] : Utility::jdecode($udata["dealership"], true);
                        if ($dealership && $dealership["status"] == "active") {
                            $d_rates = (array)Config::get("options/dealership/rates");
                            if (is_array(current($dealership["discounts"])))
                                $d_rates = array_merge($d_rates, $dealership["discounts"]);
                            $o_quantity = sizeof(User::dealership_orders($udata['id'], $d_rates));
                        }
                    }


                    if (!Coupon::validate($coupon, $udata["id"] ?? 0))
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => Coupon::get_message(),
                        ]));


                    $coupons = Session::get("coupons", true);
                    $coupons = $coupons ? explode(",", $coupons) : [];

                    if (in_array($coupon["id"], $coupons))
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/basket/error4"),
                        ]));

                    if ($coupon["pservices"]) {
                        $c_products = Products::find_products_in_coupon($coupon["pservices"]);

                        $used_groups = [];
                        $usable = false;
                        $lang = Bootstrap::$lang->clang;
                        $items = Basket::listing();
                        $e_ds = false;

                        if ($coupons) {
                            $_used_items = [];
                            foreach ($coupons as $c) {
                                $c = Coupon::get(null, $c);
                                if ($c && !Coupon::validate($c, $udata["id"] ?? 0)) continue;
                                $c_pds = Products::find_products_in_coupon($c["pservices"]);
                                foreach ($items as $item_id => $item) {
                                    $options = isset($item["options"]) ? $item["options"] : [];
                                    $product = false;
                                    $condition = false;

                                    if (isset($options["type"]) && isset($options["id"]))
                                        $product = Products::get($options["type"], $options["id"], $lang);

                                    if (isset($options["event"]) && isset($options["type"])) {
                                        if ($options["type"] == "hosting" && $options["event"] == "HostingOrder")
                                            $condition = true;
                                        if ($options["type"] == "server" && $options["event"] == "ServerOrder")
                                            $condition = true;
                                        if ($options["type"] == "domain" && $options["event"] == "DomainNameRegisterOrder")
                                            $condition = true;
                                        if ($options["type"] == "domain" && $options["event"] == "DomainNameTransferRegisterOrder")
                                            $condition = true;
                                        if ($options["type"] == "domain" && $options["event"] == "RenewalDomain")
                                            $condition = true;
                                        if ($options["type"] == "software" && $options["event"] == "SoftwareOrder")
                                            $condition = true;
                                        if ($options["type"] == "special" && $options["event"] == "SpecialProductOrder")
                                            $condition = true;
                                        if ($options["type"] == "sms" && $options["event"] == "SmsProductOrder")
                                            $condition = true;
                                    }

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

                        foreach ($items as $item_id => $item) {
                            $options = isset($item["options"]) ? $item["options"] : [];
                            $product = false;
                            $condition = false;

                            if (isset($options["type"]) && isset($options["id"]))
                                $product = Products::get($options["type"], $options["id"], $lang);

                            if (isset($options["event"]) && isset($options["type"])) {
                                if ($options["type"] == "hosting" && $options["event"] == "HostingOrder")
                                    $condition = true;
                                if ($options["type"] == "server" && $options["event"] == "ServerOrder")
                                    $condition = true;
                                if ($options["type"] == "domain" && $options["event"] == "DomainNameRegisterOrder")
                                    $condition = true;
                                if ($options["type"] == "domain" && $options["event"] == "DomainNameTransferRegisterOrder")
                                    $condition = true;
                                if ($options["type"] == "domain" && $options["event"] == "RenewalDomain")
                                    $condition = true;
                                if ($options["type"] == "software" && $options["event"] == "SoftwareOrder")
                                    $condition = true;
                                if ($options["type"] == "special" && $options["event"] == "SpecialProductOrder")
                                    $condition = true;
                                if ($options["type"] == "sms" && $options["event"] == "SmsProductOrder")
                                    $condition = true;
                            }

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
                                    }
                                    if ($coupon["period_type"] != $pd_type) $available_p = false;
                                    elseif ($coupon["period_type"] != "none" && $coupon["period_duration"] != $pd_duration) $available_p = false;
                                }

                                if ($find_product && $available_p) {
                                    if (!$coupon["use_merge"] && isset($_used_items[$item_id])) {
                                        die(Utility::jencode([
                                            'status'  => "error",
                                            'message' => __("website/basket/error17"),
                                        ]));
                                    }
                                    $usable = true;
                                    $used_groups[$p_k] = true;
                                }
                                if ($d_rates && Products::find_in_rates($product, $d_rates, $o_quantity)) $e_ds = true;
                            }
                        }

                        if ($udata) {
                            if (isset($dealership["status"]) && $d_status && $dealership["status"] == "active" && !$coupon["dealership"]) {
                                if ($e_ds) $usable = false;
                            }
                        }
                    } else
                        $usable = "none";


                    if (!$usable && $usable != "none" && $e_ds)
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/basket/error5"),
                        ]));
                    elseif (!$usable)
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/basket/error6"),
                        ]));

                    if ($usable === "none")
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/basket/error6"),
                        ]));

                    array_push($coupons, $coupon["id"]);

                    echo Utility::jencode(['status' => "successful"]);

                    $coupons = implode(",", $coupons);
                    Session::set("coupons", $coupons, true);
                }
            }
        }


        private function delete_coupon()
        {
            $id = (int)Filter::init("POST/coupon_id", "numbers");
            if ($id) {
                if (Session::get("coupons")) {
                    $dcoupons = Session::get("coupons", true);
                    $dcoupons = $dcoupons == null ? [] : explode(",", $dcoupons);
                    if ($dcoupons) {
                        if (in_array($id, $dcoupons)) {
                            $gkey = array_search($id, $dcoupons);
                            if (gettype($gkey) != "boolean") {
                                unset($dcoupons[$gkey]);
                                $dcoupons = array_values($dcoupons);
                                $dcoupons = implode(",", $dcoupons);
                                Session::set("coupons", $dcoupons, true);
                                echo Utility::jencode(['status' => "successful"]);
                            }
                        }
                    }
                }
            }
        }


        private function pay_main()
        {

            Helper::Load(["Orders", "Invoices", "Products", "Money", "User", "Basket"]);
            $sdata = UserManager::LoginData("member");

            if (!$this->checking_user($sdata)) return false;

            $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : false;
            if ($id && Validation::isInt($id)) $checkout = Basket::get_checkout($id, $sdata["id"], "basket");


            if (!isset($checkout) || !$checkout) {
                Utility::redirect($this->CRLink("basket-payment"));
                die();
            }

            $checkout_data = $checkout["data"];

            $pmethods = Config::get("modules/payment-methods");
            if (!(round($checkout_data["total"], 2) > 0.00)) $pmethods = ["Free"];

            if (!in_array($checkout_data["pmethod"], $pmethods)) die("There is no such payment method");


            $p_m_data = Modules::Load("Payment", $checkout_data["pmethod"]);
            if (!class_exists($checkout_data["pmethod"])) die("There is no such payment addons");
            $module = new $checkout_data["pmethod"]();

            $module->set_checkout($checkout);

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
            ]);


            $success = $this->CRLink("pay-successful");
            $failed = $this->CRLink("basket-payment") . "?status=fail";

            Session::set("last_paid_page", Utility::jencode([
                'success' => $success,
                'failed'  => $failed,
            ]), true);

            $links = [
                'bring'           => $this->CRLink("basket") . "?bring=",
                'successful-page' => $success,
                'failed-page'     => $failed,
                'back'            => $this->CRLink("basket-payment"),
            ];

            $this->addData("meta", __("website/basket/meta2"));
            $this->addData("header_background", $this->header_background());
            $this->addData("header_title", __("website/basket/header-title-pay"));
            $this->addData("header_description", __("website/basket/header-desc3"));

            $lang_list = $this->getData("lang_list");
            $lang_size = $this->getData("lang_count");
            if ($lang_size > 0) {
                $keys = array_keys($lang_list);
                $lang_size -= 1;
                for ($i = 0; $i <= $lang_size; $i++) {
                    $key = $lang_list[$keys[$i]]["key"];
                    $lang_list[$keys[$i]]["link"] = $this->CRLink("basket-pay", [$id], $key);
                }
                $this->addData("lang_list", $lang_list);
            }


            if (method_exists($module, "get_auth_token"))
                $links["callback"] = $this->CRLink("payment", [$checkout_data["pmethod"], $module->get_auth_token(), "callback"]);

            $this->addData("links", $links);
            $this->addData("module", $module);
            $this->addData("checkout", $checkout);
            $this->addData("_LANG", $module->lang);

            if ($module->page_type == "in-page") {
                $this->addData("page", $this->view->chose(false, true)->render($module->payform, $this->data, true));
                $this->view->chose("website")->render("basket-pay", $this->data);
            } elseif ($module->page_type == "full-page")
                $this->view->chose(false, true)->render($module->payform, $this->data);
        }


        private function payment_main()
        {

            Helper::Load("Basket");

            $udata = UserManager::LoginData("member");

            if (!Config::get("options/easy-order") && $udata && !$this->checking_user($udata)) return false;

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
            ]);


            if ($this->data["basket_count"] < 1) {
                Utility::redirect($this->CRLink("basket"));
                die();
            }

            $links = [
                'bring'        => $this->CRLink("basket") . "?bring=",
                'basket'       => $this->CRLink("basket"),
                'payment'      => $this->CRLink("basket-payment"),
                'ac-info'      => $this->CRLink("ac-ps-info"),
                'bring-info'   => $this->CRLink("ac-ps-info") . "?bring=",
                'balance-page' => $this->CRLink("ac-ps-balance"),
            ];

            $this->addData("links", $links);
            $this->addData("meta", __("website/basket/meta"));
            $this->addData("header_background", $this->header_background());
            $this->addData("header_title", __("website/basket/header-title-payment"));
            $this->addData("header_description", __("website/basket/header-desc2"));

            $lang_list = $this->getData("lang_list");
            $lang_size = $this->getData("lang_count");
            if ($lang_size > 0) {
                $keys = array_keys($lang_list);
                $lang_size -= 1;
                for ($i = 0; $i <= $lang_size; $i++) {
                    $key = $lang_list[$keys[$i]]["key"];
                    $lang_list[$keys[$i]]["link"] = $this->CRLink("basket-payment", false, $key);
                }
                $this->addData("lang_list", $lang_list);
            }


            if ($udata) {

                $udata = array_merge
                (
                    $udata,
                    User::getData($udata["id"], 'full_name,company_name,phone,email,country', 'array'),
                    User::getInfo($udata["id"], ['kind', 'company_tax_office', 'company_tax_number', 'identity'])
                );

                $this->addData("udata", $udata);


                $this->view->chose("website")->render("basket-payment", $this->data);
            } else {
                $this->addData("kind_status", (Config::get("options/sign/up/kind/status") == 1));
                $this->addData("email_verify_status", (Config::get("options/sign/up/email/verify") == 1));
                $this->addData("gsm_status", (Config::get("options/sign/up/gsm/status") == 1));
                $this->addData("gsm_required", (Config::get("options/sign/up/gsm/required") == 1));
                $this->addData("sms_verify_status", (Config::get("options/sign/up/gsm/verify") == 1));

                $this->addData("custom_fields", $this->model->get_custom_fields(Bootstrap::$lang->clang));

                $this->view->chose("website")->render("basket-account", $this->data);
            }

        }


        public function main()
        {
            $bring = Filter::init("GET/bring", "route");
            if ($bring && $bring != '') return $this->main_bring($bring);
            if (isset($this->params[0]) && $this->params[0] == "payment") return $this->payment_main();
            if (isset($this->params[0]) && $this->params[0] == "pay") return $this->pay_main();

            if (!Config::get("options/visitors-will-see-basket") && !$this->checking_user()) return false;

            $udata = UserManager::LoginData();


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
            ]);


            $links = [
                'bring'          => $this->CRLink("basket") . "?bring=",
                'payment'        => $this->CRLink("basket-payment"),
                'whois-profiles' => $this->CRLink("ac-ps-products-t", ["domain"]) . "?page=whois_profiles",
            ];

            $this->addData("links", $links);
            $this->addData("meta", __("website/basket/meta"));
            $this->addData("header_background", $this->header_background());
            $this->addData("header_title", __("website/basket/name"));
            $this->addData("header_description", __("website/basket/header-desc"));
            $this->addData("udata", $udata);
            if ($udata) $this->addData("whois_profiles", User::whois_profiles($udata["id"]));
            $this->addData("contact_types", [
                'registrant'     => __("website/account_products/whois-contact-type-registrant"),
                'administrative' => __("website/account_products/whois-contact-type-administrative"),
                'technical'      => __("website/account_products/whois-contact-type-technical"),
                'billing'        => __("website/account_products/whois-contact-type-billing"),
            ]);

            if ($udata) {
                $udata = array_merge($udata, User::getData($udata["id"], [
                    'name',
                    'surname',
                    'full_name',
                    'company_name',
                    'email',
                ], 'assoc'), User::getInfo($udata["id"], [
                    'gsm_cc',
                    'gsm',
                ]));

                $address = AddressManager::getAddress(0, $udata["id"]);

                if ($address) $udata["address"] = $address;


                $zipcode = AddressManager::generate_postal_code($udata["address"]["country_code"]);
                $state_x = $udata["address"]["counti"];
                $city_y = $udata["address"]["city"];
                $country_code = $udata["address"]["country_code"];

                Filter::$transliterate_cc = $country_code;


                if ($country_code == "TR") {
                    $state = $state_x;
                    $city = $city_y;
                } else {
                    $state = $city_y;
                    $city = $state_x;
                }

                $user_whois_info = [
                    'Name'             => $udata["full_name"],
                    'FirstName'        => $udata["name"],
                    'LastName'         => $udata["surname"],
                    'Company'          => $udata["company_name"],
                    'AddressLine1'     => $udata["address"]["address"],
                    'AddressLine2'     => null,
                    'ZipCode'          => $udata["address"]["zipcode"] ? $udata["address"]["zipcode"] : $zipcode,
                    'State'            => $state,
                    'City'             => $city,
                    'Country'          => $country_code,
                    'EMail'            => $udata["email"],
                    'Phone'            => $udata["gsm"],
                    'PhoneCountryCode' => $udata["gsm_cc"],
                    'Fax'              => null,
                    'FaxCountryCode'   => null,
                ];


                $this->addData("user_whois_info", $user_whois_info);
            }


            $this->view->chose("website")->render("basket", $this->data);
        }
    }