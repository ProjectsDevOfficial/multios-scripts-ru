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
            if (!Admin::isPrivilege(Config::get("privileges/USERS"))) die();
        }


        private function fetch_average($size = 1, $rate = 50)
        {
            return (float)$size * $rate / 100;

        }


        private function censored($data = '', $type = '')
        {
            $data = trim($data);
            if (!$data) return $data;

            $str_arr = Utility::str_split($data);
            $str = null;
            $size = sizeof($str_arr) - 1;

            if ($type == "phone") $lastCharC = $size - 4;
            else {
                $average = $this->fetch_average($size);
                $average_x = $average / 2;
                $firstCharC = $average_x;
                $lastCharC = $size - $average_x;
            }

            if ($type == "email") {
                $split = explode("@", $data);
                $prefix = $split[0];
                $suffix = $split[1];
                $dots = explode(".", $suffix);
                $str_arr = str_split($prefix);
                $size = sizeof($str_arr) - 1;
                $charC = $size < 5 ? $size - 3 : $size - 6;
                for ($i = 0; $i <= $size; $i++) {
                    $char = isset($str_arr[$i]) ? $str_arr[$i] : '';;
                    if ($i > $charC) $str .= '*';
                    else $str .= $char;
                }
                $str .= "@";

                $str_arr = str_split($dots[0]);
                $size = sizeof($str_arr);
                $str .= str_repeat("*", $size);
                unset($dots[0]);
                $str .= "." . implode(".", $dots);
            } else {
                $size_x = $size;

                if (isset($firstCharC)) {
                    for ($i = 0; $i <= $size; $i++) {
                        $char = isset($str_arr[$i]) ? $str_arr[$i] : '';;
                        if ($i < $firstCharC) $str .= '*';
                        else $str .= $char;
                    }
                    if (isset($lastCharC)) {
                        $str_arr = Utility::str_split($str);
                        $size = $size_x;
                        $str = null;
                    }
                }
                if (isset($lastCharC)) {
                    for ($i = 0; $i <= $size; $i++) {
                        $char = isset($str_arr[$i]) ? $str_arr[$i] : '';;
                        if ($i > $lastCharC) $str .= '*';
                        else $str .= $char;
                    }
                }
            }

            return $str == '' ? $data : $str;
        }


        private function get_rules()
        {
            return [
                'country-city'    => Bootstrap::$lang->get_cm("admin/users/document-filter-rule-country-city"),
                'age'             => Bootstrap::$lang->get_cm("admin/users/document-filter-rule-age"),
                'last-login-diff' => Bootstrap::$lang->get_cm("admin/users/document-filter-rule-last-login-diff"),
                'account-type'    => Bootstrap::$lang->get_cm("admin/users/document-filter-rule-account-type"),
                'vpn'             => Bootstrap::$lang->get_cm("admin/users/document-filter-rule-vpn"),
                'ip-subnet'       => Bootstrap::$lang->get_cm("admin/users/document-filter-rule-ip-subnet"),
            ];
        }


        private function getAddresses($id = 0, $name = null)
        {
            $lang = Config::get("general/local");
            $data = $this->model->getAddresses($id);
            if ($data) {
                $keys = array_keys($data);
                $size = sizeof($keys) - 1;
                for ($i = 0; $i <= $size; $i++) {
                    $var = $data[$keys[$i]];
                    $vname = $name != null ? $name . ' ' : null;
                    $vname .= "(" . $var["address"];

                    if (Validation::isInt($var["counti"])) {
                        $counti = AddressManager::getCountiName($var["counti"]);
                        if ($counti) {
                            $vname .= " / " . $counti;
                            $data[$keys[$i]]["counti_name"] = $counti;
                        } else
                            $vname .= " / " . $var["counti"];
                    } elseif ($var["counti"] != '' && !Validation::isInt($var["counti"])) {
                        $vname .= " / " . $var["counti"];
                    }

                    if (Validation::isInt($var["city"])) {
                        $city = AddressManager::getCityName($var["city"]);
                        if ($city) {
                            $vname .= " / " . $city;
                            $data[$keys[$i]]["city_name"] = $city;
                        } else
                            $vname .= " / " . $var["city"];
                    } elseif ($var["city"] != '' && !Validation::isInt($var["city"])) {
                        $vname .= " / " . $var["city"];
                    }

                    if ($var["country_id"] != 0) {
                        $country = AddressManager::getCountry($var["country_id"], "t2.name", $lang);
                        if ($country) $vname .= " / " . $country["name"];
                        $data[$keys[$i]]["country_name"] = $country["name"];
                    }
                    $vname .= ")";
                    if ($var["zipcode"] != '') $vname .= " - " . $var["zipcode"];
                    $data[$keys[$i]]["name"] = $vname;
                    unset($data[$keys[$i]]["owner_id"]);
                }
            }
            return $data;
        }


        private function delete_address()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");

            if (!$id) return false;

            $address = $this->model->getAddress($id);
            if (!$address) return false;

            $udata = User::getInfo($address["owner_id"], ["default_address"]);


            $delete = $this->model->delete_address($id);
            if (!$delete) return false;

            if ($udata["default_address"] == $id) {

                $addresses = $this->model->getAddresses($address["owner_id"]);
                if ($addresses) {
                    User::setInfo($address["owner_id"], ["default_address" => $addresses[0]["id"]]);
                    User::setData($address["owner_id"], ["country" => $addresses[0]["country_id"]]);
                    $this->model->set_address($addresses[0]["id"], ["detouse" => 1]);
                } else User::deleteInfo($address["owner_id"], "default_address");
            }

            $total = $this->model->totalAddress($address["owner_id"]);

            $adata = UserManager::LoginData("admin");


            User::addAction($adata["id"], "delete", "deleted-user-address", [
                'id'      => $id,
                'user_id' => $address["owner_id"],
            ]);
            echo Utility::jencode([
                'status' => "successful",
                'id'     => $id,
                'total'  => $total,
            ]);

        }


        private function add_address()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $uid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$uid) return false;

            $udata = User::getData($uid, "id,full_name", "array");
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
                    'message' => __("admin/users/error11"),
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
                    'message' => __("admin/users/success15"),
                    'id'      => $added,
                ]);
                if ($firstAddress) {
                    User::AddInfo($udata["id"], ['default_address' => $added]);
                    if ($udata["default_address"]) $this->model->set_address($udata["default_address"], ['detouse' => 0]);
                }

                if ($overwritenadoninv) User::overwrite_new_address_on_invoices($udata["id"], $added);
            } else
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/users/error10"),
                ]));

            $adata = UserManager::LoginData("admin");

            User::addAction($adata["id"], "added", "added-new-user-address", [
                'id'        => $added,
                'user_id'   => $udata["id"],
                'user_name' => $udata["full_name"],
            ]);
        }


        private function edit_address()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $uid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$uid) return false;

            $udata = User::getData($uid, "id,full_name,country", "array");
            if (!$udata) return false;

            $udata = array_merge($udata, User::getInfo($udata["id"], ["default_address"]));

            $id = (int)Filter::init("POST/id", "numbers");

            $addr = $this->model->getAddress($id);
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
                    'message' => __("admin/users/error11"),
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

            $set = $this->model->set_address($id, $set_address);

            if ($set) {
                echo Utility::jencode([
                    'status'  => "successful",
                    'message' => __("admin/users/success16"),
                    'id'      => $id,
                ]);
                if ($firstAddress) {
                    User::AddInfo($udata["id"], ['default_address' => $id]);
                    if ($udata["default_address"]) $this->model->set_address($udata["default_address"], ['detouse' => 0]);
                }

                if ($overwritenadoninv) User::overwrite_new_address_on_invoices($udata["id"], $id);

            } else
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/users/error10"),
                ]));

            $adata = UserManager::LoginData("admin");

            User::addAction($adata["id"], "alteration", "changed-user-address", [
                'id'        => $id,
                'user_id'   => $udata["id"],
                'user_name' => $udata["full_name"],
            ]);

        }


        private function ajax_dealerships()
        {

            Helper::Load("Money");
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

            $filteredList = $this->model->get_dealerships($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_dealerships_total($searches);
            $listTotal = $this->model->get_dealerships_total();

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                $situations = $situations["users"];

                if ($filteredList) {
                    $this->addData("situations", $situations);
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-user-dealership", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }

        private function ajax_affiliates()
        {

            Helper::Load("Money");
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

            $filteredList = $this->model->get_affiliates($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_affiliates_total($searches);
            $listTotal = $this->model->get_affiliates_total();

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                $situations = $situations["users"];

                if ($filteredList) {
                    $this->addData("situations", $situations);
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-user-affiliate", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }

        private function ajax_withdrawals()
        {

            Helper::Load("Money");
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
            if ($aff_id = (int)Filter::init("GET/aff_id", "numbers")) $searches["aff_id"] = $aff_id;

            $filteredList = $this->model->get_affiliate_withdrawals($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_affiliate_withdrawals_total($searches);
            if (isset($searches['word'])) unset($searches['word']);
            $listTotal = $this->model->get_affiliate_withdrawals_total($searches);

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                $situations = $situations["affiliate-withdrawal"];

                if ($filteredList) {
                    $this->addData("aff_id", $aff_id);
                    $this->addData("situations", $situations);
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-user-affiliate-withdrawals", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }

        private function ajax_blacklist()
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

            $filteredList = $this->model->get_blacklist($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_blacklist_total($searches);
            $listTotal = $this->model->get_blacklist_total();

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                $situations = $situations["users"];

                if ($filteredList) {
                    $this->addData("situations", $situations);
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-user-blacklist", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }

        private function ajax_document_filters()
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

            $filteredList = $this->model->get_document_filters($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_document_filters_total($searches);
            $listTotal = $this->model->get_document_filters_total();

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                $situations = $situations["users"];

                if ($filteredList) {
                    $this->addData("situations", $situations);
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-user-document-filters", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }

        private function ajax_document_records()
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

            $filteredList = $this->model->get_document_records($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_document_records_total($searches);
            $listTotal = $this->model->get_document_records_total();

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                $situations = $situations["user-document-record"];

                if ($filteredList) {
                    $this->addData("situations", $situations);
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-user-document-records", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }

        private function ajax_assignment_clients()
        {

            Helper::Load("Money");
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

            $filteredList = $this->model->get_affiliate_assignment_clients($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_affiliate_assignment_clients_total($searches);
            if (isset($searches['word'])) unset($searches['word']);
            $listTotal = $this->model->get_affiliate_assignment_clients_total($searches);

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                if ($filteredList) {
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-user-affiliate-assign-clients", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }

        private function ajax_assignment_orders()
        {

            Helper::Load("Money");
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

            $filteredList = $this->model->get_affiliate_assignment_orders($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_affiliate_assignment_orders_total($searches);
            if (isset($searches['word'])) unset($searches['word']);
            $listTotal = $this->model->get_affiliate_assignment_orders_total($searches);

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                if ($filteredList) {

                    $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                    $transaction_situations = $situations["affiliate-transaction"];

                    $this->addData("transaction_situations", $transaction_situations);

                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-user-affiliate-assign-orders", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }

        private function ajax_select_affiliates()
        {
            $this->takeDatas("language");
            $search = Filter::init("GET/search", "hclear");
            $data = [];
            $data2 = $this->model->select_affiliates($search);
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

        private function ajax_select_clients()
        {
            $this->takeDatas("language");
            $search = Filter::init("GET/search", "hclear");
            $data = [];
            $data2 = $this->model->select_clients($search);
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

        private function ajax_select_orders()
        {
            $this->takeDatas("language");
            $search = Filter::init("GET/search", "hclear");
            $data = [];
            $data2 = $this->model->select_orders($search);
            if ($data2) {
                foreach ($data2 as $d) {
                    $data[] = [
                        'id'   => $d["id"],
                        'text' => $d["name"] . " (#" . $d["id"] . ")",
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


        private function ajax_gdpr_requests()
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

            $filteredList = $this->model->get_gdpr_requests($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_gdpr_requests_total($searches);
            $listTotal = $this->model->get_gdpr_requests_total();

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                $situations = $situations["gdpr-requests"];

                if ($filteredList) {
                    $this->addData("situations", $situations);
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-user-gdpr-requests", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }

        private function ajax_gdpr_downloaders()
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

            $filteredList = $this->model->get_gdpr_downloaders($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_gdpr_downloaders_total($searches);
            $listTotal = $this->model->get_gdpr_downloaders_total();

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                if ($filteredList) {
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-user-gdpr-downloaders", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }

        private function ajax_gdpr_approvers()
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

            $filteredList = $this->model->get_gdpr_approvers($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_gdpr_approvers_total($searches);
            $listTotal = $this->model->get_gdpr_approvers_total();

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                if ($filteredList) {
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-user-gdpr-approvers", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function ajax_gdpr_disapprovers()
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

            $filteredList = $this->model->get_gdpr_disapprovers($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_gdpr_disapprovers_total($searches);
            $listTotal = $this->model->get_gdpr_disapprovers_total();

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {
                if ($filteredList) {
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-user-gdpr-disapprovers", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function ajax_list()
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

            if ($group = (int)Filter::init("GET/group", "numbers")) {
                $group = $this->model->get_group($group);
                if ($group) {
                    $searches["group_id"] = $group["id"];
                }
            }

            if (!Validation::isEmpty(Filter::GET("sSearch"))) $searches['word'] = Filter::init("GET/sSearch", "hclear");

            $filteredList = $this->model->get_users($searches, $orders, $start, $end);
            $filterTotal = $this->model->get_users_total($searches);
            $listTotal = $this->model->get_users_total();

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {

                $privOperation = Admin::isPrivilege("USERS_OPERATION");
                $privDelete = Admin::isPrivilege("USERS_DELETE");

                $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                $situations = $situations["users"];

                if ($filteredList) {
                    $this->addData("privOperation", $privOperation);
                    $this->addData("privDelete", $privDelete);
                    $this->addData("situations", $situations);
                    $this->addData("list", $filteredList);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-users", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function ajax_messages()
        {

            $uid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$uid) return false;

            $udata = User::getData($uid, "*", "array");
            if (!$udata) return false;

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

            $filteredList = $this->model->get_messages($udata["id"], $searches, $orders, $start, $end);
            $filterTotal = $this->model->get_messages_total($udata["id"], $searches);
            $listTotal = $this->model->get_messages_total($udata["id"]);

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
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-user-messages", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function ajax_actions()
        {

            $uid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            $admins = Filter::GET("admins") == "true" ? true : false;
            if (!$uid && !$admins) return false;

            if ($uid) {
                $udata = User::getData($uid, "*", "array");
                if (!$udata) return false;
            }

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

            $filteredList = $this->model->get_actions($admins ? false : $udata["id"], $searches, $orders, $start, $end);
            $filterTotal = $this->model->get_actions_total($admins ? false : $udata["id"], $searches);
            $listTotal = $this->model->get_actions_total($admins ? false : $udata["id"]);

            $this->takeDatas("language");

            $output = array_merge($output, [
                "sEcho"                => Filter::init("GET/sEcho", "numbers"),
                "iTotalRecords"        => $listTotal,
                "iTotalDisplayRecords" => $filterTotal,
                "aaData"               => [],
            ]);

            if ($listTotal) {

                if ($filteredList) {
                    $this->addData("list", $filteredList);
                    $this->addData("admins", $admins);
                    $output["aaData"] = $this->view->chose("admin")->render("ajax-user-actions", $this->data, false, true);
                }
            }

            echo Utility::jencode($output);
        }


        private function delete_group()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) return false;
            $group = $this->model->get_group($id);
            if (!$group) return false;

            $del = $this->model->delete_group($id);

            $adata = UserManager::LoginData("admin");

            User::addAction($adata["id"], "deleted", "deleted-user-group", [
                'id'   => $id,
                'name' => $group["name"],
            ]);

            echo Utility::jencode(['status' => "successful"]);
        }


        private function manage_groups()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $ids = Filter::POST("id");
            $names = Filter::POST("name");
            $descs = Filter::POST("description");
            $size = sizeof($names) - 1;

            for ($i = 0; $i <= $size; $i++) {
                $id = isset($ids[$i]) ? (int)Filter::numbers($ids[$i]) : 0;
                $name = isset($names[$i]) ? Filter::html_clear($names[$i]) : false;
                $desc = isset($descs[$i]) ? Filter::html_clear($descs[$i]) : false;

                if ($name) {
                    $data = ['name' => $name, 'description' => $desc];
                    if ($id) $this->model->set_group($id, $data);
                    else $this->model->insert_group($data);
                }
            }


            $adata = UserManager::LoginData("admin");

            User::addAction($adata["id"], "alteration", "changed-user-group");

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/users/success1"),
                'redirect' => $this->AdminCRLink("users"),
            ]);
        }


        private function getCities()
        {
            $country = (int)Filter::init("POST/country", "numbers");
            if (!$country) return false;
            $data = AddressManager::getCities($country);

            if ($data) {
                $result = [
                    'status' => "successful",
                    'data'   => $data,
                ];
            } else $result["status"] = "none";

            echo Utility::jencode($result);
        }


        private function getCounties()
        {
            $city = (int)Filter::init("POST/city", "numbers");
            if (!$city) return false;
            $data = AddressManager::getCounties($city);

            if ($data) {
                $result = [
                    'status' => "successful",
                    'data'   => $data,
                ];
            } else $result["status"] = "none";

            echo Utility::jencode($result);
        }


        private function add_new_user()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $kind = Filter::init("POST/kind", "letters");

            $full_name = Filter::init("POST/full_name", "hclear");
            $full_name = Utility::substr($full_name, 0, 255);
            $full_name = Utility::ucfirst_space($full_name, ___("package/charset-code"));
            $email = Filter::init("POST/email", "email");
            $gsm = Filter::init("POST/gsm", "numbers");
            $landlinep = Filter::init("POST/landline_phone", "numbers");
            $identity = Filter::init("POST/identity", "identity");
            $birthday = Filter::init("POST/birthday", "numbers", "\/");
            $company_name = Filter::init("POST/company_name", "hclear");
            $company_taxnu = Filter::init("POST/company_tax_number", "letters_numbers", "-");
            $company_taxoff = Filter::init("POST/company_tax_office", "letters");
            $enotifications = (int)Filter::init("POST/email_notifications", "numbers");
            $snotifications = (int)Filter::init("POST/sms_notifications", "numbers");
            $status = Filter::init("POST/status", "letters");
            $lang = Filter::init("POST/lang", "route");
            $tkt_restricted = (int)Filter::init("POST/ticket_restricted", "numbers");
            $tkt_blocked = (int)Filter::init("POST/ticket_blocked", "numbers");
            $taxation = (int)Filter::init("POST/taxation", "numbers");
            $never_suspend = (int)Filter::init("POST/never_suspend", "numbers");
            $never_cancel = (int)Filter::init("POST/never_cancel", "numbers");
            $group_id = (int)Filter::init("POST/group_id", "numbers");
            $currency = (int)Filter::init("POST/currency", "numbers");
            $password = Filter::init("POST/password", "password");

            $country = Filter::init("POST/country", "numbers");
            $city = Filter::init("POST/city", "hclear");
            $counti = Filter::init("POST/counti", "hclear");
            $address = Filter::init("POST/address", "hclear");
            $zipcode = substr(Filter::init("POST/zipcode", "hclear"), 0, 20);
            $notification = Filter::init("POST/notification", "numbers");

            if(!$country) $country = AddressManager::get_id_with_cc(Config::get("general/country"));



            $set_datas = [];
            $set_infos = [];

            if ($kind == "individual" || $kind == "corporate") $set_infos["kind"] = $kind;

            if ($kind == "corporate") {
                if ($company_name) $set_infos["company_name"] = $company_name;
                if ($company_taxnu) $set_infos["company_tax_number"] = $company_taxnu;
                if ($company_taxoff) $set_infos["company_tax_office"] = $company_taxoff;
            }

            if (Validation::isEmpty($full_name))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='full_name']",
                    'message' => __("admin/users/error1"),
                ]));

            $smash = Filter::name_smash($full_name);
            $name = $smash["first"];
            $surname = $smash["last"];
            $set_datas["name"] = $name;
            $set_datas["surname"] = $surname;
            $set_datas["full_name"] = $full_name;

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

            if (User::email_check($email, "member"))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='email']",
                    'message' => __("admin/users/error4"),
                ]));

            $set_datas["email"] = $email;
            $set_infos["verified-email"] = $email;

            if (!Validation::isEmpty($landlinep)) {
                if (Config::get("options/sign/up/landline-phone/checker") && User::landlinep_check($landlinep))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='landline_phone']",
                        'message' => __("admin/users/error5"),
                    ]));
                $set_infos["landline_phone"] = $landlinep;
            }

            if (strlen($gsm) >= 10) {
                if (!Validation::isPhone($gsm))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "#gsm",
                        'message' => __("admin/users/error6"),
                    ]));

                $gsm_parse = Filter::phone_smash($gsm);
                $phone = $gsm;
                $gsm_cc = $gsm_parse["cc"];
                $gsm = $gsm_parse["number"];

                if (Config::get("options/sign/up/gsm/checker") && User::gsm_check($gsm, $gsm_cc, "member"))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "#gsm",
                        'message' => __("admin/users/error7"),
                    ]));

                $set_infos["gsm_cc"] = $gsm_cc;
                $set_infos["gsm"] = $gsm;
                $set_infos["phone"] = $phone;
                $set_infos["verified-gsm"] = $phone;

            } else
                $phone = '';


            if ($birthday && Validation::isDate($birthday)) $set_infos["birthday"] = $birthday;

            if ($identity) $set_infos["identity"] = $identity;

            if (Validation::isEmpty($password))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='password']",
                    'message' => __("admin/users/error8"),
                ]));

            if (Utility::strlen($password) < $min_length = Config::get("options/password-length"))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='password']",
                    'message' => __("admin/users/error9", ['{length}' => $min_length]),
                ]));

            $set_datas["password"] = User::_crypt("member", $password, "encrypt", '-O@cb6Dxvarhc+Ghp}1eW~Y0yYfrfI#RBWdl');

            $set_datas["type"] = "member";
            $set_datas["group_id"] = $group_id;
            $set_datas["status"] = $status;
            $set_datas["lang"] = $lang;
            $set_datas["country"] = $country;
            $set_datas["currency"] = $currency;
            $set_datas["balance_currency"] = $currency;
            $set_datas["creation_time"] = DateManager::Now();
            $set_datas["last_login_time"] = DateManager::Now();
            $set_datas["ip"] = UserManager::GetIP();

            $set_infos["email_notifications"] = $enotifications;
            $set_infos["sms_notifications"] = $snotifications;
            if ($tkt_restricted) $set_infos["ticket_restricted"] = $tkt_restricted;
            if ($tkt_blocked) $set_infos["ticket_blocked"] = $tkt_blocked;
            if ($taxation) $set_infos["taxation"] = 0;
            if ($never_suspend) $set_infos["never_suspend"] = 1;
            if ($never_cancel) $set_infos["never_cancel"] = 1;

            $adata = UserManager::LoginData("admin");

            $set_infos["created_by"] = $adata["id"];

            $brand = View::is_brand();
            if ($brand) {
                $account_limit = $brand["account_limit"];
                if ($account_limit) {
                    $total_accounts = $this->model->db->select("COUNT(id) AS total")->from("users")->where("type", "=", "member");
                    $total_accounts = $total_accounts->build() ? $total_accounts->getObject()->total : 0;
                    if ($account_limit <= $total_accounts) die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("admin/users/error10") . " #ERROR: Account limit exceeded.",
                    ]));
                }
            }


            $h_ClientDetailsValidation = Hook::run("ClientDetailsValidation", [
                'source'           => "admin",
                'name'             => $name,
                'surname'          => $surname,
                'full_name'        => $full_name,
                'email'            => $email,
                'password'         => $password,
                'lang'             => $lang,
                'currency'         => $currency,
                'balance_currency' => $currency,
                'country'          => $country,
            ]);

            if ($h_ClientDetailsValidation) {
                foreach ($h_ClientDetailsValidation as $item) {
                    if (isset($item["error"]) && $item["error"])
                        die(Utility::jencode([
                            'type'    => "information",
                            'status'  => "error",
                            'message' => $item["error"],
                        ]));
                }
            }

            $insert = $this->model->insert($set_datas);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/users/error10"),
                ]));

            User::setInfo($insert, $set_infos);

            if (!Validation::isEmpty($country) && !Validation::isEmpty($city) && !Validation::isEmpty($counti) && !Validation::isEmpty($address)) {
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
                    'owner_id'           => $insert,
                    'country_id'         => $country,
                    'city'               => $city,
                    'counti'             => $counti,
                    'address'            => $address,
                    'zipcode'            => $zipcode,
                    'detouse'            => 1,
                ]);
                User::AddInfo($insert, ['default_address' => $added]);
            }

            User::addAction($adata["id"], "added", "added-new-user", [
                'id'   => $insert,
                'name' => $full_name,
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/users/success2"),
                'redirect' => $this->AdminCRLink("users"),
            ]);

            if ($notification) {
                Helper::Load(["Notification"]);
                Notification::welcome($insert);
            }

            $client_data = array_merge((array)User::getData($insert,
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
                ], "array"), User::getInfo($insert,
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
            $client_data["address"] = AddressManager::getAddress(0, $insert);
            $client_data['source'] = "admin";
            $client_data['password'] = $password;

            Hook::run("ClientCreated", $client_data);

        }


        private function add_blacklist()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            $reason = strip_tags(Filter::init("POST/reason"), "\n");

            if (!$id) return false;

            $data = User::getData($id, ['id', 'full_name', 'email', 'phone', 'blacklist', 'ip'], 'assoc');
            if (!$data) return false;

            $data = array_merge($data, User::getInfo($id, ['identity']));

            $a_data = UserManager::LoginData("admin");

            $apply = User::setBlackList($data, 'add', $reason, $a_data["id"]);

            if (gettype($apply) !== 'boolean')
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => $apply,
                ]));

            User::addAction($a_data["id"], 'alteration', 'User successfully added to blacklist', ['user_id' => $id]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/success23"),
            ]);
        }

        private function delete_blacklist()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            if (!$id) return false;

            $data = User::getData($id, ['id', 'full_name', 'email', 'phone', 'blacklist', 'ip'], 'assoc');
            if (!$data) return false;

            $data = array_merge($data, User::getInfo($id, ['identity']));

            $apply = User::setBlackList($data, 'remove');

            if (gettype($apply) !== 'boolean')
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => $apply,
                ]));


            $a_data = UserManager::LoginData("admin");

            User::addAction($a_data["id"], 'alteration', 'User successfully removed from blacklist', ['user_id' => $id]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/success22"),
            ]);
        }


        private function verified_email()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $uid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$uid) return false;

            $udata = User::getData($uid, "id,email,full_name", "array");
            if (!$udata) return false;

            User::setInfo($udata["id"], ['verified-email' => $udata["email"]]);

            $adata = UserManager::LoginData("admin");

            User::addAction($adata["id"], "alteration", "verified-user-email", [
                'user_id'   => $udata["id"],
                'user_name' => $udata["full_name"],
            ]);

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

            Hook::run("ClientEmailVerificationCompleted", $client_data);


            Utility::redirect($this->AdminCRLink("users-2", ["detail", $udata["id"]]));

        }

        private function verified_gsm()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $uid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$uid) return false;

            $udata = User::getData($uid, "*", "array");
            if (!$udata) return false;

            $udata = array_merge($udata, User::getInfo($udata["id"], ["phone"]));
            if (!$udata["phone"]) return false;

            User::setInfo($udata["id"], ['verified-gsm' => $udata["phone"]]);

            $adata = UserManager::LoginData("admin");

            User::addAction($adata["id"], "alteration", "verified-user-gsm", [
                'user_id'   => $udata["id"],
                'user_name' => $udata["full_name"],
            ]);

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

            Hook::run("ClientSMSVerificationCompleted", $client_data);

            Utility::redirect($this->AdminCRLink("users-2", ["detail", $udata["id"]]));

        }


        private function edit_user_dp()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Notification", "Money"]);

            $dp_status = (int)Filter::init("POST/dp_status", "numbers");
            $dp_status = $dp_status ? "active" : "inactive";
            $dp_rqmincdtmnt = Filter::init("POST/dp_require_min_credit_amount", "amount");
            $dp_rqmincdtcid = (int)Filter::init("POST/dp_require_min_credit_cid", "numbers");
            $dp_rqmincdtmnt = Money::deformatter($dp_rqmincdtmnt, $dp_rqmincdtcid);
            $dp_rqmindctmnt = Filter::init("POST/dp_require_min_discount_amount", "amount");
            $dp_rqmindctcid = (int)Filter::init("POST/dp_require_min_discount_cid", "numbers");
            $dp_rqmindctmnt = Money::deformatter($dp_rqmindctmnt, $dp_rqmindctcid);
            $dp_onlyctpaid = Filter::init("POST/only_credit_paid", "numbers");
            $dp_discounts = Filter::POST("dp_discounts");

            $uid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$uid) return false;

            $udata = User::getData($uid, "*", "array");
            if (!$udata) return false;

            $infos = User::getInfo($udata["id"], [
                'identity_required',
                'identity_checker',
                'birthday_required',
                'birthday_adult_verify',
                'identity',
                'company_name',
                'company_tax_number',
                'company_tax_office',
                'kind',
                'birthday',
                'gsm_cc',
                'gsm',
                'phone',
                'landline_phone',
                'email_notifications',
                'sms_notifications',
                'ticket_restricted',
                'ticket_blocked',
                'taxation',
                'verified-email',
                'verified-gsm',
                'dealership',
                'security_question',
                'security_question_answer',
                'force-document-verification-filters',
                'block-proxy-usage',
                'block-payment-gateways',
                'never_suspend',
                'never_cancel',
            ]);

            $udata = array_merge($udata, $infos);


            $set_infos = [];
            $del_infos = [];

            if ($dp_discounts && is_array($dp_discounts)) {
                $rates = [];
                foreach ($dp_discounts as $k => $v) {
                    if (!is_array($v)) continue;
                    $count = sizeof($v['from']);
                    $from_s = isset($v["from"]) ? $v["from"] : [];
                    $to_s = isset($v["to"]) ? $v["to"] : [];
                    $rate_s = isset($v["rate"]) ? $v["rate"] : [];
                    if ($count) {
                        $count -= 1;
                        for ($i = 0; $i <= $count; $i++) {
                            $from = (int)Filter::numbers(isset($from_s[$i]) ? $from_s[$i] : 0);
                            $to = (int)Filter::numbers(isset($to_s[$i]) ? $to_s[$i] : 0);
                            $rate = Filter::amount(isset($rate_s[$i]) ? $rate_s[$i] : 0);
                            $rate = str_replace(",", ".", $rate);
                            $rate = round($rate, 2);

                            //if(Config::get("options/dealership/activation") == 'auto' && $from < 2) continue;

                            if ($rate > 0.00) {
                                $rates[$k][] = [
                                    'from' => $from,
                                    'to'   => $to,
                                    'rate' => $rate,
                                ];
                            }
                        }
                    }
                }
                $dp_discounts = $rates;
            }

            $odelarship = $udata["dealership"] ? Utility::jdecode($udata["dealership"], true) : [];
            $dealership = [];
            $dealership["status"] = $dp_status;
            if ($dp_rqmincdtmnt > 0.00) {
                $dealership["require_min_credit_amount"] = $dp_rqmincdtmnt;
                $dealership["require_min_credit_cid"] = $dp_rqmincdtcid;
            }

            if ($dp_rqmindctmnt > 0.00) {
                $dealership["require_min_discount_amount"] = $dp_rqmindctmnt;
                $dealership["require_min_discount_cid"] = $dp_rqmindctcid;
            }
            if (strlen($dp_onlyctpaid) > 0) $dealership["only_credit_paid"] = (int)$dp_onlyctpaid;
            $dealership["discounts"] = $dp_discounts ? $dp_discounts : [];

            if ((!isset($odelarship["status"]) && $dp_status == "active") || (isset($odelarship["status"]) && $dp_status == "active" && $odelarship["status"] != $dp_status) && !isset($odelarship['activation_time']))
                $dealership['activation_time'] = DateManager::Now();

            $set_infos["dealership"] = Utility::jencode($dealership);


            if ($set_infos) User::setInfo($udata["id"], $set_infos);
            if ($del_infos) User::deleteInfo($udata["id"], $del_infos);


            if ((!isset($odelarship["status"]) && $dp_status == "active") || (isset($odelarship["status"]) && $dp_status == "active" && $odelarship["status"] != $dp_status))
                Notification::dealership_has_been_activated($udata["id"]);


            $u_data = UserManager::LoginData("admin");

            User::addAction($u_data["id"], 'alteration', "User reseller settings has been saved");

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/success21"),
            ]);

        }


        private function edit_user()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $uid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$uid) return false;

            $udata = User::getData($uid, "*", "array");
            if (!$udata) return false;

            $infos = User::getInfo($udata["id"], [
                'identity_required',
                'identity_checker',
                'birthday_required',
                'birthday_adult_verify',
                'identity',
                'company_name',
                'company_tax_number',
                'company_tax_office',
                'kind',
                'birthday',
                'gsm_cc',
                'gsm',
                'phone',
                'landline_phone',
                'email_notifications',
                'sms_notifications',
                'ticket_restricted',
                'ticket_blocked',
                'taxation',
                'verified-email',
                'verified-gsm',
                'dealership',
                'security_question',
                'security_question_answer',
                'force-document-verification-filters',
                'block-proxy-usage',
                'block-payment-gateways',
                'never_suspend',
                'never_cancel',
                'exempt-proxy-check',
            ]);

            $udata = array_merge($udata, $infos);

            if ($udata["security_question"])
                $udata["security_question"] = Crypt::decode($udata["security_question"], Config::get("crypt/user"));

            if ($udata["security_question_answer"])
                $udata["security_question_answer"] = Crypt::decode($udata["security_question_answer"], Config::get("crypt/user"));

            Helper::Load(["Money"]);

            $kind = Filter::init("POST/kind", "letters");

            $full_name = Filter::init("POST/full_name", "hclear");

            $full_name = Utility::substr($full_name, 0, 255);
            $full_name = Utility::ucfirst_space($full_name, ___("package/charset-code"));
            $email = Filter::init("POST/email", "email");
            $gsm = Filter::init("POST/gsm", "numbers");
            $landlinep = Filter::init("POST/landline_phone", "numbers");
            $identity = Filter::init("POST/identity", "identity");
            $birthday = Filter::init("POST/birthday", "numbers", "\/");
            $company_name = Filter::init("POST/company_name", "hclear");
            $company_taxnu = Filter::init("POST/company_tax_number", "letters_numbers", "-");
            $company_taxoff = Filter::init("POST/company_tax_office", "hclear");
            $enotifications = (int)Filter::init("POST/email_notifications", "numbers");
            $snotifications = (int)Filter::init("POST/sms_notifications", "numbers");
            $status = Filter::init("POST/status", "letters");
            $lang = Filter::init("POST/lang", "route");
            $tkt_restricted = (int)Filter::init("POST/ticket_restricted", "numbers");
            $tkt_blocked = (int)Filter::init("POST/ticket_blocked", "numbers");
            $taxation = (int)Filter::init("POST/taxation", "numbers");
            $never_suspend = (int)Filter::init("POST/never_suspend", "numbers");
            $never_cancel = (int)Filter::init("POST/never_cancel", "numbers");
            $force_identity = (int)Filter::init("POST/force_identity", "numbers");
            $group_id = (int)Filter::init("POST/group_id", "numbers");
            $currency = (int)Filter::init("POST/currency", "numbers");
            $cfields = Filter::POST("cfields");
            $password = Filter::init("POST/password", "password");
            $security_question = Filter::init("POST/security_question", "hclear");
            $security_question_answer = Filter::init("POST/security_question_answer", "hclear");

            $identity_required = (int)Filter::init("POST/sign_up_identity_required", "numbers");
            $identity_checker = (int)Filter::init("POST/sign_up_identity_checker", "numbers");
            $birthday_required = (int)Filter::init("POST/sign_birthday_required", "numbers");
            $birthday_adult_verify = (int)Filter::init("POST/sign_birthday_adult_verify", "numbers");

            $f_d_v_fs = (array)Filter::POST("force-document-verification-filters");
            $b_p_g = (array)Filter::POST("block-payment-gateways");
            $b_p_u = (int)Filter::init("POST/block-proxy-usage", "numbers");
            if ($f_d_v_fs) $f_d_v_fs = implode(",", $f_d_v_fs);
            if ($b_p_g) $b_p_g = implode(",", $b_p_g);
            $e_p_c = (int)Filter::init("POST/exempt-proxy-check", "numbers");


            $set_datas = [];
            $set_infos = [];
            $del_infos = [];

            if ($kind != $udata["kind"]) if ($kind == "individual" || $kind == "corporate") $set_infos["kind"] = $kind;

            if ($identity_required && !$udata['identity_required']) $set_infos['identity_required'] = 1;
            elseif (!$identity_required) $del_infos[] = 'identity_required';

            if ($identity_checker && !$udata['identity_checker']) $set_infos['identity_checker'] = 1;
            elseif (!$identity_checker) $del_infos[] = 'identity_checker';

            if ($birthday_required && !$udata['birthday_required']) $set_infos['birthday_required'] = 1;
            elseif (!$birthday_required) $del_infos[] = 'birthday_required';

            if ($birthday_adult_verify && !$udata['birthday_adult_verify']) $set_infos['birthday_adult_verify'] = 1;
            elseif (!$birthday_adult_verify) $del_infos[] = 'birthday_adult_verify';


            if ($company_name != $udata["company_name"]) $set_infos["company_name"] = $company_name;
            if ($company_taxnu != $udata["company_tax_number"]) $set_infos["company_tax_number"] = $company_taxnu;
            if ($company_taxoff != $udata["company_tax_office"]) $set_infos["company_tax_office"] = $company_taxoff;

            if ($security_question != $udata["security_question"])
                $set_infos["security_question"] = Crypt::encode($security_question, Config::get("crypt/user"));

            if ($security_question_answer != $udata["security_question_answer"])
                $set_infos["security_question_answer"] = Crypt::encode($security_question_answer, Config::get("crypt/user"));

            if ($f_d_v_fs && $f_d_v_fs != $udata['force-document-verification-filters'])
                $set_infos['force-document-verification-filters'] = $f_d_v_fs;
            elseif (!$f_d_v_fs && $udata['force-document-verification-filters'])
                $del_infos[] = 'force-document-verification-filters';

            if ($b_p_g && $b_p_g != $udata['block-payment-gateways'])
                $set_infos['block-payment-gateways'] = $b_p_g;
            elseif (!$b_p_g && $udata['block-payment-gateways'])
                $del_infos[] = 'block-payment-gateways';

            if ($b_p_u && $b_p_u != $udata['block-proxy-usage'])
                $set_infos['block-proxy-usage'] = $b_p_u;
            elseif (!$b_p_u && $udata['block-proxy-usage'])
                $del_infos[] = 'block-proxy-usage';


            if ($e_p_c && $e_p_c != $udata['exempt-proxy-check'])
                $set_infos['exempt-proxy-check'] = $e_p_c;
            elseif (!$e_p_c && $udata['exempt-proxy-check'])
                $del_infos[] = 'exempt-proxy-check';


            if ($full_name != $udata["full_name"]) {

                if (Validation::isEmpty($full_name))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='full_name']",
                        'message' => __("admin/users/error1"),
                    ]));

                $smash = Filter::name_smash($full_name);
                $name = $smash["first"];
                $surname = $smash["last"];
                $set_datas["name"] = $name;
                $set_datas["surname"] = $surname;
                $set_datas["full_name"] = $full_name;
            }


            if ($email != $udata["email"]) {
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

                if (User::email_check($email, "member"))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='email']",
                        'message' => __("admin/users/error4"),
                    ]));
                $set_datas["email"] = $email;
                $set_infos["verified-email"] = $email;
            }

            if ($landlinep != $udata["landline_phone"]) {
                if (Config::get("options/sign/up/landline-phone/checker") && User::landlinep_check($landlinep))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='landline_phone']",
                        'message' => __("admin/users/error5"),
                    ]));
                $set_infos["landline_phone"] = $landlinep;
            }

            if ($gsm != $udata["phone"]) {

                if (strlen($gsm) >= 10) {

                    if (!Validation::isPhone($gsm))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "#gsm",
                            'message' => __("admin/users/error6"),
                        ]));

                    $gsm_parse = Filter::phone_smash($gsm);
                    $phone = $gsm;
                    $gsm_cc = $gsm_parse["cc"];
                    $gsm = $gsm_parse["number"];

                    if (Config::get("options/sign/up/gsm/checker") && User::gsm_check($gsm, $gsm_cc, "member"))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "#gsm",
                            'message' => __("admin/users/error7"),
                        ]));

                    $set_infos["gsm_cc"] = $gsm_cc;
                    $set_infos["gsm"] = $gsm;
                    $set_infos["phone"] = $phone;
                    $set_infos["verified-gsm"] = $phone;

                } else {
                    $del_infos[] = "gsm_cc";
                    $del_infos[] = "gsm";
                    $del_infos[] = "phone";
                    $del_infos[] = "verified-gsm";
                }
            }

            if ($birthday != $udata["birthday"]) {
                if ($birthday && Validation::isDate($birthday))
                    $set_infos["birthday"] = $birthday;
                else
                    $del_infos[] = "birthday";
            }

            if ($identity != $udata["identity"]) $set_infos["identity"] = $identity;

            if ($group_id != $udata["group_id"]) $set_datas["group_id"] = $group_id;
            if ($status != $udata["status"]) $set_datas["status"] = $status;
            if ($lang != $udata["lang"]) $set_datas["lang"] = $lang;
            if ($currency != $udata["currency"]) $set_datas["currency"] = $currency;
            if ($enotifications != $udata["email_notifications"]) $set_infos["email_notifications"] = $enotifications;
            if ($snotifications != $udata["sms_notifications"]) $set_infos["sms_notifications"] = $snotifications;
            if ($tkt_restricted != $udata["ticket_restricted"]) $set_infos["ticket_restricted"] = $tkt_restricted;
            if ($tkt_blocked != $udata["ticket_blocked"]) $set_infos["ticket_blocked"] = $tkt_blocked;
            if ($taxation == 1 && $udata["taxation"] == null) $set_infos["taxation"] = 0;
            else User::deleteInfo($udata["id"], "taxation");

            if ($never_suspend) $set_infos["never_suspend"] = 1;
            else User::deleteInfo($udata["id"], "never_suspend");

            if ($never_cancel) $set_infos["never_cancel"] = 1;
            else User::deleteInfo($udata["id"], "never_cancel");

            if ($force_identity) $set_infos["force_identity"] = 1;
            else User::deleteInfo($udata["id"], "force_identity");

            if ($cfields) {
                foreach ($cfields as $k => $v) {
                    $k = (int)Filter::numbers($k);
                    $f = $this->model->get_custom_field($k);
                    if ($f) {
                        $data = User::getInfo($udata["id"], ['field_' . $k]);
                        $value = $data['field_' . $k];

                        $v = $f["type"] == "checkbox" && is_array($v) ? implode(",", $v) : $v;
                        $v = Filter::html_clear($v);
                        if ($v != $value) $set_infos['field_' . $k] = $v;
                    }
                }
            }


            if ($password) $set_datas["password"] = User::_crypt("member", $password, "encrypt", '-O@cb6Dxvarhc+Ghp}1eW~Y0yYfrfI#RBWdl');

            Helper::Load(["Notification"]);

            $client_data = array_merge((array)User::getData($uid,
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
                ], "array"), User::getInfo($uid,
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
            $client_data["address"] = AddressManager::getAddress(0, $uid);
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


            if ($set_datas) User::setData($udata["id"], $set_datas);
            if ($set_infos) User::setInfo($udata["id"], $set_infos);
            if ($del_infos) User::deleteInfo($udata["id"], $del_infos);

            if ($status == "blocked" && $udata["status"] != $status)
                Notification::account_has_been_blocked($udata["id"]);

            if ($status == "active" && $udata["status"] != $status)
                Notification::account_has_been_activated($udata["id"]);


            $client_data = array_merge((array)User::getData($uid,
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
                ], "array"), User::getInfo($uid,
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
            $client_data["address"] = AddressManager::getAddress(0, $uid);
            $client_data["source"] = "admin";
            $client_data["password"] = $password;


            if (isset($set_datas["password"])) Hook::run("ClientChangePassword", $client_data);

            Hook::run("ClientInformationModified", $client_data);


            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-user-informations", [
                'id' => $udata["id"],
            ]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/success3"),
            ]);

        }


        private function remind_unpaid_bill()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $uid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$uid) return false;

            $udata = User::getData($uid, "id", "array");
            if (!$udata) return false;

            $unpaid_bills = $this->model->get_invoices("unpaid", $udata["id"]);

            if (!$unpaid_bills)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/users/error12"),
                ]));

            Helper::Load(["Notification", "Invoices", "Orders", "Money", "Products"]);

            foreach ($unpaid_bills as $row) Notification::invoice_reminder($row["id"], $row["remaining_day"]);


            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/success4"),
            ]);

        }


        private function stored_card_remove()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error")])
                );

            $adata = UserManager::LoginData("admin");
            $uid = (int)Filter::numbers($this->params[1]);
            $udata = User::getData($uid, "id,lang", "array");
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

            $m_name = $get["module"];

            $m = Modules::Load("Payment", $m_name);

            if ($m && class_exists($m_name)) {
                $module = new $m_name();
                if (method_exists($module, 'remove_stored_card')) {
                    if (!$module->remove_stored_card($id)) {
                        Modules::save_log("Payment", $m_name, "remove_stored_card", "Card ID: " . $id . ", LN4: " . $get["ln4"], $module->error);
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
            User::addAction($adata["id"], 'alteration', 'credit-card-was-deleted', ['ln4' => $get["ln4"]]);

            echo Utility::jencode(['status' => "successful"]);
        }


        private function add_credit()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $uid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$uid) return false;

            $udata = User::getData($uid, "id,balance,balance_currency", "array");
            if (!$udata) return false;

            Helper::Load(["Money"]);

            $description = Filter::init("POST/description", "hclear");
            $type = Filter::init("POST/type", "letters");
            $amount = Filter::init("POST/amount", "amount");
            $cid = $udata["balance_currency"];
            $amount = Money::deformatter($amount, $cid);

            $add = $this->model->insert_credit([
                'user_id'     => $udata["id"],
                'description' => $description,
                'type'        => $type,
                'amount'      => $amount,
                'cid'         => $cid,
                'cdate'       => DateManager::Now(),
            ]);

            if (!$add)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/users/error10"),
                ]));

            $before_balance = round($udata["balance"], 2);

            if ($type == "up") $new_balance = $before_balance + $amount;
            if ($type == "down") $new_balance = $before_balance - $amount;

            User::setData($udata["id"], ['balance' => $new_balance]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "added", "added-user-" . $type . "-credit", [
                'id'             => $add,
                'user_id'        => $udata["id"],
                'before_balance' => Money::formatter_symbol($before_balance, $cid),
                'new_balance'    => Money::formatter_symbol($new_balance, $cid),
                'amount'         => Money::formatter_symbol($amount, $cid),
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);

        }


        private function edit_credit()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $uid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$uid) return false;

            $udata = User::getData($uid, "id,balance,balance_currency", "array");
            if (!$udata) return false;

            Helper::Load(["Money"]);

            $id = (int)Filter::init("POST/id", "numbers");

            $get = $this->model->get_credit($id);
            if (!$get) return false;

            $description = Filter::init("POST/description", "hclear");
            $type = Filter::init("POST/type", "letters");
            $amount = Filter::init("POST/amount", "amount");
            $amount = Money::deformatter($amount, $get["cid"]);


            $set = $this->model->set_credit($id, [
                'description' => $description,
                'type'        => $type,
                'amount'      => $amount,
            ]);

            if (!$set)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/users/error10"),
                ]));

            $before_balance = round($udata["balance"], 2);
            $u_curr = $udata["balance_currency"];

            $get_amount = Money::exChange($get["amount"], $get["cid"], $u_curr);

            if ($get["type"] == "up") $before_balance = ($before_balance - $get_amount);
            if ($get["type"] == "down") $before_balance = ($before_balance + $get_amount);

            $amount = Money::exChange($amount, $get["cid"], $u_curr);

            if ($type == "up") $new_balance = $before_balance + $amount;
            if ($type == "down") $new_balance = $before_balance - $amount;

            User::setData($udata["id"], ['balance' => $new_balance]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "changed-user-credit", [
                'id'            => $id,
                'user_id'       => $udata["id"],
                'before_amount' => Money::formatter_symbol($before_balance, $u_curr),
                'new_balance'   => Money::formatter_symbol($new_balance, $u_curr),
                'amount'        => Money::formatter_symbol($amount, $u_curr),
            ]);


            echo Utility::jencode([
                'status' => "successful",
            ]);

        }


        private function edit_notes()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $uid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$uid) return false;

            $udata = User::getData($uid, "id", "array");
            if (!$udata) return false;

            $notes = Filter::POST("notes");

            User::setInfo($udata["id"], ['notes' => $notes]);

            $adata = UserManager::LoginData("admin");

            User::addAction($adata["id"], "alteration", "changed-user-notes", [
                'user_id' => $udata["id"],
            ]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/success5"),
            ]);

        }


        private function delete_credit()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $uid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$uid) return false;

            $udata = User::getData($uid, "id,balance,balance_currency", "array");
            if (!$udata) return false;

            Helper::Load(["Money"]);

            $id = (int)Filter::init("POST/id", "numbers");

            $get = $this->model->get_credit($id);
            if (!$get) return false;


            $delete = $this->model->delete_credit($id);

            if (!$delete)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/users/error10"),
                ]));

            $before_balance = $udata["balance"];
            $u_curr = $udata["balance_currency"];
            $amount = Money::exChange($get["amount"], $get["cid"], $u_curr);

            if ($get["type"] == "up")
                $new_balance = ($before_balance - $amount);
            else
                $new_balance = ($before_balance + $amount);

            User::setData($udata["id"], ['balance' => $new_balance]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "deleted", "deleted-user-credit", [
                'id'            => $id,
                'user_id'       => $udata["id"],
                'before_amount' => Money::formatter_symbol($before_balance, $u_curr),
                'new_balance'   => Money::formatter_symbol($new_balance, $u_curr),
                'amount'        => Money::formatter_symbol($amount, $u_curr),
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);

        }


        private function block_user()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $uid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$uid) return false;

            $udata = User::getData($uid, "id,status,full_name", "array");
            if (!$udata) return false;
            if ($udata["status"] != "active") return false;

            User::setData($udata["id"], ['status' => "blocked"]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "has-been-blocked-user", [
                'user_id'   => $udata["id"],
                'user_name' => $udata["full_name"],
            ]);

            Helper::Load("Notification");
            Notification::account_has_been_blocked($udata["id"]);


            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/users/success6"),
                'redirect' => $this->AdminCRLink("users-2", ["detail", $udata["id"]]),
            ]);

        }


        private function unblock_user()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $uid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$uid) return false;

            $udata = User::getData($uid, "id,status,full_name", "array");
            if (!$udata) return false;
            if ($udata["status"] == "active") return false;

            User::setData($udata["id"], ['status' => "active"]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "has-been-activated-user", [
                'user_id'   => $udata["id"],
                'user_name' => $udata["full_name"],
            ]);

            Helper::Load("Notification");
            Notification::account_has_been_activated($udata["id"]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/users/success7"),
                'redirect' => $this->AdminCRLink("users-2", ["detail", $udata["id"]]),
            ]);

        }


        private function suspend_all_of_services()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $uid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$uid) return false;

            $udata = User::getData($uid, "id,status,full_name", "array");
            if (!$udata) return false;

            $udata = array_merge($udata, User::getInfo($udata["id"], ["suspend_all_of_services"]));

            if ($udata["suspend_all_of_services"]) return false;

            Helper::Load(["Orders", "Products"]);

            $orders = Orders::get_orders($udata["id"], 'active');

            if (!$orders)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/users/error13"),
                ]));

            foreach ($orders as $order) Orders::MakeOperation("suspended", $order, false, false);

            User::setInfo($udata["id"], ["suspend_all_of_services" => true]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "has-been-suspended-all-of-user-services", [
                'user_id'   => $udata["id"],
                'user_name' => $udata["full_name"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/users/success8"),
                'redirect' => $this->AdminCRLink("users-2", ["detail", $udata["id"]]),
            ]);

        }


        private function unsuspend_all_of_services()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $uid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$uid) return false;

            $udata = User::getData($uid, "id,status,full_name", "array");
            if (!$udata) return false;

            $udata = array_merge($udata, User::getInfo($udata["id"], ["suspend_all_of_services"]));

            if (!$udata["suspend_all_of_services"]) return false;

            Helper::Load(["Orders", "Products"]);

            $orders = Orders::get_orders($udata["id"], 'suspended');

            if (!$orders)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/users/error14"),
                ]));

            foreach ($orders as $order) Orders::MakeOperation("active", $order, false, false);

            User::deleteInfo($udata["id"], ["suspend_all_of_services"]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "suspended-orders-have-been-activated", [
                'user_id'   => $udata["id"],
                'user_name' => $udata["full_name"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/users/success9"),
                'redirect' => $this->AdminCRLink("users-2", ["detail", $udata["id"]]),
            ]);

        }


        private function delete_everything_about_user()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $uid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$uid) return false;

            $udata = User::getData($uid, "id,status,full_name", "array");
            if (!$udata) return false;

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

            Helper::Load(["Orders", "Products", "Invoices", "Tickets"]);

            $delete = $this->model->delete($udata["id"]);
            if (!$delete)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/users/error10"),
                ]));

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "deleted", "deleted-user", [
                'user_id'   => $udata["id"],
                'user_name' => $udata["full_name"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/users/success10"),
                'redirect' => $this->AdminCRLink("users"),
            ]);

        }


        private function reset_and_send_password()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $uid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$uid) return false;

            $udata = User::getData($uid, "id,status,full_name,secure_hash", "array");
            if (!$udata) return false;

            if (!$udata["secure_hash"]) User::setData($udata["id"], ['secure_hash' => User::secure_hash($udata["id"])]);

            Helper::Load(["Notification"]);

            Notification::forget_password($udata["id"]);

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "reminder-of-login-information", [
                'user_id'   => $udata["id"],
                'user_name' => $udata["full_name"],
            ]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/success12"),
            ]);

        }


        private function send_sms(){
            $this->takeDatas("language");

            if(DEMO_MODE)
                die(Utility::jencode([
                    'status' => "error",
                    'message' => __("website/others/demo-mode-error")
                ]));

            $uid    = isset($this->params[1]) ? (int) Filter::numbers($this->params[1]) : 0;
            if(!$uid) return false;

            $udata       = User::getData($uid,"id,status,full_name","array");
            if (!$udata) return false;

            $udata       = array_merge($udata,User::getInfo($udata["id"],["phone","gsm_cc","gsm"]));


            if(Validation::isEmpty($udata["phone"]))
                die(Utility::jencode([
                    'status' => "error",
                    'message' => __("admin/users/error16"),
                ]));

            $message      = Filter::init("POST/message");

            if(Validation::isEmpty($message))
                die(Utility::jencode([
                    'status' => "error",
                    'message' => __("admin/users/error15"),
                ]));

            Modules::Load("SMS");
            $mname  = Config::get("modules/sms");

            if (Validation::isEmpty($mname))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "There is no SMS module selected.",
                ]));

            $sms    = new $mname();
            $send   = $sms->body($message);
            $send   = $send->addNumber($udata["gsm"],$udata["gsm_cc"])->submit();
            if($send) LogManager::Sms_Log($udata["id"],"sent-sms-to-user",$sms->getTitle(),$sms->getBody(),implode(",",$sms->getNumbers()));

            if(!$send)
                die(Utility::jencode([
                    'status' => "error",
                    'message' => __("admin/users/error17",[
                        '{error}' => $sms->getError()
                    ]),
                ]));

            $adata      = UserManager::LoginData("admin");
            User::addAction($adata["id"],"alteration","sent-sms-to-user",[
                'user_id' => $udata["id"],
                'user_name' => $udata["full_name"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
                'message' => __("admin/users/success13"),
            ]);

        }


        private function send_mail()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $uid = isset($this->params[1]) ? (int)Filter::numbers($this->params[1]) : 0;
            if (!$uid) return false;

            $udata = User::getData($uid, "id,full_name", "array");
            if (!$udata) return false;

            $subject = Filter::init("POST/subject", "hclear");
            $message = Filter::init("POST/message");

            if (Validation::isEmpty($message))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/users/error15"),
                ]));

            Helper::Load(["Notification"]);

            $adata = UserManager::LoginData("admin");
            $adata = array_merge($adata, User::getInfo($adata["id"], ["signature"]));

            $send = Notification::message_from_admin([
                'subject'       => $subject,
                'user_id'       => $udata["id"],
                'signature'     => $adata["signature"],
                'admin_message' => $message,
            ]);

            if ($send != "OK")
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/users/error17", ['{error}' => print_r($send, true)]),
                ]));

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "sent-mail-to-user", [
                'user_id'   => $udata["id"],
                'user_name' => $udata["full_name"],
            ]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/success14"),
            ]);

        }


        private function delete_user()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $uid = (int)Filter::init("POST/id", "numbers");
            if (!$uid) return false;

            $udata = User::getData($uid, "id,status,full_name", "array");
            if (!$udata) return false;

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

            Helper::Load(["Registrar", "Orders"]);

            $delete = User::delete($udata["id"]);
            if (!$delete)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/users/error10"),
                ]));

            $adata = UserManager::LoginData("admin");
            User::addAction($adata["id"], "deleted", "deleted-user", [
                'user_id'   => $udata["id"],
                'user_name' => $udata["full_name"],
            ]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/success10"),
            ]);
        }


        private function add_document_filter()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $name = Filter::init("POST/name", "hclear");
            $status = (int)Filter::init("POST/status", "numbers");
            $rules = Filter::POST("rules");
            $fields = Filter::POST("fields");

            if ($status) $status = "active";
            else $status = "inactive";

            if (Validation::isEmpty($name))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='name']",
                    'message' => __("admin/users/error19"),
                ]));

            $new_rules = [];
            $new_fields = [];

            if ($rules && is_array($rules)) {
                foreach ($rules as $k => $rule) {
                    $f_type = $rule["type"];
                    if ($f_type) $new_rules[$k] = $rule;
                }
            }

            if ($fields && is_array($fields)) {
                foreach ($fields as $l_key => $l_fields) {
                    foreach ($l_fields as $k => $field) {
                        $f_name = $field["name"];
                        if (Validation::isEmpty($f_name))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "input[name='fields[" . $l_key . "][" . $k . "][name]']",
                                'message' => __("admin/users/error19"),
                            ]));
                        if (isset($field["options"]) && $field["options"]) {
                            $opts = $field["options"];
                            $field["options"] = [];
                            foreach ($opts as $opt) if (!Validation::isEmpty($opt)) $field["options"][] = $opt;
                        }
                        if (!isset($new_fields[$l_key])) $new_fields[$l_key] = [];
                        $new_fields[$l_key][$k] = $field;
                    }
                }
            }

            $local_l_key = Config::get("general/local");

            if (!isset($new_fields[$local_l_key]) || sizeof($new_fields[$local_l_key]) < 1)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/users/error20"),
                ]));

            $new_rules = $new_rules ? Utility::jencode($new_rules) : '';
            $new_fields = $new_fields ? Utility::jencode($new_fields) : '';

            $create = $this->model->add_document_filter([
                'name'   => $name,
                'status' => $status,
                'rules'  => $new_rules,
                'fields' => $new_fields,
            ]);

            if (!$create)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Can't create the filter",
                ]));

            $u_data = UserManager::LoginData("admin");

            User::addAction($u_data["id"], 'added', "Added a new client document verification filter", ['id' => $create]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/users/success17"),
                'redirect' => $this->AdminCRLink("users-2", ["document-verification", "filters"]),
            ]);

        }

        private function delete_document_filter()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");

            $delete = $this->model->delete_document_filter($id);

            if (!$delete)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Can't delete the document filter",
                ]));


            $u_data = UserManager::LoginData("admin");

            User::addAction($u_data["id"], 'delete', "client document verification filter deleted", ['id' => $id]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/success18"),
            ]);

        }

        private function edit_document_filter()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("GET/id", "numbers");

            $filter = $this->model->get_document_filter($id);
            if (!$filter)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown document filter ID",
                ]));

            $name = Filter::init("POST/name", "hclear");
            $status = (int)Filter::init("POST/status", "numbers");
            $rules = Filter::POST("rules");
            $fields = Filter::POST("fields");

            if ($status) $status = "active";
            else $status = "inactive";

            if (Validation::isEmpty($name))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='name']",
                    'message' => __("admin/users/error19"),
                ]));

            $new_rules = [];
            $new_fields = [];

            if ($rules && is_array($rules)) {
                foreach ($rules as $k => $rule) {
                    $f_type = $rule["type"];
                    if ($f_type) $new_rules[$k] = $rule;
                }
            }

            if ($fields && is_array($fields)) {
                foreach ($fields as $l_key => $l_fields) {
                    foreach ($l_fields as $k => $field) {
                        $f_name = $field["name"];
                        if (Validation::isEmpty($f_name))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "input[name='fields[" . $l_key . "][" . $k . "][name]']",
                                'message' => __("admin/users/error19"),
                            ]));
                        if (isset($field["options"]) && $field["options"]) {
                            $opts = $field["options"];
                            $field["options"] = [];
                            foreach ($opts as $opt) if (!Validation::isEmpty($opt)) $field["options"][] = $opt;
                        }
                        if (!isset($new_fields[$l_key])) $new_fields[$l_key] = [];
                        $new_fields[$l_key][$k] = $field;
                    }
                }
            }

            $local_l_key = Config::get("general/local");

            if (!isset($new_fields[$local_l_key]) || sizeof($new_fields[$local_l_key]) < 1)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/users/error20"),
                ]));

            $new_rules = $new_rules ? Utility::jencode($new_rules) : '';
            $new_fields = $new_fields ? Utility::jencode($new_fields) : '';

            $edit = $this->model->edit_document_filter($id, [
                'name'   => $name,
                'status' => $status,
                'rules'  => $new_rules,
                'fields' => $new_fields,
            ]);

            if (!$edit)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Can't update the filter",
                ]));

            $u_data = UserManager::LoginData("admin");

            User::addAction($u_data["id"], 'alteration', "client document verification filter updated", ['id' => $id]);

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/users/success19"),
                'redirect' => $this->AdminCRLink("users-2", ["document-verification", "filters"]),
            ]);

        }

        private function edit_document_record()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $records = (array)Filter::POST("records");

            if ($records) {
                foreach ($records as $id => $record) {
                    $status = $record["status"];
                    $msg = Filter::html_clear($record["status_msg"]);
                    $this->model->set_document_record($id, [
                        'status'     => $status,
                        'status_msg' => $msg,
                        'unread'     => 1,
                    ]);
                }
            }


            $u_data = UserManager::LoginData("admin");

            User::addAction($u_data["id"], 'alteration', "client document verification record updated");

            echo Utility::jencode([
                'status'   => "successful",
                'message'  => __("admin/users/success20"),
                'redirect' => $this->AdminCRLink("users-2", ["document-verification", "records"]),
            ]);

        }


        private function update_affiliate_settings()
        {
            $this->takeDatas("language");

            Helper::Load("Money");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $status = (bool)(int)Filter::init("POST/status", "numbers");
            $input_1 = (bool)(int)Filter::init("POST/view-without-membership", "numbers");
            $input_2 = (bool)(int)Filter::init("POST/show-p-commission-rates", "numbers");
            $input_3 = Filter::init("POST/commission-period", "letters");
            $input_4 = Filter::init("POST/commission-delay", "numbers");
            $input_5 = Filter::init("POST/redirect", "hclear");
            $min_p_a = Filter::init("POST/min-payment-amount", "amount");
            $min_p_a = Money::deformatter($min_p_a, Config::get("general/currency"));
            $rate = Filter::init("POST/rate", "amount");
            $rate = str_replace(",", ".", $rate);
            if ($rate == '') $rate = 0;
            $p_gs = Filter::init("POST/payment-gateways");
            $content = Filter::init("POST/content");
            $c_dn = (int)Filter::init("POST/cookie-duration", "numbers");

            $config_sets = [];
            $config_sets2 = [];
            $gateways_lid = (int)Filter::init("POST/gateways-lid", "numbers");


            if ($status != Config::get("options/affiliate/status"))
                $config_sets['options']['affiliate']['status'] = $status;

            if ($input_1 != Config::get("options/affiliate/view-without-membership"))
                $config_sets['options']['affiliate']['view-without-membership'] = $input_1;

            if ($input_2 != Config::get("options/affiliate/show-p-commission-rates"))
                $config_sets['options']['affiliate']['show-p-commission-rates'] = $input_2;

            if ($input_3 != Config::get("options/affiliate/commission-period"))
                $config_sets['options']['affiliate']['commission-period'] = $input_3;

            if ($input_4 != Config::get("options/affiliate/commission-delay"))
                $config_sets['options']['affiliate']['commission-delay'] = $input_4;

            if ($input_5 != Config::get("options/affiliate/redirect"))
                $config_sets['options']['affiliate']['redirect'] = $input_5;

            if ($min_p_a != Config::get("options/affiliate/min-payment-amount"))
                $config_sets['options']['affiliate']['min-payment-amount'] = $min_p_a;

            if ($rate != Config::get("options/affiliate/rate"))
                $config_sets['options']['affiliate']['rate'] = $rate;

            if ($p_gs != Config::get("options/affiliate/payment-gateways")) {
                $config_sets['options']['affiliate']['payment-gateways'] = '';
                $config_sets2['options']['affiliate']['payment-gateways'] = $p_gs;
            }

            if ($gateways_lid != Config::get("options/affiliate/payment-gateways-lid"))
                $config_sets['options']['affiliate']['payment-gateways-lid'] = $gateways_lid;

            if ($content != Config::get("options/affiliate/content")) {
                $config_sets['options']['affiliate']['content'] = '';
                $config_sets2['options']['affiliate']['content'] = $content;
            }

            if ($c_dn != Config::get("options/affiliate/cookie-duration"))
                $config_sets['options']['affiliate']['cookie-duration'] = $c_dn;


            if ($config_sets) {
                if (isset($config_sets["options"])) {
                    $options_result = Config::set("options", $config_sets["options"]);
                    if (isset($config_sets2["options"]))
                        $options_result = Config::set("options", $config_sets2["options"]);
                    $var_export = Utility::array_export($options_result, ['pwith' => true]);
                    FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                }
            }

            $u_data = UserManager::LoginData("admin");

            User::addAction($u_data["id"], 'alteration', "Affiliate settings has been saved");

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/success21"),
            ]);
        }

        private function update_withdrawal()
        {
            $this->takeDatas("language");

            Helper::Load("Money");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");
            $status = Filter::init("POST/status", "letters");
            $status_msg = Filter::init("POST/status_msg", "hclear");

            $withdrawal = $this->model->get_affiliate_withdrawal($id);

            if (!$withdrawal) return false;


            if ($withdrawal['status'] == $status && $withdrawal['status_msg'] == $status_msg) return false;


            $set_data = [
                'status'     => $status,
                'status_msg' => $status_msg,
            ];

            if ($withdrawal['status'] != $status) {
                if ($status == 'completed')
                    $set_data['completed_time'] = DateManager::Now();
                else
                    $set_data['completed_time'] = DateManager::zero();
            }

            $this->model->set_affiliate_withdrawal($id, $set_data);

            if ($withdrawal['status'] != 'completed' && $status == 'completed') {
                $aff = User::get_affiliate(0, $withdrawal["affiliate_id"]);
                if ($aff) {
                    $balance = $aff['balance'];
                    $balance -= $withdrawal['amount'];
                    if ($balance < 0.00) $balance = 0;
                    User::set_affiliate($aff['id'], ['balance' => $balance]);
                }
            } elseif ($withdrawal['status'] == 'completed' && $status != 'completed') {
                $aff = User::get_affiliate(0, $withdrawal["affiliate_id"]);
                if ($aff) {
                    $balance = $aff['balance'];
                    $balance += $withdrawal['amount'];
                    User::set_affiliate($aff['id'], ['balance' => $balance]);
                }
            }

            $u_data = UserManager::LoginData("admin");

            User::addAction($u_data["id"], 'alteration', "Withdrawal Request Successfully Updated", [
                'id'     => $id,
                'amount' => Money::formatter_symbol($withdrawal['amount'], $withdrawal['currency']),
            ]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/success24"),
            ]);
        }

        private function delete_withdrawal()
        {
            $this->takeDatas("language");

            Helper::Load("Money");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");

            $withdrawal = $this->model->get_affiliate_withdrawal($id);

            if (!$withdrawal) return false;


            $this->model->delete_affiliate_withdrawal($id);

            if ($withdrawal['status'] == 'completed') {
                $aff = User::get_affiliate(0, $withdrawal["affiliate_id"]);
                if ($aff) {
                    $balance = $aff['balance'];
                    $balance += $withdrawal['amount'];
                    User::set_affiliate($aff['id'], ['balance' => $balance]);
                }
            }

            $u_data = UserManager::LoginData("admin");

            User::addAction($u_data["id"], 'alteration', "Withdrawal Request Successfully Deleted", [
                'id'     => $id,
                'amount' => Money::formatter_symbol($withdrawal['amount'], $withdrawal['currency']),
            ]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/success25"),
            ]);
        }

        private function delete_assigned_client()
        {
            $this->takeDatas("language");

            Helper::Load("Money");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");

            User::setData($id, ['aff_id' => 0]);


            $u_data = UserManager::LoginData("admin");

            User::addAction($u_data["id"], 'alteration', "The client is affiliate link has been removed", [
                'user_id' => $id,
            ]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/success27"),
            ]);
        }

        private function delete_assigned_order()
        {
            $this->takeDatas("language");

            Helper::Load("Money");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");

            $this->model->delete_affiliate_transaction($id);


            $u_data = UserManager::LoginData("admin");

            User::addAction($u_data["id"], 'alteration', "The client is affiliate transaction has been removed", [
                'transaction' => $id,
            ]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/success27"),
            ]);
        }

        private function update_affiliate()
        {
            $this->takeDatas("language");

            Helper::Load("Money");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");

            $aff = User::get_affiliate(0, $id);

            if (!$aff) return false;

            $disabled = (int)Filter::init("POST/disabled", "numbers");
            $disabled_note = Filter::init("POST/disabled_note");
            $c_period = Filter::init("POST/commission-period", "letters");
            $c_value = Filter::init("POST/rate", "amount");
            $balance = Filter::init("POST/balance", "amount");
            $currency = Filter::init("POST/currency", "numbers");
            $c_value = str_replace(",", ".", $c_value);
            $balance = Money::deformatter($balance, $currency);


            $sets = [
                'disabled_note' => $disabled_note,
            ];

            if ($disabled != $aff['disabled']) $sets['disabled'] = $disabled;
            if ($c_period != $aff['commission_period']) $sets['commission_period'] = $c_period;
            if ($c_value != $aff['commission_value']) $sets['commission_value'] = $c_value;
            if ($balance != $aff['balance']) $sets['balance'] = $balance;
            if ($currency != $aff["currency"]) $sets["currency"] = $currency;

            if ($sets) {
                User::set_affiliate($id, $sets);

                $u_data = UserManager::LoginData("admin");

                User::addAction($u_data["id"], 'alteration', "Client Affiliate Settings Successfully Updated", [
                    'id' => $id,
                ]);

            }

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/success26"),
            ]);
        }

        private function activate_affiliate()
        {
            $this->takeDatas("language");

            Helper::Load("Money");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = isset($this->params[1]) ? $this->params[1] : 0;

            $aff = User::get_affiliate($id);
            if ($aff) return false;

            $currency = User::getData($id, 'currency')->currency;

            User::insert_affiliate([
                'date'     => DateManager::Now(),
                'owner_id' => $id,
                'currency' => $currency,
            ]);

            Utility::redirect($this->AdminCRLink("users-2", ["detail", $id]) . "?tab=affiliate");
        }

        private function assign_client_affiliate()
        {
            $this->takeDatas("language");

            Helper::Load("Money");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $aff_id = (int)Filter::init("POST/affiliate_id", "numbers");
            $cli_id = (int)Filter::init("POST/client_id", "numbers");


            if (!$cli_id || !$aff_id) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/users/error21"),
                ]);
                return false;
            }

            $aff = User::get_affiliate(0, $aff_id);
            if (!$aff) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Affiliate user not found",
                ]);
                return false;
            }

            User::setData($cli_id, ['aff_id' => $aff_id]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/success27"),
            ]);

            $udata = UserManager::LoginData("admin");

            User::addAction($udata["id"], 'added', "Affiliate assigned to client by staff", [
                'affiliate_id' => $aff_id,
                'user_id'      => $cli_id,
            ]);


            return true;
        }

        private function assign_order_affiliate()
        {
            $this->takeDatas("language");

            Helper::Load("Money");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $aff_id = (int)Filter::init("POST/affiliate_id", "numbers");
            $order_id = (int)Filter::init("POST/order_id", "numbers");


            if (!$aff_id || !$order_id) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("admin/users/error21"),
                ]);
                return false;
            }

            $aff = User::get_affiliate(0, $aff_id);
            if (!$aff) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Affiliate user not found",
                ]);
                return false;
            }

            Helper::Load(["Orders", "Products"]);

            $order = Orders::get($order_id);

            if (!$order) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Order not found",
                ]);
                return false;
            }

            $product = Products::get($order["type"], $order["product_id"]);


            User::affiliate_apply_transaction('sale', $order, $product, $aff_id);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/success27"),
            ]);

            $udata = UserManager::LoginData("admin");

            User::addAction($udata["id"], 'added', "Affiliate assigned to order by staff", [
                'affiliate_id' => $aff_id,
                'order_id'     => $order_id,
            ]);


            return true;
        }

        private function update_dealership_settings()
        {
            $this->takeDatas("language");

            Helper::Load("Money");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $status = (bool)(int)Filter::init("POST/status", "numbers");
            $input_1 = (bool)(int)Filter::init("POST/api", "numbers");
            $input_2 = Filter::init("POST/activation", "letters");
            $input_3 = (bool)(int)Filter::init("POST/view-without-membership", "numbers");
            $input_4 = Filter::init("POST/dp_require_min_credit_amount", "amount");
            $input_5 = Filter::init("POST/dp_require_min_discount_amount", "amount");
            $input_6 = (bool)(int)Filter::init("POST/only_credit_paid", "numbers");
            $input_7 = (int)Filter::init("POST/dp_require_min_credit_cid", "numbers");
            $input_8 = (int)Filter::init("POST/dp_require_min_discount_cid", "numbers");
            $input_4 = Money::deformatter($input_4, $input_7);
            $input_5 = Money::deformatter($input_5, $input_8);

            $rates = [];
            $p_rates = Filter::POST("rates");
            $config_sets = [];


            if ($p_rates) {
                foreach ($p_rates as $k => $v) {
                    if (!is_array($v)) continue;
                    $count = sizeof($v['from']);
                    $from_s = isset($v["from"]) ? $v["from"] : [];
                    $to_s = isset($v["to"]) ? $v["to"] : [];
                    $rate_s = isset($v["rate"]) ? $v["rate"] : [];
                    if ($count) {
                        $count -= 1;
                        for ($i = 0; $i <= $count; $i++) {
                            $from = (int)Filter::numbers(isset($from_s[$i]) ? $from_s[$i] : 0);
                            $to = (int)Filter::numbers(isset($to_s[$i]) ? $to_s[$i] : 0);
                            $rate = Filter::amount(isset($rate_s[$i]) ? $rate_s[$i] : 0);
                            $rate = (float)str_replace(",", ".", $rate);
                            $rate = round($rate, 2);

                            if ($input_2 == 'auto' && $from < 2) continue;

                            if ($rate > 0.0) {
                                $rates[$k][] = [
                                    'from' => $from,
                                    'to'   => $to,
                                    'rate' => $rate,
                                ];
                            }
                        }
                    }
                }
            }


            if ($status != Config::get("options/dealership/status"))
                $config_sets['options']['dealership']['status'] = $status;

            if ($input_1 != Config::get("options/dealership/api"))
                $config_sets['options']['dealership']['api'] = $input_1;

            if ($input_2 != Config::get("options/dealership/activation"))
                $config_sets['options']['dealership']['activation'] = $input_2;

            if ($input_3 != Config::get("options/dealership/view-without-membership"))
                $config_sets['options']['dealership']['view-without-membership'] = $input_3;

            if ($input_4 != Config::get("options/dealership/require_min_credit_amount"))
                $config_sets['options']['dealership']['require_min_credit_amount'] = $input_4;

            if ($input_5 != Config::get("options/dealership/require_min_discount_amount"))
                $config_sets['options']['dealership']['require_min_discount_amount'] = $input_5;

            if ($input_6 != Config::get("options/dealership/only_credit_paid"))
                $config_sets['options']['dealership']['only_credit_paid'] = $input_6;

            if ($input_7 != Config::get("options/dealership/require_min_credit_cid"))
                $config_sets['options']['dealership']['require_min_credit_cid'] = $input_7;

            if ($input_8 != Config::get("options/dealership/require_min_discount_cid"))
                $config_sets['options']['dealership']['require_min_discount_cid'] = $input_8;

            if ($rates != Config::get("options/dealership/rates")) {
                $config_sets['options']['dealership']['rates'] = '';
                $config_sets2['options']['dealership']['rates'] = $rates;
            }


            if ($config_sets) {
                if (isset($config_sets["options"])) {
                    $options_result = Config::set("options", $config_sets["options"]);
                    if (isset($config_sets2["options"]))
                        $options_result = Config::set("options", $config_sets2["options"]);
                    $var_export = Utility::array_export($options_result, ['pwith' => true]);
                    FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                }
            }

            $u_data = UserManager::LoginData("admin");

            User::addAction($u_data["id"], 'alteration', "Dealership settings has been saved");

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/success21"),
            ]);
        }

        private function save_gdpr_settings()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $status = (bool)(int)Filter::init("POST/status", "numbers");
            $required = (bool)(int)Filter::init("POST/required", "numbers");


            $config_sets = [];

            if ($status != Config::get("options/gdpr-status"))
                $config_sets['options']['gdpr-status'] = $status;

            if ($required != Config::get("options/gdpr-required"))
                $config_sets['options']['gdpr-required'] = $required;

            if ($config_sets) {
                if (isset($config_sets["options"])) {
                    $options_result = Config::set("options", $config_sets["options"]);
                    $var_export = Utility::array_export($options_result, ['pwith' => true]);
                    FileManager::file_write(CONFIG_DIR . "options.php", $var_export);
                }
            }

            $u_data = UserManager::LoginData("admin");

            User::addAction($u_data["id"], 'alteration', "Personal Data (GDPR) settings has been saved");

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/success21"),
            ]);
        }

        private function delete_gdpr_request()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $id = (int)Filter::init("POST/id", "numbers");

            if (!$id) return false;

            $delete = $this->model->delete_gdpr_request($id);

            if (!$delete) {
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown gdpr request id",
                ]));
            }

            $u_data = UserManager::LoginData("admin");

            User::addAction($u_data["id"], 'alteration', "Personal Data (GDPR) request has been deleted");

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/users/gdpr-tx7"),
            ]);
        }

        private function update_gdpr_request()
        {
            $this->takeDatas("language");
            if(DEMO_MODE)
                die(Utility::jencode([
                    'status' => "error",
                    'message' => __("website/others/demo-mode-error")
                ]));

            $id     = (int) Filter::init("GET/id","numbers");

            if(!$id) return false;

            $rq             = $this->model->gdpr_request_detail($id);
            $status         = Filter::init("POST/status","letters_numbers");
            $remove_type    = Filter::init("POST/remove_type","route");
            $status_note    = Filter::init("POST/status_note","hclear");
            $notification   = Filter::init("POST/notification","rnumbers");
            $blacklist      = Filter::init("POST/blacklist","rnumbers");

            $admin_data     = UserManager::LoginData("admin");


            $get_user_data = User::getData($rq["user_id"],[
                'id',
                'name',
                'surname',
                'full_name',
                'company_name',
                'email',
                'phone',
                'blacklist',
                'ip',
            ],"assoc");

            $get_user_data  = array_merge($get_user_data,User::getInfo($rq["user_id"],[
                'company_name',
                'company_tax_office',
                'company_tax_number',
                'identity',
                'gsm',
                'phone',
                'verified-email',
            ]));

            Helper::Load("Notification");


            if($status && $status != $rq["status_admin"])
            {
                if($status == "remove" || $status == "anonymize" || $status == "destroy")
                {
                    if($notification) Notification::approved_gdpr_request($id);

                    if($rq["blacklist"] != $blacklist)
                        User::setBlackList($get_user_data,($blacklist ? 'add' : 'remove'),'GDPR',$admin_data["id"],false);

                    if($remove_type == "block_access")
                    {
                        User::setData($rq["user_id"],[
                            'email' => "BLOCK*".$get_user_data["email"]."*BLOCK",
                            'phone' => "BLOCK*".$get_user_data["phone"]."*BLOCK",
                        ]);
                    }
                    elseif($remove_type == "identifying_data")
                    {
                        $full_name              = $this->censored($get_user_data["full_name"]);
                        $name_smash             = Filter::name_smash($full_name);

                        $censored_data = [
                            'data' => [
                                'name'          => $name_smash['first'],
                                'surname'       => $name_smash['last'],
                                'full_name'     => $this->censored($get_user_data["full_name"]),
                                'company_name'  => $this->censored($get_user_data["company_name"]),
                                'email'         => $this->censored($get_user_data["email"],"email"),
                                'phone'         => $this->censored($get_user_data["phone"],"phone"),
                            ],
                            'info' => [],
                            'addresses' => [],
                        ];

                        if($get_user_data["company_name"])
                            $censored_data["info"]["company_name"] = $this->censored($get_user_data["company_name"]);

                        if($get_user_data["company_tax_office"])
                            $censored_data["info"]["company_tax_office"] = $this->censored($get_user_data["company_tax_office"]);

                        if($get_user_data["company_tax_number"])
                            $censored_data["info"]["company_tax_number"] = $this->censored($get_user_data["company_tax_number"]);

                        if($get_user_data["identity"])
                            $censored_data["info"]["identity"] = $this->censored($get_user_data["identity"]);

                        if($get_user_data["phone"])
                            $censored_data["info"]["phone"] = $this->censored($get_user_data["phone"],"phone");

                        if($get_user_data["gsm"])
                            $censored_data["info"]["gsm"] = $this->censored($get_user_data["gsm"],"phone");

                        if($get_user_data["verified-email"])
                            $censored_data["info"]["verified-email"] = $this->censored($get_user_data["verified-email"],"email");



                        $addresses      = $this->model->getAddresses($rq["user_id"]);

                        if($addresses)
                        {
                            foreach($addresses AS $adr)
                            {
                                $full_name2              = $adr["full_name"] ? $this->censored($adr["full_name"]) : '';
                                $name_smash2             = $full_name2 ? Filter::name_smash($full_name2) : [];
                                $censored_data["addresses"][$adr["id"]] = [
                                    'city' => Validation::isInt($adr["city"]) ? $adr["city"] : $this->censored($adr["city"]),
                                    'counti' => Validation::isInt($adr["counti"]) ? $adr["counti"] : $this->censored($adr["counti"]),
                                    'address' => Validation::isInt($adr["address"]) ? $adr["address"] : $this->censored($adr["address"]),
                                    'zipcode' => $this->censored($adr["zipcode"]),
                                    'company_name' => $adr["company_name"] ? $this->censored($adr["company_name"]) : '',
                                    'company_tax_number' => $adr["company_tax_number"] ? $this->censored($adr["company_tax_number"]) : '',
                                    'company_tax_office' => $adr["company_tax_office"] ? $this->censored($adr["company_tax_office"]) : '',
                                    'full_name' => $full_name2,
                                    'name'      => $name_smash2['first'] ?? '',
                                    'surname'   => $name_smash2['last'] ?? '',
                                    'email'     => $adr["email"] ? $this->censored($adr["email"],"email") : '',
                                    'phone'     => $adr["phone"] ? $this->censored($adr["phone"],"phone") : '',
                                ];
                            }
                        }

                        if($censored_data["data"]) User::setData($rq["user_id"],$censored_data["data"]);
                        if($censored_data["info"]) User::setInfo($rq["user_id"],$censored_data["info"]);
                        if($censored_data["addresses"])
                        {
                            foreach($censored_data["addresses"] AS $adr_id => $adr_data) $this->model->set_address($adr_id,$adr_data);
                        }
                    }
                    elseif($remove_type == "all")
                    {
                        $delete         = User::delete($rq["user_id"]);

                        if(!$delete)
                        {
                            die(Utility::jencode([
                                'status' => "error",
                                'message' => __("admin/users/gdpr-tx35"),
                            ]));
                        }
                    }
                }
            }


            $update_data        = [
                'status_note'       => $status_note,
                'remove_type'       => $remove_type,
                'blacklist'         => $blacklist,
            ];

            if($status)
            {
                $update_data["status_admin"] = $status;
                $update_data["status"] = $status == "cancelled" ? "cancelled" : "approved";
            }

            $this->model->update_gdpr_request($update_data,$id);


            $u_data         = UserManager::LoginData("admin");
            User::addAction($u_data["id"],"alteration","Updated GDPR removal request",[
                'id' => $id,
            ]);

            if($notification && $status == "cancelled") Notification::declined_gdpr_request($id);



            echo Utility::jencode([
                'status'            => "successful",
                'message'           => __("admin/users/gdpr-tx34"),
            ]);

        }


        private function operationMain($operation)
        {
            if ($operation == "actions.json") return $this->ajax_actions();
            if ($operation == "messages.json") return $this->ajax_messages();
            if ($operation == "list.json") return $this->ajax_list();
            if ($operation == "getCities") return $this->getCities();
            if ($operation == "getCounties") return $this->getCounties();

            if ($operation == "add_blacklist" && Admin::isPrivilege(["USERS_BLACKLIST"])) return $this->add_blacklist();
            if ($operation == "delete_blacklist" && Admin::isPrivilege(["USERS_BLACKLIST"])) return $this->delete_blacklist();

            if ($operation == "verified_email" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->verified_email();
            if ($operation == "verified_gsm" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->verified_gsm();
            if ($operation == "add_new_user" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->add_new_user();
            if ($operation == "edit_user" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->edit_user();
            if ($operation == "delete_group" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->delete_group();
            if ($operation == "manage_groups" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->manage_groups();
            if ($operation == "delete_address" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->delete_address();
            if ($operation == "add_address" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->add_address();
            if ($operation == "edit_address" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->edit_address();
            if ($operation == "remind_unpaid_bill" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->remind_unpaid_bill();
            if ($operation == "stored_card_remove" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->stored_card_remove();
            if ($operation == "add_credit" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->add_credit();
            if ($operation == "edit_credit" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->edit_credit();
            if ($operation == "delete_credit" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->delete_credit();
            if ($operation == "edit_notes" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->edit_notes();
            if ($operation == "block_user" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->block_user();
            if ($operation == "unblock_user" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->unblock_user();
            if ($operation == "suspend_all_of_services" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->suspend_all_of_services();
            if ($operation == "unsuspend_all_of_services" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->unsuspend_all_of_services();
            if ($operation == "delete_everything_about_user" && Admin::isPrivilege(["USERS_DELETE"])) return $this->delete_everything_about_user();
            if ($operation == "edit_user_dp" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->edit_user_dp();
            if ($operation == "delete_user" && Admin::isPrivilege(["USERS_DELETE"])) return $this->delete_user();
            if ($operation == "reset_and_send_password" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->reset_and_send_password();
            if ($operation == "send_sms" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->send_sms();
            if ($operation == "send_mail" && Admin::isPrivilege(["USERS_OPERATION"])) return $this->send_mail();
            if ($operation == "add_document_filter" && Admin::isPrivilege(["USERS_DOCUMENT_VERIFICATION"])) return $this->add_document_filter();
            if ($operation == "delete_document_filter" && Admin::isPrivilege(["USERS_DOCUMENT_VERIFICATION"])) return $this->delete_document_filter();
            if ($operation == "edit_document_filter" && Admin::isPrivilege(["USERS_DOCUMENT_VERIFICATION"])) return $this->edit_document_filter();
            if ($operation == "edit_document_record" && Admin::isPrivilege(["USERS_DOCUMENT_VERIFICATION"])) return $this->edit_document_record();
            if ($operation == "update_affiliate_settings" && Admin::isPrivilege(["USERS_AFFILIATE"])) return $this->update_affiliate_settings();
            if ($operation == "update_withdrawal" && Admin::isPrivilege(["USERS_AFFILIATE"])) return $this->update_withdrawal();
            if ($operation == "delete_withdrawal" && Admin::isPrivilege(["USERS_AFFILIATE"])) return $this->delete_withdrawal();
            if ($operation == "delete_assigned_client" && Admin::isPrivilege(["USERS_AFFILIATE"])) return $this->delete_assigned_client();
            if ($operation == "delete_assigned_order" && Admin::isPrivilege(["USERS_AFFILIATE"])) return $this->delete_assigned_order();
            if ($operation == "update_affiliate" && Admin::isPrivilege(["USERS_AFFILIATE"])) return $this->update_affiliate();
            if ($operation == "activate_affiliate" && Admin::isPrivilege(["USERS_AFFILIATE"])) return $this->activate_affiliate();
            if ($operation == "assign_client_affiliate" && Admin::isPrivilege(["USERS_AFFILIATE"])) return $this->assign_client_affiliate();
            if ($operation == "assign_order_affiliate" && Admin::isPrivilege(["USERS_AFFILIATE"])) return $this->assign_order_affiliate();
            if ($operation == "update_dealership_settings" && Admin::isPrivilege(["USERS_DEALERSHIP"])) return $this->update_dealership_settings();
            if ($operation == "save_gdpr_settings" && Admin::isPrivilege(["USERS_GDPR"])) return $this->save_gdpr_settings();
            if ($operation == "delete_gdpr_request" && Admin::isPrivilege(["USERS_GDPR"])) return $this->delete_gdpr_request();
            if ($operation == "update_gdpr_request" && Admin::isPrivilege(["USERS_GDPR"])) return $this->update_gdpr_request();

            echo "Not found operation: " . $operation;
        }


        private function pageMain($name = '')
        {
            if ($name == "create") return $this->create_detail();
            elseif ($name == "detail" && $id = isset($this->params[1]) ? Filter::numbers($this->params[1]) : 0)
                return $this->detail($id);
            elseif ($name == "document-verification") return $this->document_verification();
            elseif (!$name) return $this->listing();
            elseif ($name == "gdpr" && Admin::isPrivilege(["USERS_GDPR"])) return $this->gdpr();
            elseif ($name == "blacklist" && Admin::isPrivilege(["USERS_BLACKLIST"])) return $this->blacklist();
            elseif ($name == "affiliate" && Admin::isPrivilege(["USERS_AFFILIATE"])) return $this->affiliate();
            elseif ($name == "dealership" && Admin::isPrivilege(["USERS_DEALERSHIP"])) return $this->dealership();
            echo "Not found main: " . $name;
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
                'controller' => $this->AdminCRLink("users-1", ["create"]),
            ];

            $this->addData("links", $links);

            $meta = __("admin/users/meta-create");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("users"),
                'title' => __("admin/users/breadcrumb-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/users/breadcrumb-create"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("groups", $this->model->groups());

            $this->addData("countryList", AddressManager::getCountryList(Config::get("general/local")));

            $this->addData("cfields", $this->model->get_custom_fields(Config::get("general/local")));


            Helper::Load(["Money"]);

            $this->view->chose("admin")->render("add-user", $this->data);
        }


        private function detail($id = 0)
        {

            $user = User::getData($id, "*", "array");
            if (!$user) return false;

            $infos = User::getInfo($user["id"], [
                'identity_required',
                'identity_checker',
                'birthday_required',
                'birthday_adult_verify',
                'identity',
                'company_name',
                'company_tax_number',
                'company_tax_office',
                'kind',
                'birthday',
                'gsm_cc',
                'gsm',
                'phone',
                'landline_phone',
                'email_notifications',
                'sms_notifications',
                'ticket_restricted',
                'ticket_blocked',
                'taxation',
                'dealership',
                'notes',
                'suspend_all_of_services',
                'verified-email',
                'verified-gsm',
                'security_question',
                'security_question_answer',
                'force-document-verification-filters',
                'block-proxy-usage',
                'block-payment-gateways',
                'never_suspend',
                'never_cancel',
                'force_identity',
                'exempt-proxy-check',
                'contract2',
            ]);

            $user = array_merge($user, $infos);

            if ($user["security_question"])
                $user["security_question"] = Crypt::decode($user["security_question"], Config::get("crypt/user"));

            if ($user["security_question_answer"])
                $user["security_question_answer"] = Crypt::decode($user["security_question_answer"], Config::get("crypt/user"));

            $user["dealership"] = $user["dealership"] ? Utility::jdecode($user["dealership"], true) : [];

            if (Config::get("options/blacklist/status"))
                $this->addData("blacklist_blocking", $blacklist_blocking = User::checkBlackList($user));

            $user["blacklist"] = User::getData($user["id"], "blacklist")->blacklist;
            $user = array_merge($user, User::getInfo($user["id"], [
                'blacklist_reason',
                'blacklist_time',
                'blacklist_by_admin',
            ]));

            $aff = User::get_affiliate($user['id']);


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

            $s_group = Filter::init("GET/service_group", "route");

            $links = [
                'controller' => $this->AdminCRLink("users-2", ["detail", $id]),
            ];

            $links["add-bill"] = $this->AdminCRLink("invoices-1", ["create"]) . "?user_id=" . $user["id"];
            $links["add-order"] = $this->AdminCRLink("orders-1", ["create"]) . "?user_id=" . $user["id"];
            $links["add-ticket"] = $this->AdminCRLink("tickets-1", ["create"]) . "?user_id=" . $user["id"];
            $links["invoices"] = $this->AdminCRLink("invoices");
            $links["orders"] = $this->AdminCRLink("orders");
            $links["tickets"] = $this->AdminCRLink("tickets");
            $links["ajax-invoices"] = $links["invoices"] . "?operation=ajax-bills.json&from=user&id=" . $user["id"];
            $links["ajax-orders"] = $links["orders"] . "?operation=ajax-list&from=user&id=" . $user["id"] . "&group=" . $s_group;
            $links["ajax-tickets"] = $links["tickets"] . "?operation=requests.json&from=user&id=" . $user["id"];
            $links["ajax-messages"] = $links["controller"] . "?operation=messages.json";
            $links["ajax-actions"] = $links["controller"] . "?operation=actions.json";
            if ($aff) $links["ajax-withdrawals"] = $this->AdminCRLink("users-1", ["affiliate"]) . "?list=withdrawals&aff_id=" . ($aff["id"] ? $aff["id"] : 0);


            if ($aff) $links['tracking'] = $this->CRLink("affiliate-link", [$aff['id']]);

            $this->addData("links", $links);

            $meta = [
                'title' => __("admin/users/meta-detail", ['{name}' => $user["full_name"]]),
            ];

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("users"),
                'title' => __("admin/users/breadcrumb-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/users/breadcrumb-detail", ['{name}' => $user["full_name"]]),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("groups", $this->model->groups());

            $this->addData("countryList", AddressManager::getCountryList(Config::get("general/local")));

            $cfields = $this->model->get_custom_fields($user["lang"]);
            if ($cfields) {
                $new_fields = [];
                foreach ($cfields as $cfield) $new_fields[] = "field_" . $cfield["id"];
                $user = array_merge($user, User::getInfo($user["id"], $new_fields));
            }

            $this->addData("cfields", $cfields);

            $this->addData("user", $user);

            $special_groups = AdminModel::getSpecialGroups();

            $this->addData("special_groups", $special_groups);

            $name = ($user["kind"] == "corporate") ? $user["company_name"] : $user["full_name"];

            $this->addData("acAddresses", AddressManager::getAddressesList($user["id"], $name));

            $this->addData("loginLogs", $this->model->get_login_logs($user["id"]));

            if ($user["group_id"]) {
                $group = $this->model->get_group($user["group_id"]);
                if ($group) $this->addData("group", $group);
            }

            Helper::Load(["Money"]);

            $statistics = ['total_trade_volume' => 0];
            $statistics["invoices"] = $this->model->get_invoice_statistics($user["id"]);

            $order_groups = __("admin/orders/product-groups");
            if ($special_groups) foreach ($special_groups as $row) $order_groups[$row["id"]] = $row["title"];
            foreach ($order_groups as $k => $name) {
                $statistics["orders"][$k] = [
                    'name'     => $name,
                    'amount'   => 0,
                    'quantity' => 0,
                ];

                if (Validation::isInt($k)) {
                    $type = "special";
                    $type_id = $k;
                } else {
                    $type = $k;
                    $type_id = 0;
                }
                $get_statistic = $this->model->get_order_statistics($user["id"], $type, $type_id);
                if ($get_statistic) {
                    $statistics["orders"][$k]["amount"] += $get_statistic["amount"];
                    $statistics["orders"][$k]["quantity"] += $get_statistic["quantity"];
                    $statistics["total_trade_volume"] += $get_statistic["amount"];
                }
            }

            $bring = Filter::init("GET/bring", "route");

            if ($bring == "login") {
                $password = $user["password"];
                User::setData($user["id"], ['ip' => UserManager::GetIP()]);
                UserManager::Logout("member");
                UserManager::Login("member", $user["id"], $password, $user["lang"], '-O@cb6Dxvarhc+Ghp}1eW~Y0yYfrfI#RBWdl');
                header("Location: " . $this->CRLink("my-account", false, $user["lang"]));
                die();
            }

            $this->addData("statistics", $statistics);

            $this->addData("creditLogs", $this->model->get_credits($user["id"]));

            $this->addData("document_verification_filters", $this->model->get_document_filters());

            $modules = Modules::Load("Payment", false, true);
            $p_gateways = [];
            if ($modules) {
                foreach ($modules as $key => $val)
                    $p_gateways[$key] = ($val["lang"]["name"] ?? ($val["config"]["meta"]["name"] ?? $key));
            }
            $this->addData("payment_gateways", $p_gateways);

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
            $this->addData("service_group", $s_group);

            $this->addData("aff", $aff);


            Helper::Load("Products");

            $lang = Bootstrap::$lang->clang;
            $n_products = Products::get_groups_products($lang);

            $this->addData("products", $n_products);

            if ($aff) {
                $pending_balance_today = User::affiliate_pending_balance($aff['id'], true);
                $pending_balance_total = User::affiliate_pending_balance($aff['id']);
                $total_balance = $aff['balance'];
                $total_withdrawals = User::affiliate_withdrawals_total($aff['id']);
                $references_today = User::affiliate_references_total($aff['id'], true);
                $references_total = User::affiliate_references_total($aff['id']);
                $hits_today = User::affiliate_hits_total($aff['id'], true);
                $hits_total = User::affiliate_hits_total($aff['id']);
                $transaction_list = User::affiliate_transactions($aff['id']);
                $referrer_list = User::affiliate_referrers($aff['id']);


                $this->addData("pending_balance_today", Money::formatter_symbol($pending_balance_today, $aff['currency']));
                $this->addData("pending_balance_total", Money::formatter_symbol($pending_balance_total, $aff['currency']));
                $this->addData("total_balance", Money::formatter_symbol($total_balance, $aff['currency']));
                $this->addData("total_withdrawals", Money::formatter_symbol($total_withdrawals, $aff['currency']));
                $this->addData("references_today", $references_today);
                $this->addData("references_total", $references_total);
                $this->addData("hits_today", $hits_today);
                $this->addData("hits_total", $hits_total);

                $this->addData("transaction_list", $transaction_list);
                $this->addData("referrer_list", $referrer_list);

                $situations = $this->view->chose("admin")->render("common-needs", false, true, true);
                $transaction_situations = $situations["affiliate-transaction"];

                $this->addData("transaction_situations", $transaction_situations);
            }

            $c_s_m = Config::get("modules/card-storage-module");

            $stored_cards = Models::$init->db->select()->from("users_stored_cards");
            $stored_cards->where("user_id", "=", $user["id"]);
            $stored_cards->order_by("id DESC");
            $stored_cards = $stored_cards->build() ? $stored_cards->fetch_assoc() : [];


            $this->addData("stored_cards", $stored_cards);
            $this->addData("c_s_m", $c_s_m);


            $this->view->chose("admin")->render("detail-user", $this->data);
        }


        private function listing()
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
                'controller' => $this->AdminCRLink("users"),
                'create'     => $this->AdminCRLink("users-1", ["create"]),
            ];
            $links["ajax"] = $links["controller"] . "?operation=list.json";

            $this->addData("links", $links);

            $meta = __("admin/users/meta-list");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/users/breadcrumb-list"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            if ($group = (int)Filter::init("GET/group", "numbers")) {
                $group = $this->model->get_group($group);
                if ($group) {
                    $this->addData("group", $group);
                }
            }

            $this->addData("groups", $this->model->groups());

            $this->view->chose("admin")->render("users", $this->data);
        }


        private function document_verification()
        {
            $page = isset($this->params[1]) ? $this->params[1] : false;
            if ($page == "filters") return $this->document_filters();
            if ($page == "records") return $this->document_records();
        }

        private function document_filters()
        {

            $page = Filter::init("GET/page", "letters");
            $list = Filter::init("GET/list", "letters");

            if ($page == "add") return $this->add_document_filter_page();
            elseif ($page == "edit") return $this->edit_document_filter_page();
            if ($list) return $this->ajax_document_filters();

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
                'controller' => $this->AdminCRLink("users-2", ["document-verification", "filters"]),
            ];
            $links["add"] = $links["controller"] . "?page=add";
            $links["ajax"] = $links["controller"] . "?list=true";

            $this->addData("links", $links);

            $meta = __("admin/users/meta-document-filter-list");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => $this->AdminCRLink("users"),
                    'title' => __("admin/index/menu-users"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/index/menu-users-document-verification"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => null,
                'title' => __("admin/users/breadcrumb-document-filter-list"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->view->chose("admin")->render("user-document-filters", $this->data);
        }

        private function document_records()
        {

            $detail = Filter::init("GET/detail", "numbers");
            $list = Filter::init("GET/list", "letters");

            if ($detail) return $this->detail_document_record_page($detail);
            if ($list) return $this->ajax_document_records();

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
                'controller' => $this->AdminCRLink("users-2", ["document-verification", "records"]),
            ];
            $links["ajax"] = $links["controller"] . "?list=true";

            $this->addData("links", $links);

            $meta = __("admin/users/meta-document-record-list");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => $this->AdminCRLink("users"),
                    'title' => __("admin/index/menu-users"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/index/menu-users-document-verification"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => null,
                'title' => __("admin/users/breadcrumb-document-record-list"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->view->chose("admin")->render("user-document-records", $this->data);
        }

        private function add_document_filter_page()
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
                'controller' => $this->AdminCRLink("users-2", ["document-verification", "filters"]) . "?page=add",
            ];

            $this->addData("links", $links);

            $meta = __("admin/users/meta-add-document-filter");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => $this->AdminCRLink("users"),
                    'title' => __("admin/index/menu-users"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/index/menu-users-document-verification"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("users-2", ["document-verification", "filters"]),
                'title' => __("admin/users/breadcrumb-document-filter-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/users/breadcrumb-add-document-filter"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("_rules", $this->get_rules());

            $this->view->chose("admin")->render("add-user-document-filter", $this->data);
        }

        private function edit_document_filter_page()
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

            $id = (int)Filter::init("GET/id", "numbers");
            if (!$id) return false;

            $filter = $this->model->get_document_filter($id);

            if (!$filter) return false;

            $links = [
                'controller' => $this->AdminCRLink("users-2", ["document-verification", "filters"]) . "?page=edit&id=" . $id,
            ];

            $this->addData("links", $links);

            $meta = __("admin/users/meta-edit-document-filter");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => $this->AdminCRLink("users"),
                    'title' => __("admin/index/menu-users"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/index/menu-users-document-verification"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("users-2", ["document-verification", "filters"]),
                'title' => __("admin/users/breadcrumb-document-filter-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/users/breadcrumb-edit-document-filter"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("_rules", $this->get_rules());

            $this->addData("filter", $filter);

            $this->view->chose("admin")->render("edit-user-document-filter", $this->data);
        }

        private function detail_document_record_page($id = 0)
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

            $user = User::getData($id, ['id', 'full_name'], 'assoc');


            $links = [
                'controller' => $this->AdminCRLink("users-2", ["document-verification", "records"]) . "?detail=" . $id,
            ];

            $this->addData("links", $links);

            $meta = ['title' => __("admin/users/meta-detail-document-record", [
                '{user_name}' => $user["full_name"],
            ])];

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => $this->AdminCRLink("users"),
                    'title' => __("admin/index/menu-users"),
                ],
                [
                    'link'  => null,
                    'title' => __("admin/index/menu-users-document-verification"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("users-2", ["document-verification", "records"]),
                'title' => __("admin/users/breadcrumb-document-record-list"),
            ]);

            array_push($breadcrumbs, [
                'link'  => false,
                'title' => __("admin/users/breadcrumb-detail-document-record", ['{user_name}' => $user["full_name"]]),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("user", $user);

            $records = $this->model->document_records($id);

            $this->addData("records", $records);

            $this->view->chose("admin")->render("detail-user-document-record", $this->data);
        }

        private function blacklist()
        {
            $list = Filter::init("GET/list", "letters");

            if ($list) return $this->ajax_blacklist();

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
                'controller' => $this->AdminCRLink("users-1", ["blacklist"]),
            ];
            $links["ajax"] = $links["controller"] . "?list=true";

            $links["settings"] = $this->AdminCRLink("settings-p", ["fraud-protection"]);

            $this->addData("links", $links);

            $meta = __("admin/users/meta-blacklist");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
                [
                    'link'  => $this->AdminCRLink("users"),
                    'title' => __("admin/index/menu-users"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => null,
                'title' => __("admin/users/breadcrumb-blacklist"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->view->chose("admin")->render("user-blacklist", $this->data);
        }

        private function affiliate()
        {
            $list = Filter::init("GET/list", "route");
            $param = isset($this->params[1]) ? $this->params[1] : false;

            if ($param == 'settings') return $this->affiliate_settings();

            if ($list == "affiliates") return $this->ajax_affiliates();
            if ($list == "withdrawals") return $this->ajax_withdrawals();
            elseif ($list == "assignment-clients") return $this->ajax_assignment_clients();
            elseif ($list == "assignment-orders") return $this->ajax_assignment_orders();
            elseif ($list == "select-affiliates") return $this->ajax_select_affiliates();
            elseif ($list == "select-clients") return $this->ajax_select_clients();
            elseif ($list == "select-orders") return $this->ajax_select_orders();

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
                'controller' => $this->AdminCRLink("users-1", ["affiliate"]),
                'settings'   => $this->AdminCRLink("users-2", ["affiliate", "settings"]),
            ];
            $links["ajax-affiliates"] = $links["controller"] . "?list=affiliates";
            $links["ajax-withdrawals"] = $links["controller"] . "?list=withdrawals";
            $links["ajax-assignment-clients"] = $links["controller"] . "?list=assignment-clients";
            $links["ajax-assignment-orders"] = $links["controller"] . "?list=assignment-orders";
            $links["select-affiliates"] = $links["controller"] . "?list=select-affiliates";
            $links["select-clients"] = $links["controller"] . "?list=select-clients";
            $links["select-orders"] = $links["controller"] . "?list=select-orders";

            $this->addData("links", $links);

            $meta = __("admin/users/meta-affiliate");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => null,
                'title' => __("admin/users/breadcrumb-affiliate"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->view->chose("admin")->render("user-affiliate", $this->data);
        }

        private function affiliate_settings()
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
                'controller' => $this->AdminCRLink("users-2", ["affiliate", "settings"]),
            ];

            $this->addData("links", $links);

            $meta = __("admin/users/meta-affiliate-settings");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("users-1", ["affiliate"]),
                'title' => __("admin/users/breadcrumb-affiliate"),
            ]);

            array_push($breadcrumbs, [
                'link'  => null,
                'title' => __("admin/users/breadcrumb-affiliate-settings"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->view->chose("admin")->render("user-affiliate-settings", $this->data);
        }

        private function dealership()
        {
            $list = Filter::init("GET/list", "letters");
            $param = isset($this->params[1]) ? $this->params[1] : false;

            if ($param == 'settings') return $this->dealership_settings();

            if ($list) return $this->ajax_dealerships();

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
                'controller' => $this->AdminCRLink("users-1", ["dealership"]),
                'settings'   => $this->AdminCRLink("users-2", ["dealership", "settings"]),
            ];
            $links["ajax-dealerships"] = $links["controller"] . "?list=true";

            $this->addData("links", $links);

            $meta = __("admin/users/meta-dealership");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => null,
                'title' => __("admin/users/breadcrumb-dealership"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->view->chose("admin")->render("user-dealership", $this->data);
        }

        private function dealership_settings()
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
                'controller' => $this->AdminCRLink("users-2", ["dealership", "settings"]),
            ];

            $this->addData("links", $links);

            $meta = __("admin/users/meta-dealership-settings");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("users-1", ["dealership"]),
                'title' => __("admin/users/breadcrumb-dealership"),
            ]);

            array_push($breadcrumbs, [
                'link'  => null,
                'title' => __("admin/users/breadcrumb-dealership-settings"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            Helper::Load("Products");

            $lang = Bootstrap::$lang->clang;
            $n_products = Products::get_groups_products($lang);


            $this->addData("products", $n_products);


            $this->view->chose("admin")->render("user-dealership-settings", $this->data);
        }

        private function gdpr()
        {
            $list = Filter::init("GET/list", "route");
            $param = isset($this->params[1]) ? $this->params[1] : false;

            if ($list == "gdpr-requests")
                return $this->ajax_gdpr_requests();
            elseif ($list == "gdpr-downloaders")
                return $this->ajax_gdpr_downloaders();
            elseif ($list == "gdpr-approvers")
                return $this->ajax_gdpr_approvers();
            elseif ($list == "gdpr-disapprovers")
                return $this->ajax_gdpr_disapprovers();
            elseif ($param == 'detail')
                return $this->gdpr_request_detail();


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
                'controller' => $this->AdminCRLink("users-1", ["gdpr"]),
                'contracts'  => $this->AdminCRLink("manage-website-2", ["pages", "contracts"]),
            ];
            $links["ajax-requests"] = $links["controller"] . "?list=gdpr-requests";
            $links["ajax-downloaders"] = $links["controller"] . "?list=gdpr-downloaders";
            $links["ajax-approvers"] = $links["controller"] . "?list=gdpr-approvers";
            $links["ajax-disapprovers"] = $links["controller"] . "?list=gdpr-disapprovers";

            $this->addData("links", $links);

            $meta = __("admin/users/meta-gdpr");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => null,
                'title' => __("admin/users/breadcrumb-gdpr"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("requests_total", $this->model->get_gdpr_requests_total());
            $this->addData("downloaders_total", $this->model->get_gdpr_downloaders_total());
            $this->addData("approvers_total", $this->model->get_gdpr_approvers_total());
            $this->addData("disapprovers_total", $this->model->get_gdpr_disapprovers_total());


            $this->view->chose("admin")->render("user-gdpr", $this->data);
        }


        private function gdpr_request_detail()
        {

            $id = (int)Filter::init("GET/id", "numbers");

            if (!$id) return false;

            $rq = $this->model->gdpr_request_detail($id);


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
                'controller' => $this->AdminCRLink("users-2", ["gdpr", "detail"]) . "?id=" . $id,
                'user_link'  => $this->AdminCRLink("users-2", ["detail", $rq["user_id"]]),
            ];

            $this->addData("links", $links);

            $meta = __("admin/users/meta-gdpr-detail");

            $this->addData("meta", $meta);

            $breadcrumbs = [
                [
                    'link'  => $this->AdminCRLink("dashboard"),
                    'title' => __("admin/index/breadcrumb-name"),
                ],
            ];

            array_push($breadcrumbs, [
                'link'  => $this->AdminCRLink("users-1", ["gdpr"]),
                'title' => __("admin/users/breadcrumb-gdpr"),
            ]);

            array_push($breadcrumbs, [
                'link'  => null,
                'title' => __("admin/users/breadcrumb-gdpr-detail"),
            ]);

            $this->addData("breadcrumb", $breadcrumbs);

            $this->addData("rq", $rq);

            $user = User::getData($rq["user_id"], "full_name", "array");

            $this->addData("user", $user);

            $this->addData("active_orders", $this->model->services_count($rq["user_id"], 1));
            $this->addData("inactive_orders", $this->model->services_count($rq["user_id"], 0));
            $this->addData("invoice_count", $this->model->invoice_count($rq["user_id"]));


            $this->view->chose("admin")->render("user-gdpr-request", $this->data);
        }


        public function main()
        {

            if (Filter::POST("operation")) return $this->operationMain(Filter::init("POST/operation", "route"));
            if (Filter::GET("operation")) return $this->operationMain(Filter::init("GET/operation", "route"));

            $page = isset($this->params[0]) ? $this->params[0] : false;
            return $this->pageMain($page);
        }
    }