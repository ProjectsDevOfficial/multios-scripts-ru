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
        private $type, $id;
        public const ATTACHMENT_FOLDER = RESOURCE_DIR . "uploads" . DS . "attachments" . DS;


        public function __construct($arg = [])
        {
            parent::__construct();
            $this->params = $arg['params'];

            if (!Config::get("options/basket-system")) {
                $this->main_404();
                die();
            }

            if (!Config::get("options/visitors-will-see-basket") && !UserManager::LoginCheck("member")) {
                Utility::redirect($this->CRLink("sign-in"));
                die();
            }
        }


        private function step_token($type = '', $id = 0, $step = '', $extra = '')
        {
            $ext = isset($extra) && $extra != '' ? "-" . $extra : null;
            return $type . "-" . $id . "-" . $step . $ext;
        }


        private function set_step($token, $data)
        {
            $data = Utility::jencode($data);
            Session::set($token, $data, true);
        }


        private function delete_step($token)
        {
            if (Session::get($token)) Session::delete($token);
        }


        private function get_step($token)
        {
            $data = Session::get($token, true);
            if ($data) $data = Utility::jdecode($data, true);
            return $data ? $data : [];
        }


        private function get_product($type = '', $id = 0)
        {
            $lang = Bootstrap::$lang->clang;
            $data = false;
            Helper::Load("Products");

            if($type != "special" && !Config::get("options/pg-activation/".$type))
                return false;

            if ($type == "software") $data = Products::get($type, $id, $lang);
            elseif ($type == "domain") $data = true;
            elseif ($type == "hosting") $data = Products::get($type, $id, $lang);
            elseif ($type == "server") $data = Products::get($type, $id, $lang);
            elseif ($type == "sms") $data = Products::get($type, $id, $lang);
            elseif ($type == "special") $data = Products::get($type, $id, $lang);

            if (isset($data['status']) && $data["status"] != "active") {
                Utility::redirect(APP_URI);
                return false;
            }

            if($type == "special") {
                $group = Products::getCategory($data["type_id"] ?? 0,false,"t1.id,t1.status");
                if($group["status"] != "active")
                    return false;
            }

            return $data;
        }


        private function addons($ids = '', $selection = [])
        {
            if (is_array($selection) && $selection) {
                $period = $selection["period"];
                $time = $selection["time"];
            }

            $new_result = [];

            if (!Validation::isEmpty($ids)) {
                $lang = Bootstrap::$lang->clang;
                $result = $this->model->get_addons($lang, $ids);
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
                                $var["override_usrcurrency"] = $product_link["override_usrcurrency"] ?? 0;
                                $result[$keys[$i]]["options"] = $var["options"];
                                $result[$keys[$i]]["override_usrcurrency"] = $var["override_usrcurrency"];
                            }
                        }

                        $show_by_pp = isset($var["properties"]["show_by_pp"]) ? $var["properties"]["show_by_pp"] : false;

                        if ($show_by_pp && !isset($availableAddons)) $availableAddons = [];

                        if (isset($period) && isset($time) && $period && $show_by_pp) {
                            if ($var["options"]) {
                                foreach ($var["options"] as $opt) {
                                    if ($opt["period_time"] == 0) $opt["period_time"] = 1;
                                    // $opt["period"] == "none" ||
                                    if (($opt["period"] == $period && $opt["period_time"] == $time)) {
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


        private function requirements($ids = '', $additional_requirements = [])
        {
            $return = [];
            if ($additional_requirements) {
                $ids_arr = explode(",", $ids);
                foreach ($additional_requirements as $row) {
                    if (in_array($row["id"], $ids_arr)) $return[] = $row;
                }
            } elseif (!Validation::isEmpty($ids)) {
                $lang = Bootstrap::$lang->clang;
                $result = $this->model->get_requirements($lang, $ids);
                if ($result) {
                    $keys = array_keys($result);
                    $size = sizeof($keys) - 1;
                    for ($i = 0; $i <= $size; $i++) {
                        $var = $result[$keys[$i]];
                        $result[$keys[$i]]["options"] = $var["options"] ? Utility::jdecode($var["options"], true) : [];
                        $result[$keys[$i]]["properties"] = $var["properties"] ? Utility::jdecode($var["properties"], true) : [];
                    }
                    $return = $result;
                }
            }
            return $return;
        }


        private function default_header_background()
        {
            $cache = self::$cache;
            $cache->setCache("osteps");
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


        private function get_product_header_background($type = '', $id = 0)
        {
            $cache = self::$cache;
            $cache->setCache("osteps");
            $cname = "header_background-" . $type . "-" . $id;
            $cache->eraseExpired();
            if (!$cache->isCached($cname) || !Config::get("general/cache")) {
                $data = false;

                if ($type == "software") {
                    $data = $this->model->get_software_product($id);
                    $hrbg = $data["header_background"];

                    if (!$hrbg && $data["category"]) $hrbg = $this->model->get_category_header_background($data["category"]);

                    if (!$hrbg) $hrbg = $this->model->get_header_background_default($type);

                    $data = $hrbg;

                } elseif ($type == "hosting" || $type == "server" || $type == "special") {
                    $data = $this->model->get_product($id);
                    $hrbg = false;
                    if (!$hrbg && $data["category"]) $hrbg = $this->model->get_category_header_background($data["category"]);
                    if (!$hrbg) $hrbg = $this->model->get_header_background_default($type);

                    $data = $hrbg;

                } else {
                    $hrbg = $this->model->get_header_background_default($type, $id);
                    $data = $hrbg;
                }

                if ($data) $data = Utility::image_link_determiner($data, Config::get("pictures/header-background/folder"));
                if (Config::get("general/cache")) $cache->store($cname, $data);
            } else
                $data = $cache->retrieve($cname);
            return $data;
        }


        private function getTLD($name = '', $rank = '0')
        {
            $cache = self::$cache;
            $cache->setCache("osteps");
            $cname = "tld-";
            $cname .= $name != '' ? $name : null;
            $cname .= !is_string($rank) ? $rank : null;
            $cache->eraseExpired();
            if (!$cache->isCached($cname) || !Config::get("general/cache")) {
                $data = $this->model->getTLD($name, $rank);
                if ($data) {
                    $data["register"] = Products::get_price("register", "tld", $data["id"]);
                    $data["renewal"] = Products::get_price("renewal", "tld", $data["id"]);
                }
                if (Config::get("general/cache")) $cache->store($cname, $data);
            } else
                $data = $cache->retrieve($cname);
            return $data;
        }


        private function getHostingList($product = [], $step_data = false)
        {
            $lang = Bootstrap::$lang->clang;

            $period = false;
            $time = false;
            if (!$product && $step_data) $product = Products::get("domain", $step_data["tld"]);
            if ($step_data) {
                if ($product["type"] == "domain") {
                    $period = "year";
                    $time = $step_data["period"];
                } else {
                    $period = $step_data["selection"]["period"];
                    $time = $step_data["selection"]["time"];
                }
            }

            $promotions = Products::get_promotions_for_product($product["type"], $product["id"], $period, $time);

            $data = false;
            $getGroups = $this->model->getCategoriesHosting(0, $lang);
            if ($getGroups) {
                $data = [];
                foreach ($getGroups as $g) {
                    $g["route"] = $this->CRLink("products", [$g["route"]]);
                    $d = [];
                    $productsx = $this->getProductsHosting($g["id"], $lang, $promotions);
                    if ($productsx) {
                        $d = array_merge($d, $g);
                        $d["products"] = $productsx;
                    }
                    $categories = $this->model->getCategoriesHosting($g["id"], $lang);
                    if ($categories) {
                        foreach ($categories as $c) {
                            $dc = [];
                            $products = $this->getProductsHosting($c["id"], $lang, $promotions);
                            if ($products) {
                                if (!$productsx && !isset($d["title"])) $d = array_merge($d, $g);
                                $dc = $c;
                                $dc["products"] = $products;
                            }
                            if ($dc) $d["categories"][] = $dc;
                        }
                    }
                    array_push($data, $d);
                }
            }
            return $data;
        }


        private function getProductsHosting($category = 0, $lang = '', $promotions = [])
        {

            $data = $this->model->getProductsHosting($category, $lang);
            if ($data) {
                $keys = array_keys($data);
                $size = sizeof($keys) - 1;
                for ($i = 0; $i <= $size; $i++) {
                    $var = $data[$keys[$i]];
                    $data[$keys[$i]]["price"] = Products::get_price("periodicals", "products", $var["id"], $lang);
                    $data[$keys[$i]]["options"] = $var["options"] == "" ? [] : Utility::jdecode($var["options"], true);
                    $data[$keys[$i]]["optionsl"] = $var["optionsl"] == "" ? [] : Utility::jdecode($var["optionsl"], true);
                    $data[$keys[$i]]["module_data"] = $var["module_data"] == "" ? [] : Utility::jdecode($var["module_data"], true);

                    ## PROMOTION CONTROL START ##
                    $price = $data[$keys[$i]]["price"]["amount"];
                    $cid = $data[$keys[$i]]["price"]["cid"];
                    $period = $data[$keys[$i]]["price"]["period"];
                    $time = $data[$keys[$i]]["price"]["time"];

                    $promotions_x = Products::get_product_promotional("hosting", $var["id"], $period, $time);
                    if ($promotions_x) {
                        foreach ($promotions_x as $row) {
                            if (isset($promotions[$row["id"]])) {
                                $price = Products::apply_promotion($row, $price, $cid);
                                $data[$keys[$i]]["price"]["amount"] = $price;
                            }
                        }
                    }
                    ## PROMOTION CONTROL END ##


                }
            }
            return $data;
        }


        private function software_post($data = [])
        {

            $this->takeDatas("language");

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'order-steps'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            $product = $data["product"];
            $hide_domain = isset($product["options"]["hide_domain"]) && $product["options"]["hide_domain"];
            $hide_hosting = isset($product["options"]["hide_hosting"]) && $product["options"]["hide_hosting"];

            if ($data["step"] == 1) { // Step 1 START

                $selection = Filter::init("POST/selection", "numbers");

                if (Validation::isEmpty($selection) || !Validation::isInt($selection))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/osteps/error6"),
                    ]));

                $selection = (int)$selection;

                if (!isset($data["product"]["price"][$selection]))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/osteps/error6"),
                    ]));

                $selection = $data["product"]["price"][$selection];

                $this->set_step($data["step_token"], ['selection' => $selection, 'status' => "completed"]);


                $getHostingList = $this->getHostingList($data["product"]);
                $getAddons = $this->addons($data["product"]["addons"]);
                $getRequirements = $this->requirements($data["product"]["requirements"]);

                Helper::Load(["Basket"]);

                if (!$hide_domain) {
                    $continue_token = $this->step_token($data["type"], $data["id"], "domain");
                    $continue_url = $this->CRLink("order-steps-p", [$data["type"], $data["id"], "domain"]);
                } elseif (Config::get("options/pg-activation/hosting") && $getHostingList && !$hide_hosting) {
                    $continue_token = $this->step_token($data["type"], $data["id"], "hosting");
                    $continue_url = $this->CRLink("order-steps-p", [$data["type"], $data["id"], "hosting"]);
                } elseif ($getAddons) {
                    $continue_token = $this->step_token($data["type"], $data["id"], "addons");
                    $continue_url = $this->CRLink("order-steps-p", [$data["type"], $data["id"], "addons"]);
                } elseif ($getRequirements) {
                    $continue_token = $this->step_token($data["type"], $data["id"], "requirements");
                    $continue_url = $this->CRLink("order-steps-p", [$data["type"], $data["id"], "requirements"]);
                }

                if (!$hide_domain || (Config::get("options/pg-activation/hosting") && $getHostingList && !$hide_hosting) || $getAddons || $getRequirements) {
                    $this->set_step($continue_token, ['status' => "incomplete"]);
                    die(Utility::jencode([
                        'status'   => "successful",
                        'redirect' => $continue_url,
                    ]));
                } else {
                    $category_title = isset($data["product"]["category_title"]) ? $data["product"]["category_title"] : '';
                    $category_route = isset($data["product"]["category_route"]) ? $data["product"]["category_route"] : '';

                    Basket::set(false, $data["product"]["title"], [
                        'event'          => "SoftwareOrder",
                        'type'           => "software",
                        'id'             => $data["product"]["id"],
                        'selection'      => $selection,
                        'category'       => $category_title,
                        'category_route' => $category_route,
                    ], false);
                    Basket::save();

                    die(Utility::jencode([
                        'status'   => "successful",
                        'redirect' => $this->CRLink("basket"),
                    ]));
                }


            } // Step 1 END

            if ($data["step"] == "domain") { // Step Domain START

                $step1 = $this->get_step($this->step_token($data["type"], $data["id"], 1));
                $selection = $step1["selection"];

                if (!$step1) die("Step 1 is empty");

                $type = Filter::init("POST/type", "letters");
                $domain = Filter::init("POST/domain", "domain");
                $domain = str_replace("www.", "", $domain);
                $domain = trim($domain);
                $sld = null;
                $tld = null;
                $parse = Utility::domain_parser($domain);
                if ($parse["host"] != '' && strlen($parse["host"]) >= 2) {
                    $sld = $parse["host"];
                    $tld = $parse["tld"];
                }


                $getFirstTLD = $this->getTLD(null, 0);
                if ($getFirstTLD) $getFirstTLD = $getFirstTLD["name"];
                $tld = $tld == null ? $getFirstTLD : $tld;
                $fdomain = $sld . "." . $tld;

                if ($sld == '' || $tld == '')
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => $type == "registrar" ? "#DomainCheck input[name='domain']" : "#license_domain",
                        'message' => __("website/osteps/error1"),
                    ]));

                if ($type == "registrar" || $type == "license") {

                    $getHostingList = $this->getHostingList($data["product"]);
                    $getAddons = $this->addons($data["product"]["addons"]);
                    $getRequirements = $this->requirements($data["product"]["requirements"]);

                    if (Validation::check_prohibited($fdomain, ['domain', 'word']))
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/account/prohibited-alert"),
                        ]));


                    Helper::Load(["Basket"]);
                    if ($type == "registrar") {
                        $tinfo = $this->getTLD($tld);
                        if ($tinfo) {

                            if (Basket::count()) {
                                foreach (Basket::listing() as $item) {
                                    $options = $item["options"];
                                    if ($options["type"] == "domain" && $item["name"] == $fdomain)
                                        die(Utility::jencode([
                                            'status'  => "error",
                                            'for'     => "#DomainCheck input[name='domain']",
                                            'message' => __("website/domain/error11"),
                                        ]));
                                }
                            }

                            $domain_unique = Basket::set(false, $fdomain, [
                                'event'          => "DomainNameRegisterOrder",
                                'type'           => "domain",
                                'id'             => $tinfo["id"],
                                'period'         => "year",
                                'period_time'    => 1,
                                'category'       => __("website/osteps/category-domain"),
                                'category_route' => $this->CRLink("domain"),
                                'sld'            => $sld,
                                'tld'            => $tld,
                                'dns'            => Config::get("options/ns-addresses"),
                            ], false);

                            Basket::save();
                        }
                    }

                    if (Config::get("options/pg-activation/hosting") && $getHostingList && !$hide_hosting) {
                        $continue_token = $this->step_token($data["type"], $data["id"], "hosting");
                        $continue_url = $this->CRLink("order-steps-p", [$data["type"], $data["id"], "hosting"]);
                    } elseif ($getAddons) {
                        $continue_token = $this->step_token($data["type"], $data["id"], "addons");
                        $continue_url = $this->CRLink("order-steps-p", [$data["type"], $data["id"], "addons"]);
                    } elseif ($getRequirements) {
                        $continue_token = $this->step_token($data["type"], $data["id"], "requirements");
                        $continue_url = $this->CRLink("order-steps-p", [$data["type"], $data["id"], "requirements"]);
                    }

                    if ((Config::get("options/pg-activation/hosting") && $getHostingList && !$hide_hosting) || $getAddons || $getRequirements) {
                        $this->set_step($data["step_token"], [
                            'type'          => $type,
                            'domain'        => $fdomain,
                            'domain_unique' => isset($domain_unique) ? $domain_unique : false,
                            'status'        => "completed",
                        ]);
                        $this->set_step($continue_token, ['status' => "incomplete"]);
                        die(Utility::jencode([
                            'status'   => "successful",
                            'redirect' => $continue_url,
                        ]));
                    } else {
                        $category_title = isset($data["product"]["category_title"]) ? $data["product"]["category_title"] : '';
                        $category_route = isset($data["product"]["category_route"]) ? $data["product"]["category_route"] : '';

                        Basket::set(false, $data["product"]["title"], [
                            'event'          => "SoftwareOrder",
                            'type'           => "software",
                            'id'             => $data["product"]["id"],
                            'domain'         => $fdomain,
                            'selection'      => $selection,
                            'category'       => $category_title,
                            'category_route' => $category_route,
                        ], false);
                        Basket::save();

                        die(Utility::jencode([
                            'status'   => "successful",
                            'redirect' => $this->CRLink("basket"),
                        ]));
                    }
                } else die("Error #1");
            } // Step Domain END

            if ($data["step"] == "hosting") { // Step Hosting START
                $type = Filter::init("POST/type", "letters");
                $lang = Bootstrap::$lang->clang;

                Helper::Load("Basket");
                $step1 = $this->get_step($this->step_token($data["type"], $data["id"], 1));
                $step2 = $this->get_step($this->step_token($data["type"], $data["id"], "domain"));


                if ($type == "selection") {
                    $selection = Filter::init("POST/selection", "numbers");
                    if (Validation::isEmpty($selection))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "select[name='selection']",
                            'message' => __("website/osteps/error2"),
                        ]));

                    $hosting = $this->model->getProductHosting($selection, $lang);
                    if (!$hosting)
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "select[name='selection']",
                            'message' => __("website/osteps/error2"),
                        ]));

                    $getPrice = Products::get_price("periodicals", "products", $hosting["id"], $lang);
                    $getCategory = $this->model->getTopCategory($hosting["category"], $lang);

                    if (!$step1) die("Step 1 is empty");

                    if ($getCategory) {
                        $category_title = $getCategory["title"];
                        $category_route = $this->CRLink("products", [$getCategory["route"]]);
                    }

                    $fdomain = $step2["domain"] ?? '';

                    $subdomain_hosting_detection = false;
                    $same_domain_detection = false;

                    $main_domain = Utility::getDomain();
                    $whois_servers = include STORAGE_DIR . "whois-servers.php";
                    if ($whois_servers) {
                        $servers = [];
                        foreach ($whois_servers as $k => $v) {
                            $k_split = explode(",", $k);
                            foreach ($k_split as $k_row) {
                                $servers[$k_row] = $v;
                            }
                        }
                        $servers = array_keys($servers);
                        $parse2 = Utility::domain_parser($main_domain);
                        $main_tld = $parse2["tld"];
                        if (!in_array($main_tld, $servers)) $main_domain = $main_tld;
                    }

                    if (stristr($fdomain, '.' . $main_domain)) $subdomain_hosting_detection = true;
                    if ($main_domain == $fdomain) $same_domain_detection = true;

                    if (Config::get("options/allow-sub-hosting")) $subdomain_hosting_detection = false;


                    if (Validation::check_prohibited($fdomain, ['domain', 'word']) || $subdomain_hosting_detection || $same_domain_detection)
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/account/prohibited-alert"),
                        ]));


                    $hosting_unique = Basket::set(false, $hosting["title"], [
                        'event'          => "HostingOrder",
                        'type'           => "hosting",
                        'id'             => $hosting["id"],
                        'selection'      => $getPrice,
                        'domain'         => $fdomain,
                        'category'       => isset($category_title) ? $category_title : null,
                        'category_route' => isset($category_route) ? $category_route : null,
                    ], false);
                    Basket::save();
                    $sdata = [
                        'status'         => "completed",
                        'type'           => $type,
                        'selection'      => $hosting["id"],
                        'hosting_unique' => $hosting_unique,
                    ];
                } elseif ($type == "none") {
                    $sdata = ['status' => "completed", 'type' => $type, 'hosting_unique' => false];
                } else
                    die("ERROR 1");


                $getAddons = $this->addons($data["product"]["addons"]);
                $getRequirements = $this->requirements($data["product"]["requirements"]);

                if ($getAddons || $getRequirements) {
                    $where = $getAddons ? "addons" : "requirements";
                    $this->set_step($data["step_token"], $sdata);
                    $this->set_step($this->step_token($data["type"], $data["id"], $where), ['status' => "incomplete"]);
                    $redirect = $this->CRLink("order-steps-p", [$data["type"], $data["id"], $where]);
                } else {
                    $category_title = isset($data["product"]["category_title"]) ? $data["product"]["category_title"] : '';
                    $category_route = isset($data["product"]["category_route"]) ? $data["product"]["category_route"] : '';

                    Basket::set(false, $data["product"]["title"], [
                        'event'          => "SoftwareOrder",
                        'type'           => "software",
                        'id'             => $data["product"]["id"],
                        'selection'      => $step1["selection"],
                        'domain'         => $step2["domain"],
                        'category'       => $category_title,
                        'category_route' => $category_route,
                    ], false);
                    Basket::save();

                    $this->delete_step($this->step_token($data["type"], $data["id"], 1));
                    $this->delete_step($this->step_token($data["type"], $data["id"], "domain"));
                    $this->delete_step($data["step_token"]);
                    $redirect = $this->CRLink("basket");
                }

                die(Utility::jencode([
                    'status'   => "successful",
                    'redirect' => $redirect,
                ]));

            } // Step Hosting END

            if ($data["step"] == "addons") { // Addons START

                $step1t = $this->step_token($data["type"], $data["id"], 1);
                $step2t = $this->step_token($data["type"], $data["id"], "domain");
                $step3t = $this->step_token($data["type"], $data["id"], "hosting");
                $step4t = $data["step_token"];
                $step1 = $this->get_step($step1t);
                $step2 = $this->get_step($step2t);
                $step3 = $this->get_step($step3t);
                $step4 = $this->get_step($step3t);

                $getAddons = $this->addons($data["product"]["addons"], isset($step1["selection"]) ? $step1["selection"] : false);
                $addons = Filter::POST("addons");
                $addons_values = Filter::POST("addons_values");
                $as_selected = [];
                $as_selected_v = [];

                if ($getAddons) {
                    foreach ($getAddons as $addon) {
                        if (isset($addons[$addon["id"]]) && Validation::isInt($addons[$addon["id"]])) {
                            $options = $addon["options"];
                            foreach ($options as $k => $v) {
                                if ($v["id"] == $addons[$addon["id"]]) {
                                    $as_selected[$addon["id"]] = $v["id"];
                                    if ($addon["type"] == "quantity") {
                                        if (isset($addons_values[$addon["id"]])) {
                                            $addon_quantity = (int)Filter::numbers($addons_values[$addon["id"]]);
                                            if ($addon_quantity) $as_selected_v[$addon["id"]] = $addon_quantity;
                                        }
                                    }
                                }
                            }
                        }
                        if (isset($addon["properties"]["compulsory"]) && $addon["properties"]["compulsory"] && !isset($as_selected[$addon["id"]]))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "*[name='addons[" . $addon["id"] . "]']",
                                'message' => __("website/osteps/addon-required", ['{name}' => $addon["name"]]),
                            ]));
                        if ($addon["type"] == "quantity" && isset($addon["properties"]["compulsory"]) && $addon["properties"]["compulsory"] && !isset($as_selected_v[$addon["id"]]))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "*[name='addons[" . $addon["id"] . "]']",
                                'message' => __("website/osteps/addon-required", ['{name}' => $addon["name"]]),
                            ]));
                    }
                }


                $requirements = [];
                foreach ($getAddons as $addon) {
                    if (isset($as_selected[$addon["id"]]) && strlen($addon["requirements"]) > 0) {
                        $rqs = explode(",", $addon["requirements"]);
                        foreach ($rqs as $rq) if (!in_array($rq, $requirements)) $requirements[] = $rq;
                    }
                }

                if (!$requirements && $data["product"]["requirements"])
                    $requirements = explode(",", $data["product"]["requirements"]);


                if (isset($requirements) && $requirements)
                    foreach ($requirements as $rk => $rq)
                        if (!is_array($rq))
                            if (!Products::requirement($rq)) unset($requirements[$rk]);

                if ($requirements) {
                    $sdata = [
                        'status'       => "completed",
                        'requirements' => $requirements,
                    ];

                    if ($as_selected) $sdata["addons"] = $as_selected;
                    if ($as_selected_v) $sdata["addons_values"] = $as_selected_v;

                    $this->set_step($step4t, $sdata);

                    $this->set_step($this->step_token($data["type"], $data["id"], "requirements"), ['status' => "incomplete"]);
                    $redirect = $this->CRLink("order-steps-p", [$data["type"], $data["id"], "requirements"]);

                } else {
                    $category_title = isset($data["product"]["category_title"]) ? $data["product"]["category_title"] : '';
                    $category_route = isset($data["product"]["category_route"]) ? $data["product"]["category_route"] : '';
                    Helper::Load("Basket");
                    $idata = [
                        'event'          => "SoftwareOrder",
                        'type'           => "software",
                        'id'             => $data["product"]["id"],
                        'selection'      => $step1["selection"],
                        'domain'         => isset($step2["domain"]) ? $step2["domain"] : false,
                        'category'       => $category_title,
                        'category_route' => $category_route,
                    ];

                    if ($as_selected) $idata["addons"] = $as_selected;
                    if ($as_selected_v) $idata["addons_values"] = $as_selected_v;

                    Basket::set(false, $data["product"]["title"], $idata, false);
                    Basket::save();

                    $this->delete_step($step1t);
                    $this->delete_step($step2t);
                    $this->delete_step($step3t);
                    $this->delete_step($step4t);

                    $redirect = $this->CRLink("basket");
                }

                echo Utility::jencode([
                    'status'   => "successful",
                    'redirect' => $redirect,
                ]);

            } // Addons END

            if ($data["step"] == "requirements") { // Requirements START

                $step1t = $this->step_token($data["type"], $data["id"], 1);
                $step2t = $this->step_token($data["type"], $data["id"], "domain");
                $step3t = $this->step_token($data["type"], $data["id"], "hosting");
                $step4t = $this->step_token($data["type"], $data["id"], "addons");
                $step5t = $data["step_token"];
                $step1 = $this->get_step($step1t);
                $step2 = $this->get_step($step2t);
                $step3 = $this->get_step($step3t);
                $step4 = $this->get_step($step4t);
                $step5 = $this->get_step($step5t);
                if (!$step1) $step1 = [];
                if (!$step2) $step2 = [];
                if (!$step3) $step3 = [];
                if (!$step4) $step4 = [];
                if (!$step5) $step5 = [];

                $step_data = array_merge($step1, $step2, $step3, $step4, $step5);

                if (!$step1) die("Not Found Step 1 Data");

                $getRequirements = $this->requirements($data["product"]["requirements"]);
                $requirements = Filter::POST("requirements");
                $rs_selected = [];

                if ($step4 && isset($step4["requirements"]))
                    $getRequirements = $this->requirements(implode(",", $step4["requirements"]));

                if ($getRequirements) {
                    foreach ($getRequirements as $requirement) {
                        $values = null;
                        $options = $requirement["options"];
                        $properties = $requirement["properties"];
                        if ($requirement["type"] == "file") {
                            $files = Filter::FILES("requirement-" . $requirement["id"]);
                            if (isset($properties["compulsory"]) && $properties["compulsory"])
                                if (!$files)
                                    die(Utility::jencode([
                                        'status'  => "error",
                                        'for'     => "input[name='requirement-" . $requirement["id"] . "[]']",
                                        'message' => __("website/osteps/field-required", ['{name}' => $requirement["name"]]),
                                    ]));

                            if ($files && !DEMO_MODE) {
                                $extensions = isset($options["allowed-extensions"]) ? $options["allowed-extensions"] : Config::get("options/product-fields-extensions");
                                $max_filesize = isset($options["max-file-size"]) ? $options["max-file-size"] : Config::get("options/product-fields-max-file-size");
                                Helper::Load("Uploads");
                                $upload = Helper::get("Uploads");
                                $upload->init($files, [
                                    'date'          => false,
                                    'multiple'      => true,
                                    'max-file-size' => $max_filesize,
                                    'folder'        => ROOT_DIR . "temp" . DS,
                                    'allowed-ext'   => $extensions,
                                    'file-name'     => "random",
                                    'width'         => Config::get("pictures/product-requirements/sizing/width"),
                                    'height'        => Config::get("pictures/product-requirements/sizing/height"),
                                ]);
                                if (!$upload->processed())
                                    die(Utility::jencode([
                                        'status'  => "error",
                                        'for'     => "input[name='requirement-" . $requirement["id"] . "[]']",
                                        'message' => __("website/osteps/failed-field-upload", ['{error}' => $upload->error]),
                                    ]));
                                if ($upload->operands) {
                                    $values = Utility::jencode($upload->operands);
                                }
                            }
                        } else {
                            if (isset($properties["compulsory"]) && $properties["compulsory"])
                                if ((($requirement["type"] == "input" || $requirement["type"] == "password" || $requirement["type"] == "textarea") && (!isset($requirements[$requirement["id"]]) || Validation::isEmpty($requirements[$requirement["id"]]))) || (($requirement["type"] == "select" || $requirement["type"] == "radio" || $requirement["type"] == "checkbox") && (!isset($requirements[$requirement["id"]]) || !Validation::isInt($requirements[$requirement["id"]]))))
                                    die(Utility::jencode([
                                        'status'  => "error",
                                        'for'     => "*[name='requirements[" . $requirement["id"] . "]']",
                                        'message' => __("website/osteps/field-required", ['{name}' => $requirement["name"]]),
                                    ]));

                            if ($requirement["type"] == "input" || $requirement["type"] == "password") {
                                $values = Filter::html_clear($requirements[$requirement["id"]]);
                                $values = Utility::short_text($values, 0, 500);
                            } elseif ($requirement["type"] == "textarea") {
                                $values = $requirements[$requirement["id"]];
                                $values = Utility::short_text($values, 0, 5000);
                            }

                            if ($requirement["type"] == "select" || $requirement["type"] == "radio" || $requirement["type"] == "checkbox") {
                                $values = [];
                                if (isset($requirements[$requirement["id"]])) {
                                    $value = $requirements[$requirement["id"]];
                                    if (!is_array($value)) {
                                        if (Validation::isInt($value)) $value = [$value];
                                        else $value = [];
                                    }
                                    foreach ($options as $opt) {
                                        if (in_array($opt["id"], $value)) {
                                            $values[] = $opt["id"];
                                        }
                                    }
                                }
                            }
                        }
                        if ($values) {
                            if (is_array($values)) $values = implode(",", $values);
                            $rs_selected[$requirement["id"]] = $values;
                        }

                        $checkingRequirement = Hook::run("checkingRequirementToOrderSteps", [
                            'product'     => $data["product"],
                            'step_data'   => $step_data,
                            'requirement' => $requirement,
                            'value'       => $values,
                        ]);

                        if ($checkingRequirement) {
                            foreach ($checkingRequirement as $row) {
                                if (isset($row["status"]) && $row["status"] == "error")
                                    die(Utility::jencode([
                                        'status'  => "error",
                                        'for'     => "*[name='requirements[" . $requirement["id"] . "]']",
                                        'message' => $row["message"],
                                    ]));

                            }
                        }

                    }
                }

                Helper::Load("Basket");
                $category_title = isset($data["product"]["category_title"]) ? $data["product"]["category_title"] : '';
                $category_route = isset($data["product"]["category_route"]) ? $data["product"]["category_route"] : '';

                $idata = [
                    'event'          => "SoftwareOrder",
                    'type'           => "software",
                    'id'             => $data["product"]["id"],
                    'selection'      => $step1["selection"],
                    'domain'         => isset($step2["domain"]) ? $step2["domain"] : '',
                    'category'       => $category_title,
                    'category_route' => $category_route,
                ];

                if ($step4 && isset($step4["addons"])) $idata["addons"] = $step4["addons"];
                if ($step4 && isset($step4["addons_values"])) $idata["addons_values"] = $step4["addons_values"];
                if ($rs_selected) $idata["requirements"] = $rs_selected;

                Basket::set(false, $data["product"]["title"], $idata, false);
                Basket::save();

                $redirect = $this->CRLink("basket");

                $this->delete_step($this->step_token($data["type"], $data["id"], 1));
                $this->delete_step($this->step_token($data["type"], $data["id"], "domain"));
                $this->delete_step($this->step_token($data["type"], $data["id"], "hosting"));
                $this->delete_step($this->step_token($data["type"], $data["id"], "addons"));
                $this->delete_step($data["step_token"]);

                echo Utility::jencode(['status' => "successful", 'redirect' => $redirect]);


            } // Requirements END

        }


        private function domain_post($data = [])
        {

            $this->takeDatas("language");

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'order-steps'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            Helper::Load("Basket");

            if ($data["step"] == "requirements") { // Requirements START

                $lang = Bootstrap::$lang->clang;

                $step1t = $this->step_token($data["type"], $data["id"], 1);
                $step1 = $this->get_step($step1t);


                if (!$step1) die("Not Found Step 1 Data");


                Helper::Load("Orders");

                $modules = Modules::Load("Registrars", "All", true);


                $paperwork = false;
                $extensions = [];
                $elected = [];

                if (isset($step1["elected"]) && is_array($step1["elected"]) && $step1["elected"]) {
                    foreach ($step1["elected"] as $k => $dom) {
                        $elected[$dom["tld"]] = $k;
                        $tinfo = $this->getTLD($dom["tld"]);
                        if ($tinfo) {
                            if (isset($tinfo['required_docs_info']) && strlen($tinfo['required_docs_info']) > 1) {
                                $tinfo['required_docs_info'] = Utility::jdecode($tinfo['required_docs_info'], true);
                                $lkeys = array_keys($tinfo['required_docs_info']);
                                if (isset($lkeys[$lang]))
                                    $tinfo['required_docs_info'] = $tinfo['required_docs_info'][$lang];
                                else
                                    $tinfo['required_docs_info'] = $tinfo['required_docs_info'][$lkeys[0]];
                            }


                            $dom["tinfo"] = $tinfo;
                            $extensions[$dom["tld"]] = $dom;

                            if ($tinfo["module"] != "none" && $tinfo["module"]) {
                                if (isset($modules[$tinfo["module"]]["config"]["settings"]["doc-fields"][$tinfo["name"]])) {
                                    if ($modules[$tinfo["module"]]["config"]["settings"]["doc-fields"][$tinfo["name"]])
                                        $paperwork = true;
                                }
                            }

                            $manuel_doc = $this->model->db->select("id")->from("tldlist_docs")->where("tld", "=", $tinfo["name"])->build();
                            if ($manuel_doc)
                                $paperwork = true;

                            if ($paperwork) $extensions[$dom["tld"]]["requirements"] = Orders::detect_docs_in_domain(false, $tinfo);
                        }
                    }
                }

                if (!$paperwork) {
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => "Not found documents",
                    ]));
                }

                foreach ($extensions as $k => $t) {
                    if (!$t["requirements"]) continue;

                    foreach ($t["requirements"] as $req_id => $requirement) {
                        $file_data = [];
                        $module_data = [];

                        $value = Filter::quotes(Filter::init("POST/requirements/" . $t["tld"] . "/" . $req_id, "hclear"));
                        $select_value = $value;
                        $required = $requirement["required"] ?? false;

                        if ($requirement['type'] == 'file')
                            $value = Filter::FILES("requirement-" . $t["tld"] . "-" . $req_id);

                        if (is_array($required) && $required) {
                            $required_fields = $required;
                            $required = false;

                            $pref = explode("_", $req_id)[0];
                            foreach ($required_fields as $target_f_id => $search_values) {
                                if ($required) continue;

                                $ptf = $pref . "_" . $target_f_id;
                                if (isset($t["requirements"][$ptf])) {
                                    $notEmpty = false;

                                    if (!is_array($search_values) && $search_values == "NOT_EMPTY") $notEmpty = true;
                                    if (!is_array($search_values)) $search_values = [$search_values];

                                    $target_f = $t["requirements"][$ptf];
                                    $target_type = $target_f["type"];

                                    if ($target_type == 'file')
                                        $target_value = Filter::FILES("requirement-" . $t["tld"] . "-" . $ptf);
                                    else
                                        $target_value = Filter::quotes(Filter::init("POST/requirements/" . $t["tld"] . "/" . $ptf, "hclear"));

                                    if (!$notEmpty && $target_type == "select" || $target_type == "text") {
                                        if (in_array($target_value, $search_values)) $required = true;
                                    } elseif (strlen($target_value) > 0) $required = true;
                                }
                            }
                        }


                        if ($required && (($requirement["type"] == "file" && !$value) || ($requirement["type"] != "file" && Utility::strlen($value) < 1))) {
                            echo Utility::jencode([
                                'status'  => "error",
                                'message' => __("website/osteps/error11"),
                            ]);
                            return false;
                        }

                        if ($requirement["type"] == "select" && strlen($value) > 0) {
                            if (!isset($requirement["options"][$value])) {
                                echo Utility::jencode([
                                    'status'  => "error",
                                    'message' => __("website/osteps/error11"),
                                ]);
                                return false;
                            }
                            $select_value = RegistrarModule::get_doc_lang($requirement["options"][$value]);
                        } elseif ($requirement["type"] == "file" && $value) {
                            Helper::Load("Uploads");

                            $exts = $requirement["allowed_ext"] ?? '';
                            if (!$exts) $exts = Config::get("options/product-fields-extensions");
                            $exts = str_replace(" ", "", $exts);
                            $max_file_size = $requirement["max_file_size"] ?? 3;
                            $max_file_size = FileManager::converByte($max_file_size . "MB");

                            Helper::Load("Uploads");
                            $upload = Helper::get("Uploads");

                            $upload->init($value, [
                                'date'          => false,
                                'multiple'      => false,
                                'max-file-size' => $max_file_size,
                                'folder'        => ROOT_DIR . "temp" . DS,
                                'allowed-ext'   => $exts,
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
                            $file_data['path'] = ROOT_DIR . "temp" . DS . $file_result["name"];
                            $file_data['size'] = $file_result["size"];
                        }


                        // Detect Module Data
                        if ($requirement["type"] != "file" && substr($req_id, 0, 4) == "mod_" && strlen($value) > 0) {
                            $mod_k = substr($req_id, 4);
                            $module_data = ['key' => $mod_k];

                            if ($requirement["type"] == "text") $module_data["value"] = $value;
                            elseif ($requirement["type"] == "select") $module_data["value"] = $value;
                            elseif ($requirement["type"] == "file") $module_data["value"] = $file_data["path"];
                        }

                        if ($requirement["type"] != "file" && strlen($value) < 1) continue;

                        $req_values = [
                            'name'        => $requirement['name'],
                            'value'       => $file_data ? '' : $select_value,
                            'module_data' => $module_data,
                            'file'        => $file_data,
                        ];

                        $step1["elected"][$elected[$t["tld"]]]["docs"][$req_id] = $req_values;
                        $extensions[$k]["docs"][$req_id] = $req_values;

                    }
                }


                $this->set_step($step1t, $step1);
                $this->set_step($data["step_token"], ['status' => "completed"]);


                $getHostingList = $this->getHostingList(false, $step1["elected"][0] ?? false);

                if (Config::get("options/domain-hide-hosting") || !Config::get("options/pg-activation/hosting"))
                    $getHostingList = false;


                if ($getHostingList) {
                    $continue_token = $this->step_token($data["type"], $data["id"], "hosting");
                    $this->set_step($continue_token, ['status' => "incomplete"]);
                    $redirect = $this->CRLink("order-steps-p", [$data["type"], $data["id"], "hosting"]);
                }
                else {
                    if ($extensions) {
                        foreach ($extensions as $dom) {
                            $tinfo = $dom["tinfo"];
                            $tdata = [
                                'event'          => isset($dom["tcode"]) && $dom["tcode"] != '' ? "DomainNameTransferRegisterOrder" : "DomainNameRegisterOrder",
                                'type'           => "domain",
                                'id'             => $tinfo["id"],
                                "period"         => "year",
                                'period_time'    => $dom["period"],
                                'category'       => __("website/osteps/category-domain"),
                                'category_route' => $this->CRLink("domain"),
                                'sld'            => $dom["sld"],
                                'tld'            => $dom["tld"],
                                'dns'            => Config::get("options/ns-addresses"),
                            ];
                            if (isset($dom["tcode"]) && $dom["tcode"] != '') $tdata["tcode"] = $dom["tcode"];
                            if (isset($dom["renewal"]) && $dom["renewal"]) {
                                $tdata["renewal"] = true;
                                $tdata["category"] = __("website/osteps/category-domain-renewal");
                            }
                            if (isset($dom["docs"]) && $dom["docs"]) $tdata["docs"] = $dom["docs"];
                            Basket::set(false, $dom["domain"], $tdata, false);
                        }
                        Basket::save();
                    }

                    $this->delete_step($step1t);
                    $this->delete_step($data["step_token"]);
                    $redirect = $this->CRLink("basket");
                }

                die(Utility::jencode([
                    'status'   => "successful",
                    'redirect' => $redirect,
                ]));
            } // Requirements END
            elseif ($data["step"] == "hosting") {
                $type = Filter::init("POST/type", "letters_numbers");
                $lang = Bootstrap::$lang->clang;

                $step1t = $this->step_token($data["type"], $data["id"], 1);
                $step2t = $this->step_token($data["type"], $data["id"], "requirements");
                $step1 = $this->get_step($step1t);


                if ($type == "none") {
                    $ns1 = Filter::init("POST/ns1", "domain");
                    $ns2 = Filter::init("POST/ns2", "domain");
                    $ns3 = Filter::init("POST/ns3", "domain");
                    $ns4 = Filter::init("POST/ns4", "domain");
                    $dns = [];

                    if (!Validation::isEmpty($ns1)) $dns["ns1"] = $ns1;
                    if (!Validation::isEmpty($ns2)) $dns["ns2"] = $ns2;
                    if (!Validation::isEmpty($ns3)) $dns["ns3"] = $ns3;
                    if (!Validation::isEmpty($ns4)) $dns["ns4"] = $ns4;

                    if (Validation::isEmpty($ns1) || Validation::isEmpty($ns2))
                        die(Utility::jencode([
                            'status'  => "error",
                            'message' => __("website/osteps/error4"),
                        ]));

                    $nsAddresses = Config::get("options/ns-addresses");
                    $nsbr = implode("<br>", $nsAddresses);

                    if (!Validation::isEmpty($ns1) && !Validation::NSCheck($ns1))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "input[name='ns1']",
                            'message' => __("website/osteps/error5", ['{ns}' => "NS1", '{dns-addresses}' => $nsbr]),
                        ]));

                    if (!Validation::isEmpty($ns2) && !Validation::NSCheck($ns2))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "input[name='ns2']",
                            'message' => __("website/osteps/error5", ['{ns}' => "NS2", '{dns-addresses}' => $nsbr]),
                        ]));

                    if (!Validation::isEmpty($ns3) && !Validation::NSCheck($ns3))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "input[name='ns3']",
                            'message' => __("website/osteps/error5", ['{ns}' => "NS3", '{dns-addresses}' => $nsbr]),
                        ]));

                    if (!Validation::isEmpty($ns4) && !Validation::NSCheck($ns4))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "input[name='ns4']",
                            'message' => __("website/osteps/error5", ['{ns}' => "NS4", '{dns-addresses}' => $nsbr]),
                        ]));

                }

                if ($type == "selection") {
                    $selection = Filter::init("POST/selection", "numbers");
                    if (Validation::isEmpty($selection))
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "select[name='selection']",
                            'message' => __("website/osteps/error2"),
                        ]));

                    $hosting = $this->model->getProductHosting($selection, $lang);
                    if (!$hosting)
                        die(Utility::jencode([
                            'status'  => "error",
                            'for'     => "select[name='selection']",
                            'message' => __("website/osteps/error2"),
                        ]));

                    $hosting_mopt = $hosting["module_data"] == '' ? [] : Utility::jdecode($hosting["module_data"], true);
                    $hosting_opt = $hosting["options"] == '' ? [] : Utility::jdecode($hosting["options"], true);
                    if ($hosting["module"] == "none" || $hosting["module"] == "") {
                        $dns = [];
                        if (isset($hosting_opt["ns1"]) && $hosting_opt["ns1"] != '') $dns["ns1"] = $hosting_opt["ns1"];
                        if (isset($hosting_opt["ns2"]) && $hosting_opt["ns2"] != '') $dns["ns2"] = $hosting_opt["ns2"];
                        if (isset($hosting_opt["ns3"]) && $hosting_opt["ns3"] != '') $dns["ns3"] = $hosting_opt["ns3"];
                        if (isset($hosting_opt["ns4"]) && $hosting_opt["ns4"] != '') $dns["ns4"] = $hosting_opt["ns4"];
                    } elseif (isset($hosting_opt["server_id"]) && $hosting_opt["server_id"]) {
                        $server = Products::get_server($hosting_opt["server_id"]);
                        $dns = [];
                        if ($server["ns1"] != '') $dns["ns1"] = $server["ns1"];
                        if ($server["ns2"] != '') $dns["ns2"] = $server["ns2"];
                        if ($server["ns3"] != '') $dns["ns3"] = $server["ns3"];
                        if ($server["ns4"] != '') $dns["ns4"] = $server["ns4"];
                    } elseif (isset($hosting_mopt["server_id"]) && $hosting_mopt["server_id"]) {
                        $server = Products::get_server($hosting_mopt["server_id"]);
                        $dns = [];
                        if ($server["ns1"] != '') $dns["ns1"] = $server["ns1"];
                        if ($server["ns2"] != '') $dns["ns2"] = $server["ns2"];
                        if ($server["ns3"] != '') $dns["ns3"] = $server["ns3"];
                        if ($server["ns4"] != '') $dns["ns4"] = $server["ns4"];
                    }

                    Helper::Load("Basket");
                    $getPrice = Products::get_price("periodicals", "products", $hosting["id"], $lang);
                    $getCategory = $this->model->getTopCategory($hosting["category"], $lang);
                    if (!$step1) die("Step 1 is empty");
                    if ($getCategory) {
                        $category_title = $getCategory["title"];
                        $category_route = $this->CRLink("products", [$getCategory["route"]]);
                        Basket::set(false, $hosting["title"], [
                            'event'          => "HostingOrder",
                            'type'           => "hosting",
                            'id'             => $hosting["id"],
                            'selection'      => $getPrice,
                            'domain'         => $step1["elected"][0]["domain"],
                            'category'       => isset($category_title) ? $category_title : null,
                            'category_route' => isset($category_route) ? $category_route : null,
                        ], false);
                    }
                }


                if (isset($step1["elected"]) && is_array($step1["elected"]) && $step1["elected"]) {
                    foreach ($step1["elected"] as $dom) {
                        $tinfo = $this->getTLD($dom["tld"]);

                        if ($tinfo) {
                            $tdata = [
                                'event'          => isset($dom["tcode"]) && $dom["tcode"] != '' ? "DomainNameTransferRegisterOrder" : "DomainNameRegisterOrder",
                                'type'           => "domain",
                                'id'             => $tinfo["id"],
                                'period'         => "year",
                                'period_time'    => $dom["period"],
                                'category'       => __("website/osteps/category-domain"),
                                'category_route' => $this->CRLink("domain"),
                                'sld'            => $dom["sld"],
                                'tld'            => $dom["tld"],
                                'dns'            => Config::get("options/ns-addresses"),
                            ];
                            if (isset($dom["tcode"]) && $dom["tcode"] != '') $tdata["tcode"] = $dom["tcode"];
                            if (isset($dns) && $dns) $tdata["dns"] = $dns;
                            if (isset($dom["docs"]) && $dom["docs"]) $tdata["docs"] = $dom["docs"];

                            Basket::set(false, $dom["domain"], $tdata, false);
                        }
                    }
                }

                Basket::save();

                $this->delete_step($step1t);
                $this->delete_step($step2t);
                $this->delete_step($data["step_token"]);

                echo Utility::jencode([
                    'status'   => "successful",
                    'redirect' => $this->CRLink("basket"),
                ]);

            }
        }


        private function hosting_post($data = [])
        {

            $this->takeDatas("language");

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'order-steps'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            $hide_domain = isset($data["product"]["options"]["hide_domain"]) && $data["product"]["options"]["hide_domain"];

            if ($data["step"] == 1) { // Step 1 START

                $selection = Filter::init("POST/selection", "numbers");

                if (Validation::isEmpty($selection) || !Validation::isInt($selection))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/osteps/error6"),
                    ]));

                $selection = (int)$selection;

                if (!isset($data["product"]["price"][$selection]))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/osteps/error6"),
                    ]));

                $selection = $data["product"]["price"][$selection];


                Helper::Load("Basket");
                $category_title = $data["product"]["category_title"] ?? '';
                $category_route = $data["product"]["category_route"] ?? '';
                $price = $selection;

                $hosting_data = [
                    'event'          => "HostingOrder",
                    'type'           => "hosting",
                    'id'             => $data["id"],
                    'selection'      => $price,
                    'domain'         => '',
                    'category'       => $category_title ?? null,
                    'category_route' => $category_route ?? null,
                    '_time'          => DateManager::strtotime(),
                ];

                $this->set_step($data["step_token"], ['hosting_data' => $hosting_data,'selection' => $selection, 'status' => "completed"]);

                if ($hide_domain) {

                    $sdata = ['hosting_data' => $hosting_data];

                    $sdata["status"] = "completed";

                    $this->set_step($this->step_token($data["type"], $data["id"], "domain"), $sdata);

                    $getAddons = $this->addons($data["product"]["addons"]);
                    $getRequirements = $this->requirements($data["product"]["requirements"]);

                    if ($getAddons) {
                        $continue_token = $this->step_token($data["type"], $data["id"], "addons");
                        $this->set_step($continue_token, ['status' => "incomplete"]);
                        $redirect = $this->CRLink("order-steps-p", [$data["type"], $data["id"], "addons"]);
                    } elseif ($getRequirements) {
                        $continue_token = $this->step_token($data["type"], $data["id"], "requirements");
                        $this->set_step($continue_token, ['status' => "incomplete"]);
                        $redirect = $this->CRLink("order-steps-p", [$data["type"], $data["id"], "requirements"]);
                    } else {

                        Basket::set(false, $data["product"]["title"], $hosting_data, false);
                        Basket::save();

                        $this->delete_step($this->step_token($data["type"], $data["id"], 1));
                        $this->delete_step($this->step_token($data["type"], $data["id"], "domain"));
                        $redirect = $this->CRLink("basket");
                    }
                } else {
                    $this->set_step($this->step_token($data["type"], $data["id"], "domain"), ['status' => "incomplete"]);
                    $redirect = $this->CRLink("order-steps-p", [$data["type"], $data["id"], "domain"]);
                }

                echo Utility::jencode(['status' => "successful", 'redirect' => $redirect]);

            } // Step 1 END

            if ($data["step"] == "domain") { // Step Domain START
                $lang = Bootstrap::$lang->clang;
                $type = Filter::init("POST/type", "letters");
                $domain = Filter::init("POST/domain", "domain");
                $domain = str_replace("www.", "", $domain);
                $tld = null;
                $parse = Utility::domain_parser($domain);
                $sld = null;

                if ($parse && $parse["host"] != '' && strlen($parse["host"]) >= 2) {
                    $sld = $parse["host"];
                    $tld = $parse["tld"];
                }

                $getFirstTLD = $this->getTLD(null, 0);
                if ($getFirstTLD) $getFirstTLD = $getFirstTLD["name"];
                $tld = $tld == null ? $getFirstTLD : $tld;

                if ($sld == '' || $tld == '')
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => $type == "registrar" ? "#DomainCheck input[name='domain']" : "#hosting_domain",
                        'message' => __("website/osteps/error1"),
                    ]));

                $fdomain = $sld . "." . $tld;

                $subdomain_hosting_detection = false;
                $same_domain_detection = false;

                $main_domain = Utility::getDomain();
                $whois_servers = include STORAGE_DIR . "whois-servers.php";
                if ($whois_servers) {
                    $servers = [];
                    foreach ($whois_servers as $k => $v) {
                        $k_split = explode(",", $k);
                        foreach ($k_split as $k_row) {
                            $servers[$k_row] = $v;
                        }
                    }
                    $servers = array_keys($servers);
                    $parse2 = Utility::domain_parser($main_domain);
                    $main_tld = $parse2["tld"];
                    if (!in_array($main_tld, $servers)) $main_domain = $main_tld;
                }

                $subdomains = $data["product"]["subdomains"];
                if ($subdomains) $subdomains = explode("\n", $subdomains);

                if ($subdomains && $type == "subdomain") {
                    foreach ($subdomains as $sd) {
                        if (stristr($fdomain, $sd)) {
                            $subdomain_hosting_detection = true;
                            break;
                        }
                    }
                } else {
                    if (stristr($fdomain, '.' . $main_domain)) $subdomain_hosting_detection = true;
                }

                if ($type == "subdomain" && !$subdomain_hosting_detection)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/osteps/error12"),
                    ]));


                if ($main_domain == $fdomain) $same_domain_detection = true;

                if (Config::get("options/allow-sub-hosting") || $type == "subdomain")
                    $subdomain_hosting_detection = false;


                if (Validation::check_prohibited($fdomain, ['domain', 'word']) || $subdomain_hosting_detection || $same_domain_detection)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account/prohibited-alert"),
                    ]));

                $checkHosting = $this->model->check_hosting($fdomain);

                Helper::Load("Basket");
                $items = Basket::listing();
                foreach($items AS $item){
                    $itemOpt = $item["options"];
                    if($itemOpt["type"] == $data["product"]["type"] && $itemOpt["id"] == $data["product"]["id"])
                    {
                        if($itemOpt["domain"] == $fdomain) $checkHosting = true;
                    }
                }

                if ($checkHosting)
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => $type == "registrar" ? "#DomainCheck input[name='domain']" : "#hosting_domain",
                        'message' => __("website/osteps/error10"),
                    ]));


                if ($type == "registrar" || $type == "none" || $type == "subdomain") {
                    Helper::Load(["Basket"]);

                    $step1t = $this->step_token($data["type"], $data["id"], 1);
                    $step1 = $this->get_step($step1t);

                    if (!$step1) die("Step 1 is empty");

                    $category_title = isset($data["product"]["category_title"]) ? $data["product"]["category_title"] : '';
                    $category_route = isset($data["product"]["category_route"]) ? $data["product"]["category_route"] : '';
                    $price = $step1["selection"];

                    if (!$price) die("Price not found!");

                    $sdata = [
                        'hosting_data' => [
                            'event'          => "HostingOrder",
                            'type'           => "hosting",
                            'id'             => $data["id"],
                            'selection'      => $price,
                            'domain'         => $fdomain,
                            'category'       => isset($category_title) ? $category_title : null,
                            'category_route' => isset($category_route) ? $category_route : null,
                        ]
                    ];

                    if ($type == "registrar") {
                        $tinfo = $this->getTLD($tld);
                        if ($tinfo) {

                            if (Basket::count()) {
                                foreach (Basket::listing() as $item) {
                                    $options = $item["options"];
                                    if ($options["type"] == "domain" && $item["name"] == $fdomain)
                                        die(Utility::jencode([
                                            'status'  => "error",
                                            'for'     => "input[name='domain']",
                                            'message' => __("website/domain/error11"),
                                        ]));
                                }
                            }

                            if ($data["product"]["module"] == "none" || $data["product"]["module"] == '') {
                                $dns = [];
                                if (isset($data["product"]["options"]["dns"]["ns1"]) && $data["product"]["options"]["dns"]["ns1"])
                                    $dns = $data["product"]["options"]["dns"];

                                if (!$dns) $dns = Config::get("options/ns-addresses");
                            } elseif (isset($data["product"]["options"]["server_id"])) {
                                $server = Products::get_server($data["product"]["options"]["server_id"]);
                                $dns = [];
                                if ($server["ns1"] != '') $dns["ns1"] = $server["ns1"];
                                if ($server["ns2"] != '') $dns["ns2"] = $server["ns2"];
                                if ($server["ns3"] != '') $dns["ns3"] = $server["ns3"];
                                if ($server["ns4"] != '') $dns["ns4"] = $server["ns4"];
                            } elseif (isset($data["product"]["module_data"]["server_id"])) {
                                $server = Products::get_server($data["product"]["module_data"]["server_id"]);
                                $dns = [];
                                if ($server["ns1"] != '') $dns["ns1"] = $server["ns1"];
                                if ($server["ns2"] != '') $dns["ns2"] = $server["ns2"];
                                if ($server["ns3"] != '') $dns["ns3"] = $server["ns3"];
                                if ($server["ns4"] != '') $dns["ns4"] = $server["ns4"];
                            }
                            if (!isset($dns) || !$dns) $dns = Config::get("options/ns-addresses");


                            $domain_unique = Basket::set(false, $fdomain, [
                                'event'          => "DomainNameRegisterOrder",
                                'type'           => "domain",
                                'id'             => $tinfo["id"],
                                'period'         => "year",
                                'period_time'    => 1,
                                'category'       => __("website/osteps/category-domain"),
                                'category_route' => $this->CRLink("domain"),
                                'sld'            => $sld,
                                'tld'            => $tld,
                                'dns'            => $dns,
                            ], false);
                            $sdata["domain_unique"] = $domain_unique;

                            Basket::save();
                        }
                    }

                    $sdata["status"] = "completed";

                    $this->set_step($data["step_token"], $sdata);

                    $getAddons = $this->addons($data["product"]["addons"]);
                    $getRequirements = $this->requirements($data["product"]["requirements"]);


                    if ($getAddons) {
                        $continue_token = $this->step_token($data["type"], $data["id"], "addons");
                        $this->set_step($continue_token, ['status' => "incomplete"]);
                        $redirect = $this->CRLink("order-steps-p", [$data["type"], $data["id"], "addons"]);
                    }
                    elseif ($getRequirements) {
                        $continue_token = $this->step_token($data["type"], $data["id"], "requirements");
                        $this->set_step($continue_token, ['status' => "incomplete"]);
                        $redirect = $this->CRLink("order-steps-p", [$data["type"], $data["id"], "requirements"]);
                    }
                    else {
                        Basket::set(false,$data["product"]["title"],$sdata["hosting_data"]);
                        Basket::save();
                        $this->delete_step($step1t);
                        $this->delete_step($data["step_token"]);
                        $redirect = $this->CRLink("basket");
                    }

                    die(Utility::jencode([
                        'status'   => "successful",
                        'redirect' => $redirect,
                    ]));
                } else die("Error #1");
            } // Step Domain END

            if ($data["step"] == "addons") { // Addons START

                $step1t = $this->step_token($data["type"], $data["id"], 1);
                $step2t = $this->step_token($data["type"], $data["id"], "domain");
                $step3t = $this->step_token($data["type"], $data["id"], "addons");
                $step1 = $this->get_step($step1t);
                $step2 = $this->get_step($step2t);
                $step3 = $this->get_step($step3t);

                $getAddons = $this->addons($data["product"]["addons"], isset($step1["selection"]) ? $step1["selection"] : false);
                $addons = Filter::POST("addons");
                $addons_values = Filter::POST("addons_values");
                $as_selected = [];
                $as_selected_v = [];

                if ($getAddons) {
                    foreach ($getAddons as $addon) {
                        if (isset($addons[$addon["id"]]) && Validation::isInt($addons[$addon["id"]])) {
                            $options = $addon["options"];
                            foreach ($options as $k => $v) {
                                if ($v["id"] == $addons[$addon["id"]]) {
                                    $as_selected[$addon["id"]] = $v["id"];
                                    if ($addon["type"] == "quantity") {
                                        if (isset($addons_values[$addon["id"]])) {
                                            $addon_quantity = (int)Filter::numbers($addons_values[$addon["id"]]);
                                            if ($addon_quantity) $as_selected_v[$addon["id"]] = $addon_quantity;
                                        }
                                    }
                                }
                            }
                        }
                        if (isset($addon["properties"]["compulsory"]) && $addon["properties"]["compulsory"] && !isset($as_selected[$addon["id"]]))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "*[name='addons[" . $addon["id"] . "]']",
                                'message' => __("website/osteps/addon-required", ['{name}' => $addon["name"]]),
                            ]));
                        if ($addon["type"] == "quantity" && isset($addon["properties"]["compulsory"]) && $addon["properties"]["compulsory"] && !isset($as_selected_v[$addon["id"]]))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "*[name='addons[" . $addon["id"] . "]']",
                                'message' => __("website/osteps/addon-required", ['{name}' => $addon["name"]]),
                            ]));
                    }
                }

                Helper::Load("Basket");
                $item1 = $step2["hosting_data"];
                if (!$item1)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/osteps/error3"),
                    ]));

                $options = $item1;
                if ($as_selected) $options["addons"] = $as_selected;
                if ($as_selected_v) $options["addons_values"] = $as_selected_v;

                $requirements = [];
                foreach ($getAddons as $addon) {
                    if (isset($as_selected[$addon["id"]]) && strlen($addon["requirements"]) > 0) {
                        $rqs = explode(",", $addon["requirements"]);
                        foreach ($rqs as $rq) if (!in_array($rq, $requirements)) $requirements[] = $rq;
                    }
                }
                if (!$requirements && $data["product"]["requirements"])
                    $requirements = explode(",", $data["product"]["requirements"]);


                if (isset($requirements) && $requirements)
                    foreach ($requirements as $rk => $rq)
                        if (!is_array($rq))
                            if (!Products::requirement($rq)) unset($requirements[$rk]);

                $step2["hosting_data"] = $options;

                if ($requirements) {
                    $this->set_step($step2t, $step2);
                    $this->set_step($step3t, [
                        'status'       => "completed",
                        'requirements' => $requirements,
                    ]);

                    $this->set_step($this->step_token($data["type"], $data["id"], "requirements"), ['status' => "incomplete"]);

                    echo Utility::jencode([
                        'status'   => "successful",
                        'redirect' => $this->CRLink("order-steps-p", [$data["type"], $data["id"], "requirements"]),
                    ]);

                } else {
                    Basket::set(false,$data["product"]["title"],$step2["hosting_data"]);
                    Basket::save();

                    $this->delete_step($step1t);
                    $this->delete_step($step2t);
                    $this->delete_step($step3t);

                    echo Utility::jencode([
                        'status'   => "successful",
                        'redirect' => $this->CRLink("basket"),
                    ]);
                }

            } // Addons END

            if ($data["step"] == "requirements") { // Requirements START

                $step1t = $this->step_token($data["type"], $data["id"], 1);
                $step2t = $this->step_token($data["type"], $data["id"], "domain");
                $step3t = $this->step_token($data["type"], $data["id"], "addons");
                $step4t = $this->step_token($data["type"], $data["id"], "requirements");
                $step1 = $this->get_step($step1t);
                $step2 = $this->get_step($step2t);
                $step3 = $this->get_step($step3t);
                $step4 = $this->get_step($step4t);

                if (!$step1) die("Not Found Step 1 Data");
                if (!$step2) die("Not Found Step 2 Data");

                $getRequirements = $this->requirements($data["product"]["requirements"]);
                $requirements = Filter::POST("requirements");
                $rs_selected = [];

                if ($step3 && isset($step3["requirements"]))
                    $getRequirements = $this->requirements(implode(",", $step3["requirements"]));


                if ($getRequirements) {
                    foreach ($getRequirements as $requirement) {
                        $values = null;
                        $options = $requirement["options"];
                        $properties = $requirement["properties"];
                        $is_input = in_array($requirement["type"], ["input", "textarea"]);
                        $is_opt = in_array($requirement["type"], ["select", "radio", "checkbox"]);
                        $is_plural = $requirement["type"] == "checkbox";
                        $value = null;
                        if (isset($requirements[$requirement["id"]])) $value = $requirements[$requirement["id"]];
                        if ($is_opt && !is_array($value)) $value = Utility::substr($value, 0, 200);

                        $opt_ids = [];
                        if ($options) foreach ($options as $option) $opt_ids[] = $option["id"];

                        if ($requirement["type"] == "file") {
                            $files = Filter::FILES("requirement-" . $requirement["id"]);
                            if (isset($properties["compulsory"]) && $properties["compulsory"])
                                if (!$files)
                                    die(Utility::jencode([
                                        'status'  => "error",
                                        'for'     => "input[name='requirement-" . $requirement["id"] . "[]']",
                                        'message' => __("website/osteps/field-required", ['{name}' => $requirement["name"]]),
                                    ]));
                            if ($files && !DEMO_MODE) {
                                $extensions = isset($options["allowed-extensions"]) ? $options["allowed-extensions"] : Config::get("options/product-fields-extensions");
                                $max_filesize = isset($options["max-file-size"]) ? $options["max-file-size"] : Config::get("options/product-fields-max-file-size");
                                Helper::Load("Uploads");
                                $upload = Helper::get("Uploads");
                                $upload->init($files, [
                                    'date'          => false,
                                    'multiple'      => true,
                                    'max-file-size' => $max_filesize,
                                    'folder'        => ROOT_DIR . "temp" . DS,
                                    'allowed-ext'   => $extensions,
                                    'file-name'     => "random",
                                    'width'         => Config::get("pictures/product-requirements/sizing/width"),
                                    'height'        => Config::get("pictures/product-requirements/sizing/height"),
                                ]);
                                if (!$upload->processed())
                                    die(Utility::jencode([
                                        'status'  => "error",
                                        'for'     => "input[name='requirement-" . $requirement["id"] . "[]']",
                                        'message' => __("website/osteps/failed-field-upload", ['{error}' => $upload->error]),
                                    ]));
                                if ($upload->operands) {
                                    $values = Utility::jencode($upload->operands);
                                }
                            }
                        } elseif (isset($properties["compulsory"]) && $properties["compulsory"]) {
                            $compulsory = false;

                            if (!isset($requirements[$requirement["id"]])) $compulsory = true;
                            elseif (Validation::isEmpty($value)) $compulsory = true;
                            elseif ($is_input && Utility::strlen($value) == 0) $compulsory = true;

                            elseif ($is_opt && $is_plural && !is_array($value)) $compulsory = true;

                            elseif ($is_opt && $is_plural && $value)
                                foreach ($value as $val) if (!in_array($val, $opt_ids)) $compulsory = true;

                                elseif ($is_opt && !$is_plural && !in_array($value, $opt_ids)) $compulsory = true;


                            if ($compulsory)
                                die(Utility::jencode([
                                    'status'  => "error",
                                    'for'     => "*[name='requirements[" . $requirement["id"] . "]']",
                                    'message' => __("website/osteps/field-required", ['{name}' => $requirement["name"]]),
                                ]));
                        }


                        $checkingRequirement = Hook::run("checkingRequirementToOrderSteps", [
                            'product'     => $data["product"],
                            'step_data'   => $step4,
                            'requirement' => $requirement,
                            'value'       => $value,
                        ]);

                        if ($checkingRequirement) {
                            foreach ($checkingRequirement as $row) {
                                if (isset($row["status"]) && $row["status"] == "error")
                                    die(Utility::jencode([
                                        'status'  => "error",
                                        'for'     => "*[name='requirements[" . $requirement["id"] . "]']",
                                        'message' => $row["message"],
                                    ]));

                            }
                        }


                        if ($requirement["type"] == "input" || $requirement["type"] == "password") $values = Utility::short_text(Filter::html_clear($value), 0, 500);
                        elseif ($requirement["type"] == "textarea") $values = Utility::short_text($value, 0, 5000);
                        elseif ($is_opt) {
                            $values = [];
                            if (isset($requirements[$requirement["id"]])) {
                                if (!is_array($value)) $value = [$value];
                                foreach ($options as $opt) {
                                    if (in_array($opt["id"], $value)) {
                                        $values[] = $opt["id"];
                                    }
                                }
                            }
                        }

                        if ($values) {
                            if (is_array($values)) $values = implode(",", $values);
                            $rs_selected[$requirement["id"]] = $values;
                        }
                    }
                }

                Helper::Load("Basket");

                $item1 = $step2["hosting_data"];
                if (!$item1)
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/osteps/error3"),
                    ]));

                if ($rs_selected) $item1["requirements"] = $rs_selected;
                Basket::set(false, $data["product"]["title"], $item1);
                Basket::save();

                $this->delete_step($step1t);
                $this->delete_step($step2t);
                $this->delete_step($step3t);
                $this->delete_step($step4t);

                echo Utility::jencode(['status' => "successful", 'redirect' => $this->CRLink("basket")]);
            } // Requirements END

        }


        private function server_post($data=[])
        {

            $this->takeDatas("language");

            if(!defined("DISABLE_CSRF")){
                $token = Filter::init("POST/token","hclear");
                if(!$token || !Validation::verify_csrf_token($token,'order-steps'))
                    die(Utility::jencode([
                        'status' => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }


            if(!$data["product"]["haveStock"]) die();

            $getRequirements    = $this->requirements($data["product"]["requirements"]);

            $step1t         = $this->step_token($data["type"],$data["id"],1);
            $step1          = $this->get_step($step1t);

            $getAddons          = $this->addons($data["product"]["addons"],isset($step1["selection"]) ? $step1["selection"] : false);

            if($data["step"] == 1){ // Step 1 START

                $selection  = Filter::init("POST/selection","numbers");

                if(Validation::isEmpty($selection) || !Validation::isInt($selection))
                    die(Utility::jencode([
                        'status' => "error",
                        'message' => __("website/osteps/error6"),
                    ]));

                $selection = (int)$selection;

                if(!isset($data["product"]["price"][$selection]))
                    die(Utility::jencode([
                        'status' => "error",
                        'message' => __("website/osteps/error6"),
                    ]));

                $selection = $data["product"]["price"][$selection];

                $this->set_step($data["step_token"],['selection' => $selection,'status' => "completed"]);
                /*
                if($getAddons || $getRequirements || Config::get("options/hidsein")){
                    if($getAddons || Config::get("options/hidsein")) {
                        $where = 'configuration';
                        $this->set_step($this->step_token($data["type"],$data["id"],$where),['status' => "incomplete"]);
                    }
                    elseif($getRequirements)
                    {
                        $where = 'requirements';

                        $category_title     = isset($data["product"]["category_title"]) ? $data["product"]["category_title"] : '';
                        $category_route     = isset($data["product"]["category_route"]) ? $data["product"]["category_route"] : '';
                        $bdata              = [
                            'event' => "ServerOrder",
                            'type'  => $data["type"],
                            'id'    => $data["id"],
                            'selection' => $selection,
                            'category' => isset($category_title) ? $category_title : NULL,
                            'category_route' => isset($category_route) ? $category_route : NULL
                        ];
                        $bdata["_time"] = DateManager::strtotime();

                        Helper::Load("Basket");

                        $unique = Basket::set(false,$data["product"]["title"],$bdata,false);
                        Basket::save();

                        $this->set_step($this->step_token($data["type"],$data["id"],"configuration"),['status' => "completed"]);
                        $this->set_step($this->step_token($data["type"],$data["id"],"requirements"),[
                            'status' => "incomplete",
                            'server_unique' => $unique,
                        ]);
                    }
                    else
                        $where = "none";
                    $redirect = $this->CRLink("order-steps-p",[$data["type"],$data["id"],$where]);
                }
                */
                if($getAddons || $getRequirements || Config::get("options/hidsein"))
                {
                    $where = 'configuration';
                    $this->set_step($this->step_token($data["type"],$data["id"],$where),['status' => "incomplete"]);
                    $redirect = $this->CRLink("order-steps-p",[$data["type"],$data["id"],$where]);
                }
                else{

                    $category_title     = isset($data["product"]["category_title"]) ? $data["product"]["category_title"] : '';
                    $category_route     = isset($data["product"]["category_route"]) ? $data["product"]["category_route"] : '';
                    $bdata              = [
                        'event' => "ServerOrder",
                        'type'  => $data["type"],
                        'id'    => $data["id"],
                        'selection' => $selection,
                        'category' => isset($category_title) ? $category_title : NULL,
                        'category_route' => isset($category_route) ? $category_route : NULL
                    ];
                    $bdata["_time"] = DateManager::strtotime();

                    Helper::Load("Basket");

                    $unique = Basket::set(false,$data["product"]["title"],$bdata,false);
                    Basket::save();
                    $this->delete_step($step1t);
                    $redirect = $this->CRLink("basket");
                }

                echo Utility::jencode(['status' => "successful",'redirect' => $redirect]);

            } // Step 1 END
            if($data["step"] == "configuration"){ // Step Configuration START
                $OrderSummary   = Filter::init("POST/OrderSummary","numbers");
                $step1t         = $this->step_token($data["type"],$data["id"],1);
                $step1          = $this->get_step($step1t);


                if($OrderSummary){
                    $lcid           = Config::get("general/currency");
                    $result         = ['status' => "successful"];
                    $total_amount   = 0;

                    Helper::Load("Money");

                    $addons         = Filter::POST("addons");
                    $addons_values  = Filter::POST("addons_values");
                    if($addons && is_array($addons)){
                        foreach($getAddons AS $addon){
                            if(isset($addons[$addon["id"]]) && Validation::isInt($addons[$addon["id"]])){
                                $options    = $addon["options"];
                                foreach($options AS $k=>$v){
                                    if($v["id"] == $addons[$addon["id"]]){
                                        $amount  = Money::exChange($v["amount"],$v["cid"],$lcid);
                                        if($addon["type"] == "quantity"){
                                            $addon_val = 0;
                                            if(isset($addons_values[$addon["id"]])){
                                                $addon_val = $addons_values[$addon["id"]];
                                                $addon_val = (int) Filter::numbers($addon_val);
                                            }
                                            $v["name"] = $addon_val."x";
                                            $amount =  ($amount * $addon_val);
                                            if($addon_val < 1) continue;
                                        }

                                        $total_amount += $amount;
                                        $result["data"][] = [
                                            'name' => $addon["name"]." - ".$v["name"],
                                            'amount' => $amount ? Money::formatter_symbol($amount,$lcid,!$addon["override_usrcurrency"]) : ___("needs/free-amount"),
                                        ];

                                    }
                                }
                            }
                        }
                    }

                    $pprice         = $step1["selection"];
                    $total_amount   += Money::exChange($pprice["amount"],$pprice["cid"],$lcid);

                    $result["total_amount"] = Money::formatter_symbol($total_amount,$lcid,true);

                    die(Utility::jencode($result));
                }

                $hostname       = Utility::substr(Filter::init("POST/hostname","letters_numbers",".\/"),0,255);
                $ns1            = Utility::substr(Filter::init("POST/ns1","domain"),0,255);
                $ns2            = Utility::substr(Filter::init("POST/ns2","domain"),0,255);
                $password       = Utility::substr(Filter::init("POST/password","password"),0,255);



                $checkHook = Hook::run("checkServerConfigurationAtOrderStep",[
                    'product'       => $data["product"],
                    'step_data'     => $step1,
                    'data'          => [
                        'hostname' => $hostname,
                        'ns1'      => $ns1,
                        'ns2'      => $ns2,
                        'password' => $password,
                    ],
                ]);

                if($checkHook){
                    foreach($checkHook AS $row){
                        if(isset($row["status"]) && $row["status"] == "error")
                            die(Utility::jencode([
                                'status' => "error",
                                'message' => $row["message"],
                            ]));

                    }
                }


                $addons         = Filter::POST("addons");
                $addons_values  = Filter::POST("addons_values");
                $as_selected    = [];
                $as_selected_v  = [];

                if($getAddons){
                    foreach($getAddons AS $addon){
                        if(isset($addons[$addon["id"]]) && Validation::isInt($addons[$addon["id"]])){
                            $options    = $addon["options"];
                            foreach($options AS $k=>$v){
                                if($v["id"] == $addons[$addon["id"]]){
                                    $as_selected[$addon["id"]] = $v["id"];
                                    if($addon["type"] == "quantity"){
                                        if(isset($addons_values[$addon["id"]])){
                                            $addon_quantity = (int) Filter::numbers($addons_values[$addon["id"]]);
                                            if($addon_quantity) $as_selected_v[$addon["id"]] = $addon_quantity;
                                        }
                                    }
                                }
                            }
                        }
                        if(isset($addon["properties"]["compulsory"]) && $addon["properties"]["compulsory"] && !isset($as_selected[$addon["id"]]))
                            die(Utility::jencode([
                                'status' => "error",
                                'for' => "*[name='addons[".$addon["id"]."]']",
                                'message' => __("website/osteps/addon-required",['{name}' => $addon["name"]]),
                            ]));
                        if($addon["type"] == "quantity" && isset($addon["properties"]["compulsory"]) && $addon["properties"]["compulsory"] && !isset($as_selected_v[$addon["id"]]))
                            die(Utility::jencode([
                                'status' => "error",
                                'for' => "*[name='addons[".$addon["id"]."]']",
                                'message' => __("website/osteps/addon-required",['{name}' => $addon["name"]]),
                            ]));
                    }
                }

                /*
                $requirements       = [];
                foreach($getAddons AS $addon){
                    if(isset($as_selected[$addon["id"]]) && strlen($addon["requirements"])>0){
                        $rqs = explode(",",$addon["requirements"]);
                        foreach($rqs AS $rq) if(!in_array($rq,$requirements)) $requirements[] = $rq;
                    }
                }
                if(!$requirements && $getRequirements)
                    foreach($getRequirements AS $getRequirement) $requirements[] = $getRequirement["id"];
                */

                $category_title     = isset($data["product"]["category_title"]) ? $data["product"]["category_title"] : '';
                $category_route     = isset($data["product"]["category_route"]) ? $data["product"]["category_route"] : '';
                $bdata              = [
                    'event' => "ServerOrder",
                    'type'  => $data["type"],
                    'id'    => $data["id"],
                    'selection' => $step1["selection"],
                    'category' => isset($category_title) ? $category_title : NULL,
                    'category_route' => isset($category_route) ? $category_route : NULL
                ];

                if($hostname) $bdata["hostname"] = $hostname;
                if($ns1) $bdata["ns1"] = $ns1;
                if($ns2) $bdata["ns2"] = $ns2;
                if($password) $bdata["password"] = $password;

                if($as_selected) $bdata["addons"]               = $as_selected;
                if($as_selected_v) $bdata["addons_values"]      = $as_selected_v;


                if(Validation::isEmpty($ns1) && Validation::isEmpty($ns2) && Validation::isEmpty($password) && Validation::isEmpty($hostname)) $bdata["_time"] = DateManager::strtotime();


                // REQUIREMENT DATA START
                $requirements       = Filter::POST("requirements");
                $rs_selected        = [];

                if($getRequirements){
                    foreach($getRequirements AS $requirement){
                        $values     = NULL;
                        $options    = $requirement["options"];
                        $properties = $requirement["properties"];
                        $is_input   = in_array($requirement["type"],["input","textarea"]);
                        $is_opt     = in_array($requirement["type"],["select","radio","checkbox"]);
                        $is_plural  = $requirement["type"] == "checkbox";
                        $value      = NULL;
                        if(isset($requirements[$requirement["id"]])) $value = $requirements[$requirement["id"]];
                        if($is_opt && !is_array($value)) $value = Utility::substr($value,0,200);

                        $opt_ids = [];
                        if($options) foreach($options AS $option) $opt_ids[] = $option["id"];

                        if($requirement["type"] == "file"){
                            $files  = Filter::FILES("requirement-".$requirement["id"]);
                            if(isset($properties["compulsory"]) && $properties["compulsory"])
                                if(!$files)
                                    die(Utility::jencode([
                                        'status' => "error",
                                        'for' => "input[name='requirement-".$requirement["id"]."[]']",
                                        'message' => __("website/osteps/field-required",['{name}' => $requirement["name"]]),
                                    ]));
                            if($files && !DEMO_MODE){
                                $extensions = isset($options["allowed-extensions"]) ? $options["allowed-extensions"] : Config::get("options/product-fields-extensions");
                                $max_filesize = isset($options["max-file-size"]) ? $options["max-file-size"] : Config::get("options/product-fields-max-file-size");
                                Helper::Load("Uploads");
                                $upload = Helper::get("Uploads");
                                $upload->init($files,[
                                    'date' => false,
                                    'multiple' => true,
                                    'max-file-size' => $max_filesize,
                                    'folder' => ROOT_DIR."temp".DS,
                                    'allowed-ext' => $extensions,
                                    'file-name' => "random",
                                    'width'  => Config::get("pictures/product-requirements/sizing/width"),
                                    'height' => Config::get("pictures/product-requirements/sizing/height"),
                                ]);
                                if(!$upload->processed())
                                    die(Utility::jencode([
                                        'status' => "error",
                                        'for' => "input[name='requirement-".$requirement["id"]."[]']",
                                        'message' => __("website/osteps/failed-field-upload",['{error}' => $upload->error])
                                    ]));
                                if($upload->operands){
                                    $values = Utility::jencode($upload->operands);
                                }
                            }
                        }
                        elseif(isset($properties["compulsory"]) && $properties["compulsory"]){
                            $compulsory = false;

                            if(!isset($requirements[$requirement["id"]])) $compulsory = true;
                            elseif(Validation::isEmpty($value)) $compulsory = true;
                            elseif($is_input && Utility::strlen($value) == 0) $compulsory = true;

                            elseif($is_opt && $is_plural && !is_array($value)) $compulsory = true;

                            elseif($is_opt && $is_plural && $value)
                                foreach($value AS $val) if(!in_array($val,$opt_ids)) $compulsory = true;

                                elseif($is_opt && !$is_plural && !in_array($value,$opt_ids)) $compulsory = true;


                            if($compulsory)
                                die(Utility::jencode([
                                    'status' => "error",
                                    'for' => "*[name='requirements[".$requirement["id"]."]']",
                                    'message' => __("website/osteps/field-required",['{name}' => $requirement["name"]]),
                                ]));
                        }

                        if($requirement["type"] == "input" || $requirement["type"] == "password") $values = Utility::short_text(Filter::html_clear($value),0,500);
                        elseif($requirement["type"] == "textarea") $values = Utility::short_text($value,0,5000);
                        elseif($is_opt){
                            $values     = [];
                            if(isset($requirements[$requirement["id"]])){
                                if(!is_array($value)) $value = [$value];
                                foreach($options AS $opt){
                                    if(in_array($opt["id"],$value)){
                                        $values[] = $opt["id"];
                                    }
                                }
                            }
                        }
                        if($values){
                            if(is_array($values)) $values = implode(",",$values);
                            $rs_selected[$requirement["id"]] = $values;
                        }
                    }
                }
                if($rs_selected) $bdata["requirements"] = $rs_selected;
                // Requirement DATA END


                Helper::Load("Basket");

                $unique = Basket::set(false,$data["product"]["title"],$bdata,false);
                Basket::save();

                /*
                if(isset($requirements) && $requirements)
                    foreach($requirements AS $rk => $rq)
                        if(!is_array($rq))
                            if(!Products::requirement($rq)) unset($requirements[$rk]);


                if(isset($requirements) && $requirements){
                    $this->set_step($data["step_token"],['status' => "completed"]);
                    $this->set_step($this->step_token($data["type"],$data["id"],"requirements"),[
                        'status' => "incomplete",
                        'server_unique' => $unique,
                        'requirements' => $requirements,
                    ]);
                    $redirect = $this->CRLink("order-steps-p",[$data["type"],$data["id"],"requirements"]);
                }
                else{*/
                $this->delete_step($step1t);
                $this->delete_step($data["step_token"]);
                $redirect = $this->CRLink("basket");
                //}
                echo Utility::jencode(['status' => "successful",'redirect' => $redirect]);

            } // Step Configuration END
            if($data["step"] == "requirements"){ // Requirements START

                $step1t             = $this->step_token($data["type"],$data["id"],1);
                $step2t             = $this->step_token($data["type"],$data["id"],"configuration");
                $step3t             = $this->step_token($data["type"],$data["id"],"requirements");
                $step1              = $this->get_step($step1t);
                $step2              = $this->get_step($step2t);
                $step3              = $this->get_step($step3t);

                if(!$step1) $step1 = [];
                if(!$step2) $step2 = [];
                if(!$step3) $step3 = [];


                if(!$step1) die("Not Found Step 1 Data");
                if(!$step2) die("Not Found Step 2 Data");

                $getRequirements    = $this->requirements($data["product"]["requirements"]);
                $requirements       = Filter::POST("requirements");
                $rs_selected        = [];

                if($step3 && isset($step3["requirements"]))
                    $getRequirements = $this->requirements(implode(",",$step3["requirements"]));


                if($getRequirements){
                    foreach($getRequirements AS $requirement){
                        $values     = NULL;
                        $options    = $requirement["options"];
                        $properties = $requirement["properties"];
                        $is_input   = in_array($requirement["type"],["input","textarea"]);
                        $is_opt     = in_array($requirement["type"],["select","radio","checkbox"]);
                        $is_plural  = $requirement["type"] == "checkbox";
                        $value      = NULL;
                        if(isset($requirements[$requirement["id"]])) $value = $requirements[$requirement["id"]];
                        if($is_opt && !is_array($value)) $value = Utility::substr($value,0,200);

                        $opt_ids = [];
                        if($options) foreach($options AS $option) $opt_ids[] = $option["id"];

                        if($requirement["type"] == "file"){
                            $files  = Filter::FILES("requirement-".$requirement["id"]);
                            if(isset($properties["compulsory"]) && $properties["compulsory"])
                                if(!$files)
                                    die(Utility::jencode([
                                        'status' => "error",
                                        'for' => "input[name='requirement-".$requirement["id"]."[]']",
                                        'message' => __("website/osteps/field-required",['{name}' => $requirement["name"]]),
                                    ]));
                            if($files && !DEMO_MODE){
                                $extensions = isset($options["allowed-extensions"]) ? $options["allowed-extensions"] : Config::get("options/product-fields-extensions");
                                $max_filesize = isset($options["max-file-size"]) ? $options["max-file-size"] : Config::get("options/product-fields-max-file-size");
                                Helper::Load("Uploads");
                                $upload = Helper::get("Uploads");
                                $upload->init($files,[
                                    'date' => false,
                                    'multiple' => true,
                                    'max-file-size' => $max_filesize,
                                    'folder' => ROOT_DIR."temp".DS,
                                    'allowed-ext' => $extensions,
                                    'file-name' => "random",
                                    'width'  => Config::get("pictures/product-requirements/sizing/width"),
                                    'height' => Config::get("pictures/product-requirements/sizing/height"),
                                ]);
                                if(!$upload->processed())
                                    die(Utility::jencode([
                                        'status' => "error",
                                        'for' => "input[name='requirement-".$requirement["id"]."[]']",
                                        'message' => __("website/osteps/failed-field-upload",['{error}' => $upload->error])
                                    ]));
                                if($upload->operands){
                                    $values = Utility::jencode($upload->operands);
                                }
                            }
                        }
                        elseif(isset($properties["compulsory"]) && $properties["compulsory"]){
                            $compulsory = false;

                            if(!isset($requirements[$requirement["id"]])) $compulsory = true;
                            elseif(Validation::isEmpty($value)) $compulsory = true;
                            elseif($is_input && Utility::strlen($value) == 0) $compulsory = true;

                            elseif($is_opt && $is_plural && !is_array($value)) $compulsory = true;

                            elseif($is_opt && $is_plural && $value)
                                foreach($value AS $val) if(!in_array($val,$opt_ids)) $compulsory = true;

                                elseif($is_opt && !$is_plural && !in_array($value,$opt_ids)) $compulsory = true;


                            if($compulsory)
                                die(Utility::jencode([
                                    'status' => "error",
                                    'for' => "*[name='requirements[".$requirement["id"]."]']",
                                    'message' => __("website/osteps/field-required",['{name}' => $requirement["name"]]),
                                ]));
                        }


                        $checkingRequirement = Hook::run("checkingRequirementToOrderSteps",[
                            'product'       => $data["product"],
                            'step_data'     => array_merge($step1,$step2,$step3),
                            'requirement'   => $requirement,
                            'value'         => $value,
                        ]);

                        if($checkingRequirement){
                            foreach($checkingRequirement AS $row){
                                if(isset($row["status"]) && $row["status"] == "error")
                                    die(Utility::jencode([
                                        'status' => "error",
                                        'for' => "*[name='requirements[".$requirement["id"]."]']",
                                        'message' => $row["message"],
                                    ]));

                            }
                        }


                        if($requirement["type"] == "input" || $requirement["type"] == "password") $values = Utility::short_text(Filter::html_clear($value),0,500);
                        elseif($requirement["type"] == "textarea") $values = Utility::short_text($value,0,5000);
                        elseif($is_opt){
                            $values     = [];
                            if(isset($requirements[$requirement["id"]])){
                                if(!is_array($value)) $value = [$value];
                                foreach($options AS $opt){
                                    if(in_array($opt["id"],$value)){
                                        $values[] = $opt["id"];
                                    }
                                }
                            }
                        }

                        if($values){
                            if(is_array($values)) $values = implode(",",$values);
                            $rs_selected[$requirement["id"]] = $values;
                        }
                    }
                }

                Helper::Load("Basket");

                $item1      = Basket::get($step3["server_unique"]);
                if(!$item1)
                    die(Utility::jencode([
                        'status' => "error",
                        'message' => __("website/osteps/error3"),
                    ]));

                $options            = $item1["options"];
                if($rs_selected) $options["requirements"] = $rs_selected;
                Basket::set($step3["server_unique"],$data["product"]["title"],$options);
                Basket::save();

                $this->delete_step($step1t);
                $this->delete_step($step2t);
                $this->delete_step($step3t);

                echo Utility::jencode(['status' => "successful",'redirect' => $this->CRLink("basket")]);
            } // Requirements END

        }


        private function special_post($data = [])
        {

            $this->takeDatas("language");

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'order-steps'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }

            if (!$data["product"]["haveStock"]) die();

            $show_domain = isset($data["product"]["options"]["show_domain"]) ? $data["product"]["options"]["show_domain"] : false;

            $getRequirements = [];
            $step1_data = $this->get_step($this->step_token($this->type, $this->id, 1));
            $step2_data = $this->get_step($this->step_token($this->type, $this->id, "domain"));
            $step3_data = $this->get_step($this->step_token($this->type, $this->id, "requirements"));
            $step4_data = $this->get_step($this->step_token($this->type, $this->id, "addons"));
            $step_data = array_merge($step1_data, $step2_data, $step3_data, $step4_data);


            $bring_hook_requirements = Hook::run("addRequirementToOrderSteps", $data["product"], $step_data);
            if ($bring_hook_requirements)
                foreach ($bring_hook_requirements as $rows) if ($rows) foreach ($rows as $row) $getRequirements[] = $row;


            $get_requirements = $this->requirements($data["product"]["requirements"]);
            if ($get_requirements) foreach ($get_requirements as $row) $getRequirements[] = $row;

            $getAddons = $this->addons($data["product"]["addons"], isset($step_data["selection"]) ? $step_data["selection"] : false);


            if ($data["step"] == 1) { // Step 1 START

                $selection = Filter::init("POST/selection", "numbers");

                if (Validation::isEmpty($selection) || !Validation::isInt($selection))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/osteps/error6"),
                    ]));

                $selection = (int)$selection;

                if (!isset($data["product"]["price"][$selection]))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/osteps/error6"),
                    ]));

                $selection = $data["product"]["price"][$selection];

                if ($show_domain || $getAddons || $getRequirements) {

                    if ($show_domain)
                        $where = "domain";
                    elseif ($getAddons)
                        $where = "addons";
                    else
                        $where = "requirements";

                    $this->set_step($data["step_token"], ['selection' => $selection, 'status' => "completed"]);
                    $this->set_step($this->step_token($data["type"], $data["id"], $where), ['status' => "incomplete"]);
                    $redirect = $this->CRLink("order-steps-p", [$data["type"], $data["id"], $where]);

                } else {

                    Helper::Load("Basket");

                    $category_title = isset($data["product"]["category_title"]) ? $data["product"]["category_title"] : '';
                    $category_route = isset($data["product"]["category_route"]) ? $data["product"]["category_route"] : '';

                    Basket::set(false, $data["product"]["title"], [
                        'event'          => "SpecialProductOrder",
                        'type'           => $data["type"],
                        'id'             => $data["id"],
                        'selection'      => $selection,
                        'category'       => isset($category_title) ? $category_title : null,
                        'category_route' => isset($category_route) ? $category_route : null,
                        '_time'          => DateManager::strtotime(),
                    ], false);
                    Basket::save();

                    $redirect = $this->CRLink("basket");
                }
                echo Utility::jencode(['status' => "successful", 'redirect' => $redirect]);

            } // Step 1 END

            if ($data["step"] == "domain") { // Step Domain START

                $step1 = $this->get_step($this->step_token($data["type"], $data["id"], 1));
                $selection = $step1["selection"];

                if (!$step1) die("Step 1 is empty");

                $type = Filter::init("POST/type", "letters");
                $domain = Filter::init("POST/domain", "domain");
                $domain = str_replace("www.", "", $domain);
                $domain = trim($domain);
                $sld = null;
                $tld = null;
                $parse = Utility::domain_parser($domain);
                if ($parse["host"] != '' && strlen($parse["host"]) >= 2) {
                    $sld = $parse["host"];
                    $tld = $parse["tld"];
                }


                $getFirstTLD = $this->getTLD(null, 0);
                if ($getFirstTLD) $getFirstTLD = $getFirstTLD["name"];
                $tld = $tld == null ? $getFirstTLD : $tld;
                $fdomain = $sld . "." . $tld;

                if ($sld == '' || $tld == '')
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => $type == "registrar" ? "#DomainCheck input[name='domain']" : "#special_domain",
                        'message' => __("website/osteps/error1"),
                    ]));

                if (Validation::check_prohibited($fdomain, ['domain', 'word']))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => __("website/account/prohibited-alert"),
                    ]));

                if ($type == "registrar" || $type == "normal") {
                    Helper::Load(["Basket"]);
                    if ($type == "registrar") {
                        $tinfo = $this->getTLD($tld);
                        if ($tinfo) {

                            if (Basket::count()) {
                                foreach (Basket::listing() as $item) {
                                    $options = $item["options"];
                                    if ($options["type"] == "domain" && $item["name"] == $fdomain)
                                        die(Utility::jencode([
                                            'status'  => "error",
                                            'for'     => "#DomainCheck input[name='domain']",
                                            'message' => __("website/domain/error11"),
                                        ]));
                                }
                            }

                            $domain_unique = Basket::set(false, $fdomain, [
                                'event'          => "DomainNameRegisterOrder",
                                'type'           => "domain",
                                'id'             => $tinfo["id"],
                                'period'         => "year",
                                'period_time'    => 1,
                                'category'       => __("website/osteps/category-domain"),
                                'category_route' => $this->CRLink("domain"),
                                'sld'            => $sld,
                                'tld'            => $tld,
                                'dns'            => Config::get("options/ns-addresses"),
                            ], false);

                            Basket::save();
                        }
                    }

                    if ($getAddons) {
                        $continue_token = $this->step_token($data["type"], $data["id"], "addons");
                        $continue_url = $this->CRLink("order-steps-p", [$data["type"], $data["id"], "addons"]);
                    } elseif ($getRequirements) {
                        $continue_token = $this->step_token($data["type"], $data["id"], "requirements");
                        $continue_url = $this->CRLink("order-steps-p", [$data["type"], $data["id"], "requirements"]);
                    }

                    if ($getAddons || $getRequirements) {
                        $this->set_step($data["step_token"], [
                            'type'          => $type,
                            'domain'        => $fdomain,
                            'domain_unique' => isset($domain_unique) ? $domain_unique : false,
                            'status'        => "completed",
                        ]);
                        $this->set_step($continue_token, ['status' => "incomplete"]);
                        die(Utility::jencode([
                            'status'   => "successful",
                            'redirect' => $continue_url,
                        ]));
                    } else {
                        $category_title = isset($data["product"]["category_title"]) ? $data["product"]["category_title"] : '';
                        $category_route = isset($data["product"]["category_route"]) ? $data["product"]["category_route"] : '';
                        Basket::set(false, $data["product"]["title"], [
                            'event'          => "SpecialProductOrder",
                            'type'           => "special",
                            'id'             => $data["product"]["id"],
                            'domain'         => $fdomain,
                            'selection'      => $selection,
                            'category'       => $category_title,
                            'category_route' => $category_route,
                        ], false);
                        Basket::save();

                        die(Utility::jencode([
                            'status'   => "successful",
                            'redirect' => $this->CRLink("basket"),
                        ]));
                    }
                } else die("Error #1");
            } // Step Domain END

            if ($data["step"] == "addons") { // Addons START

                $addons = Filter::POST("addons");
                $addons_values = Filter::POST("addons_values");
                $as_selected = [];
                $as_selected_v = [];

                if ($addons && is_array($addons)) {
                    foreach ($getAddons as $addon) {
                        if (isset($addons[$addon["id"]]) && Validation::isInt($addons[$addon["id"]])) {
                            $options = $addon["options"];
                            foreach ($options as $k => $v) {
                                if ($v["id"] == $addons[$addon["id"]]) {
                                    $as_selected[$addon["id"]] = $v["id"];
                                    if ($addon["type"] == "quantity") {
                                        if (isset($addons_values[$addon["id"]])) {
                                            $addon_quantity = (int)Filter::numbers($addons_values[$addon["id"]]);
                                            if ($addon_quantity) $as_selected_v[$addon["id"]] = $addon_quantity;
                                        }
                                    }
                                }
                            }
                        }
                        if (isset($addon["properties"]["compulsory"]) && $addon["properties"]["compulsory"] && !isset($as_selected[$addon["id"]]))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "*[name='addons[" . $addon["id"] . "]']",
                                'message' => __("website/osteps/addon-required", ['{name}' => $addon["name"]]),
                            ]));
                        if ($addon["type"] == "quantity" && isset($addon["properties"]["compulsory"]) && $addon["properties"]["compulsory"] && !isset($as_selected_v[$addon["id"]]))
                            die(Utility::jencode([
                                'status'  => "error",
                                'for'     => "*[name='addons[" . $addon["id"] . "]']",
                                'message' => __("website/osteps/addon-required", ['{name}' => $addon["name"]]),
                            ]));
                    }
                }


                $step1t = $this->step_token($data["type"], $data["id"], 1);
                $step2t = $this->step_token($data["type"], $data["id"], "domain");
                $step3t = $this->step_token($data["type"], $data["id"], "addons");
                $step1 = $this->get_step($step1t);
                $step2 = $this->get_step($step2t);
                $step3 = $this->get_step($step3t);

                $requirements = [];
                foreach ($getAddons as $addon) {
                    if (isset($as_selected[$addon["id"]]) && strlen($addon["requirements"]) > 0) {
                        $rqs = explode(",", $addon["requirements"]);
                        foreach ($rqs as $rq) if (!in_array($rq, $requirements)) $requirements[] = $rq;
                    }
                }
                if (!$requirements && $getRequirements)
                    foreach ($getRequirements as $getRequirement) $requirements[] = $getRequirement["id"];

                if (isset($requirements) && $requirements)
                    foreach ($requirements as $rk => $rq)
                        if (!is_array($rq))
                            if (!Products::requirement($rq)) unset($requirements[$rk]);


                if ($requirements) {
                    $sdata = [
                        'status'       => "completed",
                        'requirements' => $requirements,
                    ];

                    if ($as_selected) $sdata["addons"] = $as_selected;
                    if ($as_selected_v) $sdata["addons_values"] = $as_selected_v;

                    $this->set_step($step3t, $sdata);

                    $this->set_step($this->step_token($data["type"], $data["id"], "requirements"), ['status' => "incomplete"]);
                    $redirect = $this->CRLink("order-steps-p", [$data["type"], $data["id"], "requirements"]);

                } else {
                    Helper::Load("Basket");
                    $category_title = isset($data["product"]["category_title"]) ? $data["product"]["category_title"] : '';
                    $category_route = isset($data["product"]["category_route"]) ? $data["product"]["category_route"] : '';
                    $idata = [
                        'event'          => "SpecialProductOrder",
                        'type'           => $data["type"],
                        'id'             => $data["id"],
                        'selection'      => $step1["selection"],
                        'domain'         => isset($step2["domain"]) ? $step2["domain"] : false,
                        'category'       => isset($category_title) ? $category_title : null,
                        'category_route' => isset($category_route) ? $category_route : null,
                        '_time'          => DateManager::strtotime(),
                    ];

                    if ($as_selected) $idata["addons"] = $as_selected;
                    if ($as_selected_v) $idata["addons_values"] = $as_selected_v;

                    Basket::set(false, $data["product"]["title"], $idata, false);
                    Basket::save();

                    if ($step1t) $this->delete_step($step1t);
                    if ($step2t) $this->delete_step($step2t);
                    if ($step3t) $this->delete_step($step3t);

                    $redirect = $this->CRLink("basket");
                }

                echo Utility::jencode([
                    'status'   => "successful",
                    'redirect' => $redirect,
                ]);

            } // Addons END

            if ($data["step"] == "requirements") { // Requirements START

                $step1t = $this->step_token($data["type"], $data["id"], 1);
                $step2t = $this->step_token($data["type"], $data["id"], "domain");
                $step3t = $this->step_token($data["type"], $data["id"], "addons");
                $step4t = $this->step_token($data["type"], $data["id"], "requirements");
                $step1 = $this->get_step($step1t);
                $step2 = $this->get_step($step2t);
                $step3 = $this->get_step($step3t);
                $step4 = $this->get_step($step4t);

                if (!$step1) die("Not Found Step 1 Data");

                $category_title = isset($data["product"]["category_title"]) ? $data["product"]["category_title"] : '';
                $category_route = isset($data["product"]["category_route"]) ? $data["product"]["category_route"] : '';

                $item_opt = [
                    'event'          => "SpecialProductOrder",
                    'type'           => $data["type"],
                    'id'             => $data["id"],
                    'selection'      => $step1["selection"],
                    'domain'         => isset($step2["domain"]) ? $step2["domain"] : false,
                    'category'       => isset($category_title) ? $category_title : null,
                    '_time'          => DateManager::strtotime(),
                    'category_route' => isset($category_route) ? $category_route : null,
                ];


                $requirements = Filter::POST("requirements");
                $rs_selected = [];

                if ($step3 && isset($step3["requirements"])) {

                    $getRequirements = [];

                    if ($bring_hook_requirements)
                        foreach ($bring_hook_requirements as $rows) if ($rows) foreach ($rows as $row) $getRequirements[] = $row;

                    $get_requirements = $this->requirements(implode(",", $step3["requirements"]));
                    if ($get_requirements) foreach ($get_requirements as $row) $getRequirements[] = $row;
                }


                if ($getRequirements) {
                    foreach ($getRequirements as $requirement) {
                        $values = null;
                        $options = $requirement["options"];
                        $properties = $requirement["properties"];
                        $is_input = in_array($requirement["type"], ["input", "textarea"]);
                        $is_opt = in_array($requirement["type"], ["select", "radio", "checkbox"]);
                        $is_plural = $requirement["type"] == "checkbox";
                        $value = null;
                        if (isset($requirements[$requirement["id"]])) $value = $requirements[$requirement["id"]];
                        if ($is_opt && !is_array($value)) $value = Utility::substr($value, 0, 200);

                        $filterRequirements = Hook::run("filterRequirementToOrderSteps", [
                            'product'     => $data["product"],
                            'step_data'   => $step_data,
                            'requirement' => $requirement,
                            'value'       => $value,
                        ]);

                        if ($filterRequirements)
                            foreach ($filterRequirements as $item) if (isset($item["value"])) $value = $item["value"];


                        $opt_ids = [];
                        if ($options) foreach ($options as $option) $opt_ids[] = $option["id"];

                        if ($requirement["type"] == "file") {
                            $files = Filter::FILES("requirement-" . $requirement["id"]);
                            if (isset($properties["compulsory"]) && $properties["compulsory"])
                                if (!$files)
                                    die(Utility::jencode([
                                        'status'  => "error",
                                        'for'     => "input[name='requirement-" . $requirement["id"] . "[]']",
                                        'message' => __("website/osteps/field-required", ['{name}' => $requirement["name"]]),
                                    ]));
                            if ($files && !DEMO_MODE) {
                                $extensions = isset($options["allowed-extensions"]) ? $options["allowed-extensions"] : Config::get("options/product-fields-extensions");
                                $max_filesize = isset($options["max-file-size"]) ? $options["max-file-size"] : Config::get("options/product-fields-max-file-size");
                                Helper::Load("Uploads");
                                $upload = Helper::get("Uploads");
                                $upload->init($files, [
                                    'date'          => false,
                                    'multiple'      => true,
                                    'max-file-size' => $max_filesize,
                                    'folder'        => ROOT_DIR . "temp" . DS,
                                    'allowed-ext'   => $extensions,
                                    'file-name'     => "random",
                                    'width'         => Config::get("pictures/product-requirements/sizing/width"),
                                    'height'        => Config::get("pictures/product-requirements/sizing/height"),
                                ]);
                                if (!$upload->processed())
                                    die(Utility::jencode([
                                        'status'  => "error",
                                        'for'     => "input[name='requirement-" . $requirement["id"] . "[]']",
                                        'message' => __("website/osteps/failed-field-upload", ['{error}' => $upload->error]),
                                    ]));
                                if ($upload->operands) {
                                    $values = Utility::jencode($upload->operands);
                                }
                            }
                        } elseif (isset($properties["compulsory"]) && $properties["compulsory"]) {
                            $compulsory = false;

                            if (!isset($requirements[$requirement["id"]])) $compulsory = true;
                            elseif (Validation::isEmpty($value)) $compulsory = true;
                            elseif ($is_input && Utility::strlen($value) == 0) $compulsory = true;

                            elseif ($is_opt && $is_plural && !is_array($value)) $compulsory = true;

                            elseif ($is_opt && $is_plural && $value)
                                foreach ($value as $val) if (!in_array($val, $opt_ids)) $compulsory = true;

                                elseif ($is_opt && !$is_plural && !in_array($value, $opt_ids)) $compulsory = true;


                            if ($compulsory)
                                die(Utility::jencode([
                                    'status'  => "error",
                                    'for'     => "*[name='requirements[" . $requirement["id"] . "]']",
                                    'message' => __("website/osteps/field-required", ['{name}' => $requirement["name"]]),
                                ]));
                        }

                        $checkingRequirement = Hook::run("checkingRequirementToOrderSteps", [
                            'product'     => $data["product"],
                            'step_data'   => $step_data,
                            'requirement' => $requirement,
                            'value'       => $value,
                        ]);

                        if ($checkingRequirement) {
                            foreach ($checkingRequirement as $row) {
                                if (isset($row["status"]) && $row["status"] == "error")
                                    die(Utility::jencode([
                                        'status'  => "error",
                                        'for'     => "*[name='requirements[" . $requirement["id"] . "]']",
                                        'message' => $row["message"],
                                    ]));

                            }
                        }


                        if ($requirement["type"] == "input" || $requirement["type"] == "password") $values = Utility::short_text(Filter::html_clear($value), 0, 500);
                        elseif ($requirement["type"] == "textarea") $values = Utility::short_text($value, 0, 5000);
                        elseif ($is_opt) {
                            $values = [];
                            if (isset($requirements[$requirement["id"]])) {
                                if (!is_array($value)) $value = [$value];
                                foreach ($options as $opt) {
                                    if (in_array($opt["id"], $value)) {
                                        $values[] = $opt["id"];
                                    }
                                }
                            }
                        }

                        if ($values) {
                            if (is_array($values)) $values = implode(",", $values);
                            if (!isset($properties["define_attribute_to_basket_item_options"]) || !$properties["define_attribute_to_basket_item_options"]) $rs_selected[$requirement["id"]] = $values;

                            if (isset($properties["define_attribute_to_basket_item_options"]))
                                if ($properties["define_attribute_to_basket_item_options"])
                                    $item_opt[$properties["define_attribute_to_basket_item_options"]] = $values;
                        }
                    }
                }

                Helper::Load("Basket");
                if ($step3 && isset($step3["addons"])) $item_opt["addons"] = $step3["addons"];
                if ($step3 && isset($step3["addons_values"])) $item_opt["addons_values"] = $step3["addons_values"];
                if ($rs_selected) $item_opt["requirements"] = $rs_selected;

                Basket::set(false, $data["product"]["title"], $item_opt, false);
                Basket::save();

                $this->delete_step($step1t);
                $this->delete_step($step2t);
                $this->delete_step($step3t);
                $this->delete_step($step4t);
                $redirect = $this->CRLink("basket");

                echo Utility::jencode(['status' => "successful", 'redirect' => $redirect]);
            } // Requirements END
        }


        private function sms_post($data = [])
        {

            $this->takeDatas("language");

            if (!defined("DISABLE_CSRF")) {
                $token = Filter::init("POST/token", "hclear");
                if (!$token || !Validation::verify_csrf_token($token, 'order-steps'))
                    die(Utility::jencode([
                        'status'  => "error",
                        'message' => ___("needs/invalid-csrf-token"),
                    ]));
            }


            if ($data["step"] == 1) { // Step 1 START

                $step1t = $data["step_token"];

                $full_name = Filter::init("POST/name", "hclear");
                $full_name = Utility::substr($full_name, 0, 255);
                $full_name = Utility::ucfirst_space($full_name, ___("package/charset-code"));

                $identity = Filter::init("POST/identity", "identity");
                $birthday = Filter::init("POST/birthday", "numbers", "\/");

                if ($birthday) {
                    $birthday = str_replace("/", "-", $birthday);
                    $birthday = DateManager::format("Y-m-d", $birthday);
                }

                if (Validation::isEmpty($full_name))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='name']",
                        'message' => __("website/sign/up-submit-empty-full_name"),
                    ]));

                if (Validation::isEmpty($birthday))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='birthday']",
                        'message' => __("website/sign/up-birthday-empty"),
                    ]));

                if (Validation::isEmpty($identity))
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='identity']",
                        'message' => __("website/sign/empty-identity-number"),
                    ]));

                $check = Validation::isidentity($identity, $full_name, $birthday);
                if (!$check)
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='identity']",
                        'message' => __("website/sign/up-submit-invalid-identity"),
                    ]));

                $this->set_step($step1t, [
                    'status'   => "completed",
                    'name'     => $full_name,
                    'birthday' => $birthday,
                    'identity' => $identity,
                ]);

                $this->set_step($this->step_token($data["type"], $data["id"], "origin"), ['status' => "incomplete"]);
                $redirect = $this->CRLink("order-steps-p", [$data["type"], $data["id"], "origin"]);

                echo Utility::jencode(['status' => "successful", 'redirect' => $redirect]);
            } // Step 1 END

            if ($data["step"] == "origin") { // Step Origin Start

                $step1t = $this->step_token($data["type"], $data["id"], 1);
                $step1 = $this->get_step($step1t);

                if (!$step1 || !isset($step1["name"])) die("Step 1 data not found");

                $origin = Filter::init("POST/origin", "noun");
                $attachments = Filter::FILES("attachments");
                $length = Utility::strlen($origin);


                if ($length > 11 || $length < 1)
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='origin']",
                        'message' => __("website/account_products/send-origin-error1"),
                    ]));

                if (!$attachments)
                    die(Utility::jencode([
                        'status'  => "error",
                        'for'     => "input[name='attachments[]']",
                        'message' => "Lütfen Evrak Yükleyiniz.",
                    ]));

                if ($attachments && is_array($attachments) && !DEMO_MODE) {
                    Helper::Load("Uploads");
                    $upload = Helper::get("Uploads");
                    $upload->init($attachments, [
                        'date'          => false,
                        'multiple'      => true,
                        'max-file-size' => Config::get("options/product-fields-max-file-size"),
                        'folder'        => ROOT_DIR . "temp" . DS,
                        'allowed-ext'   => Config::get("options/product-fields-extensions"),
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
                }
                $attachments = isset($attachments) ? $attachments : [];

                Helper::Load("Basket");

                Basket::set(false, $data["product"]["title"], [
                    'event'          => "SmsProductOrder",
                    'fields'         => [
                        'attachments' => $attachments,
                        'origin'      => $origin,
                        'name'        => $step1["name"],
                        'birthday'    => $step1["birthday"],
                        'identity'    => $step1["identity"],
                    ],
                    'type'           => $data["type"],
                    'id'             => $data["id"],
                    'selection'      => $data["product"]["price"][0],
                    'category'       => ___("constants/category-sms/title"),
                    'category_route' => $data["product"]["category_route"],
                ], false);
                Basket::save();

                $this->delete_step($step1t);
                $this->delete_step($data["step_token"]);

                echo Utility::jencode(['status' => "successful", 'redirect' => $this->CRLink("basket")]);

            } // Step Origin END

        }


        public function main()
        {
            if (!isset($this->params[0]) || !isset($this->params[1]) || !isset($this->params[2])) die();
            $lang = Bootstrap::$lang->clang;
            $type = strtolower(Filter::letters($this->params[0]));
            $id = Filter::numbers($this->params[1]);
            $step = Filter::init($this->params[2], "route");

            if (Validation::isEmpty($type) || Validation::isEmpty($id) || Validation::isEmpty($step)) die();

            $types = explode(",", Config::get("options/product-types"));
            if (!in_array($type, $types)) die();

            $product = $this->get_product($type, $id);
            if (!$product) die();
            $token = $this->step_token($type, $id, $step);

            $step_data = $this->get_step($token);

            if (!$step_data && $step != 1) {
                Utility::redirect($this->CRLink("order-steps-p", [$type, $id, 1]));
                die();
            }

            $data = [
                'type'       => $type,
                'id'         => $id,
                'step'       => $step,
                'step_token' => $token,
                'step_data'  => $step_data,
                'product'    => $product,
            ];

            $this->type = $type;
            $this->id = $id;

            if (Filter::isPOST()) return $this->{$type . "_post"}($data);

            $this->data = array_merge($this->data, $data);

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


            $breadcrumb = [];
            $links = [];
            $meta = [];
            $steps = [];
            $htitle = null;

            array_push($breadcrumb, [
                'link'  => $this->CRLink("home"),
                'title' => __("website/index/breadcrumb-home"),
            ]);

            $step1d = $this->get_step($this->step_token($type, $id, 1));

            if ($type == "domain") {

                Helper::Load("Orders");

                if (!Config::get("options/pg-activation/domain")) return $this->main_404();

                $header_background = $this->get_product_header_background("domain");

                $getHostingList = $this->getHostingList(false, isset($step1d["elected"][0]) ? $step1d["elected"][0] : '');

                if (Config::get("options/domain-hide-hosting")) $getHostingList = false;

                $modules = Modules::Load("Registrars", "All", true);

                $paperwork = false;
                $extensions = [];

                if (isset($step1d["elected"]) && is_array($step1d["elected"]) && $step1d["elected"]) {
                    foreach ($step1d["elected"] as $dom) {
                        $tinfo = $this->getTLD($dom["tld"]);
                        if ($tinfo) {
                            if (isset($tinfo['required_docs_info']) && strlen($tinfo['required_docs_info']) > 1) {
                                $tinfo['required_docs_info'] = Utility::jdecode($tinfo['required_docs_info'], true);
                                $lkeys = array_keys($tinfo['required_docs_info']);
                                if (isset($lkeys[$lang]))
                                    $tinfo['required_docs_info'] = $tinfo['required_docs_info'][$lang];
                                else
                                    $tinfo['required_docs_info'] = $tinfo['required_docs_info'][$lkeys[0]];
                            }


                            $dom["tinfo"] = $tinfo;
                            $extensions[$dom["tld"]] = $dom;

                            if ($tinfo["module"] != "none" && $tinfo["module"]) {
                                if (isset($modules[$tinfo["module"]]["config"]["settings"]["doc-fields"][$tinfo["name"]])) {
                                    if ($modules[$tinfo["module"]]["config"]["settings"]["doc-fields"][$tinfo["name"]])
                                        $paperwork = true;
                                }
                            }

                            $manuel_doc = $this->model->db->select("id")->from("tldlist_docs")->where("tld", "=", $tinfo["name"])->build();
                            if ($manuel_doc)
                                $paperwork = true;

                            if ($paperwork) $extensions[$dom["tld"]]["requirements"] = Orders::detect_docs_in_domain(false, $tinfo);
                        }
                    }
                }

                $this->addData("tlds", $extensions);


                array_push($steps, [
                    'id'   => 1,
                    'link' => $this->CRLink("order-steps-p", [$type, $id, 1]),
                    'name' => __("website/osteps/set-domain-name"),
                ]);

                if ($paperwork)
                    array_push($steps, [
                        'id'   => "requirements",
                        'link' => $this->CRLink("order-steps-p", [$type, $id, "requirements"]),
                        'name' => __("website/osteps/set-requirements"),
                    ]);

                if (Config::get("options/pg-activation/hosting") && $getHostingList)
                    array_push($steps, [
                        'id'   => "hosting",
                        'link' => $this->CRLink("order-steps-p", [$type, $id, "hosting"]),
                        'name' => __("website/osteps/set-web-hosting"),
                    ]);

                array_push($steps, [
                    'id'   => "added-basket",
                    'link' => $this->CRLink("order-steps-p", [$type, $id, "basket-added"]),
                    'name' => __("website/osteps/basket-added"),
                ]);

                $htitle = __("website/osteps/domain-register2");
                $meta['title'] = __("website/osteps/meta-title", ['{product-name}' => $htitle]);

                array_push($breadcrumb, [
                    'link'  => $this->CRLink("domain"),
                    'title' => __("website/osteps/domain-register"),
                ]);

                array_push($breadcrumb, [
                    'link'  => null,
                    'title' => __("website/osteps/order"),
                ]);

                $this->addData("hosting_list", $getHostingList);
                $this->addData("hosting_link", $this->CRLink("products", ["hosting"]));
                $this->addData("dns", Config::get("options/ns-addresses"));

                if ($step == 1) {
                    if ($step_data) {
                        if ($paperwork) {
                            $reqdata = ["status" => "incomplete"];
                            $reqdata_token = $this->step_token($type, $id, "requirements");
                            $this->set_step($reqdata_token, $reqdata);
                            Utility::redirect($this->CRLink("order-steps-p", [$type, $id, "requirements"]));
                        } elseif (Config::get("options/pg-activation/hosting") && $getHostingList && $id == 1) {
                            $hostdata = ["status" => "incomplete"];
                            $hosttoken = $this->step_token($type, $id, "hosting");
                            $this->set_step($hosttoken, $hostdata);
                            Utility::redirect($this->CRLink("order-steps-p", [$type, $id, "hosting"]));
                        } else {
                            if ($extensions) {
                                foreach ($extensions as $dom) {
                                    $tinfo = $dom["tinfo"];
                                    $tdata = [
                                        'event'          => isset($dom["tcode"]) && $dom["tcode"] != '' ? "DomainNameTransferRegisterOrder" : "DomainNameRegisterOrder",
                                        'type'           => "domain",
                                        'id'             => $tinfo["id"],
                                        "period"         => "year",
                                        'period_time'    => $dom["period"],
                                        'category'       => __("website/osteps/category-domain"),
                                        'category_route' => $this->CRLink("domain"),
                                        'sld'            => $dom["sld"],
                                        'tld'            => $dom["tld"],
                                        'dns'            => Config::get("options/ns-addresses"),
                                    ];
                                    if (isset($dom["tcode"]) && $dom["tcode"] != '') $tdata["tcode"] = $dom["tcode"];
                                    if (isset($dom["renewal"]) && $dom["renewal"]) {
                                        $tdata["renewal"] = true;
                                        $tdata["category"] = __("website/osteps/category-domain-renewal");
                                    }
                                    Basket::set(false, $dom["domain"], $tdata, false);
                                }
                                Basket::save();
                            }
                            $this->delete_step($token);
                            Utility::redirect($this->CRLink("basket"));
                            return false;
                        }
                    } else {
                        Utility::redirect($this->CRLink("domain"));
                        return false;
                    }
                }
            }

            if ($type == "software") {

                if (!Config::get("options/pg-activation/software")) return $this->main_404();

                $hide_domain = isset($product["options"]["hide_domain"]) && $product["options"]["hide_domain"];
                $hide_hosting = isset($product["options"]["hide_hosting"]) && $product["options"]["hide_hosting"];


                $header_background = $this->get_product_header_background("software", $product["id"]);

                $getFirstTLD = $this->getTLD(null, 0);
                $getHostingList = $this->getHostingList($product, $step1d);
                $getAddons = $this->addons($product["addons"], isset($step1d["selection"]) ? $step1d["selection"] : false);
                $getRequirements = $this->requirements($product["requirements"]);

                if ($step2 = $this->get_step($this->step_token($type, $id, "hosting")))
                    $this->addData("step2_data", $step2);

                if ($step3 = $this->get_step($this->step_token($type, $id, "addons")))
                    $this->addData("step3_data", $step3);

                if ($step3 && isset($step3["requirements"])) {
                    $getRequirements = $this->requirements(implode(",", $step3["requirements"]));
                }

                if ($step4 = $this->get_step($this->step_token($type, $id, "requirements")))
                    $this->addData("step4_data", $step4);


                array_push($steps, [
                    'id'   => 1,
                    'link' => $this->CRLink("order-steps-p", [$type, $id, 1]),
                    'name' => __("website/osteps/duration-of-service"),
                ]);

                if (!$hide_domain)
                    array_push($steps, [
                        'id'   => "domain",
                        'link' => $this->CRLink("order-steps-p", [$type, $id, 1]),
                        'name' => __("website/osteps/set-domain-name"),
                    ]);


                if (Config::get("options/pg-activation/hosting") && $getHostingList && !$hide_hosting)
                    array_push($steps, [
                        'id'   => "hosting",
                        'link' => $this->CRLink("order-steps-p", [$type, $id, "hosting"]),
                        'name' => __("website/osteps/set-web-hosting"),
                    ]);

                if ($getAddons)
                    array_push($steps, [
                        'id'   => "addons",
                        'link' => $this->CRLink("order-steps-p", [$type, $id, "addons"]),
                        'name' => __("website/osteps/set-additional-services"),
                    ]);

                if ($getRequirements)
                    array_push($steps, [
                        'id'   => "requirements",
                        'link' => $this->CRLink("order-steps-p", [$type, $id, "requirements"]),
                        'name' => __("website/osteps/set-requirements"),
                    ]);

                if (sizeof($steps) < 3) {
                    array_push($steps, [
                        'id'   => "added-basket",
                        'link' => $this->CRLink("order-steps-p", [$type, $id, "basket-added"]),
                        'name' => __("website/osteps/basket-added"),
                    ]);
                }


                $htitle = $product["title"];
                $meta['title'] = __("website/osteps/meta-title", ['{product-name}' => $product["title"]]);

                array_push($breadcrumb, [
                    'link'  => $this->CRLink("softwares"),
                    'title' => __("website/softwares/breadcrumb-title"),
                ]);

                if ($product["category_title"] != '')
                    array_push($breadcrumb, [
                        'link'  => $product["category_route"],
                        'title' => $product["category_title"],
                    ]);

                array_push($breadcrumb, [
                    'link'  => null,
                    'title' => __("website/osteps/order"),
                ]);


                $links["domain_check"] = $this->CRLink("domain");
                $this->addData("firstTLD", $getFirstTLD);
                $this->addData("domain_override_usrcurrency", Config::get("options/domain-override-user-currency"));
                $this->addData("hosting_list", $getHostingList);
                $this->addData("hosting_link", $this->CRLink("products", ["hosting"]));
                $this->addData("addons", $getAddons);
                $this->addData("requirements", $getRequirements);
            }

            if ($type == "hosting") {

                if (!Config::get("options/pg-activation/hosting")) return $this->main_404();

                $hide_domain = isset($product["options"]["hide_domain"]) && $product["options"]["hide_domain"];

                $header_background = $this->get_product_header_background("hosting", $product["id"]);

                $getFirstTLD = $this->getTLD(null, 0);
                $getAddons = $this->addons($product["addons"], isset($step1d["selection"]) ? $step1d["selection"] : false);
                $getRequirements = $this->requirements($product["requirements"]);

                $step1t = $this->step_token($data["type"], $data["id"], 1);
                $step2t = $this->step_token($data["type"], $data["id"], "domain");
                $step3t = $this->step_token($data["type"], $data["id"], "addons");
                $step4t = $this->step_token($data["type"], $data["id"], "requirements");
                $step1 = $this->get_step($step1t);
                $step2 = $this->get_step($step2t);
                $step3 = $this->get_step($step3t);
                $step4 = $this->get_step($step4t);

                if ($step3 && isset($step3["requirements"]))
                    $getRequirements = $this->requirements(implode(",", $step3["requirements"]));


                array_push($steps, [
                    'id'   => 1,
                    'link' => $this->CRLink("order-steps-p", [$type, $id, 1]),
                    'name' => __("website/osteps/duration-of-service"),
                ]);

                if (!$hide_domain)
                    array_push($steps, [
                        'id'   => "domain",
                        'link' => $this->CRLink("order-steps-p", [$type, $id, "domain"]),
                        'name' => __("website/osteps/set-domain-name"),
                    ]);

                if ($getAddons)
                    array_push($steps, [
                        'id'   => "addons",
                        'link' => $this->CRLink("order-steps-p", [$type, $id, "addons"]),
                        'name' => __("website/osteps/set-additional-services"),
                    ]);

                if ($getRequirements)
                    array_push($steps, [
                        'id'   => "requirements",
                        'link' => $this->CRLink("order-steps-p", [$type, $id, "requirements"]),
                        'name' => __("website/osteps/set-requirements"),
                    ]);

                array_push($steps, [
                    'id'   => "added-basket",
                    'link' => $this->CRLink("order-steps-p", [$type, $id, "basket-added"]),
                    'name' => __("website/osteps/basket-added"),
                ]);

                $htitle = $product["title"];
                $meta['title'] = __("website/osteps/meta-title", ['{product-name}' => $product["title"]]);


                $lang = Bootstrap::$lang->clang;

                array_push($breadcrumb, [
                    'link'  => $this->CRLink("products", ["hosting"]),
                    'title' => ___("constants/category-hosting/title"),
                ]);

                $parents = Products::get_parent_categories_breadcrumb($product["category"], $lang);
                if ($parents) {
                    $parents = array_reverse($parents);
                    $breadcrumb = array_merge($breadcrumb, $parents);
                }

                array_push($breadcrumb, [
                    'link'  => null,
                    'title' => __("website/osteps/order"),
                ]);

                $links["domain_check"] = $this->CRLink("domain");
                $this->addData("firstTLD", $getFirstTLD);
                $this->addData("domain_override_usrcurrency", Config::get("options/domain-override-user-currency"));
                $this->addData("addons", $getAddons);
                $this->addData("requirements", $getRequirements);
                $this->addData("product", $data["product"]);

                Helper::Load(["Products", "Orders"]);

                if ($data["product"]["module"] == "none" || $data["product"]["module"] == '') {
                    $dns = [];
                    if (isset($data["product"]["options"]["dns"]["ns1"]) && $data["product"]["options"]["dns"]["ns1"] != '') array_push($dns, $data["product"]["options"]["dns"]["ns1"]);
                    if (isset($data["product"]["options"]["dns"]["ns2"]) && $data["product"]["options"]["dns"]["ns2"] != '') array_push($dns, $data["product"]["options"]["dns"]["ns2"]);
                    if (isset($data["product"]["options"]["dns"]["ns3"]) && $data["product"]["options"]["dns"]["ns3"] != '') array_push($dns, $data["product"]["options"]["dns"]["ns3"]);
                    if (isset($data["product"]["options"]["dns"]["ns4"]) && $data["product"]["options"]["dns"]["ns4"] != '') array_push($dns, $data["product"]["options"]["dns"]["ns4"]);
                } elseif ((isset($data["product"]["options"]["server_id"]) && $data["product"]["options"]["server_id"]) || (isset($data["product"]["module_data"]["server_id"]) && $data["product"]["module_data"]["server_id"])) {
                    $server_id = isset($data["product"]["module_data"]["server_id"]) ? $data["product"]["module_data"]["server_id"] : 0;
                    if (isset($data["product"]["options"]["server_id"]) && $data["product"]["options"]["server_id"])
                        $server_id = $data["product"]["options"]["server_id"];


                    $group_id = $data["product"]["module_data"]["server_group_id"] ?? ($data["product"]["options"]["server_group_id"] ?? 0);
                    $group = $group_id > 0 ? Products::get_server_group($group_id) : false;

                    if ($group) {
                        $catch_server = Products::catch_server_in_group($group["servers"], $group["fill_type"]);
                        if ($catch_server) {
                            $server_id = $catch_server;
                        }
                    }


                    $server = Products::get_server($server_id);
                    $dns = [];
                    if ($server["ns1"] != '') array_push($dns, $server["ns1"]);
                    if ($server["ns2"] != '') array_push($dns, $server["ns2"]);
                    if ($server["ns3"] != '') array_push($dns, $server["ns3"]);
                    if ($server["ns4"] != '') array_push($dns, $server["ns4"]);
                }
                if (!isset($dns) || !$dns) $dns = Config::get("options/ns-addresses");
                $this->addData("dns_addresses", $dns);
            }

            if ($type == "server") {

                if (!Config::get("options/pg-activation/server")) return $this->main_404();

                $header_background = $this->get_product_header_background("server", $product["id"]);

                $getAddons = $this->addons($product["addons"], isset($step1d["selection"]) ? $step1d["selection"] : false);
                $getRequirements = $this->requirements($product["requirements"]);


                $step1t = $this->step_token($data["type"], $data["id"], 1);
                $step2t = $this->step_token($data["type"], $data["id"], "configuration");
                $step3t = $this->step_token($data["type"], $data["id"], "requirements");
                $step1 = $this->get_step($step1t);
                $step2 = $this->get_step($step2t);


                array_push($steps, [
                    'id'   => 1,
                    'link' => $this->CRLink("order-steps-p", [$type, $id, 1]),
                    'name' => __("website/osteps/duration-of-service"),
                ]);

                if ($getAddons || $getRequirements || Config::get("options/hidsein"))
                    array_push($steps, [
                        'id'   => "configuration",
                        'link' => $this->CRLink("order-steps-p", [$type, $id, "configuration"]),
                        'name' => __("website/osteps/server-configuration"),
                    ]);


                array_push($steps, [
                    'id'   => "added-basket",
                    'link' => $this->CRLink("order-steps-p", [$type, $id, "basket-added"]),
                    'name' => __("website/osteps/basket-added"),
                ]);

                $htitle = $product["title"];
                $meta['title'] = __("website/osteps/meta-title", ['{product-name}' => $product["title"]]);

                $lang = Bootstrap::$lang->clang;

                array_push($breadcrumb, [
                    'link'  => $this->CRLink("products", ["server"]),
                    'title' => ___("constants/category-server/title"),
                ]);

                $parents = Products::get_parent_categories_breadcrumb($product["category"], $lang);
                if ($parents) {
                    $parents = array_reverse($parents);
                    $breadcrumb = array_merge($breadcrumb, $parents);
                }

                array_push($breadcrumb, [
                    'link'  => null,
                    'title' => __("website/osteps/order"),
                ]);

                $this->addData("addons", $getAddons);
                $this->addData("requirements", $getRequirements);
                $this->addData("step1_data", $this->get_step($this->step_token($type, $id, 1)));

            }

            if ($type == "special") {

                $header_background = $this->get_product_header_background("special", $product["id"]);

                $getFirstTLD = $this->getTLD(null, 0);
                $getAddons = $this->addons($product["addons"], isset($step1d["selection"]) ? $step1d["selection"] : false);
                $getRequirements = [];

                $step1_data = $this->get_step($this->step_token($type, $id, 1));
                $step2_data = $this->get_step($this->step_token($type, $id, "domain"));
                $step3_data = $this->get_step($this->step_token($type, $id, "requirements"));
                $step4_data = $this->get_step($this->step_token($type, $id, "addons"));
                $step_data = array_merge($step1_data, $step2_data, $step3_data, $step4_data);

                if ((!$step1_data || !isset($step1_data["selection"])) && $step != 1) {
                    Utility::redirect($this->CRLink("order-steps-p", [$type, $id, 1]));
                    exit;
                }


                $bring_hook_requirements = Hook::run("addRequirementToOrderSteps", $product, $step_data);

                if ($bring_hook_requirements)
                    foreach ($bring_hook_requirements as $rows) if ($rows) foreach ($rows as $row) $getRequirements[] = $row;

                $get_requirements = $this->requirements($product["requirements"]);

                if ($get_requirements) foreach ($get_requirements as $row) $getRequirements[] = $row;


                if ($step4_data && isset($step4_data["requirements"])) {

                    $getRequirements = [];

                    if ($bring_hook_requirements)
                        foreach ($bring_hook_requirements as $rows) if ($rows) foreach ($rows as $row) $getRequirements[] = $row;

                    $get_requirements = $this->requirements(implode(",", $step4_data["requirements"]));
                    if ($get_requirements) foreach ($get_requirements as $row) $getRequirements[] = $row;
                }

                array_push($steps, [
                    'id'   => 1,
                    'link' => $this->CRLink("order-steps-p", [$type, $id, 1]),
                    'name' => __("website/osteps/duration-of-service"),
                ]);

                if (isset($product["options"]["show_domain"]) && $product["options"]["show_domain"])
                    array_push($steps, [
                        'id'   => "domain",
                        'link' => $this->CRLink("order-steps-p", [$type, $id, "domain"]),
                        'name' => __("website/osteps/set-domain-name"),
                    ]);

                if ($getAddons)
                    array_push($steps, [
                        'id'   => "addons",
                        'link' => $this->CRLink("order-steps-p", [$type, $id, "addons"]),
                        'name' => __("website/osteps/set-additional-services"),
                    ]);

                if ($getRequirements)
                    array_push($steps, [
                        'id'   => "requirements",
                        'link' => $this->CRLink("order-steps-p", [$type, $id, "requirements"]),
                        'name' => __("website/osteps/set-requirements"),
                    ]);

                array_push($steps, [
                    'id'   => "added-basket",
                    'link' => $this->CRLink("order-steps-p", [$type, $id, "basket-added"]),
                    'name' => __("website/osteps/basket-added"),
                ]);

                $htitle = $product["title"];
                $meta['title'] = __("website/osteps/meta-title", ['{product-name}' => $product["title"]]);

                $parents = Products::get_parent_categories_breadcrumb($product["category"], $lang);
                if ($parents) {
                    $parents = array_reverse($parents);
                    $breadcrumb = array_merge($breadcrumb, $parents);
                }

                array_push($breadcrumb, [
                    'link'  => null,
                    'title' => __("website/osteps/order"),
                ]);

                if ($step2 = $this->get_step($this->step_token($type, $id, "addons")))
                    $this->addData("step2_data", $step2);

                if ($step3 = $this->get_step($this->step_token($type, $id, "requirements")))
                    $this->addData("step3_data", $step3);

                if ($getRequirements && $step2 && isset($step2["requirements"])) {
                    $getRequirements = $this->requirements(implode(",", $step2["requirements"]), $getRequirements);
                }

                $links["domain_check"] = $this->CRLink("domain");
                $this->addData("firstTLD", $getFirstTLD);
                $this->addData("domain_override_usrcurrency", Config::get("options/domain-override-user-currency"));
                $this->addData("addons", $getAddons);
                $this->addData("requirements", $getRequirements);
            }


            if ($type == "sms") {

                $header_background = $this->get_product_header_background("sms");

                $getRequirements = $this->requirements($product["requirements"]);

                array_push($steps, [
                    'id'   => 1,
                    'link' => $this->CRLink("order-steps-p", [$type, $id, 1]),
                    'name' => __("website/osteps/compulsory-information"),
                ]);

                array_push($steps, [
                    'id'   => "origin",
                    'link' => $this->CRLink("order-steps-p", [$type, $id, "origin"]),
                    'name' => __("website/osteps/set-origin"),
                ]);

                array_push($steps, [
                    'id'   => "added-basket",
                    'link' => $this->CRLink("order-steps-p", [$type, $id, "basket-added"]),
                    'name' => __("website/osteps/basket-added"),
                ]);

                $htitle = $product["title"];
                $meta['title'] = __("website/osteps/meta-title", ['{product-name}' => $product["title"]]);


                array_push($breadcrumb, [
                    'link'  => $this->CRLink("products", ["sms"]),
                    'title' => ___("constants/category-sms/title"),
                ]);

                array_push($breadcrumb, [
                    'link'  => null,
                    'title' => __("website/osteps/order"),
                ]);

                $this->addData("requirements", $getRequirements);
            }

            if (!$header_background) $header_background = $this->default_header_background();

            Helper::Load("User", "Money");
            $links["step"] = $this->CRLink("order-steps-p", [$type, $id, $step]);

            $this->addData("header_background", $header_background);
            $this->addData("header_title", $htitle);
            $this->addData("breadcrumb", $breadcrumb);

            $this->addData("meta", $meta);
            $this->addData("links", $links);
            $this->addData("steps", $steps);
            if (isset($step1d["selection"])) $this->addData("selected_period", $step1d["selection"]);

            $this->view->chose("website")->render("order-steps-" . $type, $this->data);
        }
    }