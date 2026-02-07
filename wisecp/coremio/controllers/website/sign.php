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
        protected $params, $data = [], $sso = [];

        public function __construct($arg = [])
        {
            parent::__construct();
            $this->params = $arg['params'];
        }


        public function main()
        {
            if (isset($this->params[0])) {

                if ($this->params[0] == 'provider' && isset($this->params[1])) {
                    $this->connect_with_provider();
                } elseif ($this->params[0] == 'up') {
                    $this->sign_up();
                } elseif ($this->params[0] == 'in') {
                    $this->sign_in();
                } elseif ($this->params[0] == 'out') {
                    $this->sign_out();
                } elseif ($this->params[0] == 'forget') {
                    $this->sign_forget();
                } elseif ($this->params[0] == 'reset-password') {
                    $this->reset_password();
                }
            }
        }


        private function connect_with_provider()
        {
            $mod_name = Filter::letters_numbers($this->params[1], "_-");
            $mod_name = substr($mod_name, 0, 100);
            $module = Modules::Load("Addons", "Connect" . $mod_name, false, true);
            if (!$module) return false;
            $mod_name_c = "Connect" . $mod_name;
            $module = new $mod_name_c();
            if (!$module) return false;
            $method = isset($this->params[2]) ? Filter::letters_numbers($this->params[2]) : false;
            if ($method) {
                if ($method == "feedback") {
                    $format = isset($this->params[3]) ? Filter::letters_numbers($this->params[3]) : false;
                    $type = Filter::REQUEST("_type");
                    if ($result = $module->feedback()) {

                        Helper::Load(["User", "Money"]);

                        $this->takeDatas(["language"]);


                        $field_name = $result["field_info"]["name"];
                        $field_value = $result["field_info"]["value"];

                        $user = false;
                        $fieldCheck = User::findInfo($field_name, $field_value);
                        if ($fieldCheck) $user = User::getData($fieldCheck["owner_id"], "id,status,type,email,password,lang", "array");

                        if (!$user) {
                            if ($find_user = User::email_check($result["data"]["email"], "member"))
                                $user = User::getData($find_user, "id,status,type,email,password,lang,secure_hash", "array");

                            if (!$user && $find_user = User::email_check($result["data"]["email"], "admin"))
                                $user = User::getData($find_user, "id,status,type,email,password,lang,secure_hash", "array");
                        }

                        if ($user) {
                            $secure_hash = User::secure_hash($user["id"], "decrypt");
                            if ($secure_hash) {
                                if ($user["email"] != $secure_hash["email"])
                                    die("Security Problem!");
                            }
                        }

                        if ($type == "register" && !$user) {
                            $full_name = $result["data"]["name"];
                            if ($result["data"]["surname"]) $full_name .= " " . $result["data"]["surname"];
                            $country = ___("package/country-id");
                            if ($ip_info = UserManager::ip_info()) {
                                $getCountry = $ip_info["countryCode"];
                                $getCountry = AddressManager::get_id_with_cc($getCountry);
                                if ($getCountry) $country = $getCountry;
                            }
                            $lang = isset($result["data"]["lang"]) ? $result["data"]["lang"] : Bootstrap::$lang->clang;

                            $password = Utility::generate_hash(16);


                            if (Validation::check_prohibited($result["data"]["email"], ['domain', 'email', 'word']))
                                die(Utility::jencode([
                                    'status'  => "error",
                                    'message' => __("website/account/prohibited-alert"),
                                ]));


                            $create_data = [
                                'type'             => "member",
                                'name'             => $result["data"]["name"],
                                'surname'          => $result["data"]["surname"],
                                'full_name'        => $full_name,
                                'email'            => $result["data"]["email"],
                                'password'         => User::_crypt("member", $password, 'encrypt', '-O@cb6Dxvarhc+Ghp}1eW~Y0yYfrfI#RBWdl'),
                                'creation_time'    => DateManager::Now(),
                                'last_login_time'  => DateManager::Now(),
                                'ip'               => UserManager::GetIP(),
                                'lang'             => $lang,
                                'country'          => $country,
                                'currency'         => Money::getUCID(),
                                'balance_currency' => Money::getUCID(),
                            ];

                            $affiliates = Cookie::get("Affiliates", true);
                            $affiliates = $affiliates ? Utility::jdecode($affiliates, true) : [];

                            if ($affiliates) $create_data['aff_id'] = current(array_keys($affiliates));


                            $create = User::create($create_data);

                            if ($create) {
                                $user = User::getData($create, "id,status,type,email,password,lang", "array");

                                $create_idata = [
                                    'kind'                => "individual",
                                    'verified-email'      => $result["data"]["email"],
                                    'email_notifications' => 1,
                                    'sms_notifications'   => 1,
                                ];
                                if ($affiliates) $create_idata['aff_ctime'] = current($affiliates);
                                if ($affiliates) {
                                    $day = Config::get("options/affiliate/cookie-duration");
                                    array_shift($affiliates);
                                    foreach ($affiliates as $k => $v) {
                                        $expires = DateManager::strtotime(DateManager::next_date([$v, 'day' => $day]));
                                        if (DateManager::strtotime() > $expires) unset($affiliates[$k]);
                                    }
                                    if ($affiliates) {
                                        $date = end($affiliates);
                                        $expires = DateManager::strtotime(DateManager::next_date([$date, 'day' => $day]));
                                        Cookie::set("Affiliates", Utility::jencode($affiliates), $expires, true);
                                    } else
                                        Cookie::delete("Affiliates");
                                }


                                User::setInfo($create, $create_idata);

                                $client_data = array_merge((array)User::getData($create,
                                    [
                                        'id',
                                        'status',
                                        'name',
                                        'surname',
                                        'full_name',
                                        'company_name',
                                        'email',
                                        'phone',
                                        'currency',
                                        'lang',
                                        'country',
                                    ], "array"), User::getInfo($create,
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
                                $client_data["address"] = AddressManager::getAddress(0, $create);
                                $client_data['source'] = "api";
                                $client_data['password'] = $password;
                                Hook::run("ClientCreated", $client_data);

                            }
                        }

                        if (isset($user["type"]) && $user["type"] == "admin") $user = false;

                        if (!$user) {
                            if ($format == "json")
                                die(Utility::jencode([
                                    'status'  => "error",
                                    'message' => __("website/sign/connect-with-provider-err1"),
                                ]));
                            else
                                die(__("website/sign/connect-with-provider-err1"));
                        }

                        $user = Utility::jdecode(Utility::jencode($user));

                        $e_p_b = false;
                        $g_info = User::getInfo($user->id, ['exempt-proxy-check']);
                        $g_info = $g_info["exempt-proxy-check"];
                        $e_p_b = $g_info ? true : false;

                        if (!$e_p_b && Config::get("options/proxy-block") && UserManager::is_proxy() === true)
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => ___("errors/error9"),
                            ]));


                        if ($user->status != "active")
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => __("website/sign/in-submit-status-blocking"),
                            ]));

                        User::addAction($user->id, "add", "connected-to-provider", ['name' => $mod_name]);

                        $redirect = $this->do_login($user->id, $user, false, true);

                        if ($format == "json")
                            echo Utility::jencode([
                                'status'   => "successful",
                                'redirect' => $redirect,
                            ]);
                        else
                            Utility::redirect($redirect);

                    } else {
                        if ($format == "json")
                            die(Utility::jencode([
                                'status'  => "error",
                                'message' => $module->error,
                            ]));
                        else
                            die($module->error);
                    }
                }
            }
        }


        private function sign_up()
        {
            if (!Config::get("options/sign/up/status")) return $this->main_404();

            if (UserManager::LoginCheck("member")) return Utility::redirect($this->CRLink("my-account"));

            if (Filter::isPOST()) return $this->sign_up_submit();

            if (Config::get("options/crtacwshop")) {
                Utility::redirect($this->CRLink("sign-in"));
                return false;
            }

            $this->takeDatas([
                "sign-all",
                "sign_logo_link",
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


            $captcha_status = Config::get("options/captcha/status") == 1 && Config::get("options/captcha/sign-up") == 1;
            if (!$captcha_status && Config::get("options/BotShield/status")) {
                Helper::Load("BotShield");
                $captcha_status = BotShield::is_blocked("sign-up");
            }

            if ($captcha_status) {
                Helper::Load("Captcha");
                $captcha = Helper::get("Captcha");
                $this->addData("captcha", $captcha);
            }

            $this->addData("meta", __("website/sign/meta/up"));
            $this->addData("kind_status", (Config::get("options/sign/up/kind/status") == 1));
            $this->addData("email_verify_status", (Config::get("options/sign/up/email/verify") == 1));
            $this->addData("gsm_status", (Config::get("options/sign/up/gsm/status") == 1));
            $this->addData("gsm_required", (Config::get("options/sign/up/gsm/required") == 1));
            $this->addData("sms_verify_status", (Config::get("options/sign/up/gsm/verify") == 1));

            $lang_list = $this->getData("lang_list");
            $lang_size = $this->getData("lang_count");
            if ($lang_size > 1) {
                $keys = array_keys($lang_list);
                $lang_size -= 1;
                for ($i = 0; $i <= $lang_size; $i++) {
                    if (!$lang_list[$keys[$i]]["selected"]) {
                        $key = $lang_list[$keys[$i]]["key"];
                        $lang_list[$keys[$i]]["link"] = $this->CRLink("sign-up", false, $key);
                    } else
                        $lang_list[$keys[$i]]["link"] = $this->ControllerURI();
                }
                $this->addData("lang_list", $lang_list);
            }

            $this->addData("custom_fields", $this->model->get_custom_fields(Bootstrap::$lang->clang));


            $this->view->chose("website")->render("sign-up", $this->data);
        }


        private function sign_out()
        {
            if (!UserManager::LoginCheck("member"))
                return Utility::redirect($this->CRLink("sign-in"));
            Helper::Load("User"); // $user = Helper::get("User");
            $data = UserManager::LoginData("member");
            User::addAction($data["id"], "out", "logged-out");
            UserManager::Logout("member");
            $referer = Utility::getReferer(true);
            $location = ($referer == '') ? $this->CRLink("home") : $referer;
            Utility::redirect($location);
        }


        private function sign_in()
        {

            if (!Config::get("options/sign/in/status")) return $this->main_404();

            if (UserManager::LoginCheck("member"))
                return Utility::redirect($this->CRLink("my-account"));

            if (Filter::isPOST() || Filter::init("GET/sso_token"))
                return $this->sign_in_submit();

            $this->takeDatas([
                "sign-all",
                "sign_logo_link",
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


            $captcha_status = Config::get("options/captcha/status") && Config::get("options/captcha/sign-in");
            if (!$captcha_status && Config::get("options/BotShield/status")) {
                Helper::Load("BotShield");
                $captcha_status = BotShield::is_blocked("sign-in");
            }

            if ($captcha_status) {
                Helper::Load("Captcha");
                $captcha = Helper::get("Captcha");
                $this->addData("captcha_sign_in", $captcha);
            }

            $captcha_status = Config::get("options/captcha/status") && Config::get("options/captcha/sign-forget");
            if (!$captcha_status && Config::get("options/BotShield/status")) {
                Helper::Load("BotShield");
                $captcha_status = BotShield::is_blocked("sign-forget");
            }

            if ($captcha_status) {
                Helper::Load("Captcha");
                $captcha = Helper::get("Captcha");
                $this->addData("captcha_sign_forget", $captcha);
            }


            $lang_list = $this->getData("lang_list");
            $lang_size = $this->getData("lang_count");
            if ($lang_size > 1) {
                $keys = array_keys($lang_list);
                $lang_size -= 1;
                for ($i = 0; $i <= $lang_size; $i++) {
                    if (!$lang_list[$keys[$i]]["selected"]) {
                        $key = $lang_list[$keys[$i]]["key"];
                        $lang_list[$keys[$i]]["link"] = $this->CRLink("sign-in", false, $key);
                    } else
                        $lang_list[$keys[$i]]["link"] = $this->ControllerURI();
                }
                $this->addData("lang_list", $lang_list);
            }


            $this->addData("meta", __("website/sign/meta/in"));
            $this->view->chose("website")->render("sign-in", $this->data);
        }


        private function sign_up_submit()
        {
            if (Config::get("options/sign/up/status") !== 1)
                return false;

            $this->takeDatas("language");

            $from = Filter::init("REQUEST/from", "route");


            if (DEMO_MODE)
                die(Utility::jencode([
                    'type'    => "information",
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            Helper::Load("BotShield");

            $captcha_status = Config::get("options/captcha/status") && Config::get("options/captcha/sign-up");
            if (!$captcha_status && Config::get("options/BotShield/status")) {
                $captcha_status = BotShield::is_blocked("sign-up");
            }

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'sign'))
                    die(Utility::jencode([
                        'type'    => "information",
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }


            if ($captcha_status && $from != "order_steps") {
                Helper::Load("Captcha");
                $captcha = Helper::get("Captcha");
                if (!$captcha->check())
                    die(Utility::jencode([
                        'type'    => "information",
                        'status'  => "error",
                        'for'     => $captcha->input_name ? "input[name='" . $captcha->input_name . "']" : null,
                        'message' => ___("needs/submit-invalid-captcha"),
                    ]));
            }

            $affiliates = Cookie::get("Affiliates", true);
            $affiliates_s = Session::get("Affiliates", true);

            if (!$affiliates && $affiliates_s) $affiliates = $affiliates_s;
            $affiliates = $affiliates ? Utility::jdecode($affiliates, true) : [];


            $cfields = Filter::POST("cfields");

            $full_name = trim(Filter::init("POST/full_name", "hclear"));
            $full_name = Utility::substr($full_name, 0, 255);
            $full_name = Utility::ucfirst_space($full_name);

            $email = Filter::init("POST/email", "email");
            $password = Filter::init("POST/password", "password");
            $passworda = Filter::init("POST/password_again", "password");
            $contract = Filter::init("POST/contract", "rnumbers");

            // This information will be added to the users_informations table data.
            $gsm = Filter::init("POST/gsm", "numbers");
            $gsm = Utility::substr($gsm, 0, 20);
            $kind = Filter::init("POST/kind", "letters");

            if (!$kind || !($kind == 'individual' || $kind == 'corporate')) $kind = "individual";

            // Validation Empty Check

            if (Validation::isEmpty($full_name))
                die(Utility::jencode([
                    'type'    => "information",
                    'status'  => "error",
                    'for'     => "input[name='full_name']",
                    'message' => __("website/sign/up-submit-empty-full_name"),
                ]));

            $smash = Filter::name_smash($full_name);
            $name = $smash["first"];
            $surname = $smash["last"];

            $identity = Filter::init("POST/identity", "identity");
            $birthday = Filter::init("POST/birthday", "numbers", "\/\-");
            $company_name = Filter::init("POST/company_name", "hclear");
            $company_taxnu = Filter::init("POST/company_tax_number", "letters_numbers", "-");
            $company_taxoff = Filter::init("POST/company_tax_office", "hclear");

            $country_x = Filter::init("POST/country", "numbers");
            $city = Filter::init("POST/city", "hclear");
            $counti = Filter::init("POST/counti", "hclear");
            $address = Filter::init("POST/address", "hclear");
            $zipcode = substr(Filter::init("POST/zipcode", "hclear"), 0, 20);

            $identity_status = Config::get("options/sign/up/kind/individual/identity/status");
            $identity_required = Config::get("options/sign/up/kind/individual/identity/required");
            $identity_checker = Config::get("options/sign/up/kind/individual/identity/checker");
            $birthday_status = Config::get("options/sign/birthday/status");
            $birthday_required = Config::get("options/sign/birthday/required");
            $birthday_adult_verify = Config::get("options/sign/birthday/adult_verify");
            $email_notifications = (int)Filter::init("POST/email_notifications", "rnumbers");
            $sms_notifications = (int)Filter::init("POST/sms_notifications", "rnumbers");


            if (Validation::isEmpty($name) || Validation::isEmpty($surname))
                die(Utility::jencode([
                    'type'    => "information",
                    'status'  => "error",
                    'for'     => "input[name='full_name']",
                    'message' => __("website/sign/up-submit-empty-full_name"),
                ]));


            if (Validation::isEmpty($email))
                die(Utility::jencode([
                    'type'    => "information",
                    'status'  => "error",
                    'for'     => "input[name='email']",
                    'message' => __("website/sign/up-submit-empty-email"),
                ]));

            if (!Validation::isEmail($email))
                die(Utility::jencode([
                    'type'    => "information",
                    'status'  => "error",
                    'for'     => "input[name='email']",
                    'message' => __("website/sign/up-submit-invalid-email"),
                ]));

            Helper::Load(["User", "Money"]);


            if (Config::get("options/block-user-temporary-email") && UserManager::is_temporary_mail($email))
                die(Utility::jencode([
                    'type'    => "information",
                    'status'  => "error",
                    'message' => ___("errors/error11"),
                ]));


            if (User::email_check($email, "member") || User::email_check($email, "admin"))
                die(Utility::jencode([
                    'type'    => "information",
                    'status'  => "error",
                    'for'     => "input[name='email']",
                    'message' => __("website/sign/up-submit-email-somebody-else"),
                ]));

            if (
                Config::get("options/sign/up/gsm/status") == 1 &&
                Config::get("options/sign/up/gsm/required") == 1
            ) {
                if (Validation::isEmpty($gsm) || strlen($gsm) < 5)
                    die(Utility::jencode([
                        'type'    => "information",
                        'status'  => "error",
                        'for'     => "#gsm",
                        'message' => __("website/sign/up-submit-empty-gsm"),
                    ]));

                if (!Validation::isPhone($gsm))
                    die(Utility::jencode([
                        'type'    => "information",
                        'status'  => "error",
                        'for'     => "#gsm",
                        'message' => __("website/sign/invalid-gsm-number"),
                    ]));
            }

            if (strlen($gsm) >= 5) {
                $gsm_parse = Filter::phone_smash($gsm);
                $gsm_cc = $gsm_parse["cc"];
                $gsm = $gsm_parse["number"];
                $phone = $gsm_cc . $gsm;
            } else {
                $gsm_cc = '';
                $gsm = '';
                $phone = '';
            }

            if (
                Config::get("options/sign/up/gsm/status") == 1 &&
                Config::get("options/sign/up/gsm/checker") == 1 &&
                User::gsm_check($gsm, $gsm_cc, "member")
            )
                die(Utility::jencode([
                    'type'    => "information",
                    'status'  => "error",
                    'for'     => "#gsm",
                    'message' => __("website/sign/up-submit-gsm-somebody-else"),
                ]));

            if ($country_x) {
                $check_country = AddressManager::CheckCountry($country_x);
                if (!$check_country) {
                    die(Utility::jencode([
                        'type'    => "information",
                        'status'  => "error",
                        'for'     => "*[name=country]",
                        'message' => __("admin/invoices/error15"),
                    ]));
                }

                if (Validation::isEmpty($city))
                    die(Utility::jencode([
                        'type'    => "information",
                        'status'  => "error",
                        'message' => __("admin/invoices/error16"),
                    ]));

                if (Validation::isInt($city)) {
                    $check_city = AddressManager::CheckCity($city);
                    if (!$check_city) return false;
                }

                if (Validation::isEmpty($counti))
                    die(Utility::jencode([
                        'type'    => "information",
                        'status'  => "error",
                        'message' => __("admin/invoices/error17"),
                    ]));

                if (Validation::isInt($counti)) {
                    $check_counti = AddressManager::CheckCounti($counti);
                    if (!$check_counti) return false;
                }
            }


            $set_infos = [];

            if ($cfields) {
                foreach ($cfields as $k => $v) {
                    $k = (int)Filter::numbers($k);
                    $f = $this->model->get_custom_field($k);
                    if ($f) {
                        if ($f["required"] && Validation::isEmpty($v))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "#cfield_" . $k,
                                'message' => __("website/sign/empty-custom-field", ['{name}' => $f["name"]]),
                            ]));
                        $v = $f["type"] == "checkbox" && is_array($v) ? implode(",", $v) : $v;
                        $v = Filter::html_clear($v);
                        $set_infos['field_' . $k] = $v;
                    }
                }
            }


            if (Validation::isEmpty($password))
                die(Utility::jencode([
                    'type'    => "information",
                    'status'  => "error",
                    'for'     => "input[name='password']",
                    'message' => __("website/sign/up-submit-empty-password"),
                ]));

            if (Utility::strlen($password) < $min_length = Config::get("options/password-length"))
                die(Utility::jencode([
                    'type'    => "information",
                    'status'  => "error",
                    'for'     => "input[name='password']",
                    'message' => __("website/sign/password-is-too-short", ['{length}' => $min_length]),
                ]));

            if (Validation::isEmpty($passworda))
                die(Utility::jencode([
                    'type'    => "information",
                    'status'  => "error",
                    'for'     => "input[name='password_again']",
                    'message' => __("website/sign/up-submit-empty-password_again"),
                ]));

            if ($passworda != $password)
                die(Utility::jencode([
                    'type'    => "information",
                    'status'  => "error",
                    'for'     => "input[name='password_again']",
                    'message' => __("website/sign/up-submit-invalid-password_again"),
                ]));

            if ($contract != 1)
                die(Utility::jencode([
                    'type'    => "information",
                    'status'  => "error",
                    'message' => __("website/sign/up-submit-contract-not-selected"),
                ]));

            if (Config::get("options/proxy-block") && UserManager::is_proxy() === true)
                die(Utility::jencode([
                    'type'    => "information",
                    'status'  => "error",
                    'message' => ___("errors/error9"),
                ]));


            $epassword = User::_crypt("member", $password, "encrypt", '-O@cb6Dxvarhc+Ghp}1eW~Y0yYfrfI#RBWdl');

            $lang = Bootstrap::$lang->clang;
            $currency = Money::getUCID();
            $ipInfo = UserManager::ip_info();
            if ($ipInfo)
                $country = AddressManager::get_id_with_cc($ipInfo["countryCode"]);
            else
                $country = AddressManager::get_id_with_cc(Config::get("general/country"));


            if ($country_x) $country = $country_x;


            $brand = View::is_brand();
            if ($brand) {
                $account_limit = $brand["account_limit"];
                if ($account_limit) {
                    $total_accounts = $this->model->db->select("COUNT(id) AS total")->from("users")->where("type", "=", "member");
                    $total_accounts = $total_accounts->build() ? $total_accounts->getObject()->total : 0;
                    if ($account_limit <= $total_accounts)
                        die(Utility::jencode([
                            'type'    => "register",
                            'status'  => "error",
                            'message' => __("website/sign/up-submit-invalid-register") . " #ERROR: Account limit exceeded.",
                        ]));
                }
            }

            if (
                Validation::check_prohibited($full_name, ['domain', 'email', 'word']) ||
                Validation::check_prohibited($email, ['domain', 'email', 'word']) ||
                Validation::check_prohibited($phone, ['gsm'])
            )
                die(Utility::jencode([
                    'type'    => "information",
                    'status'  => "error",
                    'message' => __("website/account/prohibited-alert"),
                ]));


            if ($birthday_status && isset($_POST["birthday"])) {
                if ($birthday_required && Validation::isEmpty($birthday))
                    die(Utility::jencode([
                        'type'    => "information",
                        'status'  => "error",
                        'for'     => "input[name='birthday']",
                        'message' => __("website/sign/up-birthday-empty"),
                    ]));
                $birthday = str_replace("/", "-", $birthday);

                if ($birthday) $birthday = DateManager::format("Y-m-d", $birthday);
                $set_infos["birthday"] = $birthday;

                if ($birthday_adult_verify && $birthday) {
                    $age = DateTime::createFromFormat('Y-m-d', $birthday)->diff(new DateTime('now'))->y;
                    if ($age < 18)
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/account_info/error3"),
                        ]));
                }
            }

            if ($identity_status && isset($_POST["identity"]) && $country == 227) {
                if ($identity_required && Validation::isEmpty($identity))
                    die(Utility::jencode([
                        'type'    => "information",
                        'status'  => "error",
                        'for'     => "input[name='identity']",
                        'message' => __("website/sign/empty-identity-number"),
                    ]));

                if ($identity_checker) {

                    if ($birthday_status) {
                        if (Validation::isEmpty($birthday))
                            die(Utility::jencode([
                                'type'    => "information",
                                'status'  => "error",
                                'for'     => "input[name='birthday']",
                                'message' => __("website/sign/up-birthday-empty"),
                            ]));
                    }


                    $check = Validation::isidentity($identity, $full_name, $birthday);
                    if (!$check)
                        die(Utility::jencode([
                            'type'    => "information",
                            'status'  => "error",
                            'for'     => "input[name='identity']",
                            'message' => __("website/sign/up-submit-invalid-identity"),
                        ]));

                    if (User::identity_check($identity))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "input[name='identity']",
                            'message' => "T.C.K.N. bir başkası tarafından kullanılmaktadır.",
                        ]));
                }
                $set_infos["identity"] = $identity;
            }


            $h_ClientDetailsValidation = Hook::run("ClientDetailsValidation", [
                'source'           => "client",
                'name'             => $name,
                'surname'          => $surname,
                'full_name'        => $full_name,
                'company_name'     => $company_name,
                'company_tax_num'  => $company_taxnu,
                'company_tax_off'  => $company_taxoff,
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

            $data = [
                'type'             => "member",
                'name'             => $name,
                'surname'          => $surname,
                'full_name'        => $full_name,
                'company_name'     => $company_name,
                'email'            => $email,
                'password'         => $epassword,
                'last_login_time'  => DateManager::Now(),
                'creation_time'    => DateManager::Now(),
                'lang'             => $lang,
                'currency'         => $currency,
                'balance_currency' => $currency,
                'country'          => $country,
            ];


            if ($affiliates) $data['aff_id'] = current(array_keys($affiliates));


            $register = $this->model->register($data);

            if (!$register)
                die(Utility::jencode([
                    'type'    => "register",
                    'status'  => "error",
                    'message' => __("website/sign/up-submit-invalid-register"),
                ]));


            $idata = [
                'contract1'           => 1,
                'contract2'           => 1,
                'email_notifications' => $email_notifications,
                'sms_notifications'   => $sms_notifications,
                'company_name'        => $company_name,
            ];

            if ($set_infos) $idata = array_merge($idata, $set_infos);


            if ($country_x) {
                $address_data = [
                    'name'               => $name,
                    'surname'            => $surname,
                    'full_name'          => $full_name,
                    'kind'               => $kind,
                    'company_name'       => $company_name,
                    'company_tax_office' => $company_taxoff,
                    'company_tax_number' => $company_taxnu,
                    'identity'           => $identity,
                    'email'              => $email,
                    'phone'              => $phone,
                    'owner_id'           => $register,
                    'country_id'         => $country,
                    'city'               => $city,
                    'counti'             => $counti,
                    'address'            => $address,
                    'zipcode'            => $zipcode,
                    'detouse'            => 1,
                ];
                $register_address = Models::$init->db->insert("users_addresses", $address_data);
                $register_address = $register_address ? Models::$init->db->lastID() : 0;
                if ($register_address) $idata['default_address'] = $register_address;
            }


            if ($affiliates) $idata['aff_ctime'] = current($affiliates);
            if ($affiliates) {
                $day = Config::get("options/affiliate/cookie-duration");
                array_shift($affiliates);
                foreach ($affiliates as $k => $v) {
                    $expires = DateManager::strtotime(DateManager::next_date([$v, 'day' => $day]));
                    if (DateManager::strtotime() > $expires) unset($affiliates[$k]);
                }
                if ($affiliates) {
                    $date = end($affiliates);
                    $expires = DateManager::strtotime(DateManager::next_date([$date, 'day' => $day]));
                    Cookie::set("Affiliates", Utility::jencode($affiliates), $expires, true);
                } else
                    Cookie::delete("Affiliates");
            }


            if (isset($gsm) && isset($gsm_cc)) {
                $idata["gsm"] = $gsm;
                $idata["gsm_cc"] = $gsm_cc;
                $idata["phone"] = $phone;
            }

            if (Config::get("options/sign/up/kind/status") == 1) {
                $idata["kind"] = $kind;
            }

            if ($company_taxnu) $idata["company_tax_number"] = $company_taxnu;
            if ($company_taxoff) $idata["company_tax_office"] = $company_taxoff;


            User::AddInfo($register, $idata);

            // Delete Newsletter
            if ($this->model->isNewsletter("email", $email)) $this->model->DeleteNewsletter("email", $email);
            if (isset($gsm) && $this->model->isNewsletter("gsm", $gsm)) $this->model->DeleteNewsletter("gsm", $gsm);

            Helper::Load(["Notification"]);

            if (Config::get("options/sign/up/email/verify")) {
                $sending_bte = ['hour' => 1];
                $code = rand(1000, 9999);
                $sending = Notification::email_activation($register, $code);
                $sending_limit = 1;
                if ($sending == "OK") {
                    if ($sending_limit != 0) {
                        $total_sending = LogManager::getLogCount("verify-code-email");
                        $total_sending++;
                        LogManager::setLogCount("verify-code-email", $total_sending);

                        if ($total_sending == $sending_limit && current($sending_bte)) {
                            User::addBlocked("verify-code-send-email", $register, [
                                'ip'    => UserManager::GetIP(),
                                'email' => $email,
                            ], DateManager::next_date($sending_bte));
                            LogManager::deleteLogCount("verify-code-email");
                        }
                    }
                }
            }

            if (Config::get("options/sign/up/gsm/verify")) {
                $sending_limit = 1;
                $sending_bte = ['hour' => 1];
                $code = rand(1000, 9999);
                $sending = Notification::gsm_activation($register, $code);
                if ($sending == "OK") {
                    if ($sending_limit != 0) {
                        $total_sending = LogManager::getLogCount("verify-code-gsm");
                        $total_sending++;
                        LogManager::setLogCount("verify-code-gsm", $total_sending);

                        if ($total_sending == $sending_limit && current($sending_bte)) {
                            User::addBlocked("verify-code-send-gsm", $register, [
                                'ip'    => UserManager::GetIP(),
                                'phone' => $phone,
                            ], DateManager::next_date($sending_bte));
                            LogManager::deleteLogCount("verify-code-gsm");
                        }
                    }
                }
            }


            $token = UserManager::Create_Login_Token($register, $email, $epassword);
            UserManager::Login("member", $register, $epassword, $lang, '-O@cb6Dxvarhc+Ghp}1eW~Y0yYfrfI#RBWdl');
            User::Login_Refresh($register, $token);
            User::addLastLogin($register, $token);

            User::addAction($register, "added", "sign-up");
            User::addAction($register, "in", "logged-on");

            $redirect = Session::get("loc_address");
            if (Validation::isEmpty($redirect) || $redirect == APP_URI . "/") $redirect = $this->CRLink("my-account");


            if (!$captcha_status && Config::get("options/BotShield/status")) BotShield::was_attempt("sign-up");


            echo Utility::jencode([
                'type'     => "register",
                'status'   => "successful",
                'redirect' => $redirect,
            ]);

            Notification::welcome($register);

            $client_data = array_merge((array)User::getData($register,
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
                ], "array"), User::getInfo($register,
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
            $client_data["address"] = AddressManager::getAddress(0, $register);
            $client_data["source"] = "client";
            $client_data["password"] = $password;
            Hook::run("ClientCreated", $client_data);
        }


        private function sign_in_submit()
        {
            if (Config::get("options/sign/in/status") !== 1) return false;

            Helper::Load(["BotShield", "User"]);

            $this->takeDatas("language");

            $from = Filter::init("REQUEST/from", "route");
            $sso = Filter::init("GET/sso_token");

            if ($sso && is_string($sso)) {
                $sso = Crypt::decode($sso, "<sso>" . Config::get("crypt/user") . "</sso>");
                if ($sso) {
                    $sso = Utility::jdecode($sso, true);
                    if ($sso) {
                        $api_id = $sso["api_id"] ?? "na";
                        $now = DateManager::strtotime();
                        $expiry = $sso["expiry"] ?? $now - 1;

                        if ($expiry > $now) {
                            $user_id = (int)$sso["id"] ?? 0;
                            $user = $user_id > 0 ? User::getData($user_id, "id,email,password") : false;
                            if ($user) {
                                $sso_email = $user->email;
                                $sso_password = $user->password;
                                $this->sso = $sso;
                            }
                        }
                    }
                }
            }


            if ($sso && isset($sso_email) && isset($sso_password) && $sso_email && $sso_password) {
                $email = $sso_email;
                $password = $sso_password;
            } else {
                $sso = false;
                $email = Filter::init("POST/email", "email");
                $password = Filter::init("POST/password", "password");
            }

            if (!$sso && !Filter::isPOST()) {
                Utility::redirect($this->CRLink("sign-in"));
                return false;
            }

            $remember = Filter::init("POST/remember", "rnumbers");

            $h_PreLoggedOn = Hook::run("PreClientLoggedOn", [
                'email'    => $email,
                'password' => $password,
                'remember' => $remember,
            ]);

            $h_user_id = 0;

            if ($h_PreLoggedOn) {
                foreach ($h_PreLoggedOn as $item) {
                    if (isset($item["user_id"]) && $item["user_id"]) {
                        $h_user_id = $item["user_id"];
                    }
                }
            }


            if ($sso) $h_user_id = $sso["id"];


            if (!$sso) {
                $two_factor = Config::get("options/two-factor-verification");
                $location_verification = Config::get("options/location-verification");

                if ($two_factor) {
                    if (Filter::POST("action") == "two-factor-check") return $this->two_factor_check();
                    elseif (Filter::POST("action") == "two-factor-resend") return $this->two_factor_resend();
                }

                if ($location_verification) if (Filter::POST("action") == "location-verification") return $this->location_verification();


                $captcha_status = Config::get("options/captcha/status") && Config::get("options/captcha/sign-in");
                if (!$captcha_status && Config::get("options/BotShield/status")) {
                    $captcha_status = BotShield::is_blocked("sign-in");
                }

                if (!defined("DISABLE_CSRF")) {
                    $token = Filter::init("POST/token", "hclear");
                    if (!$token || !Validation::verify_csrf_token($token, 'sign'))
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => ___("needs/invalid-csrf-token"),
                        ]));
                }

                if ($captcha_status && $from != "order_steps") {
                    Helper::Load("Captcha");
                    $captcha = Helper::get("Captcha");
                    if (!$captcha->check())
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => $captcha->input_name ? "input[name='" . $captcha->input_name . "']" : null,
                            'message' => ___("needs/submit-invalid-captcha"),
                        ]));
                }

            }


            if (!$h_user_id) {
                if (Validation::isEmpty($email))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='email']",
                        'message' => __("website/sign/in-submit-empty-email"),
                    ]));

                elseif (Validation::isEmpty($password))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='password']",
                        'message' => __("website/sign/in-submit-empty-password"),
                    ]));

                elseif (!Validation::isEmail($email))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='email']",
                        'message' => __("website/sign/in-submit-invalid-email"),
                    ]));
            }

            if (!$sso) {
                $blocking_time = Config::get("options/blocking-times/sign-in-attempt");
                if (User::CheckBlocked("login-brute-force", 0, [
                    'ip' => UserManager::GetIP(),
                ]))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/sign/in-submit-blocking", [
                            '{blocking-time}' => DateManager::str_expression($blocking_time),
                        ]),
                    ]));
            }


            if ($h_user_id)
                $check = User::getData($h_user_id, ["id", "status", "lang", "email", "password", "phone"]);
            else
                $check = User::LoginCheck('member', $email, $password);


            if (!$check) {
                $attemptc = LogManager::getLogCount("sign_in_attempt_count");
                $attemptl = Config::get("options/sign/in/attempt_limit");

                $attemptc++;

                LogManager::setLogCount("sign_in_attempt_count", $attemptc);

                if ($attemptl != 0 && $attemptc == $attemptl && current($blocking_time)) {
                    User::addBlocked('login-brute-force', 0, [
                        'email' => $email,
                        'ip'    => UserManager::GetIP(),
                    ], DateManager::next_date($blocking_time));
                    LogManager::deleteLogCount("sign_in_attempt_count");
                }

                if (!$captcha_status && Config::get("options/BotShield/status")) BotShield::was_attempt("sign-in");

                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/sign/in-submit-invalid-info"),
                ]));
            }

            if (Utility::strtolower($check->email) !== Utility::strtolower($email)) return false;

            LogManager::deleteLogCount("sign_in_attempt_count");

            $user_id = $check->id;

            if ($check->status == "inactive")
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/sign/in-submit-status-inactive"),
                ]));

            if ($check->status == "blocked")
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/sign/in-submit-status-blocking"),
                ]));

            $info = User::getInfo($check->id, [
                'block-proxy-usage',
                'exempt-proxy-check',
            ]);

            $e_p_b = $info["exempt-proxy-check"] ? true : false;


            if (!$e_p_b && ($info["block-proxy-usage"] || Config::get("options/proxy-block")) && UserManager::is_proxy() === true)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => ___("errors/error9"),
                ]));


            Helper::Load(["User", "Notification"]);

            $phone = $check->phone;

            $ip = UserManager::GetIP();


            $u_data = User::getInfo($check->id, ["two_factor"]);


            $ip_check = $this->model->db->select("id")->from("users_last_logins AS ull");
            $ip_check->where("ull.owner_id", "=", $check->id, "&&");
            $ip_check->where("ull.ip", "=", $ip, "&&");
            $ip_check->where("ull.ctime BETWEEN CURDATE() - INTERVAL 30 DAY AND CURDATE() + INTERVAL 1 DAY");
            $ip_check->limit(1);
            $ip_check = $ip_check->build() ? $ip_check->getObject()->id : 0;


            if (!$sso) {
                if ($two_factor && $phone && $u_data["two_factor"] == 1 && !$ip_check) {
                    $code = rand(1000, 9999);
                    $send = Notification::gsm_activation($user_id, $code);
                    $expire_minute = 3;
                    $expire = DateManager::next_date(['minute' => $expire_minute]);
                    if ($send == "OK") {
                        Session::set("two_factor", Utility::jencode([
                            'user_id'  => $user_id,
                            'code'     => $code,
                            'expire'   => DateManager::strtotime($expire),
                            'remember' => $remember,
                            'phone'    => $phone,
                        ]), true);

                        $phone = "+" . $phone;
                        $phone_len = strlen($phone) - 4;
                        $phone_end = substr($phone, -4);
                        $phone = str_repeat("*", $phone_len) . $phone_end;

                        die(Utility::jencode([
                            'status' => "two-factor",
                            'expire' => $expire,
                            'phone'  => $phone,
                        ]));
                    } else
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => 'Failed to Send Verification Code.',
                        ]));
                } elseif ($location_verification && !$ip_check) {
                    $info = UserManager::ip_info();
                    if ($info) {
                        $country = $info["countryCode"];
                        $city = $info["city"];

                        $last_login = Models::$init->db->select()->from("users_last_logins");
                        $last_login->where("owner_id", "=", $user_id);
                        $last_login->order_by("id DESC");
                        $last_login = $last_login->build() ? $last_login->getAssoc() : false;

                        if ($last_login["country_code"] && $last_login["city"]) {
                            if ((Config::get("options/location-verification-type") == "city" && ($country != $last_login["country_code"] || $city != $last_login["city"])) || (Config::get("options/location-verification-type") == "country" && $country != $last_login["country_code"])) {
                                if ($phone) {
                                    $phone = "+" . $phone;
                                    $phone_len = strlen($phone) - 4;
                                    $phone_end = substr($phone, -4);
                                    $phone = str_repeat("*", $phone_len) . $phone_end;
                                }
                                Session::delete("location_verification");
                                $this->location_verification($user_id, $phone, $remember);
                            }
                        }
                    }
                }
            }

            $this->do_login($user_id, $check, $remember);
        }


        private function location_verification($user_id = 0, $phone = '', $remember = 0)
        {

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'sign'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            $location_verification = Session::get("location_verification", true);

            $selected_method = false;

            if (!$user_id && $location_verification) {
                $location_verification = Utility::jdecode($location_verification, true);
                if ($location_verification) {
                    $user_id = $location_verification["user_id"];
                    $selected_method = $location_verification["selected_method"];
                    $phone = $location_verification["phone"];
                }
            }

            if (!$user_id) die();

            $apply = Filter::POST("apply");
            $selection_method = Filter::init("POST/selected_method", "route");

            $methods = [];
            if ($phone) $methods['phone'] = $phone;

            Helper::Load(["User", "Notification"]);

            $user_info = User::getInfo($user_id, ["security_question", "security_question_answer"]);
            $security_question = $user_info["security_question"];
            $security_question_answer = $user_info["security_question_answer"];
            if ($security_question) {
                $security_question = Crypt::decode($security_question, Config::get("crypt/user"));
                $security_question_answer = Crypt::decode($security_question_answer, Config::get("crypt/user"));
            }
            if ($security_question && $security_question_answer) $methods["security_question"] = $security_question;


            if (!$methods) $operation = false;
            elseif (!$location_verification && sizeof($methods) == 1) {
                $keys = array_keys($methods);
                $operation = "selected_method";
                $selection_method = $keys[0];
            } elseif ($selection_method && !$selected_method) $operation = "selected_method";
            elseif ($selected_method && $apply == "check") $operation = "check";
            elseif ($selected_method == "phone" && $apply == "resend") {

                $phone_data = isset($location_verification["phone_data"]) ? $location_verification["phone_data"] : false;
                if (!$phone_data) die();

                $expire = (int)$phone_data["expire"];

                if ($expire > DateManager::strtotime())
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/sign/two-factor-wait-expire"),
                    ]));

                $selection_method = "phone";
                $selected_method = false;
                $operation = "selected_method";
            } else $operation = "selection";


            if ($operation == "selection") {
                $result = [
                    'methods'         => $methods,
                    'status'          => 'location-verification',
                    'selected_method' => false,
                ];
                $location_verification_data = [];

                $location_verification_data["user_id"] = $user_id;
                $location_verification_data["phone"] = $phone;
                $location_verification_data["remember"] = $remember;
                $location_verification_data["selected_method"] = false;

                Session::set("location_verification", Utility::jencode($location_verification_data), true);
                die(Utility::jencode($result));
            } elseif ($operation == "selected_method") {
                if (!($selection_method == "phone" || $selection_method == "security_question") || !isset($methods[$selection_method]))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => "Please select a method.",
                    ]));

                $result = [
                    'status'          => 'location-verification',
                    'selected_method' => $selection_method,
                ];
                $location_verification_data = [];

                $location_verification_data["user_id"] = $user_id;
                $location_verification_data["remember"] = $remember;
                $location_verification_data["selected_method"] = $selection_method;

                if ($selection_method == "phone") {

                    $result["phone"] = $phone;
                    $location_verification_data["phone"] = $phone;

                    $code = rand(1000, 9999);
                    $send = Notification::gsm_activation($user_id, $code);
                    $expire_minute = 3;
                    $expire = DateManager::next_date(['minute' => $expire_minute]);
                    if ($send == "OK") {

                        $location_verification_data["phone_data"]["expire"] = DateManager::strtotime($expire);
                        $location_verification_data["phone_data"]["code"] = $code;
                        $result["expire"] = $expire;

                    } else
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => 'Failed to Send Verification Code.',
                        ]));

                } elseif ($selection_method == "security_question") {
                    $result["security_question"] = $security_question;
                }

                Session::set("location_verification", Utility::jencode($location_verification_data), true);
                die(Utility::jencode($result));
            } elseif ($operation == "check") {

                if ($selected_method == "phone") {
                    $phone_data = isset($location_verification["phone_data"]) ? $location_verification["phone_data"] : false;
                    if (!$phone_data) die();

                    $expire = (int)$phone_data["expire"];
                    $code = (int)$phone_data["code"];

                    if ($expire < DateManager::strtotime())
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/sign/two-factor-expired"),
                        ]));

                    $enter_code = (int)Filter::init("POST/code", "numbers");

                    if (!$enter_code || $enter_code !== $code)
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => 'input[name=code]',
                            'message' => __("website/sign/two-factor-invalid-code"),
                        ]));
                } elseif ($selected_method == "security_question") {
                    $enter_answer = Filter::init("POST/security_question_answer", "hclear");
                    $enter_answer = trim($enter_answer);
                    $enter_answer = Utility::strtolower($enter_answer);
                    $security_question_answer = Utility::strtolower($security_question_answer);

                    if ($enter_answer !== $security_question_answer)
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => 'input[name=security_answer]',
                            'message' => __("website/sign/invalid-security-question-answer"),
                        ]));
                }

                Session::delete("location_verification");

                $check = User::getData($user_id, "id,email,password,lang");

                $this->do_login($user_id, $check, $remember);
            }

        }


        private function two_factor_check()
        {

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'sign'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            $two_factor = Session::get("two_factor", true);
            if (!$two_factor)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Invalid Two Factor Verification",
                ]));
            $two_factor = Utility::jdecode($two_factor, true);
            $user_id = (int)$two_factor["user_id"];
            $code = (int)$two_factor["code"];
            $expire = (int)$two_factor["expire"];
            $remember = (int)$two_factor["remember"];

            if (!$expire || $expire < DateManager::strtotime())
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/sign/two-factor-expired"),
                ]));

            $enter_code = (int)Filter::init("POST/code", "numbers");

            if (!$enter_code || $enter_code !== $code)
                die(Utility::jencode([
                    'status'  => "error",
                    'for'     => 'input[name=code]',
                    'message' => __("website/sign/two-factor-invalid-code"),
                ]));

            Helper::Load(["User"]);

            $check = User::getData($user_id, "id,email,password,lang");

            Session::delete("two_factor");

            $this->do_login($user_id, $check, $remember);
        }


        private function two_factor_resend()
        {

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'sign'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            $two_factor = Session::get("two_factor", true);
            if (!$two_factor)
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => "Invalid Two Factor Verification",
                ]));
            $two_factor = Utility::jdecode($two_factor, true);
            $user_id = (int)$two_factor["user_id"];
            $phone = $two_factor["phone"];
            $expire = (int)$two_factor["expire"];
            $remember = (int)$two_factor["remember"];

            if ($expire > DateManager::strtotime())
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => __("website/sign/two-factor-wait-expire"),
                ]));

            Helper::Load(["User", "Notification"]);

            $code = rand(1000, 9999);
            $send = Notification::gsm_activation($user_id, $code);
            $expire_minute = 3;
            $expire = DateManager::next_date(['minute' => $expire_minute]);
            if ($send == "OK") {
                Session::set("two_factor", Utility::jencode([
                    'user_id'  => $user_id,
                    'code'     => $code,
                    'expire'   => DateManager::strtotime($expire),
                    'remember' => $remember,
                    'phone'    => $phone,
                ]), true);

                $phone = "+" . $phone;
                $phone_len = strlen($phone) - 4;
                $phone_end = substr($phone, -4);
                $phone = str_repeat("*", $phone_len) . $phone_end;

                die(Utility::jencode([
                    'status' => "two-factor",
                    'expire' => $expire,
                    'phone'  => $phone,
                ]));
            } else
                die(Utility::jencode([
                    'status'  => "error",
                    'message' => 'Failed to Send Verification Code.',
                ]));

        }


        private function do_login($user_id, $check, $remember = false, $return = false)
        {
            $email = $check->email;
            $epassword = $check->password;

            $affiliates = Cookie::get("Affiliates", true);
            $affiliates = $affiliates ? Utility::jdecode($affiliates, true) : [];
            if ($affiliates) {
                $day = Config::get("options/affiliate/cookie-duration");
                foreach ($affiliates as $k => $v) {
                    $expires = DateManager::strtotime(DateManager::next_date([$v, 'day' => $day]));
                    if (DateManager::strtotime() > $expires) unset($affiliates[$k]);
                }
                $affiliates = Utility::jencode($affiliates);
                User::setInfo($user_id, ['Affiliates' => $affiliates]);
            }


            $login_token = UserManager::Create_Login_Token($user_id, $email, $epassword);

            if ($remember) UserManager::Login_Remember("member", $login_token);

            UserManager::Login("member", $user_id, $epassword, $check->lang, '-O@cb6Dxvarhc+Ghp}1eW~Y0yYfrfI#RBWdl');
            User::Login_Refresh($user_id, $login_token);
            User::addLastLogin($user_id, $login_token);
            User::addAction($user_id, "in", $this->sso ? "Logged on with SSO" : "logged-on", $this->sso);

            Helper::Load("Money");
            $getData = User::getData($user_id, "currency,lang");
            $currency = $getData->currency;
            $lang = $getData->lang;

            if (Money::Currency($currency, true)) if ($currency != 0) Money::setCurrency($currency);

            if (Bootstrap::$lang->LangExists($lang)) Bootstrap::$lang->change($lang, false);

            $redirect = $this->sso ? $this->sso["redirect"] ?? '' : Session::get("loc_address");


            if (!$redirect || $redirect == $this->CRLink("home") || $redirect == APP_URI . "/") $redirect = $this->CRLink("my-account", false, $lang);

            if ($redirect == $this->CRLink("sign-out")) $redirect = $this->CRLink("my-account", false, $lang);


            $client_data = array_merge((array)User::getData($user_id,
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
                ], "array"), User::getInfo($user_id,
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
            $client_data["address"] = AddressManager::getAddress(0, $user_id);
            $client_data["source"] = "client";

            Hook::run("ClientLoggedOn", $client_data);

            if (!$return) {
                if ($this->sso) Utility::redirect($redirect);
                else
                    die(Utility::jencode([
                        'status'   => "successful",
                        'redirect' => $redirect,
                    ]));
            }

            return $redirect;
        }


        private function reset_password_submit($user_id = 0, $info = [], $by = '')
        {
            $this->takeDatas("language");

            if (DEMO_MODE)
                die(Utility::jencode([
                    'type'    => "information",
                    'status'  => "error",
                    'message' => __("website/others/demo-mode-error"),
                ]));

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'sign'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }


            $password = Filter::init("POST/password", "password");
            $password_again = Filter::init("POST/password_again", "password");
            $security_question_answer = Utility::strtolower(Filter::init("POST/security_question_answer", "hclear"));


            if ($by == "mobile" && $info["security_question"]) {
                if (Validation::isEmpty($security_question_answer))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='security_question_answer']",
                        'message' => __("website/account_info/empty-have-fields"),
                    ]));

                if ($security_question_answer != Utility::strtolower($info["security_question_answer"]))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='security_question_answer']",
                        'message' => __("website/sign/invalid-security-question-answer"),
                    ]));
            }

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

            $udata = ['id' => $user_id];

            User::setData($udata["id"], ['password' => $epassword]);
            User::deleteInfo($user_id, ['reset_password_key', 'reset_password_exp']);

            User::addAction($udata["id"], "alteration", "changed-password");

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

            echo Utility::jencode([
                'status'   => "successful",
                'redirect' => $this->CRLink("sign-in"),
            ]);
        }


        private function sign_forget()
        {

            if (!Config::get("options/sign/in/status")) return false;

            if (Filter::isPOST()) {

                $this->takeDatas("language");

                $from = Filter::init("REQUEST/from", "route");

                Helper::Load("BotShield");

                $captcha_status = Config::get("options/captcha/status") && Config::get("options/captcha/sign-forget");
                if (!$captcha_status && Config::get("options/BotShield/status")) {
                    $captcha_status = BotShield::is_blocked("sign-forget");
                }

                if (!defined("DISABLE_CSRF")) {
                    $token = Filter::init("POST/token", "hclear");
                    if (!$token || !Validation::verify_csrf_token($token, 'sign'))
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => ___("needs/invalid-csrf-token"),
                        ]));
                }

                if ($captcha_status && $from != "order_steps") {
                    Helper::Load("Captcha");
                    $captcha = Helper::get("Captcha");
                    if (!$captcha->check())
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => $captcha->input_name ? "input[name='" . $captcha->input_name . "']" : null,
                            'message' => ___("needs/submit-invalid-captcha"),
                        ]));
                }

                $email = Filter::init("POST/email", "email");

                if (Validation::isEmpty($email))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='email']",
                        'message' => __("website/sign/forget-submit-empty-email"),
                    ]));

                if (!Validation::isEmail($email))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='email']",
                        'message' => __("website/sign/forget-submit-invali-email"),
                    ]));

                if (DEMO_MODE)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/others/demo-mode-error"),
                    ]));

                Helper::Load("User");

                $blocking_time = Config::get("options/blocking-times/forget-password");

                if (User::CheckBlocked("forget-password", 0, [
                    'ip' => UserManager::GetIP(),
                ]))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/sign/forget-submit-blocking", [
                            '{blocking-time}' => DateManager::str_expression($blocking_time),
                        ]),
                    ]));

                if (!User::email_check($email, "member")) {
                    if (!$captcha_status && Config::get("options/BotShield/status")) BotShield::was_attempt("sign-forget");

                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='email']",
                        'message' => __("website/sign/forget-submit-invalid-info"),
                    ]));
                }

                $info = $this->model->get_user_info($email);
                if (!$info)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/sign/forget-unexpected-error"),
                    ]));

                Helper::Load("Notification");

                $send = Notification::forget_password($info->id);

                if ($send == "OK") {
                    if (sizeof($blocking_time) > 0)
                        User::addBlocked("forget-password", 0, [
                            'email' => $email,
                            'ip'    => UserManager::GetIP(),
                        ], DateManager::next_date($blocking_time));

                    User::addAction($info->id, "sent", "forgotten-password");

                    echo Utility::jencode(['status' => "sent"]);

                } else
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/sign/forget-failed-send"),
                    ]));

            }
        }


        private function reset_password()
        {
            Helper::Load(["User"]);
            if (UserManager::LoginCheck("member")) {
                Utility::redirect($this->CRLink("my-account"));
                return false;
            }

            $verify_key = Filter::REQUEST("verify");
            $by_name = Filter::REQUEST("by");

            if (!$verify_key) return false;

            $key_encode = Crypt::encode($verify_key, Config::get("crypt/user"));

            $find = Models::$init->db->select()->from("users_informations");
            $find->where("name", "=", "reset_password_key", "&&");
            $find->where("content", "=", $key_encode);
            $find = $find->build() ? $find->getObject() : false;
            if ($find) {
                $user_id = $find->owner_id;

                $user_data = User::getData($user_id, "type", "array");

                if ($user_data["type"] != "member") return false;

                $info = User::getInfo($user_id, [
                    "reset_password_exp",
                    "security_question",
                    "security_question_answer",
                ]);
                $expire = $info["reset_password_exp"];
                if ($expire) $expire = Crypt::decode($expire, Config::get("crypt/user"));
                $expire = (int)$expire;
                if (!$expire || $expire < DateManager::strtotime()) $user_id = false;
                if ($info["security_question"]) {
                    $info["security_question"] = Crypt::decode($info["security_question"], Config::get("crypt/user"));
                    $info["security_question_answer"] = Crypt::decode($info["security_question_answer"], Config::get("crypt/user"));
                    $security_question = $info["security_question"];
                }
            }

            if (isset($user_id) && $user_id && Filter::isPOST()) return $this->reset_password_submit($user_id, $info, $by_name);

            if (isset($user_id) && $user_id) $this->addData("user_id", $user_id);

            $this->addData("by_name", $by_name);

            if (isset($security_question) && $security_question) $this->addData("security_question", $security_question);


            $this->takeDatas([
                "sign-all",
                "sign_logo_link",
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

            $this->addData("controller_link", $this->CRLink("sign/reset-password") . "?verify=" . $verify_key . "&by=" . $by_name);

            $this->addData("meta", __("website/sign/meta/reset-password"));

            $this->view->chose("website")->render("sign-reset-password", $this->data);
        }

    }