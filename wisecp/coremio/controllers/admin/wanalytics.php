<?php
    defined('CORE_FOLDER') or exit('You can not get in here!');

    class Controller extends Controllers
    {
        protected $params, $data = [];
        private $links = [];
        private $page_maps = [
            'overview'  => [],
            'clients'   => [
                'overview',
                'countries',
                'languages',
                'high-trade-volume',
                'credits-available',
                'blocked',
                'non-orders',
            ],
            'sales'     => [
                'overview',
                'product-based',
                'cancelled',
            ],
            'financial' => [
                'cancelled-invoices',
                'refunded-invoices',
                'income-reports',
                'expense-reports',
                'profit-loss-analysis',
                'payment-methods',
                'vat-accrual',
            ],
            'tickets'   => [
                'overview',
                'client-based',
                'product-based',
            ],
        ];


        public function __construct($arg = [])
        {
            parent::__construct();
            $this->params = $arg['params'];

            if (!UserManager::LoginCheck("admin")) {
                Utility::redirect($this->AdminCRLink("sign-in"));
                die();
            }
            Helper::Load("Admin");
            if (!Admin::isPrivilege(Config::get("privileges/WANALYTICS"))) die();
        }


        public function main()
        {
            $routes = $this->params;
            $route_1 = isset($routes[0]) && $routes[0] ? Filter::letters_numbers($routes[0], "\-") : false;
            $route_2 = isset($routes[1]) && $routes[1] ? Filter::letters_numbers($routes[1], "\-") : false;
            $route_3 = isset($routes[2]) && $routes[2] ? Filter::letters_numbers($routes[2], "\-\.") : false;
            $action_func = $route_1 . '_action_' . $route_3;
            $page_func = 'page_' . $route_1;
            if ($route_2)
                $page_func .= '_' . $route_2;
            $action_func = str_replace(['-', '.'], '_', $action_func);
            $page_func = str_replace('-', '_', $page_func);

            if ($route_1 && $route_2 == 'action' && $route_3 && method_exists($this, $action_func)) {
                unset($routes[0]);
                unset($routes[1]);
                unset($routes[2]);
                $params = array_values($routes);
                $this->takeDatas(["language"]);

                return call_user_func([$this, $action_func], $params);
            } elseif ($route_1 && method_exists($this, $page_func)) {
                if ($route_1) unset($routes[0]);
                if ($route_2) unset($routes[1]);
                $params = array_values($routes);

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

                $name_1 = $route_1;
                $name_2 = $route_1 . '-' . $route_2;

                $page_name_1 = __("admin/wanalytics/page-" . $name_1);
                $page_name_2 = __("admin/wanalytics/page-" . $name_2);
                $page_name = $route_2 ? $page_name_2 : $page_name_1;

                $this->addData("meta", ['title' => __("admin/wanalytics/name") . " / " . $page_name]);
                $this->addData("page_title", $page_name);

                $breadcrumb = [
                    [
                        'link'  => $this->AdminCRLink("dashboard"),
                        'title' => __("admin/index/breadcrumb-name"),
                    ],
                    [
                        'link'  => $this->AdminCRLink("wanalytics/overview"),
                        'title' => __("admin/wanalytics/name"),
                    ],
                ];

                if ($route_2)
                    $breadcrumb[] = [
                        'link'  => null,
                        'title' => $page_name_2,
                    ];
                else
                    $breadcrumb[] = [
                        'link'  => null,
                        'title' => $page_name_1,
                    ];

                $this->addData("breadcrumb", $breadcrumb);

                $this->links["base"] = $this->AdminCRLink("wanalytics/");
                $this->links["controller"] = $this->AdminCRLink("wanalytics/" . $route_1 . ($route_2 ? '/' . $route_2 : ''));

                $this->addData("page_maps", $this->page_maps);
                $this->addData("route_1", $route_1);
                $this->addData("route_2", $route_2);

                return call_user_func([$this, $page_func], $params);
            } elseif ($route_1 && $route_2 !== 'action') die("Not Found Page");
            elseif ($route_1 && $route_2 == 'action' && $route_3) die("Not Found Action");
            return false;
        }


        private function page_overview()
        {
            $this->addData("links", $this->links);
            $this->view->chose("admin")->render("wanalytics-overview", $this->data);
        }

        private function overview_action_statistics_json()
        {
            header("Content-Type: application/json");

            $lang = Bootstrap::$lang->clang;

            $output = [];
            $random = 5;
            $online = $random;


            // Set Online Count
            $online = $this->model->get_online_client_count();

            $output["online"] = $online;

            // Set Map
            $countries_count = [];

            $online_client_cities = $this->model->get_online_client_cities($lang);
            if ($online_client_cities) {
                foreach ($online_client_cities as $row) {
                    if (!isset($countries_count[$row["country_code"]]))
                        $countries_count[$row["country_code"]] = $row["count"];
                    else
                        $countries_count[$row["country_code"]] += $row["count"];

                    $latlng = explode(",", $row["latlng"]);
                    $output["country_markers"][] = [
                        "country"   => $row["country_name"],
                        "city"      => $row["city_name"],
                        "count"     => $row["count"],
                        "latitude"  => $latlng[0],
                        "longitude" => $latlng[1],
                    ];
                }
            }

            // Set Center Map
            if ($countries_count && sizeof($countries_count) > 1) {
                $max = max($countries_count);
                $key = array_search($max, $countries_count);

                $get_country_id = AddressManager::get_id_with_cc($key);
                $get_country = AddressManager::getCountry($get_country_id, 't1.latlng');
                if ($get_country) {
                    $latlng = explode(",", $get_country["latlng"]);
                    $output["map_center"] = [
                        'zoom' => 2,
                        'lat'  => $latlng[0],
                        'lng'  => $latlng[1],
                    ];
                }
            } elseif (sizeof($countries_count) == 1) {
                $key = array_keys($countries_count);
                $key = $key[0];

                $get_country_id = AddressManager::get_id_with_cc($key);
                $get_country = AddressManager::getCountry($get_country_id, 't1.latlng');
                if ($get_country) {
                    $latlng = explode(",", $get_country["latlng"]);
                    $output["map_center"] = [
                        'zoom' => 5,
                        'lat'  => $latlng[0],
                        'lng'  => $latlng[1],
                    ];
                }
            } else $output["map_center"] = ['zoom' => 3];

            // Set User List
            $get_online_client_list = $this->model->get_online_client_list($lang);
            if ($get_online_client_list) {
                foreach ($get_online_client_list as $row) {
                    $output["user_list"][] = [
                        'id'          => $row["id"],
                        'name'        => $row["full_name"],
                        'on_page'     => $row["last_visited_page"],
                        'country'     => $row["country_name"],
                        'city'        => $row["city_name"],
                        'detail_link' => $this->AdminCRLink("users-2", ["detail", $row["id"]]),
                    ];
                }
            }

            echo Utility::jencode($output);
        }

        private function page_clients()
        {
            return $this->page_clients_overview();
        }

        private function page_clients_overview()
        {

            $range = Filter::init("GET/range", "letters_numbers", "\-_");
            $from = Filter::init("GET/from", "numbers", "\-");
            $to = Filter::init("GET/to", "numbers", "\-");
            $chart_view = Filter::init("GET/chart-view", "letters_numbers", "\-_");

            if (!$chart_view) $chart_view = "striped";

            if (!$range) $range = "last_7_days";

            if ($range == "last_7_days" && (!$from || !$to)) {
                $from = DateManager::old_date(['week' => 1], 'Y-m-d');
                $to = DateManager::Now('Y-m-d');
            }

            if ($from && !Validation::isDate($from)) $from = '';
            if ($to && !Validation::isDate($to)) $to = '';

            if ($range == 'custom' && (!$from || !$to)) $range = 'all_times';
            if (!in_array($range, array_keys(__("admin/wanalytics/element-date-range-items")))) die('Range Error');

            $results = $this->model->get_clients_overview($from, $to);

            $result_count = 0;
            $result = [];
            $list = [];

            if ($results && $range == "all_times") {
                $from = $results[0]["date"];
                $to = $results[sizeof($results) - 1]["date"];
            }

            $begin = new DateTime($from);
            $end = new DateTime(DateManager::next_date([$to, 'day' => 1], 'Y-m-d'));

            $monthly_begin = new DateTime(DateManager::format('Y-m-01', $from));
            $monthly_end = new DateTime(DateManager::format('Y-m-t', $to));

            $interval = DateInterval::createFromDateString('1 month');
            $monthly_loop = new DatePeriod($monthly_begin, $interval, $monthly_end);
            $monthly_loop_size = 0;
            if ($monthly_loop) foreach ($monthly_loop as $v) $monthly_loop_size += 1;

            if ($monthly_loop_size == 1 || $monthly_loop_size > 24) $chart_view = 'striped';


            $visible_monthly_view = false;
            if ($chart_view !== 'bar' && $monthly_loop_size > 1 && $monthly_loop_size < 25) $visible_monthly_view = true;

            $this->addData("visible_monthly_view", $visible_monthly_view);


            if ($results) {
                if ($chart_view == 'bar') {
                    foreach ($monthly_loop as $dt) {
                        $mon = ___("date/month-" . strtolower($dt->format("F")));
                        $year = $dt->format("Y");
                        $result[$dt->format("Y-m")] = [
                            'name'  => $mon . " " . $year,
                            'count' => 0,
                        ];
                    }
                    if ($results) {
                        foreach ($results as $row) {
                            $result[DateManager::format("Y-m", $row["date"])]["count"] += $row["count"];
                            $result_count += $row["count"];
                        }
                    }
                    if ($result) foreach ($result as $row) $list[] = $row;
                }
                if ($chart_view == 'striped') {
                    $interval = DateInterval::createFromDateString('1 day');
                    $loop = new DatePeriod($begin, $interval, $end);
                    if ($loop) foreach ($loop as $dt) $result[$dt->format("Y-m-d")] = 0;
                    foreach ($results as $row) {
                        $result[$row["date"]] = $row["count"];
                        $result_count += $row["count"];
                    }
                    if ($result) {
                        foreach ($result as $k => $v) {
                            $mon = ___("date/month-" . strtolower(DateManager::format("F", $k)));
                            $year = DateManager::format("Y", $k);
                            $day = DateManager::format("d", $k);
                            $list[] = [
                                'name'  => $day . ' ' . $mon . ' ' . $year,
                                'count' => $v,
                            ];
                        }
                    }
                }
            }

            $this->addData("result", $result);
            $this->addData("list", $list);
            $this->addData("result_count", $result_count);

            $this->addData("filter_range", $range);
            $this->addData("filter_from", $from);
            $this->addData("filter_to", $to);
            $this->links["controller_filtered"] = $this->links["controller"];
            $this->links["controller_filtered"] .= "?range=" . $range;
            if ($from) $this->links["controller_filtered"] .= "&from=" . $from;
            if ($to) $this->links["controller_filtered"] .= "&to=" . $to;

            $this->addData("filter_chart_view", $chart_view);

            $this->addData("links", $this->links);
            $this->view->chose("admin")->render("wanalytics-clients-overview", $this->data);
        }

        private function page_clients_countries()
        {
            $this->addData("links", $this->links);

            $range = Filter::init("GET/range", "letters_numbers", "\-_");
            $from = Filter::init("GET/from", "numbers", "\-");
            $to = Filter::init("GET/to", "numbers", "\-");
            $country = (int)Filter::init("GET/country", "numbers");
            $chart_view = "countries";

            if (!$range) $range = "last_7_days";

            if ($range == "last_7_days" && (!$from || !$to)) {
                $from = DateManager::old_date(['week' => 1], 'Y-m-d');
                $to = DateManager::Now('Y-m-d');
            }

            if ($from && !Validation::isDate($from)) $from = '';
            if ($to && !Validation::isDate($to)) $to = '';

            if ($range == 'custom' && (!$from || !$to)) $range = 'all_times';
            if (!in_array($range, array_keys(__("admin/wanalytics/element-date-range-items")))) return false;

            $results = $this->model->get_clients_by_countries($from, $to, $country);

            $result_count = 0;
            $result = [];
            $list = [];


            foreach ($results as $row) {
                $country = AddressManager::getCountry($row["country"], "t1.id,t1.a2_iso,t2.name");
                $result[] = [
                    'cc'    => $country["a2_iso"],
                    'name'  => $country["name"],
                    'count' => $row["count"],
                ];
                $list[] = [
                    'name'  => $country["name"],
                    'count' => $row["count"],
                ];
                $result_count += $row["count"];
            }


            $countries = AddressManager::getCountries("t1.id,t2.name", Bootstrap::$lang->clang);
            $current_countries = [];
            $group_countries = $this->model->get_clients_group_countries();
            if ($group_countries) foreach ($group_countries as $row) $current_countries[] = $row["country"];
            foreach ($countries as $k => $v) if (!in_array($v["id"], $current_countries)) unset($countries[$k]);

            Utility::sksort($list, 'count');

            $this->addData("countries", $countries);
            $this->addData("result", $result);
            $this->addData("list", $list);
            $this->addData("result_count", $result_count);


            $this->addData("filter_range", $range);
            $this->addData("filter_from", $from);
            $this->addData("filter_to", $to);
            $this->addData("filter_country", $country);

            $this->addData("filter_chart_view", $chart_view);

            $this->view->chose("admin")->render("wanalytics-clients-countries", $this->data);
        }

        private function page_clients_languages()
        {
            $this->addData("links", $this->links);

            $range = Filter::init("GET/range", "letters_numbers", "\-_");
            $from = Filter::init("GET/from", "numbers", "\-");
            $to = Filter::init("GET/to", "numbers", "\-");
            $language = Filter::init("GET/language", "route");
            $chart_view = "pie";

            if (!$range) $range = "last_7_days";

            if ($range == "last_7_days" && (!$from || !$to)) {
                $from = DateManager::old_date(['week' => 1], 'Y-m-d');
                $to = DateManager::Now('Y-m-d');
            }

            if ($from && !Validation::isDate($from)) $from = '';
            if ($to && !Validation::isDate($to)) $to = '';

            if ($range == 'custom' && (!$from || !$to)) $range = 'all_times';
            if (!in_array($range, array_keys(__("admin/wanalytics/element-date-range-items")))) return false;

            $results = $this->model->get_clients_by_languages($from, $to, $language);

            $result_count = 0;
            $result = [];
            $list = [];

            foreach ($results as $row) $result_count += $row["count"];


            foreach ($results as $row) {
                $count = (int)$row["count"];
                $rate = ($count / $result_count) * 100;
                $rate = number_format($rate, 1, '.', '');
                $result[] = [
                    'name'  => ___("package/name", false, $row["lang"]),
                    'count' => $rate,
                ];
                $list[] = [
                    'name'  => ___("package/name", false, $row["lang"]),
                    'count' => $count,
                    'rate'  => $rate,
                ];
            }

            if ($range == "all_times")
                $from = DateManager::format("Y-m-d", $this->model->db->select("creation_time")->from("users")->where("type", "=", "member")->order_by("id ASC")->build(true)->getObject()->creation_time);


            Utility::sksort($result, 'count');
            Utility::sksort($list, 'count');

            $this->addData("result", $result);
            $this->addData("list", $list);
            $this->addData("result_count", $result_count);

            $this->addData("filter_range", $range);
            $this->addData("filter_from", $from);
            $this->addData("filter_to", $to);
            $this->addData("filter_lang", $language);

            $this->addData("filter_chart_view", $chart_view);

            $this->view->chose("admin")->render("wanalytics-clients-languages", $this->data);
        }

        private function page_clients_high_trade_volume()
        {
            $this->addData("links", $this->links);

            Helper::Load(["Money"]);

            $currency = (int)Filter::init("GET/currency", "numbers");
            if (!$currency) $currency = Config::get("general/currency");


            $results = $this->model->get_clients_by_high_trade_volume($currency);
            $result = [];


            foreach ($results as $row) {
                $name = $row["user_name"];
                $trade_volume = Money::formatter_symbol($row["trade_volume"], $currency);

                $result[] = [
                    'name'         => $name,
                    'trade_volume' => $trade_volume,
                    'detail_link'  => $this->AdminCRLink("users-2", ["detail", $row["id"]]),
                ];
            }


            $this->addData("result", $result);

            $this->addData("filter_currency", $currency);

            $this->view->chose("admin")->render("wanalytics-clients-high-trade-volume", $this->data);
        }

        private function page_clients_credits_available()
        {
            $this->addData("links", $this->links);

            Helper::Load(["Money"]);

            $currency = (int)Filter::init("GET/currency", "numbers");
            if (!$currency) $currency = Config::get("general/currency");

            $results = $this->model->get_clients_by_credits_available($currency);
            $result = [];
            $result_count = 0;
            $result_total = 0;


            foreach ($results as $row) {
                $name = $row["user_name"];
                $result_count += 1;
                $result_total += $row["balance"];
                $result[] = [
                    'name'        => $name,
                    'amount'      => Money::formatter_symbol($row["balance"], $currency),
                    'detail_link' => $this->AdminCRLink("users-2", ["detail", $row["id"]]),
                ];
            }


            $this->addData("result", $result);
            $this->addData("result_count", $result_count);
            $this->addData("result_total", Money::formatter_symbol($result_total, $currency));

            $this->addData("filter_currency", $currency);

            $this->view->chose("admin")->render("wanalytics-clients-credits-available", $this->data);
        }

        private function page_clients_blocked()
        {
            $this->addData("links", $this->links);


            $results = $this->model->get_clients_blocked();
            $result_count = 0;
            $result = [];


            foreach ($results as $row) {
                $name = $row["full_name"];
                $row = array_merge($row, User::getInfo($row["id"], ['notes']));

                $result[] = [
                    'name'        => $name,
                    'notes'       => $row["notes"],
                    'detail_link' => $this->AdminCRLink("users-2", ["detail", $row["id"]]),
                ];
                $result_count += 1;
            }


            $this->addData("result", $result);
            $this->addData("result_count", $result_count);

            $this->view->chose("admin")->render("wanalytics-clients-blocked", $this->data);
        }

        private function page_clients_non_orders()
        {
            $this->addData("links", $this->links);


            $results = $this->model->get_clients_non_orders();
            $result_count = 0;
            $result = [];


            foreach ($results as $row) {
                $name = $row["full_name"];
                $last_login_time = DateManager::format(Config::get("options/date-format") . " - H:i", $row["last_login_time"]);

                $result[] = [
                    'name'            => $name,
                    'last_login_time' => $last_login_time,
                    'detail_link'     => $this->AdminCRLink("users-2", ["detail", $row["id"]]),
                ];
                $result_count += 1;
            }


            $this->addData("result", $result);
            $this->addData("result_count", $result_count);

            $this->view->chose("admin")->render("wanalytics-clients-non-orders", $this->data);
        }

        private function page_sales_overview()
        {

            Helper::Load(["Money"]);

            $range = Filter::init("GET/range", "letters_numbers", "\-_");
            $from = Filter::init("GET/from", "numbers", "\-");
            $to = Filter::init("GET/to", "numbers", "\-");
            $chart_view = Filter::init("GET/chart-view", "letters_numbers", "\-_");
            $currency = (int)Filter::init("GET/currency", "numbers");
            $country = (int)Filter::init("GET/country", "numbers");
            if (!$currency) $currency = Config::get("general/currency");

            if (!$chart_view) $chart_view = "striped";

            if (!$range) $range = "last_7_days";

            if ($range == "last_7_days" && (!$from || !$to)) {
                $from = DateManager::old_date(['week' => 1], 'Y-m-d');
                $to = DateManager::Now('Y-m-d');
            }

            if ($from && !Validation::isDate($from)) $from = '';
            if ($to && !Validation::isDate($to)) $to = '';

            if ($range == 'custom' && (!$from || !$to)) $range = 'all_times';
            if (!in_array($range, array_keys(__("admin/wanalytics/element-date-range-items")))) die("Range Error");

            $results = $this->model->get_sales_overview($from, $to, $currency, $country);


            $result_count = 0;
            $result_total = 0;
            $result = [];
            $list = [];

            if ($results && $range == "all_times") {
                $from = $results[0]["date"];
                $to = $results[sizeof($results) - 1]["date"];
            }

            $begin = new DateTime($from);
            $end = new DateTime(DateManager::next_date([$to, 'day' => 1], 'Y-m-d'));

            $monthly_begin = new DateTime(DateManager::format('Y-m-01', $from));
            $monthly_end = new DateTime(DateManager::format('Y-m-t', $to));

            $interval = DateInterval::createFromDateString('1 month');
            $monthly_loop = new DatePeriod($monthly_begin, $interval, $monthly_end);
            $monthly_loop_size = 0;
            if ($monthly_loop) foreach ($monthly_loop as $v) $monthly_loop_size += 1;

            if ($monthly_loop_size == 1 || $monthly_loop_size > 24) $chart_view = 'striped';


            $visible_monthly_view = false;
            if ($chart_view !== 'bar' && $monthly_loop_size > 1 && $monthly_loop_size < 25) $visible_monthly_view = true;

            $this->addData("visible_monthly_view", $visible_monthly_view);


            if ($results) {
                if ($chart_view == 'bar') {
                    foreach ($monthly_loop as $dt) {
                        $mon = ___("date/month-" . strtolower($dt->format("F")));
                        $year = $dt->format("Y");
                        $result[$dt->format("Y-m")] = [
                            'name'  => $mon . " " . $year,
                            'count' => 0,
                            'total' => 0,
                        ];
                    }
                    if ($results) {
                        foreach ($results as $row) {
                            $result[DateManager::format("Y-m", $row["date"])]["count"] += $row["count"];
                            $result[DateManager::format("Y-m", $row["date"])]["total"] += $row["total"];
                            $result_count += $row["count"];
                            $result_total += $row["total"];
                        }
                    }
                    if ($result) {
                        foreach ($result as $row) {
                            $row["total"] = Money::formatter_symbol($row["total"], $currency);
                            $list[] = $row;
                        }
                    }
                }
                if ($chart_view == 'striped') {
                    $interval = DateInterval::createFromDateString('1 day');
                    $loop = new DatePeriod($begin, $interval, $end);
                    if ($loop) foreach ($loop as $dt) $result[$dt->format("Y-m-d")] = [
                        'count' => 0,
                        'total' => 0,
                    ];
                    foreach ($results as $row) {
                        $result[$row["date"]]['count'] = $row["count"];
                        $result[$row["date"]]['total'] = $row["total"];
                        $result_count += $row["count"];
                        $result_total += $row["total"];
                    }
                    if ($result) {
                        foreach ($result as $k => $v) {
                            $mon = ___("date/month-" . strtolower(DateManager::format("F", $k)));
                            $year = DateManager::format("Y", $k);
                            $day = DateManager::format("d", $k);
                            $list[] = [
                                'name'  => $day . ' ' . $mon . ' ' . $year,
                                'count' => $v["count"],
                                'total' => Money::formatter_symbol($v["total"], $currency),
                            ];
                        }
                    }
                }
            }

            $countries = AddressManager::getCountries("t1.id,t2.name", Bootstrap::$lang->clang);
            $current_countries = [];

            $group_countries = $this->model->get_sales_overview_group_countries(false, false, $currency);
            if ($group_countries) foreach ($group_countries as $row) $current_countries[] = $row["country"];
            foreach ($countries as $k => $v) if (!in_array($v["id"], $current_countries)) unset($countries[$k]);

            $this->addData("countries", $countries);

            $this->addData("result", $result);
            $this->addData("list", $list);
            $this->addData("result_count", $result_count);
            $this->addData("result_total", Money::formatter_symbol($result_total, $currency));


            $this->addData("filter_range", $range);
            $this->addData("filter_from", $from);
            $this->addData("filter_to", $to);
            $this->addData("filter_country", $country);
            $this->addData("filter_currency", $currency);

            $this->links["controller_filtered"] = $this->links["controller"];
            $this->links["controller_filtered"] .= "?range=" . $range;
            if ($from) $this->links["controller_filtered"] .= "&from=" . $from;
            if ($to) $this->links["controller_filtered"] .= "&to=" . $to;
            if ($country) $this->links["controller_filtered"] .= "&country=" . $country;
            if ($currency) $this->links["controller_filtered"] .= "&currency=" . $currency;

            $this->addData("filter_chart_view", $chart_view);

            $this->addData("links", $this->links);
            $this->view->chose("admin")->render("wanalytics-sales-overview", $this->data);
        }

        private function page_sales_product_based()
        {

            Helper::Load(["Money", "Products"]);

            $range = Filter::init("GET/range", "letters_numbers", "\-_");
            $from = Filter::init("GET/from", "numbers", "\-");
            $to = Filter::init("GET/to", "numbers", "\-");
            $chart_view = Filter::init("GET/chart-view", "letters_numbers", "\-_");
            $currency = (int)Filter::init("GET/currency", "numbers");
            $country = (int)Filter::init("GET/country", "numbers");
            if (!$currency) $currency = Config::get("general/currency");
            $s_product = Filter::init("GET/product", "route", '\/');
            $s_period = Filter::init("GET/period", "route");

            $product = [];
            if ($s_product) {
                $product_ex = explode("/", $s_product);
                $product_type = isset($product_ex[0]) ? Filter::letters($product_ex[0]) : false;
                $product_id = isset($product_ex[1]) ? (int)$product_ex[1] : false;
                $product = Products::get($product_type, $product_id);
                if (!$product) $s_product = '';
            }

            $period = [];

            if ($s_period == 'monthly') $period = ['period' => 'month', 'time' => 1];
            elseif ($s_period == '3_monthly') $period = ['period' => 'month', 'time' => 3];
            elseif ($s_period == '6_monthly') $period = ['period' => 'month', 'time' => 6];
            elseif ($s_period == 'yearly') $period = ['period' => 'year', 'time' => 1];
            elseif ($s_period == '2_yearly') $period = ['period' => 'year', 'time' => 2];
            elseif ($s_period == '3_yearly') $period = ['period' => 'year', 'time' => 3];
            elseif ($s_period == 'onetime') $period = ['period' => 'none', 'time' => 1];


            if (!$chart_view) $chart_view = "striped";

            if (!$range) $range = "last_7_days";

            if ($range == "last_7_days" && (!$from || !$to)) {
                $from = DateManager::old_date(['week' => 1], 'Y-m-d');
                $to = DateManager::Now('Y-m-d');
            }

            if ($from && !Validation::isDate($from)) $from = '';
            if ($to && !Validation::isDate($to)) $to = '';

            if ($range == 'custom' && (!$from || !$to)) $range = 'all_times';
            if (!in_array($range, array_keys(__("admin/wanalytics/element-date-range-items")))) die("Range Error");

            $results = $this->model->get_sales_product_based($from, $to, $currency, $country, $product, $period);


            $result_count = 0;
            $result_total = 0;
            $result = [];
            $list = [];

            if ($results && $range == "all_times") {
                $from = $results[0]["date"];
                $to = $results[sizeof($results) - 1]["date"];
            }

            $begin = new DateTime($from);
            $end = new DateTime(DateManager::next_date([$to, 'day' => 1], 'Y-m-d'));

            $monthly_begin = new DateTime(DateManager::format('Y-m-01', $from));
            $monthly_end = new DateTime(DateManager::format('Y-m-t', $to));

            $interval = DateInterval::createFromDateString('1 month');
            $monthly_loop = new DatePeriod($monthly_begin, $interval, $monthly_end);
            $monthly_loop_size = 0;
            if ($monthly_loop) foreach ($monthly_loop as $v) $monthly_loop_size += 1;

            if ($monthly_loop_size == 1 || $monthly_loop_size > 24) $chart_view = 'striped';


            $visible_monthly_view = false;
            if ($chart_view !== 'bar' && $monthly_loop_size > 1 && $monthly_loop_size < 25) $visible_monthly_view = true;

            $this->addData("visible_monthly_view", $visible_monthly_view);


            if ($results) {
                if ($chart_view == 'bar') {
                    foreach ($monthly_loop as $dt) {
                        $mon = ___("date/month-" . strtolower($dt->format("F")));
                        $year = $dt->format("Y");
                        $result[$dt->format("Y-m")] = [
                            'name'  => $mon . " " . $year,
                            'count' => 0,
                            'total' => 0,
                        ];
                    }
                    if ($results) {
                        foreach ($results as $row) {
                            $result[DateManager::format("Y-m", $row["date"])]["count"] += $row["count"];
                            $result[DateManager::format("Y-m", $row["date"])]["total"] += $row["total"];
                            $result_count += $row["count"];
                            $result_total += $row["total"];
                        }
                    }
                    if ($result) {
                        foreach ($result as $row) {
                            $row["total"] = Money::formatter_symbol($row["total"], $currency);
                            $list[] = $row;
                        }
                    }
                }
                if ($chart_view == 'striped') {
                    $interval = DateInterval::createFromDateString('1 day');
                    $loop = new DatePeriod($begin, $interval, $end);
                    if ($loop) foreach ($loop as $dt) $result[$dt->format("Y-m-d")] = [
                        'count' => 0,
                        'total' => 0,
                    ];
                    foreach ($results as $row) {
                        $result[$row["date"]]['count'] = $row["count"];
                        $result[$row["date"]]['total'] = $row["total"];
                        $result_count += $row["count"];
                        $result_total += $row["total"];
                    }
                    if ($result) {
                        foreach ($result as $k => $v) {
                            $mon = ___("date/month-" . strtolower(DateManager::format("F", $k)));
                            $year = DateManager::format("Y", $k);
                            $day = DateManager::format("d", $k);
                            $list[] = [
                                'name'  => $day . ' ' . $mon . ' ' . $year,
                                'count' => $v["count"],
                                'total' => Money::formatter_symbol($v["total"], $currency),
                            ];
                        }
                    }
                }
            }

            $countries = AddressManager::getCountries("t1.id,t2.name", Bootstrap::$lang->clang);
            $current_countries = [];

            $group_countries = $this->model->get_sales_product_based_group_countries(false, false, $currency, false, false);
            if ($group_countries) foreach ($group_countries as $row) $current_countries[] = $row["country"];
            foreach ($countries as $k => $v) if (!in_array($v["id"], $current_countries)) unset($countries[$k]);

            $this->addData("countries", $countries);

            $this->addData("result", $result);
            $this->addData("list", $list);
            $this->addData("result_count", $result_count);
            $this->addData("result_total", Money::formatter_symbol($result_total, $currency));

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


            $this->addData("filter_range", $range);
            $this->addData("filter_from", $from);
            $this->addData("filter_to", $to);
            $this->addData("filter_country", $country);
            $this->addData("filter_currency", $currency);
            $this->addData("s_product", $s_product);
            $this->addData("s_period", $s_period);

            $this->links["controller_filtered"] = $this->links["controller"];
            $this->links["controller_filtered"] .= "?range=" . $range;
            if ($from) $this->links["controller_filtered"] .= "&from=" . $from;
            if ($to) $this->links["controller_filtered"] .= "&to=" . $to;
            if ($country) $this->links["controller_filtered"] .= "&country=" . $country;
            if ($currency) $this->links["controller_filtered"] .= "&currency=" . $currency;
            if ($s_product) $this->links["controller_filtered"] .= "&product=" . $s_product;
            if ($s_period) $this->links["controller_filtered"] .= "&period=" . $s_period;
            $this->addData("filter_chart_view", $chart_view);

            $this->addData("links", $this->links);
            $this->view->chose("admin")->render("wanalytics-sales-product-based", $this->data);
        }

        private function page_sales_cancelled()
        {

            Helper::Load(["Money", "Products"]);

            $range = Filter::init("GET/range", "letters_numbers", "\-_");
            $from = Filter::init("GET/from", "numbers", "\-");
            $to = Filter::init("GET/to", "numbers", "\-");
            $chart_view = Filter::init("GET/chart-view", "letters_numbers", "\-_");
            $currency = (int)Filter::init("GET/currency", "numbers");
            $country = (int)Filter::init("GET/country", "numbers");
            if (!$currency) $currency = Config::get("general/currency");
            $s_product = Filter::init("GET/product", "route", '\/');
            $s_period = Filter::init("GET/period", "route");

            $product = [];
            if ($s_product) {
                $product_ex = explode("/", $s_product);
                $product_type = isset($product_ex[0]) ? Filter::letters($product_ex[0]) : false;
                $product_id = isset($product_ex[1]) ? (int)$product_ex[1] : false;
                $product = Products::get($product_type, $product_id);
                if (!$product) $s_product = '';
            }

            $period = [];

            if ($s_period == 'monthly') $period = ['period' => 'month', 'time' => 1];
            elseif ($s_period == '3_monthly') $period = ['period' => 'month', 'time' => 3];
            elseif ($s_period == '6_monthly') $period = ['period' => 'month', 'time' => 6];
            elseif ($s_period == 'yearly') $period = ['period' => 'year', 'time' => 1];
            elseif ($s_period == '2_yearly') $period = ['period' => 'year', 'time' => 2];
            elseif ($s_period == '3_yearly') $period = ['period' => 'year', 'time' => 3];
            elseif ($s_period == 'onetime') $period = ['period' => 'none', 'time' => 1];


            if (!$chart_view) $chart_view = "striped";

            if (!$range) $range = "last_7_days";

            if ($range == "last_7_days" && (!$from || !$to)) {
                $from = DateManager::old_date(['week' => 1], 'Y-m-d');
                $to = DateManager::Now('Y-m-d');
            }

            if ($from && !Validation::isDate($from)) $from = '';
            if ($to && !Validation::isDate($to)) $to = '';

            if ($range == 'custom' && (!$from || !$to)) $range = 'all_times';
            if (!in_array($range, array_keys(__("admin/wanalytics/element-date-range-items")))) die("Range Error");

            $results = $this->model->get_sales_product_based($from, $to, $currency, $country, $product, $period, 'cancelled');


            $result_count = 0;
            $result_total = 0;
            $result = [];
            $list = [];

            if ($results && $range == "all_times") {
                $from = $results[0]["date"];
                $to = $results[sizeof($results) - 1]["date"];
            }

            $begin = new DateTime($from);
            $end = new DateTime(DateManager::next_date([$to, 'day' => 1], 'Y-m-d'));

            $monthly_begin = new DateTime(DateManager::format('Y-m-01', $from));
            $monthly_end = new DateTime(DateManager::format('Y-m-t', $to));

            $interval = DateInterval::createFromDateString('1 month');
            $monthly_loop = new DatePeriod($monthly_begin, $interval, $monthly_end);
            $monthly_loop_size = 0;
            if ($monthly_loop) foreach ($monthly_loop as $v) $monthly_loop_size += 1;

            if ($monthly_loop_size == 1 || $monthly_loop_size > 24) $chart_view = 'striped';


            $visible_monthly_view = false;
            if ($chart_view !== 'bar' && $monthly_loop_size > 1 && $monthly_loop_size < 25) $visible_monthly_view = true;

            $this->addData("visible_monthly_view", $visible_monthly_view);


            if ($results) {
                if ($chart_view == 'bar') {
                    foreach ($monthly_loop as $dt) {
                        $mon = ___("date/month-" . strtolower($dt->format("F")));
                        $year = $dt->format("Y");
                        $result[$dt->format("Y-m")] = [
                            'name'  => $mon . " " . $year,
                            'count' => 0,
                            'total' => 0,
                        ];
                    }
                    if ($results) {
                        foreach ($results as $row) {
                            $result[DateManager::format("Y-m", $row["date"])]["count"] += $row["count"];
                            $result[DateManager::format("Y-m", $row["date"])]["total"] += $row["total"];
                            $result_count += $row["count"];
                            $result_total += $row["total"];
                        }
                    }
                    if ($result) {
                        foreach ($result as $row) {
                            $row["total"] = Money::formatter_symbol($row["total"], $currency);
                            $list[] = $row;
                        }
                    }
                }
                if ($chart_view == 'striped') {
                    $interval = DateInterval::createFromDateString('1 day');
                    $loop = new DatePeriod($begin, $interval, $end);
                    if ($loop) foreach ($loop as $dt) $result[$dt->format("Y-m-d")] = [
                        'count' => 0,
                        'total' => 0,
                    ];
                    foreach ($results as $row) {
                        $result[$row["date"]]['count'] = $row["count"];
                        $result[$row["date"]]['total'] = $row["total"];
                        $result_count += $row["count"];
                        $result_total += $row["total"];
                    }
                    if ($result) {
                        foreach ($result as $k => $v) {
                            $mon = ___("date/month-" . strtolower(DateManager::format("F", $k)));
                            $year = DateManager::format("Y", $k);
                            $day = DateManager::format("d", $k);
                            $list[] = [
                                'name'  => $day . ' ' . $mon . ' ' . $year,
                                'count' => $v["count"],
                                'total' => Money::formatter_symbol($v["total"], $currency),
                            ];
                        }
                    }
                }
            }

            $countries = AddressManager::getCountries("t1.id,t2.name", Bootstrap::$lang->clang);
            $current_countries = [];

            $group_countries = $this->model->get_sales_product_based_group_countries(false, false, $currency, false, false, 'cancelled');
            if ($group_countries) foreach ($group_countries as $row) $current_countries[] = $row["country"];
            foreach ($countries as $k => $v) if (!in_array($v["id"], $current_countries)) unset($countries[$k]);

            $this->addData("countries", $countries);

            $this->addData("result", $result);
            $this->addData("list", $list);
            $this->addData("result_count", $result_count);
            $this->addData("result_total", Money::formatter_symbol($result_total, $currency));

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

            $this->addData("filter_range", $range);
            $this->addData("filter_from", $from);
            $this->addData("filter_to", $to);
            $this->addData("filter_country", $country);
            $this->addData("filter_currency", $currency);
            $this->addData("s_product", $s_product);
            $this->addData("s_period", $s_period);

            $this->links["controller_filtered"] = $this->links["controller"];
            $this->links["controller_filtered"] .= "?range=" . $range;
            if ($from) $this->links["controller_filtered"] .= "&from=" . $from;
            if ($to) $this->links["controller_filtered"] .= "&to=" . $to;
            if ($country) $this->links["controller_filtered"] .= "&country=" . $country;
            if ($currency) $this->links["controller_filtered"] .= "&currency=" . $currency;
            if ($s_product) $this->links["controller_filtered"] .= "&product=" . $s_product;
            if ($s_period) $this->links["controller_filtered"] .= "&period=" . $s_period;
            $this->addData("filter_chart_view", $chart_view);

            $this->addData("links", $this->links);
            $this->view->chose("admin")->render("wanalytics-sales-cancelled", $this->data);
        }


        private function page_financial_cancelled_invoices()
        {

            Helper::Load(["Money"]);

            $range = Filter::init("GET/range", "letters_numbers", "\-_");
            $from = Filter::init("GET/from", "numbers", "\-");
            $to = Filter::init("GET/to", "numbers", "\-");
            $chart_view = Filter::init("GET/chart-view", "letters_numbers", "\-_");
            $currency = (int)Filter::init("GET/currency", "numbers");
            $country = (int)Filter::init("GET/country", "numbers");
            if (!$currency) $currency = Config::get("general/currency");

            if (!$chart_view) $chart_view = "striped";

            if (!$range) $range = "last_7_days";

            if ($range == "last_7_days" && (!$from || !$to)) {
                $from = DateManager::old_date(['week' => 1], 'Y-m-d');
                $to = DateManager::Now('Y-m-d');
            }

            if ($from && !Validation::isDate($from)) $from = '';
            if ($to && !Validation::isDate($to)) $to = '';

            if ($range == 'custom' && (!$from || !$to)) $range = 'all_times';
            if (!in_array($range, array_keys(__("admin/wanalytics/element-date-range-items")))) die("Range Error");

            $results = $this->model->get_financial_invoices($from, $to, $currency, $country);


            $result_count = 0;
            $result_total = 0;
            $result = [];
            $list = [];

            if ($results && $range == "all_times") {
                $from = $results[0]["date"];
                $to = $results[sizeof($results) - 1]["date"];
            }

            $begin = new DateTime($from);
            $end = new DateTime(DateManager::next_date([$to, 'day' => 1], 'Y-m-d'));

            $monthly_begin = new DateTime(DateManager::format('Y-m-01', $from));
            $monthly_end = new DateTime(DateManager::format('Y-m-t', $to));

            $interval = DateInterval::createFromDateString('1 month');
            $monthly_loop = new DatePeriod($monthly_begin, $interval, $monthly_end);
            $monthly_loop_size = 0;
            if ($monthly_loop) foreach ($monthly_loop as $v) $monthly_loop_size += 1;

            if ($monthly_loop_size == 1 || $monthly_loop_size > 24) $chart_view = 'striped';


            $visible_monthly_view = false;
            if ($chart_view !== 'bar' && $monthly_loop_size > 1 && $monthly_loop_size < 25) $visible_monthly_view = true;

            $this->addData("visible_monthly_view", $visible_monthly_view);


            if ($results) {
                if ($chart_view == 'bar') {
                    foreach ($monthly_loop as $dt) {
                        $mon = ___("date/month-" . strtolower($dt->format("F")));
                        $year = $dt->format("Y");
                        $result[$dt->format("Y-m")] = [
                            'name'  => $mon . " " . $year,
                            'count' => 0,
                            'total' => 0,
                        ];
                    }
                    if ($results) {
                        foreach ($results as $row) {
                            $result[DateManager::format("Y-m", $row["date"])]["count"] += $row["count"];
                            $result[DateManager::format("Y-m", $row["date"])]["total"] += $row["total"];
                            $result_count += $row["count"];
                            $result_total += $row["total"];
                        }
                    }
                    if ($result) {
                        foreach ($result as $row) {
                            $row["total"] = Money::formatter_symbol($row["total"], $currency);
                            $list[] = $row;
                        }
                    }
                }
                if ($chart_view == 'striped') {
                    $interval = DateInterval::createFromDateString('1 day');
                    $loop = new DatePeriod($begin, $interval, $end);
                    if ($loop) foreach ($loop as $dt) $result[$dt->format("Y-m-d")] = [
                        'count' => 0,
                        'total' => 0,
                    ];
                    foreach ($results as $row) {
                        $result[$row["date"]]['count'] = $row["count"];
                        $result[$row["date"]]['total'] = $row["total"];
                        $result_count += $row["count"];
                        $result_total += $row["total"];
                    }
                    if ($result) {
                        foreach ($result as $k => $v) {
                            $mon = ___("date/month-" . strtolower(DateManager::format("F", $k)));
                            $year = DateManager::format("Y", $k);
                            $day = DateManager::format("d", $k);
                            $list[] = [
                                'name'  => $day . ' ' . $mon . ' ' . $year,
                                'count' => $v["count"],
                                'total' => Money::formatter_symbol($v["total"], $currency),
                            ];
                        }
                    }
                }
            }

            $countries = AddressManager::getCountries("t1.id,t2.name", Bootstrap::$lang->clang);
            $current_countries = [];

            $group_countries = $this->model->get_financial_invoices_group_countries($from, $to, $currency);
            if ($group_countries) foreach ($group_countries as $row) $current_countries[] = $row["country"];
            foreach ($countries as $k => $v) if (!in_array($v["id"], $current_countries)) unset($countries[$k]);

            $this->addData("countries", $countries);

            $this->addData("result", $result);
            $this->addData("list", $list);
            $this->addData("result_count", $result_count);
            $this->addData("result_total", Money::formatter_symbol($result_total, $currency));

            $this->addData("filter_range", $range);
            $this->addData("filter_from", $from);
            $this->addData("filter_to", $to);
            $this->addData("filter_country", $country);
            $this->addData("filter_currency", $currency);

            $this->links["controller_filtered"] = $this->links["controller"];
            $this->links["controller_filtered"] .= "?range=" . $range;
            if ($from) $this->links["controller_filtered"] .= "&from=" . $from;
            if ($to) $this->links["controller_filtered"] .= "&to=" . $to;
            if ($country) $this->links["controller_filtered"] .= "&country=" . $country;
            if ($currency) $this->links["controller_filtered"] .= "&currency=" . $currency;
            $this->addData("filter_chart_view", $chart_view);

            $this->addData("links", $this->links);
            $this->view->chose("admin")->render("wanalytics-financial-cancelled-invoices", $this->data);
        }

        private function page_financial_refunded_invoices()
        {

            Helper::Load(["Money"]);

            $range = Filter::init("GET/range", "letters_numbers", "\-_");
            $from = Filter::init("GET/from", "numbers", "\-");
            $to = Filter::init("GET/to", "numbers", "\-");
            $chart_view = Filter::init("GET/chart-view", "letters_numbers", "\-_");
            $currency = (int)Filter::init("GET/currency", "numbers");
            $country = (int)Filter::init("GET/country", "numbers");
            if (!$currency) $currency = Config::get("general/currency");

            if (!$chart_view) $chart_view = "striped";

            if (!$range) $range = "last_7_days";

            if ($range == "last_7_days" && (!$from || !$to)) {
                $from = DateManager::old_date(['week' => 1], 'Y-m-d');
                $to = DateManager::Now('Y-m-d');
            }

            if ($from && !Validation::isDate($from)) $from = '';
            if ($to && !Validation::isDate($to)) $to = '';

            if ($range == 'custom' && (!$from || !$to)) $range = 'all_times';
            if (!in_array($range, array_keys(__("admin/wanalytics/element-date-range-items")))) die("Range Error");

            $results = $this->model->get_financial_invoices($from, $to, $currency, $country, 'refund');


            $result_count = 0;
            $result_total = 0;
            $result = [];
            $list = [];

            if ($results && $range == "all_times") {
                $from = $results[0]["date"];
                $to = $results[sizeof($results) - 1]["date"];
            }

            $begin = new DateTime($from);
            $end = new DateTime(DateManager::next_date([$to, 'day' => 1], 'Y-m-d'));

            $monthly_begin = new DateTime(DateManager::format('Y-m-01', $from));
            $monthly_end = new DateTime(DateManager::format('Y-m-t', $to));

            $interval = DateInterval::createFromDateString('1 month');
            $monthly_loop = new DatePeriod($monthly_begin, $interval, $monthly_end);
            $monthly_loop_size = 0;
            if ($monthly_loop) foreach ($monthly_loop as $v) $monthly_loop_size += 1;

            if ($monthly_loop_size == 1 || $monthly_loop_size > 24) $chart_view = 'striped';


            $visible_monthly_view = false;
            if ($chart_view !== 'bar' && $monthly_loop_size > 1 && $monthly_loop_size < 25) $visible_monthly_view = true;

            $this->addData("visible_monthly_view", $visible_monthly_view);


            if ($results) {
                if ($chart_view == 'bar') {
                    foreach ($monthly_loop as $dt) {
                        $mon = ___("date/month-" . strtolower($dt->format("F")));
                        $year = $dt->format("Y");
                        $result[$dt->format("Y-m")] = [
                            'name'  => $mon . " " . $year,
                            'count' => 0,
                            'total' => 0,
                        ];
                    }
                    if ($results) {
                        foreach ($results as $row) {
                            $result[DateManager::format("Y-m", $row["date"])]["count"] += $row["count"];
                            $result[DateManager::format("Y-m", $row["date"])]["total"] += $row["total"];
                            $result_count += $row["count"];
                            $result_total += $row["total"];
                        }
                    }
                    if ($result) {
                        foreach ($result as $row) {
                            $row["total"] = Money::formatter_symbol($row["total"], $currency);
                            $list[] = $row;
                        }
                    }
                }
                if ($chart_view == 'striped') {
                    $interval = DateInterval::createFromDateString('1 day');
                    $loop = new DatePeriod($begin, $interval, $end);
                    if ($loop) foreach ($loop as $dt) $result[$dt->format("Y-m-d")] = [
                        'count' => 0,
                        'total' => 0,
                    ];
                    foreach ($results as $row) {
                        $result[$row["date"]]['count'] = $row["count"];
                        $result[$row["date"]]['total'] = $row["total"];
                        $result_count += $row["count"];
                        $result_total += $row["total"];
                    }
                    if ($result) {
                        foreach ($result as $k => $v) {
                            $mon = ___("date/month-" . strtolower(DateManager::format("F", $k)));
                            $year = DateManager::format("Y", $k);
                            $day = DateManager::format("d", $k);
                            $list[] = [
                                'name'  => $day . ' ' . $mon . ' ' . $year,
                                'count' => $v["count"],
                                'total' => Money::formatter_symbol($v["total"], $currency),
                            ];
                        }
                    }
                }
            }

            $countries = AddressManager::getCountries("t1.id,t2.name", Bootstrap::$lang->clang);
            $current_countries = [];

            $group_countries = $this->model->get_financial_invoices_group_countries(false, false, $currency, 'refund');
            if ($group_countries) foreach ($group_countries as $row) $current_countries[] = $row["country"];
            foreach ($countries as $k => $v) if (!in_array($v["id"], $current_countries)) unset($countries[$k]);

            $this->addData("countries", $countries);

            $this->addData("result", $result);
            $this->addData("list", $list);
            $this->addData("result_count", $result_count);
            $this->addData("result_total", Money::formatter_symbol($result_total, $currency));

            $this->addData("filter_range", $range);
            $this->addData("filter_from", $from);
            $this->addData("filter_to", $to);
            $this->addData("filter_country", $country);
            $this->addData("filter_currency", $currency);

            $this->links["controller_filtered"] = $this->links["controller"];
            $this->links["controller_filtered"] .= "?range=" . $range;
            if ($from) $this->links["controller_filtered"] .= "&from=" . $from;
            if ($to) $this->links["controller_filtered"] .= "&to=" . $to;
            if ($country) $this->links["controller_filtered"] .= "&country=" . $country;
            if ($currency) $this->links["controller_filtered"] .= "&currency=" . $currency;
            $this->addData("filter_chart_view", $chart_view);

            $this->addData("links", $this->links);
            $this->view->chose("admin")->render("wanalytics-financial-refunded-invoices", $this->data);
        }

        private function page_financial_income_reports()
        {

            Helper::Load(["Money"]);

            $range = Filter::init("GET/range", "letters_numbers", "\-_");
            $from = Filter::init("GET/from", "numbers", "\-");
            $to = Filter::init("GET/to", "numbers", "\-");
            $chart_view = Filter::init("GET/chart-view", "letters_numbers", "\-_");
            $currency = (int)Filter::init("GET/currency", "numbers");
            if (!$currency) $currency = Config::get("general/currency");

            if (!$chart_view) $chart_view = "striped";

            if (!$range) $range = "last_7_days";

            if ($range == "last_7_days" && (!$from || !$to)) {
                $from = DateManager::old_date(['week' => 1], 'Y-m-d');
                $to = DateManager::Now('Y-m-d');
            }

            if ($from && !Validation::isDate($from)) $from = '';
            if ($to && !Validation::isDate($to)) $to = '';

            if ($range == 'custom' && (!$from || !$to)) $range = 'all_times';
            if (!in_array($range, array_keys(__("admin/wanalytics/element-date-range-items")))) die("Range Error");

            $results = $this->model->get_financial_inex_reports('income', $from, $to, $currency);


            $result_count = 0;
            $result_total = 0;
            $result = [];
            $list = [];

            if ($results && $range == "all_times") {
                $from = $results[0]["date"];
                $to = $results[sizeof($results) - 1]["date"];
            }

            $begin = new DateTime($from);
            $end = new DateTime(DateManager::next_date([$to, 'day' => 1], 'Y-m-d'));

            $monthly_begin = new DateTime(DateManager::format('Y-m-01', $from));
            $monthly_end = new DateTime(DateManager::format('Y-m-t', $to));

            $interval = DateInterval::createFromDateString('1 month');
            $monthly_loop = new DatePeriod($monthly_begin, $interval, $monthly_end);
            $monthly_loop_size = 0;
            if ($monthly_loop) foreach ($monthly_loop as $v) $monthly_loop_size += 1;

            if ($monthly_loop_size == 1 || $monthly_loop_size > 24) $chart_view = 'striped';


            $visible_monthly_view = false;
            if ($chart_view !== 'bar' && $monthly_loop_size > 1 && $monthly_loop_size < 25) $visible_monthly_view = true;

            $this->addData("visible_monthly_view", $visible_monthly_view);


            if ($results) {
                if ($chart_view == 'bar') {
                    foreach ($monthly_loop as $dt) {
                        $mon = ___("date/month-" . strtolower($dt->format("F")));
                        $year = $dt->format("Y");
                        $result[$dt->format("Y-m")] = [
                            'name'  => $mon . " " . $year,
                            'count' => 0,
                            'total' => 0,
                        ];
                    }
                    if ($results) {
                        foreach ($results as $row) {
                            $result[DateManager::format("Y-m", $row["date"])]["count"] += $row["count"];
                            $result[DateManager::format("Y-m", $row["date"])]["total"] += $row["total"];
                            $result_count += $row["count"];
                            $result_total += $row["total"];
                        }
                    }
                    if ($result) {
                        foreach ($result as $row) {
                            $row["total"] = Money::formatter_symbol($row["total"], $currency);
                            $list[] = $row;
                        }
                    }
                }
                if ($chart_view == 'striped') {
                    $interval = DateInterval::createFromDateString('1 day');
                    $loop = new DatePeriod($begin, $interval, $end);
                    if ($loop) foreach ($loop as $dt) $result[$dt->format("Y-m-d")] = [
                        'count' => 0,
                        'total' => 0,
                    ];
                    foreach ($results as $row) {
                        $result[$row["date"]]['count'] = $row["count"];
                        $result[$row["date"]]['total'] = $row["total"];
                        $result_count += $row["count"];
                        $result_total += $row["total"];
                    }
                    if ($result) {
                        foreach ($result as $k => $v) {
                            $mon = ___("date/month-" . strtolower(DateManager::format("F", $k)));
                            $year = DateManager::format("Y", $k);
                            $day = DateManager::format("d", $k);
                            $list[] = [
                                'name'  => $day . ' ' . $mon . ' ' . $year,
                                'count' => $v["count"],
                                'total' => Money::formatter_symbol($v["total"], $currency),
                            ];
                        }
                    }
                }
            }

            $this->addData("result", $result);
            $this->addData("list", $list);
            $this->addData("result_count", $result_count);
            $this->addData("result_total", Money::formatter_symbol($result_total, $currency));

            $this->addData("filter_range", $range);
            $this->addData("filter_from", $from);
            $this->addData("filter_to", $to);
            $this->addData("filter_currency", $currency);

            $this->links["controller_filtered"] = $this->links["controller"];
            $this->links["controller_filtered"] .= "?range=" . $range;
            if ($from) $this->links["controller_filtered"] .= "&from=" . $from;
            if ($to) $this->links["controller_filtered"] .= "&to=" . $to;
            if ($currency) $this->links["controller_filtered"] .= "&currency=" . $currency;
            $this->addData("filter_chart_view", $chart_view);

            $this->addData("links", $this->links);
            $this->view->chose("admin")->render("wanalytics-financial-income-reports", $this->data);
        }

        private function page_financial_expense_reports()
        {

            Helper::Load(["Money"]);

            $range = Filter::init("GET/range", "letters_numbers", "\-_");
            $from = Filter::init("GET/from", "numbers", "\-");
            $to = Filter::init("GET/to", "numbers", "\-");
            $chart_view = Filter::init("GET/chart-view", "letters_numbers", "\-_");
            $currency = (int)Filter::init("GET/currency", "numbers");
            if (!$currency) $currency = Config::get("general/currency");

            if (!$chart_view) $chart_view = "striped";

            if (!$range) $range = "last_7_days";

            if ($range == "last_7_days" && (!$from || !$to)) {
                $from = DateManager::old_date(['week' => 1], 'Y-m-d');
                $to = DateManager::Now('Y-m-d');
            }

            if ($from && !Validation::isDate($from)) $from = '';
            if ($to && !Validation::isDate($to)) $to = '';

            if ($range == 'custom' && (!$from || !$to)) $range = 'all_times';
            if (!in_array($range, array_keys(__("admin/wanalytics/element-date-range-items")))) die("Range Error");


            $results = $this->model->get_financial_inex_reports('expense', $from, $to, $currency);


            $result_count = 0;
            $result_total = 0;
            $result = [];
            $list = [];

            if ($results && $range == "all_times") {
                $from = $results[0]["date"];
                $to = $results[sizeof($results) - 1]["date"];
            }

            $begin = new DateTime($from);
            $end = new DateTime(DateManager::next_date([$to, 'day' => 1], 'Y-m-d'));

            $monthly_begin = new DateTime(DateManager::format('Y-m-01', $from));
            $monthly_end = new DateTime(DateManager::format('Y-m-t', $to));

            $interval = DateInterval::createFromDateString('1 month');
            $monthly_loop = new DatePeriod($monthly_begin, $interval, $monthly_end);
            $monthly_loop_size = 0;
            if ($monthly_loop) foreach ($monthly_loop as $v) $monthly_loop_size += 1;

            if ($monthly_loop_size == 1 || $monthly_loop_size > 24) $chart_view = 'striped';


            $visible_monthly_view = false;
            if ($chart_view !== 'bar' && $monthly_loop_size > 1 && $monthly_loop_size < 25) $visible_monthly_view = true;

            $this->addData("visible_monthly_view", $visible_monthly_view);


            if ($results) {
                if ($chart_view == 'bar') {
                    foreach ($monthly_loop as $dt) {
                        $mon = ___("date/month-" . strtolower($dt->format("F")));
                        $year = $dt->format("Y");
                        $result[$dt->format("Y-m")] = [
                            'name'  => $mon . " " . $year,
                            'count' => 0,
                            'total' => 0,
                        ];
                    }
                    if ($results) {
                        foreach ($results as $row) {
                            $result[DateManager::format("Y-m", $row["date"])]["count"] += $row["count"];
                            $result[DateManager::format("Y-m", $row["date"])]["total"] += $row["total"];
                            $result_count += $row["count"];
                            $result_total += $row["total"];
                        }
                    }
                    if ($result) {
                        foreach ($result as $row) {
                            $row["total"] = Money::formatter_symbol($row["total"], $currency);
                            $list[] = $row;
                        }
                    }
                }
                if ($chart_view == 'striped') {
                    $interval = DateInterval::createFromDateString('1 day');
                    $loop = new DatePeriod($begin, $interval, $end);
                    if ($loop) foreach ($loop as $dt) $result[$dt->format("Y-m-d")] = [
                        'count' => 0,
                        'total' => 0,
                    ];
                    foreach ($results as $row) {
                        $result[$row["date"]]['count'] = $row["count"];
                        $result[$row["date"]]['total'] = $row["total"];
                        $result_count += $row["count"];
                        $result_total += $row["total"];
                    }
                    if ($result) {
                        foreach ($result as $k => $v) {
                            $mon = ___("date/month-" . strtolower(DateManager::format("F", $k)));
                            $year = DateManager::format("Y", $k);
                            $day = DateManager::format("d", $k);
                            $list[] = [
                                'name'  => $day . ' ' . $mon . ' ' . $year,
                                'count' => $v["count"],
                                'total' => Money::formatter_symbol($v["total"], $currency),
                            ];
                        }
                    }
                }
            }

            $this->addData("result", $result);
            $this->addData("list", $list);
            $this->addData("result_count", $result_count);
            $this->addData("result_total", Money::formatter_symbol($result_total, $currency));

            $this->addData("filter_range", $range);
            $this->addData("filter_from", $from);
            $this->addData("filter_to", $to);
            $this->addData("filter_currency", $currency);

            $this->links["controller_filtered"] = $this->links["controller"];
            $this->links["controller_filtered"] .= "?range=" . $range;
            if ($from) $this->links["controller_filtered"] .= "&from=" . $from;
            if ($to) $this->links["controller_filtered"] .= "&to=" . $to;
            if ($currency) $this->links["controller_filtered"] .= "&currency=" . $currency;
            $this->addData("filter_chart_view", $chart_view);

            $this->addData("links", $this->links);
            $this->view->chose("admin")->render("wanalytics-financial-expense-reports", $this->data);
        }

        private function page_financial_profit_loss_analysis()
        {

            Helper::Load(["Money"]);

            $range = Filter::init("GET/range", "letters_numbers", "\-_");
            $from = Filter::init("GET/from", "numbers", "\-");
            $to = Filter::init("GET/to", "numbers", "\-");
            $chart_view = Filter::init("GET/chart-view", "letters_numbers", "\-_");
            $currency = (int)Filter::init("GET/currency", "numbers");
            if (!$currency) $currency = Config::get("general/currency");

            if (!$chart_view) $chart_view = "striped";

            if (!$range) $range = "last_7_days";

            if ($range == "last_7_days" && (!$from || !$to)) {
                $from = DateManager::old_date(['week' => 1], 'Y-m-d');
                $to = DateManager::Now('Y-m-d');
            }

            if ($from && !Validation::isDate($from)) $from = '';
            if ($to && !Validation::isDate($to)) $to = '';

            if ($range == 'custom' && (!$from || !$to)) $range = 'all_times';
            if (!in_array($range, array_keys(__("admin/wanalytics/element-date-range-items")))) die("Range Error");

            $results = $this->model->get_financial_profit_loss_analysis($from, $to, $currency);

            $result_count = 0;
            $result_total = 0;
            $result = [];
            $list = [];

            if ($results && $range == "all_times") {
                $from = $results[0]["date"];
                $to = $results[sizeof($results) - 1]["date"];
            }

            $begin = new DateTime($from);
            $end = new DateTime(DateManager::next_date([$to, 'day' => 1], 'Y-m-d'));

            $monthly_begin = new DateTime(DateManager::format('Y-m-01', $from));
            $monthly_end = new DateTime(DateManager::format('Y-m-t', $to));

            $interval = DateInterval::createFromDateString('1 month');
            $monthly_loop = new DatePeriod($monthly_begin, $interval, $monthly_end);
            $monthly_loop_size = 0;
            if ($monthly_loop) foreach ($monthly_loop as $v) $monthly_loop_size += 1;

            if ($monthly_loop_size == 1 || $monthly_loop_size > 24) $chart_view = 'striped';


            $visible_monthly_view = false;
            if ($chart_view !== 'bar' && $monthly_loop_size > 1 && $monthly_loop_size < 25) $visible_monthly_view = true;

            $this->addData("visible_monthly_view", $visible_monthly_view);


            if ($results) {
                if ($chart_view == 'bar') {
                    foreach ($monthly_loop as $dt) {
                        $mon = ___("date/month-" . strtolower($dt->format("F")));
                        $year = $dt->format("Y");
                        $result[$dt->format("Y-m")] = [
                            'count'         => 0,
                            'name'          => $mon . " " . $year,
                            'income'        => 0,
                            'income_count'  => 0,
                            'expense'       => 0,
                            'expense_count' => 0,
                            'total'         => 0,
                        ];
                    }
                    if ($results) {
                        foreach ($results as $row) {
                            $result[DateManager::format("Y-m", $row["date"])]["income"] += $row["income"];
                            $result[DateManager::format("Y-m", $row["date"])]["income_count"] += $row["income_count"];
                            $result[DateManager::format("Y-m", $row["date"])]["expense"] += $row["income"];
                            $result[DateManager::format("Y-m", $row["date"])]["expense_count"] += $row["expense_count"];
                            $row_total = abs($row["total"]);
                            if ($row["total"] < 0) {
                                $result_total -= $row_total;
                                $result[DateManager::format("Y-m", $row["date"])]["total"] -= $row_total;
                            } else {
                                $result_total += $row["total"];
                                $result[DateManager::format("Y-m", $row["date"])]["total"] += $row_total;
                            }
                        }
                    }
                    if ($result) {
                        foreach ($result as $row) {
                            $row["income_format"] = Money::formatter_symbol($row["income"], $currency);
                            $row["expense_format"] = Money::formatter_symbol($row["expense"], $currency);
                            $row["total_format"] = Money::formatter_symbol($row["total"], $currency);
                            $list[] = $row;
                        }
                    }
                }
                if ($chart_view == 'striped') {
                    $interval = DateInterval::createFromDateString('1 day');
                    $loop = new DatePeriod($begin, $interval, $end);
                    if ($loop)
                        foreach ($loop as $dt) $result[$dt->format("Y-m-d")] = [
                            'income'        => 0,
                            'income_count'  => 0,
                            'expense'       => 0,
                            'expense_count' => 0,
                            'total'         => 0,
                        ];

                    foreach ($results as $row) {
                        $result[$row["date"]]['income'] = $row["income"];
                        $result[$row["date"]]['income_count'] = $row["income_count"];
                        $result[$row["date"]]['expense'] = $row["expense"];
                        $result[$row["date"]]['expense_count'] = $row["expense_count"];
                        $result[$row["date"]]['total'] = $row["total"];
                        if ($row["total"] < 0) $result_total -= abs($row["total"]);
                        else $result_total += $row["total"];
                    }
                    if ($result) {
                        foreach ($result as $k => $v) {
                            $mon = ___("date/month-" . strtolower(DateManager::format("F", $k)));
                            $year = DateManager::format("Y", $k);
                            $day = DateManager::format("d", $k);
                            $list[] = [
                                'name'           => $day . ' ' . $mon . ' ' . $year,
                                'income_count'   => $v["income_count"],
                                'expense_count'  => $v["expense_count"],
                                'income'         => $v["income"],
                                'expense'        => $v["expense"],
                                'total'          => $v["total"],
                                'income_format'  => Money::formatter_symbol($v["income"], $currency),
                                'expense_format' => Money::formatter_symbol($v["expense"], $currency),
                                'total_format'   => Money::formatter_symbol($v["total"], $currency),
                            ];
                        }
                    }
                }
            }

            $this->addData("result", $result);
            $this->addData("list", $list);
            $this->addData("result_count", $result_count);
            $this->addData("result_total_type", $result_total < 0 ? "minus" : "plus");
            $this->addData("result_total", Money::formatter_symbol(abs($result_total), $currency));

            $this->addData("filter_range", $range);
            $this->addData("filter_from", $from);
            $this->addData("filter_to", $to);
            $this->addData("filter_currency", $currency);

            $this->links["controller_filtered"] = $this->links["controller"];
            $this->links["controller_filtered"] .= "?range=" . $range;
            if ($from) $this->links["controller_filtered"] .= "&from=" . $from;
            if ($to) $this->links["controller_filtered"] .= "&to=" . $to;
            if ($currency) $this->links["controller_filtered"] .= "&currency=" . $currency;
            $this->addData("filter_chart_view", $chart_view);

            $this->addData("links", $this->links);
            $this->view->chose("admin")->render("wanalytics-financial-profit-loss-analysis", $this->data);
        }

        private function page_financial_payment_methods()
        {

            Helper::Load(["Money"]);

            $range = Filter::init("GET/range", "letters_numbers", "\-_");
            $from = Filter::init("GET/from", "numbers", "\-");
            $to = Filter::init("GET/to", "numbers", "\-");
            $chart_view = Filter::init("GET/chart-view", "letters_numbers", "\-_");
            $currency = (int)Filter::init("GET/currency", "numbers");
            if (!$currency) $currency = Config::get("general/currency");
            $method = Filter::init("GET/method", "route");

            if (!$chart_view) $chart_view = "striped";

            if (!$range) $range = "last_7_days";

            if ($range == "last_7_days" && (!$from || !$to)) {
                $from = DateManager::old_date(['week' => 1], 'Y-m-d');
                $to = DateManager::Now('Y-m-d');
            }

            if ($from && !Validation::isDate($from)) $from = '';
            if ($to && !Validation::isDate($to)) $to = '';

            if ($range == 'custom' && (!$from || !$to)) $range = 'all_times';
            if (!in_array($range, array_keys(__("admin/wanalytics/element-date-range-items")))) die("Range Error");

            $results = $this->model->get_financial_invoices($from, $to, $currency, false, 'paid', $method);


            $result_count = 0;
            $result_total = 0;
            $result = [];
            $list = [];

            if ($results && $range == "all_times") {
                $from = $results[0]["date"];
                $to = $results[sizeof($results) - 1]["date"];
            }

            $begin = new DateTime($from);
            $end = new DateTime(DateManager::next_date([$to, 'day' => 1], 'Y-m-d'));

            $monthly_begin = new DateTime(DateManager::format('Y-m-01', $from));
            $monthly_end = new DateTime(DateManager::format('Y-m-t', $to));

            $interval = DateInterval::createFromDateString('1 month');
            $monthly_loop = new DatePeriod($monthly_begin, $interval, $monthly_end);
            $monthly_loop_size = 0;
            if ($monthly_loop) foreach ($monthly_loop as $v) $monthly_loop_size += 1;

            if ($monthly_loop_size == 1 || $monthly_loop_size > 24) $chart_view = 'striped';


            $visible_monthly_view = false;
            if ($chart_view !== 'bar' && $monthly_loop_size > 1 && $monthly_loop_size < 25) $visible_monthly_view = true;

            $this->addData("visible_monthly_view", $visible_monthly_view);


            if ($results) {
                if ($chart_view == 'bar') {
                    foreach ($monthly_loop as $dt) {
                        $mon = ___("date/month-" . strtolower($dt->format("F")));
                        $year = $dt->format("Y");
                        $result[$dt->format("Y-m")] = [
                            'name'  => $mon . " " . $year,
                            'count' => 0,
                            'total' => 0,
                        ];
                    }
                    if ($results) {
                        foreach ($results as $row) {
                            $result[DateManager::format("Y-m", $row["date"])]["count"] += $row["count"];
                            $result[DateManager::format("Y-m", $row["date"])]["total"] += $row["total"];
                            $result_count += $row["count"];
                            $result_total += $row["total"];
                        }
                    }
                    if ($result) {
                        foreach ($result as $row) {
                            $row["total"] = Money::formatter_symbol($row["total"], $currency);
                            $list[] = $row;
                        }
                    }
                }
                if ($chart_view == 'striped') {
                    $interval = DateInterval::createFromDateString('1 day');
                    $loop = new DatePeriod($begin, $interval, $end);
                    if ($loop) foreach ($loop as $dt) $result[$dt->format("Y-m-d")] = [
                        'count' => 0,
                        'total' => 0,
                    ];
                    foreach ($results as $row) {
                        $result[$row["date"]]['count'] = $row["count"];
                        $result[$row["date"]]['total'] = $row["total"];
                        $result_count += $row["count"];
                        $result_total += $row["total"];
                    }
                    if ($result) {
                        foreach ($result as $k => $v) {
                            $mon = ___("date/month-" . strtolower(DateManager::format("F", $k)));
                            $year = DateManager::format("Y", $k);
                            $day = DateManager::format("d", $k);
                            $list[] = [
                                'name'  => $day . ' ' . $mon . ' ' . $year,
                                'count' => $v["count"],
                                'total' => Money::formatter_symbol($v["total"], $currency),
                            ];
                        }
                    }
                }
            }

            $modules = Modules::Load("Payment", 'All', true);

            if ($modules) {
                $methods = [];
                foreach ($modules as $k => $v) $methods[$k] = $v["lang"]["name"] ?? $v["config"]["meta"]["name"];
                $this->addData("methods", $methods);
            }


            $this->addData("result", $result);
            $this->addData("list", $list);
            $this->addData("result_count", $result_count);
            $this->addData("result_total", Money::formatter_symbol($result_total, $currency));

            $this->addData("filter_range", $range);
            $this->addData("filter_from", $from);
            $this->addData("filter_to", $to);
            $this->addData("filter_currency", $currency);
            $this->addData("method", $method);

            $this->links["controller_filtered"] = $this->links["controller"];
            $this->links["controller_filtered"] .= "?range=" . $range;
            if ($from) $this->links["controller_filtered"] .= "&from=" . $from;
            if ($to) $this->links["controller_filtered"] .= "&to=" . $to;
            if ($currency) $this->links["controller_filtered"] .= "&currency=" . $currency;
            $this->addData("filter_chart_view", $chart_view);

            $this->addData("links", $this->links);
            $this->view->chose("admin")->render("wanalytics-financial-payment-methods", $this->data);
        }

        private function page_financial_vat_accrual()
        {

            Helper::Load(["Money"]);

            $range = Filter::init("GET/range", "letters_numbers", "\-_");
            $from = Filter::init("GET/from", "numbers", "\-");
            $to = Filter::init("GET/to", "numbers", "\-");
            $chart_view = Filter::init("GET/chart-view", "letters_numbers", "\-_");
            $currency = (int)Filter::init("GET/currency", "numbers");
            if (!$currency) $currency = Config::get("general/currency");

            if (!$chart_view) $chart_view = "striped";

            if (!$range) $range = "last_7_days";

            if ($range == "last_7_days" && (!$from || !$to)) {
                $from = DateManager::old_date(['week' => 1], 'Y-m-d');
                $to = DateManager::Now('Y-m-d');
            }

            if ($from && !Validation::isDate($from)) $from = '';
            if ($to && !Validation::isDate($to)) $to = '';

            if ($range == 'custom' && (!$from || !$to)) $range = 'all_times';
            if (!in_array($range, array_keys(__("admin/wanalytics/element-date-range-items")))) die("Range Error");

            $results = $this->model->get_financial_vat_accrual($from, $to, $currency);


            $result_count = 0;
            $result_total = 0;
            $result = [];
            $list = [];

            if ($results && $range == "all_times") {
                $from = $results[0]["date"];
                $to = $results[sizeof($results) - 1]["date"];
            }

            $begin = new DateTime($from);
            $end = new DateTime(DateManager::next_date([$to, 'day' => 1], 'Y-m-d'));

            $monthly_begin = new DateTime(DateManager::format('Y-m-01', $from));
            $monthly_end = new DateTime(DateManager::format('Y-m-t', $to));

            $interval = DateInterval::createFromDateString('1 month');
            $monthly_loop = new DatePeriod($monthly_begin, $interval, $monthly_end);
            $monthly_loop_size = 0;
            if ($monthly_loop) foreach ($monthly_loop as $v) $monthly_loop_size += 1;

            if ($monthly_loop_size == 1 || $monthly_loop_size > 24) $chart_view = 'striped';


            $visible_monthly_view = false;
            if ($chart_view !== 'bar' && $monthly_loop_size > 1 && $monthly_loop_size < 25) $visible_monthly_view = true;

            $this->addData("visible_monthly_view", $visible_monthly_view);


            if ($results) {
                if ($chart_view == 'bar') {
                    foreach ($monthly_loop as $dt) {
                        $mon = ___("date/month-" . strtolower($dt->format("F")));
                        $year = $dt->format("Y");
                        $result[$dt->format("Y-m")] = [
                            'name'  => $mon . " " . $year,
                            'count' => 0,
                            'total' => 0,
                        ];
                    }
                    if ($results) {
                        foreach ($results as $row) {
                            $result[DateManager::format("Y-m", $row["date"])]["count"] += $row["count"];
                            $result[DateManager::format("Y-m", $row["date"])]["total"] += $row["total"];
                            $result_count += $row["count"];
                            $result_total += $row["total"];
                        }
                    }
                    if ($result) {
                        foreach ($result as $row) {
                            $row["total"] = Money::formatter_symbol($row["total"], $currency);
                            $list[] = $row;
                        }
                    }
                }
                if ($chart_view == 'striped') {
                    $interval = DateInterval::createFromDateString('1 day');
                    $loop = new DatePeriod($begin, $interval, $end);
                    if ($loop) foreach ($loop as $dt) $result[$dt->format("Y-m-d")] = [
                        'count' => 0,
                        'total' => 0,
                    ];
                    foreach ($results as $row) {
                        $result[$row["date"]]['count'] = $row["count"];
                        $result[$row["date"]]['total'] = $row["total"];
                        $result_count += $row["count"];
                        $result_total += $row["total"];
                    }
                    if ($result) {
                        foreach ($result as $k => $v) {
                            $mon = ___("date/month-" . strtolower(DateManager::format("F", $k)));
                            $year = DateManager::format("Y", $k);
                            $day = DateManager::format("d", $k);
                            $list[] = [
                                'name'  => $day . ' ' . $mon . ' ' . $year,
                                'count' => $v["count"],
                                'total' => Money::formatter_symbol($v["total"], $currency),
                            ];
                        }
                    }
                }
            }

            $this->addData("result", $result);
            $this->addData("list", $list);
            $this->addData("result_count", $result_count);
            $this->addData("result_total", Money::formatter_symbol($result_total, $currency));

            $this->addData("filter_range", $range);
            $this->addData("filter_from", $from);
            $this->addData("filter_to", $to);
            $this->addData("filter_currency", $currency);

            $this->links["controller_filtered"] = $this->links["controller"];
            $this->links["controller_filtered"] .= "?range=" . $range;
            if ($from) $this->links["controller_filtered"] .= "&from=" . $from;
            if ($to) $this->links["controller_filtered"] .= "&to=" . $to;
            if ($currency) $this->links["controller_filtered"] .= "&currency=" . $currency;
            $this->addData("filter_chart_view", $chart_view);

            $this->addData("links", $this->links);
            $this->view->chose("admin")->render("wanalytics-financial-vat-accrual", $this->data);
        }

        private function page_tickets_overview()
        {

            $range = Filter::init("GET/range", "letters_numbers", "\-_");
            $from = Filter::init("GET/from", "numbers", "\-");
            $to = Filter::init("GET/to", "numbers", "\-");
            $chart_view = Filter::init("GET/chart-view", "letters_numbers", "\-_");

            if (!$chart_view) $chart_view = "striped";

            if (!$range) $range = "last_7_days";

            if ($range == "last_7_days" && (!$from || !$to)) {
                $from = DateManager::old_date(['week' => 1], 'Y-m-d');
                $to = DateManager::Now('Y-m-d');
            }

            if ($from && !Validation::isDate($from)) $from = '';
            if ($to && !Validation::isDate($to)) $to = '';

            if ($range == 'custom' && (!$from || !$to)) $range = 'all_times';
            if (!in_array($range, array_keys(__("admin/wanalytics/element-date-range-items")))) die('Range Error');


            $results = $this->model->get_tickets_overview($from, $to);

            $result_count = 0;
            $result = [];
            $list = [];

            if ($results && $range == "all_times") {
                $from = $results[0]["date"];
                $to = $results[sizeof($results) - 1]["date"];
            }

            $begin = new DateTime($from);
            $end = new DateTime(DateManager::next_date([$to, 'day' => 1], 'Y-m-d'));

            $monthly_begin = new DateTime(DateManager::format('Y-m-01', $from));
            $monthly_end = new DateTime(DateManager::format('Y-m-t', $to));

            $interval = DateInterval::createFromDateString('1 month');
            $monthly_loop = new DatePeriod($monthly_begin, $interval, $monthly_end);
            $monthly_loop_size = 0;
            if ($monthly_loop) foreach ($monthly_loop as $v) $monthly_loop_size += 1;

            if ($monthly_loop_size == 1 || $monthly_loop_size > 24) $chart_view = 'striped';


            $visible_monthly_view = false;
            if ($chart_view !== 'bar' && $monthly_loop_size > 1 && $monthly_loop_size < 25) $visible_monthly_view = true;

            $this->addData("visible_monthly_view", $visible_monthly_view);

            if ($results) {
                if ($chart_view == 'bar') {
                    foreach ($monthly_loop as $dt) {
                        $mon = ___("date/month-" . strtolower($dt->format("F")));
                        $year = $dt->format("Y");
                        $result[$dt->format("Y-m")] = [
                            'name'  => $mon . " " . $year,
                            'count' => 0,
                        ];
                    }
                    if ($results) {
                        foreach ($results as $row) {
                            $result[DateManager::format("Y-m", $row["date"])]["count"] += $row["count"];
                            $result_count += $row["count"];
                        }
                    }
                    if ($result) foreach ($result as $row) $list[] = $row;
                }
                if ($chart_view == 'striped') {
                    $interval = DateInterval::createFromDateString('1 day');
                    $loop = new DatePeriod($begin, $interval, $end);
                    if ($loop) foreach ($loop as $dt) $result[$dt->format("Y-m-d")] = 0;
                    foreach ($results as $row) {
                        $result[$row["date"]] = $row["count"];
                        $result_count += $row["count"];
                    }
                    if ($result) {
                        foreach ($result as $k => $v) {
                            $mon = ___("date/month-" . strtolower(DateManager::format("F", $k)));
                            $year = DateManager::format("Y", $k);
                            $day = DateManager::format("d", $k);
                            $list[] = [
                                'name'  => $day . ' ' . $mon . ' ' . $year,
                                'count' => $v,
                            ];
                        }
                    }
                }
            }

            $this->addData("result", $result);
            $this->addData("list", $list);
            $this->addData("result_count", $result_count);

            $this->addData("filter_range", $range);
            $this->addData("filter_from", $from);
            $this->addData("filter_to", $to);

            $this->links["controller_filtered"] = $this->links["controller"];
            $this->links["controller_filtered"] .= "?range=" . $range;
            if ($from) $this->links["controller_filtered"] .= "&from=" . $from;
            if ($to) $this->links["controller_filtered"] .= "&to=" . $to;
            $this->addData("filter_chart_view", $chart_view);

            $this->addData("links", $this->links);
            $this->view->chose("admin")->render("wanalytics-tickets-overview", $this->data);
        }

        private function page_tickets_product_based()
        {

            Helper::Load(["Products"]);

            $range = Filter::init("GET/range", "letters_numbers", "\-_");
            $from = Filter::init("GET/from", "numbers", "\-");
            $to = Filter::init("GET/to", "numbers", "\-");
            $chart_view = Filter::init("GET/chart-view", "letters_numbers", "\-_");
            $s_product = Filter::init("GET/product", "route", '\/');


            $product = [];
            if ($s_product) {
                $product_ex = explode("/", $s_product);
                $product_type = isset($product_ex[0]) ? Filter::letters($product_ex[0]) : false;
                $product_id = isset($product_ex[1]) ? (int)$product_ex[1] : false;
                $product = Products::get($product_type, $product_id);
                if (!$product) $s_product = '';
            }


            if (!$chart_view) $chart_view = "striped";

            if (!$range) $range = "last_7_days";

            if ($range == "last_7_days" && (!$from || !$to)) {
                $from = DateManager::old_date(['week' => 1], 'Y-m-d');
                $to = DateManager::Now('Y-m-d');
            }

            if ($from && !Validation::isDate($from)) $from = '';
            if ($to && !Validation::isDate($to)) $to = '';

            if ($range == 'custom' && (!$from || !$to)) $range = 'all_times';
            if (!in_array($range, array_keys(__("admin/wanalytics/element-date-range-items")))) die('Range Error');

            $results = $this->model->get_tickets_overview($from, $to, $product);

            $result_count = 0;
            $result = [];
            $list = [];


            if ($results && $range == "all_times") {
                $from = $results[0]["date"];
                $to = $results[sizeof($results) - 1]["date"];
            }

            $begin = new DateTime($from);
            $end = new DateTime(DateManager::next_date([$to, 'day' => 1], 'Y-m-d'));

            $monthly_begin = new DateTime(DateManager::format('Y-m-01', $from));
            $monthly_end = new DateTime(DateManager::format('Y-m-t', $to));

            $interval = DateInterval::createFromDateString('1 month');
            $monthly_loop = new DatePeriod($monthly_begin, $interval, $monthly_end);
            $monthly_loop_size = 0;
            if ($monthly_loop) foreach ($monthly_loop as $v) $monthly_loop_size += 1;

            if ($monthly_loop_size == 1 || $monthly_loop_size > 24) $chart_view = 'striped';


            $visible_monthly_view = false;
            if ($chart_view !== 'bar' && $monthly_loop_size > 1 && $monthly_loop_size < 25) $visible_monthly_view = true;

            $this->addData("visible_monthly_view", $visible_monthly_view);

            if ($results) {
                if ($chart_view == 'bar') {
                    foreach ($monthly_loop as $dt) {
                        $mon = ___("date/month-" . strtolower($dt->format("F")));
                        $year = $dt->format("Y");
                        $result[$dt->format("Y-m")] = [
                            'name'  => $mon . " " . $year,
                            'count' => 0,
                        ];
                    }
                    if ($results) {
                        foreach ($results as $row) {
                            $result[DateManager::format("Y-m", $row["date"])]["count"] += $row["count"];
                            $result_count += $row["count"];
                        }
                    }
                    if ($result) foreach ($result as $row) $list[] = $row;
                }
                if ($chart_view == 'striped') {
                    $interval = DateInterval::createFromDateString('1 day');
                    $loop = new DatePeriod($begin, $interval, $end);
                    if ($loop) foreach ($loop as $dt) $result[$dt->format("Y-m-d")] = 0;
                    foreach ($results as $row) {
                        $result[$row["date"]] = $row["count"];
                        $result_count += $row["count"];
                    }
                    if ($result) {
                        foreach ($result as $k => $v) {
                            $mon = ___("date/month-" . strtolower(DateManager::format("F", $k)));
                            $year = DateManager::format("Y", $k);
                            $day = DateManager::format("d", $k);
                            $list[] = [
                                'name'  => $day . ' ' . $mon . ' ' . $year,
                                'count' => $v,
                            ];
                        }
                    }
                }
            }

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

            $this->addData("result", $result);
            $this->addData("list", $list);
            $this->addData("result_count", $result_count);
            $this->addData("filter_chart_view", $chart_view);

            $this->addData("filter_range", $range);
            $this->addData("filter_from", $from);
            $this->addData("filter_to", $to);
            $this->addData("s_product", $s_product);

            $this->links["controller_filtered"] = $this->links["controller"];
            $this->links["controller_filtered"] .= "?range=" . $range;
            if ($from) $this->links["controller_filtered"] .= "&from=" . $from;
            if ($to) $this->links["controller_filtered"] .= "&to=" . $to;
            if ($s_product) $this->links["controller_filtered"] .= "&product=" . $s_product;

            $this->addData("links", $this->links);
            $this->view->chose("admin")->render("wanalytics-tickets-product-based", $this->data);
        }

        private function page_tickets_client_based()
        {

            $range = Filter::init("GET/range", "letters_numbers", "\-_");
            $from = Filter::init("GET/from", "numbers", "\-");
            $to = Filter::init("GET/to", "numbers", "\-");
            $chart_view = Filter::init("GET/chart-view", "letters_numbers", "\-_");

            if (!$chart_view) $chart_view = "striped";

            if (!$range) $range = "last_7_days";

            if ($range == "last_7_days" && (!$from || !$to)) {
                $from = DateManager::old_date(['week' => 1], 'Y-m-d');
                $to = DateManager::Now('Y-m-d');
            }

            if ($from && !Validation::isDate($from)) $from = '';
            if ($to && !Validation::isDate($to)) $to = '';

            if ($range == 'custom' && (!$from || !$to)) $range = 'all_times';
            if (!in_array($range, array_keys(__("admin/wanalytics/element-date-range-items")))) die('Range Error');


            $results = $this->model->get_tickets_client_based($from, $to);

            $result_count = 0;
            $result = [];

            if ($results && $range == "all_times") {
                $from = $results[0]["date"];
                $to = $results[sizeof($results) - 1]["date"];
            }

            if ($results) {
                Utility::sksort($results, 'count');
                foreach ($results as $row) {
                    $result_count += $row["count"];
                    $result[] = [
                        'count'       => $row["count"],
                        'name'        => $row["full_name"],
                        'detail_link' => $this->AdminCRLink("users-2", ["detail", $row["id"]]),
                    ];
                }
            }

            $this->addData("result", $result);
            $this->addData("result_count", $result_count);

            $this->addData("filter_range", $range);
            $this->addData("filter_from", $from);
            $this->addData("filter_to", $to);

            $this->links["controller_filtered"] = $this->links["controller"];
            $this->links["controller_filtered"] .= "?range=" . $range;
            if ($from) $this->links["controller_filtered"] .= "&from=" . $from;
            if ($to) $this->links["controller_filtered"] .= "&to=" . $to;
            $this->addData("filter_chart_view", $chart_view);

            $this->addData("links", $this->links);
            $this->view->chose("admin")->render("wanalytics-tickets-client-based", $this->data);
        }

    }