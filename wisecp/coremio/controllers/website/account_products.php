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
        private $contact_types = [
            'registrant',
            'administrative',
            'technical',
            'billing',
        ];
        public const ATTACHMENT_FOLDER = RESOURCE_DIR . "uploads" . DS . "attachments" . DS;


        public function __construct($arg = [])
        {
            parent::__construct();
            $this->params = $arg['params'];

            if (!UserManager::LoginCheck("member")) {
                Utility::redirect($this->CRLink("sign-in"));
                die();
            }

            $udata = UserManager::LoginData("member");
            $redirect_link = User::full_access_control_account($udata);
            if ($redirect_link) {
                Utility::redirect($redirect_link);
                die();
            }
            Helper::Load("Orders");
        }


        public function main()
        {
            if (isset($this->params[0]) && $this->params[0] == "detail" && isset($this->params[1]) && $this->params[1] != '') {
                unset(Bootstrap::$init->route[0]);
                return $this->detail_main();
            } else
                return $this->products_main();
        }


        private function mainOperation($operation = '')
        {
            if ($operation == "modify_default_nameserver")
                return $this->modify_default_nameserver();
            elseif ($operation == "create_whois_profile")
                return $this->create_whois_profile_submit();
            elseif ($operation == "delete_whois_profile")
                return $this->delete_whois_profile();
            elseif ($operation == "edit_whois_profile")
                return $this->edit_whois_profile_submit();
            return false;
        }


        private function mainPage($page = '')
        {
            if ($page == "whois_profiles") return $this->whois_profiles();
            elseif ($page == "create_whois_profile") return $this->create_whois_profile();
            elseif ($page == "edit_whois_profile") return $this->edit_whois_profile();
            return false;
        }


        private function delete_whois_profile()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $udata = UserManager::LoginData();
            $id = (int)Filter::init("POST/id", "numbers");

            $get = User::get_whois_profile($id, $udata["id"]);

            if (!$get) exit("Not found whois profile");

            User::delete_whois_profile($id);

            User::addAction($udata["id"], "alteration", "Whois profile deleted", ['id' => $id]);


            if ($get["detouse"]) {
                $rows = User::whois_profiles($udata["id"]);
                if ($rows && sizeof($rows) == 1) {
                    foreach ($rows as $row) {
                        User::set_whois_profile($row["id"], ['detouse' => 1]);
                        break;
                    }
                }
            }

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("website/account_products/domain-whois-tx15"),
            ]);

        }


        private function create_whois_profile_submit()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $udata = UserManager::LoginData();


            $detouse = (int)Filter::init("POST/detouse", "numbers");
            $profile_name = Filter::init("POST/profile_name", "hclear");
            $full_name = Filter::init("POST/Name", "hclear");
            $company_name = Filter::init("POST/Company", "hclear");
            $email = Filter::init("POST/EMail", "email");
            $pcountry_code = Filter::init("POST/PhoneCountryCode", "numbers");
            $phone = Filter::init("POST/Phone", "numbers");
            $fcountry_code = Filter::init("POST/FaxCountryCode", "numbers");
            $fax = Filter::init("POST/Fax", "numbers");
            $address = Filter::init("POST/Address", "hclear");
            $city = Filter::init("POST/City", "hclear");
            $state = Filter::init("POST/State", "hclear");
            $zipcode = Filter::init("POST/ZipCode", "hclear");
            $country_code = Filter::init("POST/Country", "letters");

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

            if (Validation::isEmpty($profile_name))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-whois-tx16"),
                ]));

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

            $data = [
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

            if ($detouse) User::remove_detouse_whois_profile($udata["id"]);

            User::create_whois_profile([
                'owner_id'    => $udata["id"],
                'detouse'     => $detouse ? 1 : 0,
                'name'        => $profile_name,
                'information' => Utility::jencode($data),
                'created_at'  => DateManager::Now(),
                'updated_at'  => DateManager::Now(),
            ]);

            $rows = User::whois_profiles($udata["id"]);
            if ($rows && sizeof($rows) == 1) {
                foreach ($rows as $row) {
                    User::set_whois_profile($row["id"], ['detouse' => 1]);
                    break;
                }
            }


            User::addAction($udata["id"], "alteration", "Whois profile created");

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("website/account_products/domain-whois-tx13"),
            ]);

        }

        private function edit_whois_profile_submit()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $udata = UserManager::LoginData();

            $id = (int)Filter::init("POST/id", "numbers");

            $profile = User::get_whois_profile($id, $udata["id"]);

            if (!$profile) exit("Not found profile");


            $detouse = (int)Filter::init("POST/detouse", "numbers");
            $profile_name = Filter::init("POST/profile_name", "hclear");
            $full_name = Filter::init("POST/Name", "hclear");
            $company_name = Filter::init("POST/Company", "hclear");
            $email = Filter::init("POST/EMail", "email");
            $pcountry_code = Filter::init("POST/PhoneCountryCode", "numbers");
            $phone = Filter::init("POST/Phone", "numbers");
            $fcountry_code = Filter::init("POST/FaxCountryCode", "numbers");
            $fax = Filter::init("POST/Fax", "numbers");
            $address = Filter::init("POST/Address", "hclear");
            $city = Filter::init("POST/City", "hclear");
            $state = Filter::init("POST/State", "hclear");
            $zipcode = Filter::init("POST/ZipCode", "hclear");
            $country_code = Filter::init("POST/Country", "letters");

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

            if (Validation::isEmpty($profile_name))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-whois-tx16"),
                ]));

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

            $data = [
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

            if ($detouse && !$profile["detouse"]) User::remove_detouse_whois_profile($udata["id"]);

            User::set_whois_profile($id, [
                'detouse'     => $detouse ? 1 : 0,
                'name'        => $profile_name,
                'information' => Utility::jencode($data),
                'updated_at'  => DateManager::Now(),
            ]);


            $has_detouse = 0;

            $rows = User::whois_profiles($udata["id"]);
            if ($rows) {
                foreach ($rows as $row) if ($row["detouse"]) $has_detouse = $row["id"];
                if ($has_detouse < 1) User::set_whois_profile($rows[0]["id"], ["detouse" => 1]);
            }


            User::addAction($udata["id"], "alteration", "Whois profile edited", ['id' => $id]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("website/account_products/domain-whois-tx14"),
            ]);

        }


        private function modify_default_nameserver()
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $udata = UserManager::LoginData();


            $default_nameserver = [];

            $values = Filter::init("POST/values");

            if ($values && is_array($values)) {
                foreach ($values as $ns) {
                    if (Utility::strlen($ns) < 5 || !is_string($ns)) continue;
                    $default_nameserver[] = Filter::domain($ns);
                }
            }

            $default_nameserver = Utility::jencode($default_nameserver);

            User::setInfo($udata["id"], ['default_nameserver' => $default_nameserver]);

            User::addAction($udata["id"], "alteration", "Default nameserver changed");

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/automation/success1"),
            ]);

        }


        private function whois_profiles()
        {

            $this->addData("pname", "account_products");
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

            $lang = Bootstrap::$lang->clang;
            $udata = UserManager::LoginData("member");


            $address = AddressManager::getAddress(0, $udata["id"]);
            $udata = array_merge($udata, User::getData($udata["id"], "name,surname,full_name,company_name,email", "array"));

            $udata["address"] = $address;

            $visibility_balance = false;

            $balanceModule = Modules::Load("Payment", "Balance", true);
            if ($balanceModule) $visibility_balance = $balanceModule["config"]["settings"]["status"];

            $this->addData("visibility_balance", $visibility_balance);
            $this->addData("udata", $udata);

            $this->addData("page_type", "account");

            $breadcrumb = [
                [
                    'link'  => $this->CRLink("my-account"),
                    'title' => __("website/account/breadcrumb-dashboard"),
                ],
            ];

            $meta = __("website/account_products/meta-domain");
            $header_title = __("website/account_products/domain-whois-tx6");

            if (isset($meta["title"]) && $meta["title"]) $meta["title"] = $header_title;

            array_push($breadcrumb, [
                'link'  => $this->CRLink("ac-ps-products"),
                'title' => __("website/account_products/page-title-type-all"),
            ], [
                'link'  => $this->CRLink("ac-ps-products-t", ["domain"]),
                'title' => __("website/account_products/page-title-type-domain"),
            ], [
                'link'  => null,
                'title' => $header_title,
            ]);
            $controller_link = $this->CRLink("ac-ps-products-t", ["domain"]);


            $this->addData("gtype", "domain");
            $this->addData("meta", $meta);
            $this->addData("header_title", $header_title);
            $this->addData("page_title", $header_title);
            $this->addData("panel_breadcrumb", $breadcrumb);


            $links = [
                'controller' => $controller_link,
                'create'     => $controller_link . "?page=create_whois_profile",
            ];


            $this->addData("links", $links);
            $this->addData("list", User::whois_profiles($udata["id"]));

            $this->view->chose("website")->render("ac-products-domain-whois-profiles", $this->data);

            return true;
        }

        private function create_whois_profile()
        {
            $this->addData("pname", "account_products");
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

            $lang = Bootstrap::$lang->clang;
            $udata = UserManager::LoginData("member");


            $address = AddressManager::getAddress(0, $udata["id"]);
            $udata = array_merge($udata, User::getData($udata["id"], "name,surname,full_name,company_name,email", "array"));

            $udata["address"] = $address;

            $visibility_balance = false;

            $balanceModule = Modules::Load("Payment", "Balance", true);
            if ($balanceModule) $visibility_balance = $balanceModule["config"]["settings"]["status"];

            $this->addData("visibility_balance", $visibility_balance);
            $this->addData("udata", $udata);

            $this->addData("page_type", "account");

            $breadcrumb = [
                [
                    'link'  => $this->CRLink("my-account"),
                    'title' => __("website/account/breadcrumb-dashboard"),
                ],
            ];

            $meta = __("website/account_products/meta-domain");
            $header_title = __("website/account_products/domain-whois-tx11");

            if (isset($meta["title"]) && $meta["title"]) $meta["title"] = $header_title;

            array_push($breadcrumb, [
                'link'  => $this->CRLink("ac-ps-products"),
                'title' => __("website/account_products/page-title-type-all"),
            ], [
                'link'  => $this->CRLink("ac-ps-products-t", ["domain"]),
                'title' => __("website/account_products/page-title-type-domain"),
            ], [
                'link'  => $this->CRLink("ac-ps-products-t", ["domain"]) . "?page=whois_profiles",
                'title' => __("website/account_products/domain-whois-tx6"),
            ], [
                'link'  => null,
                'title' => $header_title,
            ]);
            $controller_link = $this->CRLink("ac-ps-products-t", ["domain"]);


            $this->addData("gtype", "domain");
            $this->addData("meta", $meta);
            $this->addData("header_title", $header_title);
            $this->addData("page_title", $header_title);
            $this->addData("panel_breadcrumb", $breadcrumb);


            $links = [
                'controller' => $controller_link,
            ];


            $this->addData("links", $links);


            $this->view->chose("website")->render("ac-products-domain-create-whois-profile", $this->data);

            return true;
        }

        private function edit_whois_profile()
        {

            $lang = Bootstrap::$lang->clang;
            $udata = UserManager::LoginData("member");


            $id = (int)Filter::init("GET/id", "numbers");

            $profile = User::get_whois_profile($id, $udata["id"]);

            if (!$profile) exit("Not found profile");


            $this->addData("pname", "account_products");
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


            $address = AddressManager::getAddress(0, $udata["id"]);
            $udata = array_merge($udata, User::getData($udata["id"], "name,surname,full_name,company_name,email", "array"));

            $udata["address"] = $address;

            $visibility_balance = false;

            $balanceModule = Modules::Load("Payment", "Balance", true);
            if ($balanceModule) $visibility_balance = $balanceModule["config"]["settings"]["status"];

            $this->addData("visibility_balance", $visibility_balance);
            $this->addData("udata", $udata);

            $this->addData("page_type", "account");

            $breadcrumb = [
                [
                    'link'  => $this->CRLink("my-account"),
                    'title' => __("website/account/breadcrumb-dashboard"),
                ],
            ];

            $meta = __("website/account_products/meta-domain");
            $header_title = __("website/account_products/domain-whois-tx17");

            if (isset($meta["title"]) && $meta["title"]) $meta["title"] = $header_title;

            array_push($breadcrumb, [
                'link'  => $this->CRLink("ac-ps-products"),
                'title' => __("website/account_products/page-title-type-all"),
            ], [
                'link'  => $this->CRLink("ac-ps-products-t", ["domain"]),
                'title' => __("website/account_products/page-title-type-domain"),
            ], [
                'link'  => $this->CRLink("ac-ps-products-t", ["domain"]) . "?page=whois_profiles",
                'title' => __("website/account_products/domain-whois-tx6"),
            ], [
                'link'  => null,
                'title' => $header_title,
            ]);
            $controller_link = $this->CRLink("ac-ps-products-t", ["domain"]);


            $this->addData("gtype", "domain");
            $this->addData("meta", $meta);
            $this->addData("header_title", $header_title);
            $this->addData("page_title", $header_title);
            $this->addData("panel_breadcrumb", $breadcrumb);


            $links = [
                'controller' => $controller_link,
            ];


            $this->addData("links", $links);

            $this->addData("profile", $profile);


            $this->view->chose("website")->render("ac-products-domain-edit-whois-profile", $this->data);

            return true;
        }


        private function products_main()
        {

            if ($operation = Filter::init("REQUEST/operation", "route")) return $this->mainOperation($operation);
            if ($page = Filter::init("GET/page", "route")) return $this->mainPage($page);

            $type = (isset($this->params[0])) ? Filter::init($this->params[0], "letters_numbers") : false;

            Helper::Load("Money");


            $this->addData("pname", "account_products");
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

            $lang = Bootstrap::$lang->clang;
            $udata = UserManager::LoginData("member");


            $address = AddressManager::getAddress(0, $udata["id"]);
            $udata = array_merge($udata, User::getData($udata["id"], "name,surname,full_name,company_name,email", "array"));

            $udata["address"] = $address;

            $visibility_balance = false;

            $balanceModule = Modules::Load("Payment", "Balance", true);
            if ($balanceModule) $visibility_balance = $balanceModule["config"]["settings"]["status"];

            $this->addData("visibility_balance", $visibility_balance);
            $this->addData("udata", $udata);


            if ($type == "special") {
                $category_id = Filter::init("GET/category", "rnumbers");
                $category = $this->model->isCategory($category_id, $lang);
                if (!$category) die("There is no such category.");
                $category_IDs = "";
                $product_IDs = "";
                $category_IDs .= $category["id"] . ",";
                $categories = $this->model->_product_categories($lang, $category["id"]);
                if ($categories && is_array($categories))
                    foreach ($categories as $cat) $category_IDs .= $cat["id"] . ",";
                $category_IDs = rtrim($category_IDs, ",");
                if ($category_IDs != '') {
                    $products = $this->model->_category_products($category_IDs);
                    if ($products) $product_IDs = $products->ids;
                }
            } else {
                $category = false;
                $product_IDs = false;
            }


            $this->addData("page_type", "account");


            if (isset($category) && is_array($category))
                $this->addData("category", $category);

            $breadcrumb = [
                [
                    'link'  => $this->CRLink("my-account"),
                    'title' => __("website/account/breadcrumb-dashboard"),
                ],
            ];


            if ($type == '') {
                $meta = __("website/account_products/meta-all");
                $header_title = __("website/account_products/page-title-type-all");
                array_push($breadcrumb, [
                    'link'  => null,
                    'title' => $header_title,
                ]);
                $controller_link = $this->CRLink("ac-ps-products");
            } elseif ($type == 'special') {
                $meta = ['title' => $category["title"]];
                $header_title = $category["title"];
                array_push($breadcrumb, [
                    'link'  => $this->CRLink("ac-ps-products"),
                    'title' => __("website/account_products/page-title-type-all"),
                ], [
                    'link'  => null,
                    'title' => $category["title"],
                ]);

                $controller_link = $this->CRLink("ac-ps-products-t", ["special"]) . "?category" . $category_id;
            } else {
                $meta = __("website/account_products/meta-" . $type);
                $header_title = __("website/account_products/page-title-type-" . $type);
                array_push($breadcrumb, [
                    'link'  => $this->CRLink("ac-ps-products"),
                    'title' => __("website/account_products/page-title-type-all"),
                ], [
                    'link'  => null,
                    'title' => $header_title,
                ]);
                $controller_link = $this->CRLink("ac-ps-products-t", [$type]);
            }

            $this->addData("category", $category);
            $this->addData("gtype", $type);
            $this->addData("meta", $meta);
            $this->addData("header_title", $header_title);
            $this->addData("page_title", $header_title);
            $this->addData("panel_breadcrumb", $breadcrumb);
            $type_view = empty($type) ? "-all" : "-" . $type;

            $situations = $this->view->chose("website")->render("common-needs", false, true, true);
            $situations = $situations["product"];

            $this->addData("situations", $situations);

            if (Filter::GET("approve_ctoc_s_t")) {
                $evt_id = (int)Filter::init("GET/approve_ctoc_s_t", "numbers");
                Helper::Load("Events");
                $evt = $evt_id ? Events::get($evt_id) : false;
                if ($evt && $evt["data"]["to_id"] == $udata["id"] && $evt["status"] == 'pending') Events::approved($evt);
            }


            $filter_status = Filter::init("GET/filter_status", "letters_numbers");

            $filter_count = [
                'all'        => $this->model->get_products_count($udata["id"], $type, $product_IDs),
                'waiting'    => $this->model->get_products_count($udata["id"], $type, $product_IDs, 'waiting'),
                'active'     => $this->model->get_products_count($udata["id"], $type, $product_IDs, 'active'),
                'inprocess'  => $this->model->get_products_count($udata["id"], $type, $product_IDs, 'inprocess'),
                'cancelled'  => $this->model->get_products_count($udata["id"], $type, $product_IDs, 'cancelled'),
                'upcoming30' => $this->model->get_products_count($udata["id"], $type, $product_IDs, 'upcoming30'),
            ];

            $links = [
                'controller' => $controller_link,
            ];

            if ($type == "domain") {

                $default_nameserver = User::getInfo($udata["id"], ['default_nameserver']);
                $default_nameserver = $default_nameserver["default_nameserver"];
                $default_nameserver = $default_nameserver ? Utility::jdecode($default_nameserver, true) : [];
                $links["whois-profiles"] = $links["controller"] . "?page=whois_profiles";

                $this->addData("default_nameserver", $default_nameserver);

            }

            $this->addData("filter_counts", $filter_count);
            $this->addData("filter_status", $filter_status);
            $this->addData("links", $links);

            $this->addData("list", $this->get_products($udata["id"], $type, $product_IDs, $filter_status));


            $this->view->chose("website")->render("ac-products" . $type_view, $this->data);
        }


        private function get_products($uid = 0, $type = '', $pids = '', $status = '')
        {
            $lang = Bootstrap::$lang->clang;
            Helper::Load("Invoices");
            $main_tax_rate = Invoices::getTaxRate();
            $taxation = Invoices::getTaxation();


            $data = $this->model->get_products($uid, $type, $pids, $status);
            if ($data) {
                $keys = array_keys($data);
                $size = sizeof($keys) - 1;
                for ($i = 0; $i <= $size; $i++) {
                    $row = $data[$keys[$i]];
                    $tax_rate = $main_tax_rate;
                    $inv = Invoices::get_last_invoice($row["id"], '', 't2.taxrate');

                    if ($inv && $inv["taxrate"] > 0.00) $tax_rate = $inv["taxrate"];
                    if (!$taxation) $tax_rate = 0;

                    $proanse_amount = $row["amount"];

                    if ($tax_rate > 0.00) {
                        $tax_amount = Money::get_tax_amount($proanse_amount, $tax_rate);
                        $row["amount"] = $proanse_amount + $tax_amount;
                        $data[$keys[$i]]["amount"] = $row["amount"];
                    }

                    $data[$keys[$i]]["options"] = ($row["options"] != '') ? Utility::jdecode($row["options"], true) : [];
                    $data[$keys[$i]]["detail_link"] = $this->CRLink("ac-ps-product", [$row["id"]]);
                    if ($row["type"] == "special" || $row["type"] == "hosting" || $row["type"] == "server" || $row["type"] == "sms") {
                        $isCategory = $this->model->product_category($row["product_id"], $lang);
                        $data[$keys[$i]]["category"] = $isCategory ? $isCategory["title"] : false;
                    }
                    if ($row["type"] == "domain" && $row["status"] == "inprocess") {
                        if (Orders::detect_docs_in_domain(Orders::get($row["id"])))
                            $data[$keys[$i]]["status"] = "requireDoc";
                    }
                }
            }
            return $data;
        }


        private function getGroups($uid = 0, $pid = 0)
        {
            $data = $this->model->getGroups($uid, $pid);
            if ($data) {
                $keys = array_keys($data);
                $size = sizeof($keys) - 1;
                for ($i = 0; $i <= $size; $i++) $data[$keys[$i]]["numbers"] = $data[$keys[$i]]["numbers"] != '' ? explode(",", $data[$keys[$i]]["numbers"]) : [];
            }
            return $data;
        }


        private function DetailMain_POST($proanse, $udata)
        {
            $operation = Filter::init("REQUEST/operation", "letters_numbers", "_-");

            if ($operation == "subscription_detail")
                return $this->subscription_detail($proanse, $udata);

            if ($operation == 'domain_resend_verification_mail')
                return $this->domain_resend_verification_mail($proanse, $udata);
            if ($operation == "cancel_subscription")
                return $this->cancel_subscription($proanse, $udata);

            if ($operation == "remove_transfer_service" && Config::get("options/ctoc-service-transfer"))
                return $this->remove_transfer_service($proanse, $udata);
            if ($operation == "transfer_service" && Config::get("options/ctoc-service-transfer"))
                return $this->transfer_service($proanse, $udata);
            if ($operation == "change_software_domain")
                return $this->change_software_domain($proanse, $udata);
            if ($operation == "reissue_software")
                return $this->reissue_software($proanse, $udata);
            if ($operation == "set_auto_pay_status")
                return $this->set_auto_pay_status($proanse, $udata);
            if ($operation == "requirement-file-download")
                return $this->requirement_file_download($proanse, $udata);
            if ($operation == "order_renewal")
                return $this->order_renewal($proanse, $udata);
            if ($operation == "buy_addons_summary")
                return $this->buy_addons($proanse, $udata, true);
            if ($operation == "buy_addons")
                return $this->buy_addons($proanse, $udata);
            if ($operation == "add_new_group")
                return $this->add_new_group_submit($proanse, $udata);
            elseif ($operation == "change_group_numbers")
                return $this->change_group_numbers($proanse, $udata);
            elseif ($operation == "delete_group")
                return $this->delete_group($proanse, $udata);
            elseif ($operation == "update_black_list")
                return $this->update_black_list($proanse, $udata);
            elseif ($operation == "get_credit")
                return $this->get_sms_credit($proanse, $udata);
            elseif ($operation == "get_sms_report")
                return $this->get_sms_report($proanse, $udata);
            elseif ($operation == "update_cancel_link")
                return $this->update_cancel_link($proanse, $udata);
            elseif ($operation == "submit_sms")
                return $this->submit_sms($proanse, $udata);
            elseif ($operation == "new_origin_request")
                return $this->new_origin_request($proanse, $udata);
            elseif ($operation == "add_attachment")
                return $this->add_origin_attachment($proanse, $udata);
            elseif ($operation == "sms_credit_renewal")
                return $this->sms_credit_renewal($proanse, $udata);
            elseif ($operation == "hosting_add_new_email")
                return $this->hosting_add_new_email($proanse, $udata);
            elseif ($operation == "hosting_update_email")
                return $this->hosting_update_email($proanse, $udata);
            elseif ($operation == "hosting_delete_email")
                return $this->hosting_delete_email($proanse, $udata);
            elseif ($operation == "hosting_add_new_email_forward")
                return $this->hosting_add_new_email_forward($proanse, $udata);
            elseif ($operation == "hosting_delete_email_forward")
                return $this->hosting_delete_email_forward($proanse, $udata);
            elseif ($operation == "hosting_change_password")
                return $this->hosting_change_password($proanse, $udata);
            elseif ($operation == "domain_modify_whois")
                return $this->domain_modify_whois($proanse, $udata);
            elseif ($operation == "domain_modify_dns")
                return $this->domain_modify_dns($proanse, $udata);
            elseif ($operation == "domain_add_cns")
                return $this->domain_add_cns($proanse, $udata);
            elseif ($operation == "domain_modify_cns")
                return $this->domain_modify_cns($proanse, $udata);
            elseif ($operation == "domain_delete_cns")
                return $this->domain_delete_cns($proanse, $udata);
            elseif ($operation == "domain_modify_transferlock")
                return $this->domain_modify_transferlock($proanse, $udata);
            elseif ($operation == "domain_transfer_code_submit")
                return $this->domain_transfer_code_submit($proanse, $udata);
            elseif ($operation == "domain_renewal")
                return $this->domain_renewal($proanse, $udata);
            elseif ($operation == "domain_whois_privacy")
                return $this->domain_whois_privacy($proanse, $udata);
            elseif ($operation == "canceled_product")
                return $this->canceled_product($proanse, $udata);
            elseif ($operation == "remove_cancelled_product")
                return $this->remove_cancelled_product($proanse, $udata);
            elseif ($operation == "set_upgrade_product" && Config::get("options/product-upgrade/status"))
                return $this->set_upgrade_product($proanse, $udata);
            elseif ($operation == "add_dns_record")
                return $this->add_dns_record($proanse, $udata);
            elseif ($operation == "update_dns_record")
                return $this->update_dns_record($proanse, $udata);
            elseif ($operation == "delete_dns_record")
                return $this->delete_dns_record($proanse, $udata);
            elseif ($operation == "set_forward_domain")
                return $this->set_forward_domain($proanse, $udata);
            elseif ($operation == "cancel_forward_domain")
                return $this->cancel_forward_domain($proanse, $udata);
            elseif ($operation == "add_email_forward")
                return $this->add_email_forward($proanse, $udata);
            elseif ($operation == "delete_email_forward")
                return $this->delete_email_forward($proanse, $udata);
            elseif ($operation == "update_email_forward")
                return $this->update_email_forward($proanse, $udata);
            elseif ($operation == "add_dns_sec_record")
                return $this->add_dns_sec_record($proanse, $udata);
            elseif ($operation == "delete_dns_sec_record")
                return $this->delete_dns_sec_record($proanse, $udata);
            elseif ($operation == "add_domain_doc")
                return $this->add_domain_doc($proanse, $udata);
            elseif ($operation == "download_domain_doc_file")
                return $this->download_domain_doc_file($proanse, $udata);
            elseif ($operation == "sent_domain_doc")
                return $this->sent_domain_doc($proanse, $udata);
            else
                return false;
        }


        private function download_domain_doc_file($proanse, $udata)
        {
            if ($proanse["status"] != "inprocess") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $options = $proanse["options"];

            $id = (int)Filter::init("GET/id", "numbers");

            $doc = $this->model->db->select()->from("users_products_docs");
            $doc->where("id", "=", $id, "&&");
            $doc->where("owner_id", "=", $proanse["id"]);
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

        private function sent_domain_doc($proanse, $udata)
        {
            if ($proanse["status"] != "inprocess") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $options = $proanse["options"];

            $docs = $this->model->db->select()->from("users_products_docs");
            $docs->where("owner_id", "=", $proanse["id"], "&&");
            $docs->where("status", "!=", "declined", "&&");
            $docs->where("status", "!=", "verified");
            $docs->order_by("id DESC");
            $docs = $docs->build() ? $docs->fetch_assoc() : [];

            if (!$docs) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-doc-29"),
                ]);
                return false;
            }

            $found_manuel_verification = false;
            $module_docs = [];

            foreach ($docs as $d) {
                $m_data = $d["module_data"] ? Crypt::decode($d["module_data"], Config::get("crypt/user")) : '';
                $m_data = $m_data ? Utility::jdecode($d["module_data"], true) : [];
                $d["module_data"] = $m_data;

                $is_module = ($m_data && sizeof($m_data) > 0);

                if ($d["status"] == "unsent" || $d["status"] == "pending") {
                    if ($is_module)
                        $module_docs[$d["id"]] = $d;
                    else
                        $found_manuel_verification = true;
                }
                if ($d["status"] == "unsent")
                    $this->model->db->update("users_products_docs", ['status' => 'pending'])->where("id", "=", $d["id"])->save();
            }

            if (!(isset($options["config"]) && $options["config"]) && $proanse["module"] !== "none" && $proanse["module"]) {
                $notification = !$found_manuel_verification;
                $action = Orders::MakeOperation($found_manuel_verification ? "approve" : "active", $proanse["id"], $proanse["product_id"], $notification);
                if ($action) {
                    foreach ($module_docs as $d_id => $d)
                        $this->model->db->update("users_products_docs", [
                            'status'     => 'verified',
                            'status_msg' => '',
                        ])->where("id", "=", $d_id)->save();
                } else {
                    foreach ($module_docs as $d_id => $d)
                        $this->model->db->update("users_products_docs", [
                            'status'     => 'declined',
                            'status_msg' => Utility::strlen(Orders::$message) > 0 ? Orders::$message : '',
                        ])->where("id", "=", $d_id)->save();
                }
            }
            $proanse = Orders::get($proanse["id"]);

            if ($proanse["status"] == "active" && $found_manuel_verification)
                Orders::set($proanse["id"], ['status' => "inprocess"]);


            User::addAction($udata["id"], "alteration", "domain-sent-for-verification", ['domain' => $options["domain"]]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("website/account_products/domain-doc-28"),
            ]);
        }

        private function add_domain_doc($proanse, $udata)
        {
            if ($proanse["status"] != "inprocess") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $options = $proanse["options"];
            $module = false;
            $fetchModule = false;
            $status = "unsent";

            if ($proanse["module"] != "none" && $proanse["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $proanse["module"])) {
                    $module = new $proanse["module"]();
                }
            }

            $ll = Config::get("general/local");
            $ulang = $udata["lang"] ?? $ll;


            $manuel_doc_fields = [];
            $module_docs = [];

            // External Verification Docs
            $operator_docs = $options["verification_operator_docs"] ?? [];

            // Found Module Information/Document Fields
            if ($proanse["module"] !== "none" && $proanse["module"] && isset($module) && $module) {
                if (isset($module->config["settings"]["doc-fields"][$options["tld"]]) && $module->config["settings"]["doc-fields"][$options["tld"]])
                    $module_docs = $module->config["settings"]["doc-fields"][$options["tld"]];
            }


            // Found Manuel Information/Document Fields
            $found_doc_fields = $this->model->db->select()->from("tldlist_docs");
            $found_doc_fields->where("tld", "=", $options["tld"]);
            $found_doc_fields->order_by("sortnum ASC");
            if ($found_doc_fields->build()) $manuel_doc_fields = $found_doc_fields->fetch_assoc();


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

            $doc_id = Filter::init("POST/doc_id", "route");


            if (!$info_docs || !$doc_id) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown Problem",
                ]);
                return false;
            }


            if (!isset($info_docs[$doc_id])) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown Problem",
                ]);
                return false;
            }

            $doc = $info_docs[$doc_id] ?? [];
            $text = Filter::quotes(Filter::init("POST/text", "hclear"));
            $select = Filter::init("POST/select", "route");
            $file = Filter::init("FILES/file");
            $file_data = [];
            $value = '';
            $module_data = [];


            if ($doc["type"] == "text") {
                if (Validation::isEmpty($text)) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/domain-doc-25"),
                    ]);
                    return false;
                }
                $value = $text;
            } elseif ($doc["type"] == "select") {
                if (!isset($doc["options"][$select])) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/domain-doc-26"),
                    ]);
                    return false;
                }

                $value = $doc["options"][$select];
            } elseif ($doc["type"] == "file") {
                if (!$file) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/domain-doc-27"),
                    ]);
                    return false;
                }
                Helper::Load("Uploads");

                $extensions = $doc["allowed_ext"] ?? '';
                if (!$extensions) $extensions = Config::get("options/product-fields-extensions");
                $extensions = str_replace(" ", "", $extensions);
                $max_file_size = $doc["max_file_size"] ?? 3;
                $max_file_size = FileManager::converByte($max_file_size . "MB");

                Helper::Load("Uploads");
                $upload = Helper::get("Uploads");

                $upload->init($file, [
                    'date'          => false,
                    'multiple'      => false,
                    'max-file-size' => $max_file_size,
                    'folder'        => self::ATTACHMENT_FOLDER,
                    'allowed-ext'   => $extensions,
                    'file-name'     => "random",
                ]);
                if (!$upload->processed()) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/osteps/failed-field-upload", ['{error}' => $upload->error]),
                    ]);
                    return false;
                }
                $file_result = $upload->operands[0];
                $file_data['name'] = $file_result["file_name"];
                $file_data['local_name'] = $file_result["name"];
                $file_data['path'] = self::ATTACHMENT_FOLDER . $file_result["name"];
                $file_data['size'] = $file_result["size"];
            }


            // Detect Module Data
            if (substr($doc_id, 0, 4) == "mod_") {
                $mod_k = substr($doc_id, 4);
                $module_data = ['key' => $mod_k];

                if ($doc["type"] == "text") $module_data["value"] = $value;
                elseif ($doc["type"] == "select") $module_data["value"] = $select;
                elseif ($doc["type"] == "file") $module_data["value"] = $file_data["path"];
            }


            $already_check = $this->model->db->select("id")->from("users_products_docs");
            $already_check->where("owner_id", "=", $proanse["id"], "&&");
            $already_check->where("doc_id", "=", $doc_id, "&&");
            $already_check->where("status", "!=", "declined");
            $already_check = $already_check->build();

            if ($already_check) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-doc-30"),
                ]);
                return false;
            }


            $p_module_data = ($module_data ? Utility::jencode($module_data) : '');
            $p_file_data = ($file_data ? Utility::jencode($file_data) : '');
            $this->model->db->insert("users_products_docs", [
                'owner_id'    => $proanse["id"],
                'doc_id'      => $doc_id,
                'name'        => $doc['name'],
                'value'       => $value ? Crypt::encode($value, Config::get("crypt/user")) : '',
                'module_data' => $p_module_data ? Crypt::encode($p_module_data, Config::get("crypt/user")) : '',
                'file'        => $p_file_data ? Crypt::encode($p_file_data, Config::get("crypt/user")) : '',
                'created_at'  => DateManager::Now(),
                'updated_at'  => DateManager::Now(),
                'status'      => $status,
            ]);

            User::addAction($udata["id"], "alteration", "added-doc-to-domain", ['domain' => $options["domain"]]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("admin/financial/success6"),
            ]);
        }

        private function add_dns_record($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


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


            $options = $proanse["options"];
            if ($proanse["module"] == "none") {
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown module",
                ]));
            }

            $fetchModule = Modules::Load("Registrars", $proanse["module"]);
            $module = new $proanse["module"]();

            if (!in_array($type, $fetchModule["config"]["settings"]["dns-record-types"] ?? []))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown dns type",
                ]));

            if (method_exists($module, "set_order")) $module->set_order($proanse);

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


            User::addAction($udata["id"], "alteration", "domain-dns-record-created", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);
        }

        private function update_dns_record($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


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


            $options = $proanse["options"];
            if ($proanse["module"] == "none") {
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown module",
                ]));
            }

            $fetchModule = Modules::Load("Registrars", $proanse["module"]);
            $module = new $proanse["module"]();

            if (!in_array($type, $fetchModule["config"]["settings"]["dns-record-types"] ?? []))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown dns type",
                ]));

            if (method_exists($module, "set_order")) $module->set_order($proanse);

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

        private function delete_dns_record($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $type = Filter::init("POST/type", "letters_numbers");
            $name = Filter::init("POST/name", "hclear");
            $value = Filter::init("POST/value", "hclear");
            $identity = Filter::init("POST/identity", "hclear");


            if (Validation::isEmpty($type) || Validation::isEmpty($name) || Validation::isEmpty($value))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-dns-records-12"),
                ]));


            $options = $proanse["options"];
            if ($proanse["module"] == "none") return false;

            $fetchModule = Modules::Load("Registrars", $proanse["module"]);
            $module = new $proanse["module"]();

            if (!in_array($type, $fetchModule["config"]["settings"]["dns-record-types"] ?? [])) {
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown dns type",
                ]));
            }

            if (method_exists($module, "set_order")) $module->set_order($proanse);

            if (!method_exists($module, 'deleteDnsRecord')) return false;

            $apply = $module->deleteDnsRecord($type, $name, $value, $identity);

            if (!$apply) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]);
                return false;
            }

            User::addAction($udata["id"], "alteration", "domain-dns-record-deleted", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);
        }

        private function set_forward_domain($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

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


            $options = $proanse["options"];
            if ($proanse["module"] == "none") return false;

            $fetchModule = Modules::Load("Registrars", $proanse["module"]);
            $module = new $proanse["module"]();


            if (method_exists($module, "set_order")) $module->set_order($proanse);

            if (!method_exists($module, 'setForwardingDomain')) return false;

            $apply = $module->setForwardingDomain($protocol, $method, $domain);

            if (!$apply) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]);
                return false;
            }

            User::addAction($udata["id"], "alteration", "domain-set-forward-domain");

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("website/account_products/domain-forwarding-tx7"),
            ]);

        }

        private function cancel_forward_domain($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $options = $proanse["options"];
            if ($proanse["module"] == "none") return false;

            $fetchModule = Modules::Load("Registrars", $proanse["module"]);
            $module = new $proanse["module"]();


            if (method_exists($module, "set_order")) $module->set_order($proanse);

            if (!method_exists($module, 'cancelForwardingDomain')) return false;

            $apply = $module->cancelForwardingDomain();

            if (!$apply) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]);
                return false;
            }

            User::addAction($udata["id"], "alteration", "domain-set-forward-domain");

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("website/account_products/domain-forwarding-tx8"),
            ]);

        }


        private function add_email_forward($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $prefix = Filter::init("POST/prefix", "email");
            $target = Filter::init("POST/target", "email");


            if (Validation::isEmpty($prefix) || Validation::isEmpty($target))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-forwarding-tx20"),
                ]));


            $options = $proanse["options"];
            if ($proanse["module"] == "none") {
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown module",
                ]));
            }

            $fetchModule = Modules::Load("Registrars", $proanse["module"]);
            $module = new $proanse["module"]();


            if (method_exists($module, "set_order")) $module->set_order($proanse);

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


            User::addAction($udata["id"], "alteration", "domain-email-forward-created", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);
        }

        private function delete_email_forward($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $prefix = Filter::init("POST/prefix", "email");
            $target = Filter::init("POST/target", "email");
            $identity = Filter::init("POST/identity", "hclear");


            if (Validation::isEmpty($prefix) || Validation::isEmpty($target))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-forwarding-tx20"),
                ]));


            $options = $proanse["options"];
            if ($proanse["module"] == "none") return false;

            $fetchModule = Modules::Load("Registrars", $proanse["module"]);
            $module = new $proanse["module"]();


            if (method_exists($module, "set_order")) $module->set_order($proanse);

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

            User::addAction($udata["id"], "alteration", "domain-email-forward-deleted", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);
        }

        private function update_email_forward($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $prefix = Filter::init("POST/prefix", "email");
            $target = Filter::init("POST/target", "email");
            $target_new = Filter::init("POST/target_new", "email");
            $identity = Filter::init("POST/identity", "hclear");


            if (Validation::isEmpty($prefix) || Validation::isEmpty($target))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-forwarding-tx20"),
                ]));


            $options = $proanse["options"];
            if ($proanse["module"] == "none") {
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown module",
                ]));
            }

            $fetchModule = Modules::Load("Registrars", $proanse["module"]);
            $module = new $proanse["module"]();


            if (method_exists($module, "set_order")) $module->set_order($proanse);

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


            User::addAction($udata["id"], "alteration", "domain-email-forward-updated", [
                'domain' => $options["domain"],
                'prefix' => $prefix,
                'target' => $target,
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);
        }


        private function add_dns_sec_record($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


            $digest = Filter::init("POST/digest", "hclear");
            $key_tag = Filter::init("POST/key_tag", "hclear");
            $digest_type = Filter::init("POST/digest_type", "numbers");
            $algorithm = Filter::init("POST/algorithm", "numbers");


            if (Validation::isEmpty($digest) || Validation::isEmpty($key_tag) || Validation::isEmpty($digest_type) || Validation::isEmpty($algorithm))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/domain-dns-records-12"),
                ]));


            $options = $proanse["options"];
            if ($proanse["module"] == "none") {
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown module",
                ]));
            }

            $fetchModule = Modules::Load("Registrars", $proanse["module"]);
            $module = new $proanse["module"]();

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


            if (method_exists($module, "set_order")) $module->set_order($proanse);

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


            User::addAction($udata["id"], "alteration", "domain-dns-sec-record-created", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);
        }


        private function delete_dns_sec_record($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));


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


            $options = $proanse["options"];
            if ($proanse["module"] == "none") return false;

            $fetchModule = Modules::Load("Registrars", $proanse["module"]);
            $module = new $proanse["module"]();

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

            if (method_exists($module, "set_order")) $module->set_order($proanse);

            if (!method_exists($module, 'deleteDnsSecRecord')) return false;

            $apply = $module->deleteDnsSecRecord($digest, $key_tag, $digest_type, $algorithm, $identity);

            if (!$apply) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]);
                return false;
            }

            User::addAction($udata["id"], "alteration", "domain-dns-sec-record-deleted", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);
        }


        private function domain_resend_verification_mail($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $options = $proanse["options"];

            Helper::Load(["Money", "Basket", "Events", "Notification"]);


            if ($proanse["module"] == "none") return false;

            $fetchModule = Modules::Load("Registrars", $proanse["module"]);
            $module = new $proanse["module"]();

            if (!method_exists($module, 'resend_verification_mail')) return false;

            $apply = $module->resend_verification_mail($proanse["options"]);

            if (!$apply) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => $module->error,
                ]);
                return false;
            }

            User::addAction($udata["id"], "alteration", "domain-resend-verification-mail", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("website/account_products/domain-resend-verification-success"),
            ]);
        }


        private function cancel_subscription($proanse = [], $udata = [])
        {
            $this->takeDatas("language");
            if ($proanse["subscription_id"] < 1) return false;

            $subscription = Orders::get_subscription($proanse["subscription_id"]);

            if (!$subscription) return false;

            $subscription = Orders::sync_subscription($subscription);

            if ($subscription == "cancelled") echo Utility::jencode(['status' => "successful"]);

            $cancel = Orders::cancel_subscription($subscription, $proanse);

            if (!$cancel) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => Orders::$message,
                ]);
                return false;
            }

            $a_data = UserManager::LoginData();
            User::addAction($a_data["id"], "cancellation", "recurrence-subscription-cancelled", [
                'identifier' => $subscription["identifier"],
            ]);

            Orders::add_history($a_data["id"], $proanse["id"], 'recurrence-subscription-cancelled', ['identifier' => $subscription["identifier"]]);


            echo Utility::jencode(['status' => "successful"]);

            return true;
        }

        private function subscription_detail($proanse = [], $udata = [])
        {
            $this->takeDatas("language");
            if ($proanse["subscription_id"] < 1)
                return false;

            $subscription = Orders::get_subscription($proanse["subscription_id"]);

            if (!$subscription) return false;

            $subscription = Orders::sync_subscription($subscription);


            Helper::Load(["Orders", "Products", "Money"]);

            $situations = $this->view->chose("website")->render("common-needs", false, false, true);
            $subscription_situations = $situations["subscription"];

            $links = ['controller' => $this->CRLink("ac-ps-product", [$proanse["id"]])];

            include $this->view->get_template_dir() . "ac-product-subscription-detail.php";

            return true;
        }


        private function set_auto_pay_status($proanse, $udata)
        {
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $status = (int)Filter::init("POST/status", "numbers");
            $status_p = $status ? "on" : "off";


            $stored_cards = Models::$init->db->select("id")->from("users_stored_cards");
            $stored_cards->where("user_id", "=", $udata["id"]);
            $stored_cards = $stored_cards->build() ? $stored_cards->fetch_assoc() : false;

            if ($status && !$stored_cards) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/auto-pay-3"),
                ]);
                return false;
            }

            Orders::set($proanse["id"], ['auto_pay' => $status]);

            Orders::add_history($udata["id"], $proanse["id"], "changed-auto-pay-status-" . $status_p);


            User::addAction($udata["id"], "alteration", "changed-order-auto-pay-status-" . $status_p, [
                'order_id'   => $proanse["id"],
                'order_name' => $proanse["name"],
            ]);

            echo Utility::jencode(['status' => "successful"]);
        }

        private function change_software_domain($proanse, $udata)
        {
            if ($proanse["type"] != "software") return false;
            if ($proanse["status"] != "active") return false;
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $domain = Filter::init("POST/domain", "domain");

            if (Validation::isEmpty($domain))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='domain']",
                    'message' => __("website/account_products/error13"),
                ]));

            $domain = Utility::strtolower($domain);

            $domain = str_replace('www.', '', $domain);

            $parse = Utility::domain_parser("http://" . $domain);

            if (!$parse || !$parse["host"] || !$parse["tld"])
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='domain']",
                    'message' => __("website/account_products/error13"),
                ]));

            $domain = $parse["domain"];

            $options = $proanse["options"];

            Helper::Load("Products");

            $product = Products::get("software", $proanse["product_id"]);


            if ($domain == $options["domain"])
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='domain']",
                    'message' => __("website/account_products/error13"),
                ]));


            $change_domain = Config::get("options/software-change-domain/status");
            $change_domain_limit = Config::get("options/software-change-domain/limit");

            Helper::Load("Events");

            if (isset($product["options"]["change-domain"])) $change_domain = $product["options"]["change-domain"];
            if (isset($options["change-domain"])) $change_domain = $options["change-domain"];
            if ($change_domain && strlen($change_domain_limit) > 0) {
                $apply_changes = Events::getList('log', 'order', $proanse["id"], 'change-domain');
                $apply_count = $apply_changes ? sizeof($apply_changes) : 0;
                if ($apply_count >= (int)$change_domain_limit) $change_domain = false;
            }
            if (!$change_domain) return false;


            if (Validation::check_prohibited($domain, ['domain', 'word']))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account/prohibited-alert"),
                ]));

            $options["domain"] = $domain;

            Helper::Load(["Orders"]);

            Orders::set($proanse["id"], ['options' => Utility::jencode($options)]);

            Orders::add_history($udata["id"], $proanse["id"], "change-domain", [
                'old_domain' => $proanse["options"]["domain"],
                'new_domain' => $options["domain"],
            ]);


            User::addAction($udata["id"], "alteration", "change-software-domain", [
                'order_id'   => $proanse["id"],
                'old_domain' => $proanse["options"]["domain"],
                'new_domain' => $options["domain"],
                'order_name' => $proanse["name"],
            ]);

            echo Utility::jencode(['status' => "successful"]);
        }

        private function reissue_software($proanse, $udata)
        {
            if ($proanse["type"] != "software") return false;
            if ($proanse["status"] != "active") return false;

            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Orders", "Products"]);

            $product = Products::get("software", $proanse["product_id"]);

            $options = $proanse["options"];
            $options_p = $product["options"];

            if (isset($options["license_parameters"]) && $options["license_parameters"]) {
                foreach ($options["license_parameters"] as $k => $p) {
                    if (isset($options_p["license_parameters"][$k]) && $options_p["license_parameters"][$k]) {
                        if ($options_p["license_parameters"][$k]["match"]) unset($options["license_parameters"][$k]);
                    }
                }
            }

            if (isset($options["ip"])) unset($options["ip"]);
            /*
            if(isset($options_p["license_parameters"]["ip"]["match"]) && $options_p["license_parameters"]["ip"]["match"])
            {}
            */


            Orders::set($proanse["id"], ['options' => Utility::jencode($options)]);

            Orders::add_history($udata["id"], $proanse["id"], "Reissue");


            User::addAction($udata["id"], "alteration", "Reissue transaction was made to service #" . $proanse["id"]);

            echo Utility::jencode(['status' => "successful"]);
        }

        private function requirement_file_download($proanse, $udata)
        {
            $rid = (int)Filter::init("GET/rid", "numbers");
            $key = (int)Filter::init("GET/key", "numbers");
            if (!$rid) die();

            $requirement = $this->model->get_requirement($rid);
            if (!$requirement) die();
            if (!$requirement["response"]) die();

            if ($requirement["owner_id"] != $proanse["id"]) die();

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


        private function order_renewal($proanse, $udata)
        {
            if ($proanse["status"] == "cancelled" || $proanse["period"] == "none") die("Access denied");
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $period = Filter::init("POST/period", "letters_numbers");
            if ($period != "specialpricing" && Validation::isEmpty($period)) die("Error 1");

            Helper::Load(["Products", "Basket", "Orders"]);


            if ($period == 'specialpricing') {
                $selection = [
                    'id'     => 0,
                    'period' => $proanse["period"],
                    'time'   => $proanse["period_time"],
                    'amount' => $proanse["amount"],
                    'cid'    => $proanse["amount_cid"],
                ];
            } else {
                $product = Products::get($proanse["type"], $proanse["product_id"]);
                if (!$product) die();

                $prices = $product["price"];
                if (!isset($prices[$period])) die();
                $selection = $prices[$period];
            }


            $opData = [
                'event'          => "ExtendOrderPeriod",
                'event_data'     => [
                    'usproduct_id' => $proanse["id"],
                    'period'       => $selection["period"],
                    'period_time'  => $selection["time"],
                ],
                'type'           => $proanse["type"],
                'id'             => $proanse["product_id"],
                'category'       => __("website/osteps/category-order-renewal"),
                'category_route' => $this->CRLink("ac-ps-products"),
                'renewal'        => true,
            ];

            if ($selection["id"] == 0) {
                $opData["amount"] = $selection["amount"];
                $opData["cid"] = $selection["cid"];
                $opData['period'] = $selection["period"];
                $opData['period_time'] = $selection["time"];
            } else
                $opData['selection'] = $selection;


            if (isset($proanse["options"]["domain"]) && $proanse["options"]["domain"])
                $opData["domain"] = $proanse["options"]["domain"];

            Basket::set(false, $proanse["name"], $opData, false);
            Basket::save();
            echo Utility::jencode([
                'status'   => "successful",
                'redirect' => $this->CRLink("basket"),
            ]);

        }


        private function buy_addons_get_addons($ids = '', $selection = [])
        {
            if (is_array($selection) && $selection) {
                $period = $selection["period"];
                $time = $selection["time"];
            }

            $new_result = [];

            if (!Validation::isEmpty($ids)) {
                $lang = Bootstrap::$lang->clang;
                $result = $this->model->buy_addons_get_addons($lang, $ids);
                if ($result) {
                    $keys = array_keys($result);
                    $size = sizeof($keys) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $var = $result[$keys[$i]];
                        $result[$keys[$i]]["options"] = $var["options"] ? Utility::jdecode($var["options"], true) : [];
                        $result[$keys[$i]]["properties"] = $var["properties"] ? Utility::jdecode($var["properties"], true) : [];

                        $var = $result[$keys[$i]];

                        if ($var["product_type_link"]) {
                            $product_link = Products::get($var["product_type_link"], $var["product_id_link"]);
                            if ($product_link && $product_link["price"]) {
                                foreach ($product_link["price"] as $p_row) {
                                    $var["options"][] = [
                                        'id'          => $p_row["id"],
                                        'name'        => ___("needs/iwwant"),
                                        'period'      => $p_row["period"],
                                        'period_time' => $p_row["time"],
                                        'amount'      => $p_row["amount"],
                                        'cid'         => $p_row["cid"],
                                    ];
                                }
                                $result[$keys[$i]]["options"] = $var["options"];
                            }
                        }

                        $show_by_pp = isset($var["properties"]["show_by_pp"]) ? $var["properties"]["show_by_pp"] : false;

                        if ($show_by_pp && !isset($availableAddons)) $availableAddons = [];

                        if (isset($period) && isset($time) && $period && $show_by_pp) {
                            if ($var["options"]) {
                                foreach ($var["options"] as $opt) {
                                    if ($opt["period_time"] == 0) $opt["period_time"] = 1;
                                    if ($opt["period"] == "none" || ($opt["period"] == $period && $opt["period_time"] == $time)) {
                                        if (!isset($new_result[$keys[$i]])) {
                                            $var["options"] = [];
                                            $new_result[$keys[$i]] = $var;
                                            $new_result[$keys[$i]]["options"][] = $opt;
                                        } else
                                            $new_result[$keys[$i]]["options"][] = $opt;
                                    }
                                }
                            }
                        } else
                            $new_result[$keys[$i]] = $var;
                    }
                }
            }
            return $new_result;
        }


        private function buy_addons($proanse, $udata, $summary = false)
        {
            if ($proanse["status"] == "cancelled") die("Access denied");
            $this->takeDatas("language");
            if (!$summary && DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load("Products");

            $product = Products::get($proanse["type"], $proanse["product_id"]);

            $getAddons = $this->buy_addons_get_addons($product["addons"], ['period' => $proanse['period'], 'time' => $proanse["period_time"]]);

            $lcid = Config::get("general/currency");
            $result = ['status' => "successful"];
            $total_amount = 0;
            $items = [];

            Helper::Load(["Money", "Invoices"]);

            $addons = Filter::POST("addons");
            $addons_values = Filter::POST("addons_values");
            if ($addons && is_array($addons)) {
                foreach ($getAddons as $addon) {
                    if (isset($addons[$addon["id"]]) && Validation::isInt($addons[$addon["id"]])) {
                        $options = $addon["options"];
                        foreach ($options as $k => $v) {
                            if ($v["id"] == $addons[$addon["id"]]) {
                                $amount = Money::exChange($v["amount"], $v["cid"], $lcid);
                                $addon_val = 0;
                                if ($addon["type"] == "quantity") {
                                    if (isset($addons_values[$addon["id"]])) {
                                        $addon_val = $addons_values[$addon["id"]];
                                        $addon_val = (int)Filter::numbers($addon_val);
                                    }
                                    $amount = ($amount * $addon_val);
                                    if ($addon_val < 1) continue;
                                }
                                $total_amount += $amount;
                                $result["data"][] = [
                                    'name'   => $addon["name"] . " - " . $v["name"] . ($addon_val > 0 ? ' x ' . $addon_val : ''),
                                    'amount' => $amount ? Money::formatter_symbol($amount, $lcid, !$addon["override_usrcurrency"]) : ___("needs/free-amount"),
                                ];
                                $items[] = [
                                    'amount'   => $amount,
                                    'name'     => $proanse["name"] . " (#" . $proanse["id"] . ") - " . $addon["name"] . ": " . ($addon_val > 0 ? $addon_val . "x " : '') . $v["name"],
                                    'user_pid' => $proanse["id"],
                                    'options'  => [
                                        'event'      => "AddonOrder",
                                        'event_data' => [
                                            'addon_id'        => $addon["id"],
                                            'option_id'       => $v["id"],
                                            'option_quantity' => $addon_val,
                                        ],
                                    ],
                                ];
                            }
                        }
                    }
                }
            }


            if ($summary) {
                $result["total_amount"] = Money::formatter_symbol($total_amount, $lcid, true);

                echo Utility::jencode($result);

                return true;
            }

            $old_invoices = Invoices::get_order_invoices($proanse["id"]);

            if ($old_invoices) {
                foreach ($old_invoices as $i) {
                    if ($i["status"] != "unpaid") continue;
                    $i_items = Invoices::get_items($i["id"]);
                    if ($i_items) {
                        foreach ($i_items as $it) {
                            if ($it["options"]["event"] == "AddonOrder") {
                                /*
                                echo Utility::jencode([
                                    'status' => "error",
                                    'message' => __("website/account_products/error18"),
                                ]);
                                return false;
                                */
                                Invoices::MakeOperation('delete', $i["id"]);
                                break;
                            }
                        }
                    }
                }
            }


            if (!$items) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/osteps/error2"),
                ]);
                return false;
            }

            $generate = Invoices::bill_generate([
                'user_id' => $udata["id"],
                'status'  => "unpaid",
                'pmethod' => "none",
                'amount'  => $total_amount,
                'cid'     => $lcid,
            ], $items, true);

            if (!$generate && Invoices::$message == "no-user-address") {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/balance/buy-error5"),
                ]);
                return false;
            } elseif (!$generate) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => "Problem!",
                ]);
                return false;
            }

            echo Utility::jencode([
                'status'   => "successful",
                'redirect' => $this->CRLink("ac-ps-detail-invoice", [$generate["id"]]),
            ]);

        }


        private function remove_transfer_service($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load("Events");

            $id = (int)Filter::init("POST/id", "numbers");

            if (!$id) return false;

            $evt = Events::get($id);
            if ($evt["user_id"] != $udata["id"]) return false;

            Events::delete($id);

            User::addAction($udata["id"], 'added', 'A customer has been designated for service transfer', $evt["data"]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("website/account_products/success3"),
            ]);
        }

        private function transfer_service($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");
            $this->takeDatas("language");
            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $email = Filter::init("POST/email", "email");
            $g_password = Filter::init("POST/password", "password");

            if (!$email || !Validation::isEmail($email))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='email']",
                    'message' => __("website/account_products/error14"),
                ]));

            $user_id = User::email_check($email);

            if (!$user_id)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='email']",
                    'message' => __("website/account_products/error15"),
                ]));


            if ($user_id == $udata["id"])
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='email']",
                    'message' => __("website/account_products/error16"),
                ]));

            if (!$g_password)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name=password]",
                    'message' => ___("needs/permission-delete-item-empty-password"),
                ]));

            if (!User::_password_verify("member", $g_password, $udata["password"]))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name=password]",
                    'message' => ___("needs/permission-delete-item-invalid-password"),
                ]));

            Helper::Load(["Orders", "Products"]);

            $options = $proanse["options"];

            $ctoc_service_transfer = false;

            if ($proanse["type"] == "software") {
                $product = Products::get("software", $proanse["product_id"]);
                $ctoc_type = 'software';
                $ctoc_service_transfer = Config::get("options/ctoc-service-transfer/status");
                if ($ctoc_service_transfer) {
                    if (Config::get("options/ctoc-service-transfer/" . $ctoc_type . "/status")) {
                        $ctoc_limit = Config::get("options/ctoc-service-transfer/" . $ctoc_type . "/limit");
                        if (isset($product["options"]["ctoc-service-transfer"])) {
                            $ctoc_service_transfer = $product["options"]["ctoc-service-transfer"]["status"];
                            $ctoc_limit = $product["options"]["ctoc-service-transfer"]["limit"];
                        }
                        if ($ctoc_service_transfer) {
                            Helper::Load("Events");
                            $aprv_l = Events::getList('transaction', 'order', $proanse["id"], 'ctoc-service-transfer', 'approved');
                            $ctoc_count = $aprv_l ? sizeof($aprv_l) : 0;
                            if (strlen($ctoc_limit) > 0 && (int)$ctoc_count >= (int)$ctoc_limit) $ctoc_service_transfer = false;
                        }
                    } else $ctoc_service_transfer = false;
                }
            } elseif ($proanse["type"] == "sms") {
                $product = Products::get("sms", $proanse["product_id"]);
                $ctoc_type = 'sms';
                $ctoc_service_transfer = Config::get("options/ctoc-service-transfer/status");
                if ($ctoc_service_transfer) {
                    if (Config::get("options/ctoc-service-transfer/" . $ctoc_type . "/status")) {
                        $ctoc_limit = Config::get("options/ctoc-service-transfer/" . $ctoc_type . "/limit");
                        if (isset($product["options"]["ctoc-service-transfer"])) {
                            $ctoc_service_transfer = $product["options"]["ctoc-service-transfer"]["status"];
                            $ctoc_limit = $product["options"]["ctoc-service-transfer"]["limit"];
                        }
                        if ($ctoc_service_transfer) {
                            Helper::Load("Events");
                            $aprv_l = Events::getList('transaction', 'order', $proanse["id"], 'ctoc-service-transfer', 'approved');
                            $ctoc_count = $aprv_l ? sizeof($aprv_l) : 0;
                            if (strlen($ctoc_limit) > 0 && (int)$ctoc_count >= (int)$ctoc_limit) $ctoc_service_transfer = false;
                        }
                    } else $ctoc_service_transfer = false;
                }
            } elseif ($proanse["type"] == "hosting") {
                $product = Products::get("hosting", $proanse["product_id"]);
                $ctoc_type = 'hosting';
                $ctoc_service_transfer = Config::get("options/ctoc-service-transfer/status");
                if ($ctoc_service_transfer) {
                    if (Config::get("options/ctoc-service-transfer/" . $ctoc_type . "/status")) {
                        $ctoc_limit = Config::get("options/ctoc-service-transfer/" . $ctoc_type . "/limit");
                        if (isset($product["options"]["ctoc-service-transfer"])) {
                            $ctoc_service_transfer = $product["options"]["ctoc-service-transfer"]["status"];
                            $ctoc_limit = $product["options"]["ctoc-service-transfer"]["limit"];
                        }
                        if ($ctoc_service_transfer) {
                            Helper::Load("Events");
                            $aprv_l = Events::getList('transaction', 'order', $proanse["id"], 'ctoc-service-transfer', 'approved');
                            $ctoc_count = $aprv_l ? sizeof($aprv_l) : 0;
                            if (strlen($ctoc_limit) > 0 && (int)$ctoc_count >= (int)$ctoc_limit) $ctoc_service_transfer = false;
                        }
                    } else $ctoc_service_transfer = false;
                }
            } elseif ($proanse["type"] == "server") {
                $product = Products::get("server", $proanse["product_id"]);
                $ctoc_type = 'server';
                $ctoc_service_transfer = Config::get("options/ctoc-service-transfer/status");
                if ($ctoc_service_transfer) {
                    if (Config::get("options/ctoc-service-transfer/" . $ctoc_type . "/status")) {
                        $ctoc_limit = Config::get("options/ctoc-service-transfer/" . $ctoc_type . "/limit");
                        if (isset($product["options"]["ctoc-service-transfer"])) {
                            $ctoc_service_transfer = $product["options"]["ctoc-service-transfer"]["status"];
                            $ctoc_limit = $product["options"]["ctoc-service-transfer"]["limit"];
                        }
                        if ($ctoc_service_transfer) {
                            Helper::Load("Events");
                            $aprv_l = Events::getList('transaction', 'order', $proanse["id"], 'ctoc-service-transfer', 'approved');
                            $ctoc_count = $aprv_l ? sizeof($aprv_l) : 0;
                            if (strlen($ctoc_limit) > 0 && (int)$ctoc_count >= (int)$ctoc_limit) $ctoc_service_transfer = false;
                        }
                    } else $ctoc_service_transfer = false;
                }
            } elseif ($proanse["type"] == "domain") {
                $ctoc_type = 'domain';
                $ctoc_service_transfer = Config::get("options/ctoc-service-transfer/status");
                if ($ctoc_service_transfer) {
                    if (Config::get("options/ctoc-service-transfer/" . $ctoc_type . "/status")) {
                        $ctoc_limit = Config::get("options/ctoc-service-transfer/" . $ctoc_type . "/limit");
                        if ($ctoc_service_transfer) {
                            Helper::Load("Events");
                            $aprv_l = Events::getList('transaction', 'order', $proanse["id"], 'ctoc-service-transfer', 'approved');
                            $ctoc_count = $aprv_l ? sizeof($aprv_l) : 0;
                            if (strlen($ctoc_limit) > 0 && (int)$ctoc_count >= (int)$ctoc_limit) $ctoc_service_transfer = false;
                        }
                    } else $ctoc_service_transfer = false;
                }
            } elseif ($proanse["type"] == "special") {
                $product = Products::get("special", $proanse["product_id"]);
                $group = Products::getCategory($proanse["type_id"], false, "t1.options");
                $ctoc_service_transfer = Config::get("options/ctoc-service-transfer/status");
                if ($ctoc_service_transfer) {
                    if (isset($group["options"]["ctoc-service-transfer"]) && $group["options"]["ctoc-service-transfer"]["status"]) {
                        $ctoc_limit = $group["options"]["ctoc-service-transfer"]["limit"];
                        if (isset($product["options"]["ctoc-service-transfer"])) {
                            $ctoc_service_transfer = $product["options"]["ctoc-service-transfer"]["status"];
                            $ctoc_limit = $product["options"]["ctoc-service-transfer"]["limit"];
                        }
                        if ($ctoc_service_transfer) {
                            Helper::Load("Events");
                            $aprv_l = Events::getList('transaction', 'order', $proanse["id"], 'ctoc-service-transfer', 'approved');
                            $ctoc_count = $aprv_l ? sizeof($aprv_l) : 0;
                            if (strlen($ctoc_limit) > 0 && (int)$ctoc_count >= (int)$ctoc_limit) $ctoc_service_transfer = false;
                            if ($ctoc_service_transfer) {
                                $ctoc_s_t_list = Events::getList('transaction', 'order', $proanse["id"], 'ctoc-service-transfer', 'pending', $udata["id"]);
                                $this->addData("ctoc_s_t_list", $ctoc_s_t_list);
                            }
                        }
                    } else $ctoc_service_transfer = false;
                }
            }

            if (!$ctoc_service_transfer) return false;

            $from = User::getData($udata["id"], 'id,full_name,email', 'assoc');
            $to = User::getData($user_id, 'id,full_name,email', 'assoc');

            $o_requests = Events::getList('transaction', 'order', $proanse["id"], 'ctoc-service-transfer', 'pending');
            if ($o_requests) {
                foreach ($o_requests as $o_request) {
                    $o_request["data"] = Utility::jdecode($o_request["data"], true);
                    if ($o_request["data"]["to_id"] == $to["id"])
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "input[name='email']",
                            'message' => __("website/account_products/error17"),
                        ]));
                }
            }

            $_data = [
                'order_id'       => $proanse["id"],
                'product_name'   => $proanse["name"],
                'from_id'        => $from["id"],
                'from_full_name' => $from["full_name"],
                'from_email'     => $from["email"],
                'to_id'          => $to["id"],
                'to_full_name'   => $to["full_name"],
                'to_email'       => $to["email"],
            ];

            $evt_id = Events::create([
                'type'     => "transaction",
                'owner'    => "order",
                'owner_id' => $proanse["id"],
                'name'     => "ctoc-service-transfer",
                'status'   => "pending",
                'user_id'  => $udata["id"],
                'data'     => $_data,
            ]);

            Helper::Load("Notification");

            Notification::approve_ctoc_service_transfer($evt_id);

            User::addAction($udata["id"], 'added', 'A customer has been designated for service transfer', $_data);

            echo Utility::jencode([
                'status' => "successful",
                'reload' => true,
            ]);
        }


        private function set_upgrade_product($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $options = $proanse["options"];
            $lang = Bootstrap::$lang->clang;

            if ($proanse["period"] == "none") return false;

            Helper::Load(["Money", "Products", "Orders", "Invoices"]);

            if (!($proanse["type"] == "hosting" || $proanse["type"] == "server" || $proanse["type"] == "special")) return false;

            if ($proanse["type"] == "hosting" && !Config::get("options/product-upgrade/hosting")) return false;
            if ($proanse["type"] == "server" && !Config::get("options/product-upgrade/server")) return false;

            if ($proanse["type"] == "special") {
                $group = Products::getCategory($proanse["type_id"], $lang, "t1.options");
                if ($group && isset($group["options"]["upgrading"]))
                    if (!$group["options"]["upgrading"]) return false;
            }


            $product = Products::get($proanse["type"], $proanse["product_id"], $lang);


            if (!$product)
                $product = [
                    'id'      => $proanse["product_id"],
                    'type'    => $proanse["type"],
                    'type_id' => $proanse["type_id"],
                    'title'   => $proanse["name"],
                ];

            $ordinfo = Orders::period_info($proanse);
            $up_products = $this->upgrade_products($proanse, $product, $ordinfo["remaining-amount"]);

            $sproduct = (int)Filter::init("POST/product_id", "numbers");
            $sprice = (int)Filter::init("POST/pirce_id", "numbers");

            if (!$sproduct)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/error7"),
                ]));

            if (!isset($up_products["prices"][$sproduct][$sprice])) return false;

            $sprice = $up_products["prices"][$sproduct][$sprice];
            $sproduct = Products::get($product["type"], $sproduct, $lang);

            if ($product["id"] == $sproduct["id"]) return false;

            $invoice = Invoices::generate_upgrade($proanse, $product, $sproduct, $sprice, "unpaid");
            if (!$invoice) {
                if (Invoices::$message == "repetition")
                    $errmsg = "error8";
                elseif (Invoices::$message == "no-user-address")
                    $errmsg = "error9";
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/" . $errmsg),
                ]));
            }

            Orders::generate_updown("up", $invoice, $proanse, $product, $sproduct, $sprice);

            User::addAction($udata["id"], "added", "upgrade-request-was-made", [
                'order_id'    => $proanse["id"],
                'old-product' => $product["title"],
                'new-product' => $sproduct["title"],
            ]);

            Helper::Load(["Notification"]);
            Notification::invoice_created($invoice);

            echo Utility::jencode([
                'status'   => "successful",
                'redirect' => $this->CRLink("ac-ps-detail-invoice", [$invoice["id"]]),
            ]);

        }


        private function sms_credit_renewal($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $pid = Filter::init("POST/pid", "numbers");
            if (Validation::isEmpty($pid)) die("Error 1");

            Helper::Load(["Products", "Basket"]);
            $lang = Bootstrap::$lang->clang;
            $product = Products::get("sms", $pid, $lang);
            if (!$product) die("Error 2");

            $opData = [
                'event'          => "RenewalSmsCredit",
                'event_data'     => [
                    'usproduct_id' => $proanse["id"],
                    'product_id'   => $product["id"],
                ],
                'type'           => "sms",
                'id'             => $product["id"],
                'selection'      => $product["price"][0],
                'category'       => __("website/account_products/renewal-sms-credit"),
                'category_route' => null,
            ];

            Basket::set(false, $product["title"] . " - Kredi Yükleme", $opData, false);
            Basket::save();
            echo Utility::jencode([
                'status'   => "successful",
                'redirect' => $this->CRLink("basket"),
            ]);

        }


        private function domain_whois_privacy($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $options = $proanse["options"];

            Helper::Load(["Money", "Basket", "Events", "Notification"]);

            $status = Filter::init("POST/status", "letters");

            if (!isset($options["whois_manage"])) return false;

            $wprivacy = isset($options["whois_privacy"]) && $options["whois_privacy"];
            $whidden_amount = Config::get("options/domain-whois-privacy/amount");
            $whidden_cid = Config::get("options/domain-whois-privacy/cid");

            if ($proanse["module"] != "none" && $proanse["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $proanse["module"])) {
                    $module = new $proanse["module"]();
                    $whidden_amount = $fetchModule["config"]["settings"]["whidden-amount"] ?? false;
                    $whidden_cid = $fetchModule["config"]["settings"]["whidden-currency"] ?? false;
                }
            }

            $whois_privacy_purchase = $whidden_amount > 0.00;

            if ($whois_privacy_purchase) {
                $isAddon = $this->model->db->select("id")->from("users_products_addons");
                $isAddon->where("status", "=", "active", "&&");
                $isAddon->where("owner_id", "=", $proanse["id"], "&&");
                $isAddon->where("addon_key", "=", "whois-privacy");
                $isAddon = $isAddon->build() ? $isAddon->getObject()->id : false;
                if ($isAddon) $whois_privacy_purchase = false;
                else $whois_privacy_purchase = true;
            }


            if ($status == "enable" && $whois_privacy_purchase) {
                $bdata = [
                    'event'          => "ModifyDomainWhoisPrivacy",
                    'event_data'     => [
                        'usproduct_id' => $proanse["id"],
                    ],
                    'period'         => "year",
                    'period_time'    => 1,
                    'amount'         => $whidden_amount,
                    'cid'            => $whidden_cid,
                    'category'       => __("website/account_products/whois-privacy-basket-category"),
                    'category_route' => $this->CRLink("ac-ps-products-t", ['domain']),
                ];
                Basket::set(false, __("admin/orders/whois-privacy-invoice-description", [
                    '{name}' => $proanse["name"],
                ]), $bdata, false);
                Basket::save();

                echo Utility::jencode([
                    'status'   => "successful",
                    'message'  => "go-to-basket",
                    'redirect' => $this->CRLink("basket"),
                ]);

                return false;
            }

            $set_proanse = [];

            if (isset($module) && $module) {

                $modify = $module->modifyPrivacyProtection($options, $status);
                if (!$modify)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/error2", ['{error}' => $module->error]),
                    ]));
            } else {

                $isCreated = Events::isCreated("operation", "order", $proanse["id"], "modify-whois-privacy", "pending");

                if ($isCreated)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/error4"),
                    ]));

                $evID = Events::create([
                    'type'     => "operation",
                    'owner'    => "order",
                    'owner_id' => $proanse["id"],
                    'name'     => "modify-whois-privacy",
                    'data'     => [
                        'status' => $status,
                        'domain' => $options["domain"],
                    ],
                ]);

                if ($evID) Notification::need_manually_transaction($proanse["id"], $evID);

                $set_proanse['unread'] = 0;
            }

            if ($status == "enable") $message = __("website/account_products/success1");
            if ($status == "disable") $message = __("website/account_products/success2");

            $options["whois_privacy"] = $status == "enable";

            $set_proanse['options'] = Utility::jencode($options);

            $this->model->update_product($proanse["id"], $udata["id"], $set_proanse);

            User::addAction($udata["id"], "alteration", "domain-whois-privacy-" . $status . "d", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'redirect' => $this->CRLink("ac-ps-product", [$proanse["id"]]) . "?tab=whois&whois=protection",
                'message'  => $message,
            ]);

        }


        private function domain_renewal($proanse, $udata)
        {
            if (!($proanse["status"] == "active" || $proanse["status"] == "suspended")) die("Access denied");
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $year = Filter::init("POST/period", "numbers");
            if (Validation::isEmpty($year)) die("Error 1");
            if ($year > 50 || $year < 1) die("Error 2");

            $options = $proanse["options"];

            $getTLD = $this->model->getTLD(0, $options["tld"]);

            if (!$getTLD) return false;

            $disable_tlds = ['de'];

            if ($d_tlds = Config::get("options/disable-renewal-tld")) {
                foreach ($d_tlds as $t) if (!in_array($t, $disable_tlds)) $disable_tlds[] = $t;
            }

            //$remaining_day  = DateManager::remaining_day($proanse["duedate"],DateManager::Now("Y-m-d"));


            if (in_array($options["tld"], $disable_tlds)) {
                echo Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/error19"),
                ]);
                return false;
            }


            Helper::Load(["Basket", "Money"]);
            $opData = [
                'event'          => "RenewalDomain",
                'event_data'     => [
                    'usproduct_id' => $proanse["id"],
                    'year'         => $year,
                ],
                'type'           => "domain",
                'id'             => $getTLD["id"],
                'sld'            => $options["name"],
                'tld'            => $options["tld"],
                'period'         => "year",
                'period_time'    => $year,
                'category'       => __("website/osteps/category-domain-renewal"),
                'category_route' => $this->CRLink("ac-ps-products-t", ['domain']),
                'renewal'        => true,
            ];

            if ($udata["dealership"] && $getTLD) {
                $dealership = Utility::jdecode($udata["dealership"], true);
                if ($dealership && isset($dealership["status"]) && $dealership["status"] == "active") {
                    $discounts = $dealership["discounts"];
                    if (isset($discounts["domain"]))
                        $discount_rate = $discounts["domain"];
                    else
                        $discount_rate = 0;

                    if ($discount_rate) {

                        $getAmount = $this->model->get_price("renewal", "tld", $getTLD["id"]);
                        $tld_amount = $getAmount["amount"];

                        $discount_amount = Money::get_discount_amount($tld_amount, $discount_rate);
                        $tld_amount -= $discount_amount;

                        $tld_amount = $tld_amount * $year;

                        $opData["amount"] = $tld_amount;
                        $opData["cid"] = $getAmount["cid"];
                    }
                }
            }

            Basket::set(false, $proanse["name"], $opData, false);
            Basket::save();
            echo Utility::jencode([
                'status'   => "successful",
                'redirect' => $this->CRLink("basket"),
            ]);

        }


        private function domain_transfer_code_submit($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $options = $proanse["options"];

            if ($proanse["module"] != "none" && $proanse["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $proanse["module"])) {
                    $module = new $proanse["module"]();
                }
            }

            if (isset($options["transferlock"]) && $options["transferlock"])
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/submit-transfer-code-error1"),
                ]));

            if (isset($module) && $module && method_exists($module, "getAuthCode")) {

                if (LogManager::MLogCount("domain-transfer-code-" . $proanse["id"], $udata["id"], [
                        'ctime' => DateManager::Now("Y-m-d"),
                    ], true) == 2 || LogManager::SLogCount("domain-transfer-code-" . $proanse["id"], $udata["id"], [
                        'ctime' => DateManager::Now("Y-m-d"),
                    ], true) == 2)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/submit-transfer-code-error2"),
                    ]));

                if (method_exists($module, "getAuthCode")) {
                    $getAuthCode = $module->getAuthCode($options);
                    if (!$getAuthCode)
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/account_products/submit-transfer-code-error3", ['{error}' => $module->error]),
                        ]));

                    Helper::Load(["Notification"]);

                    $submit = Notification::domain_submit_transfer_code($proanse, $getAuthCode);

                    if ($submit != "OK")
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/account_products/submit-transfer-code-error4"),
                        ]));
                }

                User::addAction($udata["id"], "send", "sent-domain-transfer-code", [
                    'domain' => $options["domain"],
                    'id'     => $proanse["id"],
                ]);

                die(Utility::jencode(['status' => "successful"]));
            } else {
                Helper::Load(["Events", "Notification"]);

                $isCreated = Events::isCreated("operation", "order", $proanse["id"], "domain-send-transfer-code", "pending");

                if ($isCreated)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/error4"),
                    ]));

                $evID = Events::create([
                    'type'     => "operation",
                    'owner'    => "order",
                    'owner_id' => $proanse["id"],
                    'name'     => "domain-send-transfer-code",
                    'data'     => [
                        'domain' => $options["domain"],
                    ],
                ]);

                if ($evID) Notification::need_manually_transaction($proanse["id"], $evID);

                User::addAction($udata["id"], "added", "request-to-send-domain-transfer-code", [
                    'domain' => $options["domain"],
                ]);

                $this->model->update_product($proanse["id"], $udata["id"], [
                    'unread' => 0,
                ]);


                die(Utility::jencode(['status' => "successful", 'type' => "request"]));
            }

        }


        private function domain_modify_transferlock($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $options = $proanse["options"];

            if ($proanse["module"] != "none" && $proanse["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $proanse["module"])) {
                    $module = new $proanse["module"]();
                }
            }

            $status = $options["transferlock"] ? "disable" : "enable";

            if (isset($module) && $module && method_exists($module, 'ModifyTransferLock')) {
                $modify = $module->ModifyTransferLock($options, $status);
                if (!$modify)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/modify-transferlock-error1", ['{error}' => $module->error]),
                    ]));
            } else {
                Helper::Load(["Events", "Notification"]);

                $isCreated = Events::isCreated("operation", "order", $proanse["id"], "modify-domain-transferlock", "pending");

                if ($isCreated)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/error4"),
                    ]));

                $evID = Events::create([
                    'type'     => "operation",
                    'owner'    => "order",
                    'owner_id' => $proanse["id"],
                    'name'     => "modify-domain-transferlock",
                    'data'     => [
                        'status' => $status,
                        'domain' => $options["domain"],
                    ],
                ]);

                if ($evID) Notification::need_manually_transaction($proanse["id"], $evID);
            }

            $options["transferlock"] = $status == "enable";
            $options["transferlock_latest_update"] = DateManager::Now();

            $this->model->update_product($proanse["id"], $udata["id"], [
                'options' => Utility::jencode($options),
                'unread'  => 0,
            ]);

            User::addAction($udata["id"], "alteration", "changed-domain-transferlock", [
                'status' => $status,
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode(['status' => "successful"]);


        }


        private function domain_delete_cns($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $options = $proanse["options"];

            $set_proanse = [];

            if ($proanse["module"] != "none" && $proanse["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $proanse["module"])) {
                    $module = new $proanse["module"]();
                }
            }

            $cns_id = (int)Filter::init("POST/id", "numbers");
            if (!$cns_id) return false;

            if (isset($module) && $module) {
                $list = $module->CNSList($options);


                if (!$list) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => $module->error,
                    ]);
                    return false;
                }

                $cns = $list[$cns_id] ?? [];

                if (!$cns) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => "Child name server not found",
                    ]);
                    return false;
                }

                $delete = $module->DeleteCNS($options, $cns["ns"], $cns["ip"]);
                if (!$delete)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/delete-cns-error2", ['{error}' => $module->error]),
                    ]));

                $list = $module->CNSList($options);
                $options["cns_list"] = $list ? $list : [];
            } else {

                if (!isset($options["cns_list"][$cns_id])) return false;

                $cns = $options["cns_list"][$cns_id];


                Helper::Load(["Events", "Notification"]);

                $isCreated = Events::isCreated("operation", "order", $proanse["id"], "modify-domain-cns", "pending");

                if ($isCreated)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/error4"),
                    ]));

                $evID = Events::create([
                    'type'     => "operation",
                    'owner'    => "order",
                    'owner_id' => $proanse["id"],
                    'name'     => "modify-domain-cns",
                    'data'     => [
                        'transaction' => "delete",
                        'ns'          => $cns["ns"],
                        'ip'          => $cns["ip"],
                        'domain'      => $options["domain"],
                    ],
                ]);

                if ($evID) Notification::need_manually_transaction($proanse["id"], $evID);

                $set_proanse["unread"] = 0;

                unset($options["cns_list"][$cns_id]);
            }


            $set_proanse['options'] = Utility::jencode($options);

            $this->model->update_product($proanse["id"], $udata["id"], $set_proanse);

            User::addAction($udata["id"], "delete", "deleted-domain-cns", [
                'cns-name' => $cns["ns"],
                'cns-ip'   => $cns["ip"],
                'name'     => $proanse["name"],
                'id'       => $proanse["id"],
            ]);

            echo Utility::jencode([
                'status' => "successful",
            ]);
        }


        private function domain_modify_cns($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $options = $proanse["options"];

            $set_proanse = [];

            if ($proanse["module"] != "none" && $proanse["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $proanse["module"])) {
                    $module = new $proanse["module"]();
                }
            }

            $id = Filter::init("POST/id", "numbers");
            $ns = Filter::init("POST/ns", "letters_numbers", "\-.");
            $ip = Filter::init("POST/ip", "numbers", ".");

            if (Validation::isEmpty($ns) || Validation::isEmpty($ip) || Validation::isEmpty($id))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/add-cns-error1"),
                ]));

            if (!stristr($ns, $proanse["options"]["domain"]))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/add-cns-error3"),
                ]));

            if (!Validation::isInt($id))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/add-cns-error1"),
                ]));


            if (isset($module) && $module) {

                $list = $module->CNSList($options);


                if (!$list) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => $module->error,
                    ]);
                    return false;
                }

                $cns = $list[$id] ?? [];

                if (!$cns) {
                    echo Utility::jencode([
                        'status'  => "error",
                        'message' => "Child name server not found",
                    ]);
                    return false;
                }


                $modify = $module->ModifyCNS($options, $cns, $ns, $ip);
                if (!$modify)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/modify-cns-error1", ['{error}' => $module->error]),
                    ]));

                $list = $module->CNSList($options);

                $options["cns_list"] = $list ? $list : [];

            } else {
                if (!isset($options["cns_list"]))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/modify-cns-error1", ['{error}' => "CNS Not Found"]),
                    ]));

                $cns_list = $options["cns_list"];
                $keys = array_keys($cns_list);
                if (!in_array($id, $keys))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/modify-cns-error1", ['{error}' => "NS Not Found"]),
                    ]));

                $cns = $cns_list[$id];

                if ($cns["ns"] == $ns && $cns["ip"] == $ip)
                    die(Utility::jencode(['status' => "successful"]));


                Helper::Load(["Events", "Notification"]);

                $isCreated = Events::isCreated("operation", "order", $proanse["id"], "modify-domain-cns", "pending");

                if ($isCreated)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/error4"),
                    ]));

                $evID = Events::create([
                    'type'     => "operation",
                    'owner'    => "order",
                    'owner_id' => $proanse["id"],
                    'name'     => "modify-domain-cns",
                    'data'     => [
                        'transaction' => "edit",
                        'old-ns'      => $cns["ns"],
                        'old-ip'      => $cns["ip"],
                        'new-ns'      => $ns,
                        'new-ip'      => $ip,
                        'domain'      => $options["domain"],
                    ],
                ]);

                if ($evID) Notification::need_manually_transaction($proanse["id"], $evID);

                $set_proanse["unread"] = 0;

                $options["cns_list"][$id] = ['ns' => $ns, 'ip' => $ip];

            }


            $set_proanse['options'] = Utility::jencode($options);


            $this->model->update_product($proanse["id"], $udata["id"], $set_proanse);


            User::addAction($udata["id"], "alteration", "changed-domain-cns", [
                'old_ns' => $cns["ns"],
                'old_ip' => $cns["ip"],
                'new_ns' => $ns,
                'new_ip' => $ip,
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode(['status' => "successful"]);

        }


        private function domain_add_cns($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $options = $proanse["options"];

            $set_proanse = [];

            if ($proanse["module"] != "none" && $proanse["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $proanse["module"])) {
                    $module = new $proanse["module"]();
                }
            }

            $ns = Filter::init("POST/ns", "domain");
            $ip = Filter::init("POST/ip", "ip");

            if (Validation::isEmpty($ns) || Validation::isEmpty($ip))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/add-cns-error1"),
                ]));

            if (!stristr($ns, $proanse["options"]["domain"]))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/add-cns-error3"),
                ]));


            if (isset($module) && $module) {
                $addCNS = $module->addCNS($options, $ns, $ip);
                if (!$addCNS)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/add-cns-error2", [
                            '{error}' => $module->error,
                        ]),
                    ]));

                $list = $module->CNSList($options);

                $options["cns_list"] = $list ? $list : [];
            } else {
                Helper::Load(["Events", "Notification"]);

                $isCreated = Events::isCreated("operation", "order", $proanse["id"], "modify-domain-cns", "pending");

                if ($isCreated)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/error4"),
                    ]));

                $evID = Events::create([
                    'type'     => "operation",
                    'owner'    => "order",
                    'owner_id' => $proanse["id"],
                    'name'     => "modify-domain-cns",
                    'data'     => [
                        'transaction' => "add",
                        'ns'          => $ns,
                        'ip'          => $ip,
                        'domain'      => $options["domain"],
                    ],
                ]);

                if ($evID) Notification::need_manually_transaction($proanse["id"], $evID);

                $set_proanse["unread"] = 0;

                if (!isset($options["cns_list"])) $options["cns_list"] = [];
                $id = sizeof($options["cns_list"]) + 1;
                $options["cns_list"][$id] = ['ns' => $ns, 'ip' => $ip];

            }

            $set_proanse['options'] = Utility::jencode($options);

            $this->model->update_product($proanse["id"], $udata["id"], $set_proanse);

            User::addAction($udata["id"], "added", "added-domain-cns", [
                'ns'     => $ns,
                'ip'     => $ip,
                'name'   => $proanse["name"],
                'id'     => $proanse["id"],
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status'   => "successful",
                'redirect' => $this->CRLink("ac-ps-product", [$proanse["id"]]) . "?tab=dns",
                'message'  => __("website/account_products/added-cns"),
            ]);

        }


        private function domain_modify_dns($proanse, $udata)
        {
            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $options = $proanse["options"];

            $set_proanse = [];

            $module = false;
            if ($proanse["module"] != "none" && $proanse["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $proanse["module"])) {
                    $module = new $proanse["module"]();
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

            if (!($new_dns[0] && $new_dns[1]))
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/error12"),
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


            if (!$modified)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/error5"),
                ]));

            if ($module) {
                $modifyDns = $module->ModifyDns($options, $new_dns);
                if (!$modifyDns)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/modify-dns-error2", [
                            '{error}' => $module->error,
                        ]),
                    ]));
            } else {

                Helper::Load(["Events", "Notification"]);

                $isCreated = Events::isCreated("operation", "order", $proanse["id"], "modify-domain-dns", "pending");

                if ($isCreated)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/error4"),
                    ]));

                $evID = Events::create([
                    'type'     => "operation",
                    'owner'    => "order",
                    'owner_id' => $proanse["id"],
                    'name'     => "modify-domain-dns",
                    'data'     => [
                        'modified' => $modified,
                        'domain'   => $options["domain"],
                    ],
                ]);

                if ($evID) Notification::need_manually_transaction($proanse["id"], $evID);

                $set_proanse['unread'] = 0;

            }

            $set_proanse['options'] = Utility::jencode($options);

            $this->model->update_product($proanse["id"], $udata["id"], $set_proanse);
            User::addAction($udata["id"], "alteration", "changed-domain-dns", [
                'domain' => $options["domain"],
            ]);

            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("website/account_products/dns-changed"),
            ]);


        }


        private function domain_modify_whois($proanse, $udata)
        {

            if ($proanse["status"] != "active") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $options = $proanse["options"];

            $set_proanse = [];

            $module = false;
            if ($proanse["module"] != "none" && $proanse["module"]) {
                if ($fetchModule = Modules::Load("Registrars", $proanse["module"])) {
                    $module = new $proanse["module"]();
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


            $diff1 = $options["whois"];

            if (!isset($diff1["registrant"])) {
                $diff1_n = [];
                foreach ($this->contact_types as $ct) $diff1_n[$ct] = $diff1;
                $diff1 = $diff1_n;
            }


            $diff2 = $data;
            $diff_result = [];

            foreach ($diff2 as $ct => $ct_data) {
                foreach ($ct_data as $k => $v) {
                    if (!($diff1[$ct][$k] == '' && $v == '') && $diff1[$ct][$k] != $v) $diff_result[$ct][$k] = $v;
                }
            }

            if ($h_operations = Hook::run("DomainWhoisChange", ['order' => $proanse, 'whois' => $data]))
                foreach ($h_operations as $h_operation)
                    if ($h_operation && isset($h_operation["error"]) && $h_operation["error"])
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => $h_operation["error"],
                        ]));

            if ($module && method_exists($module, 'ModifyWhois')) {

                $modify = $module->ModifyWhois($options, $data);
                if (!$modify)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/modify-whois-error3", ['{error}' => $module->error]),
                    ]));

            } else {

                Helper::Load(["Events", "Notification"]);

                $isCreated = Events::isCreated("operation", "order", $proanse["id"], "modify-whois-infos", "pending");

                if ($isCreated)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account_products/error4"),
                    ]));

                $evID = Events::create([
                    'type'     => "operation",
                    'owner'    => "order",
                    'owner_id' => $proanse["id"],
                    'name'     => "modify-whois-infos",
                    'data'     => [
                        'modified' => $diff_result,
                        'domain'   => $options["domain"],
                    ],
                ]);

                if ($evID) Notification::need_manually_transaction($proanse["id"], $evID);

                $set_proanse['unread'] = 0;
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
                        foreach ($this->contact_types as $c) {
                            $data[$c]["profile_id"] = $profile_id;
                        }
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

            $change_options = $options;
            $change_options["whois"] = $data;

            $set_proanse['options'] = Utility::jencode($change_options);

            $this->model->update_product($proanse["id"], $udata["id"], $set_proanse);

            User::addAction($udata["id"], "alteration", "changed-domain-whois-infos", [
                'name' => $proanse["name"],
                'id'   => $proanse["id"],
            ]);

            $order = Orders::get($proanse["id"]);

            Hook::run("DomainWhoisChanged", $order);


            echo Utility::jencode([
                'status'  => "successful",
                'message' => __("website/account_products/whois-changed"),
            ]);
        }


        private function canceled_product($proanse, $udata)
        {
            if ($proanse["status"] == "cancelled") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $reason = Filter::init("POST/reason", "text");
            $urgency = Filter::init("POST/urgency", "route");

            if (!($urgency == "now" || $urgency == "period-ending")) return false;

            Helper::Load(["Events"]);

            if (Validation::isEmpty($reason))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "textarea[name=reason]",
                    'message' => __("website/account_products/canceled-err3"),
                ]));

            $previouslyCheck = Events::isCreated("operation", "order", $proanse["id"], "cancelled-product-request", false, false, true);

            if ($previouslyCheck) if (DateManager::strtotime($previouslyCheck["cdate"]) < DateManager::strtotime($proanse["renewaldate"])) $previouslyCheck = false;


            if ($previouslyCheck)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/canceled-err2"),
                ]));


            $data = [
                'reason'  => $reason,
                'urgency' => $urgency,
            ];

            $insert = Events::create([
                'user_id'  => $udata["id"],
                'type'     => "operation",
                'owner'    => "order",
                'owner_id' => $proanse["id"],
                'name'     => "cancelled-product-request",
                'data'     => $data,
            ]);

            if (!$insert)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/canceled-err1"),
                ]));

            User::addAction($udata["id"], "added", "canceled-product-request", [
                'id' => $proanse["id"],
            ]);

            Hook::run("OrderCancellationRequest", $proanse, $previouslyCheck);


            Helper::Load(["Notification"]);
            Notification::cancel_request_created($proanse["id"], $urgency, $reason);

            echo Utility::jencode(['status' => "successful"]);
        }


        private function remove_cancelled_product($proanse, $udata)
        {
            if ($proanse["status"] == "cancelled") die("Access denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load(["Events"]);

            $previouslyCheck = Events::isCreated("operation", "order", $proanse["id"], "cancelled-product-request", false, false, true);

            if ($previouslyCheck) if (DateManager::strtotime($previouslyCheck["cdate"]) < DateManager::strtotime($proanse["renewaldate"])) $previouslyCheck = false;

            if (!$previouslyCheck) return false;

            $delete = Events::delete($previouslyCheck["id"]);

            if (!$delete) return false;

            User::addAction($udata["id"], "added", "Cancellation request has been removed for Order #" . $proanse["id"], [
                'id' => $proanse["id"],
            ]);

            Hook::run("OderRemoveCancellationRequest", $proanse, $previouslyCheck);

            echo Utility::jencode(['status' => "successful"]);
        }


        private function hosting_change_password($proanse, $udata)
        {
            if ($proanse["type"] != "hosting") die("Access Denied");
            if ($proanse["status"] != "active") die("Access Denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $options = isset($proanse["options"]) ? $proanse["options"] : [];
            if (isset($options["server_id"]) && $options["server_id"] != 0) {
                $server = $this->getServer($options["server_id"]);
                if ($server) {
                    if ($server["status"] == "active") {
                        Modules::Load("Servers", $server["type"]);
                        $module_name = $server["type"] . "_Module";
                        $operations = new $module_name($server, $options);
                        if (method_exists($operations, "set_order")) $operations->set_order($proanse);
                        $password = Filter::init("POST/password", "password");

                        if (Validation::isEmpty($password))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "input[name='password']",
                                'message' => __("website/account_products/hosting-change-password-err1"),
                            ]));

                        if (method_exists($operations, 'getPasswordStrength')) {
                            $strength = $operations->getPasswordStrength($password);
                            if (!$strength)
                                die(Utility::jencode([
                                    'status'  => "error",
                                    'for'     => "input[name='password']",
                                    'message' => $operations->error,
                                ]));
                            if ($strength < 65)
                                die(Utility::jencode([
                                    'status'  => "error",
                                    'for'     => "input[name='password']",
                                    'message' => __("website/account_products/password-strength-weak"),
                                ]));
                        }

                        $options = $proanse["options"];
                        $old_pw = $options["config"]["password"];

                        $cry_pass = Crypt::encode($password, Config::get("crypt/user"));

                        $options["config"]["password"] = $cry_pass;
                        if (isset($options["ftp_info"]["username"])) $options["ftp_info"]["password"] = $cry_pass;
                        else {
                            $options["ftp_info"] = [
                                'ip'       => $server["ip"],
                                'host'     => "ftp." . $options["domain"],
                                'username' => $options["config"]["user"],
                                'password' => $cry_pass,
                                'port'     => 21,
                            ];
                        }
                        $this->model->update_product($proanse["id"], $udata["id"], ['options' => Utility::jencode($options)]);


                        if (method_exists($operations, 'change_password'))
                            $changed = $operations->change_password($password);
                        else
                            $changed = $operations->changePassword(false, $password);

                        if (!$changed) {
                            $options["config"]["password"] = $old_pw;
                            if (isset($options["ftp_info"]["username"])) $options["ftp_info"]["password"] = $old_pw;

                            $this->model->update_product($proanse["id"], $udata["id"], ['options' => Utility::jencode($options)]);

                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => __("website/account_products/hosting-change-password-err2", ['{error}' => $operations->error]),
                            ]));
                        }


                        Helper::Load("User");
                        User::addAction($udata["id"], "alteration", "changed-hosting-password", [
                            'order_id'   => $proanse["id"],
                            'order_name' => $proanse["name"],
                        ]);
                        Orders::add_history($udata['id'], $proanse["id"], 'hosting-order-password-changed');
                        echo Utility::jencode(['status' => "successful"]);

                    }
                }
            }
        }


        private function hosting_delete_email_forward($proanse, $udata)
        {
            if ($proanse["type"] != "hosting") die("Access Denied");
            if ($proanse["status"] != "active") die("Access Denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $options = isset($proanse["options"]) ? $proanse["options"] : [];
            if (isset($options["server_id"]) && $options["server_id"] != 0) {
                $server = $this->getServer($options["server_id"]);
                if ($server) {
                    if ($server["status"] == "active") {
                        Modules::Load("Servers", $server["type"]);
                        $module_name = $server["type"] . "_Module";
                        $operations = new $module_name($server, $options);

                        if (method_exists($operations, "set_order")) $operations->set_order($proanse);

                        $dest = Filter::init("POST/dest", "email");
                        $forward = Filter::init("POST/forward", "email");

                        if (Validation::isEmpty($dest))
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => "Select your dest email",
                            ]));

                        if (Validation::isEmpty($forward))
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => "Select your forward email",
                            ]));

                        $forwards = $operations->getForwardsList();
                        $found = false;
                        foreach ($forwards as $fo) if ($fo["dest"] == $dest && $fo["forward"] == $forward) $found = $fo;

                        if (!$found)
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => "Select your dest and forward email",
                            ]));

                        $deleted = $operations->deleteEmailForward($dest, $forward);
                        if (!$deleted)
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => "Delete Failed. Error: " . $operations->error,
                            ]));

                        Helper::Load("User");

                        User::addAction($udata["id"], "delete", "deleted-hosting-email-forward", [
                            'id'      => $proanse["id"],
                            'name'    => $proanse["name"],
                            'email'   => $dest,
                            'forward' => $forward,
                        ]);

                        echo Utility::jencode(['status' => "successful"]);

                    }
                }
            }
        }


        private function hosting_add_new_email_forward($proanse, $udata)
        {
            if ($proanse["type"] != "hosting") die("Access Denied");
            if ($proanse["status"] != "active") die("Access Denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $options = isset($proanse["options"]) ? $proanse["options"] : [];
            if (isset($options["server_id"]) && $options["server_id"] != 0) {
                $server = $this->getServer($options["server_id"]);
                if ($server) {
                    if ($server["status"] == "active") {
                        Modules::Load("Servers", $server["type"]);
                        $module_name = $server["type"] . "_Module";
                        $operations = new $module_name($server, $options);

                        if (method_exists($operations, "set_order")) $operations->set_order($proanse);

                        $email = Filter::init("POST/email", "letters_numbers", "\-_.");
                        $domain = Filter::init("POST/domain", "hclear");
                        $forward = Filter::init("POST/forward", "email");

                        if (Validation::isEmpty($email))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "input[name='email']",
                                'message' => __("website/account_products/hosting-add-email-forward-err1"),
                            ]));

                        if (Validation::isEmpty($domain))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "select[name='domain']",
                                'message' => __("website/account_products/hosting-add-email-forward-err2"),
                            ]));

                        if (Validation::isEmpty($forward))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "input[name='forward']",
                                'message' => __("website/account_products/hosting-add-email-forward-err3"),
                            ]));

                        $domains = $operations->getMailDomains();

                        if (!in_array($domain, $domains))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "select[name='domain']",
                                'message' => __("website/account_products/hosting-add-email-forward-err2"),
                            ]));
                        $dest = $email . "@" . $domain;
                        $added = $operations->addNewEmailForward($domain, $dest, $forward);
                        if (!$added)
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => __("website/account_products/hosting-add-email-forward-err4", ['{error}' => $operations->error]),
                            ]));

                        Helper::Load("User");

                        User::addAction($udata["id"], "added", "added-hosting-email-forward", [
                            'order_id'   => $proanse["id"],
                            'order_name' => $proanse["name"],
                            'email'      => $dest,
                            'forward'    => $forward,
                        ]);

                        echo Utility::jencode(['status' => "successful"]);

                    }
                }
            }
        }


        private function hosting_delete_email($proanse, $udata)
        {
            if ($proanse["type"] != "hosting") die("Access Denied");
            if ($proanse["status"] != "active") die("Access Denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $options = isset($proanse["options"]) ? $proanse["options"] : [];
            if (isset($options["server_id"]) && $options["server_id"] != 0) {
                $server = $this->getServer($options["server_id"]);
                if ($server) {
                    if ($server["status"] == "active") {
                        Modules::Load("Servers", $server["type"]);
                        $module_name = $server["type"] . "_Module";
                        $operations = new $module_name($server, $options);

                        if (method_exists($operations, "set_order")) $operations->set_order($proanse);

                        $email = Filter::init("POST/address", "email");


                        if (Validation::isEmpty($email))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "input[name='username']",
                                'message' => __("website/account_products/hosting-update-email-err1"),
                            ]));


                        $emails = $operations->getEmailList();
                        $found = false;
                        if ($emails && is_array($emails)) foreach ($emails as $e) if ($e["email"] == $email) $found = $e;

                        if (!$found)
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => __("website/account_products/hosting-update-email-err1"),
                            ]));

                        Helper::Load("User");

                        $domain = $found["domain"];
                        $email = $found["username"];

                        $deleted = $operations->deleteEmail($domain, $email);
                        if (!$deleted)
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => __("website/account_products/hosting-delete-email-err1", ['{error}' => $operations->error]),
                            ]));

                        User::addAction($udata["id"], "delete", "deleted-hosting-email", [
                            'id'    => $proanse["id"],
                            'name'  => $proanse["name"],
                            'email' => $email,
                        ]);

                        echo Utility::jencode(['status' => "successful"]);

                    }
                }
            }
        }


        private function hosting_update_email($proanse, $udata)
        {
            if ($proanse["type"] != "hosting") die("Access Denied");
            if ($proanse["status"] != "active") die("Access Denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $options = isset($proanse["options"]) ? $proanse["options"] : [];
            if (isset($options["server_id"]) && $options["server_id"] != 0) {
                $server = $this->getServer($options["server_id"]);
                if ($server) {
                    if ($server["status"] == "active") {
                        Modules::Load("Servers", $server["type"]);
                        $module_name = $server["type"] . "_Module";
                        $operations = new $module_name($server, $options);

                        if (method_exists($operations, "set_order")) $operations->set_order($proanse);

                        $password = Filter::init("POST/password", "password");
                        $password_a = Filter::init("POST/password_again", "password");
                        $quota = Filter::init("POST/quota", "numbers");
                        $unlimited = Filter::init("POST/unlimited", "rnumbers");
                        $quota = $unlimited == 1 ? $quota = 0 : $quota;
                        $email = Filter::init("POST/email", "email");


                        if (Validation::isEmpty($email))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "input[name='username']",
                                'message' => __("website/account_products/hosting-update-email-err1"),
                            ]));


                        /*if(Validation::isEmpty($password_a))
                            die(Utility::jencode([
                                'status' => "error",
                                'for' => "input[name='password_again']",
                                'message' => __("website/account_products/hosting-add-new-email-err3"),
                            ]));

                        if($password_a != $password)
                            die(Utility::jencode([
                                'status' => "error",
                                'for' => "input[name='password_again']",
                                'message' => __("website/account_products/hosting-add-new-email-err4"),
                            ]));*/

                        $emails = $operations->getEmailList();
                        $found = false;
                        if ($emails && is_array($emails)) foreach ($emails as $e) if ($e["email"] == $email) $found = $e;

                        if (!$found)
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => __("website/account_products/hosting-update-email-err1"),
                            ]));

                        Helper::Load("User");

                        $email = $found["username"];
                        $domain = $found["domain"];

                        if (!Validation::isEmpty($password)) {
                            if (method_exists($operations, 'getPasswordStrength')) {
                                $strength = $operations->getPasswordStrength($password);
                                if ($strength < 65)
                                    die(Utility::jencode([
                                        'status'  => "error",
                                        'for'     => "input[name='password']",
                                        'message' => __("website/account_products/password-strength-weak"),
                                    ]));
                            }
                            $setPass = $operations->setPassword($domain, $email, $password);
                            if (!$setPass)
                                die(Utility::jencode([
                                    'status'  => "error",
                                    'for'     => "input[name='password']",
                                    'message' => __("website/account_products/hosting-update-email-err2", ['{error}' => $operations->error]),
                                ]));
                            User::addAction($udata["id"], "alteration", "changed-hosting-email-password", [
                                'order_id'   => $proanse["id"],
                                'order_name' => $proanse["name"],
                                'email'      => $email . "@" . $domain,
                            ]);
                        }

                        if (($quota != 0 && $quota != '' && Validation::isInt($quota) && $unlimited == 0 && $quota != $found["limit_mb"]) || ($unlimited == 1 && $found["limit"] != "unlimited")) {
                            $setQuota = $operations->setQuota($domain, $email, $quota, $unlimited);
                            if (!$setQuota)
                                die(Utility::jencode([
                                    'status'  => "error",
                                    'for'     => "input[name='quota']",
                                    'message' => __("website/account_products/hosting-update-email-err3", ['{error}' => $operations->error]),
                                ]));
                            User::addAction($udata["id"], "alteration", "changed-hosting-email-quota", [
                                'id'    => $proanse["id"],
                                'name'  => $proanse["name"],
                                'email' => $email,
                            ]);
                        }

                        echo Utility::jencode([
                            'status'  => "successful",
                            'message' => __("website/account_products/hosting-update-email-updated"),
                        ]);

                    }
                }
            }
        }


        private function hosting_add_new_email($proanse, $udata)
        {
            if ($proanse["type"] != "hosting") die("Access Denied");
            if ($proanse["status"] != "active") die("Access Denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $options = isset($proanse["options"]) ? $proanse["options"] : [];
            if (isset($options["server_id"]) && $options["server_id"] != 0) {
                $server = $this->getServer($options["server_id"]);
                if ($server) {
                    if ($server["status"] == "active") {
                        Modules::Load("Servers", $server["type"]);
                        $module_name = $server["type"] . "_Module";
                        $operations = new $module_name($server, $options);

                        if (method_exists($operations, "set_order")) $operations->set_order($proanse);

                        $username = Filter::init("POST/username", "letters_numbers", "\-_.");
                        $domain = Filter::init("POST/domain", "hclear");
                        $password = Filter::init("POST/password", "password");
                        $password_a = Filter::init("POST/password_again", "password");
                        $quota = Filter::init("POST/quota", "numbers");
                        $unlimited = Filter::init("POST/unlimited", "rnumbers");

                        if ($quota == '' || !Validation::isInt($quota) || $quota < 0 || $quota == 0)
                            $quota = 250;

                        if (Validation::isEmpty($username))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "input[name='username']",
                                'message' => __("website/account_products/hosting-add-new-email-err1"),
                            ]));

                        if (Validation::isEmpty($password))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "input[name='password']",
                                'message' => __("website/account_products/hosting-add-new-email-err2"),
                            ]));

                        if (method_exists($operations, 'getPasswordStrength')) {
                            $strength = $operations->getPasswordStrength($password);
                            if ($strength < 65)
                                die(Utility::jencode([
                                    'status'  => "error",
                                    'for'     => "input[name='password']",
                                    'message' => __("website/account_products/password-strength-weak"),
                                ]));
                        }

                        $domains = $operations->getMailDomains();

                        if (!in_array($domain, $domains))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "select[name='domain']",
                                'message' => __("website/account_products/hosting-add-new-email-err6"),
                            ]));


                        $added = $operations->addNewEmail($username, $domain, $password, $quota, $unlimited);

                        if (!$added)
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => __("website/account_products/hosting-add-new-email-err5", ['{error}' => $operations->error]),
                            ]));

                        Helper::Load("User");
                        User::addAction($udata["id"], "added", "added-hosting-new-email", [
                            'order_id'   => $proanse["id"],
                            'order_name' => $proanse["name"],
                            'email'      => $username . "@" . $domain,
                        ]);

                        echo Utility::jencode(['status' => "successful"]);

                    }
                }
            }
        }


        private function new_origin_request($proanse, $udata)
        {
            if ($proanse["type"] != "sms") die("Access Denied");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $this->takeDatas("language");

            $origin = Filter::init("POST/origin", "noun");
            $attachments = Filter::FILES("attachments");
            $length = Utility::strlen($origin);


            if ($length > 11 || $length < 1)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='origin']",
                    'message' => __("website/account_products/send-origin-error1"),
                ]));

            if ($this->model->check_origin_name($origin, $proanse["id"]))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='origin']",
                    'message' => '<strong>Bu Başlık Zaten Kayıtlıdır.</strong> <br>"Mevcut Başlıklar" listesindeki "Evrak Yükle" butonu üzerinden işlem sağlayabilirsiniz.',
                ]));


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

                if ($upload->operands) $attachments = Utility::jencode($upload->operands);
            } else
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='attachments[]']",
                    'message' => __("website/account_products/send-origin-error2"),
                ]));

            $added = $this->model->new_origin_request([
                'user_id'     => $udata["id"],
                'pid'         => $proanse["id"],
                'name'        => $origin,
                'ctime'       => DateManager::Now(),
                'attachments' => $attachments,
                'status'      => "waiting",
            ]);

            Helper::Load(["User", "Notification"]);
            User::addAction($udata["id"], "added", "added-new-sms-origin", [
                'order_id' => $proanse["id"],
                'name'     => $origin,
            ]);

            Notification::sms_origin_request_received($proanse["id"], $origin);

            echo Utility::jencode(['status' => "successful"]);
        }

        private function add_origin_attachment($proanse, $udata)
        {
            if ($proanse["type"] != "sms") die("Access Denied");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $this->takeDatas("language");

            $origin = (int)Filter::init("POST/id", "numbers");
            $attachments = Filter::FILES("files");

            $get_origin = $this->model->get_origin($origin, $proanse["id"]);

            if (!$get_origin) die();


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

                if ($upload->operands) $attachments = $upload->operands;
            } else
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "input[name='attachments[]']",
                    'message' => __("website/account_products/send-origin-error2"),
                ]));

            $get_attachments = $get_origin["attachments"] ? Utility::jdecode($get_origin["attachments"], true) : [];

            foreach ($attachments as $attachment) $get_attachments[] = $attachment;

            $this->model->set_origin($origin, [
                'attachments' => $get_attachments ? Utility::jencode($get_attachments) : '',
                'status'      => 'waiting',
                'unread'      => 0,
            ]);


            echo Utility::jencode([
                'status'   => "successful",
                'redirect' => $this->CRLink("ac-ps-product", [$proanse["id"]]) . "?tab=3",
            ]);
        }


        private function submit_sms($proanse, $udata)
        {
            if ($proanse["type"] != "sms" || $proanse["status"] != "active") die("Access Denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $message = Filter::init("POST/message", "dtext");
            $numbers = Filter::init("POST/numbers", "text");
            $origin = Filter::init("POST/origin", "numbers");

            if (Validation::isEmpty($message))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "textarea[name='message']",
                    'message' => __("website/account_products/preview-error3"),
                ]));

            $count = 0;
            if (Validation::isEmpty($numbers))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "textarea[name='numbers']",
                    'message' => __("website/account_products/submit-sms-error2"),
                ]));

            if (Validation::isEmpty($origin) || !Validation::isInt($origin))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='origin']",
                    'message' => __("website/account_products/preview-error1"),
                ]));

            $getOrigin = $this->model->getOrigin($udata["id"], $proanse["id"], $origin);
            if (!$getOrigin)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "select[name='origin']",
                    'message' => __("website/account_products/preview-error1"),
                ]));

            $black_list = isset($proanse["options"]["black_list"]) ? $proanse["options"]["black_list"] : false;
            $black_nums = [];
            if ($black_list != '') {
                $exps = explode(",", $black_list);
                if ($exps && is_array($exps) && sizeof($exps) > 0) {
                    foreach ($exps as $ex) {
                        $ex = Utility::short_text(Filter::numbers($ex), 0, 20);
                        if ($ex != '' && !in_array($ex, $black_nums)) $black_nums[] = $ex;
                    }
                }
            }

            $exps = explode("\n", $numbers);
            $nums = [];
            if ($exps && is_array($exps) && sizeof($exps) > 0) {
                foreach ($exps as $ex) {
                    $ex = Utility::short_text(Filter::numbers($ex), 0, 20);
                    if ($ex != '' && !in_array($ex, $nums) && !in_array($ex, $black_nums)) $nums[] = $ex;
                }
                $count = sizeof($nums);
            }

            if (!isset($count) || (isset($count) && $count < 1))
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => "textarea[name='numbers']",
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
                        }
                    }
                    if (!$find) $credit = $last["credit"];
                }
            }

            $options = $proanse["options"];

            $total_credit = $credit * $count;

            Modules::Load("SMS", $proanse["module"]);
            $mname = $proanse["module"];
            $sms = new $mname($proanse["options"]["config"]);

            $balance = $sms->getBalance();

            if (is_bool($balance) && !$balance)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/sms-balance-info-not-available"),
                ]));

            if ($balance != $options["balance"]) {
                $options["balance"] = $balance;
                $this->model->update_product($proanse["id"], $udata["id"], [
                    'options' => Utility::jencode($options),
                ]);
            }

            if ($balance < $total_credit)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/dont-have-sms-credit"),
                ]));

            if (property_exists($sms, "prevent_transmission_to_intl")) $sms->prevent_transmission_to_intl = true;
            $sended = $sms->body($message)->title($getOrigin)->AddNumber($nums)->submit();
            if (!$sended)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/sending-failed", [
                        '{error}' => $sms->getError(),
                    ]),
                ]));


            $rID = $sms->getReportID();
            $data = Utility::jencode([
                'report_id'    => $rID,
                'length'       => $length,
                'count'        => $count,
                'credit'       => $credit,
                'total_credit' => $total_credit,
            ]);
            LogManager::Sms_Log($udata["id"], "send-sms", $sms->getTitle(), $sms->getBody(), implode(",", $sms->getNumbers()), $data, 0, "users_products", $proanse["id"]);

            Helper::Load("User");
            User::addAction($udata["id"], "info", "send-sms");
            echo Utility::jencode(['status' => "successful"]);
        }


        private function update_cancel_link($proanse, $udata)
        {
            if ($proanse["type"] != "sms") die("Access Denied");

            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $options = $proanse["options"];
            $status = Filter::init("POST/status", "rnumbers");
            if ($status == 1 || $status == 0) {
                $status = (bool)$status;
                $options["config"]["show_cancel_link"] = $status;
                $options = Utility::jencode($options);
                $updated = $this->model->update_product($proanse["id"], $udata["id"], ['options' => $options]);
                if (!$updated)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => "Unknown error",
                    ]));

                Helper::Load("User");
                User::addAction($udata["id"], "alteration", "updated-sms-cancel-link");

                echo Utility::jencode(['status' => "successful"]);

            } else
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Unknown error",
                ]));
        }


        private function get_sms_credit($proanse, $udata)
        {
            if ($proanse["type"] != "sms") die("Access Denied");
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "successful",
                    'balance' => 456,
                ]));

            $options = $proanse["options"];

            Modules::Load("SMS", $proanse["module"]);
            $config = isset($proanse["options"]["config"]) ? $proanse["options"]["config"] : [];

            if (!$config) return false;

            $mname = $proanse["module"];
            $sms = new $mname($config);
            $balance = $sms->getBalance();

            if (is_bool($balance) && !$balance)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/sms-balance-info-not-available"),
                ]));

            echo Utility::jencode([
                'status'  => "successful",
                'balance' => $balance,
            ]);

            if (!isset($options["balance"]) || $balance != $options["balance"]) {
                $options["balance"] = $balance;
                $options = Utility::jencode($options);
                $this->model->update_product($proanse["id"], $udata["id"], ['options' => $options]);
            }
        }


        private function get_sms_report($proanse, $udata)
        {
            if ($proanse["type"] != "sms") die("Access Denied");
            $this->takeDatas("language");

            if (DEMO_MODE) die(Utility::jencode(['status' => "error"]));

            $id = (int)Filter::init("POST/id", "numbers");
            $reportd = $this->model->get_report($id, $proanse["id"]);
            if (!$reportd) die();

            Modules::Load("SMS", $proanse["module"]);
            $config = isset($proanse["options"]["config"]) ? $proanse["options"]["config"] : [];

            if (!$config) return false;

            $mname = $proanse["module"];
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


        private function add_new_group_submit($proanse, $udata)
        {
            if ($proanse["type"] != "sms") die("Access Denied");
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
                    'message' => __("website/account_products/add-new-group-err1"),
                ]));


            $added = $this->model->add_new_group([
                'user_id' => $udata["id"],
                'pid'     => $proanse["id"],
                'name'    => $name,
                'ctime'   => DateManager::Now(),
            ]);
            if (!$added)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/add-new-group-err2"),
                ]));

            Helper::Load("User");
            User::addAction($udata["id"], "added", "added-new-sms-group");
            echo Utility::jencode(['status' => "successful"]);

        }


        private function change_group_numbers($proanse, $udata)
        {
            if ($proanse["type"] != "sms") die("Access Denied");
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
                    'message' => __("website/account_products/please-select-group"),
                ]));


            $count = 0;
            if (!Validation::isEmpty($numbers)) {
                $exps = explode("\n", $numbers);
                $nums = [];
                if ($exps && is_array($exps) && sizeof($exps) > 0) {
                    foreach ($exps as $ex) {
                        $ex = Utility::short_text(Filter::numbers($ex), 0, 20);
                        if ($ex != '' && !in_array($ex, $nums)) $nums[] = $ex;
                    }
                    $count = sizeof($nums);
                    $numbers = $count > 0 ? implode(",", $nums) : null;
                } else
                    $numbers = null;
            } else
                $numbers = null;

            $check = $this->model->group_check($proanse["id"], $group, $udata["id"]);
            if (!$check)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/change-group-numbers-err1"),
                ]));

            $changed = $this->model->change_group_numbers($proanse["id"], $group, $udata["id"], $numbers);

            if (!$changed)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/change-group-numbers-err1"),
                ]));
            Helper::Load("User");
            User::addAction($udata["id"], "alteration", "changed-group-numbers");
            echo Utility::jencode([
                'status' => "successful",
                'count'  => $count,
            ]);
        }


        private function delete_group($proanse, $udata)
        {
            if ($proanse["type"] != "sms") die("Access Denied");
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
                    'message' => __("website/account_products/delete-group-err1"),
                ]));

            $check = $this->model->group_check($proanse["id"], $id, $udata["id"]);
            if (!$check)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/delete-group-err2"),
                ]));


            $deleted = $this->model->delete_group($proanse["id"], $id, $udata["id"]);
            if (!$deleted)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/delete-group-err2"),
                ]));
            Helper::Load("User");
            User::addAction($udata["id"], "delete", "deleted-sms-group");

            echo Utility::jencode(['status' => "successful"]);
        }


        private function update_black_list($proanse, $udata)
        {
            if ($proanse["type"] != "sms") die("Access Denied");
            $this->takeDatas("language");
            $numbers = Filter::init("POST/numbers", "text");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            $count = 0;
            if (!Validation::isEmpty($numbers)) {
                $exps = explode("\n", $numbers);
                $nums = [];
                if ($exps && is_array($exps) && sizeof($exps) > 0) {
                    foreach ($exps as $ex) {
                        $ex = Utility::short_text(Filter::numbers($ex), 0, 20);
                        if ($ex != '' && !in_array($ex, $nums)) $nums[] = $ex;
                    }
                    $count = sizeof($nums);
                    $numbers = $count > 0 ? implode(",", $nums) : null;
                } else
                    $numbers = null;
            } else
                $numbers = null;

            $options = $proanse["options"];
            $options["black_list"] = $numbers;
            $options = Utility::jencode($options);
            $update = $this->model->update_product($proanse["id"], $udata["id"], ['options' => $options]);
            if (!$update)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/account_products/update-black-list-err1"),
                ]));
            Helper::Load("User");
            User::addAction($udata["id"], "alteration", "updated-sms-black-list");
            echo Utility::jencode(['status' => "successful"]);
        }


        private function get_reports($uid = 0, $proanse = [])
        {
            $pid = $proanse["id"];
            $data = $this->model->get_reports($uid, $pid);
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
                    $data[$keys[$i]]["short_content"] = Utility::short_text($var["content"], 0, 41, true);
                    if ($data[$keys[$i]]["data"]["length"] < 41) $data[$keys[$i]]["content"] = null;
                }
            }
            return $data;
        }


        private function getServer($id = 0)
        {
            $data = $this->model->getServer($id);
            if ($data) $data["password"] = Crypt::decode($data["password"], Config::get("crypt/user"));
            return $data;
        }


        private function sms_credit_list()
        {
            $lang = Bootstrap::$lang->clang;
            $data = $this->model->get_sms_products($lang);
            if ($data) {
                $keys = array_keys($data);
                $size = sizeof($keys) - 1;
                for ($i = 0; $i <= $size; $i++) {
                    $var = $data[$keys[$i]];
                    $data[$keys[$i]]["price"] = $this->model->get_price("sale", "products", $var["id"], $lang);
                }
            }
            return $data;
        }


        private function upgrade_products($order = [], $product = [], $remaining_amount = false)
        {
            Helper::Load("Products");
            return Products::upgrade_products($order, $product, $remaining_amount);
        }


        private function detail_main()
        {
            $id = (isset($this->params[1])) ? Filter::init($this->params[1], "rnumbers") : false;
            if (!$id) return false;
            $udata = UserManager::LoginData("member");


            $address = AddressManager::getAddress(0, $udata["id"]);
            $udata = array_merge($udata, User::getData($udata["id"], "name,surname,full_name,company_name,email,phone", "array"));

            $udata["address"] = $address;

            $visibility_balance = false;

            $balanceModule = Modules::Load("Payment", "Balance", true);
            if ($balanceModule) $visibility_balance = $balanceModule["config"]["settings"]["status"];

            $this->addData("visibility_balance", $visibility_balance);

            $udata = array_merge($udata, User::getInfo($udata["id"], ["dealership", "gsm_cc", "gsm"]));
            $proanse = $this->model->getProanSe($udata["id"], $id);
            if (!$proanse) die(Utility::redirect($this->CRLink("ac-ps-products")));
            $proanse["options"] = ($proanse["options"] != '') ? Utility::jdecode($proanse["options"], true) : [];
            $type = $proanse["type"];
            $clang = Bootstrap::$lang->clang;

            if (!($proanse["status"] == "inprocess" || $proanse["status"] == "active" || $proanse["status"] == "suspended")) {
                Utility::redirect($this->CRLink("ac-ps-products"));
                exit();
            }

            $hookProperties = Hook::run("changePropertyToAccountOrderDetails", $proanse);
            if ($proanse["type"] != "domain" && $hookProperties) foreach ($hookProperties as $hook) if (is_array($hook) && $hook) $proanse = $hook;

            $controller_link = $this->CRLink("ac-ps-product", [$proanse["id"]]);

            if (Filter::REQUEST("operation") || Filter::REQUEST("operation")) {

                if ($type == "domain") {
                    $isProduct = $this->model->getTLD($proanse["product_id"], null, $clang);
                    if ($isProduct) {
                        if (!isset($proanse["options"]["dns_manage"]))
                            $proanse["options"]["dns_manage"] = $isProduct["dns_manage"] == 1;
                        if (!isset($proanse["options"]["whois_manage"]))
                            $proanse["options"]["whois_manage"] = $isProduct["whois_privacy"] == 1;
                        if (!isset($proanse["options"]["epp_code_manage"]))
                            $proanse["options"]["epp_code_manage"] = $isProduct["epp_code"] == 1;
                    }
                }

                return $this->DetailMain_POST($proanse, $udata);
            }

            $this->model->db->update("events")
                ->set(['unread' => 1, 'status' => 'approved'])
                ->where("type", "=", "notification", "&&")
                ->where("owner", "=", "order", "&&")
                ->where("owner_id", "=", $proanse["id"], "&&")
                ->where("unread", "=", "0")
                ->save();

            Helper::Load(["Money", "Invoices"]);

            $invoice = [];

            if ($proanse["invoice_id"]) {
                $invoice = Invoices::get($proanse["invoice_id"], ['select' => 'id,status,number,total,tax,taxrate,taxation_type,subtotal']);
                if ($invoice) $invoice["detail_link"] = $this->CRLink("ac-ps-detail-invoice", [$invoice["id"]]);
            }

            $this->addData("invoice", $invoice);


            $currency_symbols = [];
            foreach (Money::getCurrencies() as $currency) {
                $symbol = $currency["prefix"] != '' ? trim($currency["prefix"]) : trim($currency["suffix"]);
                if (!$symbol) $symbol = $currency["code"];
                $currency_symbols[] = $symbol;
            }
            $this->addData("currency_symbols", $currency_symbols);

            if ($type == "sms" && $proanse["status"] != "active") die("Access Denied!");

            if (isset($proanse["options"]["block_access"]) && $proanse["options"]["block_access"]) die("Access Denied!");

            $this->addData("pname", "account_products");
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
            $meta = __("website/account_products/detail-meta");
            $meta["title"] = __("website/account_products/detail-meta/title", ['{name}' => $proanse["name"]]);
            $this->addData("meta", $meta);
            $this->addData("clang", $clang);
            $options = $proanse["options"];


            $this->addData("options", $options);

            Helper::Load("Money");

            $proanse_amount = $proanse["amount"];

            $tax_rate = Invoices::getTaxRate();
            $taxation = Invoices::getTaxation();
            $inv = Invoices::get_last_invoice($proanse["id"], '', 't2.taxrate');

            if ($inv && $inv["taxrate"] > 0.00) $tax_rate = $inv["taxrate"];
            if (!$taxation) $tax_rate = 0;

            if ($tax_rate > 0.00) {
                $tax = Money::get_tax_amount($proanse_amount, $tax_rate);
                $proanse_amount += $tax;
            }


            $this->addData("amount", Money::formatter_symbol($proanse_amount, $proanse["amount_cid"]));

            $breadcrumb = [
                [
                    'link'  => $this->CRLink("my-account"),
                    'title' => __("website/account/breadcrumb-dashboard"),
                ],
                [
                    'link'  => $this->CRLink("ac-ps-products"),
                    'title' => __("website/account_products/page-title-type-all"),
                ],
            ];

            $this->addData("header_title", $proanse["name"]);

            $d_f_m = Config::get("modules/card-storage-module");

            if ($d_f_m && $d_f_m != "none") {
                $stored_cards = Models::$init->db->select("id")->from("users_stored_cards");
                $stored_cards->where("user_id", "=", $udata["id"]);
                $stored_cards = $stored_cards->build() ? $stored_cards->fetch_assoc() : false;
                $this->addData("stored_cards", $stored_cards);
            }

            Helper::Load("Events");

            $p_cancellation = Events::isCreated("operation", "order", $proanse["id"], "cancelled-product-request", false, false, true);

            if ($p_cancellation) if (DateManager::strtotime($p_cancellation["cdate"]) < DateManager::strtotime($proanse["renewaldate"])) $p_cancellation = false;


            $this->addData("p_cancellation", $p_cancellation);

            $subscription = $proanse["subscription_id"] > 0 ? Orders::get_subscription($proanse["subscription_id"]) : false;

            if ($subscription) {
                $m_name = $subscription["module"];
                $mod = Modules::Load("Payment", $m_name, true);
                $m_name = $mod["lang"]["invoice-name"];
                $subscription["module"] = $m_name;
                $this->addData("subscription", $subscription);
            }


            $this->addData("gtype", $proanse["type"]);

            if ($proanse["type"] == "software") {


                array_push($breadcrumb, [
                    'link'  => $this->CRLink("ac-ps-products-t", ["software"]),
                    'title' => __("website/account_products/page-title-type-software"),
                ], [
                    'link'  => null,
                    'title' => $proanse["name"],
                ]);

                Helper::Load(["Orders", "Products"]);

                $addons = Orders::addons($proanse["id"]);
                $requirements = Orders::requirements($proanse["id"]);
                $product = Products::get("software", $proanse["product_id"], $clang);

                if (isset($product["options"]["renewal_selection_hide"]) && $product["options"]["renewal_selection_hide"])
                    $proanse["options"]["disable_renewal"] = true;

                $this->addData("product", $product);

                if ($addons) $this->addData("addons", $addons);
                if ($requirements) $this->addData("requirements", $requirements);


                $delivery_file = isset($proanse["options"]["delivery_file"]) ? $proanse["options"]["delivery_file"] : false;
                $product_file = isset($product["options"]["download_file"]) ? $product["options"]["download_file"] : false;
                $download_link = isset($product["options"]["download_link"]) ? $product["options"]["download_link"] : false;

                if ($delivery_file)
                    $download_file = RESOURCE_DIR . "uploads" . DS . "orders" . DS . $delivery_file;
                elseif ($product_file)
                    $download_file = RESOURCE_DIR . "uploads" . DS . "products" . DS . $product_file;
                else
                    $download_file = false;

                $hooks = Hook::run("OrderDownload", $proanse, $product, [
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
                    $this->addData("download_link", $this->CRLink("download-id", ["order", $proanse["id"]]));


                $controller_link = $this->CRLink("ac-ps-product", [$proanse["id"]]);
                $this->addData("links", [
                    'controller' => $controller_link,
                ]);
                $this->addData("proanse", $proanse);
                $this->addData("panel_breadcrumb", $breadcrumb);

                $change_domain = Config::get("options/software-change-domain/status");
                $change_domain_limit = Config::get("options/software-change-domain/limit");


                if (isset($product["options"]["change-domain"])) $change_domain = $product["options"]["change-domain"];
                if (isset($options["change-domain"])) $change_domain = $options["change-domain"];
                if ($change_domain) {
                    Helper::Load("Events");
                    $apply_changes = Events::getList('log', 'order', $proanse["id"], 'change-domain');
                    $apply_count = $apply_changes ? sizeof($apply_changes) : 0;

                    $this->addData("change_domain_has_expired", strlen($change_domain_limit) > 0 && $apply_count >= (int)$change_domain_limit);
                    $this->addData("change_domain_used", $apply_count);
                    $this->addData("change_domain_limit", $change_domain_limit);
                }
                $this->addData("change_domain", $change_domain);

                $ctoc_type = 'software';
                $ctoc_service_transfer = Config::get("options/ctoc-service-transfer/status");
                if ($ctoc_service_transfer) {
                    if (Config::get("options/ctoc-service-transfer/" . $ctoc_type . "/status")) {
                        $ctoc_limit = Config::get("options/ctoc-service-transfer/" . $ctoc_type . "/limit");
                        if (isset($product["options"]["ctoc-service-transfer"])) {
                            $ctoc_service_transfer = $product["options"]["ctoc-service-transfer"]["status"];
                            $ctoc_limit = $product["options"]["ctoc-service-transfer"]["limit"];
                        }
                        if ($ctoc_service_transfer) {
                            Helper::Load("Events");
                            $aprv_l = Events::getList('transaction', 'order', $proanse["id"], 'ctoc-service-transfer', 'approved');
                            $ctoc_count = $aprv_l ? sizeof($aprv_l) : 0;
                            $this->addData("ctoc_has_expired", strlen($ctoc_limit) > 0 && (int)$ctoc_count >= (int)$ctoc_limit);
                            $this->addData("ctoc_used", $ctoc_count);
                            $this->addData("ctoc_limit", $ctoc_limit);
                            if ($ctoc_service_transfer) {
                                $ctoc_s_t_list = Events::getList('transaction', 'order', $proanse["id"], 'ctoc-service-transfer', 'pending', $udata["id"]);
                                $this->addData("ctoc_s_t_list", $ctoc_s_t_list);
                            }
                        }
                    } else $ctoc_service_transfer = false;
                }

                $this->addData("ctoc_service_transfer", $ctoc_service_transfer);

                if ($product && $img = $this->model->get_picture("software", $product["id"], "order"))
                    $options["product_image"] = Utility::image_link_determiner($img, Config::get("pictures/products/folder"));
                $this->addData("options", $options);

                $product_addon_ids = isset($product["addons"]) ? $product["addons"] : '';
                $purchased_ids = [];
                $product_addons = [];
                if ($addons) foreach ($addons as $k => $v) $purchased_ids[] = $v["addon_id"];

                if ($product_addon_ids) {
                    $product_addon_ids = explode(",", $product_addon_ids);
                    foreach ($product_addon_ids as $a) {
                        $addon = Products::addon($a, $clang);
                        $property = $addon["properties"];
                        $m_p = isset($property["multiple_purchases"]) && $property["multiple_purchases"];
                        if ($m_p || !in_array($a, $purchased_ids)) $product_addons[$addon["id"]] = $addon;
                    }
                    if ($product_addons) Utility::sksort($product_addons, 'rank', true);
                    $this->addData("product_addons", $product_addons);
                }


                $this->view->chose("website")->render("ac-product-software", $this->data);
            }

            if ($proanse["type"] == "sms") {

                array_push($breadcrumb, [
                    'link'  => $this->CRLink("ac-ps-products-t", ["sms"]),
                    'title' => __("website/account_products/page-title-type-sms"),
                ], [
                    'link'  => null,
                    'title' => $proanse["name"],
                ]);

                Helper::Load(["Orders", "Products"]);

                $addons = Orders::addons($proanse["id"]);
                $product = Products::get("sms", $proanse["product_id"], $clang);

                if (isset($product["options"]["renewal_selection_hide"]) && $product["options"]["renewal_selection_hide"])
                    $proanse["options"]["disable_renewal"] = true;

                $this->addData("product", $product);

                $this->addData("proanse", $proanse);
                $this->addData("panel_breadcrumb", $breadcrumb);
                $controller_link = $this->CRLink("ac-ps-product", [$proanse["id"]]);
                $cancel_link = Utility::text_replace(Bootstrap::$lang->get("sms-settings/cancel-link"), ['{pid}' => $proanse["id"]]);
                $cancel_link = Utility::link_determiner($cancel_link);
                $this->addData("links", [
                    'controller'  => $controller_link,
                    'cancel_link' => $cancel_link,
                    'api_link'    => APP_URI . "/api/sms",
                ]);
                $this->addData("cancel_text", Utility::text_replace(Bootstrap::$lang->get("sms-settings/cancel-link-text"), [
                    '{link}' => $cancel_link,
                ]));

                $this->addData("dimensions", Bootstrap::$lang->get("sms-settings/dimensions"));
                $this->addData("origins", $this->model->get_origins($udata["id"], $proanse["id"]));
                $this->addData("black_list", isset($options["black_list"]) ? $options["black_list"] : []);

                $this->addData("reports", $this->get_reports($udata["id"], $proanse));

                $this->addData("credit_list", $this->sms_credit_list());
                $this->addData("groups", $this->getGroups($udata["id"], $proanse["id"]));

                if (isset($proanse["module"]) && $proanse["module"] != "none" && $proanse["module"]) {
                    $mname = $proanse["module"];
                    Modules::Load("SMS", $mname);
                    if (class_exists($mname)) {
                        $module = new $mname($proanse["options"]["config"]);
                        $user_id = $udata["id"];
                        $secret_key = Crypt::encode($proanse["id"], Config::get("crypt/system"));
                        $this->addData("module", $module);
                        $this->addData("user_id", $user_id);
                        $this->addData("secret_key", $secret_key);
                    }
                }

                $ctoc_type = 'sms';
                $ctoc_service_transfer = Config::get("options/ctoc-service-transfer/status");
                if ($ctoc_service_transfer) {
                    if (Config::get("options/ctoc-service-transfer/" . $ctoc_type . "/status")) {
                        $ctoc_limit = Config::get("options/ctoc-service-transfer/" . $ctoc_type . "/limit");
                        if (isset($product["options"]["ctoc-service-transfer"])) {
                            $ctoc_service_transfer = $product["options"]["ctoc-service-transfer"]["status"];
                            $ctoc_limit = $product["options"]["ctoc-service-transfer"]["limit"];
                        }
                        if ($ctoc_service_transfer) {
                            Helper::Load("Events");
                            $aprv_l = Events::getList('transaction', 'order', $proanse["id"], 'ctoc-service-transfer', 'approved');
                            $ctoc_count = $aprv_l ? sizeof($aprv_l) : 0;
                            $this->addData("ctoc_has_expired", strlen($ctoc_limit) > 0 && (int)$ctoc_count >= (int)$ctoc_limit);
                            $this->addData("ctoc_used", $ctoc_count);
                            $this->addData("ctoc_limit", $ctoc_limit);
                            if ($ctoc_service_transfer) {
                                $ctoc_s_t_list = Events::getList('transaction', 'order', $proanse["id"], 'ctoc-service-transfer', 'pending', $udata["id"]);
                                $this->addData("ctoc_s_t_list", $ctoc_s_t_list);
                            }
                        }
                    } else $ctoc_service_transfer = false;
                }
                $this->addData("ctoc_service_transfer", $ctoc_service_transfer);

                $this->view->chose("website")->render("ac-product-sms", $this->data);
            }

            if ($proanse["type"] == "hosting") {
                $action = Filter::REQUEST("inc") ? Filter::REQUEST("inc") : Filter::REQUEST("action");

                if ($proanse["status"] == "inprocess" || $proanse["status"] == "waiting") {
                    Utility::redirect($this->CRLink("ac-ps-products"));
                    exit();
                }


                array_push($breadcrumb, [
                    'link'  => $this->CRLink("ac-ps-products-t", ["hosting"]),
                    'title' => __("website/account_products/page-title-type-hosting"),
                ], [
                    'link'  => null,
                    'title' => $proanse["name"],
                ]);

                if (isset($options["config"]["password"])) {
                    $options["config"]["password"] = Crypt::decode($options["config"]["password"], Config::get("crypt/user"));
                }

                if (isset($options["ftp_info"]["password"])) {
                    $options["ftp_info"]["password"] = Crypt::decode($options["ftp_info"]["password"], Config::get("crypt/user"));
                }

                if (isset($options["server_id"]) && $options["server_id"] != 0) {
                    $server = $this->getServer($options["server_id"]);
                    if ($server) {
                        if ($server["status"] == "active") {

                            $m_page = Filter::init("REQUEST/m_page", "route");
                            $m_page = str_replace('.', '', $m_page);

                            $this->addData("m_page", $m_page);

                            $this->addData("server", $server);

                            if ($server["ns1"]) $options["dns"]["ns1"] = $server["ns1"];
                            if ($server["ns2"]) $options["dns"]["ns2"] = $server["ns2"];
                            if ($server["ns3"]) $options["dns"]["ns3"] = $server["ns3"];
                            if ($server["ns4"]) $options["dns"]["ns4"] = $server["ns4"];

                            Modules::Load("Servers", $server["type"]);
                            $config = isset($options["config"]) ? $options["config"] : [];
                            $module_name = $server["type"] . "_Module";
                            $module = new $module_name($server, $options);

                            if (method_exists($module, "set_order")) $module->set_order($proanse);
                            $module->area_link = $controller_link;
                            $module->page = $m_page;

                            if ($action == "get_hosting_informations") {
                                if ($config) {

                                    $result = [];

                                    if (isset($module->config["supported"]) && in_array('disk-bandwidth-usage', $module->config["supported"]) && (method_exists($module, 'getDisk') || method_exists($module, 'getBandwidth'))) {
                                        $bandwidth = method_exists($module, 'getBandwidth') ? $module->getBandwidth() : false;
                                        $disk = method_exists($module, 'getDisk') ? $module->getDisk() : false;

                                        if ($bandwidth) {
                                            $result["usage"]["bandwidth_limit_byte"] = $bandwidth["limit"];
                                            $result["usage"]["bandwidth_used_byte"] = $bandwidth["used"];
                                            $result["usage"]["bandwidth_limit_format"] = $bandwidth["format-limit"];
                                            $result["usage"]["bandwidth_used_format"] = $bandwidth["format-used"];
                                            $result["usage"]["bandwidth_used_percent"] = $bandwidth["used-percent"];
                                        }

                                        if ($bandwidth) {
                                            $result["usage"]["disk_limit_byte"] = $disk["limit"];
                                            $result["usage"]["disk_used_byte"] = $disk["used"];
                                            $result["usage"]["disk_limit_format"] = $disk["format-limit"];
                                            $result["usage"]["disk_used_format"] = $disk["format-used"];
                                            $result["usage"]["disk_used_percent"] = $disk["used-percent"];
                                        }

                                    }

                                    $emails = method_exists($module, 'getEmailList') ? $module->getEmailList() : [];

                                    $forwards = method_exists($module, 'getForwardsList') ? $module->getForwardsList() : [];
                                    $mail_domains = method_exists($module, 'getMailDomains') ? $module->getMailDomains() : [];
                                    if ($emails) $result["emails"] = $emails;
                                    if ($mail_domains) $result["mail_domains"] = $mail_domains;
                                    if ($forwards) $result["forwards"] = $forwards;


                                    $panel_vars = [
                                        'module'          => $module,
                                        'order'           => $proanse,
                                        'options'         => $options,
                                        'controller_link' => $controller_link,
                                    ];

                                    if (method_exists($module, "clientArea"))
                                        $panel = $module->clientArea();
                                    else
                                        $panel = Modules::getPage("Servers", $server["type"], ($m_page ? "clientArea-" . $m_page : "panel"), $panel_vars);
                                    if ($panel) $result['panel'] = $panel;

                                    if ($result) echo Utility::jencode($result);
                                }
                                return false;
                            } elseif ($action == "use_method" && Filter::REQUEST("method")) {
                                if (method_exists($module, "use_method")) {
                                    $method = Filter::init("REQUEST/method", "route");

                                    if (($method == 'SingleSignOn' && method_exists($module, 'use_clientArea_SingleSignOn')) || ($method == 'SingleSignOn2' && method_exists($module, 'use_clientArea_SingleSignOn2'))) {
                                        $udata = UserManager::LoginData();
                                        Orders::add_history($udata['id'], $proanse['id'], 'hosting-order-panel-accessed', [
                                            'id'   => $server['id'],
                                            'ip'   => $server["ip"],
                                            'type' => $server["type"],
                                            'name' => $server["name"],
                                        ]);
                                    }
                                    $module->use_method($method);
                                }
                                return true;
                            }

                            $this->addData("module_con", $module);
                            $this->addData("module", $server["type"]);
                        }
                    }
                }

                Helper::Load(["Orders", "Products"]);

                $addons = Orders::addons($proanse["id"]);
                $requirements = Orders::requirements($proanse["id"]);
                $product = Products::get("hosting", $proanse["product_id"], $clang);
                $upgrade = Config::get("options/product-upgrade/status");

                if ($product) {
                    $disk_limit = $product["options"]["disk_limit"];
                    $bandwidth_limit = $product["options"]["bandwidth_limit"];
                    $email_limit = $product["options"]["email_limit"];
                    $database_limit = $product["options"]["database_limit"];
                    $addons_limit = $product["options"]["addons_limit"];
                    $subdomain_limit = $product["options"]["subdomain_limit"];
                    $ftp_limit = $product["options"]["ftp_limit"];
                    $park_limit = $product["options"]["park_limit"];
                    $max_email_per_hour = $product["options"]["max_email_per_hour"];
                    $cpu_limit = $product["options"]["cpu_limit"];

                    $options = array_merge($options, [
                        'disk_limit'         => $disk_limit,
                        'bandwidth_limit'    => $bandwidth_limit,
                        'email_limit'        => $email_limit,
                        'database_limit'     => $database_limit,
                        'addons_limit'       => $addons_limit,
                        'subdomain_limit'    => $subdomain_limit,
                        'ftp_limit'          => $ftp_limit,
                        'park_limit'         => $park_limit,
                        'max_email_per_hour' => $max_email_per_hour,
                        'cpu_limit'          => $cpu_limit,
                    ]);

                }

                if (isset($product["options"]["renewal_selection_hide"]) && $product["options"]["renewal_selection_hide"])
                    $proanse["options"]["disable_renewal"] = true;

                if ($upgrade && !Config::get("options/product-upgrade/hosting")) $upgrade = false;

                $this->addData("product", $product);

                if ($addons) $this->addData("addons", $addons);
                if ($requirements) $this->addData("requirements", $requirements);
                $this->addData("upgrade", $upgrade);
                if ($proanse["period"] != "none" && $upgrade) {
                    $ordinfo = Orders::period_info($proanse);
                    $foreign_user = User::isforeign($udata["id"]);

                    $this->addData("upgrade_times_used", $ordinfo["times-used-day"]);
                    $this->addData("upgrade_times_used_amount", $ordinfo["format-times-used-amount"]);
                    $this->addData("upgrade_remaining_day", $ordinfo["remaining-day"]);
                    $this->addData("upgrade_remaining_amount", $ordinfo["format-remaining-amount"]);
                    $this->addData("foreign_user", $foreign_user);
                    $this->addData("upgproducts", $this->upgrade_products($proanse, $product, $ordinfo["remaining-amount"]));
                }

                $this->addData("options", $options);
                $this->addData("proanse", $proanse);
                $this->addData("panel_breadcrumb", $breadcrumb);
                $controller_link = $this->CRLink("ac-ps-product", [$proanse["id"]]);
                $this->addData("links", [
                    'controller' => $controller_link,
                ]);

                $ctoc_type = 'hosting';
                $ctoc_service_transfer = Config::get("options/ctoc-service-transfer/status");
                if ($ctoc_service_transfer) {
                    if (Config::get("options/ctoc-service-transfer/" . $ctoc_type . "/status")) {
                        $ctoc_limit = Config::get("options/ctoc-service-transfer/" . $ctoc_type . "/limit");
                        if (isset($product["options"]["ctoc-service-transfer"])) {
                            $ctoc_service_transfer = $product["options"]["ctoc-service-transfer"]["status"];
                            $ctoc_limit = $product["options"]["ctoc-service-transfer"]["limit"];
                        }
                        if ($ctoc_service_transfer) {
                            Helper::Load("Events");
                            $aprv_l = Events::getList('transaction', 'order', $proanse["id"], 'ctoc-service-transfer', 'approved');
                            $ctoc_count = $aprv_l ? sizeof($aprv_l) : 0;
                            $this->addData("ctoc_has_expired", strlen($ctoc_limit) > 0 && (int)$ctoc_count >= (int)$ctoc_limit);
                            $this->addData("ctoc_used", $ctoc_count);
                            $this->addData("ctoc_limit", $ctoc_limit);
                            if ($ctoc_service_transfer) {
                                $ctoc_s_t_list = Events::getList('transaction', 'order', $proanse["id"], 'ctoc-service-transfer', 'pending', $udata["id"]);
                                $this->addData("ctoc_s_t_list", $ctoc_s_t_list);
                            }
                        }
                    } else $ctoc_service_transfer = false;
                }
                $this->addData("ctoc_service_transfer", $ctoc_service_transfer);

                $product_addon_ids = isset($product["addons"]) ? $product["addons"] : '';
                $purchased_ids = [];
                $product_addons = [];
                if ($addons) foreach ($addons as $k => $v) $purchased_ids[] = $v["addon_id"];

                if ($product_addon_ids) {
                    $product_addon_ids = explode(",", $product_addon_ids);
                    foreach ($product_addon_ids as $a) {
                        $addon = Products::addon($a, $clang);
                        $property = $addon["properties"];
                        $m_p = isset($property["multiple_purchases"]) && $property["multiple_purchases"];
                        if ($m_p || !in_array($a, $purchased_ids)) $product_addons[$addon["id"]] = $addon;
                    }
                    if ($product_addons) Utility::sksort($product_addons, 'rank', true);
                    $this->addData("product_addons", $product_addons);
                }


                $this->view->chose("website")->render("ac-product-hosting", $this->data);
            }

            if ($proanse["type"] == "server") {

                if ($proanse["status"] == "inprocess" || $proanse["status"] == "waiting") {
                    Utility::redirect($this->CRLink("ac-ps-products"));
                    exit();
                }

                array_push($breadcrumb, [
                    'link'  => $this->CRLink("ac-ps-products-t", ["server"]),
                    'title' => __("website/account_products/page-title-type-server"),
                ], [
                    'link'  => null,
                    'title' => $proanse["name"],
                ]);

                Helper::Load(["Orders", "Products"]);

                if (isset($options["server_id"]) && $options["server_id"] != 0) {
                    $server = $this->getServer($options["server_id"]);
                    if ($server) {
                        if ($server["status"] == "active") {
                            $this->addData("server", $server);

                            $m_page = Filter::init("REQUEST/m_page", "route");
                            $m_page = str_replace('.', '', $m_page);

                            $this->addData("m_page", $m_page);

                            Modules::Load("Servers", $server["type"]);
                            $module_name = $server["type"] . "_Module";
                            $module = new $module_name($server, $options);
                            if (method_exists($module, "set_order")) $module->set_order($proanse);
                            $module->area_link = $controller_link;
                            $module->page = $m_page;


                            if (Filter::REQUEST("inc") == "get_server_informations") {
                                $panel_vars = [
                                    'module'          => $module,
                                    'order'           => $proanse,
                                    'options'         => $options,
                                    'controller_link' => $controller_link,
                                ];
                                if (method_exists($module, "get_status")) $panel_vars['status'] = $module->get_status();
                                if (method_exists($module, "clientArea"))
                                    $panel = $module->clientArea();
                                else
                                    $panel = Modules::getPage("Servers", $server["type"], ($m_page ? "clientArea-" . $m_page : "panel"), $panel_vars);

                                $result = [
                                    'panel' => $panel,
                                ];
                                if (isset($panel_vars['status'])) $result['status'] = $panel_vars['status'];

                                echo Utility::jencode($result);

                                return false;
                            } elseif (Filter::REQUEST("inc") == "panel_operation_method" && Filter::REQUEST("method")) {
                                $method = Filter::init("REQUEST/method", "route");

                                if (($method == 'SingleSignOn' && method_exists($module, 'use_clientArea_SingleSignOn')) || ($method == 'SingleSignOn2' && method_exists($module, 'use_clientArea_SingleSignOn2'))) {
                                    $udata = UserManager::LoginData();
                                    Orders::add_history($udata['id'], $proanse['id'], 'server-order-panel-accessed', [
                                        'id'   => $server['id'],
                                        'ip'   => $server["ip"],
                                        'type' => $server["type"],
                                        'name' => $server["name"],
                                    ]);
                                }
                                $module->use_method($method);
                                return true;;
                            }

                            $this->addData("module_con", $module);
                            $this->addData("module", $server["type"]);
                        }
                    }
                }

                $addons = Orders::addons($proanse["id"]);
                $requirements = Orders::requirements($proanse["id"]);
                $product = Products::get("server", $proanse["product_id"], $clang);

                if (isset($product["options"]["renewal_selection_hide"]) && $product["options"]["renewal_selection_hide"])
                    $proanse["options"]["disable_renewal"] = true;

                $upgrade = Config::get("options/product-upgrade/status");

                if ($upgrade && !Config::get("options/product-upgrade/server")) $upgrade = false;

                $this->addData("upgrade", $upgrade);
                if ($proanse["period"] != "none" && $upgrade) {
                    $ordinfo = Orders::period_info($proanse);
                    $foreign_user = User::isforeign($udata["id"]);

                    $this->addData("upgrade_times_used", $ordinfo["times-used-day"]);
                    $this->addData("upgrade_times_used_amount", $ordinfo["format-times-used-amount"]);
                    $this->addData("upgrade_remaining_day", $ordinfo["remaining-day"]);
                    $this->addData("upgrade_remaining_amount", $ordinfo["format-remaining-amount"]);
                    $this->addData("foreign_user", $foreign_user);
                    $this->addData("upgproducts", $this->upgrade_products($proanse, $product, $ordinfo["remaining-amount"]));
                }

                $this->addData("product", $product);

                if ($addons) $this->addData("addons", $addons);
                if ($requirements) $this->addData("requirements", $requirements);


                if ($product && $img = $this->model->get_picture("product", $product["id"], "order"))
                    $options["product_image"] = Utility::image_link_determiner($img, Config::get("pictures/products/folder"));

                if (isset($options["login"]["password"])) {
                    $password = $options["login"]["password"];
                    $password_d = Crypt::decode($password, Config::get("crypt/user"));
                    if ($password_d) $options["login"]["password"] = $password_d;
                }


                $this->addData("options", $options);
                $this->addData("proanse", $proanse);
                $this->addData("panel_breadcrumb", $breadcrumb);
                $controller_link = $this->CRLink("ac-ps-product", [$proanse["id"]]);
                $this->addData("links", [
                    'controller' => $controller_link,
                ]);

                $ctoc_type = 'server';
                $ctoc_service_transfer = Config::get("options/ctoc-service-transfer/status");
                if ($ctoc_service_transfer) {
                    if (Config::get("options/ctoc-service-transfer/" . $ctoc_type . "/status")) {
                        $ctoc_limit = Config::get("options/ctoc-service-transfer/" . $ctoc_type . "/limit");
                        if (isset($product["options"]["ctoc-service-transfer"])) {
                            $ctoc_service_transfer = $product["options"]["ctoc-service-transfer"]["status"];
                            $ctoc_limit = $product["options"]["ctoc-service-transfer"]["limit"];
                        }
                        if ($ctoc_service_transfer) {
                            Helper::Load("Events");
                            $aprv_l = Events::getList('transaction', 'order', $proanse["id"], 'ctoc-service-transfer', 'approved');
                            $ctoc_count = $aprv_l ? sizeof($aprv_l) : 0;
                            $this->addData("ctoc_has_expired", strlen($ctoc_limit) > 0 && (int)$ctoc_count >= (int)$ctoc_limit);
                            $this->addData("ctoc_used", $ctoc_count);
                            $this->addData("ctoc_limit", $ctoc_limit);
                            if ($ctoc_service_transfer) {
                                $ctoc_s_t_list = Events::getList('transaction', 'order', $proanse["id"], 'ctoc-service-transfer', 'pending', $udata["id"]);
                                $this->addData("ctoc_s_t_list", $ctoc_s_t_list);
                            }
                        }
                    } else $ctoc_service_transfer = false;
                }
                $this->addData("ctoc_service_transfer", $ctoc_service_transfer);


                $product_addon_ids = isset($product["addons"]) ? $product["addons"] : '';
                $purchased_ids = [];
                $product_addons = [];
                if ($addons) foreach ($addons as $k => $v) $purchased_ids[] = $v["addon_id"];

                if ($product_addon_ids) {
                    $product_addon_ids = explode(",", $product_addon_ids);
                    foreach ($product_addon_ids as $a) {
                        $addon = Products::addon($a, $clang);
                        $property = $addon["properties"];
                        $m_p = isset($property["multiple_purchases"]) && $property["multiple_purchases"];
                        if ($m_p || !in_array($a, $purchased_ids)) $product_addons[$addon["id"]] = $addon;
                    }
                    if ($product_addons) Utility::sksort($product_addons, 'rank', true);
                    $this->addData("product_addons", $product_addons);
                }


                $this->view->chose("website")->render("ac-product-server", $this->data);
            }

            if ($proanse["type"] == "domain") {

                array_push($breadcrumb, [
                    'link'  => $this->CRLink("ac-ps-products-t", ["domain"]),
                    'title' => __("website/account_products/page-title-type-domain"),
                ], [
                    'link'  => null,
                    'title' => $proanse["name"],
                ]);


                $isProduct = $this->model->getTLD($proanse["product_id"], null, $clang);
                if ($isProduct) {

                    if (!isset($options["dns_manage"]))
                        $options["dns_manage"] = $isProduct["dns_manage"] == 1;
                    if (!isset($options["whois_manage"]))
                        $options["whois_manage"] = $isProduct["whois_privacy"] == 1;
                    if (!isset($options["epp_code_manage"]))
                        $options["epp_code_manage"] = $isProduct["epp_code"] == 1;


                    $this->addData("options", $options);

                    $getAmount = $this->model->get_price("renewal", "tld", $isProduct["id"]);
                    $tld_amount = $getAmount["amount"];

                    if ($udata["dealership"]) {
                        $dealership = Utility::jdecode($udata["dealership"], true);
                        if ($dealership && isset($dealership["status"]) && $dealership["status"] == "active") {
                            $discounts = $dealership["discounts"];
                            if (isset($discounts["domain"]))
                                $discount_rate = $discounts["domain"];
                            else
                                $discount_rate = 0;

                            if ($discount_rate) {
                                $discount_amount = Money::get_discount_amount($tld_amount, $discount_rate);
                                $tld_amount -= $discount_amount;
                            }
                        }
                    }

                    if ($getAmount) {
                        $renewal_list = [];
                        $min_year = $isProduct["min_years"];
                        $max_year = $isProduct["max_years"];

                        if (strlen($min_year) < 1) $min_year = 1;
                        if (strlen($max_year) < 1) $max_year = 10;

                        $min_year -= 1;
                        $max_year -= 1;


                        for ($i = $min_year; $i <= $max_year; $i++) {
                            $year = $i + 1;
                            $renewal_list[$year] = Money::formatter_symbol(($tld_amount * $year), $getAmount["cid"], true);
                        }
                        $renewal_amount = Money::formatter_symbol($tld_amount, $getAmount["cid"], true);
                        $this->addData("renewal_list", $renewal_list);
                        $this->addData("renewal_amount", $renewal_amount);
                    }
                }

                $whidden_amount = Config::get("options/domain-whois-privacy/amount");
                $whidden_cid = Config::get("options/domain-whois-privacy/cid");

                if ($proanse["module"] != "none" && $proanse["module"]) {
                    if ($fetchModule = Modules::Load("Registrars", $proanse["module"])) {
                        $module = new $proanse["module"]();
                        $whidden_amount = $fetchModule["config"]["settings"]["whidden-amount"] ?? false;
                        $whidden_cid = $fetchModule["config"]["settings"]["whidden-currency"] ?? false;
                    }
                }

                $whois_privacy_price = 0;
                if ($whidden_amount)
                    $whois_privacy_price = Money::formatter_symbol($whidden_amount, $whidden_cid, $proanse["amount_cid"]);

                if (isset($options["whois"]) && is_array($options["whois"]) && !$options["whois"]) {
                    Helper::Load(["Orders"]);
                    if (isset($module) && $module) {
                        if (method_exists($module, "set_order")) $module->set_order($proanse);
                        if ($info = $module->get_info($options)) {
                            $ulang = User::getData($proanse["owner_id"], "id,lang", "array");
                            $ulang = $ulang["lang"];

                            $isAddon = Models::$init->db->select("id")->from("users_products_addons");
                            $isAddon->where("owner_id", "=", $proanse["id"], "&&");
                            $isAddon->where("addon_key", "=", "whois-privacy");
                            $isAddon = $isAddon->build() ? $isAddon->getObject()->id : false;

                            if (isset($info["ns1"])) $options["ns1"] = $info["ns1"];
                            if (isset($info["ns2"])) $options["ns2"] = $info["ns2"];
                            if (isset($info["ns3"])) $options["ns3"] = $info["ns3"];
                            if (isset($info["ns4"])) $options["ns4"] = $info["ns4"];
                            if (isset($info["transferlock"])) $options["transferlock"] = $info["transferlock"];
                            if (isset($info["cns"])) $options["cns_list"] = $info["cns"];
                            $options["whois"] = $info["whois"];

                            if (isset($info["whois_privacy"]) && $info["whois_privacy"]) {
                                $options["whois_privacy"] = $info["whois_privacy"]["status"] == "enable";
                                if (isset($info["whois_privacy"]["end_time"])) {
                                    $options["whois_privacy_endtime"] = $info["whois_privacy"]["end_time"];
                                    if ($isAddon) {
                                        Orders::set_addon($isAddon, [
                                            "duedate" => $options["whois_privacy_endtime"],
                                            "status"  => "active",
                                            "unread"  => 1,
                                        ]);
                                    } else
                                        Orders::insert_addon([
                                            "invoice_id"  => 0,
                                            "owner_id"    => $proanse["id"],
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
                                            "cid"         => $proanse["amount_cid"],
                                            "status"      => "active",
                                            "unread"      => 1,
                                        ]);
                                }
                            } elseif (isset($options["whois_privacy"])) {
                                unset($options["whois_privacy"]);
                                if (isset($options["whois_privacy_endtime"])) unset($options["whois_privacy_endtime"]);
                                if ($isAddon) Orders::delete_addon($isAddon);
                            }
                            Orders::set($proanse["id"], ['options' => Utility::jencode($options)]);
                        }
                    }
                }

                if (isset($options["whois"]) && $options["whois"]) {
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
                }

                $wprivacy = isset($options["whois_privacy"]) && $options["whois_privacy"];

                $whois_privacy_purchase = $whidden_amount > 0.00;
                $whois_privacy_endtime = false;

                if ($whois_privacy_purchase) {
                    $isAddon = WDB::select("id,duedate,period")->from("users_products_addons");
                    $isAddon->where("status", "=", "active", "&&");
                    $isAddon->where("owner_id", "=", $proanse["id"], "&&");
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

                $this->addData("options", $options);
                $this->addData("proanse", $proanse);
                $this->addData("panel_breadcrumb", $breadcrumb);
                $controller_link = $this->CRLink("ac-ps-product", [$proanse["id"]]);
                $this->addData("links", [
                    'controller'     => $controller_link,
                    'whois-profiles' => $this->CRLink("ac-ps-products-t", ["domain"]) . "?page=whois_profiles",
                ]);

                if (isset($proanse["period"])) {
                    $start = DateManager::Now("Y-m-d");
                    $end = DateManager::format("Y-m-d", $proanse["duedate"]);
                    $remaining = DateManager::remaining_day($end, $start);
                    if ($remaining < 0) $remaining = 0;

                    $start = DateManager::format("Y-m-d", $proanse["renewaldate"]);
                    $end = DateManager::Now("Y-m-d");
                    $used_day = DateManager::remaining_day($end, $start);
                    if ($used_day < 0) $used_day = 0;

                    $this->addData("remaining_day", $remaining);
                    $this->addData("used_day", $used_day);
                }

                $ctoc_type = 'domain';
                $ctoc_service_transfer = Config::get("options/ctoc-service-transfer/status");
                if ($ctoc_service_transfer) {
                    if (Config::get("options/ctoc-service-transfer/" . $ctoc_type . "/status")) {
                        $ctoc_limit = Config::get("options/ctoc-service-transfer/" . $ctoc_type . "/limit");
                        if ($ctoc_service_transfer) {
                            Helper::Load("Events");
                            $aprv_l = Events::getList('transaction', 'order', $proanse["id"], 'ctoc-service-transfer', 'approved');
                            $ctoc_count = $aprv_l ? sizeof($aprv_l) : 0;
                            $this->addData("ctoc_has_expired", strlen($ctoc_limit) > 0 && (int)$ctoc_count >= (int)$ctoc_limit);
                            $this->addData("ctoc_used", $ctoc_count);
                            $this->addData("ctoc_limit", $ctoc_limit);
                            if ($ctoc_service_transfer) {
                                $ctoc_s_t_list = Events::getList('transaction', 'order', $proanse["id"], 'ctoc-service-transfer', 'pending', $udata["id"]);
                                $this->addData("ctoc_s_t_list", $ctoc_s_t_list);
                            }
                        }
                    } else $ctoc_service_transfer = false;
                }
                $this->addData("ctoc_service_transfer", $ctoc_service_transfer);

                if ($proanse["module"] && $proanse["module"] != "none") {
                    $module_name = $proanse["module"];
                    Modules::Load('Registrars', $module_name);

                    if (class_exists($module_name)) {
                        $module = new $module_name;

                        if (method_exists($module, 'set_order')) $module->set_order($proanse);

                        $this->addData("module", $module_name);
                        $this->addData("module_con", $module);
                    }
                }

                $whois_profiles = User::whois_profiles($udata["id"]);

                $this->addData("whois_profiles", $whois_profiles);

                $this->addData("contact_types", [
                    'registrant'     => __("website/account_products/whois-contact-type-registrant"),
                    'administrative' => __("website/account_products/whois-contact-type-administrative"),
                    'technical'      => __("website/account_products/whois-contact-type-technical"),
                    'billing'        => __("website/account_products/whois-contact-type-billing"),
                ]);


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

                $ulang = Bootstrap::$lang->clang;
                $ll = Config::get("general/local");


                $this->addData("user_whois_info", $user_whois_info);

                $this->addData("user_lang", $ulang);

                $require_verification = false;
                $manuel_doc_fields = [];
                $module_docs = [];

                // Found Module Information/Document Fields
                if ($proanse["module"] !== "none" && $proanse["module"] && isset($module) && $module) {
                    if (isset($module->config["settings"]["doc-fields"][$options["tld"]]) && $module->config["settings"]["doc-fields"][$options["tld"]])
                        $module_docs = $module->config["settings"]["doc-fields"][$options["tld"]];
                }


                // Found Manuel Information/Document Fields
                $found_doc_fields = $this->model->db->select()->from("tldlist_docs");
                $found_doc_fields->where("tld", "=", $options["tld"]);
                $found_doc_fields->order_by("sortnum ASC");
                if ($found_doc_fields->build()) $manuel_doc_fields = $found_doc_fields->fetch_assoc();

                if (isset($options["verification"]) && $options["verification"]) {

                    // added information/documents
                    $uploaded_docs = $this->model->db->select()->from("users_products_docs");
                    $uploaded_docs->where("owner_id", "=", $proanse["id"]);
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

                    if ((sizeof($manuel_doc_fields) > 0 || sizeof($module_docs) > 0 || sizeof($operator_docs) > 0) && ($proanse["status"] == "inprocess")) {
                        $require_verification = true;

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

                        $require_info_docs_content = $this->model->db->select("required_docs_info")->from("tldlist");
                        $require_info_docs_content->where("name", "=", $options["tld"]);
                        if ($require_info_docs_content->build()) {
                            $require_info_docs_content = $require_info_docs_content->getObject()->required_docs_info;
                            $require_info_docs_contents = Utility::jdecode($require_info_docs_content, true);


                            $require_info_docs_content = $require_info_docs_contents[$ll] ?? '';


                            if (isset($require_info_docs_contents[$ulang]))
                                $require_info_docs_content = $require_info_docs_contents[$ulang] ?? '';

                            if (Utility::strlen(strip_tags($require_info_docs_content)) >= 5) {
                                $this->addData("required_docs_info", $require_info_docs_content);
                            }
                        }

                        $this->addData("info_docs", $info_docs);
                        $this->addData("uploaded_docs", $uploaded_docs);
                    }

                }


                $this->addData("require_verification", $require_verification);

                $this->addData("product", $isProduct ?? false);


                $this->view->chose("website")->render("ac-product-domain", $this->data);
            }

            if ($proanse["type"] == "special") {

                if ($proanse["status"] == "inprocess" || $proanse["status"] == "waiting") {
                    Utility::redirect($this->CRLink("ac-ps-products"));
                    exit();
                }

                Helper::Load(["Orders", "Products"]);

                if ($proanse["module"] != "none" && $proanse["module"]) {
                    $module_name = $proanse["module"];

                    $m_page = Filter::init("REQUEST/m_page", "route");
                    $m_page = str_replace('.', '', $m_page);

                    $this->addData("m_page", $m_page);

                    if (Filter::REQUEST("action") == "get_details") {
                        Modules::Load("Product", $module_name);
                        if (!class_exists($module_name)) {
                            echo "Module Class Not Found: '" . $module_name . "'";
                            return true;
                        }
                        $module = new $module_name;
                        if (method_exists($module, "set_order")) $module->set_order($proanse);
                        $module->area_link = $this->CRLink("ac-ps-product", [$id]);
                        $module->page = $m_page;

                        $panel_vars = [
                            'module'          => $module,
                            'order'           => $proanse,
                            'options'         => $options,
                            'controller_link' => $controller_link,
                        ];

                        if (method_exists($module, "clientArea"))
                            $panel = $module->clientArea();
                        else
                            $panel = Modules::getPage("Product", $module_name, ($m_page ? "clientArea-" . $m_page : "ac-order-detail"), $panel_vars);

                        echo $panel;


                        return true;
                    } elseif (Filter::REQUEST("action") == "use_method" && Filter::REQUEST("method")) {
                        Modules::Load("Product", $module_name);
                        $module = new $module_name();
                        if (method_exists($module, "set_order")) $module->set_order($proanse);
                        $module->area_link = $controller_link;

                        if (method_exists($module, "use_method"))
                            $module->use_method(Filter::route(Filter::REQUEST("method")));

                        return true;
                    }

                    $module = Modules::Load("Product", $module_name, true);
                    $this->addData("module", $module_name);
                    $this->addData("module_con", $module);
                }

                $addons = Orders::addons($proanse["id"]);
                $requirements = Orders::requirements($proanse["id"]);
                $product = Products::get("special", $proanse["product_id"], $clang);
                $upgrade = Config::get("options/product-upgrade/status");

                if (isset($product["options"]["renewal_selection_hide"]) && $product["options"]["renewal_selection_hide"])
                    $proanse["options"]["disable_renewal"] = true;


                $group = Products::getCategory($proanse["type_id"], $clang, "t1.options");
                if ($group && isset($group["options"]["upgrading"])) $upgrade = $group["options"]["upgrading"];


                if ($addons) $this->addData("addons", $addons);
                if ($requirements) $this->addData("requirements", $requirements);

                $delivery_file = isset($proanse["options"]["delivery_file"]) ? $proanse["options"]["delivery_file"] : false;
                $product_file = isset($product["options"]["download_file"]) ? $product["options"]["download_file"] : false;
                $download_link = isset($product["options"]["download_link"]) ? $product["options"]["download_link"] : false;

                if ($delivery_file)
                    $download_file = RESOURCE_DIR . "uploads" . DS . "orders" . DS . $delivery_file;
                elseif ($product_file)
                    $download_file = RESOURCE_DIR . "uploads" . DS . "products" . DS . $product_file;
                else
                    $download_file = false;

                $hooks = Hook::run("OrderDownload", $proanse, $product, [
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
                    $this->addData("download_link", $this->CRLink("download-id", ["order", $proanse["id"]]));

                if ($product && $img = $this->model->get_picture("product", $product["id"], "order"))
                    $options["product_image"] = Utility::image_link_determiner($img, Config::get("pictures/products/folder"));


                $this->addData("product", $product);

                array_push($breadcrumb, [
                    'link'  => $this->CRLink("ac-ps-products-t", ["special"]) . "?category=" . $proanse["type_id"],
                    'title' => $options["group_name"],
                ]);

                array_push($breadcrumb, [
                    'link'  => null,
                    'title' => $proanse["name"],
                ]);

                $this->addData("upgrade", $upgrade);
                if ($proanse["period"] != "none" && $upgrade) {
                    $ordinfo = Orders::period_info($proanse);
                    $foreign_user = User::isforeign($udata["id"]);

                    $this->addData("upgrade_times_used", $ordinfo["times-used-day"]);
                    $this->addData("upgrade_times_used_amount", $ordinfo["format-times-used-amount"]);
                    $this->addData("upgrade_remaining_day", $ordinfo["remaining-day"]);
                    $this->addData("upgrade_remaining_amount", $ordinfo["format-remaining-amount"]);
                    $this->addData("foreign_user", $foreign_user);
                    $this->addData("upgproducts", $this->upgrade_products($proanse, $product, $ordinfo["remaining-amount"]));
                }

                $this->addData("options", $options);
                $this->addData("proanse", $proanse);
                $this->addData("panel_breadcrumb", $breadcrumb);
                $controller_link = $this->CRLink("ac-ps-product", [$proanse["id"]]);
                $this->addData("links", [
                    'controller' => $controller_link,
                ]);

                $ctoc_service_transfer = Config::get("options/ctoc-service-transfer/status");
                if ($ctoc_service_transfer) {
                    if (isset($group["options"]["ctoc-service-transfer"]) && $group["options"]["ctoc-service-transfer"]["status"]) {
                        $ctoc_limit = $group["options"]["ctoc-service-transfer"]["limit"];
                        if (isset($product["options"]["ctoc-service-transfer"])) {
                            $ctoc_service_transfer = $product["options"]["ctoc-service-transfer"]["status"];
                            $ctoc_limit = $product["options"]["ctoc-service-transfer"]["limit"];
                        }
                        if ($ctoc_service_transfer) {
                            Helper::Load("Events");
                            $aprv_l = Events::getList('transaction', 'order', $proanse["id"], 'ctoc-service-transfer', 'approved');
                            $ctoc_count = $aprv_l ? sizeof($aprv_l) : 0;
                            $this->addData("ctoc_has_expired", strlen($ctoc_limit) > 0 && (int)$ctoc_count >= (int)$ctoc_limit);
                            $this->addData("ctoc_used", $ctoc_count);
                            $this->addData("ctoc_limit", $ctoc_limit);
                            if ($ctoc_service_transfer) {
                                $ctoc_s_t_list = Events::getList('transaction', 'order', $proanse["id"], 'ctoc-service-transfer', 'pending', $udata["id"]);
                                $this->addData("ctoc_s_t_list", $ctoc_s_t_list);
                            }
                        }
                    } else $ctoc_service_transfer = false;
                }
                $this->addData("ctoc_service_transfer", $ctoc_service_transfer);

                $product_addon_ids = isset($product["addons"]) ? $product["addons"] : '';
                $purchased_ids = [];
                $product_addons = [];
                if ($addons) foreach ($addons as $k => $v) $purchased_ids[] = $v["addon_id"];

                if ($product_addon_ids) {
                    $product_addon_ids = explode(",", $product_addon_ids);
                    foreach ($product_addon_ids as $a) {
                        $addon = Products::addon($a, $clang);
                        $property = $addon["properties"];
                        $m_p = isset($property["multiple_purchases"]) && $property["multiple_purchases"];
                        if ($m_p || !in_array($a, $purchased_ids)) $product_addons[$addon["id"]] = $addon;
                    }
                    if ($product_addons) Utility::sksort($product_addons, 'rank', true);
                    $this->addData("product_addons", $product_addons);
                }

                $this->view->chose("website")->render("ac-product-special", $this->data);
            }
        }

    }